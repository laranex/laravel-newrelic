<?php

declare(strict_types=1);

namespace Laranex\LaravelNewrelic\Listeners;

use Laranex\LaravelNewrelic\Contracts\Agent;

/**
 * Ends the current New Relic transaction and starts a fresh background one, so each queue job is its own transaction.
 */
class RestartBackgroundTransaction
{
    public function __construct(protected Agent $agent, protected ?string $appName = null) {}

    public function handle(): void
    {
        $this->agent->endTransaction();
        $this->agent->startTransaction($this->appName);
        $this->agent->backgroundJob(true);
    }
}
