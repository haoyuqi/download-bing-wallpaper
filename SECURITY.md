# Security Policy

## Supported versions

Version 3.x is the currently supported release line. Security fixes target the latest release and the `master` branch; upgrade before reporting an issue that a newer release may already fix.

## Reporting a vulnerability

Report suspected vulnerabilities privately through GitHub's [security advisory](https://github.com/haoyuqi/download-bing-wallpaper/security/advisories/new). Do not open a public issue, pull request or discussion for a suspected vulnerability.

Include the affected version or commit, reproduction steps with synthetic data, expected and actual behavior, and the potential impact. Remove credentials, tokens and production data from attachments. This is a community-maintained project without a response deadline or bug bounty.

## Scope

The command's input validation, upstream response handling, URL boundary, JSON contract and release workflow are security-relevant. Treat changes to the approved Bing hosts, redirect handling, response size cap or timeout values as security changes.

## PHP runtime security

PHP 8.3.36, 8.4.27 and 8.5.12 are the first releases fixing PHP2Shell (CVE-2026-109618 and CVE-2026-109619). The Composer constraint requires those baselines or later in the same branch; installing a package update does not upgrade PHP.

CVE-2026-115560 is separate and is not fixed by those releases. It requires an unusual session configuration, so set `session.upload_progress.enabled=0` until the next PHP bugfix release. This package is a CLI command and does not start sessions, but its host application may serve web requests.

## Secrets

Credentials belong in the deployment platform's secret store, never in tracked files, logs or command arguments. The release workflow reads `RELEASE_NOTES_TOKEN`, `RELEASE_NOTES_BASE_URL` and `RELEASE_NOTES_MODEL` from repository secrets and never prints them or sends source files and diffs.
