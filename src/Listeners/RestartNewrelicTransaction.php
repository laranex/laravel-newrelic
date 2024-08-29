<?php

namespace Laranex\LaravelNewrelic\Listeners;

class RestartNewrelicTransaction
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle($_event): void
    {
        if (extension_loaded('newrelic')) {
            newrelic_end_transaction();
            newrelic_start_transaction(ini_get('newrelic.appname'));
        }
    }
}
