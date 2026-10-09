<?php

declare(strict_types=1);

namespace Laranex\LaravelNewrelic\Logging;

use Laranex\LaravelNewrelic\Contracts\LogTransport;
use Monolog\Handler\Curl\Util;
use RuntimeException;

/**
 * Posts payloads to the Logs API with cURL, retrying transient failures like Monolog's own cURL handlers do.
 *
 * A failed delivery never throws: logging must not break the request or job that logs. The failure
 * (a cURL error after the last attempt, or an HTTP error status) is written to PHP's error log instead.
 */
class CurlTransport implements LogTransport
{
    /**
     * @param  int  $timeout  Seconds to wait for the whole request
     * @param  int  $retries  Attempts before giving up on a failed request (at least one is always made)
     */
    public function __construct(protected int $timeout = 5, protected int $retries = 3) {}

    public function send(string $url, array $headers, string $body): void
    {
        $handle = curl_init();

        if ($handle === false) {
            $this->report($url, 'cURL could not be initialized');

            return;
        }

        $headerLines = [];

        foreach ($headers as $name => $value) {
            $headerLines[] = $name.': '.$value;
        }

        curl_setopt($handle, CURLOPT_URL, $url);
        curl_setopt($handle, CURLOPT_POST, true);
        curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($handle, CURLOPT_POSTFIELDS, $body);
        curl_setopt($handle, CURLOPT_HTTPHEADER, $headerLines);
        curl_setopt($handle, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($handle, CURLOPT_CONNECTTIMEOUT, $this->timeout);

        try {
            Util::execute($handle, max(1, $this->retries));
        } catch (RuntimeException $e) {
            $this->report($url, $e->getMessage());

            return;
        }

        $status = curl_getinfo($handle, CURLINFO_RESPONSE_CODE);

        if ($status >= 400) {
            $this->report($url, "HTTP {$status}");
        }
    }

    /**
     * Write a delivery failure to PHP's error log; the payload and the license key are never included.
     */
    protected function report(string $url, string $reason): void
    {
        error_log("[laravel-newrelic] Could not send logs to {$url}: {$reason}");
    }
}
