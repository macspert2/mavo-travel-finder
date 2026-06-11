/* global tvfAdmin */
( function ( $ ) {
	'use strict';

	const { ajaxUrl, nonce, restNonce, restBase, i18n } = tvfAdmin;

	// -------------------------------------------------------------------------
	// Generic autocomplete
	// -------------------------------------------------------------------------

	function setupAutocomplete( cfg ) {
		// cfg: { inputId, hiddenId, suggestionsId, lang, onSelect }
		const $input  = $( '#' + cfg.inputId );
		const $hidden = $( '#' + cfg.hiddenId );
		const $list   = $( '#' + cfg.suggestionsId );
		let timer;

		$input.on( 'input', function () {
			clearTimeout( timer );
			const q = $input.val().trim();
			if ( q.length < 2 ) { $list.empty().prop( 'hidden', true ); return; }

			timer = setTimeout( function () {
				const lang = cfg.lang() || 'fr';
				fetch( restBase + '/search-posts?q=' + encodeURIComponent( q ) + '&lang=' + lang, {
					headers: { 'X-WP-Nonce': restNonce },
				} )
					.then( r => r.json() )
					.then( function ( data ) {
						$list.empty();
						if ( ! Array.isArray( data ) || ! data.length ) {
							$list.prop( 'hidden', true );
							return;
						}
						data.forEach( function ( item ) {
							$( '<li>' )
								.text( item.title )
								.attr( 'data-id', item.id )
								.appendTo( $list );
						} );
						$list.prop( 'hidden', false );
					} );
			}, 250 );
		} );

		$list.on( 'click', 'li', function () {
			const id    = $( this ).data( 'id' );
			const title = $( this ).text();
			$input.val( title );
			$hidden.val( id );
			$list.empty().prop( 'hidden', true );
			cfg.onSelect( id );
		} );

		$( document ).on( 'click', function ( e ) {
			if ( ! $( e.target ).closest( '#' + cfg.inputId + ',#' + cfg.suggestionsId ).length ) {
				$list.empty().prop( 'hidden', true );
			}
		} );
	}

	// -------------------------------------------------------------------------
	// Segmented controls (shared between edit page and meta box)
	// -------------------------------------------------------------------------

	$( document ).on( 'click', '.tvf-seg', function () {
		const $btn   = $( this );
		const $group = $btn.closest( '.tvf-segmented' );
		$group.find( '.tvf-seg' ).removeClass( 'is-active' );
		$btn.addClass( 'is-active' );
		// Keep hidden input in sync (meta box save-via-form)
		const slug = $group.data( 'slug' );
		$( 'input[name="tvf_weight[' + slug + ']"]' ).val( $btn.data( 'val' ) );
	} );

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	function getGridValues() {
		const weights = {};
		$( '.tvf-weights-table .tvf-segmented' ).each( function () {
			const slug = $( this ).data( 'slug' );
			const val  = $( this ).find( '.tvf-seg.is-active' ).data( 'val' );
			weights[ slug ] = val !== undefined ? val : 0;
		} );
		return weights;
	}

	function setGridValues( weights ) {
		$( '.tvf-weights-table .tvf-segmented' ).each( function () {
			const slug = $( this ).data( 'slug' );
			const val  = weights[ slug ] !== undefined ? parseInt( weights[ slug ], 10 ) : 0;
			$( this ).find( '.tvf-seg' ).removeClass( 'is-active' );
			$( this ).find( '.tvf-seg[data-val="' + val + '"]' ).addClass( 'is-active' );
		} );
	}

	function setTemplateValues( weights ) {
		$( '.tvf-tpl-val' ).each( function () {
			const slug = $( this ).data( 'slug' );
			$( this ).text( weights[ slug ] !== undefined ? weights[ slug ] : '—' );
		} );
	}

	function fetchWeights( postId, lang, cb ) {
		$.ajax( {
			url: ajaxUrl,
			data: { action: 'tvf_get_weights', nonce, post_id: postId, lang },
			success: function ( r ) { if ( r.success ) cb( r.data ); },
		} );
	}

	function showMsg( text, isError ) {
		const $msg = $( '#tvf-save-msg' );
		$msg.text( text ).toggleClass( 'is-error', !! isError );
		setTimeout( function () { $msg.text( '' ).removeClass( 'is-error' ); }, 3000 );
	}

	// -------------------------------------------------------------------------
	// Main edit-weights page
	// -------------------------------------------------------------------------

	if ( $( '#tvf-save-btn' ).length ) {
		const lang = function () { return $( '#tvf-lang' ).val() || 'fr'; };
		let templateWeights = {};

		// Template picker
		setupAutocomplete( {
			inputId:       'tvf-template-search',
			hiddenId:      'tvf-template-id',
			suggestionsId: 'tvf-template-suggestions',
			lang,
			onSelect: function ( id ) {
				fetchWeights( id, lang(), function ( w ) {
					templateWeights = w;
					setTemplateValues( w );
					$( '#tvf-copy-btn' ).prop( 'disabled', false );
				} );
			},
		} );

		// Target picker
		setupAutocomplete( {
			inputId:       'tvf-target-search',
			hiddenId:      'tvf-target-id',
			suggestionsId: 'tvf-target-suggestions',
			lang,
			onSelect: function ( id ) {
				fetchWeights( id, lang(), function ( w ) {
					setGridValues( w );
					$( '#tvf-reset-btn, #tvf-save-btn' ).prop( 'disabled', false );

					// Show badge
					const hasSome = Object.values( w ).some( v => parseInt( v, 10 ) > 0 );
					const $badge  = $( '#tvf-target-badge' );
					$badge.text( hasSome ? 'Complet' : 'Vide' )
						.toggleClass( 'tvf-badge-complete', hasSome )
						.toggleClass( 'tvf-badge-empty', ! hasSome )
						.prop( 'hidden', false );
				} );
			},
		} );

		// Copy from template
		$( '#tvf-copy-btn' ).on( 'click', function () {
			setGridValues( templateWeights );
			showMsg( i18n.copied );
		} );

		// Reset grid to all zeros
		$( '#tvf-reset-btn' ).on( 'click', function () {
			setGridValues( {} );
		} );

		// Save
		$( '#tvf-save-btn' ).on( 'click', function () {
			const postId = $( '#tvf-target-id' ).val();
			if ( ! postId ) { showMsg( i18n.noPost, true ); return; }

			const $btn = $( this ).prop( 'disabled', true ).text( '…' );

			$.ajax( {
				url:    ajaxUrl,
				method: 'POST',
				data: {
					action:  'tvf_save',
					nonce,
					post_id: postId,
					lang:    lang(),
					weights: getGridValues(),
				},
				success: function ( r ) {
					if ( r.success ) {
						showMsg( i18n.saved );
					} else {
						showMsg( i18n.error, true );
					}
				},
				error: function () { showMsg( i18n.error, true ); },
				complete: function () {
					$btn.prop( 'disabled', false ).text( tvfAdmin.i18n.savedBtn || 'Enregistrer' );
				},
			} );
		} );
	}

	// -------------------------------------------------------------------------
	// Meta box — copy from another post
	// -------------------------------------------------------------------------

	if ( $( '.tvf-metabox' ).length ) {
		const $mb   = $( '.tvf-metabox' );
		const mbLang = function () { return $mb.data( 'lang' ) || 'fr'; };

		setupAutocomplete( {
			inputId:       'tvf-mb-template-search',
			hiddenId:      'tvf-mb-template-id',
			suggestionsId: 'tvf-mb-template-suggestions',
			lang:          mbLang,
			onSelect: function () {
				$( '#tvf-mb-copy-btn' ).prop( 'disabled', false );
			},
		} );

		$( '#tvf-mb-copy-btn' ).on( 'click', function () {
			const srcId = $( '#tvf-mb-template-id' ).val();
			if ( ! srcId ) return;
			fetchWeights( srcId, mbLang(), function ( w ) {
				// Apply to meta-box segmented controls
				$mb.find( '.tvf-segmented' ).each( function () {
					const slug = $( this ).data( 'slug' );
					const val  = w[ slug ] !== undefined ? parseInt( w[ slug ], 10 ) : 0;
					$( this ).find( '.tvf-seg' ).removeClass( 'is-active' );
					$( this ).find( '.tvf-seg[data-val="' + val + '"]' ).addClass( 'is-active' );
					$( 'input[name="tvf_weight[' + slug + ']"]' ).val( val );
				} );
			} );
		} );
	}

} )( jQuery );
