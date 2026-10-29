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

Pushing a `vX.Y.Z` or `X.Y.Z` tag on a commit reachable from `master` runs the Release workflow. It validates the tag, runs tests, PHPStan, Pint and Composer quality checks, generates bilingual notes, and publishes the GitHub release only when every check passes. Merging to `master` never publishes, an existing release is never rewritten, and a manual dispatch only produces a preview artifact.

Release notes come from `.github/scripts/release-notes.py`, which sends non-merge commit subjects and bodies, changed paths and line counts, and selected runtime, dependency and workflow versions to an OpenAI-compatible service configured by the `RELEASE_NOTES_TOKEN`, `RELEASE_NOTES_BASE_URL` and `RELEASE_NOTES_MODEL` secrets. Failures fall back to commit subjects, and public logs contain only generic notices. Tags pushed with the default Actions token do not trigger another workflow.

These rules apply to human contributors and automated agents alike. Do not add live network calls, weaken the coverage gate or bypass the JSON contract.
