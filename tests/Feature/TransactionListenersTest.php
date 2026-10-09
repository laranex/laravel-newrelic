<?php

declare(strict_types=1);

use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Jobs\SyncJob;
use Illuminate\Support\Facades\Event;
use Laranex\LaravelNewrelic\Listeners\EndTransaction;
use Laranex\LaravelNewrelic\Listeners\StartWebTransaction;
use Laranex\LaravelNewrelic\Tests\Fakes\FakeAgent;

function transactionJob(): SyncJob
{
    return new SyncJob(app(), '{"job":"Workbench\\\\Job","data":[]}', 'sync', 'default');
}

it('leaves queue job transactions to the New Relic agent', function (): void {
    Event::dispatch(new JobProcessed('sync', transactionJob()));
    Event::dispatch(new JobExceptionOccurred('sync', transactionJob(), new RuntimeException('boom')));
    Event::dispatch('Laravel\Horizon\Events\JobReleased', [new stdClass]);

    expect($this->agent->calls)->toBeEmpty();
});

it('starts a web transaction when Octane receives a request', function (): void {
    Event::dispatch('Laravel\Octane\Events\RequestReceived', [new stdClass, new stdClass, new stdClass]);

    expect($this->agent->calls)->toBe([
        ['startTransaction', null],
        ['backgroundJob', false],
    ]);
});

it('names and ends the transaction when an Octane request terminates, and ends it when a worker starts', function (): void {
    Event::dispatch('Laravel\Octane\Events\RequestTerminated', [new stdClass]);
    Event::dispatch('Laravel\Octane\Events\WorkerStarting', [new stdClass]);

    expect($this->agent->calls)->toBe([
        ['nameTransaction', 'unknown'],
        ['endTransaction', false],
        ['endTransaction', false],
    ]);
});

it('passes the configured application name to new web transactions', function (): void {
    $agent = new FakeAgent;

    (new StartWebTransaction($agent, 'Shop API'))->handle();
    (new EndTransaction($agent))->handle();

    expect($agent->calls)->toBe([
        ['startTransaction', 'Shop API'],
        ['backgroundJob', false],
        ['endTransaction', false],
    ]);
});

it('does nothing when the transaction listeners are disabled', function (): void {
    Event::forget('Laravel\Octane\Events\RequestReceived');

    Event::dispatch('Laravel\Octane\Events\RequestReceived', [new stdClass]);

    expect($this->agent->calls)->toBeEmpty();
});
