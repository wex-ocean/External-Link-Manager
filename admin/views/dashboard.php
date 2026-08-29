<?php
/**
 * Admin view: Main link dashboard.
 *
 * Rendered by ELM_Admin when the user visits the top-level plugin menu page.
 * Displays the ELM_List_Table with search, filters, and bulk actions.
 *
 * @package ExternalLinkManager
 * @since   0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
	<p><?php esc_html_e( 'Dashboard view — implementation added in Stage 5.', 'external-link-manager' ); ?></p>
</div>
