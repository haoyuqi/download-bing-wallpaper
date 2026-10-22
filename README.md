# Download Bing Wallpaper

[![Tests](https://github.com/haoyuqi/download-bing-wallpaper/actions/workflows/tests.yml/badge.svg?branch=master)](https://github.com/haoyuqi/download-bing-wallpaper/actions/workflows/tests.yml)
[![Coverage](https://img.shields.io/endpoint?url=https%3A%2F%2Fraw.githubusercontent.com%2Fhaoyuqi%2Fdownload-bing-wallpaper%2Fcoverage-badge%2Fcoverage.json)](https://github.com/haoyuqi/download-bing-wallpaper/actions/workflows/tests.yml?query=branch%3Amaster)

Laravel 13 package that retrieves Bing wallpaper metadata for an exact date and writes one versioned JSON document to standard output. It never downloads or saves an image.

## Requirements

- PHP 8.3+
- Laravel 13

Keep PHP updated to a patched release; see [runtime security requirements](SECURITY.md#php-runtime-security).

## Installation

```bash
composer require haoyuqi/download-bing-wallpaper
```

## Usage

```bash
php artisan bing:wallpaper --date=2026-08-31 --no-ansi
```

The command uses Bing's fixed `en-US` metadata source, searches its current eight-item response for the exact calendar date, and writes exactly one JSON document followed by a newline. No dates are substituted.

| Result | Exit code |
| --- | ---: |
| `found` or `not_found` | 0 |
| Invalid or missing date | 2 |
| Upstream or internal error | 1 |

The `--date` option is required and must be a real `YYYY-MM-DD` date. JSON fields are always ordered as `schemaVersion`, `status`, `query`, `data`, `rawPayload`, `error`, and `retrievedAt`. Technical details are represented in the JSON `error`; standard error remains empty.

## Migrating from v2

The `BingWallpaperInterface`, `download()`, and `save()` APIs were removed. Use the Artisan command and consume its JSON instead.

## Development

Run `composer check` for validation, optimized autoloading, analysis, style, and tests. CI uses fake HTTP responses and does not contact Bing.

This project was developed with AI assistance. All changes were reviewed and verified by the maintainer.

See [CONTRIBUTING.md](CONTRIBUTING.md), [SECURITY.md](SECURITY.md), and [SUPPORT.md](SUPPORT.md).

## Test coverage

CI requires **100% executable line coverage** across the complete `src/` tree,
including files that tests never load. The PHP 8.4 stable-dependency job measures
coverage with PCOV; PHP 8.3 (lowest and stable dependencies) and PHP 8.5 continue
to test compatibility. The gate compares raw covered and total line counts, so
rounding to `100.00%` cannot hide an uncovered line.

Each measured run publishes an English summary with test counts, exact coverage,
source file count, duration, and installed PHP, Laravel, PHPUnit and Composer
versions. The `package-coverage` artifact contains Clover, JUnit and measured JSON
reports. The README badge updates from successful `master` test runs through the
automatically maintained `coverage-badge` data branch; older commits and pull
requests cannot overwrite it.

To measure locally with PCOV installed:

```bash
php -d pcov.enabled=1 -d pcov.directory=src vendor/bin/phpunit \
    --coverage-clover build/coverage.xml --coverage-text
```
