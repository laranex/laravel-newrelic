# Laravel New Relic

Laravel New Relic is a package that provides a custom log channel for New Relic. It also includes listeners to split Octane transactions into individual job and event transactions.

## Why we use Laravel Newrelic Instead of New Relic Monolog Enricher?

The [New Relic Monolog Enricher](https://github.com/newrelic/newrelic-monolog-logenricher-php) is not compatible with Monolog 3.0, and it has not been updated to address this issue. To overcome this limitation, I created this package to provide a custom log channel specifically designed for New Relic.

## Installation

Before installing the Laravel New Relic package, make sure you have the [New Relic PHP agent](https://docs.newrelic.com/docs/agents/php-agent/getting-started/introduction-new-relic-php) installed on your server.

To install the Laravel New Relic package, follow these steps:

1. Install the package via Composer:

   ```bash
   composer require your-vendor/laravel-newrelic
   ```
2. Change the log channel in your `.env` file:

   ```env
   LOG_CHANNEL=newrelic
   ```

3. (Optional) Configure your New Relic application name and license key in your `.env` file:

   ```env
   NEW_RELIC_API_KEY="your_license_key"
   ```

   These values will be automatically picked up by the package.


## Changelog

For detailed information on recent changes, please see the [CHANGELOG](CHANGELOG.md).

## Contributing

We welcome contributions! Please see the [CONTRIBUTING](CONTRIBUTING.md) guide for more details.

## Security

If you discover any security issues, please report them in accordance with our security policy outlined in the [CONTRIBUTING](CONTRIBUTING.md) guide.

## Credits

- [New Relic Monolog Enricher](https://github.com/newrelic/newrelic-monolog-logenricher-php)

## Contributors

- [Soe Thura](https://github.com/thixpin)
- [Paing Soe Htike](https://github.com/paisoedev)

## Versions

| Version       | Release Date |
|---------------|--------------|
| [1.0.0](#100) | 2024-08-31   |

### 1.0.0

- Initial release

## License

This project is primarily licensed under the Apache License, Version 2.0. For more details, please refer to the [License File](LICENSE.md).

Please note that this package includes components based on the New Relic Monolog Enricher, which are subject to the New Relic License. All original code contributed to this package, excluding the New Relic Monolog Enricher components, is also made available under the [MIT License](MIT-LICENSE.md).

Users are free to use, modify, and distribute the original code under either the Apache License 2.0 or the MIT License, depending on their preference, with the exception of the New Relic Monolog Enricher components, which must be used in accordance with the New Relic License.

