<?php

declare(strict_types=1);

namespace Laranex\LaravelNewrelic\Logging;

use InvalidArgumentException;
use Laranex\LaravelNewrelic\Contracts\LogTransport;
use Monolog\Formatter\FormatterInterface;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Handler\HandlerInterface;
use Monolog\Level;
use Monolog\Logger;
use Monolog\LogRecord;

/**
 * Ships Monolog records to the New Relic Logs API, one HTTP request per record or per batch (split at the 1 MB payload limit).
 *
 * Accepts LogRecord objects and Monolog 2 style array records. Wrap it in a BufferHandler to send one batch per request.
 *
 * Derived from the New Relic Monolog Enricher (Copyright 2019 New Relic Corporation, Apache-2.0).
 */
class NewRelicHandler extends AbstractProcessingHandler
{
    public const ENDPOINT = 'log/v1';

    /**
     * The largest request body the Logs API accepts, in bytes; bigger batches are split into several requests.
     */
    public const MAX_PAYLOAD_BYTES = 1_000_000;

    /**
     * @param  string  $licenseKey  The New Relic license (ingest) key
     * @param  string|null  $host  The Logs API host; derived from the license key's region when null
     * @param  value-of<Level::VALUES>|value-of<Level::NAMES>|Level  $level  The minimum level this handler accepts
     */
    public function __construct(
        protected LogTransport $transport,
        protected string $licenseKey,
        protected ?string $host = null,
        int|string|Level $level = Logger::DEBUG,
        bool $bubble = true,
    ) {
        if ($licenseKey === '') {
            throw new InvalidArgumentException('A New Relic license key is required to ship logs to New Relic.');
        }

        parent::__construct($level, $bubble);
    }

    /**
     * The URL every payload is posted to.
     *
     * @return non-empty-string
     */
    public function url(): string
    {
        return 'https://'.($this->host ?? self::defaultHost($this->licenseKey)).'/'.self::ENDPOINT;
    }

    /**
     * The Logs API host for the region a license key belongs to (US unless the key says otherwise).
     */
    public static function defaultHost(string $licenseKey): string
    {
        $region = preg_match('/^([a-z]{2,3})[0-9]{2}x/', $licenseKey, $matches) === 1 ? '.'.$matches[1] : '';

        return "log-api{$region}.newrelic.com";
    }

    /**
     * @param  array<string, mixed>|LogRecord  $record
     */
    protected function write(array|LogRecord $record): void
    {
        $this->send('['.Record::formatted($record).']');
    }

    /**
     * Sends every record at or above the handler's level as one batch, split into as few
     * requests as the Logs API payload limit allows.
     */
    public function handleBatch(array $records): void
    {
        $formatter = $this->getFormatter();
        $chunk = [];
        $size = 2;

        foreach ($records as $record) {
            if (! $this->isHandling($record)) {
                continue;
            }

            foreach ($this->processors as $processor) {
                $record = $processor($record);
            }

            $json = $formatter->format($record);
            $length = strlen($json) + 1;

            if ($chunk !== [] && $size + $length > self::MAX_PAYLOAD_BYTES) {
                $this->send('['.implode(',', $chunk).']');
                $chunk = [];
                $size = 2;
            }

            $chunk[] = $json;
            $size += $length;
        }

        if ($chunk !== []) {
            $this->send('['.implode(',', $chunk).']');
        }
    }

    /**
     * Only the NewRelicFormatter produces payloads the Logs API accepts.
     */
    public function setFormatter(FormatterInterface $formatter): HandlerInterface
    {
        if (! $formatter instanceof NewRelicFormatter) {
            throw new InvalidArgumentException(sprintf('%s only accepts a %s.', static::class, NewRelicFormatter::class));
        }

        return parent::setFormatter($formatter);
    }

    protected function getDefaultFormatter(): FormatterInterface
    {
        return new NewRelicFormatter;
    }

    protected function send(string $json): void
    {
        $this->transport->send($this->url(), [
            'Content-Type' => 'application/json',
            'X-License-Key' => $this->licenseKey,
        ], $json);
    }
}
