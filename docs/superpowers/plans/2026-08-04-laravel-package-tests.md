# Laravel Package Tests Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add offline PHPUnit coverage for the Laravel package's download, save, and container-binding behavior.

**Architecture:** Orchestra Testbench boots a minimal Laravel application and discovers the package provider. Tests inject Guzzle's mock HTTP client into `BingWallpaper`; the production default remains a real Guzzle client. Filesystem assertions use Testbench's writable application storage path.

**Tech Stack:** PHP, PHPUnit, Orchestra Testbench, Guzzle MockHandler, Laravel 8/9.

---

### Task 1: Configure the Laravel package test harness

**Files:**
- Modify: `composer.json`
- Create: `phpunit.xml.dist`
- Create: `tests/TestCase.php`

- [ ] **Step 1: Add a failing provider-resolution test**

Create `tests/Unit/ServiceProviderTest.php` that resolves `BingWallpaperInterface` from Testbench's application and asserts it is a `BingWallpaper`.

- [ ] **Step 2: Run the test to verify the missing harness fails**

Run: `docker exec -u laradock laradock-workspace-1 sh -lc 'cd /var/www/download-bing-wallpaper-tests && composer test -- --filter=ServiceProviderTest'`

Expected: FAIL because PHPUnit and the test harness are not configured.

- [ ] **Step 3: Add PHPUnit and Orchestra Testbench configuration**

Add compatible PHPUnit and Testbench development dependencies, PSR-4 test autoloading, `composer test`, a PHPUnit configuration file, and a Testbench base test case registering `ServiceProvider`.

- [ ] **Step 4: Run the provider test to verify it passes**

Run: `docker exec -u laradock laradock-workspace-1 sh -lc 'cd /var/www/download-bing-wallpaper-tests && composer test -- --filter=ServiceProviderTest'`

Expected: PASS.

### Task 2: Add an offline wallpaper download test

**Files:**
- Modify: `src/BingWallpaper.php`
- Create: `tests/Unit/BingWallpaperTest.php`

- [ ] **Step 1: Write the failing mock-HTTP download test**

Create a Guzzle `MockHandler` returning `image-content`; construct `BingWallpaper` with the mock client and assert `download()` returns `image-content`.

- [ ] **Step 2: Run the test to verify it fails**

Run: `docker exec -u laradock laradock-workspace-1 sh -lc 'cd /var/www/download-bing-wallpaper-tests && composer test -- --filter=test_download_returns_the_wallpaper_response_body'`

Expected: FAIL because `BingWallpaper` has no injectable client.

- [ ] **Step 3: Add the minimal optional client dependency**

Store an optional `ClientInterface` in `BingWallpaper`; instantiate `Client` only when no client was supplied; use that client for the existing request.

- [ ] **Step 4: Run the download test to verify it passes**

Run: `docker exec -u laradock laradock-workspace-1 sh -lc 'cd /var/www/download-bing-wallpaper-tests && composer test -- --filter=test_download_returns_the_wallpaper_response_body'`

Expected: PASS without external HTTP traffic.

### Task 3: Add file-save behavior coverage

**Files:**
- Modify: `tests/Unit/BingWallpaperTest.php`

- [ ] **Step 1: Write the failing save test**

Use a missing path below `storage_path('framework/testing')`; call `save('image-content', $path, 'wallpaper.png')`; assert `true` and that the file contains `image-content`.

- [ ] **Step 2: Run the test to verify it fails before implementation changes**

Run: `docker exec -u laradock laradock-workspace-1 sh -lc 'cd /var/www/download-bing-wallpaper-tests && composer test -- --filter=test_save_creates_the_directory_and_writes_the_named_file'`

Expected: PASS if current production behavior already satisfies the newly specified contract; this establishes regression coverage without changing working code.

- [ ] **Step 3: Run all checks**

Run: `docker exec -u laradock laradock-workspace-1 sh -lc 'cd /var/www/download-bing-wallpaper-tests && composer test && vendor/bin/pint --test && vendor/bin/phpstan analyse'`

Expected: all tests and static checks pass.
