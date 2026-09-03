<?php

declare(strict_types=1);

namespace Haoyuqi\DownloadBingWallpaper\Support;

final class DateParser
{
    private function __construct() {}

    public static function isValid(string $date): bool
    {
        if (preg_match('/\A\d{4}-\d{2}-\d{2}\z/', $date) !== 1) {
            return false;
        }

        $year = (int) substr($date, 0, 4);
        $month = (int) substr($date, 5, 2);
        $day = (int) substr($date, 8, 2);

        return $year >= 1 && checkdate($month, $day, $year);
    }
}
