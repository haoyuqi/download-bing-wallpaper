<?php

declare(strict_types=1);

namespace Haoyuqi\DownloadBingWallpaper\Console;

use Carbon\CarbonImmutable;
use Haoyuqi\DownloadBingWallpaper\Contracts\WallpaperMetadataProvider;
use Haoyuqi\DownloadBingWallpaper\Exceptions\MetadataSourceException;
use Haoyuqi\DownloadBingWallpaper\Support\DateParser;
use Illuminate\Console\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

final class BingWallpaperCommand extends Command
{
    /** @var string */
    protected $signature = 'bing:wallpaper {--date= : Date to retrieve in YYYY-MM-DD format}';

    /** @var string */
    protected $description = 'Retrieve Bing wallpaper metadata for a date as versioned JSON';

    public function __construct(private readonly WallpaperMetadataProvider $provider)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $date = $this->option('date');

        if ($date === null) {
            return $this->emitError(
                null,
                'DATE_REQUIRED',
                'The --date option is required.',
                false,
                null,
                self::INVALID,
            );
        }

        if (! is_string($date) || ! DateParser::isValid($date)) {
            return $this->emitError(
                is_string($date) ? $date : null,
                'INVALID_DATE',
                'The date must be a real calendar date in YYYY-MM-DD format.',
                false,
                null,
                self::INVALID,
            );
        }

        try {
            $result = $this->provider->retrieve($date);
        } catch (MetadataSourceException $exception) {
            return $this->emitError(
                $date,
                $exception->errorCode,
                $exception->getMessage(),
                $exception->retryable,
                $exception->rawPayload,
                self::FAILURE,
            );
        } catch (Throwable) {
            return $this->emitError(
                $date,
                'UNEXPECTED_ERROR',
                'An unexpected error occurred while retrieving Bing wallpaper metadata.',
                false,
                null,
                self::FAILURE,
            );
        }

        return $this->emit([
            'schemaVersion' => '1.0',
            'status' => $result->status,
            'query' => $this->query($date),
            'data' => $result->data,
            'rawPayload' => $result->rawPayload,
            'error' => null,
            'retrievedAt' => $this->retrievedAt(),
        ], self::SUCCESS);
    }

    private function emitError(
        ?string $date,
        string $code,
        string $message,
        bool $retryable,
        mixed $rawPayload,
        int $exitCode,
    ): int {
        return $this->emit([
            'schemaVersion' => '1.0',
            'status' => 'error',
            'query' => $this->query($date),
            'data' => null,
            'rawPayload' => $rawPayload,
            'error' => [
                'code' => $code,
                'message' => $message,
                'retryable' => $retryable,
            ],
            'retrievedAt' => $this->retrievedAt(),
        ], $exitCode);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function emit(array $payload, int $exitCode): int
    {
        $json = json_encode(
            $payload,
            JSON_UNESCAPED_SLASHES
                | JSON_UNESCAPED_UNICODE
                | JSON_INVALID_UTF8_SUBSTITUTE
                | JSON_THROW_ON_ERROR,
        );

        $this->output->writeln($json, OutputInterface::OUTPUT_RAW);

        return $exitCode;
    }

    /**
     * @return array{source: 'bing', market: 'en-US', date: string|null}
     */
    private function query(?string $date): array
    {
        return [
            'source' => 'bing',
            'market' => 'en-US',
            'date' => $date,
        ];
    }

    private function retrievedAt(): string
    {
        return CarbonImmutable::now('UTC')->format('Y-m-d\TH:i:s\Z');
    }
}
