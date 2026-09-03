# Download Bing Wallpaper

> v3 is unreleased. It is a breaking replacement for the former image-download and file-save API.

Laravel 13 package that retrieves Bing wallpaper metadata for an exact date and writes one versioned JSON document to standard output. It never downloads or saves an image.

## Requirements

- PHP 8.3+
- Laravel 13

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

See [CONTRIBUTING.md](CONTRIBUTING.md), [SECURITY.md](SECURITY.md), and [SUPPORT.md](SUPPORT.md).
