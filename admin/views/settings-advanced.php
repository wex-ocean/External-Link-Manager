<?php
/**
 * Admin view: Settings — Advanced tab (Import / Export).
 *
 * @package ExternalLinkManager
 * @since   0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Ensure settings messages are shown here if they haven't been output already.
settings_errors( 'elm_settings_messages' );
?>

<div class="card" style="max-width: 800px; padding: 20px; margin-top: 20px;">
	<h2><?php esc_html_e( 'Export Settings', 'external-link-manager' ); ?></h2>
	<p><?php esc_html_e( 'Export your general settings and exclusion rules as a JSON file.', 'external-link-manager' ); ?></p>
	<form method="post" action="">
		<?php wp_nonce_field( 'elm_export_action', 'elm_export_nonce' ); ?>
		<?php submit_button( __( 'Export JSON', 'external-link-manager' ), 'primary', 'elm_export_submit', false ); ?>
	</form>
</div>

<div class="card" style="max-width: 800px; padding: 20px; margin-top: 20px;">
	<h2><?php esc_html_e( 'Import Settings', 'external-link-manager' ); ?></h2>
	<p><?php esc_html_e( 'Import a previously exported JSON file. This will overwrite your current settings and exclusions.', 'external-link-manager' ); ?></p>
	<form method="post" action="" enctype="multipart/form-data">
		<?php wp_nonce_field( 'elm_import_action', 'elm_import_nonce' ); ?>
		<p>
			<input type="file" name="elm_import_file" accept=".json" required />
		</p>
		<?php submit_button( __( 'Import JSON', 'external-link-manager' ), 'secondary', 'elm_import_submit', false ); ?>
	</form>
</div>
