<?php

declare(strict_types=1);

namespace Haoyuqi\DownloadBingWallpaper\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

final class CoverageReportTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/dbw-coverage-'.bin2hex(random_bytes(8));
        mkdir($this->directory);
        file_put_contents($this->directory.'/junit.xml', '<testsuites><testsuite tests="80" failures="0" errors="0" skipped="0" time="1.25"/></testsuites>');
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory.'/*') as $file) {
            unlink($file);
        }

        rmdir($this->directory);
    }

    public function test_it_reports_measured_coverage_and_installed_versions(): void
    {
        $this->writeCoverage('2', '2');
        $process = $this->report();

        self::assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        $metrics = json_decode(file_get_contents($this->directory.'/metrics.json'), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame(2, $metrics['covered_statements']);
        self::assertSame(2, $metrics['total_statements']);
        self::assertSame('100.00%', $metrics['coverage']);
        self::assertSame(PHP_VERSION, $metrics['php']);
        self::assertSame('2.10.3', $metrics['composer']);
        self::assertSame(80, $metrics['tests']);
        self::assertSame($process->getOutput(), file_get_contents($this->directory.'/summary.md'));
        self::assertStringContainsString('● Passed', $process->getOutput());
        self::assertStringContainsString('| Test duration | 1.25 s |', $process->getOutput());
        $badge = json_decode(file_get_contents($this->directory.'/badge.json'), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('100.00%', $badge['message']);
    }

    #[DataProvider('invalidCoverage')]
    public function test_it_rejects_incomplete_or_invalid_measurements(string $total, string $covered, ?string $fileTotal = null): void
    {
        $this->writeCoverage($total, $covered, $fileTotal);
        $process = $this->report();

        self::assertSame(1, $process->getExitCode());
        self::assertFileDoesNotExist($this->directory.'/badge.json');
    }

    public static function invalidCoverage(): iterable
    {
        yield 'uncovered line' => ['2', '1'];
        yield 'rounded 100 percent' => ['1000000', '999999'];
        yield 'empty coverage' => ['0', '0'];
        yield 'impossible coverage' => ['2', '3'];
        yield 'file/project mismatch' => ['2', '2', '3'];
        yield 'missing counter' => ['2', ''];
        yield 'negative counter' => ['2', '-1'];
        yield 'decimal counter' => ['2', '2.0'];
        yield 'non-ASCII counter' => ['2', '٢'];
        yield 'overflow counter' => ['999999999999999999999999', '999999999999999999999999'];
    }

    #[DataProvider('unsuccessfulTests')]
    public function test_it_rejects_unsuccessful_test_runs(string $suite): void
    {
        $this->writeCoverage('2', '2');
        file_put_contents($this->directory.'/junit.xml', '<testsuites>'.$suite.'</testsuites>');
        $process = $this->report();

        self::assertSame(1, $process->getExitCode());
        self::assertFileDoesNotExist($this->directory.'/badge.json');
    }

    public static function unsuccessfulTests(): iterable
    {
        yield 'failures' => ['<testsuite tests="1" failures="1" errors="0" skipped="0"/>'];
        yield 'errors' => ['<testsuite tests="1" failures="0" errors="1" skipped="0"/>'];
        yield 'skips' => ['<testsuite tests="1" failures="0" errors="0" skipped="1"/>'];
        yield 'empty suite' => ['<testsuite tests="0" failures="0" errors="0" skipped="0"/>'];
        yield 'missing suite' => [''];
    }

    public function test_it_fails_closed_when_a_report_is_missing_or_malformed(): void
    {
        self::assertSame(1, $this->report()->getExitCode());
        file_put_contents($this->directory.'/coverage.xml', '<invalid');
        self::assertSame(1, $this->report()->getExitCode());
        self::assertFileDoesNotExist($this->directory.'/badge.json');
    }

    private function writeCoverage(string $total, string $covered, ?string $fileTotal = null): void
    {
        $fileTotal ??= $total;
        file_put_contents($this->directory.'/coverage.xml', '<coverage><project>'
            .'<file name="src/Example.php"><metrics statements="'.$fileTotal.'" coveredstatements="'.$covered.'"/></file>'
            .'<metrics statements="'.$total.'" coveredstatements="'.$covered.'"/></project></coverage>');
    }

    private function report(): Process
    {
        $process = new Process([PHP_BINARY, dirname(__DIR__, 2).'/.github/scripts/coverage-report.php', $this->directory], env: [
            'COMPOSER_VERSION' => '2.10.3',
            'GITHUB_STEP_SUMMARY' => $this->directory.'/summary.md',
        ]);
        $process->run();

        return $process;
    }
}
