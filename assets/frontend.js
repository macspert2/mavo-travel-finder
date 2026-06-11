/* global tvfFrontend */
( function () {
	'use strict';

	const wrap = document.getElementById( 'tvf-wrap' );
	if ( ! wrap ) return;

	const restUrl      = tvfFrontend.restUrl;
	const restNonce    = tvfFrontend.nonce;
	const lang         = wrap.dataset.lang || 'fr';
	const grid         = document.getElementById( 'tvf-cards-grid' );
	const summary      = document.getElementById( 'tvf-summary' );
	const resetBtn     = document.getElementById( 'tvf-reset' );
	const loadMoreWrap = document.getElementById( 'tvf-load-more-wrap' );
	const loadMoreBtn  = document.getElementById( 'tvf-load-more' );

	const BATCH = 42;
	let   nextOffset = BATCH; // offset for the next "load more" fetch

	// -------------------------------------------------------------------------
	// URL helpers
	// -------------------------------------------------------------------------

	function getSelected() {
		const f = new URL( window.location.href ).searchParams.get( 'f' ) || '';
		return f ? f.split( ',' ).filter( Boolean ) : [];
	}

	function baseUrl() {
		const u = new URL( window.location.href );
		u.searchParams.delete( 'f' );
		return u.toString();
	}

	function buildUrl( slugs ) {
		const u = new URL( window.location.href );
		if ( slugs.length ) {
			u.searchParams.set( 'f', slugs.join( ',' ) );
		} else {
			u.searchParams.delete( 'f' );
		}
		return u.toString();
	}

	function restFetchUrl( slugs, offset ) {
		const u = new URL( restUrl );
		u.searchParams.set( 'f',      slugs.join( ',' ) );
		u.searchParams.set( 'lang',   lang );
		u.searchParams.set( 'offset', String( offset ) );
		return u.toString();
	}

	// -------------------------------------------------------------------------
	// Load-more button state
	// -------------------------------------------------------------------------

	function setLoadMore( hasMore, offset ) {
		nextOffset = offset;
		if ( ! loadMoreWrap ) return;
		if ( hasMore ) {
			loadMoreWrap.removeAttribute( 'hidden' );
		} else {
			loadMoreWrap.setAttribute( 'hidden', '' );
		}
	}

	// -------------------------------------------------------------------------
	// Fetch helpers
	// -------------------------------------------------------------------------

	let controller = null;

	/** Replace grid content with a fresh first page. */
	function loadResults( slugs ) {
		if ( controller ) controller.abort();
		controller = new AbortController();

		grid.closest( '.tvf-results' ).classList.add( 'tvf-loading' );
		setLoadMore( false, BATCH ); // hide button while loading

		fetch( restFetchUrl( slugs, 0 ), {
			signal:  controller.signal,
			headers: { 'X-WP-Nonce': restNonce },
		} )
			.then( r => r.json() )
			.then( function ( data ) {
				grid.innerHTML = data.html || '';
				grid.closest( '.tvf-results' ).classList.remove( 'tvf-loading' );
				setLoadMore( data.has_more, BATCH );
				controller = null;
				updateResetBtn( slugs );
			} )
			.catch( function ( err ) {
				if ( err.name !== 'AbortError' ) {
					grid.closest( '.tvf-results' ).classList.remove( 'tvf-loading' );
				}
			} );
	}

	/** Append the next page of cards to the existing grid. */
	function loadMore( slugs, offset ) {
		if ( ! loadMoreBtn ) return;

		const originalLabel = loadMoreBtn.textContent;
		loadMoreBtn.disabled    = true;
		loadMoreBtn.textContent = '…';

		fetch( restFetchUrl( slugs, offset ), {
			headers: { 'X-WP-Nonce': restNonce },
		} )
			.then( r => r.json() )
			.then( function ( data ) {
				if ( data.html ) {
					grid.insertAdjacentHTML( 'beforeend', data.html );
				}
				setLoadMore( data.has_more, offset + BATCH );
				loadMoreBtn.disabled    = false;
				loadMoreBtn.textContent = originalLabel;
			} )
			.catch( function () {
				loadMoreBtn.disabled    = false;
				loadMoreBtn.textContent = originalLabel;
			} );
	}

	// -------------------------------------------------------------------------
	// Summary line
	// -------------------------------------------------------------------------

	function updateSummary( slugs ) {
		if ( ! slugs.length ) {
			summary.innerHTML = '<span class="tvf-summary-empty">'
				+ escHtml( summary.dataset.emptyText || 'Aucun filtre sélectionné — destinations populaires.' )
				+ '</span>';
			return;
		}
		const labels = [];
		wrap.querySelectorAll( '.tvf-chip.is-on' ).forEach( c => labels.push( c.textContent.trim() ) );
		summary.innerHTML = '<strong>Votre sélection : </strong>' + escHtml( labels.join( ', ' ) );
	}

	function updateResetBtn( slugs ) {
		if ( ! resetBtn ) return;
		if ( slugs.length ) {
			resetBtn.removeAttribute( 'hidden' );
			if ( resetBtn.tagName === 'A' ) resetBtn.href = baseUrl();
		} else {
			resetBtn.setAttribute( 'hidden', '' );
		}
	}

	function escHtml( str ) {
		return str.replace( /&/g, '&amp;' ).replace( /</g, '&lt;' ).replace( />/g, '&gt;' );
	}

	// -------------------------------------------------------------------------
	// Chip click interception
	// -------------------------------------------------------------------------

	wrap.addEventListener( 'click', function ( e ) {
		const chip = e.target.closest( '.tvf-chip' );
		if ( ! chip ) return;

		e.preventDefault();

		const slug     = chip.dataset.slug;
		let   selected = getSelected();
		const idx      = selected.indexOf( slug );

		if ( idx >= 0 ) {
			selected.splice( idx, 1 );
		} else {
			selected.push( slug );
		}

		// Update chip states immediately
		wrap.querySelectorAll( '.tvf-chip[data-slug="' + slug + '"]' ).forEach( c => {
			c.classList.toggle( 'is-on', idx < 0 );
			c.setAttribute( 'aria-checked', idx < 0 ? 'true' : 'false' );
		} );

		// Update every chip's href
		wrap.querySelectorAll( '.tvf-chip' ).forEach( c => {
			const s    = c.dataset.slug;
			const on   = selected.includes( s );
			const next = on
				? selected.filter( x => x !== s )
				: [ ...selected, s ];
			c.href = buildUrl( next );
		} );

		history.pushState( { f: selected.join( ',' ) }, '', buildUrl( selected ) );
		updateSummary( selected );
		loadResults( selected );
	} );

	// Browser back/forward
	window.addEventListener( 'popstate', function () {
		const selected = getSelected();
		wrap.querySelectorAll( '.tvf-chip' ).forEach( c => {
			const on = selected.includes( c.dataset.slug );
			c.classList.toggle( 'is-on', on );
			c.setAttribute( 'aria-checked', on ? 'true' : 'false' );
		} );
		updateSummary( selected );
		loadResults( selected );
	} );

	// Reset button (when rendered as <button> for the no-selection state)
	if ( resetBtn && resetBtn.tagName === 'BUTTON' ) {
		resetBtn.addEventListener( 'click', function () {
			history.pushState( { f: '' }, '', baseUrl() );
			wrap.querySelectorAll( '.tvf-chip' ).forEach( c => {
				c.classList.remove( 'is-on' );
				c.setAttribute( 'aria-checked', 'false' );
			} );
			updateSummary( [] );
			loadResults( [] );
		} );
	}

	// -------------------------------------------------------------------------
	// Load more
	// -------------------------------------------------------------------------

	if ( loadMoreBtn ) {
		loadMoreBtn.addEventListener( 'click', function () {
			loadMore( getSelected(), nextOffset );
		} );
	}

} )();
