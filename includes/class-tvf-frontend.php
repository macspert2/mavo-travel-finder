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
				'f'    => [ 'sanitize_callback' => 'sanitize_text_field', 'default' => '' ],
				'lang' => [ 'sanitize_callback' => 'sanitize_key',        'default' => 'fr' ],
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
		$lang = $request->get_param( 'lang' );
		if ( ! in_array( $lang, [ 'fr', 'en', 'de' ], true ) ) {
			$lang = 'fr';
		}
		$slugs = self::parse_filter_param( $request->get_param( 'f' ) );
		$html  = self::render_cards( $slugs, $lang );

		return new WP_REST_Response( [ 'html' => $html ], 200 );
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

		$intro = $atts['intro'] ?: __( 'Sélectionnez vos critères pour trouver le voyage idéal parmi nos destinations.', 'travel-finder' );

		ob_start();
		?>
		<?php if ( $selected ) : ?>
			<link rel="canonical" href="<?php echo esc_url( $base_url ); ?>">
			<meta name="robots" content="noindex,follow">
		<?php endif; ?>

		<div class="tvf-wrap" id="tvf-wrap" data-lang="<?php echo esc_attr( $lang ); ?>">

			<div class="tvf-intro"><?php echo esc_html( $intro ); ?></div>

			<div class="tvf-summary" id="tvf-summary" aria-live="polite">
				<?php echo self::render_summary( $selected, $registry ); ?>
			</div>

			<div class="tvf-filters" id="tvf-filters">
				<?php echo self::render_filters( $selected, $registry, $base_url ); ?>
			</div>

			<div class="tvf-results-bar">
				<span id="tvf-result-count" class="tvf-result-count"></span>
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

			<div id="tvf-results" class="tvf-results">
				<?php echo self::render_cards( $selected, $lang ); ?>
			</div>

		</div>
		<?php
		return ob_get_clean();
	}

	// -------------------------------------------------------------------------
	// Render helpers
	// -------------------------------------------------------------------------

	private static function render_filters( array $selected, array $registry, string $base_url ): string {
		ob_start();

		// Row 1 — Intérêt
		echo '<div class="tvf-filter-row tvf-row-interet">';
		foreach ( $registry['interet']['filters'] as $slug => $label ) {
			echo self::chip_html( $slug, $label, $selected, $base_url );
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
				echo self::chip_html( $slug, $label, $selected, $base_url );
			}
			echo '</div></div>';
		}
		echo '</div>';

		return ob_get_clean();
	}

	private static function chip_html( string $slug, string $label, array $selected, string $base_url ): string {
		$is_on        = in_array( $slug, $selected, true );
		$new_selected = $is_on
			? array_values( array_diff( $selected, [ $slug ] ) )
			: array_merge( $selected, [ $slug ] );
		$url          = empty( $new_selected )
			? $base_url
			: add_query_arg( 'f', implode( ',', $new_selected ), $base_url );

		return sprintf(
			'<a href="%s" class="tvf-chip%s" role="checkbox" aria-checked="%s" data-slug="%s">%s</a>',
			esc_url( $url ),
			$is_on ? ' is-on' : '',
			$is_on ? 'true' : 'false',
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
	 * Renders the 16-card grid HTML, cached in a transient.
	 * Also used by the REST endpoint to return fresh HTML.
	 *
	 * @param string[] $slugs
	 */
	public static function render_cards( array $slugs, string $lang ): string {
		sort( $slugs ); // canonical order for consistent cache keys
		$cache_key = 'tvf_results_' . $lang . '_' . md5( implode( ',', $slugs ) );
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			return $cached;
		}

		$rows = TVF_Store::query_results( $lang, $slugs );

		if ( empty( $rows ) ) {
			$html = '<p class="tvf-no-results">'
				. esc_html__( 'Aucun voyage ne correspond à votre sélection. Essayez avec moins de filtres.', 'travel-finder' )
				. '</p>';
			set_transient( $cache_key, $html, HOUR_IN_SECONDS );
			return $html;
		}

		ob_start();
		echo '<div class="tvf-cards-grid">';

		foreach ( $rows as $row ) {
			$post  = get_post( (int) $row['post_id'] );
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

		echo '</div>';
		$html = ob_get_clean();
		set_transient( $cache_key, $html, HOUR_IN_SECONDS );
		return $html;
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
