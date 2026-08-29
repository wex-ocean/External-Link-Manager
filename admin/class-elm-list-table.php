<?php
/**
 * WP_List_Table subclass for the External Link Manager dashboard.
 *
 * Provides a native WordPress admin table with sortable columns, search,
 * pagination, and bulk actions — no custom JS grid required.
 * Columns: URL, Domain, Source Post, Attributes, Status, Last Scanned.
 *
 * @package ExternalLinkManager
 * @since   0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// WP_List_Table is not auto-loaded outside of list-table screens.
if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Class ELM_List_Table
 *
 * Placeholder — implementation added in Stage 5.
 */
class ELM_List_Table extends WP_List_Table {
	// TODO: implement in Stage 5.
}
