# Changelog

All notable changes to Link Smartly will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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

[1.1.0]: https://github.com/minicad-io/link-smartly/compare/v1.0.0...v1.1.0
[1.0.0]: https://github.com/minicad-io/link-smartly/releases/tag/v1.0.0
