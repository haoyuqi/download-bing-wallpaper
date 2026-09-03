<?php

declare(strict_types=1);

namespace Haoyuqi\DownloadBingWallpaper\Tests\Feature;

use Carbon\CarbonImmutable;
use Haoyuqi\DownloadBingWallpaper\Console\BingWallpaperCommand;
use Haoyuqi\DownloadBingWallpaper\Data\MetadataResult;
use Haoyuqi\DownloadBingWallpaper\Exceptions\MetadataSourceException;
use Haoyuqi\DownloadBingWallpaper\Tests\Fixtures\StubMetadataProvider;
use Haoyuqi\DownloadBingWallpaper\Tests\TestCase;
use RuntimeException;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

final class BingWallpaperCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-01T08:00:00+08:00'));
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_it_emits_the_found_envelope_as_exactly_one_json_document(): void
    {
        $data = [
            'sourceDate' => '2026-08-31',
            'sourceItemId' => '0123456789abcdef0123456789abcdef',
            'title' => '山与海',
            'copyright' => 'Example © Photographer',
            'copyrightLink' => 'https://www.bing.com/search?q=example',
            'imageUrl' => 'https://www.bing.com/th?id=example',
            'downloadUrl' => 'https://www.bing.com/th?id=example_UHD.jpg',
        ];
        $rawPayload = ['images' => [['startdate' => '20260831']]];
        $provider = new StubMetadataProvider(MetadataResult::found($data, $rawPayload));

        [$exitCode, $output] = $this->runCommand($provider, ['--date' => '2026-08-31']);

        self::assertSame(0, $exitCode);
        self::assertSame($this->encodeJson([
            'schemaVersion' => '1.0',
            'status' => 'found',
            'query' => [
                'source' => 'bing',
                'market' => 'en-US',
                'date' => '2026-08-31',
            ],
            'data' => $data,
            'rawPayload' => $rawPayload,
            'error' => null,
            'retrievedAt' => '2026-09-01T00:00:00Z',
        ]).PHP_EOL, $output);
        self::assertSame(1, $provider->calls);
    }

    public function test_it_emits_not_found_as_a_successful_result(): void
    {
        $rawPayload = ['images' => []];
        $provider = new StubMetadataProvider(MetadataResult::notFound($rawPayload));

        [$exitCode, $output] = $this->runCommand($provider, ['--date' => '1900-01-01']);
        $payload = $this->decode($output);

        self::assertSame(0, $exitCode);
        self::assertSame('not_found', $payload['status']);
        self::assertNull($payload['data']);
        self::assertSame($rawPayload, $payload['rawPayload']);
        self::assertNull($payload['error']);
    }

    public function test_it_preserves_empty_objects_when_emitting_the_raw_payload(): void
    {
        $rawPayload = json_decode('{"images":[],"metadata":{}}', false, 512, JSON_THROW_ON_ERROR);
        $provider = new StubMetadataProvider(MetadataResult::notFound($rawPayload));

        [$exitCode, $output] = $this->runCommand($provider, ['--date' => '1900-01-01']);

        self::assertSame(0, $exitCode);
        self::assertStringContainsString('"rawPayload":{"images":[],"metadata":{}}', $output);
    }

    public function test_it_rejects_a_missing_date_without_calling_the_provider(): void
    {
        $provider = new StubMetadataProvider(MetadataResult::notFound([]));

        [$exitCode, $output] = $this->runCommand($provider);

        self::assertSame(2, $exitCode);
        self::assertSame([
            'schemaVersion' => '1.0',
            'status' => 'error',
            'query' => [
                'source' => 'bing',
                'market' => 'en-US',
                'date' => null,
            ],
            'data' => null,
            'rawPayload' => null,
            'error' => [
                'code' => 'DATE_REQUIRED',
                'message' => 'The --date option is required.',
                'retryable' => false,
            ],
            'retrievedAt' => '2026-09-01T00:00:00Z',
        ], $this->decode($output));
        self::assertSame(0, $provider->calls);
    }

    public function test_it_preserves_an_explicit_empty_date_in_the_invalid_query(): void
    {
        $provider = new StubMetadataProvider(MetadataResult::notFound([]));

        [$exitCode, $output] = $this->runCommand($provider, ['--date' => '']);
        $payload = $this->decode($output);

        self::assertSame(2, $exitCode);
        self::assertSame('', $payload['query']['date']);
        self::assertSame('INVALID_DATE', $payload['error']['code']);
        self::assertSame(
            'The date must be a real calendar date in YYYY-MM-DD format.',
            $payload['error']['message'],
        );
        self::assertFalse($payload['error']['retryable']);
        self::assertSame(0, $provider->calls);
    }

    public function test_it_preserves_any_other_invalid_date_in_the_query(): void
    {
        $provider = new StubMetadataProvider(MetadataResult::notFound([]));

        [$exitCode, $output] = $this->runCommand($provider, ['--date' => '2026-02-30']);
        $payload = $this->decode($output);

        self::assertSame(2, $exitCode);
        self::assertSame('2026-02-30', $payload['query']['date']);
        self::assertSame('INVALID_DATE', $payload['error']['code']);
        self::assertSame(0, $provider->calls);
    }

    public function test_it_emits_a_stable_technical_error_without_leaking_upstream_details(): void
    {
        $rawPayload = ['error' => 'upstream detail retained as data'];
        $provider = new StubMetadataProvider(new MetadataSourceException(
            'UPSTREAM_UNAVAILABLE',
            true,
            $rawPayload,
            new RuntimeException('secret internal upstream message'),
        ));

        [$exitCode, $output] = $this->runCommand($provider, ['--date' => '2026-08-31']);
        $payload = $this->decode($output);

        self::assertSame(1, $exitCode);
        self::assertSame('error', $payload['status']);
        self::assertNull($payload['data']);
        self::assertSame($rawPayload, $payload['rawPayload']);
        self::assertSame([
            'code' => 'UPSTREAM_UNAVAILABLE',
            'message' => 'The Bing metadata service is temporarily unavailable.',
            'retryable' => true,
        ], $payload['error']);
        self::assertStringNotContainsString('secret internal', $output);
    }

    public function test_it_sanitizes_unexpected_failures(): void
    {
        $provider = new StubMetadataProvider(new RuntimeException('database password leaked here'));

        [$exitCode, $output] = $this->runCommand($provider, ['--date' => '2026-08-31']);
        $payload = $this->decode($output);

        self::assertSame(1, $exitCode);
        self::assertSame([
            'code' => 'UNEXPECTED_ERROR',
            'message' => 'An unexpected error occurred while retrieving Bing wallpaper metadata.',
            'retryable' => false,
        ], $payload['error']);
        self::assertNull($payload['rawPayload']);
        self::assertStringNotContainsString('database password', $output);
    }

    /**
     * @param  array<string, string>  $input
     * @return array{int, string}
     */
    private function runCommand(StubMetadataProvider $provider, array $input = []): array
    {
        $command = new BingWallpaperCommand($provider);
        $command->setLaravel($this->app);

        $output = new BufferedOutput;
        $exitCode = $command->run(new ArrayInput($input), $output);

        return [$exitCode, $output->fetch()];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function encodeJson(array $payload): string
    {
        return json_encode(
            $payload,
            JSON_UNESCAPED_SLASHES
                | JSON_UNESCAPED_UNICODE
                | JSON_INVALID_UTF8_SUBSTITUTE
                | JSON_THROW_ON_ERROR,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(string $output): array
    {
        self::assertSame(1, substr_count($output, PHP_EOL));

        $decoded = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);

        return $decoded;
    }
}
