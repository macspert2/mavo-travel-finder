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

	/**
	 * Top published posts by views for a specific calendar month.
	 *
	 * @param string $month_date First-of-month date, e.g. '2025-06-01'.
	 * @return WP_Post[]
	 */
	public static function get_top_posts_for_month( string $month_date, int $limit = 6 ): array {
		global $wpdb;
		$table = $wpdb->prefix . 'rpp_monthly_snapshots';

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
		] );
	}

	/** First-of-month date string for "this calendar month, one year ago". */
	public static function same_month_last_year(): string {
		return gmdate( 'Y-m-01', strtotime( '-1 year' ) );
	}
}
