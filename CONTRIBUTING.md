# Contributing

Please open an issue before proposing a substantial behavior change. Keep pull requests focused and include tests for changed command output or Bing payload handling.

Use PHP 8.3+ and run `composer check` before opening a pull request. Tests must use faked HTTP responses; do not add live Bing requests.

## Releases

Pushing a version tag on a commit reachable from `master` runs
[Release](.github/workflows/release.yml). Both `vX.Y.Z` and the historical
`X.Y.Z` tag form are accepted; keep using whichever matches the existing tag
you are following. The workflow validates the tag, runs
[Tests](.github/workflows/tests.yml), [PHPStan](.github/workflows/phpstan.yml),
[Code style](.github/workflows/pint.yml) and
[Composer Quality](.github/workflows/composer-quality.yml) on the tagged commit,
generates bilingual notes, and publishes the GitHub release only when every
check passes. Merging to `master` never publishes, and an existing release is
never rewritten.

Notes come from `.github/scripts/release-notes.py`, which sends non-merge commit
subjects and bodies, changed paths and line counts, and selected runtime,
dependency and workflow versions to an OpenAI-compatible service configured by
the `RELEASE_NOTES_TOKEN`, `RELEASE_NOTES_BASE_URL` and `RELEASE_NOTES_MODEL`
secrets. Temporary network failures, HTTP 429 and HTTP 500/502/503/504 get at
most three attempts per language. Generation failures fall back to commit
subjects; translation failures keep the English notes. Public logs contain only
generic notices.

To preview, run **Actions → Release → Run workflow** with an existing tag: it
produces a `release-notes` artifact and never publishes. Tags created with the
default Actions token do not trigger another workflow, so push tags with a human
account or a separately authorized token. [CHANGELOG.md](CHANGELOG.md) remains
the curated record for package consumers; generated notes are the GitHub release
announcement and do not replace it.
