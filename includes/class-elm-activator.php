<?php
/**
 * Fired during plugin activation.
 *
 * Creates the elm_links custom database table via dbDelta() and stores
 * the DB schema version in an option for future upgrade routines.
 *
 * @package ExternalLinkManager
 * @since   0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ELM_Activator
 *
 * Handles all one-time setup tasks that must run when the plugin is activated.
 */
class ELM_Activator {

	/**
	 * Run activation routines.
	 *
	 * Hooked via register_activation_hook() in the main plugin file.
	 *
	 * @return void
	 */
	public static function activate() {
		self::create_tables();
		self::set_default_options();
	}

	/**
	 * Create (or upgrade) the elm_links custom table using dbDelta().
	 *
	 * dbDelta() is idempotent — safe to run on every activation, including
	 * re-activations and upgrades. It will only ALTER the table when the
	 * schema definition changes.
	 *
	 * @return void
	 */
	private static function create_tables() {
		global $wpdb;

		$table_name      = $wpdb->prefix . 'elm_links';
		$charset_collate = $wpdb->get_charset_collate();

		/*
		 * IMPORTANT: dbDelta() is strict about SQL formatting:
		 *   - Two spaces between PRIMARY KEY and the column definition.
		 *   - Each KEY definition must be on its own line.
		 *   - No trailing comma on the last field before the closing parenthesis.
		 */
		$sql = "CREATE TABLE {$table_name} (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  post_id BIGINT UNSIGNED NOT NULL,
  url TEXT NOT NULL,
  url_hash CHAR(32) NOT NULL,
  domain VARCHAR(255) NOT NULL,
  anchor_text TEXT,
  attributes_json TEXT,
  is_excluded TINYINT(1) NOT NULL DEFAULT 0,
  link_status VARCHAR(20) NOT NULL DEFAULT 'unchecked',
  last_checked DATETIME NULL DEFAULT NULL,
  detected_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY post_url (post_id,url_hash),
  KEY domain_idx (domain(191)),
  KEY post_idx (post_id)
) {$charset_collate};";

		// dbDelta() requires this file.
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		// Persist the DB schema version so upgrade routines can diff against it.
		update_option( 'elm_db_version', ELM_DB_VERSION );
	}

	/**
	 * Write default plugin options if they don't already exist.
	 *
	 * Using add_option() means this is a no-op on re-activation, preserving
	 * any settings the user may have configured.
	 *
	 * @return void
	 */
	private static function set_default_options() {
		$default_settings = array(
			'open_new_tab'   => true,
			'nofollow'       => false,
			'sponsored'      => false,
			'ugc'            => false,
			'noopener'       => true,
			'noreferrer'     => false,
			'custom_class'   => '',
		);

		add_option( 'elm_settings', $default_settings );
		add_option( 'elm_exclusions', array() );
	}
}
