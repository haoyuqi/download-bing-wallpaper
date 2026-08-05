<?php

namespace Haoyuqi\DownloadBingWallpaper;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use Haoyuqi\DownloadBingWallpaper\Contracts\BingWallpaperInterface;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Carbon;

class BingWallpaper implements BingWallpaperInterface
{
    /** @var ClientInterface */
    protected $client;

    public function __construct(?ClientInterface $client = null)
    {
        $this->client = $client ?? new Client;
    }

    public function download()
    {
        $response = $this->client->request('GET', 'https://www.bing.com/HPImageArchive.aspx?format=js&idx=0&n=1&mkt=zh-CN');

        $data = json_decode($response->getBody()->getContents(), true);
        $image_url = 'https://www.bing.com'.$data['images'][0]['url'];

        $response = $this->client->request('GET', $image_url);

        return $response->getBody()->getContents();
    }

    public function save($content, $save_path, $file_name = null)
    {
        $filesystem = new Filesystem;

        $file_name = $file_name ?? Carbon::today()->toDateString().'png';

        if (! $filesystem->exists($save_path)) {
            $filesystem->makeDirectory($save_path, 0755, true);
        }

        return (bool) $filesystem->put($save_path.'/'.$file_name, $content);
    }
}
