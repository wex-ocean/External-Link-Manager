<?php
/**
 * Import/Export handler for plugin settings and exclusions.
 *
 * @package ExternalLinkManager
 * @since   0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ELM_Import_Export
 */
class ELM_Import_Export {

	/**
	 * Register hooks.
	 */
	public function init() {
		add_action( 'admin_init', array( $this, 'process_export' ) );
		add_action( 'admin_init', array( $this, 'process_import' ) );
	}

	/**
	 * Process settings export request.
	 */
	public function process_export() {
		if ( ! isset( $_POST['elm_export_nonce'] ) || ! wp_verify_nonce( $_POST['elm_export_nonce'], 'elm_export_action' ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized access.', 'external-link-manager' ) );
		}

		$data = array(
			'version'        => ELM_VERSION,
			'elm_settings'   => get_option( 'elm_settings', array() ),
			'elm_exclusions' => get_option( 'elm_exclusions', array() ),
		);

		$json = wp_json_encode( $data );

		header( 'Content-Description: File Transfer' );
		header( 'Content-Disposition: attachment; filename=elm-settings-export-' . date( 'Y-m-d' ) . '.json' );
		header( 'Content-Type: application/json; charset=' . get_option( 'blog_charset' ) );

		echo $json;
		exit;
	}

	/**
	 * Process settings import request.
	 */
	public function process_import() {
		if ( ! isset( $_POST['elm_import_nonce'] ) || ! wp_verify_nonce( $_POST['elm_import_nonce'], 'elm_import_action' ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized access.', 'external-link-manager' ) );
		}

		if ( empty( $_FILES['elm_import_file']['tmp_name'] ) ) {
			add_settings_error( 'elm_settings_messages', 'elm_import_error', __( 'Please select a file to import.', 'external-link-manager' ), 'error' );
			return;
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$file_content = file_get_contents( wp_unslash( $_FILES['elm_import_file']['tmp_name'] ) );
		if ( ! $file_content ) {
			add_settings_error( 'elm_settings_messages', 'elm_import_error', __( 'Failed to read the imported file.', 'external-link-manager' ), 'error' );
			return;
		}

		$data = json_decode( $file_content, true );
		if ( ! $data || ! isset( $data['elm_settings'] ) || ! isset( $data['elm_exclusions'] ) ) {
			add_settings_error( 'elm_settings_messages', 'elm_import_error', __( 'Invalid file format.', 'external-link-manager' ), 'error' );
			return;
		}

		// Instantiate settings to use sanitize callbacks
		$settings_class = new ELM_Settings();
		$clean_settings   = $settings_class->sanitize_settings( $data['elm_settings'] );
		$clean_exclusions = $settings_class->sanitize_exclusions( $data['elm_exclusions'] );

		update_option( 'elm_settings', $clean_settings );
		update_option( 'elm_exclusions', $clean_exclusions );

		if ( class_exists( 'ELM_Cache' ) ) {
			ELM_Cache::invalidate_all();
		}

		add_settings_error( 'elm_settings_messages', 'elm_import_success', __( 'Settings imported successfully.', 'external-link-manager' ), 'success' );
	}
}
