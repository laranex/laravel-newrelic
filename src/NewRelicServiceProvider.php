<?php

declare(strict_types=1);

namespace Laranex\LaravelNewrelic;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\ServiceProvider;
use Laranex\LaravelNewrelic\Contracts\Agent;
use Laranex\LaravelNewrelic\Contracts\LogTransport;
use Laranex\LaravelNewrelic\Listeners\EndTransaction;
use Laranex\LaravelNewrelic\Listeners\FlushLogs;
use Laranex\LaravelNewrelic\Listeners\RestartBackgroundTransaction;
use Laranex\LaravelNewrelic\Listeners\StartWebTransaction;
use Laranex\LaravelNewrelic\Logging\CurlTransport;
use Laranex\LaravelNewrelic\Logging\NewRelicLogger;

class NewRelicServiceProvider extends ServiceProvider
{
    /**
     * The Octane events that open and close a web transaction.
     *
     * @var array<string, class-string>
     */
    public const OCTANE_EVENTS = [
        'Laravel\Octane\Events\WorkerStarting' => EndTransaction::class,
        'Laravel\Octane\Events\RequestReceived' => StartWebTransaction::class,
        'Laravel\Octane\Events\RequestTerminated' => EndTransaction::class,
    ];

    /**
     * The queue events after which a fresh background transaction starts.
     *
     * @var array<string, class-string>
     */
    public const QUEUE_EVENTS = [
        'Illuminate\Queue\Events\JobProcessed' => RestartBackgroundTransaction::class,
        'Laravel\Horizon\Events\JobReleased' => RestartBackgroundTransaction::class,
    ];

    /**
     * The Octane and queue events after which the buffered "newrelic" records are sent.
     *
     * @var list<string>
     */
    public const FLUSH_EVENTS = [
        'Laravel\Octane\Events\RequestTerminated',
        'Laravel\Octane\Events\TaskTerminated',
        'Laravel\Octane\Events\TickTerminated',
        'Illuminate\Queue\Events\JobProcessed',
        'Illuminate\Queue\Events\JobExceptionOccurred',
    ];

    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/newrelic.php', 'newrelic');

        $this->app->singleton(Agent::class, NewRelicAgent::class);
        $this->app->singleton(LogTransport::class, fn (Container $app): CurlTransport => new CurlTransport(
            $this->intConfig($app, 'newrelic.transport.timeout', 5),
            $this->intConfig($app, 'newrelic.transport.retries', 3),
        ));

        $this->app->singleton(StartWebTransaction::class, fn (Container $app): StartWebTransaction => new StartWebTransaction($app->make(Agent::class), $this->appName($app)));
        $this->app->singleton(EndTransaction::class);
        $this->app->singleton(RestartBackgroundTransaction::class, fn (Container $app): RestartBackgroundTransaction => new RestartBackgroundTransaction($app->make(Agent::class), $this->appName($app)));

        $this->registerLogChannel();
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registerTransactionListeners();
        $this->registerFlushListener();

        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../config/newrelic.php' => config_path('newrelic.php'),
        ], ['newrelic', 'newrelic-config']);
    }

    /**
     * Make a "newrelic" log channel available unless the application defines its own.
     */
    protected function registerLogChannel(): void
    {
        $config = $this->app->make(ConfigRepository::class);

        if ($config->has('logging.channels.newrelic')) {
            return;
        }

        $config->set('logging.channels.newrelic', [
            'driver' => 'custom',
            'via' => NewRelicLogger::class,
            'level' => 'debug',
            'buffer' => true,
        ]);
    }

    protected function registerTransactionListeners(): void
    {
        $config = $this->app->make(ConfigRepository::class);
        $events = $this->app->make(Dispatcher::class);

        if ((bool) $config->get('newrelic.transactions.octane', true)) {
            foreach (self::OCTANE_EVENTS as $event => $listener) {
                $events->listen($event, $listener);
            }
        }

        if ((bool) $config->get('newrelic.transactions.queue', true)) {
            foreach (self::QUEUE_EVENTS as $event => $listener) {
                $events->listen($event, $listener);
            }
        }
    }

    /**
     * Send the buffered records after every unit of work, since Octane and queue workers do not exit in between.
     */
    protected function registerFlushListener(): void
    {
        $this->app->make(Dispatcher::class)->listen(self::FLUSH_EVENTS, FlushLogs::class);
    }

    protected function intConfig(Container $app, string $key, int $default): int
    {
        $value = $app->make(ConfigRepository::class)->get($key, $default);

        return is_numeric($value) ? (int) $value : $default;
    }

    protected function appName(Container $app): ?string
    {
        $name = $app->make(ConfigRepository::class)->get('newrelic.app_name');

        return is_string($name) && $name !== '' ? $name : null;
    }
}
