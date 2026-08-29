=== External Link Manager ===
Contributors: your_username
Tags: external links, links, seo, link manager, nofollow, sponsored
Requires at least: 6.4
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A fast, DB-cached, zero-bloat external link manager. Automatically apply SEO attributes and manage outgoing links without frontend performance drops.

== Description ==

External Link Manager is built for modern WordPress sites that require strict control over outbound links but refuse to compromise on frontend performance. 

Unlike older link management plugins that parse your HTML on every page load, External Link Manager shifts the heavy lifting to the backend. It scans content when a post is saved using the native `WP_HTML_Tag_Processor`, stores the links in a highly optimized custom database table, and uses a single object-cached lookup to apply attributes (`nofollow`, `sponsored`, `target="_blank"`, etc.) instantly on the frontend.

**Key Features:**
* **Zero Frontend Bloat:** Precomputed link attributes applied via the `the_content` filter using ultra-fast hash maps and cached lookups.
* **Per-Link Overrides:** Don't want to apply global rules to a specific partner link? Detach it in the dashboard and set unique attributes just for that link.
* **Granular Exclusions:** Exclude specific domains, exact URLs, entire Post Types, or specific Post IDs from being processed.
* **Native Dashboard:** Manage all detected external links through a clean, searchable `WP_List_Table` interface.
* **Clean Uninstall:** Drops its custom table and cleans up options/transients automatically if uninstalled. No orphaned data left behind.
* **Import/Export:** Easily migrate your global link rules and exclusions across environments using JSON.

== Installation ==

1. Upload the `external-link-manager` folder to the `/wp-content/plugins/` directory, or install it directly through the WordPress Plugins screen.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Navigate to **External Links > Settings** in the admin menu to configure your global link attributes (like opening in a new tab or adding `nofollow`).
4. Publish or update a post — the plugin will automatically scan and catalog any external links in your content.

== Frequently Asked Questions ==

= Does this plugin parse my content on every page load? =
No! The plugin only parses content when you save or publish a post. It stores the detected links in the database and caches the results, ensuring your frontend rendering remains incredibly fast.

= How do I exclude a specific domain from being treated as external? =
Navigate to **External Links > Settings > Exclusions**. You can enter domains (e.g., `sister-site.com`), specific URLs, or even exclude entire post types.

= Can I override the global settings for just one link? =
Yes. In the **External Links > Dashboard**, hover over the link you want to change and click "Override Attributes." You can then manually specify whether that specific link should have `nofollow`, `sponsored`, or open in a new tab, ignoring your global settings.

== Changelog ==

= 0.1.0 =
* Initial MVP release.
* Features: Global attribute management, Dashboard link table, Per-link overrides, Exclusions engine, and Import/Export capabilities.
