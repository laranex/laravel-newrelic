<?php

declare(strict_types=1);

namespace Laranex\LaravelNewrelic\Tests\Fakes;

use Laranex\LaravelNewrelic\Contracts\LogTransport;

final class FakeTransport implements LogTransport
{
    /**
     * @var list<array{url: string, headers: array<string, string>, body: string}>
     */
    public array $requests = [];

    public function send(string $url, array $headers, string $body): void
    {
        $this->requests[] = ['url' => $url, 'headers' => $headers, 'body' => $body];
    }

    /**
     * The decoded JSON body of the request at the given index.
     *
     * @return list<array<string, mixed>>
     */
    public function logs(int $index = 0): array
    {
        /** @var list<array<string, mixed>> $logs */
        $logs = json_decode($this->requests[$index]['body'], true, 512, JSON_THROW_ON_ERROR);

        return $logs;
    }
}
