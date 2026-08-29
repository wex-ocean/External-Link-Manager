<?php
/**
 * Detection layer — scans post content for external links on save.
 *
 * Uses WP_HTML_Tag_Processor (WP 6.2+ core API) to walk <a> tags in post
 * content, identifies external hrefs by comparing host against home_url(),
 * then diffs results against the elm_links table (insert new, remove stale,
 * preserve per-link overrides).
 *
 * Hooked into save_post; skips autosaves, revisions, and non-published
 * post statuses.
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
 * Responsible for:
 * 1. Scanning post content for <a> tags with external hrefs.
 * 2. Diffing detected links against existing DB rows.
 * 3. Inserting new links, pruning stale ones, updating anchor text.
 */
class ELM_Detector {

	/**
	 * The parsed host of the site's home_url(), cached per request.
	 *
	 * @var string
	 */
	private $home_host = '';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'save_post', array( $this, 'on_save_post' ), 20, 2 );
		add_action( 'before_delete_post', array( $this, 'on_delete_post' ) );
	}

	// ------------------------------------------------------------------
	// Hook callbacks
	// ------------------------------------------------------------------

	/**
	 * Fires on save_post — orchestrates scan + diff-sync.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 * @return void
	 */
	public function on_save_post( $post_id, $post ) {
		// 1. Skip autosaves.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// 2. Skip revisions.
		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}

		// 3. Only process published/updated content that's publicly viewable.
		//    Draft/pending/private content could still have links worth tracking,
		//    but we follow the plan: "only on publish/update".
		if ( 'publish' !== $post->post_status ) {
			return;
		}

		// 4. Allow filtering which post types are scanned.
		$allowed_post_types = apply_filters(
			'elm_scanned_post_types',
			array( 'post', 'page' )
		);

		if ( ! in_array( $post->post_type, $allowed_post_types, true ) ) {
			return;
		}

		// 5. Scan content and sync to DB.
		$this->scan_and_sync( $post_id, $post->post_content );
	}

	/**
	 * Fires before a post is permanently deleted — removes all link rows.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function on_delete_post( $post_id ) {
		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}

		ELM_DB::delete_links_by_post( $post_id );
		ELM_Cache::invalidate_post( $post_id );
	}

	// ------------------------------------------------------------------
	// Core scanning logic
	// ------------------------------------------------------------------

	/**
	 * Scan HTML content for external <a> tags and sync results to DB.
	 *
	 * This is the heart of the detection layer. It:
	 * 1. Parses all <a> tags via WP_HTML_Tag_Processor.
	 * 2. Identifies external links (host differs from home_url()).
	 * 3. Diffs against existing DB rows for this post.
	 * 4. Inserts new links, updates changed anchor text, prunes stale rows.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $content Post HTML content.
	 * @return void
	 */
	public function scan_and_sync( $post_id, $content ) {
		$detected = $this->extract_external_links( $content );
		$this->diff_and_write( $post_id, $detected );
	}

	/**
	 * Extract all external links from HTML content.
	 *
	 * @param string $content HTML content.
	 * @return array Array of link arrays, each containing:
	 *               'url', 'url_hash', 'domain', 'anchor_text'.
	 *               Keyed by url_hash for easy diffing.
	 */
	public function extract_external_links( $content ) {
		if ( empty( $content ) ) {
			return array();
		}

		$home_host = $this->get_home_host();
		$links     = array();

		$processor = new \WP_HTML_Tag_Processor( $content );

		while ( $processor->next_tag( 'a' ) ) {
			$href = $processor->get_attribute( 'href' );

			// Skip empty, anchor-only, or protocol-relative-but-same-domain links.
			if ( empty( $href ) ) {
				continue;
			}

			// Skip non-HTTP(S) schemes: mailto:, tel:, javascript:, #anchors, etc.
			if ( preg_match( '/^(#|mailto:|tel:|javascript:|data:)/i', $href ) ) {
				continue;
			}

			// Parse the URL to extract the host.
			$parsed = wp_parse_url( $href );

			// Skip relative URLs (no host) — they're internal by definition.
			if ( empty( $parsed['host'] ) ) {
				continue;
			}

			$link_host = strtolower( $parsed['host'] );

			// Compare against home host — if same, it's internal.
			if ( $this->is_same_host( $link_host, $home_host ) ) {
				continue;
			}

			// It's external — collect it.
			$url_hash = md5( $href );

			// Extract visible text content. WP_HTML_Tag_Processor doesn't
			// natively expose inner text, so we extract it between the
			// current <a> tag's position markers using a regex on the
			// bookmark positions. For safety, we store a reasonable fallback.
			// WP 6.7+ has get_modifiable_text(), but for 6.4 compat we
			// use a simpler approach via a helper.
			$anchor_text = $this->extract_anchor_text( $processor, $content );

			// De-duplicate: if the same URL appears multiple times in the
			// post, keep the first occurrence's anchor text.
			if ( ! isset( $links[ $url_hash ] ) ) {
				$links[ $url_hash ] = array(
					'url'         => $href,
					'url_hash'    => $url_hash,
					'domain'      => $link_host,
					'anchor_text' => $anchor_text,
				);
			}
		}

		return $links;
	}

	// ------------------------------------------------------------------
	// Diff & write
	// ------------------------------------------------------------------

	/**
	 * Diff detected links against existing DB rows and sync.
	 *
	 * - New links (hash not in DB): INSERT.
	 * - Existing links (hash in DB): update anchor_text if changed.
	 * - Stale links (hash in DB but not in detected set): DELETE
	 *   (only if no per-link override or exclusion flag — those are preserved).
	 *
	 * @param int   $post_id  Post ID.
	 * @param array $detected Detected links keyed by url_hash.
	 * @return void
	 */
	private function diff_and_write( $post_id, array $detected ) {
		// Fetch existing hashes for this post: url_hash => row {id, url_hash, anchor_text}.
		$existing = ELM_DB::get_hashes_for_post( $post_id );

		$detected_hashes = array_keys( $detected );
		$has_changes     = false;

		// --- Insert new links ---
		foreach ( $detected as $hash => $link_data ) {
			if ( isset( $existing[ $hash ] ) ) {
				// Link already exists — check if anchor text changed.
				$existing_row = $existing[ $hash ];
				if ( $existing_row->anchor_text !== $link_data['anchor_text'] ) {
					ELM_DB::update_anchor_text(
						(int) $existing_row->id,
						$link_data['anchor_text']
					);
					$has_changes = true;
				}
				continue;
			}

			// New link — insert.
			ELM_DB::insert_link(
				array(
					'post_id'     => $post_id,
					'url'         => $link_data['url'],
					'url_hash'    => $link_data['url_hash'],
					'domain'      => $link_data['domain'],
					'anchor_text' => $link_data['anchor_text'],
				)
			);
			$has_changes = true;
		}

		// --- Prune stale links ---
		// delete_stale_links handles the "preserve overrides" logic internally.
		$deleted = ELM_DB::delete_stale_links( $post_id, $detected_hashes );
		if ( ! empty( $deleted ) ) {
			$has_changes = true;
		}

		if ( $has_changes ) {
			ELM_Cache::invalidate_post( $post_id );
		}
	}

	// ------------------------------------------------------------------
	// Helpers
	// ------------------------------------------------------------------

	/**
	 * Get the site's home host, cached for the request lifetime.
	 *
	 * @return string Lowercase hostname (e.g. 'example.com').
	 */
	private function get_home_host() {
		if ( '' === $this->home_host ) {
			$parsed          = wp_parse_url( home_url() );
			$this->home_host = isset( $parsed['host'] ) ? strtolower( $parsed['host'] ) : '';
		}
		return $this->home_host;
	}

	/**
	 * Compare two hosts, stripping leading 'www.' for flexible matching.
	 *
	 * e.g. 'www.example.com' and 'example.com' are treated as the same host.
	 *
	 * @param string $host_a First host.
	 * @param string $host_b Second host (typically the home host).
	 * @return bool True if the hosts are considered the same.
	 */
	private function is_same_host( $host_a, $host_b ) {
		$strip = static function ( $host ) {
			return preg_replace( '/^www\./i', '', $host );
		};

		return $strip( $host_a ) === $strip( $host_b );
	}

	/**
	 * Extract visible anchor text for the current <a> tag.
	 *
	 * WP_HTML_Tag_Processor (pre-6.7) doesn't expose inner text directly.
	 * We use a conservative regex approach: find the serialized <a ...>
	 * opening tag in the content and grab text up to the closing </a>.
	 *
	 * Falls back to empty string if extraction fails — anchor_text is
	 * informational, not critical.
	 *
	 * @param \WP_HTML_Tag_Processor $processor Current processor state.
	 * @param string                 $content   Full HTML content.
	 * @return string Extracted anchor text (may be empty).
	 */
	private function extract_anchor_text( $processor, $content ) {
		// Use bookmarks to find our position in the content.
		$bookmark_name = '_elm_anchor_' . wp_rand();

		if ( ! $processor->set_bookmark( $bookmark_name ) ) {
			return '';
		}

		// Get the updated HTML to find our position.
		// The processor tracks byte offsets; we'll use a simpler approach:
		// get the tag's href and search for the nearest </a> after the
		// current match to extract inner content.
		$href = $processor->get_attribute( 'href' );
		$processor->release_bookmark( $bookmark_name );

		if ( empty( $href ) ) {
			return '';
		}

		// Find this specific <a> tag with this href and extract inner text.
		// We escape the href for use in regex.
		$escaped_href = preg_quote( $href, '/' );

		// Match: <a ... href="<this-href>" ...>INNER TEXT</a>
		// Using a non-greedy match for the inner content.
		$pattern = '/<a\s[^>]*href\s*=\s*["\']' . $escaped_href . '["\'][^>]*>(.*?)<\/a>/is';

		if ( preg_match( $pattern, $content, $matches ) ) {
			// Strip any nested HTML tags from anchor text, keep only text.
			$text = wp_strip_all_tags( $matches[1] );
			return mb_substr( trim( $text ), 0, 500 ); // Reasonable length cap.
		}

		return '';
	}
}
