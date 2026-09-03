<?php

declare(strict_types=1);

namespace Haoyuqi\DownloadBingWallpaper\Bing;

use Haoyuqi\DownloadBingWallpaper\Contracts\WallpaperMetadataProvider;
use Haoyuqi\DownloadBingWallpaper\Data\MetadataResult;
use Haoyuqi\DownloadBingWallpaper\Exceptions\MetadataSourceException;
use Haoyuqi\DownloadBingWallpaper\Support\DateParser;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Response;
use JsonException;
use Throwable;

final class BingWallpaperProvider implements WallpaperMetadataProvider
{
    private const ENDPOINT = 'https://global.bing.com/HPImageArchive.aspx';

    private const BASE_URL = 'https://www.bing.com';

    private const MARKET = 'en-US';

    private const MAX_RESPONSE_BYTES = 1_048_576;

    private const READ_CHUNK_BYTES = 8_192;

    /** @var list<string> */
    private const ALLOWED_URL_HOSTS = ['www.bing.com', 'global.bing.com'];

    public function __construct(private readonly Factory $http) {}

    public function retrieve(string $date): MetadataResult
    {
        try {
            $response = $this->http
                ->createPendingRequest()
                ->withOptions([
                    'allow_redirects' => false,
                    'connect_timeout' => 5.0,
                    'http_errors' => false,
                    'read_timeout' => 10.0,
                    'stream' => true,
                    'timeout' => 15.0,
                ])
                ->acceptJson()
                ->get(self::ENDPOINT, [
                    'format' => 'js',
                    'idx' => 0,
                    'n' => 8,
                    'mkt' => self::MARKET,
                ]);
        } catch (ConnectionException $exception) {
            throw $this->transportException($exception);
        }

        try {
            $body = $this->readBoundedBody($response);
        } catch (MetadataSourceException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw $this->transportException($exception);
        }

        if (! $response->successful()) {
            throw $this->httpException($response->status(), $this->decodeIfValid($body));
        }

        $rawPayload = $this->decode($body, false);
        $payload = $this->decode($body, true);

        if (! is_array($payload)
            || ! array_key_exists('images', $payload)
            || ! is_array($payload['images'])
            || ! array_is_list($payload['images'])) {
            throw $this->schemaException($rawPayload);
        }

        foreach ($payload['images'] as $image) {
            if (! is_array($image)) {
                throw $this->schemaException($rawPayload);
            }

            /** @var array<string, mixed> $image */
            $sourceDate = $this->sourceDate($image, $rawPayload);

            if ($sourceDate !== $date) {
                continue;
            }

            return MetadataResult::found(
                $this->normalizeImage($image, $sourceDate, $rawPayload),
                $rawPayload,
            );
        }

        return MetadataResult::notFound($rawPayload);
    }

    private function readBoundedBody(Response $response): string
    {
        $contentLength = trim($response->header('Content-Length'));

        if ($contentLength !== ''
            && ctype_digit($contentLength)
            && (int) $contentLength > self::MAX_RESPONSE_BYTES) {
            throw new MetadataSourceException('UPSTREAM_RESPONSE_TOO_LARGE', false);
        }

        $stream = $response->toPsrResponse()->getBody();
        $body = '';

        while (! $stream->eof()) {
            $chunk = $stream->read(min(
                self::READ_CHUNK_BYTES,
                self::MAX_RESPONSE_BYTES + 1 - strlen($body),
            ));

            if ($chunk === '') {
                break;
            }

            $body .= $chunk;

            if (strlen($body) > self::MAX_RESPONSE_BYTES) {
                throw new MetadataSourceException('UPSTREAM_RESPONSE_TOO_LARGE', false);
            }
        }

        return $body;
    }

    private function decode(string $body, bool $associative): mixed
    {
        try {
            return json_decode($body, $associative, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new MetadataSourceException('INVALID_UPSTREAM_JSON', true, previous: $exception);
        }
    }

    private function decodeIfValid(string $body): mixed
    {
        try {
            return json_decode($body, false, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }
    }

    private function httpException(int $status, mixed $rawPayload): MetadataSourceException
    {
        return match (true) {
            $status >= 300 && $status < 400 => new MetadataSourceException('UPSTREAM_REDIRECT', false, $rawPayload),
            $status === 429 => new MetadataSourceException('UPSTREAM_RATE_LIMITED', true, $rawPayload),
            $status >= 500 => new MetadataSourceException('UPSTREAM_UNAVAILABLE', true, $rawPayload),
            default => new MetadataSourceException('UPSTREAM_REQUEST_REJECTED', false, $rawPayload),
        };
    }

    private function transportException(Throwable $exception): MetadataSourceException
    {
        $code = $this->isTimeout($exception) ? 'UPSTREAM_TIMEOUT' : 'CONNECTION_FAILED';

        return new MetadataSourceException($code, true, previous: $exception);
    }

    private function isTimeout(Throwable $exception): bool
    {
        $current = $exception;

        do {
            $message = strtolower($current->getMessage());

            if (str_contains($message, 'curl error 28')
                || str_contains($message, 'timed out')
                || str_contains($message, 'timeout')) {
                return true;
            }

            $current = $current->getPrevious();
        } while ($current instanceof Throwable);

        return false;
    }

    /**
     * @param  array<string, mixed>  $image
     */
    private function sourceDate(array $image, mixed $rawPayload): string
    {
        $startDate = $image['startdate'] ?? null;

        if (! is_string($startDate) || preg_match('/\A\d{8}\z/', $startDate) !== 1) {
            throw $this->schemaException($rawPayload);
        }

        $sourceDate = substr($startDate, 0, 4).'-'.substr($startDate, 4, 2).'-'.substr($startDate, 6, 2);

        if (! DateParser::isValid($sourceDate)) {
            throw $this->schemaException($rawPayload);
        }

        return $sourceDate;
    }

    /**
     * @param  array<string, mixed>  $image
     * @return array{
     *     sourceDate: string,
     *     sourceItemId: string,
     *     title: string|null,
     *     copyright: string|null,
     *     copyrightLink: string|null,
     *     imageUrl: string,
     *     downloadUrl: string|null
     * }
     */
    private function normalizeImage(array $image, string $sourceDate, mixed $rawPayload): array
    {
        $url = $image['url'] ?? null;

        if (! is_string($url) || $url === '') {
            throw $this->schemaException($rawPayload);
        }

        $imageUrl = $this->canonicalizeUrl($url, $rawPayload);
        $urlBase = $this->optionalString($image, 'urlbase', $rawPayload);
        $copyrightLink = $this->optionalString($image, 'copyrightlink', $rawPayload);

        return [
            'sourceDate' => $sourceDate,
            'sourceItemId' => $this->sourceItemId($image['hsh'] ?? null, $sourceDate, $imageUrl),
            'title' => $this->optionalString($image, 'title', $rawPayload),
            'copyright' => $this->optionalString($image, 'copyright', $rawPayload),
            'copyrightLink' => $copyrightLink === null
                ? null
                : $this->canonicalizeUrl($copyrightLink, $rawPayload),
            'imageUrl' => $imageUrl,
            'downloadUrl' => $urlBase === null
                ? null
                : $this->canonicalizeUrl($urlBase.'_UHD.jpg', $rawPayload),
        ];
    }

    /**
     * @param  array<string, mixed>  $image
     */
    private function optionalString(array $image, string $key, mixed $rawPayload): ?string
    {
        if (! array_key_exists($key, $image) || $image[$key] === null || $image[$key] === '') {
            return null;
        }

        if (! is_string($image[$key])) {
            throw $this->schemaException($rawPayload);
        }

        return $image[$key];
    }

    private function sourceItemId(mixed $hash, string $sourceDate, string $imageUrl): string
    {
        if (is_string($hash) && preg_match('/\A[0-9a-f]{32}\z/i', $hash) === 1) {
            return strtolower($hash);
        }

        $tuple = json_encode(
            ['bing', self::MARKET, $sourceDate, $imageUrl],
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
        );

        return 'sha256:v1:'.hash('sha256', $tuple);
    }

    private function canonicalizeUrl(string $url, mixed $rawPayload): string
    {
        if (preg_match('/[\x00-\x20\x7f]/', $url) === 1 || str_contains($url, '\\')) {
            throw $this->schemaException($rawPayload);
        }

        if (str_starts_with($url, '/')) {
            if (str_starts_with($url, '//')) {
                throw $this->schemaException($rawPayload);
            }

            $url = self::BASE_URL.$url;
        }

        $parts = parse_url($url);

        if (! is_array($parts)
            || strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
            || ! isset($parts['host'])
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['fragment'])
            || (isset($parts['port']) && $parts['port'] !== 443)) {
            throw $this->schemaException($rawPayload);
        }

        $host = strtolower($parts['host']);

        if (! in_array($host, self::ALLOWED_URL_HOSTS, true)) {
            throw $this->schemaException($rawPayload);
        }

        $canonicalUrl = 'https://'.$host.($parts['path'] ?? '');

        if (array_key_exists('query', $parts)) {
            $canonicalUrl .= '?'.$parts['query'];
        }

        return $canonicalUrl;
    }

    private function schemaException(mixed $rawPayload): MetadataSourceException
    {
        return new MetadataSourceException('INVALID_UPSTREAM_SCHEMA', false, $rawPayload);
    }
}
