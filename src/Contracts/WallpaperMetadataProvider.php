<?php

declare(strict_types=1);

namespace Haoyuqi\DownloadBingWallpaper\Contracts;

use Haoyuqi\DownloadBingWallpaper\Data\MetadataResult;

interface WallpaperMetadataProvider
{
    public function retrieve(string $date): MetadataResult;
}
