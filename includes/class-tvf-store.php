<?php
defined( 'ABSPATH' ) || exit;

class TVF_Store {

	/**
	 * Transient prefix for rendered result pages. Bumped whenever the ranking or
	 * the rendered markup changes, which retires every stale entry at once; the
	 * old ones lapse on their own one-hour TTL.
	 */
	const RESULT_CACHE_PREFIX = 'tvf_r7_';

	/** Per-language cache generation counter. See cache_key(). */
	const CACHE_GEN_OPTION_PREFIX = 'tvf_cache_gen_';

	/** Dead-slug set cache prefix; the sibling of RESULT_CACHE_PREFIX. */
	const DEAD_CACHE_PREFIX = 'tvf_dead_';

	/**
	 * Builds a transient key carrying the current cache generation for $lang.
	 *
	 * Invalidation works by bumping that generation rather than deleting rows.
	 * The old approach — DELETE FROM wp_options WHERE option_name LIKE
	 * 'prefix%' — ran on every post save and took next-key locks across every
	 * index entry it visited, on a table that every page load writes to
	 * (transients are 93% of its rows). That contention, not the scan, is what
	 * profiled at 16s per save and, with a wider lock, 136s.
	 *
	 * Bumping one integer locks exactly one row. Entries from an older
	 * generation become unreachable the instant it changes and lapse on their
	 * own HOUR_IN_SECONDS TTL, so nothing has to be swept on the hot path.
	 * It also behaves identically under a persistent object cache, where
	 * transients are not wp_options rows at all and the LIKE sweep silently
	 * cleared nothing.
	 */
	public static function cache_key( string $prefix, string $lang, string $suffix ): string {
		return $prefix . self::cache_generation( $lang ) . '_' . $lang . '_' . $suffix;
	}

	/** Current cache generation for a language. Starts at 1. */
	public static function cache_generation( string $lang ): int {
		return max( 1, (int) get_option( self::CACHE_GEN_OPTION_PREFIX . $lang, 1 ) );
	}

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
	 * The SUM() expression that scores a post, plus the placeholder values it needs.
	 *
	 * Filters from a category carrying a 'score_multiplier' count for more than
	 * their stored weight — Saison is ×2, so a post tagged "automne 2" contributes
	 * 4 to an autumn search and outranks posts that merely tolerate the season.
	 * Only the selected slugs reach the SUM, so an unselected category never
	 * enters the expression at all.
	 *
	 * @param string[] $filter_slugs Non-empty selection.
	 * @return array{0: string,1: array} [ SQL expression, prepare() args ]
	 */
	private static function score_expression( array $filter_slugs ): array {
		$boosts = array_intersect_key(
			tvf_get_slug_score_multipliers(),
			array_flip( $filter_slugs )
		);

		if ( empty( $boosts ) ) {
			return [ 'SUM( pf.weight )', [] ];
		}

		$cases = '';
		$args  = [];
		foreach ( array_unique( $boosts ) as $multiplier ) {
			$slugs = array_keys( $boosts, $multiplier, true );
			$ph    = implode( ',', array_fill( 0, count( $slugs ), '%s' ) );
			$cases .= " WHEN pf.filter_slug IN ({$ph}) THEN %d";
			$args   = array_merge( $args, $slugs, [ (int) $multiplier ] );
		}

		return [ "SUM( pf.weight * CASE{$cases} ELSE 1 END )", $args ];
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
		[ $score_expr, $score_args ] = self::score_expression( $filter_slugs );
		// score CASE args + $lang + N slugs + $count + $offset
		$args = array_merge( $score_args, [ $lang ], $filter_slugs, [ $count, $offset ] );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT pf.post_id, {$score_expr} AS score,
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

	/**
	 * Ranked, published WP_Post objects for a slug combination — same
	 * scoring as query_results(), resolved to post objects in one ordered
	 * query (post__in + orderby=post__in preserves the ranking). An empty
	 * $slugs list is valid here too: query_results() falls back to its
	 * top-by-views ranking, same as the [travel_finder] page with no
	 * filters selected.
	 *
	 * @param string[] $slugs
	 * @return WP_Post[]
	 */
	public static function resolve_posts_for_slugs( string $lang, array $slugs, int $limit = 9 ): array {
		$rows = self::query_results( $lang, $slugs, 0 );
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

	/**
	 * Every stored weight for one language, keyed post_id => slug => weight.
	 *
	 * One query rather than get_weights() per post: an export covers the whole
	 * table, and the per-post form would be a few thousand round trips.
	 *
	 * @return array<int, array<string,int>> Ordered by post_id ascending.
	 */
	public static function all_weights( string $lang ): array {
		global $wpdb;
		$table = self::table_name();

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT post_id, filter_slug, weight
				   FROM {$table}
				  WHERE lang = %s
				  ORDER BY post_id ASC",
				$lang
			),
			ARRAY_A
		);

		$out = [];
		foreach ( $rows ?: [] as $row ) {
			$out[ (int) $row['post_id'] ][ $row['filter_slug'] ] = (int) $row['weight'];
		}

		return $out;
	}

	/**
	 * How many posts carry weights, per language — for the export UI, so the
	 * operator can see there is something to download before clicking.
	 *
	 * @return array<string,int> Keyed by lang; languages with no rows are absent.
	 */
	public static function count_weighted_posts(): array {
		global $wpdb;
		$table = self::table_name();

		$rows = $wpdb->get_results(
			"SELECT lang, COUNT(DISTINCT post_id) AS n FROM {$table} GROUP BY lang",
			ARRAY_A
		);

		$out = [];
		foreach ( $rows ?: [] as $row ) {
			$out[ (string) $row['lang'] ] = (int) $row['n'];
		}

		return $out;
	}

	// -------------------------------------------------------------------------
	// Write
	// -------------------------------------------------------------------------

	/**
	 * Upserts a post's filter weights and busts the result cache — but only
	 * for the weights that actually changed.
	 *
	 * This runs from save_post on every post save carrying the metabox nonce,
	 * whether or not the editor touched a single filter. It used to REPLACE
	 * one row per registered slug and then sweep wp_options unconditionally,
	 * which profiled at ~16s of a single real save. The overwhelming majority
	 * of saves change nothing here, so the diff below reduces them to one
	 * SELECT and no writes at all.
	 *
	 * @param bool $bust Pass false when the caller sweeps the cache itself
	 *                   after a batch, so an import of N rows sweeps
	 *                   wp_options once rather than N times.
	 */
	public static function save_weights( int $post_id, string $lang, array $weights, bool $bust = true ): void {
		global $wpdb;
		$table   = self::table_name();
		$allowed = array_flip( tvf_get_all_slugs() );

		// Normalise before comparing: the column is a clamped int and
		// get_weights() returns strings, so the two sides have to be made
		// like-for-like or every save looks like a change.
		$incoming = [];
		foreach ( $weights as $slug => $weight ) {
			$slug = (string) $slug;
			if ( isset( $allowed[ $slug ] ) ) {
				$incoming[ $slug ] = max( 0, min( 2, (int) $weight ) );
			}
		}

		if ( ! $incoming ) {
			return;
		}

		$current = array_map( 'intval', self::get_weights( $post_id, $lang ) );

		$changed = [];
		foreach ( $incoming as $slug => $weight ) {
			if ( ! array_key_exists( $slug, $current ) || $current[ $slug ] !== $weight ) {
				$changed[ $slug ] = $weight;
			}
		}

		if ( ! $changed ) {
			return;
		}

		foreach ( $changed as $slug => $weight ) {
			$wpdb->replace(
				$table,
				[
					'post_id'     => $post_id,
					'lang'        => $lang,
					'filter_slug' => $slug,
					'weight'      => $weight,
				],
				[ '%d', '%s', '%s', '%d' ]
			);
		}

		if ( $bust ) {
			self::bust_cache( $lang );
		}
	}

	/**
	 * Copies every French post's filter weights to its English/German
	 * Polylang translations, where a translation exists. English/German
	 * posts are translations of the French originals, so the same
	 * weights genuinely apply — this is a deliberate copy, not a
	 * query-time join through Polylang's translation tables, since the
	 * data barely changes and a copy keeps every existing query
	 * (query_results(), resolve_posts_for_slugs(), etc.) working for
	 * en/de with no code changes at all.
	 *
	 * Safe to re-run — upserts via save_weights(), so existing EN/DE rows
	 * are simply overwritten with the current French values. That also
	 * means any weights manually edited directly on an EN/DE post will
	 * be overwritten the next time this runs.
	 *
	 * @return array{synced:int, fr_posts_checked:int, languages:array{en:int,de:int}}
	 */
	public static function sync_translations(): array {
		$languages = [ 'en' => 0, 'de' => 0 ];

		if ( ! function_exists( 'pll_get_post' ) ) {
			return [ 'synced' => 0, 'fr_posts_checked' => 0, 'languages' => $languages ];
		}

		global $wpdb;
		$table = self::table_name();

		$fr_post_ids = $wpdb->get_col(
			$wpdb->prepare( "SELECT DISTINCT post_id FROM {$table} WHERE lang = %s", 'fr' )
		);

		$synced = 0;

		foreach ( $fr_post_ids as $fr_post_id ) {
			$fr_post_id = (int) $fr_post_id;
			$weights    = self::get_weights( $fr_post_id, 'fr' );

			if ( empty( $weights ) ) {
				continue;
			}

			foreach ( [ 'en', 'de' ] as $lang ) {
				$translated_id = pll_get_post( $fr_post_id, $lang );

				if ( ! $translated_id || ! get_post( $translated_id ) ) {
					continue;
				}

				self::save_weights( (int) $translated_id, $lang, $weights, false );
				++$synced;
				++$languages[ $lang ];
			}
		}

		// One sweep per language after the whole run, rather than one per
		// post written — this loop can touch every translated post on the site.
		foreach ( [ 'en', 'de' ] as $lang ) {
			if ( $languages[ $lang ] > 0 ) {
				self::bust_cache( $lang );
			}
		}

		return [
			'synced'           => $synced,
			'fr_posts_checked' => count( $fr_post_ids ),
			'languages'        => $languages,
		];
	}

	// -------------------------------------------------------------------------
	// Dead-filter detection
	// -------------------------------------------------------------------------

	/**
	 * Returns slugs of unselected filters that clicking would leave with 0 results.
	 * Used to grey out incompatible chips.
	 *
	 * Chips of a single-choice category (saison, durée) replace the current pick of
	 * that same category rather than adding to it, so they are judged against the
	 * selection they would actually produce — without it, picking "été" would grey
	 * out every other season, which are precisely the chips still worth clicking.
	 *
	 * @param string   $lang
	 * @param string[] $selected_slugs Currently active filters.
	 * @return string[] Dead (would-be-empty) filter slugs.
	 */
	public static function compute_dead_slugs( string $lang, array $selected_slugs ): array {
		$dead   = self::dead_slugs_for_context( $lang, $selected_slugs );
		$single = tvf_get_single_choice_map();

		// One extra pass per single-choice category that already has a pick: its
		// other chips are evaluated against the selection minus that pick.
		$picked_groups = [];
		foreach ( $selected_slugs as $slug ) {
			if ( isset( $single[ $slug ] ) ) {
				$picked_groups[ $single[ $slug ] ] = $slug;
			}
		}

		foreach ( $picked_groups as $group => $picked ) {
			$siblings = array_keys( array_filter( $single, static fn( $g ) => $g === $group ) );
			$reduced  = array_values( array_diff( $selected_slugs, [ $picked ] ) );
			$swapped  = self::dead_slugs_for_context( $lang, $reduced );

			// Drop this group's slugs from the base verdict, then re-add the ones
			// the swapped-selection pass still finds empty.
			$dead = array_values( array_diff( $dead, $siblings ) );
			$dead = array_merge( $dead, array_intersect( $siblings, $swapped ) );
		}

		return array_values( array_unique( $dead ) );
	}

	/**
	 * The raw "adding this slug yields nothing" verdict for one exact selection.
	 * Cached per selection; compute_dead_slugs() calls it once per context.
	 *
	 * @param string[] $selected_slugs
	 * @return string[]
	 */
	private static function dead_slugs_for_context( string $lang, array $selected_slugs ): array {
		sort( $selected_slugs ); // canonical order for cache key
		$cache_key = self::cache_key( self::DEAD_CACHE_PREFIX, $lang, md5( implode( ',', $selected_slugs ) ) );
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

	/**
	 * Returns the total count of matching published posts for a filter set.
	 * Mirrors the WHERE/HAVING logic of query_results() but runs COUNT(*)
	 * instead of fetching rows, so it is always exact regardless of offset.
	 *
	 * @param string   $lang
	 * @param string[] $filter_slugs
	 * @return int
	 */
	public static function count_results( string $lang, array $filter_slugs ): int {
		global $wpdb;
		$table = self::table_name();

		if ( empty( $filter_slugs ) ) {
			return (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(DISTINCT pf.post_id)
					 FROM {$table} pf
					 JOIN {$wpdb->posts} p ON p.ID = pf.post_id
					    AND p.post_status = 'publish' AND p.post_type = 'post'
					 WHERE pf.lang = %s",
					$lang
				)
			);
		}

		$count        = count( $filter_slugs );
		$placeholders = implode( ',', array_fill( 0, $count, '%s' ) );
		$args         = array_merge( [ $lang ], $filter_slugs, [ $count ] );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM (
				     SELECT pf.post_id
				     FROM {$table} pf
				     JOIN {$wpdb->posts} p ON p.ID = pf.post_id
				        AND p.post_status = 'publish' AND p.post_type = 'post'
				     WHERE pf.lang = %s
				       AND pf.filter_slug IN ({$placeholders})
				       AND pf.weight > 0
				     GROUP BY pf.post_id
				     HAVING COUNT(DISTINCT pf.filter_slug) = %d
				 ) AS matched",
				...$args
			)
		);
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

	/**
	 * Invalidates every cached result page and dead-slug set for one language.
	 *
	 * O(1): one option row, whatever the cache holds. Safe to call on every
	 * save. See cache_key() for why this replaced a LIKE sweep of wp_options.
	 *
	 * The counter only has to *change*, so two concurrent bumps racing to the
	 * same value still invalidate correctly — no locking needed.
	 */
	public static function bust_cache( string $lang ): void {
		$option = self::CACHE_GEN_OPTION_PREFIX . $lang;

		// autoload = false: this is read on demand, never needed on every page.
		update_option( $option, self::cache_generation( $lang ) + 1, false );
	}

	/**
	 * Physically removes orphaned cache rows, every language and generation.
	 *
	 * Superseded rows are already unreachable and expire on their own, so this
	 * is housekeeping, not invalidation — it belongs on the manual "clear
	 * cache" button and cleanup routines, never on save_post.
	 *
	 * Not per-language by design: the generation now sits between the prefix
	 * and the language, so an anchored prefix match cannot select one language
	 * without also pinning a generation. Sweeping everything is what a manual
	 * purge wants anyway.
	 *
	 * Deliberately four separate statements. Collapsing them into one
	 * DELETE ... WHERE a LIKE %s OR a LIKE %s OR ... looks like an obvious win
	 * and is not: MySQL will not combine four OR'd prefix patterns into four
	 * ranges on the option_name index. EXPLAIN on this table:
	 *
	 *     one pattern   -> type=range, rows=1215
	 *     four OR'd     -> type=index, rows=8443   (full index scan)
	 *
	 * A range DELETE locks only the rows it visits; a full index scan locks
	 * essentially the whole option_name index for the length of the
	 * transaction. Keep each pattern anchored, with no leading wildcard.
	 */
	public static function purge_cache_rows(): void {
		global $wpdb;

		foreach ( [ self::RESULT_CACHE_PREFIX, self::DEAD_CACHE_PREFIX ] as $prefix_base ) {
			foreach ( [ '_transient_', '_transient_timeout_' ] as $type ) {
				$wpdb->query(
					$wpdb->prepare(
						"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
						$wpdb->esc_like( $type . $prefix_base ) . '%'
					)
				);
			}
		}
	}
}
