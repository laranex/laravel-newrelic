<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Laranex\LaravelNewrelic\Listeners\NameWebTransaction;
use Laranex\LaravelNewrelic\NewRelicServiceProvider;
use Laranex\LaravelNewrelic\Tests\Fakes\BlogController;
use Laranex\LaravelNewrelic\Tests\Fakes\FakeAgent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Runs the request through the router, the way Octane hands it to the HTTP kernel, and returns it.
 */
function routedRequest(string $uri, string $method = 'GET'): Request
{
    $request = Request::create($uri, $method);

    try {
        app('router')->dispatch($request);
    } catch (NotFoundHttpException) {
        // A request that matches no route keeps a null route, as in a 404 response.
    }

    return $request;
}

function terminateOctaneRequest(?Request $request): void
{
    Event::dispatch('Laravel\Octane\Events\RequestTerminated', [(object) ['request' => $request]]);
}

it('names the transaction after the route name, like the New Relic agent', function (): void {
    Route::get('/blogs/{blog}', [BlogController::class, 'show'])->name('blogs.show');

    terminateOctaneRequest(routedRequest('/blogs/42'));

    expect($this->agent->calls)->toBe([
        ['nameTransaction', 'blogs.show'],
        ['endTransaction', false],
    ]);
});

it('falls back to the controller action for unnamed routes', function (): void {
    Route::get('/blogs/{blog}', [BlogController::class, 'show']);

    terminateOctaneRequest(routedRequest('/blogs/42'));

    expect($this->agent->calls[0])->toBe(['nameTransaction', BlogController::class.'@show']);
});

it('skips the random names given to unnamed cached routes', function (): void {
    Route::get('/blogs/{blog}', [BlogController::class, 'show'])->name('generated::aBcDeFgHiJ');

    terminateOctaneRequest(routedRequest('/blogs/42'));

    expect($this->agent->calls[0])->toBe(['nameTransaction', BlogController::class.'@show']);
});

it('falls back to the HTTP method and the route URI pattern for unnamed closure routes', function (string $method, string $pattern, string $uri, string $name): void {
    Route::match([$method], $pattern, fn (): string => 'ok');

    terminateOctaneRequest(routedRequest($uri, $method));

    expect($this->agent->calls[0])->toBe(['nameTransaction', $name]);
})->with([
    'route parameter' => ['GET', '/blogs/{blog}', '/blogs/42', 'GET /blogs/{blog}'],
    'post' => ['POST', 'blogs', '/blogs', 'POST /blogs'],
    'root' => ['GET', '/', '/', 'GET /'],
]);

it('names requests that matched no route "unknown" instead of their URL', function (): void {
    Route::get('/blogs/{blog}', [BlogController::class, 'show'])->name('blogs.show');

    terminateOctaneRequest(routedRequest('/missing/42'));
    terminateOctaneRequest(Request::create('/never-routed'));
    terminateOctaneRequest(null);
    Event::dispatch('Laravel\Octane\Events\RequestTerminated', [new stdClass]);
    Event::dispatch('Laravel\Octane\Events\RequestTerminated');

    expect(array_values(array_filter($this->agent->calls, fn (array $call): bool => $call[0] === 'nameTransaction')))->toBe([
        ['nameTransaction', 'unknown'],
        ['nameTransaction', 'unknown'],
        ['nameTransaction', 'unknown'],
        ['nameTransaction', 'unknown'],
        ['nameTransaction', 'unknown'],
    ]);
});

it('leaves the naming of regular (non Octane) requests to the agent', function (): void {
    Route::get('/blogs/{blog}', [BlogController::class, 'show'])->name('blogs.show');

    $this->get('/blogs/42')->assertOk()->assertSee('blog 42');

    expect($this->agent->calls)->toBeEmpty();
});

it('does not name transactions when Octane transactions are turned off', function (): void {
    Event::forget('Laravel\Octane\Events\RequestTerminated');
    config()->set('newrelic.transactions.octane', false);
    (new NewRelicServiceProvider($this->app))->boot();

    Route::get('/blogs/{blog}', [BlogController::class, 'show'])->name('blogs.show');

    terminateOctaneRequest(routedRequest('/blogs/42'));

    expect($this->agent->calls)->toBeEmpty();
});

it('passes the name to the agent it was given', function (): void {
    $agent = new FakeAgent;

    (new NameWebTransaction($agent))->handle((object) ['request' => Request::create('/')]);

    expect($agent->calls)->toBe([['nameTransaction', NameWebTransaction::UNKNOWN]]);
});
