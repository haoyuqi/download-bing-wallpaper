# Download Bing Wallpaper

## Installation

`composer require haoyuqi/download-bing-wallpaper`

## Usage

```php
<?php

namespace App\Http\Controllers;

use Haoyuqi\DownloadBingWallpaper\Contracts\BingWallpaperInterface;
use Illuminate\Http\Request;

class IndexController extends Controller
{
    protected $bingWallpaper;

    public function __construct(BingWallpaperInterface $bingWallpaper)
    {
        $this->bingWallpaper = $bingWallpaper;
    }

    public function index()
    {
        $content = $this->bingWallpaper->download();

        $this->bingWallpaper->save($content, storage_path('bing-wallpaper'), 'bing-wallpaper.png');
    }
}

```