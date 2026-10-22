# Security Policy

Version 3.x is the currently supported release line.

Report suspected vulnerabilities privately to hi.haoyuqi+github@gmail.com. Do not include secrets or exploit details in public issues.

## PHP runtime security

Consumers must keep their PHP runtime patched independently of this package.
For the October 21, 2026 security fixes, use PHP 8.3.36, 8.4.27, or 8.5.12
or a later patch in the same series. Installing a package update does not
upgrade PHP. See the [official PHP announcements](https://www.php.net/archive/2026.php).

The package retains its PHP `^8.3` compatibility declaration. CI requires the
patched runtime baselines above, requests the latest PHP patch, and checks the
actual installed platform. It tests lowest dependencies on PHP 8.3 and current
dependencies on PHP 8.3, 8.4, and 8.5, auditing each resolved dependency set.
Development requires PHPUnit 12.5.8 or later in the 12.x series to exclude
[CVE-2026-24765](https://github.com/advisories/GHSA-vvj3-c3rp-c85p).
