<?php
defined( 'ABSPATH' ) || exit;

class TVF_Importer {

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

		while ( ( $row = fgetcsv( $handle ) ) !== false ) {
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

			TVF_Store::save_weights( $post_id, $lang, $weights );
			++$count;
		}

		fclose( $handle );

		// Single cache bust after all rows (save_weights busts per-row; one final sweep).
		TVF_Store::bust_cache( $lang );

		return [ 'imported' => $count, 'errors' => $errors ];
	}
}
