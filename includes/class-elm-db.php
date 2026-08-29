<?php
/**
 * Database CRUD/query wrapper for the elm_links table.
 *
 * All queries go through $wpdb->prepare() exclusively — zero
 * string-concatenated queries. Provides a clean API for insert, update,
 * delete, and retrieval so the rest of the plugin never touches $wpdb
 * directly for link data.
 *
 * @package ExternalLinkManager
 * @since   0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ELM_DB
 *
 * Thin query layer over the {prefix}elm_links custom table. Every public
 * method returns either a typed result or WP's raw $wpdb return value.
 */
class ELM_DB {

	/**
	 * Return the fully-prefixed table name.
	 *
	 * @return string
	 */
	public static function table_name() {
		global $wpdb;
		return $wpdb->prefix . 'elm_links';
	}

	// ------------------------------------------------------------------
	// Single-row operations
	// ------------------------------------------------------------------

	/**
	 * Insert a new link row.
	 *
	 * @param array $data {
	 *     Required. Link data.
	 *
	 *     @type int    $post_id     Post ID.
	 *     @type string $url         Full URL.
	 *     @type string $url_hash    md5( $url ).
	 *     @type string $domain      Extracted host.
	 *     @type string $anchor_text Visible link text (may be empty).
	 * }
	 * @return int|false Inserted row ID on success, false on failure.
	 */
	public static function insert_link( array $data ) {
		global $wpdb;

		$now = current_time( 'mysql', true ); // GMT.

		$result = $wpdb->insert(
			self::table_name(),
			array(
				'post_id'     => absint( $data['post_id'] ),
				'url'         => $data['url'],
				'url_hash'    => $data['url_hash'],
				'domain'      => $data['domain'],
				'anchor_text' => isset( $data['anchor_text'] ) ? $data['anchor_text'] : '',
				'detected_at' => $now,
				'updated_at'  => $now,
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		return false !== $result ? $wpdb->insert_id : false;
	}

	/**
	 * Update an existing link row (by primary key).
	 *
	 * Only touches the columns provided in $data plus updated_at.
	 * Caller should NOT pass 'id' inside $data — it's used for WHERE only.
	 *
	 * @param int   $id   Row primary key.
	 * @param array $data Column => value pairs to update.
	 * @return int|false   Number of rows updated, or false on error.
	 */
	public static function update_link( $id, array $data ) {
		global $wpdb;

		$data['updated_at'] = current_time( 'mysql', true );

		// Build format array (all strings; caller can override if needed).
		$formats = array_fill( 0, count( $data ), '%s' );

		return $wpdb->update(
			self::table_name(),
			$data,
			array( 'id' => absint( $id ) ),
			$formats,
			array( '%d' )
		);
	}

	/**
	 * Delete a single link row by primary key.
	 *
	 * @param int $id Row primary key.
	 * @return int|false Number of rows deleted, or false on error.
	 */
	public static function delete_link( $id ) {
		global $wpdb;

		return $wpdb->delete(
			self::table_name(),
			array( 'id' => absint( $id ) ),
			array( '%d' )
		);
	}

	// ------------------------------------------------------------------
	// Bulk / query operations
	// ------------------------------------------------------------------

	/**
	 * Get all link rows for a given post.
	 *
	 * @param int $post_id Post ID.
	 * @return array Array of row objects (may be empty).
	 */
	public static function get_links_by_post( $post_id ) {
		global $wpdb;

		$table = self::table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM `{$table}` WHERE post_id = %d ORDER BY id ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				absint( $post_id )
			)
		);
	}

	/**
	 * Get all url_hash values for a post, keyed by url_hash.
	 *
	 * Used by the detector's diff logic to know which links already exist
	 * without loading full row data.
	 *
	 * @param int $post_id Post ID.
	 * @return array Associative array: url_hash => row object (id, url_hash, anchor_text).
	 */
	public static function get_hashes_for_post( $post_id ) {
		global $wpdb;

		$table = self::table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, url_hash, anchor_text FROM `{$table}` WHERE post_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				absint( $post_id )
			)
		);

		$keyed = array();
		foreach ( $rows as $row ) {
			$keyed[ $row->url_hash ] = $row;
		}
		return $keyed;
	}

	/**
	 * Delete all link rows for a post whose url_hash is NOT in the given set.
	 *
	 * This prunes stale links that no longer appear in the post content,
	 * but only removes rows that have NOT been manually overridden
	 * (attributes_json IS NULL) or excluded (is_excluded = 0).
	 *
	 * Links with per-link overrides or manual exclusions are preserved to
	 * avoid destroying user configuration — they'll just stop appearing
	 * in the post content but remain in the dashboard for reference.
	 *
	 * @param int   $post_id     Post ID.
	 * @param array $keep_hashes Array of url_hash strings to keep.
	 * @return int|false Number of rows deleted, or false on error.
	 */
	public static function delete_stale_links( $post_id, array $keep_hashes ) {
		global $wpdb;

		$table   = self::table_name();
		$post_id = absint( $post_id );

		if ( empty( $keep_hashes ) ) {
			// No current links — delete all non-overridden links for this post.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			return $wpdb->query(
				$wpdb->prepare(
					"DELETE FROM `{$table}` WHERE post_id = %d AND attributes_json IS NULL AND is_excluded = 0", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$post_id
				)
			);
		}

		// Build a safe set of placeholders for the IN clause.
		$placeholders = implode( ', ', array_fill( 0, count( $keep_hashes ), '%s' ) );

		// Merge post_id + hashes into a single prepare args array.
		$args   = array_merge( array( $post_id ), array_values( $keep_hashes ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->query(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
				"DELETE FROM `{$table}` WHERE post_id = %d AND url_hash NOT IN ({$placeholders}) AND attributes_json IS NULL AND is_excluded = 0",
				$args
			)
		);
	}

	/**
	 * Update anchor text for an existing row (used during diff-sync when
	 * the URL already exists but the anchor text has changed).
	 *
	 * @param int    $id          Row primary key.
	 * @param string $anchor_text New anchor text.
	 * @return int|false
	 */
	public static function update_anchor_text( $id, $anchor_text ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->update(
			self::table_name(),
			array(
				'anchor_text' => $anchor_text,
				'updated_at'  => current_time( 'mysql', true ),
			),
			array( 'id' => absint( $id ) ),
			array( '%s', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Delete all links for a given post (used when a post is trashed/deleted).
	 *
	 * @param int $post_id Post ID.
	 * @return int|false Number of rows deleted, or false on error.
	 */
	public static function delete_links_by_post( $post_id ) {
		global $wpdb;

		return $wpdb->delete(
			self::table_name(),
			array( 'post_id' => absint( $post_id ) ),
			array( '%d' )
		);
	}
}
