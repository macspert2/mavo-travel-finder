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

	/** Full-finder page per language — only French exists today; languages with no entry just don't get an escalation link. */
	const FULL_FINDER_URLS = [
		'fr' => 'https://www.mamanvoyage.com/ou-partir-trouvez-votre-prochain-voyage/',
	];

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
			$home = function_exists( 'pll_home_url' ) ? pll_home_url() : home_url( '/' );
			wp_safe_redirect( $home );
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

		$lang     = self::current_lang();
		$posts    = TVF_Store::resolve_posts_for_slugs( $lang, $slugs, 9 );
		$full_url = self::FULL_FINDER_URLS[ $lang ] ?? null;
		$more_url = $full_url ? add_query_arg( 'f', implode( ',', $slugs ), $full_url ) : null;

		ob_start();
		?>
		<section class="mv-section mv-travel-finder-focus">
			<div class="mv-container">
				<header class="mv-section__header">
					<h2 class="mv-section__title"><?php echo esc_html( self::title_for_slugs( $slugs, $lang ) ); ?></h2>
				</header>
				<?php if ( empty( $posts ) ) : ?>
					<p class="tvf-no-results">
						<?php echo esc_html( self::text( 'no_results', $lang ) ); ?>
					</p>
				<?php else : ?>
					<div class="mv-tile-grid mv-grid mv-grid--3">
						<?php foreach ( $posts as $focus_post ) : ?>
							<?php echo self::card_html( $focus_post ); ?>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
				<?php if ( $more_url ) : ?>
					<p class="mv-travel-finder-focus__more">
						<a class="mv-button mv-button--secondary" href="<?php echo esc_url( $more_url ); ?>">
							<?php echo esc_html( self::text( 'refine', $lang ) ); ?>
						</a>
					</p>
				<?php endif; ?>
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

	private static function current_lang(): string {
		return function_exists( 'pll_current_language' ) ? ( pll_current_language( 'slug' ) ?: 'fr' ) : 'fr';
	}

	/** Human label for a slug combination — catalog label if it matches one, else joined filter labels, else a generic fallback. All language-aware. */
	private static function title_for_slugs( array $slugs, string $lang ): string {
		$sorted_slugs = $slugs;
		sort( $sorted_slugs );

		foreach ( tvf_get_homepage_catalog() as $entries ) {
			foreach ( $entries as $entry ) {
				$entry_slugs = $entry['slugs'];
				sort( $entry_slugs );
				if ( $entry_slugs === $sorted_slugs ) {
					return tvf_resolve_catalog_text( $entry['label'], $lang );
				}
			}
		}

		// tvf_get_slug_labels() is French-only today, so this joined-label
		// path (for filter combos with no matching catalog entry) will
		// still show French text on en/de pages until those are
		// translated too — known gap, not hit by any curated tile today.
		$labels = tvf_get_slug_labels();
		$names  = array_filter( array_map( static fn( $s ) => $labels[ $s ] ?? null, $slugs ) );

		if ( $names ) {
			return implode( ', ', $names );
		}

		return self::text( 'fallback_title', $lang );
	}

	/** Small set of UI strings not tied to catalog data. */
	private static function text( string $key, string $lang ): string {
		$strings = [
			'no_results'    => [
				'fr' => 'Aucun voyage ne correspond à votre sélection pour le moment.',
				'en' => 'No trips match your selection just yet.',
				'de' => 'Für deine Auswahl gibt es aktuell keine Treffer.',
			],
			'refine'        => [
				'fr' => 'Affiner votre recherche',
				'en' => 'Refine your search',
				'de' => 'Suche verfeinern',
			],
			'fallback_title' => [
				'fr' => 'Nos idées de voyage',
				'en' => 'Our travel ideas',
				'de' => 'Unsere Reiseideen',
			],
		];

		return $strings[ $key ][ $lang ] ?? $strings[ $key ]['fr'];
	}

	private static function card_html( WP_Post $post ): string {
		$image_url = get_the_post_thumbnail_url( $post, 'medium_large' );
		$classes   = 'mv-tile mv-tile--media' . ( $image_url ? '' : ' mv-tile--no-media' );

		ob_start();
		?>
		<a class="<?php echo esc_attr( $classes ); ?>" href="<?php echo esc_url( get_permalink( $post ) ); ?>">
			<?php if ( $image_url ) : ?>
				<span class="mv-tile__media">
					<img class="mv-tile__img" src="<?php echo esc_url( $image_url ); ?>" alt="" loading="lazy" decoding="async">
				</span>
			<?php endif; ?>
			<span class="mv-tile__body">
				<span class="mv-tile__title"><?php echo esc_html( get_the_title( $post ) ); ?></span>
				<span class="mv-tile__description"><?php echo esc_html( wp_strip_all_tags( get_the_excerpt( $post ) ) ); ?></span>
			</span>
		</a>
		<?php
		return ob_get_clean();
	}
}
