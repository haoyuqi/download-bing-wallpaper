<?php

declare(strict_types=1);

namespace Haoyuqi\DownloadBingWallpaper\Tests\Unit;

use Haoyuqi\DownloadBingWallpaper\Bing\BingWallpaperProvider;
use Haoyuqi\DownloadBingWallpaper\Contracts\WallpaperMetadataProvider;
use Haoyuqi\DownloadBingWallpaper\Tests\TestCase;
use Illuminate\Contracts\Console\Kernel;

final class ServiceProviderTest extends TestCase
{
    public function test_it_registers_the_metadata_provider_as_a_singleton(): void
    {
        $first = $this->app->make(WallpaperMetadataProvider::class);
        $second = $this->app->make(WallpaperMetadataProvider::class);

        self::assertInstanceOf(BingWallpaperProvider::class, $first);
        self::assertSame($first, $second);
    }

    public function test_it_registers_the_json_artisan_command(): void
    {
        $commands = $this->app->make(Kernel::class)->all();

        self::assertArrayHasKey('bing:wallpaper', $commands);
    }

    public function test_the_legacy_download_and_file_api_has_been_removed(): void
    {
        self::assertFalse(class_exists('Haoyuqi\\DownloadBingWallpaper\\BingWallpaper'));
        self::assertFalse(interface_exists('Haoyuqi\\DownloadBingWallpaper\\Contracts\\BingWallpaperInterface'));
    }
}
