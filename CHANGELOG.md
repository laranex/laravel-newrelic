# Changelog

All notable changes to `laravel-newrelic` will be documented in this file

## v4.0.0 - Unreleased

Versions 2 and 3 were skipped so that every Laranex package shares the v4 major.

### Changed
- Requires PHP 8.1+ and supports Laravel 10 through 13.
- Rebuilt on the official Laravel package skeleton (Pest, PHPStan, Pint, Testbench workbench, GitHub Actions matrix).
- Requires Monolog 3.6+ (Laravel 10+ ships Monolog 3; 3.6 is the first release without PHP 8.4 implicit-nullable deprecations); the handler, formatter and processors still accept Monolog 2 style array records as well as `LogRecord` objects.
- Every `newrelic_*` call goes through the `Laranex\LaravelNewrelic\Contracts\Agent` singleton (`NewRelicAgent`), which is a no-op when the New Relic extension is not loaded, so the package is safe to install without the agent and the agent can be faked in tests.
- Log payloads are posted through the `Laranex\LaravelNewrelic\Contracts\LogTransport` singleton (`CurlTransport`, with a configurable timeout and retries) and can be swapped in tests.
- The Logs API host is derived from the license key region (`log-api.eu.newrelic.com` for EU keys) or set with `NEW_RELIC_LOG_HOST`; batches are posted as one JSON array.
- The service name, hostname, client IP and authenticated user are added per record, so they are correct on Octane. A user whose `email` cannot be read (e.g. a model with `preventAccessingMissingAttributes()`) is logged with `email: null` instead of breaking the log call.
- The agent's linking metadata `hostname` wins over the PHP hostname so logs link to the right host entity.
- Buffered records are sent after every Octane request, task and tick and after every queue job (processed or failed), instead of only when the worker process exits.
- Delivery failures (an unreachable Logs API after the last attempt, or an HTTP error status) are written to PHP's error log instead of throwing from the log call, so New Relic downtime cannot fail a request or mark a successful queue job as failed. At least one attempt is always made.
- Batches bigger than the Logs API's 1 MB payload limit are split into several requests.
- `NewRelicServiceProvider::OCTANE_EVENTS` maps each Octane event to a list of listeners, in the order they run.
- `illuminate/log`, `illuminate/queue` and `illuminate/routing` are required explicitly (the log channel, the queue log flushing and the Octane transaction naming use them directly), and the service provider uses the application's `configPath()` instead of the `config_path()` helper, which only `laravel/framework` defines.

### Added
- `newrelic.host`, `newrelic.app_name`, `newrelic.transactions.octane` (`NEW_RELIC_OCTANE_TRANSACTIONS`) and `newrelic.transport.{timeout,retries}` (`NEW_RELIC_LOG_TIMEOUT` / `NEW_RELIC_LOG_RETRIES`, default 5 seconds and 3 attempts) configuration, plus a `buffer` option on the log channel.
- Publish tags `newrelic` and `newrelic-config`.
- Octane web transactions are named after the request's route when it terminates (`Listeners\NameWebTransaction`, before `EndTransaction` on `RequestTerminated`): the route name (skipping `generated::` names), then the controller action, then the HTTP method and route URI pattern (`GET /blogs/{blog}`), and `unknown` when no route matched, never the raw URL. Under Octane the agent's own route naming often never runs, so transactions were named after the worker script. Only Octane requests are named, and only while `transactions.octane` is on.
- `Agent::nameTransaction(string $name)`, a guarded `newrelic_name_transaction()` call.
- A full Pest test suite: handler and formatter, processors, agent, log channel, listeners and service provider.

### Removed
- `Laranex\LaravelNewrelic\LaravelNewrelicLogger`, `Handler`, `AbstractHandler`, `Formatter`, `AbstractFormatter`, `Processor`, `EventMap`, `LaravelNewrelic` and the `Listeners\StartNewrelicWebTransaction`, `StopNewrelicWebTransaction`, `RestartNewrelicTransaction` classes.
- The `config/driver.php` file (the `newrelic` log channel is registered by the service provider when the app does not define one).
- The package's own queue job transactions (v1's `RestartNewrelicTransaction` on `JobProcessed` and Horizon's `JobReleased`). The New Relic PHP agent instruments Laravel's queue worker itself and reports each job as its own background transaction named `JobClass (connection)`; ending and restarting the transaction from a queue event listener cut that transaction short and left a duplicate, unnamed one. Buffered logs are still sent after every queue job.

### Upgrading
- Replace `Laranex\LaravelNewrelic\LaravelNewrelicLogger::class` with `Laranex\LaravelNewrelic\Logging\NewRelicLogger::class` in any custom `via` channel.
- The service provider is now `Laranex\LaravelNewrelic\NewRelicServiceProvider` (auto-discovered; update any manual registration).
- The config file moved from `config/laravel-newrelic.php` to `config/newrelic.php`: rename the published file and any `config('laravel-newrelic.*')` reads to `config('newrelic.*')`. `NEW_RELIC_API_KEY` still works, but `NEW_RELIC_LICENSE_KEY` is the new name.
- The handler and formatter were renamed to `Logging\NewRelicHandler` and `Logging\NewRelicFormatter`; `NewRelicHandler` now takes a `LogTransport` and the license key in its constructor instead of `setLicenseKey()`/`setHost()`.
- The listeners were renamed to `Listeners\StartWebTransaction` and `Listeners\EndTransaction`; they depend on the `Contracts\Agent` and no longer call `newrelic_*` directly. `RestartNewrelicTransaction` has no replacement.
- Queue jobs are named by the New Relic agent itself (`JobClass (connection)`), so there is no `NEW_RELIC_QUEUE_TRANSACTIONS` / `newrelic.transactions.queue` setting: remove it from `.env` and any published `config/newrelic.php`.
- A missing license key now throws when the channel is built (Laravel falls back to its emergency logger) instead of posting with `NO_LICENSE_KEY_FOUND`.

## 1.0.0 - 2024-08-30

- Initial release
