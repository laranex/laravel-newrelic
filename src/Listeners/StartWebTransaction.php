<?php

declare(strict_types=1);

namespace Laranex\LaravelNewrelic\Listeners;

use Laranex\LaravelNewrelic\Contracts\Agent;

/**
 * Starts a New Relic web transaction when Octane receives a request.
 */
class StartWebTransaction
{
    public function __construct(protected Agent $agent, protected ?string $appName = null) {}

    public function handle(): void
    {
        $this->agent->startTransaction($this->appName);
        $this->agent->backgroundJob(false);
    }
}
