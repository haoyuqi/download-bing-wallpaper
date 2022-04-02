<?php

namespace Haoyuqi\DownloadBingWallpaper;

use Haoyuqi\DownloadBingWallpaper\Contracts\BingWallpaperInterface;

class ServiceProvider extends \Illuminate\Support\ServiceProvider
{
    public function register()
    {
        parent::register();

        $this->app->singleton(BingWallpaperInterface::class, BingWallpaper::class);
    }
}
