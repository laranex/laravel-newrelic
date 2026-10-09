<?php

declare(strict_types=1);

use Laranex\LaravelNewrelic\Contracts\LogTransport;
use Laranex\LaravelNewrelic\Logging\CurlTransport;

it('implements the log transport contract', function (): void {
    expect(new CurlTransport)->toBeInstanceOf(LogTransport::class);
});

it('writes a failed delivery to the error log instead of throwing', function (int $retries): void {
    $errorLog = (string) tempnam(sys_get_temp_dir(), 'newrelic-error-log');
    // Set inside the test body: PHPUnit points error_log at its own capture file before each test runs.
    $previous = ini_set('error_log', $errorLog);

    try {
        (new CurlTransport(1, $retries))->send('http://127.0.0.1:1/log/v1', ['X-License-Key' => 'secret-key'], '[{"message":"private"}]');

        $logged = (string) file_get_contents($errorLog);
    } finally {
        ini_set('error_log', (string) $previous);
        @unlink($errorLog);
    }

    expect($logged)->toContain('[laravel-newrelic] Could not send logs to http://127.0.0.1:1/log/v1: Curl error')
        ->not->toContain('secret-key')
        ->not->toContain('private');
})->with([
    'one attempt' => [1],
    'no attempts configured' => [0],
]);
