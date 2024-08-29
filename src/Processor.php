<?php

/**
 * Copyright [2019] New Relic Corporation. All rights reserved.
 * SPDX-License-Identifier: Apache-2.0
 *
 * This file contains the Processor class for the New Relic Monolog Enricher.
 * When invoked, the class adds contextual metadata to a Monolog record that
 * links the log to the current New Relic application.
 *
 * @author New Relic PHP <php-agent@newrelic.com>
 */

namespace Laranex\LaravelNewrelic;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

/**
 * Adds metadata to log that associates it with current New Relic application
 */
class Processor implements ProcessorInterface
{
    /**
     * Returns the given record with the New Relic linking metadata added
     * if a compatible New Relic extension is loaded, otherwise returns the
     * given record unmodified
     *
     * @param  array  $record  A Monolog record
     * @return array Given record, with New Relic metadata added if available
     */
    public function __invoke(LogRecord $record)
    {
        if (function_exists('newrelic_get_linking_metadata')) {
            $linking_data = newrelic_get_linking_metadata();

            // Ensure trace.id is padded to 32 characters
            if (isset($linking_data['trace.id'])) {
                $linking_data['trace.id'] = str_pad($linking_data['trace.id'], 32, '0', STR_PAD_LEFT);
            }
            $record['extra']['newrelic-context'] = $linking_data;
        }

        return $record;
    }
}
