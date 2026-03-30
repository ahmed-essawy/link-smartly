# Contributing to Link Smartly

Thank you for your interest in contributing to Link Smartly! This document provides guidelines and instructions for contributing.

## Code of Conduct

By participating in this project, you agree to abide by our [Code of Conduct](CODE_OF_CONDUCT.md).

## How to Contribute

### Reporting Bugs

- Use the [Bug Report](https://github.com/minicad-io/link-smartly/issues/new?template=bug-report.yml) issue template.
- Search existing issues first to avoid duplicates.
- Include your WordPress version, PHP version, and plugin version.

### Suggesting Features

- Use the [Feature Request](https://github.com/minicad-io/link-smartly/issues/new?template=feature-request.yml) issue template.
- Explain the use case, not just the desired solution.

### Submitting Pull Requests

1. Fork the repository and create your branch from `develop`.
2. Make your changes following the coding standards below.
3. Test your changes thoroughly.
4. Submit a pull request to the `develop` branch.

## Development Setup

```bash
# Clone the repository
git clone https://github.com/minicad-io/link-smartly.git
cd link-smartly

# Lint PHP files
find link-smartly/ -name "*.php" -exec php -l {} \;

# Run PHPCS (optional, requires Composer)
composer require --dev wp-coding-standards/wpcs:"^3.0" dealerdirect/phpcodesniffer-composer-installer:"^1.0"
vendor/bin/phpcs link-smartly/ --standard=WordPress
```

## Coding Standards

### PHP

- Follow the [WordPress PHP Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/php/).
- Use `declare(strict_types=1)` in every PHP file.
- Use **tabs** for indentation (not spaces).
- Use Yoda conditions: `if ( 'value' === $var )`.
- Use spaces inside parentheses: `if ( $x )`, `array( 'key' => 'val' )`.
- Use named functions for hooks — no anonymous closures.
- Use strict comparisons (`===`, `!==`) everywhere.
- Add PHPDoc blocks to all functions.

### Security (Non-Negotiable)

- `if ( ! defined( 'ABSPATH' ) ) { exit; }` at the top of every PHP file.
- `check_admin_referer()` on every form handler.
- `current_user_can( 'manage_options' )` on every privileged action.
- Sanitize all input: `sanitize_text_field()`, `esc_url_raw()`, `absint()`.
- Escape all output: `esc_html()`, `esc_attr()`, `esc_url()`.

### JavaScript

- Use `var` (not `let`/`const`) for broadest compatibility.
- Wrap code in an IIFE: `(function() { 'use strict'; ... })();`
- Use `window.confirm()` / `window.alert()` instead of bare globals.
- No jQuery dependency.

### CSS

- Prefix all custom classes with `lsm-`.
- Use WordPress admin classes where appropriate (`widefat`, `button`, `notice`).
- Support responsive breakpoints: 782px and 480px.

### Internationalization

- Wrap all user-facing strings in `__()`, `_e()`, `esc_html__()`, etc.
- Text domain must be `link-smartly`.
- Add translator comments on `sprintf` patterns.

## Naming Conventions

| Type      | Prefix | Example                  |
| --------- | ------ | ------------------------ |
| Functions | `lsm_` | `lsm_init()`            |
| Constants | `LSM_` | `LSM_VERSION`            |
| Classes   | `Lsm_` | `Lsm_Settings`          |
| CSS       | `.lsm-`| `.lsm-keyword-table`    |
| Options   | `lsm_` | `lsm_settings`          |
| Hooks     | `lsm_` | `lsm_keyword_map`       |

## Branching Strategy

- `main` — stable releases only, tagged with version numbers.
- `develop` — active development, all PRs target this branch.
- `feature/*` — feature branches created from `develop`.
- `fix/*` — bug fix branches created from `develop`.

## Release Process

1. All changes merged to `develop` and tested.
2. Version bumped in `link-smartly.php` (header + `LSM_VERSION`), `readme.txt` (Stable tag), and `CHANGELOG.md`.
3. `develop` merged to `main`.
4. Tag created (e.g., `v1.0.1`).
5. GitHub Release published — triggers automatic deploy to WordPress.org.

## Questions?

Open a [Discussion](https://github.com/minicad-io/link-smartly/discussions) or reach out via the [WordPress.org support forum](https://wordpress.org/support/plugin/link-smartly/).
