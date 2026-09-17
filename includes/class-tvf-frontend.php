<?php
defined( 'ABSPATH' ) || exit;

class TVF_Frontend {

	public static function init(): void {
		add_shortcode( 'travel_finder',    [ __CLASS__, 'render_shortcode' ] );
		add_action( 'rest_api_init',       [ __CLASS__, 'register_rest_routes' ] );
		add_action( 'wp_enqueue_scripts',  [ __CLASS__, 'enqueue_assets' ] );

		// SEO for filtered finder URLs (?f=…): canonical → clean permalink, robots → noindex,follow.
		// Canonical is filtered on both integration points; only one of them is ever live
		// (Yoast unhooks core's rel_canonical when it is active), so no duplicate tag can appear.
		add_filter( 'get_canonical_url', [ __CLASS__, 'filter_canonical_url' ], 10, 2 );
		add_filter( 'wpseo_canonical',   [ __CLASS__, 'filter_seo_plugin_canonical' ] );
		// Yoast filters wp_robots at PHP_INT_MAX - 10 and explicitly leaves the last slots free.
		add_filter( 'wp_robots',         [ __CLASS__, 'filter_robots' ], PHP_INT_MAX );
	}

	// -------------------------------------------------------------------------
	// Assets
	// -------------------------------------------------------------------------

	public static function enqueue_assets(): void {
		global $post;
		if ( ! self::is_travel_finder_post( $post ) ) {
			return;
		}

		// mtime rather than TVF_VERSION: a CSS or JS edit that does not also bump
		// the constant would otherwise keep serving the previous file through
		// Autoptimize, Cloudflare and the browser alike.
		$css = TVF_PLUGIN_DIR . 'assets/frontend.css';
		$js  = TVF_PLUGIN_DIR . 'assets/frontend.js';

		wp_enqueue_style( 'tvf-frontend', TVF_PLUGIN_URL . 'assets/frontend.css', [], file_exists( $css ) ? filemtime( $css ) : TVF_VERSION );
		wp_enqueue_script( 'tvf-frontend', TVF_PLUGIN_URL . 'assets/frontend.js', [], file_exists( $js ) ? filemtime( $js ) : TVF_VERSION, true );
		$lang = self::current_lang();

		// No REST nonce: /tvf/v1/results is public read-only (permission_callback __return_true),
		// and a cached nonce goes stale for visitors served from a page cache, breaking the finder.
		//
		// The i18n block is the only copy of these strings the script has: frontend.js
		// rebuilds the summary and count lines after every chip click, and used to carry
		// its own hardcoded French for them, which reverted a translated page to French
		// on the first interaction.
		wp_localize_script( 'tvf-frontend', 'tvfFrontend', [
			'restUrl' => rest_url( 'tvf/v1/results' ),
			// The page size, so the script does not keep a second copy of a
			// number the SQL LIMIT also depends on. See TVF_Store::BATCH.
			'batch'   => TVF_Store::BATCH,
			'i18n'    => [
				'summaryPrefix' => self::text( 'summary_prefix', $lang ),
				'summaryEmpty'  => self::text( 'summary_empty', $lang ),
				'count'         => self::count_strings( $lang ),
			],
		] );
	}

	// -------------------------------------------------------------------------
	// SEO — filtered finder URLs (?f=…)
	// -------------------------------------------------------------------------

	/**
	 * WP core canonical (rel_canonical / wp_get_canonical_url).
	 * Inactive while an SEO plugin that unhooks rel_canonical (e.g. Yoast) is running.
	 */
	public static function filter_canonical_url( $canonical, $post ) {
		return self::is_filtered_finder_view( $post ) ? get_permalink( $post ) : $canonical;
	}

	/**
	 * Yoast SEO canonical ('wpseo_canonical'). Yoast intentionally outputs no canonical
	 * on noindex pages — an empty value is left untouched so that policy still wins.
	 */
	public static function filter_seo_plugin_canonical( $canonical ) {
		if ( '' === $canonical || ! is_string( $canonical ) ) {
			return $canonical;
		}

		$post = get_queried_object();

		return self::is_filtered_finder_view( $post ) ? get_permalink( $post ) : $canonical;
	}

	/**
	 * Robots directives, via core's wp_robots array (which Yoast also feeds, earlier).
	 * Only the directives that conflict with noindex,follow are dropped; max-image-preview
	 * and friends are left in place.
	 */
	public static function filter_robots( array $robots ): array {
		if ( ! self::is_filtered_finder_view( get_queried_object() ) ) {
			return $robots;
		}

		unset( $robots['index'], $robots['nofollow'] );

		return array_merge( [ 'noindex' => true, 'follow' => true ], $robots );
	}

	private static function is_travel_finder_post( $post ): bool {
		return is_a( $post, 'WP_Post' )
			&& has_shortcode( $post->post_content, 'travel_finder' );
	}

	/** The currently requested page is a Travel Finder page showing an actual filter selection. */
	private static function is_filtered_finder_view( $post ): bool {
		return self::is_travel_finder_post( $post )
			&& get_queried_object_id() === (int) $post->ID
			&& self::has_filter_request();
	}

	/** Filters requested through ?f=, parsed exactly like the finder itself parses them. */
	private static function requested_filters(): array {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- public read-only view state.
		$f = isset( $_GET['f'] ) ? sanitize_text_field( wp_unslash( $_GET['f'] ) ) : '';

		return self::parse_filter_param( $f );
	}

	private static function has_filter_request(): bool {
		return ! empty( self::requested_filters() );
	}

	// -------------------------------------------------------------------------
	// REST routes
	// -------------------------------------------------------------------------

	public static function register_rest_routes(): void {
		register_rest_route( 'tvf/v1', '/results', [
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => [ __CLASS__, 'rest_results' ],
			'permission_callback' => '__return_true',
			'args'                => [
				'f'      => [ 'sanitize_callback' => 'sanitize_text_field', 'default' => '' ],
				'lang'   => [ 'sanitize_callback' => 'sanitize_key',        'default' => 'fr' ],
				'offset' => [ 'sanitize_callback' => 'absint',              'default' => 0 ],
			],
		] );

		register_rest_route( 'tvf/v1', '/search-posts', [
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => [ __CLASS__, 'rest_search_posts' ],
			'permission_callback' => static fn() => current_user_can( 'edit_posts' ),
			'args'                => [
				'q'    => [ 'sanitize_callback' => 'sanitize_text_field', 'default' => '' ],
				'lang' => [ 'sanitize_callback' => 'sanitize_key',        'default' => 'fr' ],
			],
		] );
	}

	public static function rest_results( WP_REST_Request $request ): WP_REST_Response {
		$lang   = $request->get_param( 'lang' );
		$offset = (int) $request->get_param( 'offset' );
		if ( ! in_array( $lang, [ 'fr', 'en', 'de' ], true ) ) {
			$lang = 'fr';
		}
		$slugs  = self::parse_filter_param( $request->get_param( 'f' ) );
		$result = self::render_cards( $slugs, $lang, $offset );

		if ( $offset === 0 ) {
			$result['dead_slugs'] = TVF_Store::compute_dead_slugs( $lang, $slugs );
		}

		return new WP_REST_Response( $result, 200 );
	}

	public static function rest_search_posts( WP_REST_Request $request ): WP_REST_Response {
		$q    = $request->get_param( 'q' );
		$lang = $request->get_param( 'lang' );

		// Validated the same way rest_results() validates it: an unrecognised
		// language handed to Polylang returns nothing at all, which reads as
		// "no posts match" rather than as a bad parameter.
		if ( ! in_array( $lang, [ 'fr', 'en', 'de' ], true ) ) {
			$lang = 'fr';
		}

		$args = [
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'posts_per_page' => 10,
			's'              => $q,
		];

		if ( function_exists( 'pll_get_post' ) ) {
			$args['lang'] = $lang;
		}

		$posts = get_posts( $args );
		$data  = array_map( static fn( $p ) => [ 'id' => $p->ID, 'title' => $p->post_title ], $posts );

		return new WP_REST_Response( $data, 200 );
	}

	// -------------------------------------------------------------------------
	// Shortcode
	// -------------------------------------------------------------------------

	public static function render_shortcode( array $atts = [] ): string {
		$atts = shortcode_atts(
			[ 'intro' => '' ],
			$atts,
			'travel_finder'
		);

		$lang     = self::current_lang();
		$selected = self::requested_filters();
		$registry = tvf_get_registry( $lang );
		$base_url = self::base_url();

		$intro         = $atts['intro'] ?: self::text( 'intro', $lang );
		$results_title = self::text( 'results_title', $lang );
		$dead_slugs    = TVF_Store::compute_dead_slugs( $lang, $selected );

		ob_start();
		?>
		<div class="tvf-wrap" id="tvf-wrap" data-lang="<?php echo esc_attr( $lang ); ?>">

			<div class="tvf-intro-row">
				<div class="tvf-intro"><?php echo esc_html( $intro ); ?></div>
				<button type="button" class="tvf-share-btn" id="tvf-share"
						aria-label="<?php echo esc_attr( self::text( 'share', $lang ) ); ?>">
					<svg width="18" height="18" viewBox="0 0 24 24" fill="none"
						 stroke="currentColor" stroke-width="2"
						 stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
						<circle cx="18" cy="5" r="3"/>
						<circle cx="6" cy="12" r="3"/>
						<circle cx="18" cy="19" r="3"/>
						<line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/>
						<line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/>
					</svg>
				</button>
			</div>
			<p class="mv-share-status" id="tvf-share-tooltip" aria-live="polite" hidden>
				<?php echo esc_html( self::text( 'share_copied', $lang ) ); ?>
			</p>

			<div class="tvf-summary" id="tvf-summary">
				<span id="tvf-summary-text" aria-live="polite">
					<?php echo self::render_summary( $selected, $lang ); ?>
				</span>
				<?php if ( $selected ) : ?>
					<a href="<?php echo esc_url( $base_url ); ?>" class="tvf-reset-btn" id="tvf-reset">
						<?php echo esc_html( self::text( 'reset', $lang ) ); ?>
					</a>
				<?php else : ?>
					<button type="button" class="tvf-reset-btn" id="tvf-reset" hidden>
						<?php echo esc_html( self::text( 'reset', $lang ) ); ?>
					</button>
				<?php endif; ?>
			</div>

			<div class="tvf-filters" id="tvf-filters">
				<?php echo self::render_filters( $selected, $registry, $base_url, $dead_slugs ); ?>
			</div>

			<?php $cards = self::render_cards( $selected, $lang, 0 ); ?>

			<div class="tvf-results-header">
				<h2 class="tvf-results-title"><?php echo esc_html( $results_title ); ?></h2>
				<p class="tvf-count" id="tvf-count" aria-live="polite" aria-atomic="true"<?php echo empty( $selected ) ? ' style="display:none"' : ''; ?>><?php echo ! empty( $selected ) ? esc_html( self::format_count( $cards['total_count'] ?? 0, $selected, $lang ) ) : ''; ?></p>
			</div>

			<div id="tvf-results" class="tvf-results">
				<?php echo $cards['html']; ?>
			</div>

			<div id="tvf-load-more-wrap" class="tvf-load-more-wrap"<?php echo $cards['has_more'] ? '' : ' hidden'; ?>>
				<button type="button" class="tvf-load-more-btn" id="tvf-load-more">
					<?php echo esc_html( self::text( 'load_more', $lang ) ); ?>
				</button>
			</div>

		</div>
		<?php
		return ob_get_clean();
	}

	// -------------------------------------------------------------------------
	// Render helpers
	// -------------------------------------------------------------------------

	private static function render_filters( array $selected, array $registry, string $base_url, array $dead_slugs = [] ): string {
		ob_start();

		// Row 0 — Saison, on its own centred row above everything else: it is the
		// one filter almost every visitor has already made up their mind about.
		if ( isset( $registry['saison'] ) ) {
			echo '<div class="tvf-filter-row tvf-row-saison">';
			echo self::group_html( 'saison', $registry['saison'], $selected, $base_url, $dead_slugs );
			echo '</div>';
		}

		// Row 1 — Intérêt
		echo '<div class="tvf-filter-row tvf-row-interet">';
		foreach ( $registry['interet']['filters'] as $slug => $label ) {
			echo self::chip_html( $slug, $label, $selected, $base_url, $dead_slugs );
		}
		echo '</div>';

		// Row 2 — remaining categories, each as a labelled group
		echo '<div class="tvf-filter-row tvf-row-secondary">';
		foreach ( $registry as $cat_slug => $cat ) {
			if ( 'interet' === $cat_slug || 'saison' === $cat_slug ) {
				continue;
			}
			echo self::group_html( $cat_slug, $cat, $selected, $base_url, $dead_slugs );
		}
		echo '</div>';

		return ob_get_clean();
	}

	/**
	 * One labelled filter group. Single-choice categories are marked up as a
	 * radiogroup (and carry their "one at a time" hint) so the exclusive
	 * behaviour is announced, not just implied by the styling.
	 */
	private static function group_html( string $cat_slug, array $cat, array $selected, string $base_url, array $dead_slugs ): string {
		$is_single = ! empty( $cat['single'] );
		$label_id  = 'tvf-group-' . $cat_slug;

		$html  = '<div class="tvf-filter-group tvf-group--' . esc_attr( $cat_slug )
			. ( $is_single ? ' tvf-group--single' : '' ) . '">';
		$html .= '<span class="tvf-group-label" id="' . esc_attr( $label_id ) . '">'
			. esc_html( $cat['label'] );
		if ( $is_single && ! empty( $cat['hint'] ) ) {
			$html .= ' <span class="tvf-group-hint">' . esc_html( $cat['hint'] ) . '</span>';
		}
		$html .= '</span>';
		$html .= '<div class="tvf-group-chips" role="' . ( $is_single ? 'radiogroup' : 'group' )
			. '" aria-labelledby="' . esc_attr( $label_id ) . '">';
		foreach ( $cat['filters'] as $slug => $label ) {
			$html .= self::chip_html( $slug, $label, $selected, $base_url, $dead_slugs, $is_single ? $cat_slug : '' );
		}
		$html .= '</div></div>';

		return $html;
	}

	/**
	 * @param string $single_group Category slug when the chip belongs to a single-choice
	 *                             category, '' otherwise. Mirrored to data-single-group so
	 *                             frontend.js can apply the same swap rule without a second
	 *                             copy of the registry.
	 */
	private static function chip_html( string $slug, string $label, array $selected, string $base_url, array $dead_slugs = [], string $single_group = '' ): string {
		$is_single    = '' !== $single_group;
		$is_on        = in_array( $slug, $selected, true );
		$is_dead      = ! $is_on && in_array( $slug, $dead_slugs, true );
		$new_selected = tvf_toggle_selection( $slug, $selected );
		$url          = empty( $new_selected )
			? $base_url
			: add_query_arg( 'f', implode( ',', $new_selected ), $base_url );

		// rel="nofollow": filter permutations are crawl traps, and these URLs are noindex anyway.
		return sprintf(
			'<a href="%s" rel="nofollow" class="tvf-chip%s%s%s" role="%s" aria-checked="%s"%s data-slug="%s"%s>%s</a>',
			esc_url( $url ),
			$is_single ? ' tvf-chip--single' : '',
			$is_on   ? ' is-on'   : '',
			$is_dead ? ' is-dead' : '',
			$is_single ? 'radio' : 'checkbox',
			$is_on   ? 'true'     : 'false',
			$is_dead ? ' aria-disabled="true" tabindex="-1"' : '',
			esc_attr( $slug ),
			$is_single ? ' data-single-group="' . esc_attr( $single_group ) . '"' : '',
			esc_html( $label )
		);
	}

	/**
	 * Every visitor-facing string of the [travel_finder] shortcode, in one table.
	 *
	 * Per-language arrays rather than gettext, for the same reason as the filter
	 * registry: this plugin ships no .po/.mo files, so __() only ever returned
	 * its French literal. Same convention as TVF_Focus::text() and
	 * homepage-catalog.php. Unknown languages fall back to French.
	 *
	 * The keys prefixed `count_` are mirrored in assets/frontend.js, which
	 * rebuilds the count line client-side; they reach it through the i18n
	 * payload in enqueue_assets(), so there is only one copy of the text.
	 */
	private static function text( string $key, string $lang ): string {
		static $strings = [
			'intro'          => [
				'fr' => 'Sélectionnez vos critères pour trouver le voyage idéal parmi nos destinations.',
				'en' => 'Choose your criteria to find the perfect trip among our destinations.',
				'de' => 'Wähle Deine Kriterien und finde unter unseren Reisezielen die passende Reise.',
			],
			'results_title'  => [
				'fr' => 'Nos idées de voyage pour vous :',
				'en' => 'Travel ideas for you',
				'de' => 'Reiseideen für Dich',
			],
			'share'          => [
				'fr' => 'Partager',
				'en' => 'Share',
				'de' => 'Teilen',
			],
			'share_copied'   => [
				'fr' => 'URL copiée — partagez par e-mail, message ou réseau social !',
				'en' => 'URL copied — share it by email, message or social media!',
				'de' => 'URL kopiert — teile sie per E-Mail, Nachricht oder in sozialen Netzwerken!',
			],
			'reset'          => [
				'fr' => 'Réinitialiser',
				'en' => 'Reset',
				'de' => 'Zurücksetzen',
			],
			'load_more'      => [
				'fr' => 'Voir plus',
				'en' => 'Show more',
				'de' => 'Mehr anzeigen',
			],
			'summary_empty'  => [
				'fr' => 'Aucun filtre sélectionné — destinations populaires.',
				'en' => 'No filters selected — popular destinations.',
				'de' => 'Keine Filter ausgewählt — beliebte Reiseziele.',
			],
			// Note the French space before the colon; the other two must not have one.
			'summary_prefix' => [
				'fr' => 'Votre sélection : ',
				'en' => 'Your selection: ',
				'de' => 'Deine Auswahl: ',
			],
			'no_results'     => [
				'fr' => 'Aucun voyage ne correspond à votre sélection. Essayez avec moins de filtres.',
				'en' => 'No trips match your selection. Try using fewer filters.',
				'de' => 'Keine Reise entspricht Deiner Auswahl. Versuch es mit weniger Filtern.',
			],
			'count_none'     => [
				'fr' => 'Aucune idée ne correspond à cette sélection.',
				'en' => 'No matching ideas.',
				'de' => 'Keine passenden Ideen.',
			],
			'count_one'      => [
				'fr' => 'idée trouvée',
				'en' => 'idea found',
				'de' => 'Idee gefunden',
			],
			'count_many'     => [
				'fr' => 'idées trouvées',
				'en' => 'ideas found',
				'de' => 'Ideen gefunden',
			],
			'count_suffix'   => [
				'fr' => ' pour votre sélection',
				'en' => ' for your selection',
				'de' => ' für Deine Auswahl',
			],
		];

		return isset( $strings[ $key ] ) ? tvf_resolve_text( $strings[ $key ], $lang ) : '';
	}

	/** The `count_*` strings, for the JS mirror of format_count(). */
	private static function count_strings( string $lang ): array {
		return [
			'none'   => self::text( 'count_none', $lang ),
			'one'    => self::text( 'count_one', $lang ),
			'many'   => self::text( 'count_many', $lang ),
			'suffix' => self::text( 'count_suffix', $lang ),
		];
	}

	private static function format_count( int $count, array $selected, string $lang ): string {
		if ( 0 === $count ) {
			return self::text( 'count_none', $lang );
		}
		$noun   = self::text( 1 === $count ? 'count_one' : 'count_many', $lang );
		$suffix = ! empty( $selected ) ? self::text( 'count_suffix', $lang ) : '';

		return $count . "\u{00A0}" . $noun . $suffix;
	}

	private static function render_summary( array $selected, string $lang ): string {
		if ( empty( $selected ) ) {
			return '<span class="tvf-summary-empty">'
				. esc_html( self::text( 'summary_empty', $lang ) )
				. '</span>';
		}
		$slug_labels = tvf_get_slug_labels( $lang );
		$labels      = array_filter(
			array_map( static fn( $s ) => $slug_labels[ $s ] ?? null, $selected )
		);

		return '<strong>' . esc_html( self::text( 'summary_prefix', $lang ) ) . '</strong>'
			. esc_html( implode( ', ', $labels ) );
	}

	/**
	 * Renders a page of card <article> elements, cached in a transient.
	 * Returns ['html' => string, 'has_more' => bool, 'total_count' => int] at offset=0,
	 * or ['html' => string, 'has_more' => bool] for subsequent pages (load-more).
	 * Queries TVF_Store::BATCH + 1 rows; if the probe row comes back,
	 * has_more=true and it is discarded before rendering.
	 *
	 * @param string[] $slugs
	 * @param string   $lang
	 * @param int      $offset 0-based row offset (multiples of TVF_Store::BATCH).
	 * @return array{html: string, has_more: bool, total_count?: int}
	 */
	public static function render_cards( array $slugs, string $lang, int $offset = 0 ): array {
		sort( $slugs ); // canonical order for consistent cache keys
		$cache_key = TVF_Store::cache_key(
			TVF_Store::RESULT_CACHE_PREFIX,
			$lang,
			md5( implode( ',', $slugs ) ) . '_' . $offset
		);
		$cached    = get_transient( $cache_key );
		if ( is_array( $cached ) ) {
			// Backfill total_count for cache entries predating the count feature.
			if ( $offset === 0 && ! array_key_exists( 'total_count', $cached ) ) {
				$cached['total_count'] = TVF_Store::count_results( $lang, $slugs );
				set_transient( $cache_key, $cached, HOUR_IN_SECONDS );
			}
			return $cached;
		}

		$rows     = TVF_Store::query_results( $lang, $slugs, $offset );
		$has_more = count( $rows ) > TVF_Store::BATCH;
		if ( $has_more ) {
			array_pop( $rows ); // discard the probe row
		}

		if ( empty( $rows ) ) {
			// offset=0: wrap no-results in the grid container so JS can replace results.innerHTML uniformly.
			// offset>0: shouldn't happen in practice; return empty.
			$inner = $offset === 0
				? '<p class="tvf-no-results">'
					. esc_html( self::text( 'no_results', $lang ) )
					. '</p>'
				: '';
			$result = [
				'html'        => $offset === 0 ? '<div class="tvf-cards-grid">' . $inner . '</div>' : '',
				'has_more'    => false,
				'total_count' => 0,
			];
			set_transient( $cache_key, $result, HOUR_IN_SECONDS );
			return $result;
		}

		ob_start();
		$badge_seen = [];
		foreach ( $rows as $row ) {
			$post = get_post( (int) $row['post_id'] );
			if ( ! $post ) {
				continue;
			}
			$thumb = get_the_post_thumbnail_url( $post, 'medium_large' );
			$url   = get_permalink( $post );
			$title = get_the_title( $post );

			// Compact horizontal result tile: small thumbnail left, title right.
			$tile_classes = 'mv-tile mv-tile--result mv-tile--compact' . ( $thumb ? '' : ' mv-tile--no-media' );
			echo '<div class="' . esc_attr( $tile_classes ) . '">';
			if ( $thumb ) {
				echo '<span class="mv-tile__media">';
				echo '<img class="mv-tile__img" src="' . esc_url( $thumb ) . '" alt="" loading="lazy" decoding="async">';
				echo '</span>';
			}
			echo '<span class="mv-tile__body">';
			if ( function_exists( 'mv_get_tile_badges' ) ) {
				$badge_args = [
					'context'        => 'finder_result',
					'limit'          => 2,
					'active_filters' => $slugs,
					'seen_labels'    => $badge_seen,
				];
				$badges = mv_get_tile_badges( (int) $post->ID, $badge_args );
				mv_badges_update_seen( $badge_seen, $badges );
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo mv_render_tile_badges( $badges, $badge_args );
			}
			echo '<span class="mv-tile__title"><a class="mv-tile__link" href="' . esc_url( $url ) . '">' . esc_html( $title ) . '</a></span>';
			echo '</span>';
			echo '</div>';
		}

		$articles_html = ob_get_clean();

		// offset=0 (filter change): include the grid wrapper — JS replaces results.innerHTML with this.
		// offset>0 (load more):     bare articles only — JS appends these to the existing grid.
		$html = $offset === 0
			? '<div class="tvf-cards-grid">' . $articles_html . '</div>'
			: $articles_html;

		$result = [ 'html' => $html, 'has_more' => $has_more ];
		if ( $offset === 0 ) {
			$result['total_count'] = TVF_Store::count_results( $lang, $slugs );
		}
		set_transient( $cache_key, $result, HOUR_IN_SECONDS );
		return $result;
	}

	// -------------------------------------------------------------------------
	// Utilities
	// -------------------------------------------------------------------------

	private static function current_lang(): string {
		if ( function_exists( 'pll_current_language' ) ) {
			return pll_current_language() ?: 'fr';
		}
		return 'fr';
	}

	private static function parse_filter_param( string $f ): array {
		return tvf_parse_filter_param( $f );
	}

	private static function base_url(): string {
		// Current page URL with the 'f' param stripped — works for published pages,
		// previews (?page_id=X&preview=true), and any other query-string context.
		return remove_query_arg( 'f' );
	}
}
