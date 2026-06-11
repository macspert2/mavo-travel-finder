<?php
defined( 'ABSPATH' ) || exit;

class TVF_Frontend {

	public static function init(): void {
		add_shortcode( 'travel_finder',    [ __CLASS__, 'render_shortcode' ] );
		add_action( 'rest_api_init',       [ __CLASS__, 'register_rest_routes' ] );
		add_action( 'wp_enqueue_scripts',  [ __CLASS__, 'enqueue_assets' ] );
	}

	// -------------------------------------------------------------------------
	// Assets
	// -------------------------------------------------------------------------

	public static function enqueue_assets(): void {
		global $post;
		if ( ! is_a( $post, 'WP_Post' ) || ! has_shortcode( $post->post_content, 'travel_finder' ) ) {
			return;
		}

		wp_enqueue_style( 'tvf-frontend', TVF_PLUGIN_URL . 'assets/frontend.css', [], TVF_VERSION );
		wp_enqueue_script( 'tvf-frontend', TVF_PLUGIN_URL . 'assets/frontend.js', [], TVF_VERSION, true );
		wp_localize_script( 'tvf-frontend', 'tvfFrontend', [
			'restUrl' => rest_url( 'tvf/v1/results' ),
			'nonce'   => wp_create_nonce( 'wp_rest' ),
		] );
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
		$f_param  = isset( $_GET['f'] ) ? sanitize_text_field( wp_unslash( $_GET['f'] ) ) : '';
		$selected = self::parse_filter_param( $f_param );
		$registry = tvf_get_registry();
		$base_url = self::base_url();

		$intro      = $atts['intro'] ?: __( 'Sélectionnez vos critères pour trouver le voyage idéal parmi nos destinations.', 'travel-finder' );
		$dead_slugs = TVF_Store::compute_dead_slugs( $lang, $selected );

		ob_start();
		?>
		<?php if ( $selected ) : ?>
			<link rel="canonical" href="<?php echo esc_url( $base_url ); ?>">
			<meta name="robots" content="noindex,follow">
		<?php endif; ?>

		<div class="tvf-wrap" id="tvf-wrap" data-lang="<?php echo esc_attr( $lang ); ?>">

			<div class="tvf-intro-row">
				<div class="tvf-intro"><?php echo esc_html( $intro ); ?></div>
				<button type="button" class="tvf-share-btn" id="tvf-share"
						aria-label="<?php esc_attr_e( 'Partager', 'travel-finder' ); ?>">
					<svg width="18" height="18" viewBox="0 0 24 24" fill="none"
						 stroke="currentColor" stroke-width="2"
						 stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
						<circle cx="18" cy="5" r="3"/>
						<circle cx="6" cy="12" r="3"/>
						<circle cx="18" cy="19" r="3"/>
						<line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/>
						<line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/>
					</svg>
					<span class="tvf-share-tooltip" id="tvf-share-tooltip" role="status" hidden>
						<?php esc_html_e( 'URL copiée — partagez par e-mail, message ou réseau social !', 'travel-finder' ); ?>
					</span>
				</button>
			</div>

			<div class="tvf-summary" id="tvf-summary">
				<span id="tvf-summary-text" aria-live="polite">
					<?php echo self::render_summary( $selected, $registry ); ?>
				</span>
				<?php if ( $selected ) : ?>
					<a href="<?php echo esc_url( $base_url ); ?>" class="tvf-reset-btn" id="tvf-reset">
						<?php esc_html_e( 'Réinitialiser', 'travel-finder' ); ?>
					</a>
				<?php else : ?>
					<button type="button" class="tvf-reset-btn" id="tvf-reset" hidden>
						<?php esc_html_e( 'Réinitialiser', 'travel-finder' ); ?>
					</button>
				<?php endif; ?>
			</div>

			<div class="tvf-filters" id="tvf-filters">
				<?php echo self::render_filters( $selected, $registry, $base_url, $dead_slugs ); ?>
			</div>

			<?php $cards = self::render_cards( $selected, $lang, 0 ); ?>

			<div id="tvf-results" class="tvf-results">
				<?php echo $cards['html']; ?>
			</div>

			<div id="tvf-load-more-wrap" class="tvf-load-more-wrap"<?php echo $cards['has_more'] ? '' : ' hidden'; ?>>
				<button type="button" class="tvf-load-more-btn" id="tvf-load-more">
					<?php esc_html_e( 'Voir plus', 'travel-finder' ); ?>
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

		// Row 1 — Intérêt
		echo '<div class="tvf-filter-row tvf-row-interet">';
		foreach ( $registry['interet']['filters'] as $slug => $label ) {
			echo self::chip_html( $slug, $label, $selected, $base_url, $dead_slugs );
		}
		echo '</div>';

		// Row 2 — all other categories, each as a labelled group
		echo '<div class="tvf-filter-row tvf-row-secondary">';
		foreach ( $registry as $cat_slug => $cat ) {
			if ( 'interet' === $cat_slug ) {
				continue;
			}
			echo '<div class="tvf-filter-group">';
			echo '<span class="tvf-group-label">' . esc_html( $cat['label'] ) . '</span>';
			echo '<div class="tvf-group-chips">';
			foreach ( $cat['filters'] as $slug => $label ) {
				echo self::chip_html( $slug, $label, $selected, $base_url, $dead_slugs );
			}
			echo '</div></div>';
		}
		echo '</div>';

		return ob_get_clean();
	}

	private static function chip_html( string $slug, string $label, array $selected, string $base_url, array $dead_slugs = [] ): string {
		$is_on        = in_array( $slug, $selected, true );
		$is_dead      = ! $is_on && in_array( $slug, $dead_slugs, true );
		$new_selected = $is_on
			? array_values( array_diff( $selected, [ $slug ] ) )
			: array_merge( $selected, [ $slug ] );
		$url          = empty( $new_selected )
			? $base_url
			: add_query_arg( 'f', implode( ',', $new_selected ), $base_url );

		return sprintf(
			'<a href="%s" class="tvf-chip%s%s" role="checkbox" aria-checked="%s"%s data-slug="%s">%s</a>',
			esc_url( $url ),
			$is_on   ? ' is-on'   : '',
			$is_dead ? ' is-dead' : '',
			$is_on   ? 'true'     : 'false',
			$is_dead ? ' aria-disabled="true" tabindex="-1"' : '',
			esc_attr( $slug ),
			esc_html( $label )
		);
	}

	private static function render_summary( array $selected, array $registry ): string {
		if ( empty( $selected ) ) {
			return '<span class="tvf-summary-empty">'
				. esc_html__( 'Aucun filtre sélectionné — destinations populaires.', 'travel-finder' )
				. '</span>';
		}
		$slug_labels = tvf_get_slug_labels();
		$labels      = array_filter(
			array_map( static fn( $s ) => $slug_labels[ $s ] ?? null, $selected )
		);

		return '<strong>' . esc_html__( 'Votre sélection : ', 'travel-finder' ) . '</strong>'
			. esc_html( implode( ', ', $labels ) );
	}

	/**
	 * Renders a page of card <article> elements, cached in a transient.
	 * Returns ['html' => string, 'has_more' => bool].
	 * Queries BATCH+1 rows; if 43 come back, has_more=true and only 42 are rendered.
	 *
	 * @param string[] $slugs
	 * @param string   $lang
	 * @param int      $offset 0-based row offset (multiples of 42).
	 * @return array{html: string, has_more: bool}
	 */
	public static function render_cards( array $slugs, string $lang, int $offset = 0 ): array {
		sort( $slugs ); // canonical order for consistent cache keys
		$cache_key = 'tvf_r3_' . $lang . '_' . md5( implode( ',', $slugs ) ) . '_' . $offset;
		$cached    = get_transient( $cache_key );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$rows     = TVF_Store::query_results( $lang, $slugs, $offset );
		$has_more = count( $rows ) > 42;
		if ( $has_more ) {
			array_pop( $rows ); // discard the probe row
		}

		if ( empty( $rows ) ) {
			// offset=0: wrap no-results in the grid container so JS can replace results.innerHTML uniformly.
			// offset>0: shouldn't happen in practice; return empty.
			$inner = $offset === 0
				? '<p class="tvf-no-results">'
					. esc_html__( 'Aucun voyage ne correspond à votre sélection. Essayez avec moins de filtres.', 'travel-finder' )
					. '</p>'
				: '';
			$result = [
				'html'     => $offset === 0 ? '<div class="tvf-cards-grid">' . $inner . '</div>' : '',
				'has_more' => false,
			];
			set_transient( $cache_key, $result, HOUR_IN_SECONDS );
			return $result;
		}

		ob_start();
		foreach ( $rows as $row ) {
			$post = get_post( (int) $row['post_id'] );
			if ( ! $post ) {
				continue;
			}
			$thumb = get_the_post_thumbnail_url( $post, 'medium_large' );
			$url   = get_permalink( $post );
			$title = get_the_title( $post );

			echo '<article class="tvf-card">';
			echo '<a href="' . esc_url( $url ) . '" class="tvf-card-link">';
			if ( $thumb ) {
				echo '<div class="tvf-card-img" style="background-image:url(\'' . esc_url( $thumb ) . '\')" role="img" aria-label="' . esc_attr( $title ) . '"></div>';
			} else {
				echo '<div class="tvf-card-img tvf-card-img--no-thumb"></div>';
			}
			echo '<div class="tvf-card-overlay"><h3 class="tvf-card-title">' . esc_html( $title ) . '</h3></div>';
			echo '</a></article>';
		}

		$articles_html = ob_get_clean();

		// offset=0 (filter change): include the grid wrapper — JS replaces results.innerHTML with this.
		// offset>0 (load more):     bare articles only — JS appends these to the existing grid.
		$html = $offset === 0
			? '<div class="tvf-cards-grid">' . $articles_html . '</div>'
			: $articles_html;

		$result = [ 'html' => $html, 'has_more' => $has_more ];
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
		if ( '' === $f ) {
			return [];
		}
		$allowed = array_flip( tvf_get_all_slugs() );
		$out     = [];
		foreach ( explode( ',', $f ) as $slug ) {
			$slug = sanitize_key( trim( $slug ) );
			if ( isset( $allowed[ $slug ] ) ) {
				$out[] = $slug;
			}
		}
		return array_values( array_unique( $out ) );
	}

	private static function base_url(): string {
		// Current page URL with the 'f' param stripped — works for published pages,
		// previews (?page_id=X&preview=true), and any other query-string context.
		return remove_query_arg( 'f' );
	}
}
