<?php

declare(strict_types=1);

namespace Laranex\LaravelNewrelic\Contracts;

/**
 * Delivers a JSON payload to the New Relic Logs API.
 */
interface LogTransport
{
    /**
     * @param  non-empty-string  $url
     * @param  array<string, string>  $headers
     */
    public function send(string $url, array $headers, string $body): void;
}
