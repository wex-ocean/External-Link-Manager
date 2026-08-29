<?php
/**
 * Exclusion rule matching logic.
 *
 * Evaluates whether a given link/post should be excluded from processing
 * based on configured rules: domain whitelist, specific URL, post/page ID,
 * or post type.
 *
 * @package ExternalLinkManager
 * @since   0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ELM_Exclusions
 */
class ELM_Exclusions {

	/**
	 * Cached exclusion settings for the request.
	 *
	 * @var array|null
	 */
	private static $rules = null;

	/**
	 * Get the configured exclusion rules.
	 *
	 * @return array
	 */
	public static function get_rules() {
		if ( null === self::$rules ) {
			self::$rules = get_option( 'elm_exclusions', array() );
			self::$rules = wp_parse_args(
				self::$rules,
				array(
					'domains'    => '',
					'urls'       => '',
					'post_ids'   => '',
					'post_types' => array(),
				)
			);
		}

		return self::$rules;
	}

	/**
	 * Check if a post type is excluded.
	 *
	 * @param string $post_type
	 * @return bool
	 */
	public static function is_post_type_excluded( $post_type ) {
		$rules = self::get_rules();
		if ( empty( $rules['post_types'] ) || ! is_array( $rules['post_types'] ) ) {
			return false;
		}

		return in_array( $post_type, $rules['post_types'], true );
	}

	/**
	 * Check if a specific post ID is excluded.
	 *
	 * @param int $post_id
	 * @return bool
	 */
	public static function is_post_id_excluded( $post_id ) {
		$rules = self::get_rules();
		if ( empty( $rules['post_ids'] ) ) {
			return false;
		}

		$ids = array_map( 'absint', explode( ',', $rules['post_ids'] ) );
		return in_array( absint( $post_id ), $ids, true );
	}

	/**
	 * Check if a given URL or its domain is excluded.
	 *
	 * @param string $url Full URL to check.
	 * @return bool
	 */
	public static function is_url_excluded( $url ) {
		$rules = self::get_rules();

		// Check specific URLs.
		if ( ! empty( $rules['urls'] ) ) {
			$excluded_urls = array_map( 'trim', explode( "\n", str_replace( "\r", '', $rules['urls'] ) ) );
			if ( in_array( $url, $excluded_urls, true ) ) {
				return true;
			}
		}

		// Check domains.
		if ( ! empty( $rules['domains'] ) ) {
			$excluded_domains = array_map( 'trim', explode( "\n", str_replace( "\r", '', $rules['domains'] ) ) );
			$parsed_url       = wp_parse_url( $url );
			
			if ( ! empty( $parsed_url['host'] ) ) {
				$host = strtolower( preg_replace( '/^www\./i', '', $parsed_url['host'] ) );

				foreach ( $excluded_domains as $domain ) {
					$domain = strtolower( preg_replace( '/^www\./i', '', $domain ) );
					if ( $host === $domain || substr( $host, -strlen( '.' . $domain ) ) === '.' . $domain ) {
						return true;
					}
				}
			}
		}

		return false;
	}
}
