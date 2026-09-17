<?php
defined( 'ABSPATH' ) || exit;

class TVF_Importer {

	/**
	 * The CSV column order, shared by import and export so a file exported
	 * here re-imports unchanged. Column 0 is post_id; the rest follow
	 * tvf_get_all_slugs(), which is also what import_csv() reads positionally.
	 *
	 * @return string[]
	 */
	public static function csv_columns(): array {
		return array_merge( [ 'post_id' ], tvf_get_all_slugs() );
	}

	/**
	 * Streams every stored weight for one language as a CSV download.
	 *
	 * Emits the exact shape import_csv() expects — same header, same column
	 * order, missing weights written as 0 — so export → edit → import is a
	 * clean round trip.
	 *
	 * Writes to php://output and exits, so it must run before any markup: it
	 * is called from an admin_post_ handler, never from a page renderer.
	 *
	 * No UTF-8 BOM. Excel sometimes wants one, but it would corrupt the first
	 * header cell for anything parsing the file strictly, and every value here
	 * is an ASCII slug or an integer, so there is nothing for it to fix.
	 */
	public static function export_csv( string $lang = 'fr' ): void {
		$slugs   = tvf_get_all_slugs();
		$weights = TVF_Store::all_weights( $lang );

		$filename = sprintf( 'travel-finder-weights-%s-%s.csv', $lang, gmdate( 'Y-m-d' ) );

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=' . $filename );

		$out = fopen( 'php://output', 'w' );

		// Explicit $escape: it defaults to a backslash today but PHP 8.4 deprecates
		// relying on that, and '' is the value it is moving to. Every field here is
		// an integer or an ASCII slug, so there is nothing to escape either way —
		// but import and export must agree, so both sides pass it.
		fputcsv( $out, self::csv_columns(), ',', '"', '' );

		foreach ( $weights as $post_id => $row ) {
			$line = [ $post_id ];
			foreach ( $slugs as $slug ) {
				$line[] = $row[ $slug ] ?? 0;
			}
			fputcsv( $out, $line, ',', '"', '' );
		}

		fclose( $out );
		exit;
	}

	/**
	 * Maps the header row to slug => column index.
	 *
	 * Columns used to be read purely by position, with the header skipped
	 * unread. That silently mis-imports the moment the registry gains or loses
	 * a filter: every column after the new one shifts by one, and each post
	 * receives its neighbour's weight with no error anywhere. The header is in
	 * the file, so it decides — and a file whose header this cannot understand
	 * is rejected rather than guessed at.
	 *
	 * Unknown column names are ignored; slugs missing from the file are absent
	 * from the map, and the caller leaves those weights untouched rather than
	 * zeroing them.
	 *
	 * @param string[] $header
	 * @return array{map: array<string,int>, errors: string[]}
	 */
	private static function map_header( array $header ): array {
		$known = array_flip( tvf_get_all_slugs() );
		$map   = [];

		// A BOM on the first cell would otherwise hide 'post_id' from the
		// comparison below. Exports here are BOM-less, but files come back
		// through Excel.
		if ( isset( $header[0] ) ) {
			$header[0] = preg_replace( '/^\x{FEFF}/u', '', (string) $header[0] );
		}

		foreach ( $header as $index => $name ) {
			$name = sanitize_key( trim( (string) $name ) );

			if ( 0 === $index ) {
				if ( 'post_id' !== $name ) {
					return [
						'map'    => [],
						'errors' => [ __( 'La première colonne doit s\'appeler « post_id ».', 'travel-finder' ) ],
					];
				}
				continue;
			}

			if ( isset( $known[ $name ] ) && ! isset( $map[ $name ] ) ) {
				$map[ $name ] = $index;
			}
		}

		if ( ! $map ) {
			return [
				'map'    => [],
				'errors' => [ __( 'Aucune colonne de filtre reconnue dans l\'en-tête du fichier.', 'travel-finder' ) ],
			];
		}

		$missing = array_diff( tvf_get_all_slugs(), array_keys( $map ) );
		$errors  = [];

		if ( $missing ) {
			/* translators: %s: comma-separated list of filter slugs */
			$errors[] = sprintf(
				__( 'Colonnes absentes du fichier, laissées inchangées : %s.', 'travel-finder' ),
				implode( ', ', $missing )
			);
		}

		return [ 'map' => $map, 'errors' => $errors ];
	}

	/**
	 * Imports a CSV file into the tvf_post_filter table.
	 *
	 * Expected CSV:
	 *   Row 1: header — 'post_id' first, then one column per filter slug,
	 *          in any order (see map_header())
	 *   Col 0: post_id
	 *   Blank cell = 0
	 *
	 * A row whose post is not in $lang is skipped with an error rather than
	 * written: the table's primary key carries no language, so importing a
	 * French export with "de" selected would not add German rows, it would
	 * relabel the French ones and empty the French finder.
	 *
	 * @param string $file_path  Absolute path to the uploaded / temp CSV file.
	 * @param string $lang       Language code, e.g. 'fr'.
	 * @return array{imported:int, synced:int, errors:string[]}
	 */
	public static function import_csv( string $file_path, string $lang = 'fr' ): array {
		$handle = @fopen( $file_path, 'r' );
		if ( ! $handle ) {
			return [ 'imported' => 0, 'synced' => 0, 'errors' => [ __( 'Impossible d\'ouvrir le fichier.', 'travel-finder' ) ] ];
		}

		$errors    = [];
		$count     = 0;
		$row_num   = 0;
		$map       = null;
		$imported  = [];

		while ( ( $row = fgetcsv( $handle, 0, ',', '"', '' ) ) !== false ) {
			++$row_num;

			if ( 1 === $row_num ) {
				$header = self::map_header( $row );
				$errors = array_merge( $errors, $header['errors'] );

				if ( ! $header['map'] ) {
					fclose( $handle );
					return [ 'imported' => 0, 'synced' => 0, 'errors' => $errors ];
				}

				$map = $header['map'];
				continue;
			}

			// Skip blank / non-numeric post_id
			if ( ! isset( $row[0] ) || ! is_numeric( trim( $row[0] ) ) ) {
				continue;
			}

			$post_id = (int) $row[0];
			$post    = get_post( $post_id );

			if ( ! $post ) {
				/* translators: 1: row number, 2: post ID */
				$errors[] = sprintf( __( 'Ligne %1$d : l\'article %2$d est introuvable.', 'travel-finder' ), $row_num, $post_id );
				continue;
			}

			if ( 'publish' !== $post->post_status ) {
				/* translators: 1: row number, 2: post ID, 3: post status */
				$errors[] = sprintf( __( 'Ligne %1$d : l\'article %2$d n\'est pas publié (statut : %3$s).', 'travel-finder' ), $row_num, $post_id, $post->post_status );
				continue;
			}

			$post_lang = TVF_Store::post_lang( $post_id );

			if ( $post_lang !== $lang ) {
				/* translators: 1: row number, 2: post ID, 3: the post's language, 4: the language selected for the import */
				$errors[] = sprintf(
					__( 'Ligne %1$d : l\'article %2$d est en %3$s, pas en %4$s — ignoré.', 'travel-finder' ),
					$row_num,
					$post_id,
					$post_lang,
					$lang
				);
				continue;
			}

			$weights = [];
			foreach ( $map as $slug => $index ) {
				$raw              = isset( $row[ $index ] ) ? trim( (string) $row[ $index ] ) : '';
				$weights[ $slug ] = ( '' === $raw ) ? 0 : max( 0, min( 2, (int) $raw ) );
			}

			TVF_Store::save_weights( $post_id, $lang, $weights, false );
			$imported[] = $post_id;
			++$count;
		}

		fclose( $handle );

		// Single cache bust after all rows — save_weights() is called with
		// $bust = false above precisely so this is the only sweep.
		TVF_Store::bust_cache( $lang );

		// French weights are the source the translations are copied from, so an
		// import that changes them leaves EN/DE stale until this runs. Same
		// propagation every other write path performs, applied to exactly the
		// posts this file touched.
		$synced = 0;
		foreach ( $imported as $post_id ) {
			$synced += TVF_Store::sync_post( $post_id, $lang );
		}

		return [ 'imported' => $count, 'synced' => $synced, 'errors' => $errors ];
	}
}
