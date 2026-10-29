# Contributing

Open an issue before proposing a substantial behavior change. Keep pull requests focused, and include tests whenever command output, exit codes or Bing payload handling changes.

## Before a pull request

- Use PHP 8.3.36+, 8.4.27+ or 8.5.12+.
- Run `composer check`; it covers manifest validation, optimized autoloading, Larastan, Pint and PHPUnit.
- Tests must use faked HTTP responses. Never add a request to Bing.
- Keep `src/` at exactly 100% executable line coverage; CI fails on a single uncovered line, including in files the suite never loads.

## Compatibility

- The JSON envelope, its field names and order, the `status` values and the exit codes are the public contract. A breaking change requires a major version.
- `rawPayload` must preserve the upstream structure, including empty objects versus empty arrays.
- Wallpaper URLs stay absolute HTTPS URLs on the approved Bing hosts. Do not relax the redirect, size, timeout or host checks without a security review.
- The command must keep writing exactly one JSON document to stdout and nothing to stderr.

## Releases

Pushing a `vX.Y.Z` or `X.Y.Z` tag on a commit reachable from `master` runs the Release workflow. It validates tag ancestry, runs tests, PHPStan, Pint and Composer quality checks, and starts release notes generation only after `tests`, `phpstan`, `pint`, and `composer-quality` succeed before publishing the GitHub release. Merging to `master` never publishes, an existing release is never rewritten, and manual `workflow_dispatch` skips CI and generates the `release-notes` artifact for preview without publishing.

Release notes come from `.github/scripts/release-notes.py`, which collects the complete titles and descriptions of merged PRs associated with commits in the actual tag range, plus full messages of unmatched commits, without compression, truncation, or file-type filtering. One model request produces structured English and Simplified Chinese entries; local checks validate structure, nonempty bilingual text, safe Markdown, and coverage of every source ID, while the script renders source links and exact added, changed, or removed Composer runtime/platform constraints. Only DNS/connect failures, HTTP 429 rate limits, and HTTP 500/502/503/504 responses are retried (at most three attempts with 2- and 4-second backoff); exhausted quotas, authentication errors, timeouts, incomplete responses, and invalid generated content are not retried, and any failure stops publication without fallback or second AI review, logging only generic notices. Tags pushed with the default Actions token do not trigger another workflow.

These rules apply to human contributors and automated agents alike. Do not add live network calls, weaken the coverage gate or bypass the JSON contract.
