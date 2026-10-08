<?php

declare(strict_types=1);

namespace Laranex\LaravelNewrelic\Listeners;

use Laranex\LaravelNewrelic\Contracts\Agent;

/**
 * Ends the current New Relic transaction when an Octane worker starts or a request terminates.
 */
class EndTransaction
{
    public function __construct(protected Agent $agent) {}

    public function handle(): void
    {
        $this->agent->endTransaction();
    }
}
