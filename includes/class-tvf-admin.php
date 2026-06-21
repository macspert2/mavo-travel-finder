<?php
defined( 'ABSPATH' ) || exit;

class TVF_Admin {

	public static function init(): void {
		if ( ! is_admin() ) {
			return;
		}
		add_action( 'admin_menu',             [ __CLASS__, 'add_menu' ] );
		add_action( 'add_meta_boxes',         [ __CLASS__, 'add_meta_box' ] );
		add_action( 'save_post',              [ __CLASS__, 'save_meta_box' ] );
		add_action( 'wp_ajax_tvf_save',        [ __CLASS__, 'ajax_save' ] );
		add_action( 'wp_ajax_tvf_get_weights', [ __CLASS__, 'ajax_get_weights' ] );
		add_action( 'admin_enqueue_scripts',   [ __CLASS__, 'enqueue_assets' ] );
		add_action( 'admin_post_tvf_clear_cache', [ __CLASS__, 'handle_clear_cache' ] );
		add_action( 'admin_post_tvf_sync_translations', [ __CLASS__, 'handle_sync_translations' ] );
		add_action( 'admin_post_tvf_save_settings', [ __CLASS__, 'handle_save_settings' ] );
	}

	// -------------------------------------------------------------------------
	// Menus
	// -------------------------------------------------------------------------

	public static function add_menu(): void {
		add_menu_page(
			__( 'Travel Finder', 'travel-finder' ),
			__( 'Travel Finder', 'travel-finder' ),
			'edit_posts',
			'travel-finder',
			[ __CLASS__, 'render_edit_page' ],
			'dashicons-location-alt',
			30
		);
		add_submenu_page(
			'travel-finder',
			__( 'Modifier les poids', 'travel-finder' ),
			__( 'Modifier les poids', 'travel-finder' ),
			'edit_posts',
			'travel-finder',
			[ __CLASS__, 'render_edit_page' ]
		);
		add_submenu_page(
			'travel-finder',
			__( 'Couverture', 'travel-finder' ),
			__( 'Couverture', 'travel-finder' ),
			'edit_posts',
			'travel-finder-coverage',
			[ __CLASS__, 'render_coverage_page' ]
		);
		add_submenu_page(
			'travel-finder',
			__( 'Importer CSV', 'travel-finder' ),
			__( 'Importer CSV', 'travel-finder' ),
			'manage_options',
			'travel-finder-import',
			[ __CLASS__, 'render_import_page' ]
		);
		add_submenu_page(
			'travel-finder',
			__( 'Synchroniser EN/DE', 'travel-finder' ),
			__( 'Synchroniser EN/DE', 'travel-finder' ),
			'manage_options',
			'travel-finder-sync',
			[ __CLASS__, 'render_sync_page' ]
		);
		add_submenu_page(
			'travel-finder',
			__( 'Réglages', 'travel-finder' ),
			__( 'Réglages', 'travel-finder' ),
			'manage_options',
			'travel-finder-settings',
			[ __CLASS__, 'render_settings_page' ]
		);
	}

	public static function enqueue_assets( string $hook ): void {
		$admin_pages = [
			'toplevel_page_travel-finder',
			'travel-finder_page_travel-finder-coverage',
			'travel-finder_page_travel-finder-import',
			'travel-finder_page_travel-finder-sync',
			'travel-finder_page_travel-finder-settings',
			'post.php',
			'post-new.php',
		];
		if ( ! in_array( $hook, $admin_pages, true ) ) {
			return;
		}

		wp_enqueue_style( 'tvf-admin', TVF_PLUGIN_URL . 'assets/admin.css', [], TVF_VERSION );
		wp_enqueue_script( 'tvf-admin', TVF_PLUGIN_URL . 'assets/admin.js', [ 'jquery' ], TVF_VERSION, true );
		wp_localize_script( 'tvf-admin', 'tvfAdmin', [
			'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( 'tvf_admin' ),
			'restNonce'=> wp_create_nonce( 'wp_rest' ),
			'restBase' => rest_url( 'tvf/v1' ),
			'i18n'     => [
				'saved'    => __( 'Enregistré !', 'travel-finder' ),
				'error'    => __( 'Erreur lors de la sauvegarde.', 'travel-finder' ),
				'copied'   => __( 'Copié depuis le modèle.', 'travel-finder' ),
				'noPost'   => __( 'Choisissez d\'abord un article cible.', 'travel-finder' ),
			],
		] );
	}

	// -------------------------------------------------------------------------
	// Edit-weights page
	// -------------------------------------------------------------------------

	public static function render_edit_page(): void {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'Accès refusé.', 'travel-finder' ) );
		}

		$lang = sanitize_key( $_GET['lang'] ?? 'fr' );
		if ( ! in_array( $lang, [ 'fr', 'en', 'de' ], true ) ) {
			$lang = 'fr';
		}
		$registry = tvf_get_registry();
		?>
		<div class="wrap tvf-admin-wrap">
			<h1><?php esc_html_e( 'Travel Finder — Modifier les poids', 'travel-finder' ); ?></h1>

			<?php if ( isset( $_GET['tvf_cleared'] ) ) : ?>
				<div class="notice notice-success is-dismissible">
					<p><?php esc_html_e( 'Cache vidé — les résultats seront régénérés à la prochaine visite.', 'travel-finder' ); ?></p>
				</div>
			<?php endif; ?>

			<div class="tvf-lang-tabs">
				<?php foreach ( [ 'fr' => 'FR', 'en' => 'EN', 'de' => 'DE' ] as $l => $label ) : ?>
					<a href="<?php echo esc_url( add_query_arg( 'lang', $l ) ); ?>"
					   class="tvf-lang-tab<?php echo $l === $lang ? ' is-active' : ''; ?>">
						<?php echo esc_html( $label ); ?>
					</a>
				<?php endforeach; ?>

				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-left:auto">
					<?php wp_nonce_field( 'tvf_clear_cache', 'tvf_cache_nonce' ); ?>
					<input type="hidden" name="action" value="tvf_clear_cache">
					<button type="submit" class="button tvf-lang-tab">
						<?php esc_html_e( 'Vider le cache', 'travel-finder' ); ?>
					</button>
				</form>
			</div>

			<div class="tvf-picker-row">
				<div class="tvf-picker">
					<label for="tvf-template-search">
						<?php esc_html_e( 'Modèle (source)', 'travel-finder' ); ?>
					</label>
					<div class="tvf-picker-input-wrap">
						<input type="text" id="tvf-template-search" class="regular-text"
							placeholder="<?php esc_attr_e( 'Rechercher un article…', 'travel-finder' ); ?>" autocomplete="off">
						<ul id="tvf-template-suggestions" class="tvf-suggestions" hidden></ul>
					</div>
					<input type="hidden" id="tvf-template-id">
				</div>

				<div class="tvf-picker">
					<label for="tvf-target-search">
						<?php esc_html_e( 'Article à modifier', 'travel-finder' ); ?>
					</label>
					<div class="tvf-picker-input-wrap">
						<input type="text" id="tvf-target-search" class="regular-text"
							placeholder="<?php esc_attr_e( 'Rechercher un article…', 'travel-finder' ); ?>" autocomplete="off">
						<ul id="tvf-target-suggestions" class="tvf-suggestions" hidden></ul>
					</div>
					<input type="hidden" id="tvf-target-id">
					<span id="tvf-target-badge" class="tvf-badge" hidden></span>
				</div>
			</div>

			<div class="tvf-grid-actions">
				<button id="tvf-copy-btn" class="button" disabled>
					<?php esc_html_e( 'Copier depuis le modèle', 'travel-finder' ); ?>
				</button>
				<button id="tvf-reset-btn" class="button" disabled>
					<?php esc_html_e( 'Réinitialiser', 'travel-finder' ); ?>
				</button>
				<button id="tvf-save-btn" class="button button-primary" disabled>
					<?php esc_html_e( 'Enregistrer', 'travel-finder' ); ?>
				</button>
				<span id="tvf-save-msg" class="tvf-save-msg" aria-live="polite"></span>
			</div>

			<table class="tvf-weights-table wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th class="tvf-col-cat"><?php esc_html_e( 'Catégorie', 'travel-finder' ); ?></th>
						<th class="tvf-col-filter"><?php esc_html_e( 'Filtre', 'travel-finder' ); ?></th>
						<th class="tvf-col-tpl"><?php esc_html_e( 'Modèle', 'travel-finder' ); ?></th>
						<th class="tvf-col-val"><?php esc_html_e( 'Valeur', 'travel-finder' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $registry as $cat_slug => $cat ) : ?>
						<?php $first = true; $count = count( $cat['filters'] ); ?>
						<?php foreach ( $cat['filters'] as $slug => $label ) : ?>
							<tr>
								<?php if ( $first ) : $first = false; ?>
									<td class="tvf-cat-cell" rowspan="<?php echo (int) $count; ?>">
										<?php echo esc_html( $cat['label'] ); ?>
									</td>
								<?php endif; ?>
								<td><?php echo esc_html( $label ); ?></td>
								<td class="tvf-tpl-val" data-slug="<?php echo esc_attr( $slug ); ?>">—</td>
								<td>
									<div class="tvf-segmented" data-slug="<?php echo esc_attr( $slug ); ?>">
										<button type="button" data-val="0" class="tvf-seg is-active">0</button>
										<button type="button" data-val="1" class="tvf-seg">1</button>
										<button type="button" data-val="2" class="tvf-seg">2</button>
									</div>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<input type="hidden" id="tvf-lang" value="<?php echo esc_attr( $lang ); ?>">
		<?php
	}

	// -------------------------------------------------------------------------
	// Coverage page
	// -------------------------------------------------------------------------

	public static function render_coverage_page(): void {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'Accès refusé.', 'travel-finder' ) );
		}

		$lang          = sanitize_key( $_GET['lang'] ?? 'fr' );
		$rows          = TVF_Store::get_post_coverage( $lang );
		$total_filters = count( tvf_get_all_slugs() );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Travel Finder — Couverture', 'travel-finder' ); ?></h1>
			<table class="wp-list-table widefat fixed striped tvf-coverage-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Article', 'travel-finder' ); ?></th>
						<th style="width:160px"><?php esc_html_e( 'Filtres configurés', 'travel-finder' ); ?></th>
						<th style="width:120px"><?php esc_html_e( 'Statut', 'travel-finder' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $rows as $row ) : ?>
						<?php
						$configured = (int) $row['configured'];
						if ( $configured === 0 ) {
							$badge_class = 'tvf-badge-empty';
							$badge_text  = __( 'Vide', 'travel-finder' );
						} elseif ( $configured < $total_filters ) {
							$badge_class = 'tvf-badge-partial';
							$badge_text  = __( 'Partiel', 'travel-finder' );
						} else {
							$badge_class = 'tvf-badge-complete';
							$badge_text  = __( 'Complet', 'travel-finder' );
						}
						?>
						<tr>
							<td>
								<a href="<?php echo esc_url( get_edit_post_link( $row['ID'] ) ); ?>">
									<?php echo esc_html( $row['post_title'] ); ?>
								</a>
							</td>
							<td><?php echo $configured; ?> / <?php echo $total_filters; ?></td>
							<td><span class="tvf-badge <?php echo esc_attr( $badge_class ); ?>"><?php echo esc_html( $badge_text ); ?></span></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	// -------------------------------------------------------------------------
	// Import page
	// -------------------------------------------------------------------------

	public static function render_import_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Accès refusé.', 'travel-finder' ) );
		}

		$result = null;

		if ( isset( $_POST['tvf_import_nonce'] )
			&& wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['tvf_import_nonce'] ) ), 'tvf_import' )
			&& ! empty( $_FILES['tvf_csv']['tmp_name'] )
		) {
			$lang = sanitize_key( $_POST['tvf_import_lang'] ?? 'fr' );
			if ( ! in_array( $lang, [ 'fr', 'en', 'de' ], true ) ) {
				$lang = 'fr';
			}
			$result = TVF_Importer::import_csv( $_FILES['tvf_csv']['tmp_name'], $lang );
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Travel Finder — Importer CSV', 'travel-finder' ); ?></h1>

			<?php if ( null !== $result ) : ?>
				<div class="notice notice-<?php echo $result['errors'] ? 'warning' : 'success'; ?> is-dismissible">
					<p>
						<?php
						/* translators: %d: number of rows imported */
						printf( esc_html__( '%d article(s) importé(s).', 'travel-finder' ), (int) $result['imported'] );
						?>
					</p>
					<?php if ( $result['errors'] ) : ?>
						<ul>
							<?php foreach ( $result['errors'] as $err ) : ?>
								<li><?php echo esc_html( $err ); ?></li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<p><?php esc_html_e( 'Importez le fichier CSV des poids. La première ligne (en-tête) est ignorée. Les colonnes doivent rester dans l\'ordre d\'origine.', 'travel-finder' ); ?></p>

			<form method="post" enctype="multipart/form-data">
				<?php wp_nonce_field( 'tvf_import', 'tvf_import_nonce' ); ?>
				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="tvf_csv"><?php esc_html_e( 'Fichier CSV', 'travel-finder' ); ?></label>
						</th>
						<td>
							<input type="file" name="tvf_csv" id="tvf_csv" accept=".csv,text/csv" required>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="tvf_import_lang"><?php esc_html_e( 'Langue', 'travel-finder' ); ?></label>
						</th>
						<td>
							<select name="tvf_import_lang" id="tvf_import_lang">
								<option value="fr">FR</option>
								<option value="en">EN</option>
								<option value="de">DE</option>
							</select>
						</td>
					</tr>
				</table>
				<?php submit_button( __( 'Importer', 'travel-finder' ) ); ?>
			</form>
		</div>
		<?php
	}

	// -------------------------------------------------------------------------
	// Sync EN/DE translations page
	// -------------------------------------------------------------------------

	public static function render_sync_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Accès refusé.', 'travel-finder' ) );
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Travel Finder — Synchroniser EN/DE', 'travel-finder' ); ?></h1>

			<?php if ( isset( $_GET['tvf_synced'] ) ) : ?>
				<div class="notice notice-success is-dismissible">
					<p>
						<?php
						printf(
							/* translators: 1: number of translations updated, 2: number of French posts with weights checked */
							esc_html__( '%1$d traduction(s) mise(s) à jour, sur %2$d article(s) français avec des poids configurés.', 'travel-finder' ),
							(int) $_GET['tvf_synced'],
							(int) ( $_GET['tvf_checked'] ?? 0 )
						);
						?>
					</p>
				</div>
			<?php endif; ?>

			<p>
				<?php esc_html_e( 'Copie les poids de chaque article français vers ses traductions anglaise et allemande, via les liens de traduction Polylang — seuls les articles ayant une traduction existante sont mis à jour.', 'travel-finder' ); ?>
			</p>
			<p>
				<strong><?php esc_html_e( 'Attention :', 'travel-finder' ); ?></strong>
				<?php esc_html_e( 'à chaque exécution, les poids EN/DE existants sont remplacés par les valeurs françaises actuelles. Toute modification manuelle des poids faite directement sur un article EN ou DE sera donc écrasée la prochaine fois que cette synchronisation est lancée.', 'travel-finder' ); ?>
			</p>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'tvf_sync_translations', 'tvf_sync_nonce' ); ?>
				<input type="hidden" name="action" value="tvf_sync_translations">
				<?php submit_button( __( 'Synchroniser maintenant', 'travel-finder' ) ); ?>
			</form>
		</div>
		<?php
	}

	public static function handle_sync_translations(): void {
		check_admin_referer( 'tvf_sync_translations', 'tvf_sync_nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Accès refusé.', 'travel-finder' ) );
		}

		$result = TVF_Store::sync_translations();

		wp_safe_redirect(
			add_query_arg(
				[
					'page'        => 'travel-finder-sync',
					'tvf_synced'  => $result['synced'],
					'tvf_checked' => $result['fr_posts_checked'],
				],
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	// -------------------------------------------------------------------------
	// Réglages page
	// -------------------------------------------------------------------------

	public static function render_settings_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Accès refusé.', 'travel-finder' ) );
		}

		$selected = array_flip( tvf_get_family_travel_theme_keys() );
		$section_labels = [
			'family_travel_themes' => __( 'Thèmes familiaux', 'travel-finder' ),
			'seasonal_guides'      => __( 'Guides saisonniers', 'travel-finder' ),
			'featured_destinations'=> __( 'Destinations phares', 'travel-finder' ),
		];
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Travel Finder — Réglages', 'travel-finder' ); ?></h1>

			<?php if ( isset( $_GET['tvf_settings_saved'] ) ) : ?>
				<div class="notice notice-success is-dismissible">
					<p><?php esc_html_e( 'Réglages enregistrés.', 'travel-finder' ); ?></p>
				</div>
			<?php endif; ?>

			<?php $mv_url = menu_page_url( 'mv-settings', false ); ?>
			<?php if ( $mv_url ) : ?>
				<p>
					<?php esc_html_e( 'Réglages liés au thème (sections affichées sur les pages d’accueil et la barre latérale de recherche) :', 'travel-finder' ); ?>
					<a href="<?php echo esc_url( $mv_url ); ?>"><?php esc_html_e( 'voir cette page', 'travel-finder' ); ?></a>
				</p>
			<?php endif; ?>

			<h2><?php esc_html_e( '« Voyager selon votre famille » — thèmes affichés', 'travel-finder' ); ?></h2>
			<p>
				<?php esc_html_e( 'Choisissez les entrées du catalogue affichées dans cette section, sur les 3 langues. Seules bébé / jeunes enfants / ados ont une traduction anglaise et allemande réelle aujourd’hui — toute autre entrée s’affichera en français sur les pages EN/DE tant qu’elle n’est pas traduite.', 'travel-finder' ); ?>
			</p>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'tvf_save_settings', 'tvf_settings_nonce' ); ?>
				<input type="hidden" name="action" value="tvf_save_settings">

				<?php foreach ( tvf_get_homepage_catalog() as $section => $entries ) : ?>
					<h3><?php echo esc_html( $section_labels[ $section ] ?? $section ); ?></h3>
					<div class="tvf-key-checkboxes">
						<?php foreach ( $entries as $key => $entry ) : ?>
							<label class="tvf-key-checkbox">
								<input type="checkbox" name="family_travel_theme_keys[]" value="<?php echo esc_attr( $key ); ?>" <?php checked( isset( $selected[ $key ] ) ); ?>>
								<?php echo esc_html( tvf_resolve_catalog_text( $entry['label'], 'fr' ) ); ?>
							</label>
						<?php endforeach; ?>
					</div>
				<?php endforeach; ?>

				<?php submit_button( __( 'Enregistrer les réglages', 'travel-finder' ) ); ?>
			</form>
		</div>
		<?php
	}

	public static function handle_save_settings(): void {
		check_admin_referer( 'tvf_save_settings', 'tvf_settings_nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Accès refusé.', 'travel-finder' ) );
		}

		$valid_keys = [];
		foreach ( tvf_get_homepage_catalog() as $entries ) {
			$valid_keys = array_merge( $valid_keys, array_keys( $entries ) );
		}
		$valid_keys = array_flip( $valid_keys );

		$submitted = is_array( $_POST['family_travel_theme_keys'] ?? null ) ? $_POST['family_travel_theme_keys'] : [];
		$keys      = [];
		foreach ( $submitted as $key ) {
			$key = sanitize_key( (string) $key );
			if ( isset( $valid_keys[ $key ] ) ) {
				$keys[] = $key;
			}
		}

		update_option( 'tvf_family_travel_theme_keys', $keys );

		wp_safe_redirect(
			add_query_arg(
				[ 'page' => 'travel-finder-settings', 'tvf_settings_saved' => '1' ],
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	// -------------------------------------------------------------------------
	// Meta box
	// -------------------------------------------------------------------------

	public static function add_meta_box(): void {
		add_meta_box(
			'tvf_weights',
			__( 'Travel Finder — Poids des filtres', 'travel-finder' ),
			[ __CLASS__, 'render_meta_box' ],
			'post',
			'normal',
			'default'
		);
	}

	public static function render_meta_box( WP_Post $post ): void {
		$lang     = function_exists( 'pll_get_post_language' ) ? (string) pll_get_post_language( $post->ID ) : 'fr';
		$lang     = $lang ?: 'fr';
		$weights  = TVF_Store::get_weights( $post->ID, $lang );
		$registry = tvf_get_registry();

		wp_nonce_field( 'tvf_meta_' . $post->ID, 'tvf_meta_nonce' );
		?>
		<div class="tvf-metabox"
			 data-post-id="<?php echo (int) $post->ID; ?>"
			 data-lang="<?php echo esc_attr( $lang ); ?>">

			<div class="tvf-mb-copy-row">
				<div class="tvf-picker-input-wrap">
					<input type="text" id="tvf-mb-template-search" class="regular-text"
						placeholder="<?php esc_attr_e( 'Copier les poids depuis un autre article…', 'travel-finder' ); ?>"
						autocomplete="off">
					<ul id="tvf-mb-template-suggestions" class="tvf-suggestions" hidden></ul>
				</div>
				<input type="hidden" id="tvf-mb-template-id">
				<button type="button" id="tvf-mb-copy-btn" class="button" disabled>
					<?php esc_html_e( 'Copier', 'travel-finder' ); ?>
				</button>
			</div>

			<?php foreach ( $registry as $cat ) : ?>
				<div class="tvf-mb-category">
					<h4 class="tvf-mb-cat-label"><?php echo esc_html( $cat['label'] ); ?></h4>
					<div class="tvf-mb-filters">
						<?php foreach ( $cat['filters'] as $slug => $label ) : ?>
							<?php $w = isset( $weights[ $slug ] ) ? (int) $weights[ $slug ] : 0; ?>
							<div class="tvf-mb-filter">
								<span class="tvf-mb-filter-label"><?php echo esc_html( $label ); ?></span>
								<div class="tvf-segmented" data-slug="<?php echo esc_attr( $slug ); ?>">
									<button type="button" data-val="0" class="tvf-seg<?php echo 0 === $w ? ' is-active' : ''; ?>">0</button>
									<button type="button" data-val="1" class="tvf-seg<?php echo 1 === $w ? ' is-active' : ''; ?>">1</button>
									<button type="button" data-val="2" class="tvf-seg<?php echo 2 === $w ? ' is-active' : ''; ?>">2</button>
								</div>
								<input type="hidden"
									   name="tvf_weight[<?php echo esc_attr( $slug ); ?>]"
									   value="<?php echo (int) $w; ?>">
							</div>
						<?php endforeach; ?>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
		<?php
	}

	public static function save_meta_box( int $post_id ): void {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! isset( $_POST['tvf_meta_nonce'] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['tvf_meta_nonce'] ) ), 'tvf_meta_' . $post_id ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		if ( ! isset( $_POST['tvf_weight'] ) || ! is_array( $_POST['tvf_weight'] ) ) {
			return;
		}

		$lang    = function_exists( 'pll_get_post_language' ) ? (string) pll_get_post_language( $post_id ) : 'fr';
		$lang    = $lang ?: 'fr';
		$allowed = array_flip( tvf_get_all_slugs() );
		$weights = [];

		foreach ( $_POST['tvf_weight'] as $slug => $val ) {
			$slug = sanitize_key( (string) $slug );
			if ( isset( $allowed[ $slug ] ) ) {
				$weights[ $slug ] = max( 0, min( 2, (int) $val ) );
			}
		}

		TVF_Store::save_weights( $post_id, $lang, $weights );
	}

	// -------------------------------------------------------------------------
	// AJAX handlers
	// -------------------------------------------------------------------------

	public static function ajax_save(): void {
		check_ajax_referer( 'tvf_admin', 'nonce' );
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( 'Unauthorized', 403 );
		}

		$post_id = (int) ( $_POST['post_id'] ?? 0 );
		$lang    = sanitize_key( $_POST['lang'] ?? 'fr' );

		if ( ! $post_id || ! get_post( $post_id ) ) {
			wp_send_json_error( 'Invalid post' );
		}
		if ( ! in_array( $lang, [ 'fr', 'en', 'de' ], true ) ) {
			$lang = 'fr';
		}

		$raw     = is_array( $_POST['weights'] ?? null ) ? $_POST['weights'] : [];
		$allowed = array_flip( tvf_get_all_slugs() );
		$weights = [];

		foreach ( $raw as $slug => $val ) {
			$slug = sanitize_key( (string) $slug );
			if ( isset( $allowed[ $slug ] ) ) {
				$weights[ $slug ] = max( 0, min( 2, (int) $val ) );
			}
		}

		TVF_Store::save_weights( $post_id, $lang, $weights );
		wp_send_json_success();
	}

	public static function ajax_get_weights(): void {
		check_ajax_referer( 'tvf_admin', 'nonce' );
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( 'Unauthorized', 403 );
		}

		$post_id = (int) ( $_GET['post_id'] ?? 0 );
		$lang    = sanitize_key( $_GET['lang'] ?? 'fr' );

		if ( ! $post_id ) {
			wp_send_json_error( 'Invalid post' );
		}

		wp_send_json_success( TVF_Store::get_weights( $post_id, $lang ) );
	}

	// -------------------------------------------------------------------------
	// Cache management
	// -------------------------------------------------------------------------

	public static function handle_clear_cache(): void {
		check_admin_referer( 'tvf_clear_cache', 'tvf_cache_nonce' );
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'Accès refusé.', 'travel-finder' ) );
		}

		foreach ( [ 'fr', 'en', 'de' ] as $lang ) {
			TVF_Store::bust_cache( $lang );
		}

		wp_safe_redirect(
			add_query_arg(
				[ 'page' => 'travel-finder', 'tvf_cleared' => '1' ],
				admin_url( 'admin.php' )
			)
		);
		exit;
	}
}
