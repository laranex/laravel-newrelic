<?php

declare(strict_types=1);

namespace Laranex\LaravelNewrelic;

use Laranex\LaravelNewrelic\Contracts\Agent;

/**
 * Talks to the New Relic PHP agent through its global functions; safe to use when the agent is not installed.
 */
class NewRelicAgent implements Agent
{
    public function isLoaded(): bool
    {
        return extension_loaded('newrelic');
    }

    public function appName(): ?string
    {
        return $this->ini('newrelic.appname');
    }

    public function licenseKey(): ?string
    {
        return $this->ini('newrelic.license');
    }

    public function startTransaction(?string $appName = null): bool
    {
        if (! $this->isLoaded() || ! function_exists('newrelic_start_transaction')) {
            return false;
        }

        $appName ??= $this->appName();

        if ($appName === null) {
            return false;
        }

        return (bool) newrelic_start_transaction($appName);
    }

    public function endTransaction(bool $ignore = false): bool
    {
        if (! $this->isLoaded() || ! function_exists('newrelic_end_transaction')) {
            return false;
        }

        return (bool) newrelic_end_transaction($ignore);
    }

    public function backgroundJob(bool $flag = true): void
    {
        if (! $this->isLoaded() || ! function_exists('newrelic_background_job')) {
            return;
        }

        newrelic_background_job($flag);
    }

    public function linkingMetadata(): array
    {
        if (! $this->isLoaded() || ! function_exists('newrelic_get_linking_metadata')) {
            return [];
        }

        $linking = [];

        foreach (newrelic_get_linking_metadata() as $key => $value) {
            if (is_string($key) && is_scalar($value)) {
                $linking[$key] = (string) $value;
            }
        }

        return $linking;
    }

    private function ini(string $key): ?string
    {
        $value = ini_get($key);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
