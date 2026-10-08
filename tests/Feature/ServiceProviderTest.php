<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Laranex\LaravelNewrelic\Contracts\Agent;
use Laranex\LaravelNewrelic\Contracts\LogTransport;
use Laranex\LaravelNewrelic\Listeners\EndTransaction;
use Laranex\LaravelNewrelic\Listeners\RestartBackgroundTransaction;
use Laranex\LaravelNewrelic\Listeners\StartWebTransaction;
use Laranex\LaravelNewrelic\Logging\CurlTransport;
use Laranex\LaravelNewrelic\Logging\NewRelicLogger;
use Laranex\LaravelNewrelic\NewRelicAgent;
use Laranex\LaravelNewrelic\NewRelicServiceProvider;

it('merges the package config with its defaults', function (): void {
    expect(config('newrelic.license_key'))->toBe('us-license-key')
        ->and(config('newrelic.host'))->toBeNull()
        ->and(config('newrelic.app_name'))->toBeNull()
        ->and(config('newrelic.transactions'))->toBe(['octane' => true, 'queue' => true])
        ->and(config('newrelic.transport'))->toBe(['timeout' => 5, 'retries' => 3]);
});

it('reads the transaction and transport settings from the environment', function (): void {
    $env = [
        'NEW_RELIC_OCTANE_TRANSACTIONS' => 'false',
        'NEW_RELIC_QUEUE_TRANSACTIONS' => 'false',
        'NEW_RELIC_LOG_TIMEOUT' => '10',
        'NEW_RELIC_LOG_RETRIES' => '1',
    ];

    foreach ($env as $key => $value) {
        putenv($key.'='.$value);
    }

    try {
        $this->refreshApplication();

        expect(config('newrelic.transactions'))->toBe(['octane' => false, 'queue' => false])
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

it('listens to the Octane and queue events by default', function (): void {
    foreach (NewRelicServiceProvider::OCTANE_EVENTS + NewRelicServiceProvider::QUEUE_EVENTS as $event => $listener) {
        expect(Event::hasListeners($event))->toBeTrue("No listener for {$event}");
    }
});

it('resolves the listeners as singletons with the configured app name', function (): void {
    config()->set('newrelic.app_name', 'Shop API');
    $this->app->forgetInstance(StartWebTransaction::class);
    $this->app->forgetInstance(RestartBackgroundTransaction::class);

    app(StartWebTransaction::class)->handle();
    app(RestartBackgroundTransaction::class)->handle();

    expect(app(StartWebTransaction::class))->toBe(app(StartWebTransaction::class))
        ->and(app(EndTransaction::class))->toBe(app(EndTransaction::class))
        ->and($this->agent->calls)->toBe([
            ['startTransaction', 'Shop API'],
            ['backgroundJob', false],
            ['endTransaction', false],
            ['startTransaction', 'Shop API'],
            ['backgroundJob', true],
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

it('does not listen to the transaction events when they are turned off', function (): void {
    config()->set('newrelic.transactions.octane', false);
    config()->set('newrelic.transactions.queue', false);

    $provider = new NewRelicServiceProvider($this->app);
    Event::forget('Laravel\Octane\Events\RequestReceived');
    Event::forget('Illuminate\Queue\Events\JobProcessed');

    $provider->boot();

    expect(Event::hasListeners('Laravel\Octane\Events\RequestReceived'))->toBeFalse()
        ->and(Event::hasListeners('Illuminate\Queue\Events\JobProcessed'))->toBeFalse();
});

it('keeps a newrelic channel the application defines itself', function (): void {
    config()->set('logging.channels.newrelic', ['driver' => 'custom', 'via' => NewRelicLogger::class, 'level' => 'error', 'buffer' => false]);

    (new NewRelicServiceProvider($this->app))->register();

    expect(config('logging.channels.newrelic.level'))->toBe('error')
        ->and(config('logging.channels.newrelic.buffer'))->toBeFalse();
});
