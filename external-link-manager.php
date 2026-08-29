<?php
/**
 * Plugin Name:       External Link Manager
 * Plugin URI:        https://github.com/your-username/external-link-manager
 * Description:       A fast, DB-cached, zero-bloat external link manager. Auto-detects external links on save, applies global attributes (nofollow, noopener, target=_blank, etc.), and provides a native WP_List_Table dashboard with per-link overrides.
 * Version:           0.1.0
 * Requires at least: 6.4
 * Requires PHP:      7.4
 * Author:            Your Name
 * Author URI:        https://yourwebsite.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       external-link-manager
 * Domain Path:       /languages
 *
 * @package ExternalLinkManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin version and table version constants.
define( 'ELM_VERSION', '0.1.0' );
define( 'ELM_DB_VERSION', '1.0' );
define( 'ELM_PLUGIN_FILE', __FILE__ );
define( 'ELM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'ELM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'ELM_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Activation hook — delegates to ELM_Activator.
 */
function elm_activate() {
	require_once ELM_PLUGIN_DIR . 'includes/class-elm-activator.php';
	ELM_Activator::activate();
}
register_activation_hook( ELM_PLUGIN_FILE, 'elm_activate' );

/**
 * Deactivation hook — delegates to ELM_Deactivator.
 */
function elm_deactivate() {
	require_once ELM_PLUGIN_DIR . 'includes/class-elm-deactivator.php';
	ELM_Deactivator::deactivate();
}
register_deactivation_hook( ELM_PLUGIN_FILE, 'elm_deactivate' );

/**
 * Bootstrap the plugin after all plugins are loaded.
 */
function elm_init() {
	// Load text domain for i18n.
	load_plugin_textdomain(
		'external-link-manager',
		false,
		dirname( ELM_PLUGIN_BASENAME ) . '/languages'
	);

	// --- Load core classes ---
	require_once ELM_PLUGIN_DIR . 'includes/class-elm-db.php';
	require_once ELM_PLUGIN_DIR . 'includes/class-elm-cache.php';
	require_once ELM_PLUGIN_DIR . 'includes/class-elm-exclusions.php';
	require_once ELM_PLUGIN_DIR . 'includes/class-elm-detector.php';
	require_once ELM_PLUGIN_DIR . 'includes/class-elm-renderer.php';

	// --- Detection layer: scan post content on save ---
	$detector = new ELM_Detector();
	$detector->init();

	// --- Rendering layer: apply attributes on frontend ---
	$renderer = new ELM_Renderer();
	$renderer->init();

	// --- Admin and Settings layer ---
	if ( is_admin() ) {
		require_once ELM_PLUGIN_DIR . 'includes/class-elm-settings.php';
		$settings = new ELM_Settings();
		$settings->init();

		require_once ELM_PLUGIN_DIR . 'includes/class-elm-import-export.php';
		$import_export = new ELM_Import_Export();
		$import_export->init();

		require_once ELM_PLUGIN_DIR . 'admin/class-elm-admin.php';
		$admin = new ELM_Admin();
		$admin->init();
	}

	// Future stages: dashboard etc.
}
add_action( 'plugins_loaded', 'elm_init' );
