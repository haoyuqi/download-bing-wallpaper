<?php

namespace Haoyuqi\DownloadBingWallpaper\Tests\Unit;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Haoyuqi\DownloadBingWallpaper\BingWallpaper;
use Haoyuqi\DownloadBingWallpaper\Tests\TestCase;

class BingWallpaperTest extends TestCase
{
    public function test_download_returns_the_wallpaper_response_body(): void
    {
        $handler = new MockHandler([
            new Response(200, [], 'image-content'),
        ]);
        $client = new Client(['handler' => HandlerStack::create($handler)]);

        $wallpaper = new BingWallpaper($client);

        $this->assertSame('image-content', $wallpaper->download());
    }
}
