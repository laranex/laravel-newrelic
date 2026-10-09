<?php

declare(strict_types=1);

namespace Laranex\LaravelNewrelic\Listeners;

use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Laranex\LaravelNewrelic\Contracts\Agent;

/**
 * Names the web transaction of a finished Octane request after its route, the way the New Relic agent names
 * Laravel routes: the route name, then the controller action, then the HTTP method and the route URI pattern.
 * Requests that matched no route are named "unknown", never after their URL, to keep the number of names bounded.
 */
class NameWebTransaction
{
    /**
     * The name given to requests that did not match a route (404s, Octane routes, early middleware responses).
     */
    public const UNKNOWN = 'unknown';

    public function __construct(protected Agent $agent) {}

    /**
     * Handle Octane's RequestTerminated event, which runs before the transaction ends.
     */
    public function handle(?object $event = null): void
    {
        $request = $event !== null && property_exists($event, 'request') ? $event->request : null;

        $this->agent->nameTransaction($request instanceof Request ? $this->name($request) : self::UNKNOWN);
    }

    /**
     * The transaction name for the given request.
     */
    public function name(Request $request): string
    {
        $route = $request->route();

        if (! $route instanceof Route) {
            return self::UNKNOWN;
        }

        $name = $route->getName();

        // Routes cached without a name get a random "generated::" name, which is useless for grouping.
        if (is_string($name) && $name !== '' && ! str_starts_with($name, 'generated::')) {
            return $name;
        }

        $controller = $route->getAction('controller');

        if (is_string($controller) && $controller !== '') {
            return $controller;
        }

        return $request->getMethod().' /'.ltrim($route->uri(), '/');
    }
}
