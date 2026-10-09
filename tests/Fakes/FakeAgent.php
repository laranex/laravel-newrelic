<?php

declare(strict_types=1);

namespace Laranex\LaravelNewrelic\Tests\Fakes;

use Laranex\LaravelNewrelic\Contracts\Agent;

final class FakeAgent implements Agent
{
    /**
     * @var list<array<int, mixed>>
     */
    public array $calls = [];

    /**
     * @param  array<string, string>  $metadata
     */
    public function __construct(
        public bool $loaded = true,
        public ?string $appName = 'agent-app',
        public ?string $licenseKey = null,
        public array $metadata = [],
    ) {}

    public function isLoaded(): bool
    {
        return $this->loaded;
    }

    public function appName(): ?string
    {
        return $this->appName;
    }

    public function licenseKey(): ?string
    {
        return $this->licenseKey;
    }

    public function startTransaction(?string $appName = null): bool
    {
        $this->calls[] = ['startTransaction', $appName];

        return $this->loaded;
    }

    public function endTransaction(bool $ignore = false): bool
    {
        $this->calls[] = ['endTransaction', $ignore];

        return $this->loaded;
    }

    public function nameTransaction(string $name): bool
    {
        $this->calls[] = ['nameTransaction', $name];

        return $this->loaded;
    }

    public function backgroundJob(bool $flag = true): void
    {
        $this->calls[] = ['backgroundJob', $flag];
    }

    public function linkingMetadata(): array
    {
        return $this->metadata;
    }
}
