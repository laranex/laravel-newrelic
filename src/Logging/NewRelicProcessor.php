<?php

declare(strict_types=1);

namespace Laranex\LaravelNewrelic\Logging;

use Laranex\LaravelNewrelic\Contracts\Agent;
use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

/**
 * Adds the New Relic linking metadata to every record so logs show up in context of their APM transaction.
 *
 * Derived from the New Relic Monolog Enricher (Copyright 2019 New Relic Corporation, Apache-2.0).
 */
class NewRelicProcessor implements ProcessorInterface
{
    public function __construct(protected Agent $agent) {}

    /**
     * @param  array<string, mixed>|LogRecord  $record
     * @return ($record is LogRecord ? LogRecord : array<string, mixed>)
     */
    public function __invoke(array|LogRecord $record): array|LogRecord
    {
        $metadata = $this->agent->linkingMetadata();

        if ($metadata === []) {
            return $record;
        }

        if (isset($metadata['trace.id'])) {
            $metadata['trace.id'] = str_pad($metadata['trace.id'], 32, '0', STR_PAD_LEFT);
        }

        return Record::withExtra($record, NewRelicFormatter::CONTEXT_KEY, $metadata);
    }
}
