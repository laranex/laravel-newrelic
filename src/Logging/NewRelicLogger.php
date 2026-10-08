<?php

declare(strict_types=1);

namespace Laranex\LaravelNewrelic\Logging;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;
use Laranex\LaravelNewrelic\Contracts\Agent;
use Laranex\LaravelNewrelic\Contracts\LogTransport;
use Monolog\Handler\BufferHandler;
use Monolog\Logger;

/**
 * Builds the "newrelic" log channel: a Monolog logger that ships records to New Relic Logs.
 *
 * Use it as the "via" class of a custom channel in config/logging.php:
 *
 *     'newrelic' => ['driver' => 'custom', 'via' => NewRelicLogger::class, 'level' => 'debug', 'buffer' => true],
 */
class NewRelicLogger
{
    public function __construct(
        protected Container $container,
        protected Agent $agent,
        protected LogTransport $transport,
    ) {}

    /**
     * @param  array<string, mixed>  $config  The channel configuration: level, bubble, buffer, name
     */
    public function __invoke(array $config): Logger
    {
        $level = $config['level'] ?? 'debug';
        $bubble = (bool) ($config['bubble'] ?? true);
        $name = $config['name'] ?? 'newrelic';

        $handler = new NewRelicHandler(
            $this->transport,
            $this->licenseKey(),
            $this->host(),
            $level,
            $bubble,
        );

        $logger = new Logger(is_string($name) ? $name : 'newrelic');
        $logger->pushHandler(($config['buffer'] ?? true) ? new BufferHandler($handler, 0, $level, $bubble) : $handler);
        $logger->pushProcessor(new NewRelicProcessor($this->agent));
        $logger->pushProcessor(new MetadataProcessor($this->container));

        return $logger;
    }

    protected function licenseKey(): string
    {
        $licenseKey = $this->packageConfig('license_key') ?? $this->agent->licenseKey();

        if ($licenseKey === null) {
            throw new InvalidArgumentException(
                'No New Relic license key: set NEW_RELIC_LICENSE_KEY (or newrelic.license_key) or configure the New Relic PHP agent.',
            );
        }

        return $licenseKey;
    }

    protected function host(): ?string
    {
        return $this->packageConfig('host');
    }

    protected function packageConfig(string $key): ?string
    {
        $value = $this->container->make(ConfigRepository::class)->get('newrelic.'.$key);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
