<?php

declare(strict_types=1);

namespace Laranex\LaravelNewrelic\Logging;

use DateTimeInterface;
use Monolog\Formatter\JsonFormatter;
use Monolog\LogRecord;
use stdClass;

/**
 * Formats Monolog records as JSON objects the New Relic Logs API understands.
 *
 * The service, hostname and user attributes and then the New Relic linking metadata are lifted to
 * the top level of the record (the agent's hostname wins, so logs link to the right host entity),
 * and the record's datetime becomes a "timestamp" in milliseconds since the UNIX epoch.
 *
 * Derived from the New Relic Monolog Enricher (Copyright 2019 New Relic Corporation, Apache-2.0).
 */
class NewRelicFormatter extends JsonFormatter
{
    public const CONTEXT_KEY = 'newrelic-context';

    /**
     * @var list<string>
     */
    public const TOP_LEVEL_EXTRA = ['service', 'hostname', 'user'];

    public function __construct(bool $appendNewline = false)
    {
        parent::__construct(self::BATCH_MODE_JSON, $appendNewline);
    }

    /**
     * @param  array<string, mixed>|LogRecord  $record
     */
    public function format(array|LogRecord $record): string
    {
        return $this->toJson($this->prepare($record), true).($this->appendNewline ? "\n" : '');
    }

    /**
     * Formats a batch of records as one JSON array, which is what the Logs API expects.
     *
     * @param  array<array<string, mixed>|LogRecord>  $records
     */
    public function formatBatch(array $records): string
    {
        $prepared = [];

        foreach ($records as $record) {
            $prepared[] = $this->prepare($record);
        }

        return $this->toJson($prepared, true).($this->appendNewline ? "\n" : '');
    }

    /**
     * @param  array<string, mixed>|LogRecord  $record
     * @return array<string, mixed>
     */
    protected function prepare(array|LogRecord $record): array
    {
        $data = Record::toArray($record);
        $extra = isset($data['extra']) && is_array($data['extra']) ? $data['extra'] : [];

        foreach (self::TOP_LEVEL_EXTRA as $key) {
            if (array_key_exists($key, $extra)) {
                $data[$key] = $extra[$key];
                unset($extra[$key]);
            }
        }

        if (isset($extra[self::CONTEXT_KEY]) && is_array($extra[self::CONTEXT_KEY])) {
            $data = array_merge($data, $extra[self::CONTEXT_KEY]);
            unset($extra[self::CONTEXT_KEY]);
        }

        $data['extra'] = $extra;

        if (isset($data['datetime']) && $data['datetime'] instanceof DateTimeInterface) {
            $data['timestamp'] = self::milliseconds($data['datetime']);
            unset($data['datetime']);
        }

        $normalized = $this->normalize($data);
        $normalized = is_array($normalized) ? $normalized : [];

        foreach (['context', 'extra'] as $key) {
            if (($normalized[$key] ?? null) === []) {
                $normalized[$key] = new stdClass;
            }
        }

        return $normalized;
    }

    public static function milliseconds(DateTimeInterface $dateTime): int
    {
        return $dateTime->getTimestamp() * 1000 + intdiv((int) $dateTime->format('u'), 1000);
    }
}
