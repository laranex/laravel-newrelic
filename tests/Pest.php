<?php

declare(strict_types=1);

use Laranex\LaravelNewrelic\Tests\TestCase;
use Monolog\DateTimeImmutable;
use Monolog\Level;
use Monolog\Logger;
use Monolog\LogRecord;

uses(TestCase::class)->in(__DIR__);

/**
 * Builds a Monolog record in the shape of the installed Monolog version (array on 2, LogRecord on 3).
 *
 * @param  array<string, mixed>  $context
 * @param  array<string, mixed>  $extra
 * @return array<string, mixed>|LogRecord
 */
function record(string $message = 'hello', int $level = Logger::INFO, array $context = [], array $extra = [], ?string $formatted = null, ?DateTimeImmutable $datetime = null): array|LogRecord
{
    $datetime ??= new DateTimeImmutable(true);

    if (monologThree()) {
        $record = new LogRecord($datetime, 'app', Level::from($level), $message, $context, $extra);

        if ($formatted !== null) {
            $record->formatted = $formatted;
        }

        return $record;
    }

    return [
        'message' => $message,
        'context' => $context,
        'level' => $level,
        'level_name' => Logger::getLevelName($level),
        'channel' => 'app',
        'datetime' => $datetime,
        'extra' => $extra,
        'formatted' => $formatted,
    ];
}

/**
 * @param  array<string, mixed>|LogRecord  $record
 * @return array<string, mixed>
 */
function extra(array|LogRecord $record): array
{
    return $record instanceof LogRecord ? $record->extra : $record['extra'];
}

function monologThree(): bool
{
    return class_exists(LogRecord::class);
}
