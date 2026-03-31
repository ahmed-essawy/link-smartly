=== Link Smartly ===
Contributors: ahm.elessawy
Tags: internal links, seo, auto link, keyword linking, interlinking
Requires at least: 5.8
Tested up to: 6.9.4
Stable tag: 1.4.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Automatically insert internal links into your content based on keyword-to-URL mappings. Lightweight, cache-friendly, and SEO-focused.

== Description ==

**Link Smartly** automatically inserts internal links into your WordPress posts and pages based on keyword-to-URL mappings you define. It's the easiest way to build a strong internal link structure that improves your SEO rankings.

= Why Internal Linking Matters =

Internal links help search engines understand your site structure, distribute page authority, and help visitors discover related content. Manually managing internal links across dozens (or hundreds) of posts is tedious and error-prone. Link Smartly automates this process.

= How It Works =

1. Define keyword-to-URL mappings (e.g., "contact us" → /contact/)
2. Link Smartly scans your post content for these keywords
3. The first occurrence of each keyword is automatically converted into an internal link
4. Links are inserted at render time and cached by your page cache plugin

= Key Features =

* **Keyword-to-URL mappings** — Define unlimited keyword-to-URL pairs with an easy admin interface.
* **Smart matching** — Case-insensitive, first-occurrence-only, longest-keyword-priority matching.
* **Safe linking** — Never links inside headings, existing anchors, code blocks, or image alt text. Never links a page to itself. Never double-links.
* **Configurable limits** — Set maximum auto-links per post and minimum content word count.
* **Post type support** — Works on posts, pages, and any custom post types.
* **CSV import/export** — Bulk manage keyword mappings via CSV files.
* **Preview/dry-run** — Test which links would be inserted for any post before going live.
* **Cache-friendly** — Works with LiteSpeed Cache, WP Super Cache, and all full-page cache plugins.
* **Developer-friendly** — Comprehensive filter and action hooks for customization.
* **Lightweight** — No external dependencies, no jQuery, minimal database usage.

= Perfect For =

* Content marketers building topic clusters
* SEO professionals managing internal link structure
* Blog owners with 50+ posts who need automated cross-linking
* Any WordPress site looking to improve internal link equity distribution

== Installation ==

1. Upload the `link-smartly` folder to `/wp-content/plugins/`.
2. Activate the plugin through the "Plugins" menu in WordPress.
3. Go to **Settings → Link Smartly** to configure your keyword mappings.
4. Add keyword-URL pairs and enable auto-linking.

= Quick Start =

1. Navigate to **Settings → Link Smartly → Keywords**.
2. Add a keyword phrase (e.g., "contact us") and a target URL (e.g., /contact/).
3. Switch to the **Settings** tab to configure max links per post and post types.
4. Visit any post on your site — matching keywords will be automatically linked!

== Frequently Asked Questions ==

= Will this slow down my site? =

No. Link Smartly hooks into the `the_content` filter, which means the linking happens once when the page is rendered. If you use a full-page cache plugin (LiteSpeed Cache, WP Super Cache, etc.), the linked content is cached and served directly on subsequent visits with zero processing overhead.

= Does it work with my SEO plugin? =

Yes. Link Smartly is tested with Rank Math and Yoast SEO. It uses a very late filter priority (999) to run after other content-processing plugins.

= Can I control which post types get auto-links? =

Yes. In the Settings tab, you can select which post types should have auto-linking applied. This includes posts, pages, and any registered custom post types.

= What if a keyword appears multiple times in a post? =

Only the first occurrence is linked. This prevents over-linking and keeps content natural.

= What if two keywords overlap? =

The longer (more specific) keyword always takes priority. For example, if you have both "web design" and "web design services," the longer phrase is matched first.

= Will it link a page to itself? =

No. Link Smartly automatically detects the current page URL and skips any keyword mapping that would create a self-referencing link.

= Can I see which links will be added before enabling? =

Yes! Use the Preview tab to enter any Post ID and see exactly which auto-links would be inserted — without modifying any content.

= How do I import/export keywords? =

Go to the Import/Export tab. You can export all mappings as CSV, or import a CSV file with columns: keyword, url, active (1 or 0).

= Is this plugin free? =

Yes, completely free and open source under GPLv2. No premium tier, no upsells, no artificial limits.

== Screenshots ==

1. Keywords management — Add, edit, and toggle keyword-to-URL mappings.
2. Settings — Configure max links, post types, link attributes, and more.
3. Import/Export — Bulk manage keywords via CSV files.
4. Preview — Dry-run link preview for any post.

== Changelog ==

= 1.3.0 =
* Added unified cache layer with object cache support and transient fallback.
* Added keyword suggestion engine that scans existing content for link opportunities.
* Added orphan content detector to find pages not targeted by any keyword.
* Added WordPress dashboard widget with quick stats overview.
* Added link distribution report showing how links are spread across target URLs.
* Added analytics CSV export with keyword stats, health status, and link counts.
* Added weekly email digest with top performers, broken URLs, and zero-link keywords.
* Added WP-Cron automated health checks with configurable schedule.
* Added content processing cache for faster repeat renders.
* Added Gutenberg sidebar panel with auto-linking toggle and keyword count.
* Added REST API endpoints for suggestions and orphan pages.
* Added WP-CLI commands for keyword suggestions and orphan detection.
* Expanded inline edit to support group and active status fields.
* Added Automation settings section for cron health checks and email digest.

= 1.2.0 =
* Added AJAX keyword CRUD without page reloads.
* Added server-side pagination for keywords table.
* Added sortable columns with visual sort indicators.
* Added debounced search and filter for keywords.
* Added URL health checker with batch HTTP HEAD requests.
* Added health badges and summary cards.
* Added REST API health endpoints.
* Added WP-CLI check-urls command.
* Added undo on AJAX delete with 5-minute window.
* Added AJAX bulk actions.

= 1.1.0 =
* Added keyword groups for organization.
* Added synonym/alias support per keyword.
* Added per-keyword nofollow and new-tab overrides.
* Added per-keyword lifetime link limit (max uses).
* Added scheduled linking with start and end dates.
* Added link analytics dashboard with per-keyword counts.
* Added bulk actions (activate, deactivate, delete).
* Added search and filter for keywords.
* Added post-level exclusion meta box.
* Added external link auto-detection with nofollow and noopener.
* Added duplicate keyword detection.
* Added undo for deleted keywords.
* Added WP-CLI support.
* Added REST API endpoints.
* Extended CSV import/export with new fields.

= 1.0.0 =
* Initial release.
* Keyword-to-URL mapping management with add, edit, delete, and toggle.
* DOMDocument-based content processing for safe and reliable linking.
* Case-insensitive matching with longest-keyword priority.
* Configurable max links per post and minimum content word count.
* Post type selection for auto-linking targets.
* CSV import and export for bulk keyword management.
* Preview/dry-run feature for testing link insertion.
* Full developer hook support (filters and actions).
* Sample keyword data loaded on activation.

== Upgrade Notice ==

= 1.3.0 =
Content intelligence: keyword suggestions, orphan detection, dashboard widget, email digest, Gutenberg sidebar, link distribution report, and unified cache layer.

= 1.1.0 =
Major feature release: keyword groups, synonyms, analytics, bulk actions, search/filter, post exclusion, WP-CLI, REST API, and more.

= 1.0.0 =
Initial release. Install to start building your internal link structure automatically.
