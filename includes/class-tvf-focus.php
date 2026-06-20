<?php
defined( 'ABSPATH' ) || exit;

/**
 * [travel_finder_focus] — a calm, single-combination results view that
 * complements the full [travel_finder] filter tool. No chips, no
 * pagination: a handful of results plus one link escalating to the full
 * tool with the same `f` filters applied.
 *
 * A page hosting this shortcode has no meaning without a recognised `f`
 * combination, so visits without one redirect to the site homepage.
 */
class TVF_Focus {

	const FULL_FINDER_URL = 'https://www.mamanvoyage.com/ou-partir-trouvez-votre-prochain-voyage/';

	public static function init(): void {
		add_shortcode( 'travel_finder_focus', [ __CLASS__, 'render_shortcode' ] );
		add_action( 'template_redirect', [ __CLASS__, 'maybe_redirect' ] );
		add_action( 'wp_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );
	}

	/** Redirects to the homepage when the hosting page has no valid `f` slugs. */
	public static function maybe_redirect(): void {
		if ( is_preview() || ! self::current_post_has_shortcode() ) {
			return;
		}

		$slugs = self::current_slugs();
		if ( empty( $slugs ) ) {
			wp_safe_redirect( home_url( '/' ) );
			exit;
		}
	}

	public static function enqueue_assets(): void {
		if ( ! self::current_post_has_shortcode() ) {
			return;
		}

		wp_enqueue_style(
			'mv-home',
			get_stylesheet_directory_uri() . '/assets/css/mv-home.css',
			[],
			wp_get_theme()->get( 'Version' )
		);
	}

	public static function render_shortcode(): string {
		$slugs = self::current_slugs();

		if ( empty( $slugs ) ) {
			return ''; // maybe_redirect() already handles this on a normal page load.
		}

		$lang  = function_exists( 'pll_current_language' ) ? ( pll_current_language( 'slug' ) ?: 'fr' ) : 'fr';
		$posts = TVF_Store::resolve_posts_for_slugs( $lang, $slugs, 9 );
		$more_url = add_query_arg( 'f', implode( ',', $slugs ), self::FULL_FINDER_URL );

		ob_start();
		?>
		<section class="mv-section mv-travel-finder-focus">
			<div class="mv-container">
				<header class="mv-section__header">
					<h2 class="mv-section__title"><?php echo esc_html( self::title_for_slugs( $slugs ) ); ?></h2>
				</header>
				<?php if ( empty( $posts ) ) : ?>
					<p class="tvf-no-results">
						<?php esc_html_e( 'Aucun voyage ne correspond à votre sélection pour le moment.', 'travel-finder' ); ?>
					</p>
				<?php else : ?>
					<div class="mv-grid mv-grid--3">
						<?php foreach ( $posts as $focus_post ) : ?>
							<?php echo self::card_html( $focus_post ); ?>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
				<p class="mv-travel-finder-focus__more">
					<a class="mv-button mv-button--secondary" href="<?php echo esc_url( $more_url ); ?>">
						<?php esc_html_e( 'Affiner votre recherche', 'travel-finder' ); ?>
					</a>
				</p>
			</div>
		</section>
		<?php
		return ob_get_clean();
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	private static function current_post_has_shortcode(): bool {
		global $post;
		return is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'travel_finder_focus' );
	}

	private static function current_slugs(): array {
		$f = isset( $_GET['f'] ) ? sanitize_text_field( wp_unslash( $_GET['f'] ) ) : '';
		return tvf_parse_filter_param( $f );
	}

	/** Human label for a slug combination — catalog label if it matches one, else joined filter labels. */
	private static function title_for_slugs( array $slugs ): string {
		$sorted_slugs = $slugs;
		sort( $sorted_slugs );

		foreach ( tvf_get_homepage_catalog() as $entries ) {
			foreach ( $entries as $entry ) {
				$entry_slugs = $entry['slugs'];
				sort( $entry_slugs );
				if ( $entry_slugs === $sorted_slugs ) {
					return $entry['label'];
				}
			}
		}

		$labels = tvf_get_slug_labels();
		$names  = array_filter( array_map( static fn( $s ) => $labels[ $s ] ?? null, $slugs ) );

		return $names ? implode( ', ', $names ) : __( 'Nos idées de voyage', 'travel-finder' );
	}

	private static function card_html( WP_Post $post ): string {
		$image = get_the_post_thumbnail_url( $post, 'medium_large' );

		ob_start();
		?>
		<a class="mv-card mv-card--post" href="<?php echo esc_url( get_permalink( $post ) ); ?>">
			<?php if ( $image ) : ?>
				<span class="mv-card__image">
					<img src="<?php echo esc_url( $image ); ?>" alt="" loading="lazy">
				</span>
			<?php endif; ?>
			<span class="mv-card__body">
				<span class="mv-card__title"><?php echo esc_html( get_the_title( $post ) ); ?></span>
				<span class="mv-card__description"><?php echo esc_html( wp_strip_all_tags( get_the_excerpt( $post ) ) ); ?></span>
			</span>
		</a>
		<?php
		return ob_get_clean();
	}
}
