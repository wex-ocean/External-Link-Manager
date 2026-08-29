<?php
/**
 * Frontend rendering layer.
 *
 * Hooks into the_content filter (priority 20) to apply resolved link
 * attributes (global settings merged with per-link overrides) to external
 * <a> tags using WP_HTML_Tag_Processor. Modifies existing tags in place —
 * does not regenerate HTML from scratch.
 *
 * Performance:
 * - One cached DB lookup per post render (via ELM_Cache::get_post_links).
 * - Excluded posts/post types short-circuit before any processing.
 * - Excluded links/domains are skipped inside the tag loop.
 *
 * @package ExternalLinkManager
 * @since   0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ELM_Renderer
 *
 * Applies external link attributes on the frontend via the_content filter.
 */
class ELM_Renderer {

	/**
	 * Cached global settings array (loaded once per request).
	 *
	 * @var array|null
	 */
	private $settings = null;

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function init() {
		// Priority 20: run after standard content filters (wpautop is 10,
		// shortcodes are 11) but before most third-party filters.
		add_filter( 'the_content', array( $this, 'apply_attributes' ), 20 );
	}

	// ------------------------------------------------------------------
	// Main filter callback
	// ------------------------------------------------------------------

	/**
	 * Filter the_content — apply external link attributes.
	 *
	 * @param string $content Post HTML content.
	 * @return string Modified content with attributes applied.
	 */
	public function apply_attributes( $content ) {
		// Early bail: no content, or not a singular front-end request.
		if ( empty( $content ) || is_admin() || ! is_singular() ) {
			return $content;
		}

		$post = get_post();
		if ( ! $post ) {
			return $content;
		}

		// Early bail: check if this post type is allowed.
		$allowed_post_types = apply_filters(
			'elm_scanned_post_types',
			array( 'post', 'page' )
		);
		if ( ! in_array( $post->post_type, $allowed_post_types, true ) ) {
			return $content;
		}

		// Check global exclusions.
		if ( class_exists( 'ELM_Exclusions' ) && ( ELM_Exclusions::is_post_type_excluded( $post->post_type ) || ELM_Exclusions::is_post_id_excluded( $post->ID ) ) ) {
			return $content;
		}

		// Fetch this post's detected link rows (single cached query).
		$links = ELM_Cache::get_post_links( $post->ID );
		if ( empty( $links ) ) {
			return $content;
		}

		// Build a lookup: url_hash => link row for O(1) matching inside the loop.
		$link_map = array();
		foreach ( $links as $link ) {
			// Skip excluded links entirely — don't apply any attributes.
			if ( ! empty( $link->is_excluded ) ) {
				continue;
			}
			$link_map[ $link->url_hash ] = $link;
		}

		if ( empty( $link_map ) ) {
			return $content;
		}

		// Load global settings once per request.
		$settings = $this->get_settings();

		// Walk <a> tags with WP_HTML_Tag_Processor and apply attributes.
		$processor = new \WP_HTML_Tag_Processor( $content );

		while ( $processor->next_tag( 'a' ) ) {
			$href = $processor->get_attribute( 'href' );
			if ( empty( $href ) ) {
				continue;
			}

			// Hash the href and check if it's one of our tracked external links.
			$hash = md5( $href );
			if ( ! isset( $link_map[ $hash ] ) ) {
				continue;
			}

			// Check global URL exclusions on the fly to handle cached rows that might now be excluded.
			if ( class_exists( 'ELM_Exclusions' ) && ELM_Exclusions::is_url_excluded( $href ) ) {
				continue;
			}

			$link_row = $link_map[ $hash ];

			// Resolve attributes: merge global settings with per-link overrides.
			$resolved = $this->resolve_attributes( $settings, $link_row );

			// Apply resolved attributes to the tag.
			$this->set_tag_attributes( $processor, $resolved );
		}

		return $processor->get_updated_html();
	}

	// ------------------------------------------------------------------
	// Attribute resolution
	// ------------------------------------------------------------------

	/**
	 * Merge global settings with per-link overrides to produce final attributes.
	 *
	 * Per-link overrides (stored as JSON in attributes_json) take precedence
	 * over global settings. If attributes_json is NULL, global settings apply.
	 *
	 * @param array  $settings Global settings array.
	 * @param object $link_row DB row object with optional attributes_json.
	 * @return array Resolved settings array with the same keys as global settings.
	 */
	private function resolve_attributes( $settings, $link_row ) {
		if ( empty( $link_row->attributes_json ) ) {
			return $settings;
		}

		$overrides = json_decode( $link_row->attributes_json, true );
		if ( ! is_array( $overrides ) ) {
			return $settings;
		}

		// Overrides only replace keys they explicitly set; the rest inherit global.
		return array_merge( $settings, $overrides );
	}

	/**
	 * Apply resolved attribute settings to the current <a> tag.
	 *
	 * Modifies: target, rel, class attributes.
	 *
	 * @param \WP_HTML_Tag_Processor $processor Current processor state.
	 * @param array                  $resolved  Resolved settings array.
	 * @return void
	 */
	private function set_tag_attributes( $processor, $resolved ) {
		// --- target="_blank" ---
		if ( ! empty( $resolved['open_new_tab'] ) ) {
			$processor->set_attribute( 'target', '_blank' );
		}

		// --- rel attribute (nofollow, sponsored, ugc, noopener, noreferrer) ---
		$rel_parts   = $this->build_rel_parts( $resolved );
		$existing_rel = $processor->get_attribute( 'rel' );

		if ( ! empty( $rel_parts ) ) {
			// Merge with any existing rel values (from the editor or other plugins)
			// to avoid clobbering them (e.g., Yoast might add nofollow too).
			$merged_rel = $this->merge_rel( $existing_rel, $rel_parts );
			$processor->set_attribute( 'rel', $merged_rel );
		}

		// --- Custom CSS class ---
		if ( ! empty( $resolved['custom_class'] ) ) {
			$class = trim( $resolved['custom_class'] );
			if ( '' !== $class ) {
				$processor->add_class( $class );
			}
		}
	}

	/**
	 * Build the list of rel values from resolved settings.
	 *
	 * @param array $resolved Resolved settings.
	 * @return array Array of rel value strings (e.g. ['nofollow', 'noopener']).
	 */
	private function build_rel_parts( $resolved ) {
		$parts = array();

		$rel_map = array(
			'nofollow'   => 'nofollow',
			'sponsored'  => 'sponsored',
			'ugc'        => 'ugc',
			'noopener'   => 'noopener',
			'noreferrer' => 'noreferrer',
		);

		foreach ( $rel_map as $setting_key => $rel_value ) {
			if ( ! empty( $resolved[ $setting_key ] ) ) {
				$parts[] = $rel_value;
			}
		}

		return $parts;
	}

	/**
	 * Merge new rel values with any existing rel attribute, de-duplicating.
	 *
	 * @param string|null $existing_rel Current rel attribute value (may be null).
	 * @param array       $new_parts    New rel values to add.
	 * @return string Space-separated, de-duplicated rel string.
	 */
	private function merge_rel( $existing_rel, array $new_parts ) {
		$existing_parts = array();
		if ( ! empty( $existing_rel ) ) {
			$existing_parts = preg_split( '/\s+/', trim( $existing_rel ) );
		}

		$merged = array_unique( array_merge( $existing_parts, $new_parts ) );

		return implode( ' ', $merged );
	}

	// ------------------------------------------------------------------
	// Settings
	// ------------------------------------------------------------------

	/**
	 * Get global settings, cached for the request lifetime.
	 *
	 * @return array Settings array.
	 */
	private function get_settings() {
		if ( null === $this->settings ) {
			$this->settings = get_option( 'elm_settings', array() );

			// Ensure all expected keys exist with safe defaults.
			$this->settings = wp_parse_args(
				$this->settings,
				array(
					'open_new_tab' => true,
					'nofollow'     => false,
					'sponsored'    => false,
					'ugc'          => false,
					'noopener'     => true,
					'noreferrer'   => false,
					'custom_class' => '',
				)
			);
		}

		return $this->settings;
	}
}
