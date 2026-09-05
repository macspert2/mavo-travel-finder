<?php
defined( 'ABSPATH' ) || exit;

/**
 * Returns the 6 categories × 32 filters registry.
 * Slugs here must match CSV column headers exactly.
 */
function tvf_get_registry(): array {
	return [
		'interet' => [
			'label'   => __( 'Intérêt', 'travel-finder' ),
			'order'   => 1,
			'filters' => [
				'plage_cote'        => __( '🏖️ Plage & côte', 'travel-finder' ),
				'nature_rando'      => __( '🥾 Nature & randonnée', 'travel-finder' ),
				'gastronomie'       => __( '🍽️ Gastronomie', 'travel-finder' ),
				'culture_histoire'  => __( '🏛️ Culture & histoire', 'travel-finder' ),
				'velo'              => __( '🚴 Vélo', 'travel-finder' ),
				'voile'             => __( '⛵ Voile', 'travel-finder' ),
				'campervan'         => __( '🚐 Campervan', 'travel-finder' ),
				'ski'               => __( '⛷️ Ski', 'travel-finder' ),
				'activites_famille' => __( '🎡 Activités en famille', 'travel-finder' ),
				'detente'           => __( '🧘 Détente', 'travel-finder' ),
				'shopping'          => __( '🛍️ Shopping', 'travel-finder' ),
				'roadtrip'          => __( '🚗 Road trip', 'travel-finder' ),
				'citytrip'          => __( '🏙️ City trip', 'travel-finder' ),
			],
		],
		'saison' => [
			'label'   => __( 'Saison', 'travel-finder' ),
			'order'   => 2,
			// Single-choice: results are ANDed, so two seasons at once can only ever
			// return zero posts. Picking a season swaps the previous one out.
			'single'  => true,
			'hint'    => __( 'une seule saison à la fois', 'travel-finder' ),
			// The season a family travels in outranks every other criterion, so its
			// weight counts double when posts are scored (see TVF_Store::query_results).
			'score_multiplier' => 2,
			'filters' => [
				'hiver'     => __( '❄️ Hiver', 'travel-finder' ),
				'printemps' => __( '🌸 Printemps', 'travel-finder' ),
				'ete'       => __( '☀️ Été', 'travel-finder' ),
				'automne'   => __( '🍂 Automne', 'travel-finder' ),
			],
		],
		'duree' => [
			'label'   => __( 'Durée', 'travel-finder' ),
			'order'   => 3,
			'single'  => true,
			'hint'    => __( 'une seule durée à la fois', 'travel-finder' ),
			'filters' => [
				'2_3_jours' => __( '2–4 jours', 'travel-finder' ),
				'semaine'   => __( '1 semaine', 'travel-finder' ),
				'plus'      => __( "Plus d'une semaine", 'travel-finder' ),
			],
		],
		'budget' => [
			'label'   => __( 'Budget', 'travel-finder' ),
			'order'   => 4,
			'filters' => [
				'economique' => __( '🪙 Économique', 'travel-finder' ),
				'medium'     => __( '💶 Moyen', 'travel-finder' ),
				'eleve'      => __( '💎 Élevé', 'travel-finder' ),
			],
		],
		'age_enfants' => [
			'label'   => __( 'Âge des enfants', 'travel-finder' ),
			'order'   => 5,
			'filters' => [
				'bebes' => __( '👶 Bébés', 'travel-finder' ),
				'kids'  => __( '🧒 Enfants', 'travel-finder' ),
				'ados'  => __( '🎧 Ados', 'travel-finder' ),
			],
		],
		'geographie' => [
			'label'   => __( 'Géographie', 'travel-finder' ),
			'order'   => 6,
			'filters' => [
				'france'        => __( '🇫🇷 France', 'travel-finder' ),
				'angleterre'    => __( '🇬🇧 Angleterre', 'travel-finder' ),
				'mediterranee'  => __( '🏝️ Méditerranée', 'travel-finder' ),
				'europe'        => __( '🗺️ Europe', 'travel-finder' ),
				'sans_decalage' => __( '🌐 Peu de décalage horaire', 'travel-finder' ),
				'plus_loin'     => __( '✈️ Plus loin', 'travel-finder' ),
			],
		],
	];
}

/** Flat list of all 29 filter slugs in CSV column order. */
function tvf_get_all_slugs(): array {
	static $cache = null;
	if ( $cache !== null ) {
		return $cache;
	}
	$cache = [];
	foreach ( tvf_get_registry() as $cat ) {
		foreach ( array_keys( $cat['filters'] ) as $slug ) {
			$cache[] = $slug;
		}
	}
	return $cache;
}

/** Returns [ slug => label ] flat map. */
function tvf_get_slug_labels(): array {
	static $cache = null;
	if ( $cache !== null ) {
		return $cache;
	}
	$cache = [];
	foreach ( tvf_get_registry() as $cat ) {
		foreach ( $cat['filters'] as $slug => $label ) {
			$cache[ $slug ] = $label;
		}
	}
	return $cache;
}

/**
 * Returns [ slug => multiplier ] for filters whose category carries a
 * 'score_multiplier'. Filters of every other category are absent — the
 * scoring query treats a missing entry as a multiplier of 1.
 */
function tvf_get_slug_score_multipliers(): array {
	static $cache = null;
	if ( $cache !== null ) {
		return $cache;
	}
	$cache = [];
	foreach ( tvf_get_registry() as $cat ) {
		$mult = (int) ( $cat['score_multiplier'] ?? 1 );
		if ( $mult === 1 ) {
			continue;
		}
		foreach ( array_keys( $cat['filters'] ) as $slug ) {
			$cache[ $slug ] = $mult;
		}
	}
	return $cache;
}

/**
 * Returns [ slug => category_slug ] for every filter that lives in a
 * single-choice category (one selection at a time — see 'single' in the
 * registry). Filters of every other category are absent from this map.
 */
function tvf_get_single_choice_map(): array {
	static $cache = null;
	if ( $cache !== null ) {
		return $cache;
	}
	$cache = [];
	foreach ( tvf_get_registry() as $cat_slug => $cat ) {
		if ( empty( $cat['single'] ) ) {
			continue;
		}
		foreach ( array_keys( $cat['filters'] ) as $slug ) {
			$cache[ $slug ] = $cat_slug;
		}
	}
	return $cache;
}

/**
 * Enforces the single-choice rule: at most one selected filter per
 * single-choice category, the last one listed winning. Results are ANDed,
 * so two seasons (or two durations) at once could only ever match zero
 * posts — an old bookmark or cookie holding such a pair is repaired here
 * rather than showing an empty page.
 *
 * @param string[] $slugs
 * @return string[]
 */
function tvf_normalize_selection( array $slugs ): array {
	$single = tvf_get_single_choice_map();
	$keep   = [];
	$out    = [];

	// Walk backwards so the last occurrence of a single-choice category wins.
	foreach ( array_reverse( $slugs ) as $slug ) {
		$cat = $single[ $slug ] ?? null;
		if ( null !== $cat ) {
			if ( isset( $keep[ $cat ] ) ) {
				continue;
			}
			$keep[ $cat ] = true;
		}
		$out[] = $slug;
	}

	return array_reverse( $out );
}

/**
 * The selection that results from clicking $slug while $selected is active:
 * toggles it off if already on, otherwise adds it — replacing the current
 * pick of the same category when that category is single-choice.
 *
 * @param string[] $selected
 * @return string[]
 */
function tvf_toggle_selection( string $slug, array $selected ): array {
	if ( in_array( $slug, $selected, true ) ) {
		return array_values( array_diff( $selected, [ $slug ] ) );
	}

	$single = tvf_get_single_choice_map();
	$cat    = $single[ $slug ] ?? null;

	if ( null !== $cat ) {
		$selected = array_values( array_filter(
			$selected,
			static fn( $s ) => ( $single[ $s ] ?? null ) !== $cat
		) );
	}

	return array_merge( $selected, [ $slug ] );
}

/**
 * Parses a comma-separated `f` query value into validated filter_slugs.
 * Unknown slugs are dropped silently. Shared by TVF_Frontend and TVF_Focus
 * so both validate `f` the same way.
 *
 * @return string[]
 */
function tvf_parse_filter_param( string $f ): array {
	if ( '' === $f ) {
		return [];
	}
	$allowed = array_flip( tvf_get_all_slugs() );
	$out     = [];
	foreach ( explode( ',', $f ) as $slug ) {
		$slug = sanitize_key( trim( $slug ) );
		if ( isset( $allowed[ $slug ] ) ) {
			$out[] = $slug;
		}
	}
	return tvf_normalize_selection( array_values( array_unique( $out ) ) );
}
