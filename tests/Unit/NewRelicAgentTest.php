<?php

declare(strict_types=1);

use Laranex\LaravelNewrelic\Contracts\Agent;
use Laranex\LaravelNewrelic\NewRelicAgent;

it('implements the agent contract', function (): void {
    expect(new NewRelicAgent)->toBeInstanceOf(Agent::class);
});

it('is a safe no-op when the New Relic extension is not loaded', function (): void {
    $agent = new NewRelicAgent;

    expect($agent->isLoaded())->toBeFalse()
        ->and($agent->startTransaction('Shop'))->toBeFalse()
        ->and($agent->startTransaction())->toBeFalse()
        ->and($agent->endTransaction())->toBeFalse()
        ->and($agent->endTransaction(true))->toBeFalse()
        ->and($agent->linkingMetadata())->toBe([]);

    $agent->backgroundJob();
    $agent->backgroundJob(false);
})->skip(extension_loaded('newrelic'), 'The New Relic extension is loaded.');

it('reads the app name and license key from the agent INI settings', function (): void {
    $agent = new NewRelicAgent;

    expect($agent->appName())->toBe(ini_get('newrelic.appname') ?: null)
        ->and($agent->licenseKey())->toBe(ini_get('newrelic.license') ?: null);
});
