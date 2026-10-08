<?php

declare(strict_types=1);

namespace Laranex\LaravelNewrelic\Logging;

use Monolog\Handler\BufferHandler;

/**
 * Buffers the records of the "newrelic" channel so they can be flushed as one batch after each Octane request or queue job.
 */
class NewRelicBufferHandler extends BufferHandler {}
