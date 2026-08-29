<?php
/**
 * Cache get/set/invalidate helpers.
 *
 * Wraps wp_cache (object cache) with a transient fallback. All cache keys
 * are prefixed with 'elm_' to avoid collisions. Cache is invalidated on
 * any write to the elm_links table or on settings change.
 *
 * @package ExternalLinkManager
 * @since   0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ELM_Cache
 *
 * Placeholder — implementation added in Stage 2.
 */
class ELM_Cache {
	// TODO: implement in Stage 2.
}
