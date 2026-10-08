<?php

declare(strict_types=1);

namespace Laranex\LaravelNewrelic\Contracts;

/**
 * The New Relic PHP agent API used by this package. Every call is a no-op when the agent is not loaded.
 */
interface Agent
{
    /**
     * Whether the New Relic PHP agent (the "newrelic" extension) is loaded.
     */
    public function isLoaded(): bool;

    /**
     * The application name configured for the agent ("newrelic.appname"), when any.
     */
    public function appName(): ?string;

    /**
     * The license key configured for the agent ("newrelic.license"), when any.
     */
    public function licenseKey(): ?string;

    /**
     * Start a new transaction for the given (or the agent's configured) application.
     */
    public function startTransaction(?string $appName = null): bool;

    /**
     * End the current transaction and report it, or discard it when $ignore is true.
     */
    public function endTransaction(bool $ignore = false): bool;

    /**
     * Mark the current transaction as a background job (true) or a web transaction (false).
     */
    public function backgroundJob(bool $flag = true): void;

    /**
     * The linking metadata (entity.guid, hostname, trace.id, span.id, …) for logs in context.
     *
     * @return array<string, string>
     */
    public function linkingMetadata(): array;
}
