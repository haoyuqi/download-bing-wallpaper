<?php

declare(strict_types=1);

namespace Haoyuqi\DownloadBingWallpaper\Tests\Fixtures;

use Haoyuqi\DownloadBingWallpaper\Contracts\WallpaperMetadataProvider;
use Haoyuqi\DownloadBingWallpaper\Data\MetadataResult;
use Haoyuqi\DownloadBingWallpaper\Exceptions\MetadataSourceException;
use RuntimeException;

final readonly class ProcessScenarioProvider implements WallpaperMetadataProvider
{
    public function __construct(private string $scenario) {}

    public function retrieve(string $date): MetadataResult
    {
        return match ($this->scenario) {
            'found' => MetadataResult::found([
                'sourceDate' => $date,
                'sourceItemId' => '0123456789abcdef0123456789abcdef',
                'title' => 'Process fixture',
                'copyright' => 'Fixture © Example',
                'copyrightLink' => 'https://www.bing.com/search?q=fixture',
                'imageUrl' => 'https://www.bing.com/th?id=fixture',
                'downloadUrl' => 'https://www.bing.com/th?id=fixture_UHD.jpg',
            ], ['fixture' => 'found']),
            'not-found' => MetadataResult::notFound(['fixture' => 'not-found']),
            'slow' => $this->slowResult(),
            'upstream-error' => throw new MetadataSourceException(
                'UPSTREAM_UNAVAILABLE',
                true,
                ['fixture' => 'upstream-error'],
            ),
            'unexpected-error' => throw new RuntimeException('Private process fixture detail.'),
            default => throw new RuntimeException('Unsupported process fixture scenario.'),
        };
    }

    private function slowResult(): MetadataResult
    {
        usleep(5_000_000);

        return MetadataResult::notFound(['fixture' => 'slow']);
    }
}
