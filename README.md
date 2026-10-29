<h1 align="center">Download Bing Wallpaper</h1>

<p align="center">
  <a href="https://github.com/haoyuqi/download-bing-wallpaper/actions/workflows/tests.yml"><img alt="Tests" src="https://github.com/haoyuqi/download-bing-wallpaper/actions/workflows/tests.yml/badge.svg?branch=master"></a>
  <a href="https://github.com/haoyuqi/download-bing-wallpaper/actions/workflows/tests.yml?query=branch%3Amaster"><img alt="Coverage" src="https://img.shields.io/endpoint?url=https%3A%2F%2Fraw.githubusercontent.com%2Fhaoyuqi%2Fdownload-bing-wallpaper%2Fcoverage-badge%2Fcoverage.json"></a>
  <a href="https://www.php.net/supported-versions.php"><img alt="PHP 8.3.36+" src="https://img.shields.io/badge/PHP-8.3.36%2B-777BB4?logo=php&logoColor=white"></a>
  <a href="https://laravel.com/docs/13.x"><img alt="Laravel 13" src="https://img.shields.io/badge/Laravel-13-FF2D20?logo=laravel&logoColor=white"></a>
  <a href="LICENSE"><img alt="License: MIT" src="https://img.shields.io/badge/license-MIT-blue.svg"></a>
</p>

A Laravel 13 package that retrieves Bing wallpaper metadata for an exact date and writes one versioned JSON document to standard output. It never downloads or saves an image, and it never substitutes another date.

## Requirements

| Requirement | Notes |
| --- | --- |
| PHP 8.3.36+, 8.4.27+ or 8.5.12+ | These are the first releases fixing PHP2Shell (CVE-2026-109618 and CVE-2026-109619); the Composer constraint excludes earlier patches in each branch. |
| Laravel 13 | The package depends on the `illuminate/console`, `illuminate/http` and `illuminate/support` 13.x components. |
| `ext-json` | Used to encode the result document. |

Installing a package update does not upgrade PHP. The command is CLI-only and does not start sessions, but web applications that run on the same PHP runtime should set `session.upload_progress.enabled=0` until the next PHP bugfix release, which fixes the separate CVE-2026-115560.

## Installation

```sh
composer require haoyuqi/download-bing-wallpaper
```

## Usage

```sh
php artisan bing:wallpaper --date=2026-08-31 --no-ansi
```

The `--date` option is required and must be a real calendar date in `YYYY-MM-DD` format. The command requests Bing's fixed `en-US` source, searches its current eight-item response for that exact date, and writes exactly one JSON document followed by a newline. Standard error stays empty.

## Output

| Field | Type | Meaning |
| --- | --- | --- |
| `schemaVersion` | string | Contract version, currently `1.0`. |
| `status` | string | `found`, `not_found` or `error`. |
| `query` | object | `source` (`bing`), `market` (`en-US`) and the requested `date`, or `null` when the date was missing. |
| `data` | object or `null` | Normalized wallpaper metadata when `status` is `found`. |
| `rawPayload` | object or `null` | Parsed upstream response, unchanged, including empty objects. |
| `error` | object or `null` | Stable `code`, human-readable `message` and `retryable` flag. |
| `retrievedAt` | string | UTC timestamp in `YYYY-MM-DDTHH:MM:SSZ` format. |

The `data` object contains `sourceDate`, `sourceItemId`, `title`, `copyright`, `copyrightLink`, `imageUrl` and `downloadUrl`. `sourceItemId` is Bing's 32-character hash when valid, otherwise a deterministic `sha256:v1:...` value. Links are absolute HTTPS URLs restricted to Bing hosts.

```json
{
  "schemaVersion": "1.0",
  "status": "found",
  "query": {
    "source": "bing",
    "market": "en-US",
    "date": "2026-08-31"
  },
  "data": {
    "sourceDate": "2026-08-31",
    "sourceItemId": "abcdef0123456789abcdef0123456789",
    "title": "A title",
    "copyright": "A place — Photographer",
    "copyrightLink": "https://www.bing.com/search?q=example",
    "imageUrl": "https://www.bing.com/th?id=example_1920x1080.jpg",
    "downloadUrl": "https://www.bing.com/th?id=example_UHD.jpg"
  },
  "rawPayload": {
    "images": [
      {
        "startdate": "20260831",
        "url": "/th?id=example_1920x1080.jpg"
      }
    ]
  },
  "error": null,
  "retrievedAt": "2026-08-31T00:00:00Z"
}
```

`error.code` is stable; current values are `DATE_REQUIRED`, `INVALID_DATE`, `CONNECTION_FAILED`, `UPSTREAM_TIMEOUT`, `UPSTREAM_REDIRECT`, `UPSTREAM_RATE_LIMITED`, `UPSTREAM_REQUEST_REJECTED`, `UPSTREAM_UNAVAILABLE`, `UPSTREAM_RESPONSE_TOO_LARGE`, `INVALID_UPSTREAM_JSON`, `INVALID_UPSTREAM_SCHEMA` and `UNEXPECTED_ERROR`.

## Exit codes

| Code | Meaning |
| ---: | --- |
| 0 | `found` or `not_found`; the document is valid. |
| 2 | Missing or invalid `--date`; the provider was not called. |
| 1 | Upstream or internal failure; inspect `error`. |

## Development

```sh
composer check
php -d pcov.enabled=1 -d pcov.directory=src vendor/bin/phpunit \
    --coverage-clover build/coverage.xml --coverage-text
```

`composer check` runs manifest validation, optimized autoloading, Larastan, Pint and PHPUnit. Tests use faked HTTP responses and never contact Bing. CI requires exactly 100% executable line coverage across `src/`, including files no test loads.

Questions and reproducible bugs belong in [GitHub issues](https://github.com/haoyuqi/download-bing-wallpaper/issues); report vulnerabilities privately as described in [SECURITY.md](SECURITY.md).

## Release

Push a `vX.Y.Z` or `X.Y.Z` tag on a commit reachable from `master`. The release workflow validates the tag, runs tests and quality checks, generates bilingual notes, and publishes only when every check passes. A manual dispatch only previews the notes; an existing release is never rewritten.

## License

Project code is licensed under the [MIT license](LICENSE).
