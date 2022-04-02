<?php

namespace Haoyuqi\DownloadBingWallpaper;

use Haoyuqi\DownloadBingWallpaper\Contracts\BingWallpaperInterface;
use GuzzleHttp\Client;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Carbon;

class BingWallpaper implements BingWallpaperInterface
{
    public function download()
    {
        $client = new Client();
        $response = $client->request('GET', 'https://bing.ioliu.cn/v1?w=1920&h=1200');

        return $response->getBody()->getContents();
    }

    public function save($content, $save_path, $file_name = null)
    {
        $filesystem = new Filesystem();

        $file_name = $file_name ?? Carbon::today()->toDateString() . 'png';

        if (!$filesystem->exists($save_path)) {
            $filesystem->makeDirectory($save_path);
        }

        return (bool)$filesystem->put($save_path . '/' . $file_name, $content);
    }
}