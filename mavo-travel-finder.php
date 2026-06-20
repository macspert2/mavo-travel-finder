<?php
/**
 * Plugin Name: Mavo Travel Finder
 * Plugin URI:  https://mamanvoyage.com
 * Description: Filter and rank travel posts by weighted criteria. Drop [travel_finder] on any page.
 * Version:     1.5.3
 * Author:      Mavo
 * Text Domain: travel-finder
 * Domain Path: /languages
 * Requires at least: 6.2
 * Requires PHP: 8.0
 */

defined( 'ABSPATH' ) || exit;

define( 'TVF_VERSION',    '1.5.3' );
define( 'TVF_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'TVF_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once TVF_PLUGIN_DIR . 'includes/filters-registry.php';
require_once TVF_PLUGIN_DIR . 'includes/class-tvf-store.php';
require_once TVF_PLUGIN_DIR . 'includes/class-tvf-importer.php';
require_once TVF_PLUGIN_DIR . 'includes/class-tvf-admin.php';
require_once TVF_PLUGIN_DIR . 'includes/class-tvf-frontend.php';
require_once TVF_PLUGIN_DIR . 'includes/homepage-catalog.php';
require_once TVF_PLUGIN_DIR . 'includes/class-tvf-homepage.php';

register_activation_hook( __FILE__, [ 'TVF_Store', 'create_table' ] );

add_action( 'plugins_loaded', static function () {
    load_plugin_textdomain( 'travel-finder', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
    TVF_Admin::init();
    TVF_Frontend::init();
} );
