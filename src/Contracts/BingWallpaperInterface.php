<?php

namespace Haoyuqi\DownloadBingWallpaper\Contracts;

interface BingWallpaperInterface
{

    /**
     * @return string
     */
    public function download();

    /**
     * @param string $content
     * @param string $save_path
     * @param string $file_name
     * @return boolean
     */
    public function save($content, $save_path, $file_name = null);
}
