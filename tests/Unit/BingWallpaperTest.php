<?php

namespace Haoyuqi\DownloadBingWallpaper\Tests\Unit;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Haoyuqi\DownloadBingWallpaper\BingWallpaper;
use Haoyuqi\DownloadBingWallpaper\Tests\TestCase;
use Illuminate\Support\Facades\File;

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

    public function test_save_creates_the_directory_and_writes_the_named_file(): void
    {
        $path = storage_path('framework/testing/' . uniqid('bing-wallpaper-save-test-', true));

        $this->assertDirectoryDoesNotExist($path);

        try {
            $wallpaper = new BingWallpaper();

            $this->assertTrue($wallpaper->save('image-content', $path, 'wallpaper.png'));
            $this->assertDirectoryExists($path);
            $this->assertSame('image-content', File::get($path . '/wallpaper.png'));
        } finally {
            File::deleteDirectory($path);
        }
    }
}
