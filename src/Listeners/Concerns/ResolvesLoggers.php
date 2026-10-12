<?php

declare(strict_types=1);

namespace Laranex\LaravelNewrelic\Listeners\Concerns;

use Illuminate\Contracts\Container\Container;
use Illuminate\Log\Logger as IlluminateLogger;
use Illuminate\Log\LogManager;
use Monolog\Logger;

/**
 * @property Container $container
 */
trait ResolvesLoggers
{
    /**
     * The Monolog loggers of the log channels resolved so far; none when logging was never used.
     *
     * @return list<Logger>
     */
    protected function loggers(): array
    {
        if (! $this->container->resolved('log')) {
            return [];
        }

        $loggers = [];

        foreach ($this->container->make(LogManager::class)->getChannels() as $channel) {
            $logger = $channel instanceof IlluminateLogger ? $channel->getLogger() : $channel;

            if ($logger instanceof Logger) {
                $loggers[] = $logger;
            }
        }

        return $loggers;
    }
}
