<?php
/**
 * Uninstall handler for External Link Manager.
 *
 * Called automatically by WordPress when the plugin is deleted via the admin UI.
 * Drops the custom elm_links table and removes all plugin options.
 *
 * @package ExternalLinkManager
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// Drop the custom links table.
$table_name = $wpdb->prefix . 'elm_links';
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query( "DROP TABLE IF EXISTS `{$table_name}`" ); // Safe: table name is constructed from $wpdb->prefix, not user input.

// Remove all plugin options.
delete_option( 'elm_db_version' );
delete_option( 'elm_settings' );
delete_option( 'elm_exclusions' );

// Remove any transients stored by the plugin.
$wpdb->query( "DELETE FROM `{$wpdb->options}` WHERE `option_name` LIKE '_transient_elm_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query( "DELETE FROM `{$wpdb->options}` WHERE `option_name` LIKE '_transient_timeout_elm_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
