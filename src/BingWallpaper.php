<?php

namespace Haoyuqi\DownloadBingWallpaper;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\File;

class BingWallpaper
{
    public function download($save_path)
    {
        $client = new Client();

        $client->get('https://bing.ioliu.cn/v1?w=1920&h=1200', [
            'sink' => $save_path
        ]);

        return File::exists($save_path);
    }
}