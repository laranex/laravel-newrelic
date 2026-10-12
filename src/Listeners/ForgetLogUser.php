<?php

declare(strict_types=1);

namespace Laranex\LaravelNewrelic\Listeners;

use Illuminate\Contracts\Container\Container;
use Laranex\LaravelNewrelic\Listeners\Concerns\ResolvesLoggers;
use Laranex\LaravelNewrelic\Logging\MetadataProcessor;

/**
 * Forgets the user the "newrelic" channel resolved for its records when a unit of work starts or ends, or the user changes.
 *
 * The user is resolved once and reused for every record, so without this an Octane or queue worker
 * would log the next request or job with the previous one's user.
 */
class ForgetLogUser
{
    use ResolvesLoggers;

    public function __construct(protected Container $container) {}

    public function handle(): void
    {
        foreach ($this->loggers() as $logger) {
            foreach ($logger->getProcessors() as $processor) {
                if ($processor instanceof MetadataProcessor) {
                    $processor->reset();
                }
            }
        }
    }
}
