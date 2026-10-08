<?php

declare(strict_types=1);

use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Jobs\SyncJob;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Laranex\LaravelNewrelic\Listeners\FlushLogs;
use Laranex\LaravelNewrelic\NewRelicServiceProvider;

function syncJob(): SyncJob
{
    return new SyncJob(app(), '{"job":"Workbench\\\\Job","data":[]}', 'sync', 'default');
}

it('sends the buffered records after each processed queue job', function (): void {
    Log::channel('newrelic')->info('First job');

    expect($this->transport->requests)->toBeEmpty();

    event(new JobProcessed('sync', syncJob()));

    Log::channel('newrelic')->info('Second job');
    event(new JobProcessed('sync', syncJob()));

    expect($this->transport->requests)->toHaveCount(2)
        ->and(array_column($this->transport->logs(0), 'message'))->toBe(['First job'])
        ->and(array_column($this->transport->logs(1), 'message'))->toBe(['Second job']);
});

it('sends the buffered records when a queue job throws', function (): void {
    Log::channel('newrelic')->error('Job failed');

    event(new JobExceptionOccurred('sync', syncJob(), new RuntimeException('boom')));

    expect(array_column($this->transport->logs(), 'message'))->toBe(['Job failed']);
});

it('sends the buffered records of a stack channel after each Octane request', function (): void {
    config()->set('logging.channels.app', ['driver' => 'stack', 'channels' => ['newrelic']]);

    Log::channel('app')->info('In request');
    event('Laravel\Octane\Events\RequestTerminated');

    expect(array_column($this->transport->logs(), 'message'))->toBe(['In request']);
});

it('listens to the Octane and queue events that end a unit of work', function (): void {
    foreach (NewRelicServiceProvider::FLUSH_EVENTS as $event) {
        expect(Event::hasListeners($event))->toBeTrue("No listener for {$event}");
    }
});

it('does nothing before any log channel is resolved', function (): void {
    $this->app->forgetInstance('log');
    Log::clearResolvedInstances();
    $this->app->offsetUnset('log');

    app(FlushLogs::class)->handle();

    expect($this->transport->requests)->toBeEmpty();
});
