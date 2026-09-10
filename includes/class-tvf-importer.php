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
	 * Imports a CSV file into the tvf_post_filter table.
	 *
	 * Expected CSV:
	 *   Row 1: header (skipped)
	 *   Col 0: post_id
	 *   Cols 1–29: weights in the canonical slug order from tvf_get_all_slugs()
	 *   Blank cell = 0
	 *
	 * @param string $file_path  Absolute path to the uploaded / temp CSV file.
	 * @param string $lang       Language code, e.g. 'fr'.
	 * @return array{imported:int, errors:string[]}
	 */
	public static function import_csv( string $file_path, string $lang = 'fr' ): array {
		$handle = @fopen( $file_path, 'r' );
		if ( ! $handle ) {
			return [ 'imported' => 0, 'errors' => [ __( 'Impossible d\'ouvrir le fichier.', 'travel-finder' ) ] ];
		}

		$slugs   = tvf_get_all_slugs();
		$errors  = [];
		$count   = 0;
		$row_num = 0;

		while ( ( $row = fgetcsv( $handle, 0, ',', '"', '' ) ) !== false ) {
			++$row_num;

			if ( $row_num === 1 ) {
				continue; // skip header
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
				/* translators: 1: row number, 2: post ID */
				$errors[] = sprintf( __( 'Ligne %1$d : l\'article %2$d n\'est pas publié (statut : %3$s).', 'travel-finder' ), $row_num, $post_id, $post->post_status );
				continue;
			}

			$weights = [];
			foreach ( $slugs as $i => $slug ) {
				$raw             = isset( $row[ $i + 1 ] ) ? trim( $row[ $i + 1 ] ) : '';
				$weights[ $slug ] = ( '' === $raw ) ? 0 : max( 0, min( 2, (int) $raw ) );
			}

			TVF_Store::save_weights( $post_id, $lang, $weights, false );
			++$count;
		}

		fclose( $handle );

		// Single cache bust after all rows — save_weights() is called with
		// $bust = false above precisely so this is the only sweep.
		TVF_Store::bust_cache( $lang );

		return [ 'imported' => $count, 'errors' => $errors ];
	}
}
