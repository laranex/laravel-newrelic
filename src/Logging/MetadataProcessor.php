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
use Monolog\ResettableInterface;
use Throwable;

/**
 * Adds the application name, hostname and authenticated user's id to every record, plus the client IP
 * and the user's email when they are opted in.
 *
 * The request is resolved per record, so the metadata stays correct on Octane, where one logger serves
 * many requests. The user is resolved once and reused until reset(), which the package calls when an
 * Octane request or queue job starts or ends and when a user logs in or out.
 */
class MetadataProcessor implements ProcessorInterface, ResettableInterface
{
    protected bool $userResolved = false;

    protected ?Authenticatable $user = null;

    /**
     * @param  bool  $includeIp  Add the client IP of the current request ("newrelic.logs.include.ip")
     * @param  bool  $includeUserEmail  Add the authenticated user's email ("newrelic.logs.include.user_email")
     */
    public function __construct(
        protected Container $container,
        protected bool $includeIp = false,
        protected bool $includeUserEmail = false,
    ) {}

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

        if ($this->includeIp) {
            $record = Record::withExtra($record, 'ip', $request->getClientIp());
        }

        $user = $this->user();

        if ($user !== null) {
            $attributes = ['id' => $user->getAuthIdentifier()];

            if ($this->includeUserEmail) {
                $attributes['email'] = $this->email($user);
            }

            $record = Record::withExtra($record, 'user', $attributes);
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

    /**
     * Forget the resolved user, so the next record resolves it again.
     */
    public function reset(): void
    {
        $this->userResolved = false;
        $this->user = null;
    }

    protected function user(): ?Authenticatable
    {
        if (! $this->userResolved) {
            $this->user = $this->resolveUser();
            $this->userResolved = true;
        }

        return $this->user;
    }

    protected function resolveUser(): ?Authenticatable
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
