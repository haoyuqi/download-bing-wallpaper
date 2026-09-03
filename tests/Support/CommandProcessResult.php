<?php

declare(strict_types=1);

namespace Haoyuqi\DownloadBingWallpaper\Tests\Support;

final readonly class CommandProcessResult
{
    public function __construct(
        public string $stdout,
        public string $stderr,
        public ?int $exitCode,
        public bool $timedOut,
    ) {}
}
