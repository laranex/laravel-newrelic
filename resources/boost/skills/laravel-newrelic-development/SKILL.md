---
name: laravel-newrelic-development
description: >
  Ship Laravel logs to New Relic Logs and report Octane requests and queue jobs as separate New Relic APM transactions with laranex/laravel-newrelic.
license: Apache-2.0
metadata:
  author: Nay Thu Khant
---

# Laravel New Relic

Use this skill when a Laravel application sends its logs to New Relic or runs under the New Relic PHP agent (especially with Octane or queue workers).

## Primary Goal

- send logs to New Relic through the `newrelic` log channel and let the package split long-running processes into one APM transaction per request or job

## Workflow

### 1. Configure

- set `NEW_RELIC_LICENSE_KEY` (an ingest license key); when unset, the agent's `newrelic.license` INI value is used
- set `LOG_CHANNEL=newrelic`, or add `newrelic` to a `stack` channel
- optional: `NEW_RELIC_LOG_HOST` (defaults to the region of the license key), `NEW_RELIC_APP_NAME` (defaults to the agent's `newrelic.appname`)
- publish the config only when the defaults must change: `php artisan vendor:publish --tag="newrelic-config"`

### 2. Tune the channel

- define the channel in `config/logging.php` to change its `level` or set `'buffer' => false` (send each record at once instead of one batch per request):
  `'newrelic' => ['driver' => 'custom', 'via' => Laranex\LaravelNewrelic\Logging\NewRelicLogger::class, 'level' => 'debug', 'buffer' => true]`
- the processors add `service`, `hostname`, `ip`, `user` and, with the agent loaded, `trace.id`/`span.id`/`entity.guid` automatically; put business data in the log context

### 3. Transactions

- Octane requests become web transactions and queue jobs become background transactions without extra code
- turn either off with `newrelic.transactions.octane` / `newrelic.transactions.queue` in `config/newrelic.php`

### 4. Test without the agent

- bind a fake `Laranex\LaravelNewrelic\Contracts\Agent` and `Laranex\LaravelNewrelic\Contracts\LogTransport` in the container to assert transactions and payloads; nothing in the package needs the `newrelic` extension

## Rules, References, and Templates

- no additional resource files for this skill

## Examples

- Ship logs in context: install the New Relic PHP agent, set `NEW_RELIC_LICENSE_KEY` and `LOG_CHANNEL=newrelic`, then `Log::info('Order placed', ['order_id' => $order->id])`
- Errors only: define the `newrelic` channel with `'level' => 'error'` and add it to the `stack` channel next to `daily`

## Anti-patterns

- do not call `newrelic_*` functions directly in application code; resolve `Laranex\LaravelNewrelic\Contracts\Agent` so code keeps working where the agent is absent
- do not use the `NewRelicHandler` with another Monolog formatter; it only accepts `NewRelicFormatter`
- do not put secrets in the log context; every record is sent to New Relic
