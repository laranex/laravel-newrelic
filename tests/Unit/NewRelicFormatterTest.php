<?php

declare(strict_types=1);

use Laranex\LaravelNewrelic\Logging\NewRelicFormatter;
use Monolog\DateTimeImmutable;
use Monolog\Logger;

it('turns the datetime into a millisecond timestamp', function (): void {
    $datetime = new DateTimeImmutable(true, new DateTimeZone('UTC'));
    $datetime = $datetime->setTimestamp(1_700_000_000)->setTime(12, 34, 56, 789_500);

    $formatted = json_decode((new NewRelicFormatter)->format(record(datetime: $datetime)), true);

    expect($formatted)->not->toHaveKey('datetime')
        ->and($formatted['timestamp'])->toBe(NewRelicFormatter::milliseconds($datetime))
        ->and($formatted['timestamp'] % 1000)->toBe(789)
        ->and(NewRelicFormatter::milliseconds($datetime))->toBe($datetime->getTimestamp() * 1000 + 789);
});

it('lifts the New Relic linking metadata to the top level of the record', function (): void {
    $formatted = json_decode((new NewRelicFormatter)->format(record(extra: [
        NewRelicFormatter::CONTEXT_KEY => ['trace.id' => 'abc', 'span.id' => 'def', 'entity.guid' => 'guid'],
        'other' => 'kept',
    ])), true);

    expect($formatted)->toMatchArray(['trace.id' => 'abc', 'span.id' => 'def', 'entity.guid' => 'guid'])
        ->and($formatted['extra'])->toBe(['other' => 'kept']);
});

it('lifts the service, hostname and user attributes to the top level of the record', function (): void {
    $formatted = json_decode((new NewRelicFormatter)->format(record(extra: [
        'service' => 'Shop',
        'hostname' => 'web-1',
        'user' => ['id' => 7, 'email' => 'jane@example.com'],
        'ip' => '127.0.0.1',
    ])), true);

    expect($formatted)->toMatchArray(['service' => 'Shop', 'hostname' => 'web-1', 'user' => ['id' => 7, 'email' => 'jane@example.com']])
        ->and($formatted['extra'])->toBe(['ip' => '127.0.0.1']);
});

it('encodes empty context and extra as JSON objects', function (): void {
    $json = (new NewRelicFormatter)->format(record());

    expect($json)->toContain('"context":{}')->toContain('"extra":{}');
});

it('keeps the message, level and channel and normalizes the context', function (): void {
    $formatted = json_decode((new NewRelicFormatter)->format(record('Paid', Logger::NOTICE, ['when' => new DateTimeImmutable(true), 'amount' => 10.5])), true);

    expect($formatted)->toMatchArray(['message' => 'Paid', 'level' => Logger::NOTICE, 'level_name' => 'NOTICE', 'channel' => 'app'])
        ->and($formatted['context']['amount'])->toBe(10.5)
        ->and($formatted['context']['when'])->toBeString();
});

it('formats a batch as a JSON array of records', function (): void {
    $formatter = new NewRelicFormatter;

    $batch = json_decode($formatter->formatBatch([record('one'), record('two')]), true);

    expect($batch)->toHaveCount(2)
        ->and(array_column($batch, 'message'))->toBe(['one', 'two'])
        ->and($formatter->formatBatch([]))->toBe('[]');
});

it('appends a newline only when asked', function (): void {
    expect((new NewRelicFormatter)->format(record()))->not->toEndWith("\n")
        ->and((new NewRelicFormatter(true))->format(record()))->toEndWith("}\n")
        ->and((new NewRelicFormatter(true))->formatBatch([record()]))->toEndWith("]\n");
});

it('lets the agent linking metadata win over the hostname added by the metadata processor', function (): void {
    $formatted = json_decode((new NewRelicFormatter)->format(record(extra: [
        'hostname' => 'php-host',
        NewRelicFormatter::CONTEXT_KEY => ['hostname' => 'agent-host', 'entity.guid' => 'guid'],
    ])), true);

    expect($formatted['hostname'])->toBe('agent-host')
        ->and($formatted['entity.guid'])->toBe('guid');
});
