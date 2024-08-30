<?php

namespace Laranex\LaravelNewrelic;

trait EventMap
{
    /**
     * All of the "listen" mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $events = [
        'Laravel\Octane\Events\WorkerStarting' => [
            Listeners\StopNewrelicWebTransaction::class,
        ],
        'Laravel\Octane\Events\RequestReceived' => [
            Listeners\StartNewrelicWebTransaction::class,
        ],
        'Laravel\Octane\Events\RequestTerminated' => [
            Listeners\StopNewrelicWebTransaction::class,
        ],
        'Laravel\Horizon\Events\JobReleased' => [
            Listeners\RestartNewrelicTransaction::class,
        ],
        'Illuminate\Queue\Events\JobProcessed' => [
            Listeners\RestartNewrelicTransaction::class,
        ],
    ];
}
