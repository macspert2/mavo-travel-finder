/* global tvfFrontend */
( function () {
	'use strict';

	const wrap = document.getElementById( 'tvf-wrap' );
	if ( ! wrap ) return;

	const restUrl  = tvfFrontend.restUrl;
	const restNonce = tvfFrontend.nonce;
	const lang     = wrap.dataset.lang || 'fr';
	const results  = document.getElementById( 'tvf-results' );
	const summary  = document.getElementById( 'tvf-summary' );
	const resetBtn = document.getElementById( 'tvf-reset' );

	// -------------------------------------------------------------------------
	// Parse the current filter state from the URL
	// -------------------------------------------------------------------------

	function getSelected() {
		const url = new URL( window.location.href );
		const f   = url.searchParams.get( 'f' ) || '';
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

	// -------------------------------------------------------------------------
	// Fetch and swap results
	// -------------------------------------------------------------------------

	let controller = null;

	function loadResults( slugs ) {
		if ( controller ) controller.abort();
		controller = new AbortController();

		results.classList.add( 'tvf-loading' );

		const f   = slugs.join( ',' );
		const url = restUrl + '?f=' + encodeURIComponent( f ) + '&lang=' + lang;

		fetch( url, {
			signal:  controller.signal,
			headers: { 'X-WP-Nonce': restNonce },
		} )
			.then( r => r.json() )
			.then( function ( data ) {
				results.innerHTML   = data.html || '';
				results.classList.remove( 'tvf-loading' );
				controller = null;
				updateResetBtn( slugs );
			} )
			.catch( function ( err ) {
				if ( err.name !== 'AbortError' ) {
					results.classList.remove( 'tvf-loading' );
				}
			} );
	}

	// -------------------------------------------------------------------------
	// Summary line
	// -------------------------------------------------------------------------

	function updateSummary( slugs ) {
		if ( ! slugs.length ) {
			summary.innerHTML = '<span class="tvf-summary-empty">'
				+ ( summary.dataset.emptyText || 'Aucun filtre sélectionné — destinations populaires.' )
				+ '</span>';
			return;
		}
		const labels = [];
		wrap.querySelectorAll( '.tvf-chip.is-on' ).forEach( chip => labels.push( chip.textContent.trim() ) );
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

		// Update every chip's href to reflect the new selection
		wrap.querySelectorAll( '.tvf-chip' ).forEach( c => {
			const s    = c.dataset.slug;
			const on   = selected.includes( s );
			const next = on
				? selected.filter( x => x !== s )
				: [ ...selected, s ];
			c.href = buildUrl( next );
		} );

		// Push URL
		history.pushState( { f: selected.join( ',' ) }, '', buildUrl( selected ) );

		updateSummary( selected );
		loadResults( selected );
	} );

	// Handle browser back/forward
	window.addEventListener( 'popstate', function () {
		const selected = getSelected();

		// Sync chip states
		wrap.querySelectorAll( '.tvf-chip' ).forEach( c => {
			const on = selected.includes( c.dataset.slug );
			c.classList.toggle( 'is-on', on );
			c.setAttribute( 'aria-checked', on ? 'true' : 'false' );
		} );

		updateSummary( selected );
		loadResults( selected );
	} );

	// Reset button (when rendered as <button> by SSR for the no-selection case)
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

} )();
