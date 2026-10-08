<?php

declare(strict_types=1);

use Pest\ArchPresets\Php;

// The php and security presets ship with Pest 3+; the PHP 8.1 lane runs Pest 2.
if (class_exists(Php::class)) {
    arch()->preset()->php();

    arch()->preset()->security();
}

arch('it will not use dd(), ddd(), env(), or exit()')
    ->expect(['dd', 'ddd', 'env', 'exit'])
    ->each->not->toBeUsed();

arch('the package source declares strict types')
    ->expect('Laranex\LaravelNewrelic')
    ->toUseStrictTypes();

arch('every newrelic_* call goes through the agent')
    ->expect(['newrelic_start_transaction', 'newrelic_end_transaction', 'newrelic_background_job', 'newrelic_get_linking_metadata'])
    ->not->toBeUsedIn('Laranex\LaravelNewrelic\Listeners')
    ->not->toBeUsedIn('Laranex\LaravelNewrelic\Logging');
