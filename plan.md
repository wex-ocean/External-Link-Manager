# External Link Manager — Production Plan

**Plugin Slug:** `external-link-manager`
**Target:** WordPress.org public repository, production-grade, WP 6.4+
**Author POV:** Senior WP Engineer plan (a–z)

---

## 1. Vision & Positioning

A fast, DB-cached, zero-bloat external link manager. Differentiators vs. existing plugins (WP External Links, Extenal Links, etc.):

- **No live-parsing on frontend** — everything precomputed and cached at save time
- **Per-link override system** — not just global rules
- **Native `WP_List_Table` dashboard** — no heavy JS framework, stays lightweight
- **Broken link health checks** via Action Scheduler (not raw WP-Cron)
- **Clean uninstall** — no orphaned data, no bloat left behind

Non-goals for v1: link redirection/cloaking (legal/affiliate complexity — v2 candidate), multisite network-wide dashboard (v2), Gutenberg block-level link picker (v2).

---

## 2. Feature Set

### MVP (v1.0 — WordPress.org submission)
- Auto-detect external links in post content on save (`save_post` hook)
- Global settings:
  - Open in new tab
  - `nofollow`, `sponsored`, `ugc` (individually toggleable, per Google's 2020 link attribute guidance)
  - `noopener`, `noreferrer`
  - Custom CSS class
- Exclusion rules:
  - By domain (whitelist)
  - By specific URL
  - By post/page ID
  - By post type
- Admin dashboard: searchable/sortable table of all detected external links, source post, domain, applied attributes, last scanned date
- Per-link override (click a row → override global settings for that specific link)
- Settings page (Settings API, tabs: General / Exclusions / Advanced)
- Import/export settings as JSON
- Full i18n/l10n readiness
- Uninstall cleanup (drop custom table + options on uninstall.php)

### v1.1 (fast-follow, post-launch)
- Broken/dead link checker (Action Scheduler, batched, HTTP status check)
- CSV export of link dashboard
- Bulk actions (bulk exclude, bulk re-scan)

### v2 (roadmap, not in initial submission)
- Redirect/cloaking for affiliate links (`/go/xyz` style, with click tracking)
- Gutenberg block integration (link attribute panel in block editor sidebar)
- Multisite network dashboard
- REST API endpoints for headless/JS dashboard consumers

---

## 3. System Architecture

### 3.1 Detection Layer
- Hook: `save_post` (only on publish/update, not autosave/revision)
- Parser: `WP_HTML_Tag_Processor` (WP 6.2+ core API) — safer and faster than regex or `DOMDocument`, no external dependency
- Logic: walk `<a>` tags in post content, compare `href` host against `home_url()` host → flag as external
- Result written to custom DB table (see 3.2), diffed against existing rows (insert new, remove stale, keep overrides intact)

### 3.2 Storage Layer
Custom table instead of postmeta — postmeta doesn't scale for cross-post querying/filtering.

```sql
CREATE TABLE {$wpdb->prefix}elm_links (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  post_id BIGINT UNSIGNED NOT NULL,
  url TEXT NOT NULL,
  url_hash CHAR(32) NOT NULL,      -- md5(url) for fast unique lookups
  domain VARCHAR(255) NOT NULL,
  anchor_text TEXT,
  attributes_json TEXT,             -- per-link override, NULL = inherit global
  is_excluded TINYINT(1) DEFAULT 0,
  link_status VARCHAR(20) DEFAULT 'unchecked', -- unchecked/ok/broken (v1.1)
  last_checked DATETIME NULL,
  detected_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  UNIQUE KEY post_url (post_id, url_hash),
  KEY domain_idx (domain),
  KEY post_idx (post_id)
);
```

Created/updated via `dbDelta()` in an activation hook + version-checked upgrade routine (`elm_db_version` option).

### 3.3 Caching Layer
- Dashboard aggregate queries (counts, filters) → `wp_cache` (object cache) with transient fallback, keyed by filter params
- Cache invalidated on: link table write, settings change
- Frontend never queries the DB per-pageview for rendering — attributes are resolved from a single lightweight lookup keyed by post_id, loaded once per `the_content` pass

### 3.4 Rendering/Output Layer
- Filter: `the_content` (priority 20, after other content filters)
- On render: fetch this post's link rows (single query, cached), apply resolved attributes (global settings + per-link override merge) via `WP_HTML_Tag_Processor` — modifies existing `<a>` tags in place, doesn't regenerate HTML from scratch
- Excluded posts/post types/domains short-circuit before any processing (early bail for performance)

### 3.5 Cron/Health-Check Layer (v1.1)
- Action Scheduler (bundled or required via Composer) instead of raw WP-Cron — more reliable, has retry/logging built in
- Batched processing (e.g., 20 links per batch) to avoid timeouts on large sites
- Async HTTP HEAD requests via `wp_remote_head()`, fallback to GET if HEAD unsupported

### 3.6 Admin Layer
- Settings: WordPress Settings API, sanitized callbacks per field, capability check `manage_options`
- Dashboard: custom `WP_List_Table` subclass — native pagination, sorting, search, bulk actions, no custom JS grid needed
- Assets: enqueued only on plugin's own admin screens (`admin_enqueue_scripts` with hook suffix check) — never load plugin CSS/JS site-wide

### 3.7 Data Flow Diagram
```
Post Save → WP_HTML_Tag_Processor scan → Diff vs elm_links table → Write DB
                                                        │
                                                        ▼
Frontend render (the_content) ← Cached lookup ← elm_links table
                                                        │
                                                        ▼
Admin Dashboard (WP_List_Table) ← Cached aggregate query ← elm_links table
```

---

## 4. File/Folder Structure

```
external-link-manager/
├── external-link-manager.php        # Main plugin file, header, bootstrap
├── uninstall.php                    # Cleanup: drop table, delete options
├── readme.txt                       # WordPress.org listing (required format)
├── LICENSE.txt                      # GPLv2+
├── includes/
│   ├── class-elm-activator.php      # Activation: create table, defaults
│   ├── class-elm-deactivator.php
│   ├── class-elm-detector.php       # Detection layer (WP_HTML_Tag_Processor scan)
│   ├── class-elm-renderer.php       # Frontend attribute application
│   ├── class-elm-db.php             # Table CRUD/query wrapper
│   ├── class-elm-cache.php          # Cache get/set/invalidate helpers
│   ├── class-elm-settings.php       # Settings API registration + sanitization
│   ├── class-elm-exclusions.php     # Exclusion rule matching logic
│   ├── class-elm-cron.php           # v1.1: Action Scheduler hooks
│   └── class-elm-import-export.php
├── admin/
│   ├── class-elm-admin.php          # Menu registration, screen routing
│   ├── class-elm-list-table.php     # WP_List_Table subclass
│   ├── views/
│   │   ├── dashboard.php
│   │   ├── settings-general.php
│   │   ├── settings-exclusions.php
│   │   └── settings-advanced.php
│   └── assets/
│       ├── admin.css
│       └── admin.js
├── languages/                       # .pot file for translations
└── tests/
    ├── bootstrap.php
    └── test-detector.php            # PHPUnit + WP_UnitTestCase
```

---

## 5. Security Checklist (non-negotiable for WP.org approval)

- [ ] Nonce verification on every form submit and AJAX call (`wp_verify_nonce` / `check_admin_referer`)
- [ ] Capability checks (`current_user_can('manage_options')`) before any settings write or dashboard action
- [ ] All output escaped: `esc_html()`, `esc_url()`, `esc_attr()` — no raw `echo` of DB/user data
- [ ] All input sanitized: `sanitize_text_field()`, `esc_url_raw()`, `absint()` per field type
- [ ] SQL via `$wpdb->prepare()` exclusively — zero string-concatenated queries
- [ ] Direct file access guard on every PHP file: `if (!defined('ABSPATH')) exit;`
- [ ] No `eval()`, no `extract()`, no dynamic function calls on user input
- [ ] Settings sanitized via registered `sanitize_callback` in `register_setting()`

---

## 6. Performance Strategy

- Detection runs only on `save_post`, never on every frontend request
- Frontend uses one cached query per post render, not per-link queries
- Admin dashboard queries are indexed (domain, post_id) and cached
- Assets enqueued conditionally, only on plugin's own admin pages
- Batch processing for any bulk operation (health checks, bulk exclude) to avoid PHP timeout on large sites
- Target: <5ms added to `the_content` filter execution on a typical post with <20 links

---

## 7. WordPress.org Submission Requirements

- `readme.txt` in proper WP.org markup format (Tested up to, Stable tag, Requires PHP, short/long description, FAQ, changelog)
- GPLv2 or later license, declared in plugin header and LICENSE.txt
- No obfuscated code, no external tracking/analytics without explicit opt-in disclosure
- No "phone home" without consent
- Trademark-safe naming (avoid implying WordPress.org endorsement)
- Plugin review queue: expect 1–3 weeks; common rejection reasons to preempt — unescaped output, missing nonces, direct DB queries without `$wpdb->prepare`, missing uninstall cleanup

---

## 8. Testing Plan

- PHPUnit + `WP_UnitTestCase` for: detector logic, exclusion matching, attribute merge logic
- Manual QA matrix: WP 6.4/6.5/6.6/latest, PHP 7.4/8.0/8.2/8.3, popular themes (Twenty Twenty-Four, Astra, GeneratePress)
- Test with 500+ post site (performance/query load)
- Test plugin conflicts: Yoast SEO, Rank Math (both touch link rel attributes — need compatibility check to avoid double-processing)

---

## 9. Release Plan

| Version | Scope |
|---|---|
| 0.1 (internal) | Detection + storage layer working, no UI |
| 0.5 (alpha) | Settings + rendering layer, basic dashboard |
| 0.9 (beta) | Full dashboard, exclusions, import/export, security pass |
| 1.0 | WordPress.org submission |
| 1.1 | Broken link checker, CSV export, bulk actions |
| 2.0 | Redirect/cloaking, Gutenberg integration |

---

## 10. Immediate Next Steps

1. Scaffold plugin skeleton (main file, activator, folder structure)
2. Build `class-elm-detector.php` + DB table creation
3. Build `class-elm-renderer.php` (frontend output)
4. Build settings page + exclusions logic
5. Build dashboard `WP_List_Table`
6. Security + performance pass
7. Write `readme.txt`, submit to WordPress.org
