<?php

declare(strict_types=1);

namespace Haoyuqi\DownloadBingWallpaper\Tests\Unit\Bing;

use Haoyuqi\DownloadBingWallpaper\Bing\BingWallpaperProvider;
use Haoyuqi\DownloadBingWallpaper\Exceptions\MetadataSourceException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class BingWallpaperProviderTest extends TestCase
{
    private Factory $http;

    protected function setUp(): void
    {
        parent::setUp();

        $this->http = new Factory;
        $this->http->preventStrayRequests();
    }

    public function test_it_retrieves_and_normalizes_the_exact_requested_date(): void
    {
        $payload = $this->payload([
            $this->image('20260830'),
            $this->image('20260831', [
                'url' => 'HTTPS://WWW.BING.COM/th?id=OHR.Target_EN-US0000000000_1920x1080.jpg&rf=LaDigue_1920x1080.jpg&pid=hp',
                'urlbase' => '/th?id=OHR.Target_EN-US0000000000',
                'copyright' => 'A place — Photographer © Example',
                'copyrightlink' => 'https://WWW.BING.COM/search?q=example&form=hpcapt',
                'title' => '山与海',
                'hsh' => 'ABCDEF0123456789ABCDEF0123456789',
            ]),
        ]);
        $this->fake($payload);

        $result = (new BingWallpaperProvider($this->http))->retrieve('2026-08-31');

        self::assertSame('found', $result->status);
        self::assertSame([
            'sourceDate' => '2026-08-31',
            'sourceItemId' => 'abcdef0123456789abcdef0123456789',
            'title' => '山与海',
            'copyright' => 'A place — Photographer © Example',
            'copyrightLink' => 'https://www.bing.com/search?q=example&form=hpcapt',
            'imageUrl' => 'https://www.bing.com/th?id=OHR.Target_EN-US0000000000_1920x1080.jpg&rf=LaDigue_1920x1080.jpg&pid=hp',
            'downloadUrl' => 'https://www.bing.com/th?id=OHR.Target_EN-US0000000000_UHD.jpg',
        ], $result->data);
        $this->assertRawPayloadSame($payload, $result->rawPayload);

        $this->http->assertSentCount(1);
        $this->http->assertSent(static fn (Request $request): bool => $request->method() === 'GET'
            && $request->url() === 'https://global.bing.com/HPImageArchive.aspx?format=js&idx=0&n=8&mkt=en-US'
            && $request->hasHeader('Accept', 'application/json'));
    }

    public function test_it_returns_not_found_without_falling_back_to_another_request(): void
    {
        $payload = $this->payload([$this->image('20260830')]);
        $this->fake($payload);

        $result = (new BingWallpaperProvider($this->http))->retrieve('1999-12-31');

        self::assertSame('not_found', $result->status);
        self::assertNull($result->data);
        $this->assertRawPayloadSame($payload, $result->rawPayload);
        $this->http->assertSentCount(1);
    }

    public function test_it_preserves_empty_objects_in_the_raw_payload(): void
    {
        $body = '{"images":[],"metadata":{}}';
        $this->http->fake(static fn () => Factory::response($body));

        $result = (new BingWallpaperProvider($this->http))->retrieve('1900-01-01');

        self::assertSame('not_found', $result->status);
        self::assertSame($body, json_encode($result->rawPayload, JSON_THROW_ON_ERROR));
    }

    public function test_it_builds_a_versioned_deterministic_id_when_hsh_is_unusable(): void
    {
        $payload = $this->payload([$this->image('20260831', ['hsh' => 'not-a-hash'])]);
        $this->fake($payload);

        $result = (new BingWallpaperProvider($this->http))->retrieve('2026-08-31');
        $canonicalUrl = 'https://www.bing.com/th?id=OHR.Sample_EN-US0000000000_1920x1080.jpg&rf=LaDigue_1920x1080.jpg&pid=hp';
        $tuple = json_encode(
            ['bing', 'en-US', '2026-08-31', $canonicalUrl],
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
        );

        self::assertNotNull($result->data);
        self::assertSame('sha256:v1:'.hash('sha256', $tuple), $result->data['sourceItemId']);
    }

    public function test_it_preserves_missing_optional_fields_as_null(): void
    {
        $image = $this->image('20260831');
        unset($image['title'], $image['copyright'], $image['copyrightlink'], $image['urlbase'], $image['hsh']);
        $payload = $this->payload([$image]);
        $this->fake($payload);

        $result = (new BingWallpaperProvider($this->http))->retrieve('2026-08-31');

        self::assertNotNull($result->data);
        self::assertNull($result->data['title']);
        self::assertNull($result->data['copyright']);
        self::assertNull($result->data['copyrightLink']);
        self::assertNull($result->data['downloadUrl']);
    }

    public function test_it_rejects_invalid_json(): void
    {
        $this->http->fake(static fn () => Factory::response('{invalid'));

        $exception = $this->captureException(
            fn () => (new BingWallpaperProvider($this->http))->retrieve('2026-08-31'),
        );

        self::assertSame('INVALID_UPSTREAM_JSON', $exception->errorCode);
        self::assertTrue($exception->retryable);
        self::assertNull($exception->rawPayload);
    }

    public function test_it_preserves_parsed_payload_for_schema_errors(): void
    {
        $payload = $this->payload([$this->image('20260831', ['url' => 'https://example.com/wallpaper.jpg'])]);
        $this->fake($payload);

        $exception = $this->captureException(
            fn () => (new BingWallpaperProvider($this->http))->retrieve('2026-08-31'),
        );

        self::assertSame('INVALID_UPSTREAM_SCHEMA', $exception->errorCode);
        self::assertFalse($exception->retryable);
        $this->assertRawPayloadSame($payload, $exception->rawPayload);
    }

    #[DataProvider('invalidUrls')]
    public function test_it_rejects_urls_outside_the_fixed_bing_boundary(string $url): void
    {
        $payload = $this->payload([$this->image('20260831', ['url' => $url])]);
        $this->fake($payload);

        $exception = $this->captureException(
            fn () => (new BingWallpaperProvider($this->http))->retrieve('2026-08-31'),
        );

        self::assertSame('INVALID_UPSTREAM_SCHEMA', $exception->errorCode);
        $this->assertRawPayloadSame($payload, $exception->rawPayload);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidUrls(): iterable
    {
        yield 'protocol-relative URL' => ['//www.bing.com/image.jpg'];
        yield 'non-HTTPS URL' => ['http://www.bing.com/image.jpg'];
        yield 'unapproved host' => ['https://example.com/image.jpg'];
        yield 'credentials' => ['https://user@www.bing.com/image.jpg'];
        yield 'non-standard port' => ['https://www.bing.com:8443/image.jpg'];
        yield 'fragment' => ['https://www.bing.com/image.jpg#fragment'];
    }

    public function test_it_rejects_malformed_upstream_structure_before_normalization(): void
    {
        $payload = ['images' => ['not-an-item']];
        $this->fake($payload);

        $exception = $this->captureException(
            fn () => (new BingWallpaperProvider($this->http))->retrieve('2026-08-31'),
        );

        self::assertSame('INVALID_UPSTREAM_SCHEMA', $exception->errorCode);
        $this->assertRawPayloadSame($payload, $exception->rawPayload);
    }

    public function test_it_rejects_a_response_declared_larger_than_one_megabyte(): void
    {
        $this->http->fake(static fn () => Factory::response('{}', 200, ['Content-Length' => '1048577']));

        $exception = $this->captureException(
            fn () => (new BingWallpaperProvider($this->http))->retrieve('2026-08-31'),
        );

        self::assertSame('UPSTREAM_RESPONSE_TOO_LARGE', $exception->errorCode);
        self::assertFalse($exception->retryable);
    }

    public function test_it_stops_when_a_stream_without_content_length_exceeds_one_megabyte(): void
    {
        $this->http->fake(static fn () => Factory::response(str_repeat('x', 1_048_577)));

        $exception = $this->captureException(
            fn () => (new BingWallpaperProvider($this->http))->retrieve('2026-08-31'),
        );

        self::assertSame('UPSTREAM_RESPONSE_TOO_LARGE', $exception->errorCode);
    }

    #[DataProvider('httpErrors')]
    public function test_it_classifies_non_successful_http_responses(
        int $status,
        string $expectedCode,
        bool $retryable,
    ): void {
        $payload = ['error' => 'upstream'];
        $this->http->fake(static fn () => Factory::response($payload, $status));

        $exception = $this->captureException(
            fn () => (new BingWallpaperProvider($this->http))->retrieve('2026-08-31'),
        );

        self::assertSame($expectedCode, $exception->errorCode);
        self::assertSame($retryable, $exception->retryable);
        $this->assertRawPayloadSame($payload, $exception->rawPayload);
    }

    /**
     * @return iterable<string, array{int, string, bool}>
     */
    public static function httpErrors(): iterable
    {
        yield 'redirect' => [302, 'UPSTREAM_REDIRECT', false];
        yield 'rejected request' => [403, 'UPSTREAM_REQUEST_REJECTED', false];
        yield 'rate limited' => [429, 'UPSTREAM_RATE_LIMITED', true];
        yield 'unavailable' => [503, 'UPSTREAM_UNAVAILABLE', true];
    }

    #[DataProvider('connectionErrors')]
    public function test_it_classifies_connection_failures(string $message, string $expectedCode): void
    {
        $this->http->fake(Factory::failedConnection($message));

        $exception = $this->captureException(
            fn () => (new BingWallpaperProvider($this->http))->retrieve('2026-08-31'),
        );

        self::assertSame($expectedCode, $exception->errorCode);
        self::assertTrue($exception->retryable);
        self::assertNull($exception->rawPayload);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function connectionErrors(): iterable
    {
        yield 'timeout' => ['cURL error 28: Operation timed out', 'UPSTREAM_TIMEOUT'];
        yield 'dns failure' => ['cURL error 6: Could not resolve host', 'CONNECTION_FAILED'];
    }

    #[DataProvider('stableErrorMessages')]
    public function test_metadata_source_errors_have_stable_public_messages(
        string $code,
        bool $retryable,
        string $message,
    ): void {
        $exception = new MetadataSourceException($code, $retryable);

        self::assertSame($message, $exception->getMessage());
        self::assertSame($retryable, $exception->retryable);
    }

    /**
     * @return iterable<string, array{string, bool, string}>
     */
    public static function stableErrorMessages(): iterable
    {
        yield 'connection failure' => ['CONNECTION_FAILED', true, 'Unable to connect to the Bing metadata service.'];
        yield 'timeout' => ['UPSTREAM_TIMEOUT', true, 'The Bing metadata request timed out.'];
        yield 'redirect' => ['UPSTREAM_REDIRECT', false, 'The Bing metadata service returned an unexpected redirect.'];
        yield 'rate limit' => ['UPSTREAM_RATE_LIMITED', true, 'The Bing metadata service rate limit was reached.'];
        yield 'rejected request' => ['UPSTREAM_REQUEST_REJECTED', false, 'The Bing metadata request was rejected.'];
        yield 'unavailable' => ['UPSTREAM_UNAVAILABLE', true, 'The Bing metadata service is temporarily unavailable.'];
        yield 'response too large' => ['UPSTREAM_RESPONSE_TOO_LARGE', false, 'The Bing metadata response exceeded the allowed size.'];
        yield 'invalid JSON' => ['INVALID_UPSTREAM_JSON', true, 'The Bing metadata service returned invalid JSON.'];
        yield 'invalid schema' => ['INVALID_UPSTREAM_SCHEMA', false, 'The Bing metadata response did not match the expected structure.'];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function fake(array $payload): void
    {
        $this->http->fake(static fn () => Factory::response($payload));
    }

    /**
     * @param  list<array<string, mixed>>  $images
     * @return array{images: list<array<string, mixed>>}
     */
    private function payload(array $images): array
    {
        return ['images' => $images];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function image(string $startDate, array $overrides = []): array
    {
        return array_replace([
            'startdate' => $startDate,
            'url' => '/th?id=OHR.Sample_EN-US0000000000_1920x1080.jpg&rf=LaDigue_1920x1080.jpg&pid=hp',
            'urlbase' => '/th?id=OHR.Sample_EN-US0000000000',
            'copyright' => 'Example © Photographer',
            'copyrightlink' => '/search?q=example&form=hpcapt',
            'title' => 'Example title',
            'hsh' => '0123456789abcdef0123456789abcdef',
        ], $overrides);
    }

    /**
     * @param  callable(): mixed  $callback
     */
    private function captureException(callable $callback): MetadataSourceException
    {
        try {
            $callback();
        } catch (MetadataSourceException $exception) {
            return $exception;
        }

        self::fail('Expected a MetadataSourceException to be thrown.');
    }

    /**
     * @param  array<string, mixed>  $expected
     */
    private function assertRawPayloadSame(array $expected, mixed $actual): void
    {
        self::assertSame(
            json_encode($expected, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            json_encode($actual, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        );
    }
}
