<?php

declare(strict_types=1);

namespace Laranex\LaravelNewrelic\Logging;

use Laranex\LaravelNewrelic\Contracts\LogTransport;
use Monolog\Handler\Curl\Util;

/**
 * Posts payloads to the Logs API with cURL, retrying transient failures like Monolog's own cURL handlers do.
 */
class CurlTransport implements LogTransport
{
    /**
     * @param  int  $timeout  Seconds to wait for the whole request
     * @param  int  $retries  Attempts before giving up on a failed request
     */
    public function __construct(protected int $timeout = 5, protected int $retries = 3) {}

    public function send(string $url, array $headers, string $body): void
    {
        $handle = curl_init();

        if ($handle === false) {
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

        Util::execute($handle, $this->retries);
    }
}
