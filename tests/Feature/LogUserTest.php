<?php

declare(strict_types=1);

use Illuminate\Auth\Events\Login;
use Illuminate\Auth\GenericUser;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Jobs\SyncJob;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Laranex\LaravelNewrelic\Listeners\ForgetLogUser;
use Laranex\LaravelNewrelic\NewRelicServiceProvider;

/**
 * Binds a guard that returns the given users, one per call, and fails on any further call.
 */
function guardReturning(?GenericUser ...$users): void
{
    $guard = Mockery::mock(Guard::class);
    $guard->shouldReceive('user')->times(count($users))->andReturn(...$users);
    $auth = Mockery::mock(AuthFactory::class);
    $auth->shouldReceive('guard')->andReturn($guard);

    app()->instance(AuthFactory::class, $auth);
}

it('resolves the user once per Octane request and never shows it to the next request', function (): void {
    guardReturning(new GenericUser(['id' => 1]), new GenericUser(['id' => 2]));

    event('Laravel\Octane\Events\RequestReceived');
    Log::channel('newrelic')->info('First request');
    Log::channel('newrelic')->info('First request again');
    event('Laravel\Octane\Events\RequestTerminated');

    event('Laravel\Octane\Events\RequestReceived');
    Log::channel('newrelic')->info('Second request');
    Log::channel('newrelic')->getLogger()->reset();

    expect(array_merge(
        array_map(fn (array $log): mixed => $log['user'], $this->transport->logs(0)),
        array_map(fn (array $log): mixed => $log['user'], $this->transport->logs(1)),
    ))->toBe([['id' => 1], ['id' => 1], ['id' => 2]]);
});

it('never shows one queue job the previous job user', function (): void {
    guardReturning(new GenericUser(['id' => 1]), null);
    $job = new SyncJob(app(), '{"job":"Workbench\\\\Job","data":[]}', 'sync', 'default');

    event(new JobProcessing('sync', $job));
    Log::channel('newrelic')->info('First job');
    event(new JobProcessed('sync', $job));

    event(new JobProcessing('sync', $job));
    Log::channel('newrelic')->info('Second job');
    event(new JobProcessed('sync', $job));

    expect($this->transport->logs(0)[0]['user'])->toBe(['id' => 1])
        ->and($this->transport->logs(1)[0])->not->toHaveKey('user');
});

it('resolves the user again after a login', function (): void {
    $user = new GenericUser(['id' => 7]);
    guardReturning(null, $user);

    Log::channel('newrelic')->info('Guest');
    event(new Login('web', $user, false));
    Log::channel('newrelic')->info('Signed in');
    Log::channel('newrelic')->getLogger()->reset();

    expect($this->transport->logs()[0])->not->toHaveKey('user')
        ->and($this->transport->logs()[1]['user'])->toBe(['id' => 7]);
});

it('listens to the events that start or end a unit of work or change the user', function (): void {
    foreach (NewRelicServiceProvider::FORGET_USER_EVENTS as $event) {
        expect(Event::getRawListeners()[$event] ?? [])->toContain(ForgetLogUser::class);
    }
});

it('does nothing before any log channel is resolved', function (): void {
    $this->app->forgetInstance('log');
    Log::clearResolvedInstances();
    $this->app->offsetUnset('log');

    app(ForgetLogUser::class)->handle();

    expect($this->app->resolved('log'))->toBeFalse();
});
