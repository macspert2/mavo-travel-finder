<?php
defined( 'ABSPATH' ) || exit;

/**
 * Candidate homepage card ideas, grouped by the homepage section they could
 * appear in. This is a brainstorm catalog, not a "show all of these" list —
 * each homepage template part picks a curated subset of keys to render.
 *
 * Every `slugs` entry must reference real filter_slugs from
 * tvf_get_all_slugs(). When a card lists more than one slug they're
 * combined with AND (a post needs weight > 0 on every listed slug),
 * matching TVF_Store::query_results().
 */
function tvf_get_homepage_catalog(): array {
	return [
		'family_travel_themes' => [
			'bebe'              => [
				'label'       => __( 'Avec bébé', 'travel-finder' ),
				'description' => __( 'Nos conseils pour voyager avec un bébé.', 'travel-finder' ),
				'slugs'       => [ 'bebes' ],
			],
			'jeunes_enfants'    => [
				'label'       => __( 'Avec de jeunes enfants', 'travel-finder' ),
				'description' => __( 'Des idées de voyage adaptées aux petits.', 'travel-finder' ),
				'slugs'       => [ 'kids' ],
			],
			'ados'              => [
				'label'       => __( 'Avec des ados', 'travel-finder' ),
				'description' => __( 'Des destinations qui plaisent aussi aux ados.', 'travel-finder' ),
				'slugs'       => [ 'ados' ],
			],
			'weekend'           => [
				'label'       => __( 'Le temps d’un week-end', 'travel-finder' ),
				'description' => __( '2 à 4 jours, parfait pour une escapade courte.', 'travel-finder' ),
				'slugs'       => [ '2_3_jours' ],
			],
			'une_semaine'       => [
				'label'       => __( 'Une semaine en famille', 'travel-finder' ),
				'description' => __( 'Le format classique des vacances en famille.', 'travel-finder' ),
				'slugs'       => [ 'semaine' ],
			],
			'long_sejour'       => [
				'label'       => __( 'Plus d’une semaine', 'travel-finder' ),
				'description' => __( 'Pour les longs séjours et les grands voyages.', 'travel-finder' ),
				'slugs'       => [ 'plus' ],
			],
			'nature_rando'      => [
				'label'       => __( 'Nature & randonnée', 'travel-finder' ),
				'description' => __( 'Itinéraires de rando adaptés aux enfants.', 'travel-finder' ),
				'slugs'       => [ 'nature_rando' ],
			],
			'plage_cote'        => [
				'label'       => __( 'Plage & côte', 'travel-finder' ),
				'description' => __( 'Vacances au bord de l’eau en famille.', 'travel-finder' ),
				'slugs'       => [ 'plage_cote' ],
			],
			'activites_famille' => [
				'label'       => __( 'Activités en famille', 'travel-finder' ),
				'description' => __( 'Des activités à faire ensemble sur place.', 'travel-finder' ),
				'slugs'       => [ 'activites_famille' ],
			],
			'detente'           => [
				'label'       => __( 'Détente', 'travel-finder' ),
				'description' => __( 'Des séjours pensés pour se reposer.', 'travel-finder' ),
				'slugs'       => [ 'detente' ],
			],
			'roadtrip'          => [
				'label'       => __( 'Road trip', 'travel-finder' ),
				'description' => __( 'Voyager en van ou en road trip avec des enfants.', 'travel-finder' ),
				'slugs'       => [ 'roadtrip' ],
			],
			'velo'              => [
				'label'       => __( 'Vélo en famille', 'travel-finder' ),
				'description' => __( 'Itinéraires à vélo adaptés aux enfants.', 'travel-finder' ),
				'slugs'       => [ 'velo' ],
			],
			'voile'             => [
				'label'       => __( 'Voile en famille', 'travel-finder' ),
				'description' => __( 'Naviguer en famille.', 'travel-finder' ),
				'slugs'       => [ 'voile' ],
			],
			'gastronomie'       => [
				'label'       => __( 'Gastronomie', 'travel-finder' ),
				'description' => __( 'Des voyages gourmands en famille.', 'travel-finder' ),
				'slugs'       => [ 'gastronomie' ],
			],
			'culture_histoire'  => [
				'label'       => __( 'Culture & histoire', 'travel-finder' ),
				'description' => __( 'Découvrir l’histoire et la culture en famille.', 'travel-finder' ),
				'slugs'       => [ 'culture_histoire' ],
			],
			'shopping'          => [
				'label'       => __( 'Shopping', 'travel-finder' ),
				'description' => __( 'Des destinations shopping en famille.', 'travel-finder' ),
				'slugs'       => [ 'shopping' ],
			],
			'bebe_weekend'      => [
				'label'       => __( 'Bébé, le temps d’un week-end', 'travel-finder' ),
				'description' => __( 'Des courtes escapades adaptées aux tout-petits.', 'travel-finder' ),
				'slugs'       => [ 'bebes', '2_3_jours' ],
			],
			'ados_roadtrip'     => [
				'label'       => __( 'Road trip entre ados', 'travel-finder' ),
				'description' => __( 'Des road trips qui plaisent aux ados.', 'travel-finder' ),
				'slugs'       => [ 'ados', 'roadtrip' ],
			],
			'budget_eco'        => [
				'label'       => __( 'Petit budget', 'travel-finder' ),
				'description' => __( 'Voyager en famille sans se ruiner.', 'travel-finder' ),
				'slugs'       => [ 'economique' ],
			],
			'budget_medium'     => [
				'label'       => __( 'Budget moyen', 'travel-finder' ),
				'description' => __( 'Un bon équilibre entre confort et prix.', 'travel-finder' ),
				'slugs'       => [ 'medium' ],
			],
			'budget_premium'    => [
				'label'       => __( 'Voyage haut de gamme', 'travel-finder' ),
				'description' => __( 'Pour les séjours plus confortables.', 'travel-finder' ),
				'slugs'       => [ 'eleve' ],
			],
		],

		'seasonal_guides'       => [
			'hiver'        => [
				'label'       => __( 'Hiver', 'travel-finder' ),
				'description' => __( 'Idées de voyage pour l’hiver.', 'travel-finder' ),
				'slugs'       => [ 'hiver' ],
			],
			'printemps'    => [
				'label'       => __( 'Printemps', 'travel-finder' ),
				'description' => __( 'Idées de voyage pour le printemps.', 'travel-finder' ),
				'slugs'       => [ 'printemps' ],
			],
			'ete'          => [
				'label'       => __( 'Été', 'travel-finder' ),
				'description' => __( 'Idées de voyage pour l’été.', 'travel-finder' ),
				'slugs'       => [ 'ete' ],
			],
			'automne'      => [
				'label'       => __( 'Automne', 'travel-finder' ),
				'description' => __( 'Idées de voyage pour l’automne.', 'travel-finder' ),
				'slugs'       => [ 'automne' ],
			],
			'ete_plage'    => [
				'label'       => __( 'Été à la plage', 'travel-finder' ),
				'description' => __( 'Nos meilleures destinations plage pour l’été.', 'travel-finder' ),
				'slugs'       => [ 'ete', 'plage_cote' ],
			],
			'hiver_nature' => [
				'label'       => __( 'Hiver au grand air', 'travel-finder' ),
				'description' => __( 'Nature et randonnée pendant l’hiver.', 'travel-finder' ),
				'slugs'       => [ 'hiver', 'nature_rando' ],
			],
		],

		'featured_destinations' => [
			'france'        => [
				'label'       => __( 'France', 'travel-finder' ),
				'description' => __( 'Nos meilleures idées pour voyager en France en famille.', 'travel-finder' ),
				'slugs'       => [ 'france' ],
			],
			'angleterre'    => [
				'label'       => __( 'Angleterre', 'travel-finder' ),
				'description' => __( 'Vivre et voyager en Angleterre avec des enfants.', 'travel-finder' ),
				'slugs'       => [ 'angleterre' ],
			],
			'mediterranee'  => [
				'label'       => __( 'Méditerranée', 'travel-finder' ),
				'description' => __( 'Soleil et mer pour les vacances en famille.', 'travel-finder' ),
				'slugs'       => [ 'mediterranee' ],
			],
			'europe'        => [
				'label'       => __( 'Europe', 'travel-finder' ),
				'description' => __( 'Destinations européennes testées en famille.', 'travel-finder' ),
				'slugs'       => [ 'europe' ],
			],
			'sans_decalage' => [
				'label'       => __( 'Sans décalage horaire', 'travel-finder' ),
				'description' => __( 'Près de chez vous ou à l’autre bout du monde, sans décalage horaire à gérer.', 'travel-finder' ),
				'slugs'       => [ 'sans_decalage' ],
			],
			'plus_loin'     => [
				'label'       => __( 'Plus loin', 'travel-finder' ),
				'description' => __( 'Pour les familles prêtes à voyager plus loin.', 'travel-finder' ),
				'slugs'       => [ 'plus_loin' ],
			],
		],
	];
}

/** Returns a single catalog entry (plus its section + key), or null if unknown. */
function tvf_get_homepage_catalog_entry( string $key ): ?array {
	foreach ( tvf_get_homepage_catalog() as $section => $entries ) {
		if ( isset( $entries[ $key ] ) ) {
			return $entries[ $key ] + [
				'section' => $section,
				'key'     => $key,
			];
		}
	}
	return null;
}
