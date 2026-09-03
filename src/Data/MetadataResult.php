<?php

declare(strict_types=1);

namespace Haoyuqi\DownloadBingWallpaper\Data;

/**
 * @phpstan-type WallpaperData array{
 *     sourceDate: string,
 *     sourceItemId: string,
 *     title: string|null,
 *     copyright: string|null,
 *     copyrightLink: string|null,
 *     imageUrl: string,
 *     downloadUrl: string|null
 * }
 */
final readonly class MetadataResult
{
    /**
     * @param  'found'|'not_found'  $status
     * @param  WallpaperData|null  $data
     */
    private function __construct(
        public string $status,
        public ?array $data,
        public mixed $rawPayload,
    ) {}

    /**
     * @param  WallpaperData  $data
     */
    public static function found(array $data, mixed $rawPayload): self
    {
        return new self('found', $data, $rawPayload);
    }

    public static function notFound(mixed $rawPayload): self
    {
        return new self('not_found', null, $rawPayload);
    }
}
