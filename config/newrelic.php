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
    | The APM application that Octane request transactions are reported to.
    | When empty, the agent's "newrelic.appname" INI setting is used.
    |
    */

    'app_name' => env('NEW_RELIC_APP_NAME'),

    /*
    |--------------------------------------------------------------------------
    | Transactions
    |--------------------------------------------------------------------------
    |
    | Split Octane workers into one APM web transaction per request, named
    | after its route. Queue jobs need no setting: the New Relic PHP agent
    | reports each job as its own background transaction.
    |
    */

    'transactions' => [
        'octane' => (bool) env('NEW_RELIC_OCTANE_TRANSACTIONS', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Logs API Transport
    |--------------------------------------------------------------------------
    |
    | The number of seconds to wait for the Logs API (used for both the
    | connection and the whole request) and the number of attempts made
    | before a failed request is given up.
    |
    */

    'transport' => [
        'timeout' => (int) env('NEW_RELIC_LOG_TIMEOUT', 5),
        'retries' => (int) env('NEW_RELIC_LOG_RETRIES', 3),
    ],

];
