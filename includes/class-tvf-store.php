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
	 * Runs the scoring query. Fetches BATCH+1 rows so callers can detect whether
	 * more results exist beyond the current page.
	 *
	 * @param string   $lang
	 * @param string[] $filter_slugs
	 * @param int      $offset       0-based row offset for pagination.
	 * @return array[] rows with keys: post_id, score, views
	 */
	public static function query_results( string $lang, array $filter_slugs, int $offset = 0 ): array {
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
					 LIMIT 43 OFFSET %d",
					$lang,
					$offset
				),
				ARRAY_A
			);
		}

		$count        = count( $filter_slugs );
		$placeholders = implode( ',', array_fill( 0, $count, '%s' ) );
		// $lang + N slugs + $count + $offset
		$args = array_merge( [ $lang ], $filter_slugs, [ $count, $offset ] );

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
				 HAVING COUNT(DISTINCT pf.filter_slug) = %d
				 ORDER BY score DESC, views DESC
				 LIMIT 43 OFFSET %d",
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
	// Dead-filter detection
	// -------------------------------------------------------------------------

	/**
	 * Returns slugs of unselected filters that would produce 0 results if added
	 * to the current selection. Used to grey out incompatible chips.
	 *
	 * @param string   $lang
	 * @param string[] $selected_slugs Currently active filters.
	 * @return string[] Dead (would-be-empty) filter slugs.
	 */
	public static function compute_dead_slugs( string $lang, array $selected_slugs ): array {
		sort( $selected_slugs ); // canonical order for cache key
		$cache_key = 'tvf_dead_' . $lang . '_' . md5( implode( ',', $selected_slugs ) );
		$cached    = get_transient( $cache_key );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		global $wpdb;
		$table      = self::table_name();
		$all_slugs  = tvf_get_all_slugs();
		$candidates = array_values( array_diff( $all_slugs, $selected_slugs ) );

		if ( empty( $candidates ) ) {
			$dead = [];
		} elseif ( empty( $selected_slugs ) ) {
			$cand_ph = implode( ',', array_fill( 0, count( $candidates ), '%s' ) );
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$alive = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT DISTINCT pf.filter_slug
					 FROM {$table} pf
					 JOIN {$wpdb->posts} p ON p.ID = pf.post_id
					    AND p.post_status = 'publish' AND p.post_type = 'post'
					 WHERE pf.lang = %s
					   AND pf.filter_slug IN ({$cand_ph})
					   AND pf.weight > 0",
					...array_merge( [ $lang ], $candidates )
				)
			) ?: [];
			$dead = array_values( array_diff( $candidates, $alive ) );
		} else {
			$cand_ph = implode( ',', array_fill( 0, count( $candidates ), '%s' ) );
			$sel_ph  = implode( ',', array_fill( 0, count( $selected_slugs ), '%s' ) );
			$args    = array_merge(
				[ $lang ],
				$candidates,
				[ $lang ],
				$selected_slugs,
				[ count( $selected_slugs ) ]
			);
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$alive = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT pf.filter_slug
					 FROM {$table} pf
					 JOIN {$wpdb->posts} p ON p.ID = pf.post_id
					    AND p.post_status = 'publish' AND p.post_type = 'post'
					 WHERE pf.lang = %s
					   AND pf.filter_slug IN ({$cand_ph})
					   AND pf.weight > 0
					   AND pf.post_id IN (
					       SELECT pf2.post_id
					       FROM {$table} pf2
					       WHERE pf2.lang = %s
					         AND pf2.filter_slug IN ({$sel_ph})
					         AND pf2.weight > 0
					       GROUP BY pf2.post_id
					       HAVING COUNT(DISTINCT pf2.filter_slug) = %d
					   )
					 GROUP BY pf.filter_slug",
					...$args
				)
			) ?: [];
			$dead = array_values( array_diff( $candidates, $alive ) );
		}

		set_transient( $cache_key, $dead, HOUR_IN_SECONDS );
		return $dead;
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

		foreach ( [ 'tvf_r3_', 'tvf_dead_' ] as $prefix_base ) {
			foreach ( [ '_transient_', '_transient_timeout_' ] as $type ) {
				$wpdb->query(
					$wpdb->prepare(
						"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
						$wpdb->esc_like( $type . $prefix_base . $lang ) . '%'
					)
				);
			}
		}
	}
}
