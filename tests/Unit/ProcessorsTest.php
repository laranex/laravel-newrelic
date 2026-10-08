<?php

declare(strict_types=1);

use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Http\Request;
use Laranex\LaravelNewrelic\Logging\MetadataProcessor;
use Laranex\LaravelNewrelic\Logging\NewRelicFormatter;
use Laranex\LaravelNewrelic\Logging\NewRelicProcessor;
use Laranex\LaravelNewrelic\Logging\Record;
use Laranex\LaravelNewrelic\Tests\Fakes\FakeAgent;

it('adds the linking metadata with a zero padded trace id', function (): void {
    $processor = new NewRelicProcessor(new FakeAgent(metadata: ['trace.id' => 'abc123', 'span.id' => 'span', 'entity.guid' => 'guid']));

    $record = $processor(record());

    expect(extra($record)[NewRelicFormatter::CONTEXT_KEY])->toBe([
        'trace.id' => str_pad('abc123', 32, '0', STR_PAD_LEFT),
        'span.id' => 'span',
        'entity.guid' => 'guid',
    ]);
});

it('leaves the record alone when the agent has no linking metadata', function (): void {
    $processor = new NewRelicProcessor(new FakeAgent(loaded: false));

    $record = $processor(record(extra: ['kept' => true]));

    expect(extra($record))->toBe(['kept' => true]);
});

it('adds the service name and hostname without a request', function (): void {
    $container = new Container;
    $container->instance(ConfigRepository::class, new Repository(['app' => ['name' => 'Shop']]));

    $record = (new MetadataProcessor($container))(record());

    expect(extra($record))->toBe(['service' => 'Shop', 'hostname' => gethostname()])
        ->and(extra($record))->not->toHaveKey('ip');
});

it('falls back to "Laravel" when the application has no name', function (): void {
    $container = new Container;
    $container->instance(ConfigRepository::class, new Repository(['app' => ['name' => '']]));

    expect(extra((new MetadataProcessor($container))(record()))['service'])->toBe('Laravel');
});

it('adds the client ip of the current request and skips the user when auth is unavailable', function (): void {
    $container = new Container;
    $container->instance(ConfigRepository::class, new Repository(['app' => ['name' => 'Shop']]));
    $container->instance('request', Request::create('/orders', 'GET', server: ['REMOTE_ADDR' => '10.0.0.9']));

    $record = (new MetadataProcessor($container))(record());

    expect(extra($record)['ip'])->toBe('10.0.0.9')
        ->and(extra($record))->not->toHaveKey('user');
});

it('skips the user when the guard cannot be resolved', function (): void {
    $container = new Container;
    $container->instance(ConfigRepository::class, new Repository(['app' => ['name' => 'Shop']]));
    $container->instance('request', Request::create('/orders'));
    $container->bind(AuthFactory::class, fn () => throw new RuntimeException('no auth'));

    expect(extra((new MetadataProcessor($container))(record())))->not->toHaveKey('user');
});

it('reads and writes records of the installed Monolog version', function (): void {
    $record = record('msg', extra: ['a' => 1], formatted: '{"json":true}');

    expect(Record::toArray($record)['message'])->toBe('msg')
        ->and(Record::formatted($record))->toBe('{"json":true}')
        ->and(Record::formatted(record()))->toBe('')
        ->and(extra(Record::withExtra($record, 'b', 2)))->toBe(['a' => 1, 'b' => 2]);
});

it('creates the extra array when a Monolog 2 record has none', function (): void {
    $record = Record::withExtra(['message' => 'bare'], 'service', 'Shop');

    expect($record)->toBe(['message' => 'bare', 'extra' => ['service' => 'Shop']]);
});
