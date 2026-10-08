<?php

declare(strict_types=1);

namespace Laranex\LaravelNewrelic\Logging;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Request;
use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;
use Throwable;

/**
 * Adds the application name, hostname, client IP and authenticated user to every record.
 *
 * The request and the user are resolved per record, so the metadata stays correct on Octane,
 * where one logger serves many requests.
 */
class MetadataProcessor implements ProcessorInterface
{
    public function __construct(protected Container $container) {}

    /**
     * @param  array<string, mixed>|LogRecord  $record
     * @return ($record is LogRecord ? LogRecord : array<string, mixed>)
     */
    public function __invoke(array|LogRecord $record): array|LogRecord
    {
        $record = Record::withExtra($record, 'service', $this->serviceName());
        $record = Record::withExtra($record, 'hostname', gethostname() ?: 'unknown');

        $request = $this->request();

        if ($request === null) {
            return $record;
        }

        $record = Record::withExtra($record, 'ip', $request->getClientIp());

        $user = $this->user();

        if ($user !== null) {
            $record = Record::withExtra($record, 'user', [
                'id' => $user->getAuthIdentifier(),
                'email' => $this->email($user),
            ]);
        }

        return $record;
    }

    /**
     * Read the user's email without letting a failing accessor or a strict model break logging.
     */
    protected function email(Authenticatable $user): mixed
    {
        try {
            return data_get($user, 'email');
        } catch (Throwable) {
            return null;
        }
    }

    protected function serviceName(): string
    {
        $name = $this->container->make(ConfigRepository::class)->get('app.name', 'Laravel');

        return is_string($name) && $name !== '' ? $name : 'Laravel';
    }

    protected function request(): ?Request
    {
        if (! $this->container->bound('request')) {
            return null;
        }

        return $this->container->make('request');
    }

    protected function user(): ?Authenticatable
    {
        if (! $this->container->bound(AuthFactory::class)) {
            return null;
        }

        try {
            return $this->container->make(AuthFactory::class)->guard()->user();
        } catch (Throwable) {
            return null;
        }
    }
}
