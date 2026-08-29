<?php
/**
 * Admin view: Settings — General tab.
 *
 * @package ExternalLinkManager
 * @since   0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$settings = get_option( 'elm_settings', array() );
?>

<form method="post" action="options.php">
	<?php
	settings_fields( 'elm_settings_group' );
	do_settings_sections( 'elm_settings_group' );
	?>
	<table class="form-table" role="presentation">
		<tbody>
			<tr>
				<th scope="row"><?php esc_html_e( 'Open in new tab', 'external-link-manager' ); ?></th>
				<td>
					<label for="elm_open_new_tab">
						<input type="checkbox" id="elm_open_new_tab" name="elm_settings[open_new_tab]" value="1" <?php checked( ! empty( $settings['open_new_tab'] ) ); ?> />
						<?php esc_html_e( 'Add target="_blank" to external links', 'external-link-manager' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Link Relationships (rel)', 'external-link-manager' ); ?></th>
				<td>
					<fieldset>
						<legend class="screen-reader-text"><span><?php esc_html_e( 'Link Relationships', 'external-link-manager' ); ?></span></legend>
						<label for="elm_nofollow">
							<input type="checkbox" id="elm_nofollow" name="elm_settings[nofollow]" value="1" <?php checked( ! empty( $settings['nofollow'] ) ); ?> />
							<?php esc_html_e( 'Add "nofollow"', 'external-link-manager' ); ?>
						</label><br>
						<label for="elm_sponsored">
							<input type="checkbox" id="elm_sponsored" name="elm_settings[sponsored]" value="1" <?php checked( ! empty( $settings['sponsored'] ) ); ?> />
							<?php esc_html_e( 'Add "sponsored"', 'external-link-manager' ); ?>
						</label><br>
						<label for="elm_ugc">
							<input type="checkbox" id="elm_ugc" name="elm_settings[ugc]" value="1" <?php checked( ! empty( $settings['ugc'] ) ); ?> />
							<?php esc_html_e( 'Add "ugc"', 'external-link-manager' ); ?>
						</label><br>
						<label for="elm_noopener">
							<input type="checkbox" id="elm_noopener" name="elm_settings[noopener]" value="1" <?php checked( ! empty( $settings['noopener'] ) ); ?> />
							<?php esc_html_e( 'Add "noopener" (Recommended for target="_blank")', 'external-link-manager' ); ?>
						</label><br>
						<label for="elm_noreferrer">
							<input type="checkbox" id="elm_noreferrer" name="elm_settings[noreferrer]" value="1" <?php checked( ! empty( $settings['noreferrer'] ) ); ?> />
							<?php esc_html_e( 'Add "noreferrer"', 'external-link-manager' ); ?>
						</label>
					</fieldset>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="elm_custom_class"><?php esc_html_e( 'Custom CSS Class', 'external-link-manager' ); ?></label></th>
				<td>
					<input type="text" id="elm_custom_class" name="elm_settings[custom_class]" value="<?php echo esc_attr( isset( $settings['custom_class'] ) ? $settings['custom_class'] : '' ); ?>" class="regular-text" />
					<p class="description"><?php esc_html_e( 'Add a custom CSS class to all external links.', 'external-link-manager' ); ?></p>
				</td>
			</tr>
		</tbody>
	</table>
	<?php submit_button(); ?>
</form>
