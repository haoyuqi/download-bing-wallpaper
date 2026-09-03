<?php

declare(strict_types=1);

namespace Haoyuqi\DownloadBingWallpaper\Tests\Fixtures;

use Haoyuqi\DownloadBingWallpaper\Contracts\WallpaperMetadataProvider;
use Haoyuqi\DownloadBingWallpaper\Data\MetadataResult;
use Throwable;

final class StubMetadataProvider implements WallpaperMetadataProvider
{
    public int $calls = 0;

    public function __construct(private readonly MetadataResult|Throwable $outcome) {}

    public function retrieve(string $date): MetadataResult
    {
        $this->calls++;

        if ($this->outcome instanceof Throwable) {
            throw $this->outcome;
        }

        return $this->outcome;
    }
}
