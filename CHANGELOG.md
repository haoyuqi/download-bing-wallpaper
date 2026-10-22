# Changelog

## 3.0.1 — 2026-10-22

- Require PHPUnit 12.5.8 or later in development to exclude CVE-2026-24765.
- Require patched PHP runtimes in CI and add PHP 8.5 compatibility testing.
- Verify actual platform requirements and audit every tested dependency set.
- Document consumer PHP security updates; public APIs and installation compatibility are unchanged.

## 3.0.0 — 2026-09-03

- Breaking: replace image download and file saving with `bing:wallpaper --date=YYYY-MM-DD` JSON output.
- Require PHP 8.3+ and Laravel 13.
