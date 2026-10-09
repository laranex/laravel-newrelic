<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Laranex\LaravelNewrelic\Contracts\Agent;
use Laranex\LaravelNewrelic\Contracts\LogTransport;
use Laranex\LaravelNewrelic\Listeners\EndTransaction;
use Laranex\LaravelNewrelic\Listeners\FlushLogs;
use Laranex\LaravelNewrelic\Listeners\NameWebTransaction;
use Laranex\LaravelNewrelic\Listeners\StartWebTransaction;
use Laranex\LaravelNewrelic\Logging\CurlTransport;
use Laranex\LaravelNewrelic\Logging\NewRelicLogger;
use Laranex\LaravelNewrelic\NewRelicAgent;
use Laranex\LaravelNewrelic\NewRelicServiceProvider;

it('merges the package config with its defaults', function (): void {
    expect(config('newrelic.license_key'))->toBe('us-license-key')
        ->and(config('newrelic.host'))->toBeNull()
        ->and(config('newrelic.app_name'))->toBeNull()
        ->and(config('newrelic.transactions'))->toBe(['octane' => true])
        ->and(config('newrelic.transport'))->toBe(['timeout' => 5, 'retries' => 3]);
});

it('reads the transaction and transport settings from the environment', function (): void {
    $env = [
        'NEW_RELIC_OCTANE_TRANSACTIONS' => 'false',
        'NEW_RELIC_LOG_TIMEOUT' => '10',
        'NEW_RELIC_LOG_RETRIES' => '1',
    ];

    foreach ($env as $key => $value) {
        putenv($key.'='.$value);
    }

    try {
        $this->refreshApplication();

        expect(config('newrelic.transactions'))->toBe(['octane' => false])
            ->and(config('newrelic.transport'))->toBe(['timeout' => 10, 'retries' => 1]);
    } finally {
        foreach (array_keys($env) as $key) {
            putenv($key);
        }
    }
});

it('builds the transport from the configured timeout and retries', function (): void {
    config()->set('newrelic.transport', ['timeout' => 12, 'retries' => 2]);
    $this->app->forgetInstance(LogTransport::class);

    $transport = app(LogTransport::class);

    expect($transport)->toBeInstanceOf(CurlTransport::class)
        ->and((fn (): array => [$this->timeout, $this->retries])->call($transport))->toBe([12, 2]);
});

it('falls back to the default transport settings when the config is not numeric', function (): void {
    config()->set('newrelic.transport', ['timeout' => 'soon', 'retries' => null]);
    $this->app->forgetInstance(LogTransport::class);

    expect((fn (): array => [$this->timeout, $this->retries])->call(app(LogTransport::class)))->toBe([5, 3]);
});

it('binds the agent and the transport as singletons', function (): void {
    $this->app->forgetInstance(Agent::class);
    $this->app->forgetInstance(LogTransport::class);

    expect(app(Agent::class))->toBeInstanceOf(NewRelicAgent::class)->toBe(app(Agent::class))
        ->and(app(LogTransport::class))->toBeInstanceOf(CurlTransport::class)->toBe(app(LogTransport::class));
});

it('registers a ready to use newrelic log channel', function (): void {
    expect(config('logging.channels.newrelic'))->toBe([
        'driver' => 'custom',
        'via' => NewRelicLogger::class,
        'level' => 'debug',
        'buffer' => true,
    ]);
});

it('listens to the Octane events by default', function (): void {
    foreach (NewRelicServiceProvider::OCTANE_EVENTS as $event => $listeners) {
        expect(Event::getRawListeners()[$event] ?? [])->toContain(...$listeners);
    }
});

it('leaves queue job transactions to the New Relic agent', function (): void {
    expect(config('newrelic.transactions'))->not->toHaveKey('queue');

    foreach (['Illuminate\Queue\Events\JobProcessed', 'Illuminate\Queue\Events\JobExceptionOccurred'] as $event) {
        // Older Laravel versions register their own closure listeners on these events: check only the package's.
        $listeners = array_filter(
            Event::getRawListeners()[$event] ?? [],
            fn (mixed $listener): bool => is_string($listener) && str_starts_with($listener, 'Laranex\\LaravelNewrelic\\'),
        );

        expect(array_values($listeners))->toBe([FlushLogs::class]);
    }
});

it('names a terminated Octane request before ending its transaction', function (): void {
    $listeners = Event::getRawListeners()['Laravel\Octane\Events\RequestTerminated'];

    expect(array_search(NameWebTransaction::class, $listeners, true))
        ->toBeLessThan(array_search(EndTransaction::class, $listeners, true));
});

it('resolves the listeners as singletons with the configured app name', function (): void {
    config()->set('newrelic.app_name', 'Shop API');
    $this->app->forgetInstance(StartWebTransaction::class);

    app(StartWebTransaction::class)->handle();

    expect(app(StartWebTransaction::class))->toBe(app(StartWebTransaction::class))
        ->and(app(EndTransaction::class))->toBe(app(EndTransaction::class))
        ->and(app(NameWebTransaction::class))->toBe(app(NameWebTransaction::class))
        ->and($this->agent->calls)->toBe([
            ['startTransaction', 'Shop API'],
            ['backgroundJob', false],
        ]);
});

it('publishes the config file under the newrelic tags', function (): void {
    $source = realpath(__DIR__.'/../../config/newrelic.php');

    foreach (['newrelic', 'newrelic-config'] as $tag) {
        $paths = NewRelicServiceProvider::pathsToPublish(NewRelicServiceProvider::class, $tag);

        expect($paths)->toHaveCount(1)
            ->and(realpath((string) array_key_first($paths)))->toBe($source)
            ->and(array_values($paths))->toBe([config_path('newrelic.php')]);
    }
});

it('does not listen to the Octane transaction events when they are turned off', function (): void {
    config()->set('newrelic.transactions.octane', false);

    foreach (array_keys(NewRelicServiceProvider::OCTANE_EVENTS) as $event) {
        Event::forget($event);
    }

    (new NewRelicServiceProvider($this->app))->boot();

    // Inspect the registered listeners directly: hasListeners() is also true when any wildcard listener exists.
    $listeners = Event::getRawListeners();

    foreach (NewRelicServiceProvider::OCTANE_EVENTS as $event => $classes) {
        foreach ($classes as $listener) {
            expect($listeners[$event] ?? [])->not->toContain($listener);
        }
    }

    // Logs are still flushed after each request.
    expect($listeners['Laravel\Octane\Events\RequestTerminated'] ?? [])->toContain(FlushLogs::class);
});

it('keeps a newrelic channel the application defines itself', function (): void {
    config()->set('logging.channels.newrelic', ['driver' => 'custom', 'via' => NewRelicLogger::class, 'level' => 'error', 'buffer' => false]);

    (new NewRelicServiceProvider($this->app))->register();

    expect(config('logging.channels.newrelic.level'))->toBe('error')
        ->and(config('logging.channels.newrelic.buffer'))->toBeFalse();
});
