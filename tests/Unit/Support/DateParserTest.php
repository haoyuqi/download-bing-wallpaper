<?php

declare(strict_types=1);

namespace Haoyuqi\DownloadBingWallpaper\Tests\Unit\Support;

use Haoyuqi\DownloadBingWallpaper\Support\DateParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DateParserTest extends TestCase
{
    #[DataProvider('validDates')]
    public function test_it_accepts_real_calendar_dates_in_the_exact_format(string $date): void
    {
        self::assertTrue(DateParser::isValid($date));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function validDates(): iterable
    {
        yield 'first four-digit year' => ['0001-01-01'];
        yield 'leap day' => ['2024-02-29'];
        yield 'future date' => ['9999-12-31'];
    }

    #[DataProvider('invalidDates')]
    public function test_it_rejects_invalid_or_non_canonical_dates(string $date): void
    {
        self::assertFalse(DateParser::isValid($date));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidDates(): iterable
    {
        yield 'empty' => [''];
        yield 'missing leading zero' => ['2024-2-29'];
        yield 'impossible day' => ['2024-02-30'];
        yield 'non leap year' => ['2023-02-29'];
        yield 'year zero' => ['0000-01-01'];
        yield 'five digit year' => ['10000-01-01'];
        yield 'timestamp' => ['2024-01-01T00:00:00Z'];
        yield 'leading whitespace' => [' 2024-01-01'];
        yield 'trailing whitespace' => ['2024-01-01 '];
    }
}
