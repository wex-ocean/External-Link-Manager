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
	
	<form id="elm-links-filter" method="get">
		<input type="hidden" name="page" value="<?php echo esc_attr( $_REQUEST['page'] ); ?>" />
		<?php
		$list_table = new ELM_List_Table();
		$list_table->prepare_items();
		
		$list_table->search_box( __( 'Search Links', 'external-link-manager' ), 'elm' );
		$list_table->display();
		?>
	</form>
</div>
