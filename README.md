# Laravel New Relic

[![Latest Version on Packagist](https://img.shields.io/packagist/v/laranex/laravel-newrelic.svg?style=flat-square)](https://packagist.org/packages/laranex/laravel-newrelic)
[![Tests](https://github.com/laranex/laravel-newrelic/actions/workflows/tests.yml/badge.svg)](https://github.com/laranex/laravel-newrelic/actions/workflows/tests.yml)
[![Total Downloads](https://img.shields.io/packagist/dt/laranex/laravel-newrelic.svg?style=flat-square)](https://packagist.org/packages/laranex/laravel-newrelic)
[![License](https://img.shields.io/packagist/l/laranex/laravel-newrelic.svg?style=flat-square)](LICENSE.md)

New Relic for Laravel: ship logs to New Relic Logs and report each Octane request as a named transaction. Built for humans and AI agents.

## Why Laravel New Relic

New Relic's Monolog enricher does not support Monolog 3, which current Laravel versions use, and the New Relic PHP agent records a whole Octane worker as one endless transaction. Laravel New Relic fixes both with one log channel and a few event listeners:

- **Logs straight to New Relic Logs.** The `newrelic` log channel posts your records to the Logs API with your license key, no log forwarder or agent needed. The region's host is picked from the key.
- **Batched and safe.** Records are buffered and sent as one batch after each Octane request or queue job, with short timeouts and batches split under the 1 MB payload limit. A failed delivery goes to PHP's error log and never breaks your app.
- **Logs in context.** With the New Relic PHP agent loaded, each record carries the agent's linking metadata (`entity.guid`, `trace.id`, `span.id`), so logs show up next to their APM transaction.
- **Useful attributes.** Every record has your app name as `service`, the hostname and the authenticated user's id. The client IP and the user's email are personal data, so they are opt-in.
- **One transaction per Octane request.** Each Octane request becomes its own web transaction, named after its route name, controller action or route pattern, never the raw URL. See [Transactions](https://laranex.vercel.app/laravel-newrelic/usage#transactions).
- **Works without the agent.** Every agent call does nothing when the `newrelic` extension is not loaded, and the agent and the log transport are contracts you can fake in tests.

## Installation

```bash
composer require laranex/laravel-newrelic
```

Requirements: PHP ^8.1 with ext-curl, Laravel 10 to 13, Monolog ^3.6, and the New Relic PHP agent (optional).

## Documentation

The full documentation lives at **[laranex.vercel.app/laravel-newrelic](https://laranex.vercel.app/laravel-newrelic)**:

- [Introduction](https://laranex.vercel.app/laravel-newrelic/introduction): why Laravel New Relic and its requirements
- [Installation](https://laranex.vercel.app/laravel-newrelic/installation): installing, setting the license key and switching to the newrelic log channel
- [AI Agent Skill](https://laranex.vercel.app/laravel-newrelic/ai-agent-skill): the agent skill for Claude Code, Codex, Cursor and other coding agents
- [Usage](https://laranex.vercel.app/laravel-newrelic/usage): the log channel, the Logs API host, what gets logged, Octane transactions and queue jobs
- [Upgrading](https://laranex.vercel.app/laravel-newrelic/upgrading): upgrading from v1 to v4

## License

Apache License 2.0, by Soe Thura and Nay Thu Khant; see [LICENSE.md](LICENSE.md). `NewRelicFormatter`, `NewRelicHandler` and `NewRelicProcessor` derive from the [New Relic Monolog Enricher](https://github.com/newrelic/newrelic-monolog-logenricher-php) and carry its notice (Copyright 2019 New Relic Corporation, Apache-2.0).
