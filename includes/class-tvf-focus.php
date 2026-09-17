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

	/**
	 * Slug of the page hosting [travel_finder], per language.
	 *
	 * These are the pages the focus view escalates to; the focus pages hosting
	 * [travel_finder_focus] are different pages again (/nos-idees-de-voyage/,
	 * /en/our-travel-ideas/, /de/unsere-reiseineen/) and live in the theme.
	 */
	const FULL_FINDER_SLUGS = [
		'fr' => 'ou-partir-trouvez-votre-prochain-voyage',
		'en' => 'where-to',
		'de' => 'wohin-reisen',
	];

	/**
	 * Absolute URLs, used only if the slug lookup finds nothing.
	 *
	 * These were the whole of the mechanism, which made an editor's slug edit,
	 * a domain change and a staging copy each silently produce a link to the
	 * wrong site or a 404. They stay as a floor: on the live site with the
	 * pages in place they are never reached, and on a site where the lookup
	 * fails the link is no worse than it was before.
	 */
	const FULL_FINDER_FALLBACK_URLS = [
		'fr' => 'https://www.mamanvoyage.com/ou-partir-trouvez-votre-prochain-voyage/',
		'en' => 'https://www.mamanvoyage.com/en/where-to/',
		'de' => 'https://www.mamanvoyage.com/de/wohin-reisen/',
	];

	/** Per-request memo of resolved full-finder page IDs, keyed by language. */
	private static array $full_finder_ids = [];

	/**
	 * The URL of the full [travel_finder] page in a language, or null.
	 *
	 * Resolved from the configured slug rather than hard-coded, then — failing
	 * that — from Polylang's translation of whichever language's page can be
	 * found, so a page an editor renamed in one language is still reachable
	 * from the others. Cached in a transient; the lookup itself is one indexed
	 * query on a miss.
	 */
	public static function full_finder_url( string $lang ): ?string {
		$post_id = self::full_finder_id( $lang );

		if ( $post_id ) {
			return (string) get_permalink( $post_id );
		}

		return self::FULL_FINDER_FALLBACK_URLS[ $lang ] ?? null;
	}

	private static function full_finder_id( string $lang ): int {
		if ( isset( self::$full_finder_ids[ $lang ] ) ) {
			return self::$full_finder_ids[ $lang ];
		}

		$key    = 'tvf_ff_page_' . $lang;
		$cached = get_transient( $key );

		if ( false !== $cached ) {
			return self::$full_finder_ids[ $lang ] = (int) $cached;
		}

		$found = 0;
		$slug  = self::FULL_FINDER_SLUGS[ $lang ] ?? '';

		if ( '' !== $slug ) {
			$page  = get_page_by_path( $slug, OBJECT, 'page' );
			$found = $page instanceof WP_Post ? self::validate_page( $page->ID, $lang ) : 0;
		}

		// The slugs are a guess at what the pages were called; the translation
		// relationship is a fact an editor stated. So any language's page that
		// can be found seeds the lookup, and this language's version is
		// whatever Polylang links to it.
		if ( ! $found && function_exists( 'pll_get_post' ) ) {
			foreach ( self::FULL_FINDER_SLUGS as $other_lang => $other_slug ) {
				if ( $other_lang === $lang || '' === $other_slug ) {
					continue;
				}

				$seed = get_page_by_path( $other_slug, OBJECT, 'page' );
				if ( ! $seed instanceof WP_Post ) {
					continue;
				}

				$translated = (int) pll_get_post( $seed->ID, $lang );
				$found      = $translated ? self::validate_page( $translated, $lang ) : 0;

				if ( $found ) {
					break;
				}
			}
		}

		set_transient( $key, $found, DAY_IN_SECONDS );

		return self::$full_finder_ids[ $lang ] = $found;
	}

	/** A published page, in the language it claims to be in. */
	private static function validate_page( int $post_id, string $lang ): int {
		$post = get_post( $post_id );

		if ( ! $post instanceof WP_Post || 'page' !== $post->post_type || 'publish' !== $post->post_status ) {
			return 0;
		}

		return TVF_Store::post_lang( $post_id ) === $lang ? $post_id : 0;
	}

	/** A new page, or a slug change, must be findable without waiting out the transient. */
	public static function forget_full_finder_ids(): void {
		self::$full_finder_ids = [];

		foreach ( array_keys( self::FULL_FINDER_SLUGS ) as $lang ) {
			delete_transient( 'tvf_ff_page_' . $lang );
		}
	}

	public static function init(): void {
		add_shortcode( 'travel_finder_focus', [ __CLASS__, 'render_shortcode' ] );
		add_action( 'template_redirect', [ __CLASS__, 'maybe_redirect' ] );
		add_action( 'wp_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );

		add_action( 'save_post_page', [ __CLASS__, 'forget_full_finder_ids' ] );
		add_action( 'deleted_post', [ __CLASS__, 'forget_full_finder_ids' ] );
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

		// Same handle and same mtime versioning the child theme itself uses, so
		// whichever enqueue runs first produces the same ?ver=. The theme version
		// did not change when the stylesheet did, and caches kept the old file.
		$css = get_stylesheet_directory() . '/assets/css/mv-home.css';

		wp_enqueue_style(
			'mv-home',
			get_stylesheet_directory_uri() . '/assets/css/mv-home.css',
			[],
			file_exists( $css ) ? filemtime( $css ) : wp_get_theme()->get( 'Version' )
		);
	}

	public static function render_shortcode(): string {
		$slugs = self::current_slugs();

		if ( empty( $slugs ) ) {
			return ''; // maybe_redirect() already handles this on a normal page load.
		}

		$lang     = self::current_lang();
		$posts    = TVF_Store::resolve_posts_for_slugs( $lang, $slugs, 9 );
		$full_url = self::full_finder_url( $lang );
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

		$labels = tvf_get_slug_labels( $lang );
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
				'de' => 'Für Deine Auswahl gibt es aktuell keine Treffer.',
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
