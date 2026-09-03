<?php

declare(strict_types=1);

namespace Haoyuqi\DownloadBingWallpaper\Tests\Feature;

use Haoyuqi\DownloadBingWallpaper\Tests\Support\CommandProcess;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CommandProcessTest extends TestCase
{
    /**
     * @param  list<string>  $arguments
     */
    #[DataProvider('processScenarios')]
    public function test_the_real_process_contract_has_clean_stdout_and_stderr(
        string $scenario,
        array $arguments,
        int $expectedExitCode,
        string $expectedStatus,
        ?string $expectedErrorCode,
        ?string $expectedDate,
    ): void {
        $result = CommandProcess::run($scenario, $arguments);

        self::assertFalse($result->timedOut);
        self::assertSame($expectedExitCode, $result->exitCode);
        self::assertSame('', $result->stderr);
        self::assertSame(1, substr_count($result->stdout, "\n"));
        self::assertStringEndsWith("\n", $result->stdout);

        $payload = json_decode($result->stdout, true, 512, JSON_THROW_ON_ERROR);

        self::assertIsArray($payload);
        self::assertSame([
            'schemaVersion',
            'status',
            'query',
            'data',
            'rawPayload',
            'error',
            'retrievedAt',
        ], array_keys($payload));
        self::assertSame('1.0', $payload['schemaVersion']);
        self::assertSame($expectedStatus, $payload['status']);
        self::assertSame([
            'source' => 'bing',
            'market' => 'en-US',
            'date' => $expectedDate,
        ], $payload['query']);
        self::assertMatchesRegularExpression(
            '/\A\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z\z/',
            $payload['retrievedAt'],
        );

        if ($expectedErrorCode === null) {
            self::assertNull($payload['error']);
        } else {
            self::assertSame($expectedErrorCode, $payload['error']['code']);
        }
    }

    /**
     * @return iterable<string, array{string, list<string>, int, string, string|null, string|null}>
     */
    public static function processScenarios(): iterable
    {
        yield 'found' => ['found', ['--date=2026-08-31'], 0, 'found', null, '2026-08-31'];
        yield 'not found' => ['not-found', ['--date=1900-01-01'], 0, 'not_found', null, '1900-01-01'];
        yield 'missing date' => ['found', [], 2, 'error', 'DATE_REQUIRED', null];
        yield 'invalid date' => ['found', ['--date=2026-02-30'], 2, 'error', 'INVALID_DATE', '2026-02-30'];
        yield 'upstream error' => ['upstream-error', ['--date=2026-08-31'], 1, 'error', 'UPSTREAM_UNAVAILABLE', '2026-08-31'];
        yield 'unexpected error' => ['unexpected-error', ['--date=2026-08-31'], 1, 'error', 'UNEXPECTED_ERROR', '2026-08-31'];
    }

    public function test_the_process_harness_terminates_a_slow_child(): void
    {
        $result = CommandProcess::run('slow', ['--date=2026-08-31'], 0.25);

        self::assertTrue($result->timedOut);
        self::assertNull($result->exitCode);
    }
}
