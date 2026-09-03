<?php

declare(strict_types=1);

namespace Haoyuqi\DownloadBingWallpaper\Exceptions;

use InvalidArgumentException;
use RuntimeException;
use Throwable;

final class MetadataSourceException extends RuntimeException
{
    /** @var array<string, string> */
    private const MESSAGES = [
        'CONNECTION_FAILED' => 'Unable to connect to the Bing metadata service.',
        'UPSTREAM_TIMEOUT' => 'The Bing metadata request timed out.',
        'UPSTREAM_REDIRECT' => 'The Bing metadata service returned an unexpected redirect.',
        'UPSTREAM_RATE_LIMITED' => 'The Bing metadata service rate limit was reached.',
        'UPSTREAM_REQUEST_REJECTED' => 'The Bing metadata request was rejected.',
        'UPSTREAM_UNAVAILABLE' => 'The Bing metadata service is temporarily unavailable.',
        'UPSTREAM_RESPONSE_TOO_LARGE' => 'The Bing metadata response exceeded the allowed size.',
        'INVALID_UPSTREAM_JSON' => 'The Bing metadata service returned invalid JSON.',
        'INVALID_UPSTREAM_SCHEMA' => 'The Bing metadata response did not match the expected structure.',
    ];

    public function __construct(
        public readonly string $errorCode,
        public readonly bool $retryable,
        public readonly mixed $rawPayload = null,
        ?Throwable $previous = null,
    ) {
        $message = self::MESSAGES[$errorCode]
            ?? throw new InvalidArgumentException("Unknown metadata source error code [{$errorCode}].");

        parent::__construct($message, 0, $previous);
    }
}
