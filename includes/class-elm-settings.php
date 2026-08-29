<?php
/**
 * Settings API registration and sanitization callbacks.
 *
 * Registers all plugin settings with register_setting(), defines settings
 * sections and fields, and provides sanitize_callback per field type.
 * Settings page UI lives in admin/views/settings-*.php.
 *
 * @package ExternalLinkManager
 * @since   0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ELM_Settings
 */
class ELM_Settings {

	/**
	 * Register hooks.
	 */
	public function init() {
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	/**
	 * Register settings using the WP Settings API.
	 */
	public function register_settings() {
		// Register general settings.
		register_setting(
			'elm_settings_group',
			'elm_settings',
			array(
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
			)
		);

		// Register exclusions settings.
		register_setting(
			'elm_exclusions_group',
			'elm_exclusions',
			array(
				'sanitize_callback' => array( $this, 'sanitize_exclusions' ),
			)
		);
	}

	/**
	 * Sanitize general settings.
	 *
	 * @param array $input Raw input.
	 * @return array Sanitized output.
	 */
	public function sanitize_settings( $input ) {
		$sanitized = array();

		// Fetch existing to ensure we don't drop fields if they aren't present in form submission.
		$existing = get_option( 'elm_settings', array() );
		$input    = wp_parse_args( (array) $input, $existing );

		$sanitized['open_new_tab'] = ! empty( $input['open_new_tab'] );
		$sanitized['nofollow']     = ! empty( $input['nofollow'] );
		$sanitized['sponsored']    = ! empty( $input['sponsored'] );
		$sanitized['ugc']          = ! empty( $input['ugc'] );
		$sanitized['noopener']     = ! empty( $input['noopener'] );
		$sanitized['noreferrer']   = ! empty( $input['noreferrer'] );
		$sanitized['custom_class'] = isset( $input['custom_class'] ) ? sanitize_text_field( $input['custom_class'] ) : '';

		// Invalidate cache when settings change.
		if ( class_exists( 'ELM_Cache' ) ) {
			ELM_Cache::invalidate_all();
		}

		return $sanitized;
	}

	/**
	 * Sanitize exclusion rules.
	 *
	 * @param array $input Raw input.
	 * @return array Sanitized output.
	 */
	public function sanitize_exclusions( $input ) {
		$sanitized = array();

		// Fetch existing.
		$existing = get_option( 'elm_exclusions', array() );
		$input    = wp_parse_args( (array) $input, $existing );

		// Domains: text area, split by newline, sanitize each line.
		if ( isset( $input['domains'] ) ) {
			$domains = explode( "\n", str_replace( "\r", '', $input['domains'] ) );
			$domains = array_map( 'sanitize_text_field', $domains );
			$domains = array_map( 'trim', $domains );
			$sanitized['domains'] = implode( "\n", array_filter( $domains ) );
		} else {
			$sanitized['domains'] = '';
		}

		// URLs: text area, split by newline, sanitize each line (esc_url_raw).
		if ( isset( $input['urls'] ) ) {
			$urls = explode( "\n", str_replace( "\r", '', $input['urls'] ) );
			$urls = array_map( 'esc_url_raw', $urls );
			$urls = array_map( 'trim', $urls );
			$sanitized['urls'] = implode( "\n", array_filter( $urls ) );
		} else {
			$sanitized['urls'] = '';
		}

		// Post IDs: text field, comma separated, absint each.
		if ( isset( $input['post_ids'] ) ) {
			$ids = explode( ',', $input['post_ids'] );
			$ids = array_map( 'trim', $ids );
			$ids = array_map( 'absint', $ids );
			$sanitized['post_ids'] = implode( ', ', array_filter( $ids ) );
		} else {
			$sanitized['post_ids'] = '';
		}

		// Post types: array of strings.
		if ( isset( $input['post_types'] ) && is_array( $input['post_types'] ) ) {
			$sanitized['post_types'] = array_map( 'sanitize_key', $input['post_types'] );
		} else {
			$sanitized['post_types'] = array();
		}

		// Invalidate cache when exclusions change.
		if ( class_exists( 'ELM_Cache' ) ) {
			ELM_Cache::invalidate_all();
		}

		return $sanitized;
	}
}
