# Changelog

All notable changes to Link Smartly will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.3.0] - 2025-07-14

### Added

- **Unified cache layer** (`Lsm_Cache`): Transparent caching with `wp_cache_*` (object cache) when available, transient fallback otherwise. All plugin components migrated to use this layer.
- **Keyword suggestion engine** (`Lsm_Suggestions`): Scans published content with DOMDocument to discover internal link opportunities. Returns suggestions sorted by frequency, deduplicated against existing keyword mappings.
- **Orphan content detector**: Identifies published pages/posts not targeted by any keyword mapping. Available via REST API and WP-CLI.
- **Dashboard widget** (`Lsm_Dashboard`): WordPress admin dashboard widget showing Total Keywords, Active Keywords, Links Inserted, and Broken URLs at a glance.
- **Link distribution report**: Visual bar chart in Analytics tab showing how links are distributed across target URLs. Highlights pages receiving zero links or disproportionately many.
- **Analytics CSV export**: Full keyword report download with stats (link count, posts linked, max uses) and URL health status (status, HTTP code, last checked).
- **Email digest** (`Lsm_Notifications`): Weekly HTML email to site admin with top 5 performers, broken URLs, zero-link keywords, and summary stats. Controlled via Settings → Automation.
- **WP-Cron health checks** (`Lsm_Health`): Automated weekly URL health scanning with configurable toggle in Settings → Automation.
- **Content processing cache**: Hash-based caching in `Lsm_Linker::process_content()` to skip DOMDocument processing on unchanged content. Invalidated automatically on keyword or settings changes.
- **Gutenberg sidebar panel**: Block editor `PluginDocumentSettingPanel` with auto-linking toggle and active keyword count. Classic meta box preserved as fallback.
- **REST API endpoints**: `GET /suggestions` (with offset pagination) and `GET /orphans` for programmatic access to content intelligence features.
- **WP-CLI commands**: `wp lsm suggest` (batch content scan with table/csv/json output) and `wp lsm orphans` (orphan page listing).
- **Expanded inline edit**: Quick-edit now supports group and active status fields alongside keyword and URL.
- **Automation settings section**: New Settings tab section with toggles for automated health checks and email digest.

### Changed

- All transient calls throughout the plugin replaced with `Lsm_Cache` for consistent caching behavior.
- Post meta (`_lsm_exclude`) registered via `register_post_meta()` for REST API compatibility with the block editor.
- `wp_localize_script` expanded with `textGroup` string for inline edit UI.

## [1.2.0] - 2025-07-13

### Added

- **AJAX keyword CRUD**: Add, edit, delete, and toggle keywords without page reloads. Existing form-based handlers preserved as no-JS fallback.
- **Server-side pagination**: Keywords table now paginates via AJAX with configurable per-page (25/50/100).
- **Sortable columns**: Click keyword table headers (Keyword, URL, Group, Status, Links) to sort ascending/descending.
- **Debounced search**: Search and filter keywords on the keywords tab with 300ms debounced AJAX requests.
- **URL health checker**: Batch-check all keyword target URLs for broken links (HTTP HEAD requests with rate limiting at 5 req/sec).
- **Health badges**: Visual status indicators (OK/Redirect/Error/Unknown) for keyword URLs in localized data.
- **Health summary**: Dashboard-style cards showing counts of OK, redirect, error, and unchecked URLs.
- **REST API health endpoints**: `GET /health` for summary, `POST /health` to run checks, `GET /health/broken` for broken URLs.
- **WP-CLI `check-urls` command**: Check health of all keyword URLs from the command line with progress bar and status filtering.
- **Undo on AJAX delete**: Deleted keywords stored in transient for 5-minute undo window (AJAX handler).
- **AJAX bulk actions**: Bulk activate, deactivate, and delete without page reloads.

### Changed

- Admin JavaScript completely rewritten with AJAX-powered CRUD, pagination, sorting, and search/filter — progressive enhancement over existing form markup.
- Keywords table headers are now clickable for column sorting with visual arrow indicators.
- Table footer added with pagination controls and per-page selector.
- Filter bar elements received CSS classes for JS targeting (`lsm-filter-group`, `lsm-filter-status`, `lsm-clear-filters`).
- `wp_localize_script` expanded with new strings for AJAX UI and health results.

## [1.1.0] - 2025-07-12

### Added

- **Keyword groups**: Organize keywords into named groups for easier management.
- **Synonym support**: Define comma-separated synonyms per keyword that link to the same URL.
- **Per-keyword link attributes**: Override nofollow and new-tab settings per keyword.
- **Per-keyword lifetime limit**: Set max_uses to cap total auto-link insertions per keyword.
- **Scheduled linking**: Set start and end dates to control when keywords are active.
- **Link analytics**: Track how many times each keyword is auto-linked with a new Analytics tab.
- **Bulk actions**: Select multiple keywords for batch activate, deactivate, or delete.
- **Search and filter**: Search keywords by text, filter by group or status.
- **Post exclusion meta box**: Disable auto-linking on individual posts via a sidebar checkbox.
- **External link detection**: URLs on different domains auto-get nofollow, noopener, and target=_blank.
- **Duplicate keyword detection**: Warning when adding a keyword that already exists.
- **Undo delete**: Restore accidentally deleted keywords within 5 minutes.
- **WP-CLI support**: Manage keywords from the command line (list, add, delete, stats, import, flush-cache, reset-stats).
- **REST API**: Full CRUD endpoints at `/wp-json/link-smartly/v1/` for headless and external integrations.
- **Extended CSV**: Import and export now include group, synonyms, nofollow, new_tab, max_uses, start_date, and end_date columns.

### Changed

- Admin page layout widened to 1100px to accommodate new columns.
- Keywords table now shows Group and Links columns.
- Add keyword form expanded with group, synonyms, advanced options, and schedule fields.
- Sample keywords on activation now include group assignments.

### Fixed

- Escaped `$count` in import notice output for Plugin Check compliance.
- Added phpcs ignore comments for `fclose()` on `php://output` stream.
- Sanitized `$_FILES` input in CSV import handler.
- Removed deprecated `load_plugin_textdomain()` call (handled by WordPress 4.6+ for hosted plugins).

## [1.0.0] - 2026-03-30

### Added

- Initial release.
- Keyword-to-URL mapping management with add, edit, delete, and toggle.
- DOMDocument-based content processor for safe HTML link insertion.
- Case-insensitive, first-occurrence, longest-keyword-priority matching.
- Protection against linking inside headings, existing anchors, code, pre, script, style, textarea, button, select, option, and img elements.
- Self-link prevention — pages never link to themselves.
- Double-link prevention — same URL never linked twice per post.
- Configurable maximum links per post (default 3).
- Configurable minimum content word count threshold.
- Post type selection (posts, pages, custom post types).
- CSV import and export for bulk keyword management.
- Preview/dry-run mode to test link insertion on any post.
- Settings page with 4 tabs: Keywords, Settings, Import/Export, Preview.
- Transient caching of active keywords (24h expiry).
- Developer hooks: 7 filters and 4 actions for customization.
- Full i18n support with `link-smartly` text domain.
- Uninstall cleanup removes all plugin data.
- Vanilla JS admin interface — no jQuery dependency.

[1.2.0]: https://github.com/ahmed-essawy/link-smartly/compare/v1.1.0...v1.2.0
[1.1.0]: https://github.com/ahmed-essawy/link-smartly/compare/v1.0.0...v1.1.0
[1.0.0]: https://github.com/ahmed-essawy/link-smartly/releases/tag/v1.0.0
