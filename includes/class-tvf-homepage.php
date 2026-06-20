<?php
defined( 'ABSPATH' ) || exit;

/**
 * Resolves homepage catalog cards (see homepage-catalog.php) into actual
 * published posts, using the same scoring as the visitor-facing
 * [travel_finder] shortcode (TVF_Store::query_results()).
 */
class TVF_Homepage {

	/**
	 * Ranked, published WP_Post objects for a catalog card key.
	 * Returns an empty array if the key is unknown or nothing matches —
	 * callers should skip rendering that card rather than show it empty.
	 *
	 * @return WP_Post[]
	 */
	public static function get_card_posts( string $key, string $lang = 'fr', int $limit = 6 ): array {
		$entry = tvf_get_homepage_catalog_entry( $key );
		if ( ! $entry || empty( $entry['slugs'] ) ) {
			return [];
		}

		$rows = TVF_Store::query_results( $lang, $entry['slugs'], 0 );
		$rows = array_slice( $rows, 0, $limit );

		if ( empty( $rows ) ) {
			return [];
		}

		$ids = array_map( static fn( $row ) => (int) $row['post_id'], $rows );

		return get_posts( [
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'post__in'       => $ids,
			'orderby'        => 'post__in',
			'posts_per_page' => count( $ids ),
		] );
	}

	/** Catalog entry (label/description/slugs/section) for a key, or null if unknown. */
	public static function get_card_meta( string $key ): ?array {
		return tvf_get_homepage_catalog_entry( $key );
	}
}
