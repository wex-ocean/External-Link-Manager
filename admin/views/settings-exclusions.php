<?php
/**
 * Admin view: Settings — Exclusions tab.
 *
 * @package ExternalLinkManager
 * @since   0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$exclusions = get_option( 'elm_exclusions', array() );
$exclusions = wp_parse_args(
	$exclusions,
	array(
		'domains'    => '',
		'urls'       => '',
		'post_ids'   => '',
		'post_types' => array(),
	)
);
?>

<form method="post" action="options.php">
	<?php
	settings_fields( 'elm_exclusions_group' );
	do_settings_sections( 'elm_exclusions_group' );
	?>
	<table class="form-table" role="presentation">
		<tbody>
			<tr>
				<th scope="row"><label for="elm_exc_domains"><?php esc_html_e( 'Exclude Domains', 'external-link-manager' ); ?></label></th>
				<td>
					<textarea id="elm_exc_domains" name="elm_exclusions[domains]" rows="5" class="large-text code"><?php echo esc_textarea( $exclusions['domains'] ); ?></textarea>
					<p class="description"><?php esc_html_e( 'Enter one domain per line (e.g., example.com). Links to these domains will not be treated as external.', 'external-link-manager' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="elm_exc_urls"><?php esc_html_e( 'Exclude Specific URLs', 'external-link-manager' ); ?></label></th>
				<td>
					<textarea id="elm_exc_urls" name="elm_exclusions[urls]" rows="5" class="large-text code"><?php echo esc_textarea( $exclusions['urls'] ); ?></textarea>
					<p class="description"><?php esc_html_e( 'Enter one full URL per line (e.g., https://example.com/page). These specific URLs will not be treated as external.', 'external-link-manager' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="elm_exc_post_ids"><?php esc_html_e( 'Exclude Posts/Pages by ID', 'external-link-manager' ); ?></label></th>
				<td>
					<input type="text" id="elm_exc_post_ids" name="elm_exclusions[post_ids]" value="<?php echo esc_attr( $exclusions['post_ids'] ); ?>" class="regular-text" />
					<p class="description"><?php esc_html_e( 'Comma-separated list of Post or Page IDs to completely exclude from processing.', 'external-link-manager' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Exclude Post Types', 'external-link-manager' ); ?></th>
				<td>
					<fieldset>
						<legend class="screen-reader-text"><span><?php esc_html_e( 'Exclude Post Types', 'external-link-manager' ); ?></span></legend>
						<?php
						$post_types = get_post_types( array( 'public' => true ), 'objects' );
						foreach ( $post_types as $pt ) {
							$checked = in_array( $pt->name, $exclusions['post_types'], true );
							?>
							<label>
								<input type="checkbox" name="elm_exclusions[post_types][]" value="<?php echo esc_attr( $pt->name ); ?>" <?php checked( $checked ); ?> />
								<?php echo esc_html( $pt->label ); ?>
							</label><br>
							<?php
						}
						?>
						<p class="description"><?php esc_html_e( 'Select post types to exclude from being scanned.', 'external-link-manager' ); ?></p>
					</fieldset>
				</td>
			</tr>
		</tbody>
	</table>
	<?php submit_button(); ?>
</form>
