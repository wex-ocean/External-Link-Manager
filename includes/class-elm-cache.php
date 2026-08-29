<?php
/**
 * Cache get/set/invalidate helpers.
 *
 * Wraps wp_cache (object cache) with a transient fallback for environments
 * without a persistent object cache. All cache keys are prefixed with 'elm_'
 * to avoid collisions.
 *
 * Cache is invalidated on:
 * - Any write to the elm_links table (via ELM_Cache::invalidate_post).
 * - Settings changes (via ELM_Cache::invalidate_all).
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
 * Provides a thin, consistent API for caching link data per-post and
 * aggregate dashboard queries. Uses the object cache group 'elm' for
 * easy group-level flushing where supported.
 */
class ELM_Cache {

	/**
	 * Object cache group name.
	 *
	 * @var string
	 */
	const GROUP = 'elm';

	/**
	 * Default TTL for transient fallback (1 hour).
	 *
	 * @var int
	 */
	const TTL = HOUR_IN_SECONDS;

	// ------------------------------------------------------------------
	// Get / Set
	// ------------------------------------------------------------------

	/**
	 * Retrieve a cached value.
	 *
	 * Checks the object cache first (fastest), then falls back to a
	 * transient for environments without a persistent object cache
	 * (e.g. default WP installs without Redis/Memcached).
	 *
	 * @param string $key Cache key (without prefix — we add it).
	 * @return mixed Cached value, or false if not found.
	 */
	public static function get( $key ) {
		$found = false;
		$value = wp_cache_get( $key, self::GROUP, false, $found );

		if ( $found ) {
			return $value;
		}

		// Transient fallback.
		$transient = get_transient( 'elm_' . $key );
		if ( false !== $transient ) {
			// Warm the object cache so subsequent calls in the same
			// request hit the faster layer.
			wp_cache_set( $key, $transient, self::GROUP, self::TTL );
			return $transient;
		}

		return false;
	}

	/**
	 * Store a value in cache.
	 *
	 * Writes to both the object cache and a transient so the value
	 * persists across requests even without a persistent object cache.
	 *
	 * @param string $key   Cache key (without prefix).
	 * @param mixed  $value Value to cache (must be serializable).
	 * @param int    $ttl   Time-to-live in seconds. Default: self::TTL.
	 * @return bool True on success.
	 */
	public static function set( $key, $value, $ttl = 0 ) {
		if ( 0 === $ttl ) {
			$ttl = self::TTL;
		}

		wp_cache_set( $key, $value, self::GROUP, $ttl );
		set_transient( 'elm_' . $key, $value, $ttl );

		return true;
	}

	// ------------------------------------------------------------------
	// Invalidation
	// ------------------------------------------------------------------

	/**
	 * Invalidate cached link data for a specific post.
	 *
	 * Called after any write to elm_links for this post_id (insert, update,
	 * delete, stale-link pruning).
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public static function invalidate_post( $post_id ) {
		$key = 'post_links_' . absint( $post_id );

		wp_cache_delete( $key, self::GROUP );
		delete_transient( 'elm_' . $key );

		// Also bust any dashboard aggregate caches that may include this post.
		self::invalidate_aggregates();
	}

	/**
	 * Invalidate all plugin caches (dashboard aggregates, settings-derived).
	 *
	 * Called on settings change or when a broad cache flush is needed.
	 *
	 * @return void
	 */
	public static function invalidate_all() {
		global $wpdb;

		// Flush the entire object cache group where supported.
		wp_cache_flush_group( self::GROUP );

		// For object caches that don't support group flushing (or no
		// persistent object cache at all), delete all elm_ transients.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			"DELETE FROM `{$wpdb->options}` WHERE `option_name` LIKE '_transient_elm_%'" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			"DELETE FROM `{$wpdb->options}` WHERE `option_name` LIKE '_transient_timeout_elm_%'" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);
	}

	/**
	 * Invalidate dashboard aggregate caches only.
	 *
	 * @return void
	 */
	public static function invalidate_aggregates() {
		$aggregate_keys = array(
			'dashboard_total_count',
			'dashboard_domain_counts',
		);

		foreach ( $aggregate_keys as $key ) {
			wp_cache_delete( $key, self::GROUP );
			delete_transient( 'elm_' . $key );
		}
	}

	// ------------------------------------------------------------------
	// Convenience: cached post-link lookups
	// ------------------------------------------------------------------

	/**
	 * Get link rows for a post, with caching.
	 *
	 * This is the primary method used by the renderer. Returns the same
	 * result as ELM_DB::get_links_by_post() but cached.
	 *
	 * @param int $post_id Post ID.
	 * @return array Array of row objects (may be empty).
	 */
	public static function get_post_links( $post_id ) {
		$key    = 'post_links_' . absint( $post_id );
		$cached = self::get( $key );

		if ( false !== $cached ) {
			return $cached;
		}

		$links = ELM_DB::get_links_by_post( $post_id );
		self::set( $key, $links );

		return $links;
	}
}
