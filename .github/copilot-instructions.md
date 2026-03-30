# Link Smartly — Project Instructions

## Plugin Identity

- **Name**: Link Smartly
- **Slug**: `link-smartly`
- **Prefix**: `lsm_` (functions), `LSM_` (constants), `Lsm_` (classes), `.lsm-` (CSS)
- **Text Domain**: `link-smartly`
- **Version**: 1.0.0
- **Author**: Ahmed Essawy (minicad.io)
- **License**: GPL-2.0-or-later
- **Target**: WordPress.org plugin directory submission

## What It Does

Auto-internal-linking plugin. Users define keyword-to-URL mappings (e.g., "contact us" → /contact/). The plugin scans post content at render time and converts the first occurrence of each keyword into an internal `<a>` tag. DOMDocument-based, cache-friendly, SEO-focused.

## Architecture

```
link-smartly/                     ← Repo root
└── link-smartly/                 ← Plugin folder (WP installable)
    ├── link-smartly.php          ← Main bootstrap: constants, requires, hooks
    ├── readme.txt                ← WP.org formatted readme
    ├── uninstall.php             ← Cleanup: deletes options + transients
    ├── index.php                 ← Silence is golden
    ├── includes/
    │   ├── class-lsm-settings.php    ← Settings CRUD (wp_options: lsm_settings)
    │   ├── class-lsm-keywords.php    ← Keyword CRUD (wp_options: lsm_keywords) + transient cache
    │   ├── class-lsm-linker.php      ← Core engine: DOMDocument content processor
    │   ├── class-lsm-activator.php   ← Activation: default settings + sample data
    │   └── index.php
    ├── admin/
    │   ├── class-lsm-admin.php       ← Admin page (4 tabs) + CRUD form handlers
    │   ├── class-lsm-csv.php         ← CSV import/export
    │   ├── class-lsm-preview.php     ← Dry-run preview handler
    │   ├── views/
    │   │   ├── admin-page.php        ← Tab-navigation template (delegates to Lsm_Admin methods)
    │   │   └── index.php
    │   └── index.php
    ├── assets/
    │   ├── lsm-admin.css             ← Admin styles (lsm- prefixed, responsive)
    │   ├── lsm-admin.js              ← Vanilla JS: delete confirms + inline edit
    │   └── index.php
    └── languages/
        └── index.php
```

## Storage (No Custom Tables)

| Key | Type | Purpose |
|-----|------|---------|
| `lsm_settings` | wp_option | All plugin settings (enabled, max_links, post_types, etc.) |
| `lsm_keywords` | wp_option | Array of keyword-to-URL mappings with id, keyword, url, active |
| `lsm_active_keywords` | transient (24h) | Cached active keywords sorted longest-first |
| `lsm_preview_results` | transient (60s) | Temporary preview dry-run output |

## Tech Decisions

- **PHP 8.0+** with `declare(strict_types=1)` in every file
- **DOMDocument** for HTML parsing — never regex on HTML
- **wp_options API** only — no custom tables (designed for 50-200 keyword mappings)
- **Transient caching** on active keywords with 24h expiry
- **Filter priority 999** on `the_content` — runs after Rank Math (10-15) and other plugins
- **Vanilla JS (ES5)** — no jQuery dependency, IIFE-wrapped, uses `var`
- **Admin forms** submitted via `admin-post.php`, not AJAX

## Coding Standards (Enforced)

### PHP — WordPress Coding Standards (WPCS)
- Tabs for indentation (not spaces)
- Yoda conditions: `if ( 'value' === $var )`
- Spaces inside parentheses: `if ( $x )`, `array( 'key' => 'val' )`
- Named functions for hooks (no anonymous closures — they can't be removed)
- PHPDoc blocks on all functions (`@param`, `@return`, `@since`)
- Strict comparisons (`===`, `!==`) everywhere
- `wp_safe_redirect()` for all internal redirects
- Early returns to avoid deep nesting

### Security — Non-Negotiable
- `if ( ! defined( 'ABSPATH' ) ) { exit; }` top of every PHP file
- `check_admin_referer()` on every form handler
- `current_user_can( 'manage_options' )` on every privileged action
- `sanitize_text_field()`, `sanitize_key()`, `absint()`, `esc_url_raw()` on all input
- `wp_unslash()` before sanitizing `$_POST` / `$_GET`
- `esc_html()`, `esc_attr()`, `esc_url()` on all output
- Never `eval()`, `base64_decode()`, `@` error suppression

### JavaScript
- `var` (not `let`/`const`) for broadest compatibility
- `window.confirm()` / `window.alert()` (not bare `confirm()`)
- IIFE wrapper: `(function() { 'use strict'; ... })();`
- `wp_localize_script()` to pass PHP data to JS

### CSS
- All custom classes prefixed with `lsm-`
- Use WP admin classes where appropriate (`widefat`, `button`, `notice`)
- Responsive breakpoints: 782px and 480px
- No `!important`

### i18n
- Every user-facing string wrapped in `__()`, `_e()`, `esc_html__()`, etc.
- Text domain = `link-smartly` (must match slug)
- Translator comments on `sprintf` / `_n()` patterns

## Core Linking Rules

These rules are critical and must never be broken:

1. **Max links per post** — configurable (default 3), enforced per render
2. **Never self-link** — URL comparison prevents a page linking to itself
3. **Never double-link** — same URL never linked twice in one post
4. **Never link in protected elements** — headings (h1-h6), existing anchors, code, pre, script, style, textarea, button, select, option, img
5. **Case-insensitive matching** — `/\b(keyword)\b/iu` regex flag
6. **First occurrence only** — one link per keyword per post
7. **Longest keyword priority** — `get_active()` sorts descending by length
8. **Minimum content length** — posts below word threshold get no links
9. **Multibyte safe** — `PREG_OFFSET_CAPTURE` byte offsets converted to character offsets via `mb_strlen(substr(...))`

## Developer Hooks

### Filters
| Hook | Purpose |
|------|---------|
| `lsm_keyword_map` | Modify keyword map before processing |
| `lsm_max_links_per_post` | Override max links per post |
| `lsm_post_types` | Override allowed post types |
| `lsm_link_element` | Modify link DOM element before insertion |
| `lsm_active_keywords` | Modify active keywords after retrieval |
| `lsm_sample_keywords` | Modify sample data loaded on activation |
| `lsm_content_filter_priority` | Override the_content filter priority (default 999) |

### Actions
| Hook | Purpose |
|------|---------|
| `lsm_settings_saved` | Fires after settings are saved |
| `lsm_keywords_saved` | Fires after keywords are saved |
| `lsm_activated` | Fires after plugin activation |
| `lsm_after_link_insertion` | Fires after links are inserted into content |

## WP.org Submission Checklist

- [x] GPL-2.0-or-later license in plugin header
- [x] Folder name = slug = text domain
- [x] `readme.txt` with standard headers, FAQ (9 questions), screenshots (4), changelog
- [x] Stable tag matches Version constant
- [x] `uninstall.php` removes all plugin data
- [x] `index.php` in every directory (6 files)
- [x] No external calls, no tracking, no phoning home
- [x] No trialware or artificial limits
- [x] Assets enqueued only on plugin admin page
- [x] Settings link on Plugins list page
- [ ] Screenshots (PNG files not yet created — needed for WP.org listing)
- [ ] LICENSE file (intentionally skipped — header in main PHP file is sufficient for submission)
- [ ] `.pot` file generation for i18n (run `wp i18n make-pot` before submission)

## Build & Test

No build step required. To check PHP syntax:

```bash
# Lint all PHP files
find link-smartly/ -name "*.php" -exec php -l {} \;

# Run PHPCS (if installed)
composer install
vendor/bin/phpcs link-smartly/ --standard=WordPress

# Generate .pot translation file
wp i18n make-pot link-smartly/ link-smartly/languages/link-smartly.pot
```

## Important Warnings

- **No MiniCAD/SolidWorks/CAD references** in any demo data, placeholders, or examples — use generic terms ("contact us", "our services", "web design"). The author's SEO keyword strategy must not be exposed publicly.
- **Sample keywords** in `class-lsm-activator.php` are intentionally generic (contact us, our services, about us, pricing plans, get started).
- **Do not add jQuery** as a dependency. The plugin uses vanilla JS only.
- **Do not create custom database tables.** The wp_options API handles the expected data volume.
