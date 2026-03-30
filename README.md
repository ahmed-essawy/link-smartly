# Link Smartly

Auto-internal-linking plugin for WordPress. Define keyword-to-URL mappings — Link Smartly converts the first occurrence of each keyword into an internal link at render time. DOMDocument-based, cache-friendly, SEO-focused.

[![PHP Lint](https://github.com/minicad-io/link-smartly/actions/workflows/php-lint.yml/badge.svg)](https://github.com/minicad-io/link-smartly/actions/workflows/php-lint.yml)
[![WPCS](https://github.com/minicad-io/link-smartly/actions/workflows/wpcs.yml/badge.svg)](https://github.com/minicad-io/link-smartly/actions/workflows/wpcs.yml)
[![WP Plugin Check](https://github.com/minicad-io/link-smartly/actions/workflows/wp-plugin-check.yml/badge.svg)](https://github.com/minicad-io/link-smartly/actions/workflows/wp-plugin-check.yml)

## Features

- **Keyword-to-URL mappings** — Unlimited keyword-to-URL pairs with an easy admin interface
- **Smart matching** — Case-insensitive, first-occurrence-only, longest-keyword-priority
- **Safe linking** — Never links inside headings, anchors, code, pre, script, style, textarea, button, select, or img elements. Never self-links. Never double-links
- **Configurable limits** — Max auto-links per post, minimum content word count
- **Post type support** — Posts, pages, and any custom post types
- **CSV import/export** — Bulk manage keyword mappings
- **Preview/dry-run** — Test which links would be inserted before going live
- **Cache-friendly** — Works with LiteSpeed Cache, WP Super Cache, and all full-page cache plugins
- **Developer hooks** — 7 filters + 4 actions for customization
- **Lightweight** — No jQuery, no external dependencies, minimal database usage

## Requirements

- WordPress 6.0+
- PHP 8.0+

## Installation

### From WordPress.org

1. Go to **Plugins → Add New** in your WordPress admin.
2. Search for **Link Smartly**.
3. Click **Install Now**, then **Activate**.

### Manual

1. Download the latest release ZIP from [Releases](https://github.com/minicad-io/link-smartly/releases).
2. Upload via **Plugins → Add New → Upload Plugin**.
3. Activate the plugin.

## Quick Start

1. Navigate to **Settings → Link Smartly → Keywords**.
2. Add a keyword (e.g., "contact us") and a target URL (e.g., `/contact/`).
3. Configure max links per post and post types in the **Settings** tab.
4. Visit any post — matching keywords are automatically linked.

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
| `lsm_content_filter_priority` | Override `the_content` filter priority (default 999) |

### Actions

| Hook | Purpose |
|------|---------|
| `lsm_settings_saved` | Fires after settings are saved |
| `lsm_keywords_saved` | Fires after keywords are saved |
| `lsm_activated` | Fires after plugin activation |
| `lsm_after_link_insertion` | Fires after links are inserted into content |

## Development

```bash
# Install dev dependencies
composer install

# Run PHPCS (uses phpcs.xml.dist config)
composer phpcs

# Auto-fix what can be fixed
composer phpcbf

# Lint PHP files
find link-smartly/ -name "*.php" -exec php -l {} \;

# Generate translation file
wp i18n make-pot link-smartly/ link-smartly/languages/link-smartly.pot
```

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md) for guidelines.

## Security

Found a vulnerability? See [SECURITY.md](.github/SECURITY.md) for responsible disclosure.

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for all changes.

## License

GPL-2.0-or-later. See the [plugin header](link-smartly/link-smartly.php) for details.
