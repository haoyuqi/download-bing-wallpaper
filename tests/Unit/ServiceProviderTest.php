<?php

namespace Haoyuqi\DownloadBingWallpaper\Tests\Unit;

use Haoyuqi\DownloadBingWallpaper\BingWallpaper;
use Haoyuqi\DownloadBingWallpaper\Contracts\BingWallpaperInterface;
use Haoyuqi\DownloadBingWallpaper\Tests\TestCase;

class ServiceProviderTest extends TestCase
{
    public function test_resolves_the_bing_wallpaper_interface(): void
    {
        $wallpaper = $this->app->make(BingWallpaperInterface::class);

        $this->assertInstanceOf(BingWallpaper::class, $wallpaper);
    }
}
