<?php
defined( 'ABSPATH' ) || exit;

/**
 * Reads historical monthly view-count snapshots from
 * `{$wpdb->prefix}rpp_monthly_snapshots` — a pre-existing table from a
 * separate, already-installed stats plugin, unrelated to
 * wp_tvf_post_filter. Columns: post_id, snapshot_month (date, always the
 * 1st of the month), views. post_id = 0 rows are site-wide totals, not a
 * specific post, and are always excluded here.
 */
class TVF_Popular_Snapshots {

	public static function table(): string {
		global $wpdb;

		return $wpdb->prefix . 'rpp_monthly_snapshots';
	}

	/**
	 * Is the snapshot table actually there?
	 *
	 * It belongs to another plugin, so this one cannot assume it exists —
	 * deactivate that plugin, or restore a database without it, and every
	 * query below becomes a MySQL error logged on a page a visitor is reading.
	 * The callers already tolerate an empty result (get_most_viewed() is the
	 * documented fallback), so the missing table simply becomes one.
	 *
	 * Cached for the request: the check is one SHOW TABLES, but the callers
	 * run per card on the homepage.
	 */
	public static function available(): bool {
		static $available = null;

		if ( null !== $available ) {
			return $available;
		}

		global $wpdb;
		$table = self::table();

		return $available = ( (string) $wpdb->get_var(
			$wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) )
		) === $table );
	}

	/**
	 * Top published posts by views for a specific calendar month, in a
	 * given language. The snapshot table itself has no language column,
	 * so filtering happens via Polylang's `lang` WP_Query/get_posts arg —
	 * meaning a post excluded by language doesn't get backfilled from
	 * further down the ranking; this can return fewer than $limit posts,
	 * same tolerance as TVF_Store::resolve_posts_for_slugs().
	 *
	 * @param string $month_date First-of-month date, e.g. '2025-06-01'.
	 * @return WP_Post[]
	 */
	public static function get_top_posts_for_month( string $month_date, string $lang = 'fr', int $limit = 6 ): array {
		if ( ! self::available() ) {
			return [];
		}

		global $wpdb;
		$table = self::table();

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT post_id, views FROM {$table}
				 WHERE snapshot_month = %s AND post_id != 0
				 ORDER BY views DESC
				 LIMIT %d",
				$month_date,
				$limit
			),
			ARRAY_A
		);

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
			'lang'           => $lang,
		] );
	}

	/** First-of-month date string for "this calendar month, one year ago". */
	public static function same_month_last_year(): string {
		return gmdate( 'Y-m-01', strtotime( '-1 year' ) );
	}

	/**
	 * Top published posts by *recent* view count (wp_postmeta
	 * meta_key='views'), filtered by language — a fallback for when
	 * "last year, same month" has no snapshot data at all. Tried and
	 * replaced an earlier "most recent snapshot month" fallback that
	 * still relied on the same thin snapshot table and could return as
	 * little as 1 EN / 0 DE post after language filtering; this draws on
	 * the `views` meta instead, which doesn't depend on the snapshot
	 * table's (or wp_tvf_post_filter's) language coverage at all.
	 *
	 * This docblock said "all-time view count", twice, and that is not what
	 * the meta holds. `views` is maintained by recent-post-popularity as a
	 * rolling ~90-day total, recomputed daily, and a post with no hits in
	 * that window is actively reset to 0 — so an article that was hugely
	 * read three years ago ranks here below one nobody has ever opened.
	 * That is defensible for a "what is popular now" fallback, which is what
	 * this is; it is not a lifetime ranking, and nothing should be built on
	 * the assumption that it is.
	 *
	 * Overfetches a generous top-200 by views before language-filtering,
	 * since most of that 200 will likely be French — narrowing first
	 * would risk losing EN/DE posts that rank lower site-wide but are
	 * still that language's most-viewed.
	 *
	 * @return WP_Post[]
	 */
	public static function get_most_viewed( string $lang = 'fr', int $limit = 6 ): array {
		global $wpdb;

		$ids = $wpdb->get_col(
			"SELECT p.ID
			 FROM {$wpdb->posts} p
			 JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = 'views'
			 WHERE p.post_status = 'publish' AND p.post_type = 'post'
			 ORDER BY CAST( pm.meta_value AS UNSIGNED ) DESC
			 LIMIT 200"
		);

		if ( empty( $ids ) ) {
			return [];
		}

		return get_posts( [
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'post__in'       => array_map( 'intval', $ids ),
			'orderby'        => 'post__in',
			'posts_per_page' => $limit,
			'lang'           => $lang,
		] );
	}
}
