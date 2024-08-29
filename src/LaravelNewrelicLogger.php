<?php

namespace Laranex\LaravelNewrelic;

use Auth;
use Illuminate\Http\Request;
use Monolog\Handler\BufferHandler;
use Monolog\Logger;
use Monolog\LogRecord;

class LaravelNewrelicLogger
{
    protected $request;

    public function __construct(?Request $request = null)
    {
        $this->request = $request;
    }

    public function __invoke(array $config)
    {
        // add the new relic logger
        $log = new Logger('newrelic');
        $log->pushProcessor(new Processor);
        $handler = new Handler;

        // Optional - if you don't have the new relic php agent installed.
        $licenseKey = config('laravel-newrelic.license_key');
        if ($licenseKey && $licenseKey !== '') {
            $handler->setLicenseKey($licenseKey);
        }
        // using the BufferHandler improves the performance by batching the log
        // messages to the end of the request
        $log->pushHandler(new BufferHandler($handler));

        // foreach ($log->getHandlers() as $handler) {
        //     $handler->pushProcessor([$this, 'includeMetaData']);
        // }
        $log->pushProcessor([$this, 'includeMetaData']);

        return $log;
    }

    // lets add some extra metadata to every request
    public function includeMetaData(LogRecord $record)
    {
        // set the service or app name to the record
        $record['extra']['service'] = config('app.name');

        // set the hostname to record so we know host this was created on
        $record['extra']['hostname'] = gethostname();

        // check to see if we have a request
        if ($this->request) {
            $record['extra'] += [
                'ip' => $this->request->getClientIp(),
            ];

            // get the authenticated user
            $user = Auth::user();

            // add the user information
            if ($user) {
                $record['extra']['user'] = [
                    'id' => $user->id ?? null,
                    'email' => $user->email ?? 'guest',
                ];
            }
        }

        return $record;
    }
}
