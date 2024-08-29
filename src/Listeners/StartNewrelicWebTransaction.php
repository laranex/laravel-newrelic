<?php

namespace Laranex\LaravelNewrelic\Listeners;

class StartNewrelicWebTransaction
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
            newrelic_start_transaction(ini_get('newrelic.appname'));
            newrelic_background_job(false); // Set the transaction as a web transaction
        }
    }
}
