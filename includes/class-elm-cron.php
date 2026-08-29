<?php
/**
 * Action Scheduler integration for broken link health checks (v1.1).
 *
 * Registers scheduled actions, processes batches of links via async
 * wp_remote_head() requests (fallback to GET), and writes link_status
 * back to the elm_links table.
 *
 * @package ExternalLinkManager
 * @since   0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ELM_Cron
 *
 * Placeholder — implementation added in Stage v1.1.
 */
class ELM_Cron {
	// TODO: implement in Stage v1.1.
}
