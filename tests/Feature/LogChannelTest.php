<?php

declare(strict_types=1);

use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Log;
use Laranex\LaravelNewrelic\Logging\NewRelicLogger;
use Monolog\Handler\BufferHandler;
use Monolog\Logger;

function flushNewRelicChannel(): void
{
    $logger = Log::channel('newrelic')->getLogger();

    if ($logger instanceof Logger) {
        $logger->reset();
    }
}

it('buffers records and ships them as one batch when the logger is reset', function (): void {
    Log::channel('newrelic')->info('Order placed', ['order' => 1]);
    Log::channel('newrelic')->error('Payment failed');

    expect($this->transport->requests)->toBeEmpty();

    flushNewRelicChannel();

    expect($this->transport->requests)->toHaveCount(1)
        ->and($this->transport->requests[0]['url'])->toBe('https://log-api.newrelic.com/log/v1')
        ->and($this->transport->requests[0]['headers']['X-License-Key'])->toBe('us-license-key')
        ->and(array_column($this->transport->logs(), 'message'))->toBe(['Order placed', 'Payment failed'])
        ->and($this->transport->logs()[0]['context'])->toBe(['order' => 1]);
});

it('adds the service, hostname, client ip and linking metadata to every record', function (): void {
    $this->agent->metadata = ['trace.id' => 'abc', 'span.id' => 'def', 'entity.guid' => 'guid', 'hostname' => 'web-1'];

    $this->get('/missing-route', ['X-Forwarded-For' => '203.0.113.9']);
    Log::channel('newrelic')->info('In request');
    flushNewRelicChannel();

    $log = $this->transport->logs()[0];

    expect($log)->toMatchArray(['service' => 'Shop', 'hostname' => 'web-1', 'trace.id' => str_pad('abc', 32, '0', STR_PAD_LEFT), 'span.id' => 'def', 'entity.guid' => 'guid'])
        ->and($log['extra']['ip'])->toBe('127.0.0.1')
        ->and($log)->not->toHaveKey('newrelic-context');
});

it('adds the authenticated user to every record', function (): void {
    $user = new User;
    $user->forceFill(['id' => 7, 'email' => 'jane@example.com']);

    $this->actingAs($user)->get('/missing-route');
    Log::channel('newrelic')->warning('Suspicious');
    flushNewRelicChannel();

    expect($this->transport->logs()[0]['user'])->toBe(['id' => 7, 'email' => 'jane@example.com']);
});

it('honors the channel level and sends immediately without the buffer', function (): void {
    config()->set('logging.channels.newrelic', ['driver' => 'custom', 'via' => NewRelicLogger::class, 'level' => 'warning', 'buffer' => false]);

    Log::channel('newrelic')->info('ignored');
    Log::channel('newrelic')->error('sent');

    expect($this->transport->requests)->toHaveCount(1)
        ->and(array_column($this->transport->logs(), 'message'))->toBe(['sent']);
});

it('builds the logger with a buffered handler and the channel name', function (): void {
    $logger = app(NewRelicLogger::class)(['level' => 'debug']);

    expect($logger)->toBeInstanceOf(Logger::class)
        ->and($logger->getName())->toBe('newrelic')
        ->and($logger->getHandlers()[0])->toBeInstanceOf(BufferHandler::class)
        ->and(app(NewRelicLogger::class)(['name' => 'custom', 'buffer' => false])->getName())->toBe('custom')
        ->and(app(NewRelicLogger::class)(['name' => 'custom', 'buffer' => false])->getHandlers()[0])->not->toBeInstanceOf(BufferHandler::class);
});

it('uses the agent license key when none is configured', function (): void {
    config()->set('newrelic.license_key', null);
    $this->agent->licenseKey = 'eu01xx0123456789abcdef0123456789abcdef01';

    Log::channel('newrelic')->info('hi');
    flushNewRelicChannel();

    expect($this->transport->requests[0]['url'])->toBe('https://log-api.eu.newrelic.com/log/v1')
        ->and($this->transport->requests[0]['headers']['X-License-Key'])->toBe('eu01xx0123456789abcdef0123456789abcdef01');
});

it('posts to the configured host', function (): void {
    config()->set('newrelic.host', 'log-api.example.test');

    Log::channel('newrelic')->info('hi');
    flushNewRelicChannel();

    expect($this->transport->requests[0]['url'])->toBe('https://log-api.example.test/log/v1');
});

it('refuses to build the channel without any license key', function (): void {
    config()->set('newrelic.license_key', '');

    app(NewRelicLogger::class)(['level' => 'debug']);
})->throws(InvalidArgumentException::class, 'NEW_RELIC_LICENSE_KEY');
