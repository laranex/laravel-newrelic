---
name: laravel-newrelic
description: >
  Ship Laravel logs to New Relic Logs and report each Octane request and queue job as its own New Relic APM transaction with laranex/laravel-newrelic. Use when an application logs to New Relic, runs under the New Relic PHP agent, or tests code that does.
license: Apache-2.0
metadata:
  author: Nay Thu Khant
---

# Laravel New Relic

## When to use

- The application should send its logs to New Relic Logs (with logs in context when the New Relic PHP agent is installed).
- The application runs under the New Relic PHP agent with Octane or queue workers, where one long-running process would otherwise report a single endless transaction.
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
| `NEW_RELIC_QUEUE_TRANSACTIONS` | `newrelic.transactions.queue` | `true` |
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

With `'buffer' => true` the records are sent as one batch at the end of the request or job. Under Octane and queue workers the buffer is also sent after every `RequestTerminated`, `TaskTerminated`, `TickTerminated`, `JobProcessed` and `JobExceptionOccurred` event, so long-running workers don't hold logs until they exit. This works for a `stack` channel that includes `newrelic` too.

### Transactions

Nothing to call: with the agent loaded, each Octane request becomes a web transaction and each processed queue job (including Horizon releases) starts a fresh background transaction, reported to `newrelic.app_name`. Turn either off with `NEW_RELIC_OCTANE_TRANSACTIONS=false` / `NEW_RELIC_QUEUE_TRANSACTIONS=false`.

### Call the agent

Resolve `Laranex\LaravelNewrelic\Contracts\Agent` instead of calling `newrelic_*` functions; every method is a no-op without the extension:

```php
use Laranex\LaravelNewrelic\Contracts\Agent;

$agent = app(Agent::class);

if ($agent->isLoaded()) {
    $agent->endTransaction();
    $agent->startTransaction('Billing');
    $agent->backgroundJob();
}
```

Available methods: `isLoaded()`, `appName()`, `licenseKey()`, `startTransaction(?string $appName = null)`, `endTransaction(bool $ignore = false)`, `backgroundJob(bool $flag = true)`, `linkingMetadata()`.

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
- Flushing the buffer by hand in Octane or queue code; the package already does it after each request and job.
