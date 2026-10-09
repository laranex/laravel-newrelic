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
    ->expect(['newrelic_start_transaction', 'newrelic_end_transaction', 'newrelic_name_transaction', 'newrelic_background_job', 'newrelic_get_linking_metadata'])
    ->not->toBeUsedIn('Laranex\LaravelNewrelic\Listeners')
    ->not->toBeUsedIn('Laranex\LaravelNewrelic\Logging');

// Helpers defined only by laravel/framework (Illuminate/Foundation/helpers.php); the
// package requires standalone illuminate/* components, so it must not call them.
arch('it only calls helpers that the illuminate/* components define')
    ->expect('Laranex\LaravelNewrelic')
    ->not->toUse([
        '__', 'abort', 'abort_if', 'abort_unless', 'action', 'app', 'app_path', 'asset', 'auth',
        'back', 'base_path', 'bcrypt', 'broadcast', 'broadcast_if', 'broadcast_unless', 'cache',
        'config', 'config_path', 'context', 'cookie', 'csrf_field', 'csrf_token', 'database_path',
        'decrypt', 'defer', 'dispatch', 'dispatch_sync', 'encrypt', 'event', 'fake', 'info',
        'lang_path', 'logger', 'logs', 'method_field', 'mix', 'now', 'old', 'policy',
        'precognitive', 'public_path', 'redirect', 'report', 'report_if', 'report_unless',
        'request', 'rescue', 'resolve', 'resource_path', 'response', 'route', 'secure_asset',
        'secure_url', 'session', 'storage_path', 'to_action', 'to_route', 'today', 'trans',
        'trans_choice', 'uri', 'url', 'validator', 'view',
    ]);
