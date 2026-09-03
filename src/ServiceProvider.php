<?php

declare(strict_types=1);

namespace Haoyuqi\DownloadBingWallpaper;

use Haoyuqi\DownloadBingWallpaper\Bing\BingWallpaperProvider;
use Haoyuqi\DownloadBingWallpaper\Console\BingWallpaperCommand;
use Haoyuqi\DownloadBingWallpaper\Contracts\WallpaperMetadataProvider;
use Illuminate\Support\ServiceProvider as LaravelServiceProvider;

final class ServiceProvider extends LaravelServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(WallpaperMetadataProvider::class, BingWallpaperProvider::class);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([BingWallpaperCommand::class]);
        }
    }
}
