<?php
defined( 'ABSPATH' ) || exit;

class TVF_Store {

	public static function table_name(): string {
		global $wpdb;
		return $wpdb->prefix . 'tvf_post_filter';
	}

	public static function create_table(): void {
		global $wpdb;
		$table   = self::table_name();
		$charset = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			post_id     BIGINT UNSIGNED  NOT NULL,
			lang        VARCHAR(8)       NOT NULL DEFAULT 'fr',
			filter_slug VARCHAR(64)      NOT NULL,
			weight      TINYINT UNSIGNED NOT NULL DEFAULT 0,
			PRIMARY KEY  (post_id, filter_slug),
			KEY idx_lang_filter (lang, filter_slug),
			KEY idx_lang_weight (lang, weight)
		) ENGINE=InnoDB {$charset};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	// -------------------------------------------------------------------------
	// Read
	// -------------------------------------------------------------------------

	/** Returns [ filter_slug => weight ] for a given post + lang. */
	public static function get_weights( int $post_id, string $lang = 'fr' ): array {
		global $wpdb;
		$table = self::table_name();

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT filter_slug, weight FROM {$table} WHERE post_id = %d AND lang = %s",
				$post_id,
				$lang
			),
			ARRAY_A
		);

		return array_column( $rows, 'weight', 'filter_slug' );
	}

	/**
	 * Runs the scoring query.
	 * With no slugs: top 16 travel posts by views.
	 * With slugs: posts that match at least one filter, ranked score DESC then views DESC.
	 *
	 * @param string   $lang
	 * @param string[] $filter_slugs
	 * @return array[] rows with keys: post_id, score, views
	 */
	public static function query_results( string $lang, array $filter_slugs ): array {
		global $wpdb;
		$table = self::table_name();

		if ( empty( $filter_slugs ) ) {
			return $wpdb->get_results(
				$wpdb->prepare(
					"SELECT pf.post_id, 0 AS score,
					    CAST( COALESCE( pm.meta_value, 0 ) AS UNSIGNED ) AS views
					 FROM {$table} pf
					 JOIN {$wpdb->posts} p
					     ON p.ID = pf.post_id
					    AND p.post_status = 'publish'
					    AND p.post_type  = 'post'
					 LEFT JOIN {$wpdb->postmeta} pm
					     ON pm.post_id = pf.post_id AND pm.meta_key = 'views'
					 WHERE pf.lang = %s
					 GROUP BY pf.post_id
					 ORDER BY views DESC
					 LIMIT 16",
					$lang
				),
				ARRAY_A
			);
		}

		$placeholders = implode( ',', array_fill( 0, count( $filter_slugs ), '%s' ) );
		$args         = array_merge( [ $lang ], $filter_slugs );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT pf.post_id, SUM( pf.weight ) AS score,
				    CAST( COALESCE( pm.meta_value, 0 ) AS UNSIGNED ) AS views
				 FROM {$table} pf
				 JOIN {$wpdb->posts} p
				     ON p.ID = pf.post_id
				    AND p.post_status = 'publish'
				    AND p.post_type  = 'post'
				 LEFT JOIN {$wpdb->postmeta} pm
				     ON pm.post_id = pf.post_id AND pm.meta_key = 'views'
				 WHERE pf.lang = %s
				   AND pf.filter_slug IN ({$placeholders})
				   AND pf.weight > 0
				 GROUP BY pf.post_id
				 HAVING score > 0
				 ORDER BY score DESC, views DESC
				 LIMIT 16",
				...$args
			),
			ARRAY_A
		);
	}

	// -------------------------------------------------------------------------
	// Write
	// -------------------------------------------------------------------------

	/** Upserts all weights for a post+lang and busts the result cache. */
	public static function save_weights( int $post_id, string $lang, array $weights ): void {
		global $wpdb;
		$table   = self::table_name();
		$allowed = array_flip( tvf_get_all_slugs() );

		foreach ( $weights as $slug => $weight ) {
			$slug = (string) $slug;
			if ( ! isset( $allowed[ $slug ] ) ) {
				continue;
			}
			$wpdb->replace(
				$table,
				[
					'post_id'     => $post_id,
					'lang'        => $lang,
					'filter_slug' => $slug,
					'weight'      => max( 0, min( 2, (int) $weight ) ),
				],
				[ '%d', '%s', '%s', '%d' ]
			);
		}

		self::bust_cache( $lang );
	}

	// -------------------------------------------------------------------------
	// Coverage
	// -------------------------------------------------------------------------

	/** Returns every published post with its count of configured filter rows. */
	public static function get_post_coverage( string $lang = 'fr' ): array {
		global $wpdb;
		$table         = self::table_name();
		$total_filters = count( tvf_get_all_slugs() );

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.ID, p.post_title,
				    COUNT( pf.filter_slug ) AS configured,
				    {$total_filters}        AS total
				 FROM {$wpdb->posts} p
				 LEFT JOIN {$table} pf
				     ON pf.post_id = p.ID AND pf.lang = %s
				 WHERE p.post_status = 'publish' AND p.post_type = 'post'
				 GROUP BY p.ID
				 ORDER BY configured ASC, p.post_title ASC",
				$lang
			),
			ARRAY_A
		);
	}

	// -------------------------------------------------------------------------
	// Cache
	// -------------------------------------------------------------------------

	public static function bust_cache( string $lang ): void {
		global $wpdb;

		// Works for both DB-based and APCu/Memcache transients because we delete the option rows.
		$prefix = '_transient_tvf_results_' . $lang;
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
				$wpdb->esc_like( $prefix ) . '%'
			)
		);
		$prefix_timeout = '_transient_timeout_tvf_results_' . $lang;
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
				$wpdb->esc_like( $prefix_timeout ) . '%'
			)
		);
	}
}
