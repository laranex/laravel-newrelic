<?php

declare(strict_types=1);

namespace Laranex\LaravelNewrelic\Tests\Fakes;

final class BlogController
{
    public function show(string $blog): string
    {
        return 'blog '.$blog;
    }
}
