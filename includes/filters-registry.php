<?php
defined( 'ABSPATH' ) || exit;

/**
 * Returns the 6 categories × 29 filters registry.
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
				'activites_famille' => __( '🎡 Activités en famille', 'travel-finder' ),
				'detente'           => __( '🧘 Détente', 'travel-finder' ),
				'shopping'          => __( '🛍️ Shopping', 'travel-finder' ),
				'roadtrip'          => __( '🚗 Road trip', 'travel-finder' ),
			],
		],
		'saison' => [
			'label'   => __( 'Saison', 'travel-finder' ),
			'order'   => 2,
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
			'filters' => [
				'2_3_jours' => __( '2–3 jours', 'travel-finder' ),
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
				'sans_decalage' => __( '🌐 Sans décalage horaire', 'travel-finder' ),
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
