<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | License Key
    |--------------------------------------------------------------------------
    |
    | The New Relic license (ingest) key used to ship logs to the Logs API.
    | When empty, the key configured for the New Relic PHP agent
    | (the "newrelic.license" INI setting) is used instead.
    |
    */

    'license_key' => env('NEW_RELIC_LICENSE_KEY', env('NEW_RELIC_API_KEY')),

    /*
    |--------------------------------------------------------------------------
    | Logs API Host
    |--------------------------------------------------------------------------
    |
    | Leave empty to pick the host from the license key's region
    | (log-api.newrelic.com for US keys, log-api.eu.newrelic.com for EU keys).
    |
    */

    'host' => env('NEW_RELIC_LOG_HOST'),

    /*
    |--------------------------------------------------------------------------
    | Application Name
    |--------------------------------------------------------------------------
    |
    | The APM application that Octane request and queue job transactions are
    | reported to. When empty, the agent's "newrelic.appname" INI setting
    | is used.
    |
    */

    'app_name' => env('NEW_RELIC_APP_NAME'),

    /*
    |--------------------------------------------------------------------------
    | Transactions
    |--------------------------------------------------------------------------
    |
    | Split long-running processes into one APM transaction per unit of work:
    | one web transaction per Octane request, and one background transaction
    | per processed queue job.
    |
    */

    'transactions' => [
        'octane' => true,
        'queue' => true,
    ],

];
