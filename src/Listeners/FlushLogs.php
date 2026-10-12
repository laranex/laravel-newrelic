<?php

declare(strict_types=1);

namespace Laranex\LaravelNewrelic\Listeners;

use Illuminate\Contracts\Container\Container;
use Laranex\LaravelNewrelic\Listeners\Concerns\ResolvesLoggers;
use Laranex\LaravelNewrelic\Logging\NewRelicBufferHandler;

/**
 * Sends the buffered "newrelic" records after each Octane request or queue job.
 *
 * Long-running workers never shut down between units of work, so without this the buffer would only be sent when the process exits.
 */
class FlushLogs
{
    use ResolvesLoggers;

    public function __construct(protected Container $container) {}

    public function handle(): void
    {
        foreach ($this->loggers() as $logger) {
            foreach ($logger->getHandlers() as $handler) {
                if ($handler instanceof NewRelicBufferHandler) {
                    $handler->flush();
                }
            }
        }
    }
}
