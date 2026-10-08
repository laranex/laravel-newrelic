<?php

declare(strict_types=1);

namespace Laranex\LaravelNewrelic\Tests;

use Laranex\LaravelNewrelic\Contracts\Agent;
use Laranex\LaravelNewrelic\Contracts\LogTransport;
use Laranex\LaravelNewrelic\NewRelicServiceProvider;
use Laranex\LaravelNewrelic\Tests\Fakes\FakeAgent;
use Laranex\LaravelNewrelic\Tests\Fakes\FakeTransport;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected FakeAgent $agent;

    protected FakeTransport $transport;

    protected function setUp(): void
    {
        parent::setUp();

        $this->agent = new FakeAgent;
        $this->transport = new FakeTransport;

        $this->app->instance(Agent::class, $this->agent);
        $this->app->instance(LogTransport::class, $this->transport);
    }

    protected function getPackageProviders($app): array
    {
        return [
            NewRelicServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('k', 32)));
        $app['config']->set('app.name', 'Shop');
        $app['config']->set('newrelic.license_key', 'us-license-key');
    }
}
