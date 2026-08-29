<?php
/**
 * Detection layer — scans post content for external links on save.
 *
 * Uses WP_HTML_Tag_Processor (WP 6.2+ core API) to walk <a> tags in post
 * content, identifies external hrefs by comparing host against home_url(),
 * then diffs results against the elm_links table (insert new, remove stale,
 * preserve per-link overrides).
 *
 * @package ExternalLinkManager
 * @since   0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ELM_Detector
 *
 * Placeholder — implementation added in Stage 2.
 */
class ELM_Detector {
	// TODO: implement in Stage 2.
}
