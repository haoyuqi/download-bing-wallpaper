<?php

declare(strict_types=1);

namespace Haoyuqi\DownloadBingWallpaper\Tests;

use Haoyuqi\DownloadBingWallpaper\ServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [ServiceProvider::class];
    }
}
