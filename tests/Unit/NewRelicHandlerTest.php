<?php

declare(strict_types=1);

use Laranex\LaravelNewrelic\Logging\NewRelicFormatter;
use Laranex\LaravelNewrelic\Logging\NewRelicHandler;
use Laranex\LaravelNewrelic\Logging\Record;
use Laranex\LaravelNewrelic\Tests\Fakes\FakeTransport;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\BufferHandler;
use Monolog\Logger;

beforeEach(function (): void {
    $this->transport = new FakeTransport;
});

it('posts a single record as a one element JSON array with the license key header', function (): void {
    $handler = new NewRelicHandler($this->transport, 'us-license-key');

    $handler->handle(record('Order placed', Logger::INFO, ['order' => 1]));

    expect($this->transport->requests)->toHaveCount(1)
        ->and($this->transport->requests[0]['url'])->toBe('https://log-api.newrelic.com/log/v1')
        ->and($this->transport->requests[0]['headers'])->toBe(['Content-Type' => 'application/json', 'X-License-Key' => 'us-license-key'])
        ->and($this->transport->logs())->toHaveCount(1)
        ->and($this->transport->logs()[0]['message'])->toBe('Order placed')
        ->and($this->transport->logs()[0]['level_name'])->toBe('INFO')
        ->and($this->transport->logs()[0]['context'])->toBe(['order' => 1])
        ->and($this->transport->logs()[0]['timestamp'])->toBeInt();
});

it('derives the Logs API host from the license key region', function (string $licenseKey, string $host): void {
    expect(NewRelicHandler::defaultHost($licenseKey))->toBe($host)
        ->and((new NewRelicHandler($this->transport, $licenseKey))->url())->toBe("https://{$host}/log/v1");
})->with([
    'US key' => ['abcdef0123456789abcdef0123456789abcdef01', 'log-api.newrelic.com'],
    'EU key' => ['eu01xx0123456789abcdef0123456789abcdef01', 'log-api.eu.newrelic.com'],
]);

it('posts to a custom host when one is given', function (): void {
    $handler = new NewRelicHandler($this->transport, 'eu01xx0123456789abcdef0123456789abcdef01', 'log-api.example.test');

    $handler->handle(record());

    expect($this->transport->requests[0]['url'])->toBe('https://log-api.example.test/log/v1');
});

it('refuses an empty license key', function (): void {
    new NewRelicHandler($this->transport, '');
})->throws(InvalidArgumentException::class, 'license key');

it('sends a batch as one request and drops records below its level', function (): void {
    $handler = new NewRelicHandler($this->transport, 'us-license-key', null, Logger::WARNING);

    $handler->handleBatch([
        record('debug noise', Logger::DEBUG),
        record('disk almost full', Logger::WARNING),
        record('payment failed', Logger::ERROR),
    ]);

    expect($this->transport->requests)->toHaveCount(1)
        ->and(array_column($this->transport->logs(), 'message'))->toBe(['disk almost full', 'payment failed']);
});

it('sends nothing when every record in a batch is below its level', function (): void {
    $handler = new NewRelicHandler($this->transport, 'us-license-key', null, Logger::ERROR);

    $handler->handleBatch([record('fine', Logger::INFO)]);

    expect($this->transport->requests)->toBeEmpty();
});

it('runs its own processors on batched records', function (): void {
    $handler = new NewRelicHandler($this->transport, 'us-license-key');
    $handler->pushProcessor(fn ($record) => Record::withExtra($record, 'tenant', 'acme'));

    $handler->handleBatch([record()]);

    expect($this->transport->logs()[0]['extra'])->toBe(['tenant' => 'acme']);
});

it('ignores records below its level and respects bubbling', function (): void {
    $handler = new NewRelicHandler($this->transport, 'us-license-key', null, Logger::ERROR, false);

    expect($handler->handle(record('fine', Logger::INFO)))->toBeFalse()
        ->and($handler->handle(record('broken', Logger::ERROR)))->toBeTrue()
        ->and($this->transport->requests)->toHaveCount(1);
});

it('only accepts the New Relic formatter', function (): void {
    $handler = new NewRelicHandler($this->transport, 'us-license-key');

    expect($handler->getFormatter())->toBeInstanceOf(NewRelicFormatter::class)
        ->and($handler->setFormatter(new NewRelicFormatter))->toBe($handler);

    $handler->setFormatter(new LineFormatter);
})->throws(InvalidArgumentException::class, NewRelicFormatter::class);

it('flushes one batch when wrapped in a buffer handler', function (): void {
    $logger = new Logger('app');
    $logger->pushHandler(new BufferHandler(new NewRelicHandler($this->transport, 'us-license-key')));

    $logger->info('first');
    $logger->error('second');

    expect($this->transport->requests)->toBeEmpty();

    $logger->reset();

    expect($this->transport->requests)->toHaveCount(1)
        ->and(array_column($this->transport->logs(), 'message'))->toBe(['first', 'second']);
});

it('splits a batch into requests that stay under the Logs API payload limit', function (): void {
    $handler = new NewRelicHandler($this->transport, 'us-license-key');
    $message = str_repeat('x', 400_000);

    $handler->handleBatch([record($message.'1'), record($message.'2'), record($message.'3')]);

    expect($this->transport->requests)->toHaveCount(2)
        ->and(strlen($this->transport->requests[0]['body']))->toBeLessThanOrEqual(NewRelicHandler::MAX_PAYLOAD_BYTES)
        ->and(strlen($this->transport->requests[1]['body']))->toBeLessThanOrEqual(NewRelicHandler::MAX_PAYLOAD_BYTES)
        ->and(array_column($this->transport->logs(0), 'message'))->toBe([$message.'1', $message.'2'])
        ->and(array_column($this->transport->logs(1), 'message'))->toBe([$message.'3']);
});
