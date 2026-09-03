<?php

declare(strict_types=1);

namespace Haoyuqi\DownloadBingWallpaper\Tests\Support;

use InvalidArgumentException;
use LogicException;

final class CommandProcess
{
    /** @var list<string> */
    private const SCENARIOS = [
        'found',
        'not-found',
        'slow',
        'unexpected-error',
        'upstream-error',
    ];

    private const POLL_INTERVAL_MICROSECONDS = 10_000;

    private function __construct() {}

    /**
     * @param  list<string>  $arguments
     */
    public static function run(
        string $scenario,
        array $arguments,
        float $timeoutSeconds = 3.0,
    ): CommandProcessResult {
        if (! in_array($scenario, self::SCENARIOS, true)) {
            throw new InvalidArgumentException("Unknown process test scenario [{$scenario}].");
        }

        if ($timeoutSeconds <= 0) {
            throw new InvalidArgumentException('The process timeout must be greater than zero.');
        }

        $projectRoot = dirname(__DIR__, 2);
        $testbench = $projectRoot.'/vendor/bin/testbench';

        if (! is_file($testbench)) {
            throw new LogicException('The Testbench executable is not installed.');
        }

        $environment = getenv();
        $environment = is_array($environment) ? $environment : [];
        $environment['DBW_PROCESS_TEST_SCENARIO'] = $scenario;

        $command = [PHP_BINARY, $testbench, 'bing:wallpaper', ...$arguments, '--no-ansi', '--no-interaction'];
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open(
            $command,
            $descriptors,
            $pipes,
            $projectRoot,
            $environment,
            ['bypass_shell' => true],
        );

        if (! is_resource($process)) {
            throw new LogicException('Unable to start the Testbench command process.');
        }

        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $stdout = '';
        $stderr = '';
        $exitCode = null;
        $timedOut = false;
        $deadline = microtime(true) + $timeoutSeconds;

        while (true) {
            $stdout .= self::readAvailable($pipes[1]);
            $stderr .= self::readAvailable($pipes[2]);
            $status = proc_get_status($process);

            if (! $status['running']) {
                $exitCode = $status['exitcode'];

                break;
            }

            if (microtime(true) >= $deadline) {
                $timedOut = true;
                proc_terminate($process);

                break;
            }

            usleep(self::POLL_INTERVAL_MICROSECONDS);
        }

        $stdout .= self::readAvailable($pipes[1]);
        $stderr .= self::readAvailable($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        $closedExitCode = proc_close($process);

        if (! $timedOut && ($exitCode === null || $exitCode < 0) && $closedExitCode >= 0) {
            $exitCode = $closedExitCode;
        }

        return new CommandProcessResult(
            $stdout,
            $stderr,
            $timedOut ? null : $exitCode,
            $timedOut,
        );
    }

    /** @param resource $stream */
    private static function readAvailable($stream): string
    {
        $contents = stream_get_contents($stream);

        return is_string($contents) ? $contents : '';
    }
}
