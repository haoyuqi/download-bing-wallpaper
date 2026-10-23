<?php

declare(strict_types=1);

use Composer\InstalledVersions;

require dirname(__DIR__, 2).'/vendor/autoload.php';

function counter(?SimpleXMLElement $metrics, string $name): int
{
    $value = (string) ($metrics[$name] ?? '');
    $integer = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);

    if (! ctype_digit($value) || $integer === false) {
        throw new RuntimeException('Missing or invalid '.$name.' counter');
    }

    return $integer;
}

try {
    $directory = $argv[1] ?? 'build';
    libxml_use_internal_errors(true);
    $coverage = simplexml_load_file($directory.'/coverage.xml', options: LIBXML_NONET);
    $junit = simplexml_load_file($directory.'/junit.xml', options: LIBXML_NONET);

    if ($coverage === false || $junit === false) {
        throw new RuntimeException('Missing or malformed coverage/JUnit report');
    }

    $files = $coverage->xpath('./project//file');
    $suite = $junit->testsuite[0] ?? null;
    if (! $files || $suite === null) {
        throw new RuntimeException('Missing source files or JUnit test suite');
    }

    $total = counter($coverage->project->metrics, 'statements');
    $covered = counter($coverage->project->metrics, 'coveredstatements');
    $fileTotal = $fileCovered = 0;
    foreach ($files as $file) {
        $statements = counter($file->metrics, 'statements');
        $hits = counter($file->metrics, 'coveredstatements');
        if ($hits > $statements) {
            throw new RuntimeException('Invalid source file line totals');
        }
        $fileTotal += $statements;
        $fileCovered += $hits;
    }

    if ($total <= 0 || $covered > $total || $fileTotal !== $total || $fileCovered !== $covered) {
        throw new RuntimeException('Invalid or inconsistent executable line totals');
    }

    $runtime = [
        'php' => PHP_VERSION,
        'laravel' => ltrim(InstalledVersions::getPrettyVersion('laravel/framework'), 'v'),
        'phpunit' => ltrim(InstalledVersions::getPrettyVersion('phpunit/phpunit'), 'v'),
        'composer' => getenv('COMPOSER_VERSION'),
    ];
    foreach ($runtime as $name => $version) {
        if (! is_string($version) || ! preg_match('/\A[0-9][0-9A-Za-z.+-]*\z/', $version)) {
            throw new RuntimeException('Missing or invalid installed '.$name.' version');
        }
    }

    $tests = counter($suite, 'tests');
    $failures = counter($suite, 'failures') + counter($suite, 'errors');
    $skipped = counter($suite, 'skipped');
    $percentage = number_format($covered / $total * 100, 2, '.', '').'%';
    $metrics = array_merge($runtime, [
        'covered_statements' => $covered, 'total_statements' => $total,
        'files' => count($files), 'coverage' => $percentage,
        'tests' => $tests, 'failures' => $failures, 'skipped' => $skipped,
    ]);
    file_put_contents($directory.'/runtime.json', json_encode($runtime, JSON_THROW_ON_ERROR).PHP_EOL);
    file_put_contents($directory.'/metrics.json', json_encode($metrics, JSON_THROW_ON_ERROR).PHP_EOL);

    $success = $covered === $total && $tests > 0 && $failures === 0 && $skipped === 0;
    $status = $success ? '● Passed' : '◉ Failed';
    $summary = "## Package test report\n\n**{$status}**\n\n| Metric | Result |\n| --- | --- |\n"
        ."| Tests | {$tests} |\n| Failures / errors | {$failures} |\n| Skipped | {$skipped} |\n"
        ."| Line coverage | {$percentage} ({$covered}/{$total}) |\n| Source files | ".count($files)." |\n";
    foreach (['php' => 'PHP', 'laravel' => 'Laravel', 'phpunit' => 'PHPUnit', 'composer' => 'Composer'] as $key => $label) {
        $summary .= '| '.$label.' | '.$runtime[$key]." |\n";
    }
    $summary .= '| Test duration | '.($suite['time'] ?? 'unknown')." s |\n\n"
        ."Scope: complete `src/` tree, including unexecuted files. Required line coverage: **100%**.\n";
    echo $summary;
    if ($path = getenv('GITHUB_STEP_SUMMARY')) {
        file_put_contents($path, $summary, FILE_APPEND);
    }

    if (! $success) {
        throw new RuntimeException('Require exactly 100% executable line coverage and tests without failures, errors, or skips');
    }

    file_put_contents($directory.'/badge.json', json_encode([
        'schemaVersion' => 1, 'label' => 'coverage', 'message' => $percentage, 'color' => 'blue',
    ], JSON_THROW_ON_ERROR));
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage().PHP_EOL);
    exit(1);
}
