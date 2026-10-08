# Laravel New Relic

[![Latest Version on Packagist](https://img.shields.io/packagist/v/laranex/laravel-newrelic.svg?style=flat-square)](https://packagist.org/packages/laranex/laravel-newrelic)
[![Tests](https://img.shields.io/github/actions/workflow/status/laranex/laravel-newrelic/tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/laranex/laravel-newrelic/actions/workflows/tests.yml)
[![Total Downloads](https://img.shields.io/packagist/dt/laranex/laravel-newrelic.svg?style=flat-square)](https://packagist.org/packages/laranex/laravel-newrelic)
[![License](https://img.shields.io/packagist/l/laranex/laravel-newrelic.svg?style=flat-square)](LICENSE.md)

New Relic for Laravel applications: a `newrelic` log channel that ships your logs to New Relic Logs (with logs-in-context linking when the New Relic PHP agent is installed), and listeners that report each Octane request and each queue job as its own APM transaction instead of one endless one. It is a safe no-op when the agent is not installed.

## Documentation

Full documentation lives at **[laranex.vercel.app/laravel-newrelic](https://laranex.vercel.app/laravel-newrelic)**.

## Requirements

- PHP 8.1 or higher
- Laravel 10, 11, 12 or 13
- The [New Relic PHP agent](https://docs.newrelic.com/docs/apm/agents/php-agent/getting-started/introduction-new-relic-php/) for APM transactions and logs in context (optional: logs ship without it)

## Installation

```bash
composer require laranex/laravel-newrelic
```

Optionally publish the configuration file:

```bash
php artisan vendor:publish --tag="newrelic-config"
```

## Usage

Point your logs at the `newrelic` channel the package registers, and give it a license key (the agent's `newrelic.license` INI setting is used when none is set):

```env
LOG_CHANNEL=newrelic
NEW_RELIC_LICENSE_KEY=your-ingest-license-key
```

Then log as usual:

```php
use Illuminate\Support\Facades\Log;

Log::info('Order placed', ['order_id' => $order->id]);
```

Every record is posted to the New Relic Logs API as JSON with a millisecond `timestamp`, your `service` (the app name), `hostname`, the client `ip`, the authenticated `user` and, when the agent is loaded, the `trace.id`, `span.id` and `entity.guid` that link it to its APM transaction. Records are buffered and sent as one batch at the end of the request or job.

To tune the channel, define it yourself in `config/logging.php`:

```php
'newrelic' => [
    'driver' => 'custom',
    'via' => Laranex\LaravelNewrelic\Logging\NewRelicLogger::class,
    'level' => env('LOG_LEVEL', 'debug'),
    'buffer' => true, // false sends each record immediately
],
```

Octane requests and queue jobs are split into separate transactions automatically; turn either off with `newrelic.transactions.octane` / `newrelic.transactions.queue`, and name the APM application with `NEW_RELIC_APP_NAME` when it differs from the agent's `newrelic.appname`.

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](.github/CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](.github/SECURITY.md) on how to report security vulnerabilities.

## Credits

- [Soe Thura](https://github.com/thixpin)
- [Nay Thu Khant](https://github.com/NayThuKhant)
- [All Contributors](../../contributors)
- The formatter, handler and processor derive from the [New Relic Monolog Enricher](https://github.com/newrelic/newrelic-monolog-logenricher-php)

## License

The Apache License 2.0. Please see [License File](LICENSE.md) for more information.

The components derived from the New Relic Monolog Enricher (`NewRelicFormatter`, `NewRelicHandler`, `NewRelicProcessor`) carry New Relic's original copyright notice (Copyright 2019 New Relic Corporation, Apache-2.0).
