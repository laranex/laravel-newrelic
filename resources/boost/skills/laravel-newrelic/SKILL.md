---
name: laravel-newrelic
description: >
  Ship Laravel logs to New Relic Logs and report each Octane request as its own named New Relic APM transaction with laranex/laravel-newrelic. Use when an application logs to New Relic, runs under the New Relic PHP agent, or tests code that does.
license: Apache-2.0
metadata:
  author: Nay Thu Khant
---

# Laravel New Relic

## When to use

- The application should send its logs to New Relic Logs (with logs in context when the New Relic PHP agent is installed).
- The application runs under the New Relic PHP agent with Octane, where one long-running worker would otherwise report a single endless transaction.
- The application runs queue workers and logs to New Relic: the buffered logs are sent after every job instead of when the worker exits.
- Code needs New Relic agent calls that keep working where the `newrelic` extension is not loaded (local, CI).

## Install

```bash
composer require laranex/laravel-newrelic
```

The service provider is auto-discovered. Publish the config only when a default must change:

```bash
php artisan vendor:publish --tag="newrelic-config"
```

## Configure

```env
LOG_CHANNEL=newrelic
NEW_RELIC_LICENSE_KEY=your-ingest-license-key
```

| Env variable | Config key | Default |
| --- | --- | --- |
| `NEW_RELIC_LICENSE_KEY` (falls back to `NEW_RELIC_API_KEY`) | `newrelic.license_key` | the agent's `newrelic.license` INI value |
| `NEW_RELIC_LOG_HOST` | `newrelic.host` | picked from the license key's region (`log-api.newrelic.com`, `log-api.eu.newrelic.com`) |
| `NEW_RELIC_APP_NAME` | `newrelic.app_name` | the agent's `newrelic.appname` INI value |
| `NEW_RELIC_OCTANE_TRANSACTIONS` | `newrelic.transactions.octane` | `true` |
| `NEW_RELIC_LOG_TIMEOUT` (seconds) | `newrelic.transport.timeout` | `5` |
| `NEW_RELIC_LOG_RETRIES` (attempts) | `newrelic.transport.retries` | `3` |

## Use

### Log to New Relic

The package registers a `newrelic` log channel unless `config/logging.php` already defines one. Use it as the default channel, add it to a `stack`, or log to it directly:

```php
use Illuminate\Support\Facades\Log;

Log::info('Order placed', ['order_id' => $order->id]);
Log::channel('newrelic')->error('Payment failed', ['order_id' => $order->id]);
```

Every record gets `service` (the app name), `hostname`, the client `ip`, the authenticated `user` and, when the agent is loaded, the `trace.id`, `span.id` and `entity.guid` that link it to its APM transaction. Put business data in the log context.

### Tune the channel

Define the channel yourself to change the level or turn off buffering:

```php
// config/logging.php
'newrelic' => [
    'driver' => 'custom',
    'via' => Laranex\LaravelNewrelic\Logging\NewRelicLogger::class,
    'level' => env('LOG_LEVEL', 'debug'),
    'buffer' => true, // false sends each record immediately
],
```

With `'buffer' => true` the records are sent as one batch at the end of the request or job. Under Octane and queue workers the buffer is also sent after every `RequestTerminated`, `TaskTerminated`, `TickTerminated`, `JobProcessed` and `JobExceptionOccurred` event, so long-running workers don't hold logs until they exit. This works for a `stack` channel that includes `newrelic` too. Batches over the Logs API's 1 MB limit are split into several requests, and a failed delivery is written to PHP's error log instead of throwing, so logging never breaks a request or job.

### Transactions

Nothing to call: with the agent loaded, each Octane request becomes a web transaction reported to `newrelic.app_name`. Turn it off with `NEW_RELIC_OCTANE_TRANSACTIONS=false`.

Queue jobs need nothing from the package: the New Relic PHP agent instruments Laravel's queue worker itself and reports each job as its own background transaction named `JobClass (connection)` (for example `App\Jobs\SendInvoice (redis)`), with the job's exception recorded when it fails. The package only flushes the buffered logs after each job.

Octane web transactions are named after the matched route when the request terminates: the route name, else the controller action (`App\Http\Controllers\BlogController@show`), else the method and URI pattern (`GET /blogs/{blog}`); requests without a route are named `unknown`. Give routes names for readable transaction names. PHP-FPM requests keep the agent's own naming.

New Relic's PHP agent officially supports only Apache mod_php and PHP-FPM: Octane servers are not supported yet (ZTS builds such as FrankenPHP are unsupported, Swoole is on New Relic's roadmap), so verify Octane transactions in New Relic before relying on them.

### Call the agent

Resolve `Laranex\LaravelNewrelic\Contracts\Agent` instead of calling `newrelic_*` functions; every method is a no-op without the extension:

```php
use Laranex\LaravelNewrelic\Contracts\Agent;

$agent = app(Agent::class);

if ($agent->isLoaded()) {
    $agent->nameTransaction('billing.export');
}
```

Available methods: `isLoaded()`, `appName()`, `licenseKey()`, `startTransaction(?string $appName = null)`, `endTransaction(bool $ignore = false)`, `nameTransaction(string $name)`, `backgroundJob(bool $flag = true)`, `linkingMetadata()`.

## Test your app

Swap the container bindings for fakes; nothing needs the `newrelic` extension:

```php
use Illuminate\Support\Facades\Log;
use Laranex\LaravelNewrelic\Contracts\LogTransport;

final class FakeTransport implements LogTransport
{
    public array $requests = [];

    public function send(string $url, array $headers, string $body): void
    {
        $this->requests[] = compact('url', 'headers', 'body');
    }
}

it('ships the order log to New Relic', function () {
    config()->set('newrelic.license_key', 'test-key');
    $this->app->instance(LogTransport::class, $transport = new FakeTransport);

    Log::channel('newrelic')->info('Order placed');
    Log::channel('newrelic')->getLogger()->reset(); // send the buffered batch now

    expect($transport->requests)->toHaveCount(1);
});
```

Fake `Laranex\LaravelNewrelic\Contracts\Agent` the same way to assert transaction calls. Bind the fakes before the `newrelic` channel is first resolved.

## Avoid

- Resolving the `newrelic` channel without a license key (no `NEW_RELIC_LICENSE_KEY` and no agent `newrelic.license`): it throws an `InvalidArgumentException`. Don't make it the default channel in environments without a key.
- Calling `newrelic_*` functions directly; use the `Agent` contract so code runs where the agent is absent.
- Giving `NewRelicHandler` another formatter; it only accepts `NewRelicFormatter`.
- Putting secrets or personal data in the log context; every record is sent to New Relic.
- Naming Octane transactions after the request URL (`$request->path()`); IDs in URLs create unbounded transaction names. The package already names them after the route.
- Flushing the buffer by hand in Octane or queue code; the package already does it after each request and job.
- Starting or ending transactions around queue jobs (for example in `JobProcessed` listeners or job middleware); the agent already gives each job its own named transaction, and restarting it splits the job into duplicate, unnamed transactions.
