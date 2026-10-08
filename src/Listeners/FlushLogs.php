<?php

declare(strict_types=1);

namespace Laranex\LaravelNewrelic\Listeners;

use Illuminate\Contracts\Container\Container;
use Illuminate\Log\Logger as IlluminateLogger;
use Illuminate\Log\LogManager;
use Laranex\LaravelNewrelic\Logging\NewRelicBufferHandler;
use Monolog\Logger;

/**
 * Sends the buffered "newrelic" records after each Octane request or queue job.
 *
 * Long-running workers never shut down between units of work, so without this the buffer would only be sent when the process exits.
 */
class FlushLogs
{
    public function __construct(protected Container $container) {}

    public function handle(): void
    {
        if (! $this->container->resolved('log')) {
            return;
        }

        foreach ($this->container->make(LogManager::class)->getChannels() as $channel) {
            $logger = $channel instanceof IlluminateLogger ? $channel->getLogger() : $channel;

            if (! $logger instanceof Logger) {
                continue;
            }

            foreach ($logger->getHandlers() as $handler) {
                if ($handler instanceof NewRelicBufferHandler) {
                    $handler->flush();
                }
            }
        }
    }
}
