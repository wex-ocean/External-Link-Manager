<?php
/**
 * Admin view: Per-link attributes override.
 *
 * @package ExternalLinkManager
 * @since   0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! isset( $_GET['link_id'] ) ) {
	return;
}

$link_id = absint( $_GET['link_id'] );
$nonce   = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( $_GET['_wpnonce'] ) : '';

if ( ! wp_verify_nonce( $nonce, 'elm_override_' . $link_id ) ) {
	wp_die( esc_html__( 'Unauthorized access.', 'external-link-manager' ) );
}

global $wpdb;
require_once ELM_PLUGIN_DIR . 'includes/class-elm-db.php';
$table = ELM_DB::table_name();

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$link = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE id = %d", $link_id ) );

if ( ! $link ) {
	wp_die( esc_html__( 'Link not found.', 'external-link-manager' ) );
}

$current_override = ! empty( $link->attributes_json ) ? json_decode( $link->attributes_json, true ) : array();

// Fallback to global settings if no override exists (to pre-fill the form with something sensible).
if ( empty( $current_override ) ) {
	$current_override = get_option( 'elm_settings', array() );
}

$back_url = admin_url( 'admin.php?page=elm-dashboard' );
?>

<div class="wrap">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Override Attributes', 'external-link-manager' ); ?></h1>
	<a href="<?php echo esc_url( $back_url ); ?>" class="page-title-action"><?php esc_html_e( 'Back to Dashboard', 'external-link-manager' ); ?></a>
	<hr class="wp-header-end">

	<div class="card" style="max-width: 800px; padding: 20px; margin-top: 20px;">
		<h2><?php esc_html_e( 'Link Details', 'external-link-manager' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'URL', 'external-link-manager' ); ?></th>
				<td><strong><a href="<?php echo esc_url( $link->url ); ?>" target="_blank"><?php echo esc_html( $link->url ); ?></a></strong></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Anchor Text', 'external-link-manager' ); ?></th>
				<td><?php echo esc_html( $link->anchor_text ); ?></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Source Post', 'external-link-manager' ); ?></th>
				<td><a href="<?php echo esc_url( get_edit_post_link( $link->post_id ) ); ?>"><?php echo esc_html( get_the_title( $link->post_id ) ); ?></a></td>
			</tr>
		</table>
	</div>

	<div class="card" style="max-width: 800px; padding: 20px; margin-top: 20px;">
		<h2><?php esc_html_e( 'Override Global Settings', 'external-link-manager' ); ?></h2>
		<p><?php esc_html_e( 'Check the attributes you want to apply specifically to this link. Saving this form will disconnect this link from the global settings.', 'external-link-manager' ); ?></p>
		
		<form method="post" action="">
			<input type="hidden" name="action" value="save_override" />
			<input type="hidden" name="link_id" value="<?php echo esc_attr( $link->id ); ?>" />
			<?php wp_nonce_field( 'elm_save_override_' . $link->id, 'elm_save_override_nonce' ); ?>
			
			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row"><?php esc_html_e( 'Open in new tab', 'external-link-manager' ); ?></th>
						<td>
							<label for="elm_open_new_tab">
								<input type="checkbox" id="elm_open_new_tab" name="elm_override[open_new_tab]" value="1" <?php checked( ! empty( $current_override['open_new_tab'] ) ); ?> />
								<?php esc_html_e( 'Add target="_blank"', 'external-link-manager' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Link Relationships (rel)', 'external-link-manager' ); ?></th>
						<td>
							<fieldset>
								<legend class="screen-reader-text"><span><?php esc_html_e( 'Link Relationships', 'external-link-manager' ); ?></span></legend>
								<label for="elm_nofollow">
									<input type="checkbox" id="elm_nofollow" name="elm_override[nofollow]" value="1" <?php checked( ! empty( $current_override['nofollow'] ) ); ?> />
									<?php esc_html_e( 'Add "nofollow"', 'external-link-manager' ); ?>
								</label><br>
								<label for="elm_sponsored">
									<input type="checkbox" id="elm_sponsored" name="elm_override[sponsored]" value="1" <?php checked( ! empty( $current_override['sponsored'] ) ); ?> />
									<?php esc_html_e( 'Add "sponsored"', 'external-link-manager' ); ?>
								</label><br>
								<label for="elm_ugc">
									<input type="checkbox" id="elm_ugc" name="elm_override[ugc]" value="1" <?php checked( ! empty( $current_override['ugc'] ) ); ?> />
									<?php esc_html_e( 'Add "ugc"', 'external-link-manager' ); ?>
								</label><br>
								<label for="elm_noopener">
									<input type="checkbox" id="elm_noopener" name="elm_override[noopener]" value="1" <?php checked( ! empty( $current_override['noopener'] ) ); ?> />
									<?php esc_html_e( 'Add "noopener"', 'external-link-manager' ); ?>
								</label><br>
								<label for="elm_noreferrer">
									<input type="checkbox" id="elm_noreferrer" name="elm_override[noreferrer]" value="1" <?php checked( ! empty( $current_override['noreferrer'] ) ); ?> />
									<?php esc_html_e( 'Add "noreferrer"', 'external-link-manager' ); ?>
								</label>
							</fieldset>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="elm_custom_class"><?php esc_html_e( 'Custom CSS Class', 'external-link-manager' ); ?></label></th>
						<td>
							<input type="text" id="elm_custom_class" name="elm_override[custom_class]" value="<?php echo esc_attr( isset( $current_override['custom_class'] ) ? $current_override['custom_class'] : '' ); ?>" class="regular-text" />
						</td>
					</tr>
				</tbody>
			</table>
			<p class="submit">
				<?php submit_button( __( 'Save Override', 'external-link-manager' ), 'primary', 'submit', false ); ?>
				<?php if ( ! empty( $link->attributes_json ) ) : ?>
					<button type="submit" name="reset_to_global" value="1" class="button button-secondary" style="margin-left: 10px;" onclick="return confirm('<?php esc_attr_e( 'Are you sure you want to reset this link to use global settings?', 'external-link-manager' ); ?>');">
						<?php esc_html_e( 'Reset to Global Settings', 'external-link-manager' ); ?>
					</button>
				<?php endif; ?>
			</p>
		</form>
	</div>
</div>
