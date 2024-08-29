<?php

return [
    'newrelic' => [
        'driver' => 'custom',
        'via' => Laranex\LaravelNewrelic\LaravelNewrelicLogger::class,
    ],
];
