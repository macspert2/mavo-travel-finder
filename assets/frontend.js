/* global tvfFrontend */
( function () {
	'use strict';

	const wrap = document.getElementById( 'tvf-wrap' );
	if ( ! wrap ) return;

	const restUrl      = tvfFrontend.restUrl;
	const restNonce    = tvfFrontend.nonce;
	const lang         = wrap.dataset.lang || 'fr';
	const results      = document.getElementById( 'tvf-results' );
	const summary      = document.getElementById( 'tvf-summary' );
	const summaryText  = document.getElementById( 'tvf-summary-text' );
	const resetBtn     = document.getElementById( 'tvf-reset' );
	const loadMoreWrap = document.getElementById( 'tvf-load-more-wrap' );
	const loadMoreBtn  = document.getElementById( 'tvf-load-more' );

	const BATCH = 42;
	let   nextOffset = BATCH;

	// -------------------------------------------------------------------------
	// Cookie helpers — remember last filter state for returning visitors
	// -------------------------------------------------------------------------

	const COOKIE_NAME = 'tvf_filters';
	const COOKIE_DAYS = 30;

	function setCookie( name, value, days ) {
		const exp = new Date( Date.now() + days * 864e5 ).toUTCString();
		document.cookie = name + '=' + encodeURIComponent( value )
			+ '; expires=' + exp + '; path=/; SameSite=Lax';
	}

	function getCookie( name ) {
		const match = document.cookie.match( '(?:^|; )' + name + '=([^;]*)' );
		return match ? decodeURIComponent( match[ 1 ] ) : '';
	}

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

	/**
	 * Replace the entire results area with a fresh first page.
	 * data.html (offset=0) includes the <div class="tvf-cards-grid"> wrapper,
	 * so setting results.innerHTML restores the full correct DOM structure.
	 */
	function loadResults( slugs ) {
		if ( controller ) controller.abort();
		controller = new AbortController();

		results.classList.add( 'tvf-loading' );
		setLoadMore( false, BATCH );

		fetch( restFetchUrl( slugs, 0 ), {
			signal:  controller.signal,
			headers: { 'X-WP-Nonce': restNonce },
		} )
			.then( r => r.json() )
			.then( function ( data ) {
				results.innerHTML = data.html || '';
				results.classList.remove( 'tvf-loading' );
				setLoadMore( data.has_more, BATCH );
				controller = null;
				updateResetBtn( slugs );
				updateCount( data.total_count );
				if ( Array.isArray( data.dead_slugs ) ) {
					updateDeadChips( data.dead_slugs );
				}
			} )
			.catch( function ( err ) {
				if ( err.name !== 'AbortError' ) {
					results.classList.remove( 'tvf-loading' );
				}
			} );
	}

	/**
	 * Append the next page of bare <article> elements to the existing grid.
	 * data.html (offset>0) contains only <article> elements, no wrapper.
	 */
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
					const grid = results.querySelector( '.tvf-cards-grid' );
					if ( grid ) {
						grid.insertAdjacentHTML( 'beforeend', data.html );
					}
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
	// Dead-chip state
	// -------------------------------------------------------------------------

	function updateDeadChips( deadSlugs ) {
		wrap.querySelectorAll( '.tvf-chip' ).forEach( function ( chip ) {
			const isDead = deadSlugs.includes( chip.dataset.slug ) && ! chip.classList.contains( 'is-on' );
			chip.classList.toggle( 'is-dead', isDead );
			chip.style.opacity = isDead ? '0.35' : '';
			chip.style.cursor  = isDead ? 'not-allowed' : '';
			chip.setAttribute( 'aria-disabled', isDead ? 'true' : 'false' );
			if ( isDead ) {
				chip.setAttribute( 'tabindex', '-1' );
			} else {
				chip.removeAttribute( 'tabindex' );
			}
		} );
	}

	// -------------------------------------------------------------------------
	// Summary line
	// -------------------------------------------------------------------------

	function updateSummary( slugs ) {
		const el = summaryText || summary;
		if ( ! slugs.length ) {
			el.innerHTML = '<span class="tvf-summary-empty">'
				+ escHtml( summary.dataset.emptyText || 'Aucun filtre sélectionné — destinations populaires.' )
				+ '</span>';
			return;
		}
		const labels = [];
		wrap.querySelectorAll( '.tvf-chip.is-on' ).forEach( c => labels.push( c.textContent.trim() ) );
		el.innerHTML = '<strong>Votre sélection : </strong>' + escHtml( labels.join( ', ' ) );
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

	function formatCount( total, hasFilters ) {
		if ( total === 0 ) {
			return lang === 'en' ? 'No matching ideas.'
			     : lang === 'de' ? 'Keine passenden Ideen.'
			     : 'Aucune idée ne correspond à cette sélection.';
		}
		var noun = total === 1
			? ( lang === 'en' ? 'idea found' : lang === 'de' ? 'Idee gefunden' : 'idée trouvée' )
			: ( lang === 'en' ? 'ideas found' : lang === 'de' ? 'Ideen gefunden' : 'idées trouvées' );
		var suffix = hasFilters
			? ( lang === 'en' ? ' for your selection' : lang === 'de' ? ' für Ihre Auswahl' : ' pour votre sélection' )
			: '';
		return total + ' ' + noun + suffix;
	}

	function updateCount( total ) {
		var countEl = document.getElementById( 'tvf-count' );
		if ( ! countEl ) return;
		if ( ! getSelected().length ) {
			countEl.setAttribute( 'hidden', '' );
			return;
		}
		if ( total === undefined || total === null ) return;
		countEl.removeAttribute( 'hidden' );
		countEl.textContent = formatCount( total, true );
	}

	// -------------------------------------------------------------------------
	// Chip click interception
	// -------------------------------------------------------------------------

	wrap.addEventListener( 'click', function ( e ) {
		const chip = e.target.closest( '.tvf-chip' );
		if ( ! chip ) return;
		if ( chip.classList.contains( 'is-dead' ) ) return;

		e.preventDefault();

		const slug     = chip.dataset.slug;
		let   selected = getSelected();
		const idx      = selected.indexOf( slug );

		if ( idx >= 0 ) {
			selected.splice( idx, 1 );
		} else {
			selected.push( slug );
		}

		wrap.querySelectorAll( '.tvf-chip[data-slug="' + slug + '"]' ).forEach( c => {
			c.classList.toggle( 'is-on', idx < 0 );
			c.setAttribute( 'aria-checked', idx < 0 ? 'true' : 'false' );
		} );

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
		setCookie( COOKIE_NAME, selected.join( ',' ), COOKIE_DAYS );
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
		setCookie( COOKIE_NAME, selected.join( ',' ), COOKIE_DAYS );
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
			setCookie( COOKIE_NAME, '', COOKIE_DAYS );
		} );
	}

	// Reset button (when rendered as <a> link — initial SSR with active filters).
	// Clear the cookie before the navigation so restore logic doesn't re-apply on reload.
	if ( resetBtn && resetBtn.tagName === 'A' ) {
		resetBtn.addEventListener( 'click', function () {
			setCookie( COOKIE_NAME, '', COOKIE_DAYS );
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

	// -------------------------------------------------------------------------
	// Share button — copy URL to clipboard, show tooltip
	// -------------------------------------------------------------------------

	const shareBtn     = document.getElementById( 'tvf-share' );
	const shareTooltip = document.getElementById( 'tvf-share-tooltip' );

	if ( shareBtn && shareTooltip ) {
		let hideTimer    = null;
		let outsideClick = null;

		function hideTooltip() {
			shareTooltip.setAttribute( 'hidden', '' );
			shareBtn.classList.remove( 'is-copied' );
			clearTimeout( hideTimer );
			if ( outsideClick ) {
				document.removeEventListener( 'click', outsideClick );
				outsideClick = null;
			}
		}

		function showTooltip() {
			shareTooltip.removeAttribute( 'hidden' );
			shareBtn.classList.add( 'is-copied' );

			// Auto-hide after 3 s
			clearTimeout( hideTimer );
			hideTimer = setTimeout( hideTooltip, 3000 );

			// Hide on the next click anywhere outside the button
			if ( outsideClick ) document.removeEventListener( 'click', outsideClick );
			outsideClick = function () { hideTooltip(); };
			// Defer so this click event doesn't immediately trigger dismissal
			setTimeout( function () {
				document.addEventListener( 'click', outsideClick, { once: true } );
			}, 0 );
		}

		shareBtn.addEventListener( 'click', function ( e ) {
			e.stopPropagation();
			// Show tooltip immediately — don't wait on the async clipboard promise
			showTooltip();
			if ( navigator.clipboard && navigator.clipboard.writeText ) {
				navigator.clipboard.writeText( window.location.href ).catch( function () {} );
			} else {
				// execCommand fallback (older Safari / strict CSP)
				try {
					const ta = document.createElement( 'textarea' );
					ta.value = window.location.href;
					ta.style.cssText = 'position:fixed;top:0;left:0;opacity:0;pointer-events:none;';
					document.body.appendChild( ta );
					ta.focus();
					ta.select();
					document.execCommand( 'copy' );
					document.body.removeChild( ta );
				} catch ( _e ) {}
			}
		} );
	}

	// -------------------------------------------------------------------------
	// Restore last filter state on page load (only when URL has no f= param)
	// -------------------------------------------------------------------------

	if ( ! new URL( window.location.href ).searchParams.has( 'f' ) ) {
		const saved = getCookie( COOKIE_NAME );
		if ( saved ) {
			const slugs = saved.split( ',' ).filter( Boolean );
			if ( slugs.length ) {
				history.replaceState( { f: saved }, '', buildUrl( slugs ) );
				wrap.querySelectorAll( '.tvf-chip' ).forEach( function ( c ) {
					const on = slugs.includes( c.dataset.slug );
					c.classList.toggle( 'is-on', on );
					c.setAttribute( 'aria-checked', on ? 'true' : 'false' );
					const next = on
						? slugs.filter( x => x !== c.dataset.slug )
						: [ ...slugs, c.dataset.slug ];
					c.href = buildUrl( next );
				} );
				updateSummary( slugs );
				loadResults( slugs );
			}
		}
	}

} )();
