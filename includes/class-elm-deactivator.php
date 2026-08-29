<?php
/**
 * Fired during plugin deactivation.
 *
 * Deactivation is intentionally non-destructive — the elm_links table and
 * all plugin options are left intact so data is preserved if the user
 * re-activates. Actual cleanup happens in uninstall.php.
 *
 * @package ExternalLinkManager
 * @since   0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ELM_Deactivator
 *
 * Handles tasks that should run when the plugin is deactivated (not deleted).
 */
class ELM_Deactivator {

	/**
	 * Run deactivation routines.
	 *
	 * Hooked via register_deactivation_hook() in the main plugin file.
	 * Currently a placeholder — no destructive cleanup is performed on
	 * deactivation per WordPress best-practice guidelines.
	 *
	 * Future use: flush rewrite rules, clear scheduled events, etc.
	 *
	 * @return void
	 */
	public static function deactivate() {
		// Intentionally left non-destructive.
		// Data cleanup is handled by uninstall.php on plugin deletion.
	}
}
