<?php

namespace Laranex\LaravelNewrelic;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\ServiceProvider;

class LaravelNewrelicServiceProvider extends ServiceProvider
{
    use EventMap;

    /**
     * Bootstrap the application services.
     */
    public function boot()
    {

        $this->registerEvents();

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/config.php' => config_path('laravel-newrelic.php'),
            ], 'laravel-newrelic');

        }
    }

    /**
     * Register the application services.
     */
    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/../config/config.php', 'laravel-newrelic');
        $this->mergeConfigFrom(__DIR__.'/../config/driver.php', 'logging.channels');

        $this->bindListeners();
    }

    /**
     * Register the package's events.
     *
     * @return void
     */
    protected function registerEvents()
    {
        $events = $this->app->make(Dispatcher::class);

        foreach ($this->events as $event => $listeners) {
            foreach ($listeners as $listener) {
                $events->listen($event, $listener);
            }
        }
    }

    protected function bindListeners()
    {
        $this->app->singleton(Listeners\StartNewrelicWebTransaction::class);
        $this->app->singleton(Listeners\StopNewrelicWebTransaction::class);
        $this->app->singleton(Listeners\RestartNewrelicTransaction::class);
    }
}
