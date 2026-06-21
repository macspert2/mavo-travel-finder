<?php
defined( 'ABSPATH' ) || exit;

/**
 * Candidate homepage card ideas, grouped by the homepage section they could
 * appear in. This is a brainstorm catalog, not a "show all of these" list —
 * each homepage template part picks a curated subset of keys to render.
 *
 * `label`/`description` are language-keyed arrays (`['fr' => ..., 'en' =>
 * ..., 'de' => ...]`), resolved via tvf_resolve_catalog_text() — not
 * gettext, since this project does per-language strings directly in code
 * rather than .po/.mo files (matches the theme's mv-home-en/mv-home-de
 * template parts). Only entries actually used on an EN/DE page need real
 * translations; anything with just 'fr' falls back to French until
 * translated — safe, just not yet localized.
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
				'label'       => [
					'fr' => 'Avec bébé',
					'en' => 'With a baby',
					'de' => 'Mit Baby',
				],
				'description' => [
					'fr' => 'Nos conseils pour voyager avec un bébé.',
					'en' => 'Our tips for travelling with a baby.',
					'de' => 'Unsere Tipps für Reisen mit einem Baby.',
				],
				'slugs'       => [ 'bebes' ],
			],
			'jeunes_enfants'    => [
				'label'       => [
					'fr' => 'Avec de jeunes enfants',
					'en' => 'With young children',
					'de' => 'Mit kleinen Kindern',
				],
				'description' => [
					'fr' => 'Des idées de voyage adaptées aux petits.',
					'en' => 'Travel ideas suited to little ones.',
					'de' => 'Reiseideen für die Kleinen.',
				],
				'slugs'       => [ 'kids' ],
			],
			'ados'              => [
				'label'       => [
					'fr' => 'Avec des ados',
					'en' => 'With teens',
					'de' => 'Mit Teenagern',
				],
				'description' => [
					'fr' => 'Des destinations qui plaisent aussi aux ados.',
					'en' => 'Destinations that teens enjoy too.',
					'de' => 'Reiseziele, die auch Teenagern gefallen.',
				],
				'slugs'       => [ 'ados' ],
			],
			'weekend'           => [
				'label'       => [ 'fr' => 'Le temps d’un week-end' ],
				'description' => [ 'fr' => '2 à 4 jours, parfait pour une escapade courte.' ],
				'slugs'       => [ '2_3_jours' ],
			],
			'une_semaine'       => [
				'label'       => [ 'fr' => 'Une semaine en famille' ],
				'description' => [ 'fr' => 'Le format classique des vacances en famille.' ],
				'slugs'       => [ 'semaine' ],
			],
			'long_sejour'       => [
				'label'       => [ 'fr' => 'Plus d’une semaine' ],
				'description' => [ 'fr' => 'Pour les longs séjours et les grands voyages.' ],
				'slugs'       => [ 'plus' ],
			],
			'nature_rando'      => [
				'label'       => [ 'fr' => 'Nature & randonnée' ],
				'description' => [ 'fr' => 'Itinéraires de rando adaptés aux enfants.' ],
				'slugs'       => [ 'nature_rando' ],
			],
			'plage_cote'        => [
				'label'       => [ 'fr' => 'Plage & côte' ],
				'description' => [ 'fr' => 'Vacances au bord de l’eau en famille.' ],
				'slugs'       => [ 'plage_cote' ],
			],
			'activites_famille' => [
				'label'       => [ 'fr' => 'Activités en famille' ],
				'description' => [ 'fr' => 'Des activités à faire ensemble sur place.' ],
				'slugs'       => [ 'activites_famille' ],
			],
			'detente'           => [
				'label'       => [ 'fr' => 'Détente' ],
				'description' => [ 'fr' => 'Des séjours pensés pour se reposer.' ],
				'slugs'       => [ 'detente' ],
			],
			'roadtrip'          => [
				'label'       => [ 'fr' => 'Road trip' ],
				'description' => [ 'fr' => 'Voyager en van ou en road trip avec des enfants.' ],
				'slugs'       => [ 'roadtrip' ],
			],
			'velo'              => [
				'label'       => [ 'fr' => 'Vélo en famille' ],
				'description' => [ 'fr' => 'Itinéraires à vélo adaptés aux enfants.' ],
				'slugs'       => [ 'velo' ],
			],
			'voile'             => [
				'label'       => [ 'fr' => 'Voile en famille' ],
				'description' => [ 'fr' => 'Naviguer en famille.' ],
				'slugs'       => [ 'voile' ],
			],
			'gastronomie'       => [
				'label'       => [ 'fr' => 'Gastronomie' ],
				'description' => [ 'fr' => 'Des voyages gourmands en famille.' ],
				'slugs'       => [ 'gastronomie' ],
			],
			'culture_histoire'  => [
				'label'       => [ 'fr' => 'Culture & histoire' ],
				'description' => [ 'fr' => 'Découvrir l’histoire et la culture en famille.' ],
				'slugs'       => [ 'culture_histoire' ],
			],
			'shopping'          => [
				'label'       => [ 'fr' => 'Shopping' ],
				'description' => [ 'fr' => 'Des destinations shopping en famille.' ],
				'slugs'       => [ 'shopping' ],
			],
			'bebe_weekend'      => [
				'label'       => [ 'fr' => 'Bébé, le temps d’un week-end' ],
				'description' => [ 'fr' => 'Des courtes escapades adaptées aux tout-petits.' ],
				'slugs'       => [ 'bebes', '2_3_jours' ],
			],
			'ados_roadtrip'     => [
				'label'       => [ 'fr' => 'Road trip entre ados' ],
				'description' => [ 'fr' => 'Des road trips qui plaisent aux ados.' ],
				'slugs'       => [ 'ados', 'roadtrip' ],
			],
			'budget_eco'        => [
				'label'       => [ 'fr' => 'Budget serré' ],
				'description' => [ 'fr' => 'Voyager en famille sans se ruiner.' ],
				'slugs'       => [ 'economique' ],
			],
			'budget_medium'     => [
				'label'       => [ 'fr' => 'Bon rapport qualité-prix' ],
				'description' => [ 'fr' => 'Un bon équilibre entre confort et prix.' ],
				'slugs'       => [ 'medium' ],
			],
			'budget_premium'    => [
				'label'       => [ 'fr' => 'Se faire plaisir' ],
				'description' => [ 'fr' => 'Pour les séjours plus confortables.' ],
				'slugs'       => [ 'eleve' ],
			],
		],

		'seasonal_guides'       => [
			'hiver'        => [
				'label'       => [ 'fr' => 'Hiver' ],
				'description' => [ 'fr' => 'Idées de voyage pour l’hiver.' ],
				'slugs'       => [ 'hiver' ],
			],
			'printemps'    => [
				'label'       => [ 'fr' => 'Printemps' ],
				'description' => [ 'fr' => 'Idées de voyage pour le printemps.' ],
				'slugs'       => [ 'printemps' ],
			],
			'ete'          => [
				'label'       => [ 'fr' => 'Été' ],
				'description' => [ 'fr' => 'Idées de voyage pour l’été.' ],
				'slugs'       => [ 'ete' ],
			],
			'automne'      => [
				'label'       => [ 'fr' => 'Automne' ],
				'description' => [ 'fr' => 'Idées de voyage pour l’automne.' ],
				'slugs'       => [ 'automne' ],
			],
			'ete_plage'    => [
				'label'       => [ 'fr' => 'Été à la plage' ],
				'description' => [ 'fr' => 'Nos meilleures destinations plage pour l’été.' ],
				'slugs'       => [ 'ete', 'plage_cote' ],
			],
			'hiver_nature' => [
				'label'       => [ 'fr' => 'Hiver au grand air' ],
				'description' => [ 'fr' => 'Nature et randonnée pendant l’hiver.' ],
				'slugs'       => [ 'hiver', 'nature_rando' ],
			],
		],

		'featured_destinations' => [
			'france'        => [
				'label'       => [ 'fr' => 'France' ],
				'description' => [ 'fr' => 'Nos meilleures idées pour voyager en France en famille.' ],
				'slugs'       => [ 'france' ],
			],
			'angleterre'    => [
				'label'       => [ 'fr' => 'Angleterre' ],
				'description' => [ 'fr' => 'Vivre et voyager en Angleterre avec des enfants.' ],
				'slugs'       => [ 'angleterre' ],
			],
			'mediterranee'  => [
				'label'       => [ 'fr' => 'Méditerranée' ],
				'description' => [ 'fr' => 'Soleil et mer pour les vacances en famille.' ],
				'slugs'       => [ 'mediterranee' ],
			],
			'europe'        => [
				'label'       => [ 'fr' => 'Europe' ],
				'description' => [ 'fr' => 'Destinations européennes testées en famille.' ],
				'slugs'       => [ 'europe' ],
			],
			'sans_decalage' => [
				'label'       => [ 'fr' => 'Sans gros décalage horaire' ],
				'description' => [ 'fr' => 'Près de chez vous ou dans l’hémisphère sud, sans décalage horaire à gérer.' ],
				'slugs'       => [ 'sans_decalage' ],
			],
			'plus_loin'     => [
				'label'       => [ 'fr' => 'Plus loin' ],
				'description' => [ 'fr' => 'Pour les familles prêtes à voyager plus loin.' ],
				'slugs'       => [ 'plus_loin' ],
			],
		],
	];
}

/** Returns a single catalog entry (plus its section + key), or null if unknown. Label/description are still unresolved language arrays — see tvf_resolve_catalog_text(). */
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

/** Resolves a language-keyed catalog text field (label/description) to a single string, falling back to French if the requested language isn't translated yet. */
function tvf_resolve_catalog_text( array $field, string $lang ): string {
	return $field[ $lang ] ?? $field['fr'] ?? '';
}

/**
 * Which catalog keys the homepage "family travel themes" section shows,
 * across all 3 languages — configurable from the plugin's Réglages
 * admin page rather than hardcoded in each of the 3 theme template
 * parts (mv-home/, mv-home-en/, mv-home-de/ family-travel-themes.php).
 * Only bebe/jeunes_enfants/ados have real EN/DE translations today —
 * other catalog entries fall back to French text via
 * tvf_resolve_catalog_text() if selected.
 */
function tvf_get_family_travel_theme_keys(): array {
	$default = [ 'bebe', 'jeunes_enfants', 'ados' ];
	$saved   = get_option( 'tvf_family_travel_theme_keys', $default );
	return ( is_array( $saved ) && ! empty( $saved ) ) ? $saved : $default;
}
