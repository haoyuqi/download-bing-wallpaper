# Laravel Package Tests Design

## Goal

Add repeatable, offline tests for the package's wallpaper download and file-save behavior.

## Approach

Use PHPUnit through Orchestra Testbench, the conventional Laravel package test harness. `BingWallpaper` will accept an optional Guzzle `ClientInterface`; production construction continues to use a normal Guzzle client, while tests supply Guzzle's `MockHandler`.

## Coverage

- `download()` returns the body from the configured Bing request without using the network in tests.
- `save()` creates a missing directory, writes the requested content, and respects an explicit filename.
- The package service provider binds `BingWallpaperInterface` to `BingWallpaper` in a Laravel container.

## Boundaries

Tests do not call Bing or any external service. The public `download()` and `save()` method signatures remain unchanged.
