<?php
defined( 'ABSPATH' ) || exit;

/**
 * The 6 categories × 32 filters registry, in its raw form: `label` and `hint`
 * are language-keyed arrays (`['fr' => …, 'en' => …, 'de' => …]`), not strings.
 *
 * Not gettext, and deliberately so — this plugin ships no .po/.mo files and
 * load_plugin_textdomain() points at a directory that does not exist, so every
 * __() here returned its French literal whatever the current locale. The
 * per-language array is the convention the rest of the project already uses
 * (homepage-catalog.php, TVF_Focus::text(), TVF_Frontend::format_count()).
 *
 * Callers want resolved strings and should use tvf_get_registry( $lang );
 * this raw form exists for the slug-only helpers below, which never look at a
 * label and so should not pay to resolve one.
 *
 * Slugs here must match CSV column headers exactly.
 */
function tvf_get_registry_raw(): array {
	return [
		'interet' => [
			'label'   => [
				'fr' => 'Intérêt',
				'en' => 'Interest',
				'de' => 'Interesse',
			],
			'order'   => 1,
			'filters' => [
				'plage_cote'        => [
					'fr' => '🏖️ Plage & côte',
					'en' => '🏖️ Beach & coast',
					'de' => '🏖️ Strand & Küste',
				],
				'nature_rando'      => [
					'fr' => '🥾 Nature & randonnée',
					'en' => '🥾 Nature & hiking',
					'de' => '🥾 Natur & Wandern',
				],
				'gastronomie'       => [
					'fr' => '🍽️ Gastronomie',
					'en' => '🍽️ Food & drink',
					'de' => '🍽️ Gastronomie',
				],
				'culture_histoire'  => [
					'fr' => '🏛️ Culture & histoire',
					'en' => '🏛️ Culture & history',
					'de' => '🏛️ Kultur & Geschichte',
				],
				'velo'              => [
					'fr' => '🚴 Vélo',
					'en' => '🚴 Cycling',
					'de' => '🚴 Radfahren',
				],
				'voile'             => [
					'fr' => '⛵ Voile',
					'en' => '⛵ Sailing',
					'de' => '⛵ Segeln',
				],
				'campervan'         => [
					'fr' => '🚐 Campervan',
					'en' => '🚐 Campervan',
					'de' => '🚐 Campervan',
				],
				'ski'               => [
					'fr' => '⛷️ Ski',
					'en' => '⛷️ Skiing',
					'de' => '⛷️ Ski',
				],
				'activites_famille' => [
					'fr' => '🎡 Activités en famille',
					'en' => '🎡 Family activities',
					'de' => '🎡 Familienaktivitäten',
				],
				'detente'           => [
					'fr' => '🧘 Détente',
					'en' => '🧘 Relaxation',
					'de' => '🧘 Entspannung',
				],
				'shopping'          => [
					'fr' => '🛍️ Shopping',
					'en' => '🛍️ Shopping',
					'de' => '🛍️ Shopping',
				],
				'roadtrip'          => [
					'fr' => '🚗 Road trip',
					'en' => '🚗 Road trip',
					'de' => '🚗 Road Trip',
				],
				'citytrip'          => [
					'fr' => '🏙️ City trip',
					'en' => '🏙️ City trip',
					'de' => '🏙️ City Trip',
				],
			],
		],
		'saison' => [
			'label'   => [
				'fr' => 'Saison',
				'en' => 'Season',
				'de' => 'Jahreszeit',
			],
			'order'   => 2,
			// Single-choice: results are ANDed, so two seasons at once can only ever
			// return zero posts. Picking a season swaps the previous one out.
			'single'  => true,
			'hint'    => [
				'fr' => 'une seule saison à la fois',
				'en' => 'one season at a time',
				'de' => 'nur eine Jahreszeit',
			],
			// The season a family travels in outranks every other criterion, so its
			// weight counts double when posts are scored (see TVF_Store::query_results).
			'score_multiplier' => 2,
			'filters' => [
				'hiver'     => [
					'fr' => '❄️ Hiver',
					'en' => '❄️ Winter',
					'de' => '❄️ Winter',
				],
				'printemps' => [
					'fr' => '🌸 Printemps',
					'en' => '🌸 Spring',
					'de' => '🌸 Frühling',
				],
				'ete'       => [
					'fr' => '☀️ Été',
					'en' => '☀️ Summer',
					'de' => '☀️ Sommer',
				],
				'automne'   => [
					'fr' => '🍂 Automne',
					'en' => '🍂 Autumn',
					'de' => '🍂 Herbst',
				],
			],
		],
		'duree' => [
			'label'   => [
				'fr' => 'Durée',
				'en' => 'Duration',
				'de' => 'Dauer',
			],
			'order'   => 3,
			'single'  => true,
			'hint'    => [
				'fr' => 'une seule durée à la fois',
				'en' => 'one duration at a time',
				'de' => 'nur eine Dauer',
			],
			'filters' => [
				'2_3_jours' => [
					'fr' => '2–4 jours',
					'en' => '2–4 days',
					'de' => '2–4 Tage',
				],
				'semaine'   => [
					'fr' => '1 semaine',
					'en' => '1 week',
					'de' => '1 Woche',
				],
				'plus'      => [
					'fr' => "Plus d'une semaine",
					'en' => 'More than a week',
					'de' => 'Mehr als eine Woche',
				],
			],
		],
		'budget' => [
			'label'   => [
				'fr' => 'Budget',
				'en' => 'Budget',
				'de' => 'Budget',
			],
			'order'   => 4,
			'filters' => [
				'economique' => [
					'fr' => '🪙 Économique',
					'en' => '🪙 Budget-friendly',
					'de' => '🪙 Günstig',
				],
				'medium'     => [
					'fr' => '💶 Moyen',
					'en' => '💶 Mid-range',
					'de' => '💶 Mittel',
				],
				'eleve'      => [
					'fr' => '💎 Élevé',
					'en' => '💎 High-end',
					'de' => '💎 Gehoben',
				],
			],
		],
		'age_enfants' => [
			'label'   => [
				'fr' => 'Âge des enfants',
				'en' => "Children's age",
				'de' => 'Alter der Kinder',
			],
			'order'   => 5,
			'filters' => [
				'bebes' => [
					'fr' => '👶 Bébés',
					'en' => '👶 Babies',
					'de' => '👶 Babys',
				],
				'kids'  => [
					'fr' => '🧒 Enfants',
					'en' => '🧒 Children',
					'de' => '🧒 Kinder',
				],
				'ados'  => [
					'fr' => '🎧 Ados',
					'en' => '🎧 Teens',
					'de' => '🎧 Teenager',
				],
			],
		],
		'geographie' => [
			'label'   => [
				'fr' => 'Géographie',
				'en' => 'Geography',
				'de' => 'Region',
			],
			'order'   => 6,
			'filters' => [
				'france'        => [
					'fr' => '🇫🇷 France',
					'en' => '🇫🇷 France',
					'de' => '🇫🇷 Frankreich',
				],
				'angleterre'    => [
					'fr' => '🇬🇧 Angleterre',
					'en' => '🇬🇧 England',
					'de' => '🇬🇧 England',
				],
				'mediterranee'  => [
					'fr' => '🏝️ Méditerranée',
					'en' => '🏝️ Mediterranean',
					'de' => '🏝️ Mittelmeer',
				],
				'europe'        => [
					'fr' => '🗺️ Europe',
					'en' => '🗺️ Europe',
					'de' => '🗺️ Europa',
				],
				'sans_decalage' => [
					'fr' => '🌐 Peu de décalage horaire',
					'en' => '🌐 Little jet lag',
					'de' => '🌐 Wenig Jetlag',
				],
				'plus_loin'     => [
					'fr' => '✈️ Plus loin',
					'en' => '✈️ Further afield',
					'de' => '✈️ Weiter weg',
				],
			],
		],
	];
}

/**
 * Resolves a language-keyed text field to a single string, falling back to
 * French when the requested language isn't translated yet.
 *
 * Defined here rather than in homepage-catalog.php because filters-registry.php
 * loads first; tvf_resolve_catalog_text() is the catalog-facing alias.
 */
function tvf_resolve_text( array $field, string $lang ): string {
	return $field[ $lang ] ?? $field['fr'] ?? '';
}

/**
 * The registry with every `label` and `hint` resolved to a plain string in
 * $lang. This is what renderers want.
 *
 * $lang defaults to French so that existing callers that pass nothing — the
 * admin screens, the metabox, and the mavo-for-you plugin — keep the exact
 * behaviour they had when labels were bare French strings.
 */
function tvf_get_registry( string $lang = 'fr' ): array {
	static $cache = [];
	if ( isset( $cache[ $lang ] ) ) {
		return $cache[ $lang ];
	}

	$out = [];
	foreach ( tvf_get_registry_raw() as $cat_slug => $cat ) {
		$cat['label'] = tvf_resolve_text( $cat['label'], $lang );
		if ( isset( $cat['hint'] ) ) {
			$cat['hint'] = tvf_resolve_text( $cat['hint'], $lang );
		}
		foreach ( $cat['filters'] as $slug => $label ) {
			$cat['filters'][ $slug ] = tvf_resolve_text( $label, $lang );
		}
		$out[ $cat_slug ] = $cat;
	}

	return $cache[ $lang ] = $out;
}

/** Flat list of all 32 filter slugs in CSV column order. */
function tvf_get_all_slugs(): array {
	static $cache = null;
	if ( $cache !== null ) {
		return $cache;
	}
	$cache = [];
	foreach ( tvf_get_registry_raw() as $cat ) {
		foreach ( array_keys( $cat['filters'] ) as $slug ) {
			$cache[] = $slug;
		}
	}
	return $cache;
}

/** Returns [ slug => label ] flat map, in $lang. */
function tvf_get_slug_labels( string $lang = 'fr' ): array {
	static $cache = [];
	if ( isset( $cache[ $lang ] ) ) {
		return $cache[ $lang ];
	}
	$out = [];
	foreach ( tvf_get_registry( $lang ) as $cat ) {
		foreach ( $cat['filters'] as $slug => $label ) {
			$out[ $slug ] = $label;
		}
	}
	return $cache[ $lang ] = $out;
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
	foreach ( tvf_get_registry_raw() as $cat ) {
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
	foreach ( tvf_get_registry_raw() as $cat_slug => $cat ) {
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
