<?php

declare(strict_types=1);

namespace Haoyuqi\DownloadBingWallpaper\Tests\Fixtures;

use Haoyuqi\DownloadBingWallpaper\Contracts\WallpaperMetadataProvider;
use Illuminate\Support\ServiceProvider;
use LogicException;

final class ProcessTestServiceProvider extends ServiceProvider
{
    /** @var list<string> */
    private const SCENARIOS = [
        'found',
        'not-found',
        'slow',
        'unexpected-error',
        'upstream-error',
    ];

    public function register(): void
    {
        $scenario = getenv('DBW_PROCESS_TEST_SCENARIO');

        if ($scenario === false) {
            return;
        }

        if (! in_array($scenario, self::SCENARIOS, true)) {
            throw new LogicException("Unknown process test scenario [{$scenario}].");
        }

        $this->app->singleton(
            WallpaperMetadataProvider::class,
            static fn (): ProcessScenarioProvider => new ProcessScenarioProvider($scenario),
        );
    }
}
