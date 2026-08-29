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
 * Feature classes are loaded here in future stages.
 */
function elm_init() {
	// Load text domain for i18n.
	load_plugin_textdomain(
		'external-link-manager',
		false,
		dirname( ELM_PLUGIN_BASENAME ) . '/languages'
	);

	// Future stages: instantiate detector, renderer, admin, settings, etc.
}
add_action( 'plugins_loaded', 'elm_init' );
