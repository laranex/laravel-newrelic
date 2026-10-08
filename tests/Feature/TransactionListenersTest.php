<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Laranex\LaravelNewrelic\Listeners\EndTransaction;
use Laranex\LaravelNewrelic\Listeners\RestartBackgroundTransaction;
use Laranex\LaravelNewrelic\Listeners\StartWebTransaction;
use Laranex\LaravelNewrelic\Tests\Fakes\FakeAgent;

it('starts a web transaction when Octane receives a request', function (): void {
    Event::dispatch('Laravel\Octane\Events\RequestReceived', [new stdClass, new stdClass, new stdClass]);

    expect($this->agent->calls)->toBe([
        ['startTransaction', null],
        ['backgroundJob', false],
    ]);
});

it('ends the transaction when an Octane request terminates or a worker starts', function (): void {
    Event::dispatch('Laravel\Octane\Events\RequestTerminated', [new stdClass]);
    Event::dispatch('Laravel\Octane\Events\WorkerStarting', [new stdClass]);

    expect($this->agent->calls)->toBe([
        ['endTransaction', false],
        ['endTransaction', false],
    ]);
});

it('restarts a background transaction after each processed or released queue job', function (): void {
    Event::dispatch('Illuminate\Queue\Events\JobProcessed', [new stdClass]);
    Event::dispatch('Laravel\Horizon\Events\JobReleased', [new stdClass]);

    expect($this->agent->calls)->toBe([
        ['endTransaction', false],
        ['startTransaction', null],
        ['backgroundJob', true],
        ['endTransaction', false],
        ['startTransaction', null],
        ['backgroundJob', true],
    ]);
});

it('passes the configured application name to new transactions', function (): void {
    $agent = new FakeAgent;

    (new StartWebTransaction($agent, 'Shop API'))->handle();
    (new RestartBackgroundTransaction($agent, 'Shop Worker'))->handle();
    (new EndTransaction($agent))->handle();

    expect($agent->calls)->toBe([
        ['startTransaction', 'Shop API'],
        ['backgroundJob', false],
        ['endTransaction', false],
        ['startTransaction', 'Shop Worker'],
        ['backgroundJob', true],
        ['endTransaction', false],
    ]);
});

it('does nothing when the transaction listeners are disabled', function (): void {
    Event::forget('Laravel\Octane\Events\RequestReceived');
    Event::forget('Illuminate\Queue\Events\JobProcessed');

    Event::dispatch('Laravel\Octane\Events\RequestReceived', [new stdClass]);
    Event::dispatch('Illuminate\Queue\Events\JobProcessed', [new stdClass]);

    expect($this->agent->calls)->toBeEmpty();
});
