<?php

declare(strict_types=1);

namespace Laranex\LaravelNewrelic\Logging;

use Monolog\LogRecord;

/**
 * Reads and writes Monolog records the same way whether they are LogRecord objects (Monolog 3) or Monolog 2 style arrays.
 *
 * @internal
 */
final class Record
{
    /**
     * @param  array<string, mixed>|LogRecord  $record
     * @return array<string, mixed>
     */
    public static function toArray(array|LogRecord $record): array
    {
        return $record instanceof LogRecord ? $record->toArray() : $record;
    }

    /**
     * @param  array<string, mixed>|LogRecord  $record
     */
    public static function formatted(array|LogRecord $record): string
    {
        $formatted = $record instanceof LogRecord ? $record->formatted : ($record['formatted'] ?? null);

        return is_string($formatted) ? $formatted : '';
    }

    /**
     * @param  array<string, mixed>|LogRecord  $record
     * @return ($record is LogRecord ? LogRecord : array<string, mixed>)
     */
    public static function withExtra(array|LogRecord $record, string $key, mixed $value): array|LogRecord
    {
        if ($record instanceof LogRecord) {
            $record->extra[$key] = $value;

            return $record;
        }

        $extra = isset($record['extra']) && is_array($record['extra']) ? $record['extra'] : [];
        $extra[$key] = $value;
        $record['extra'] = $extra;

        return $record;
    }
}
