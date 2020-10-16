<?php

/*
 * Dots Mesh Observer (PHP)
 * https://github.com/dotsmesh/dotsmesh-observer-php
 * Free to use under the GPL-3.0 license.
 */

namespace X\API\Endpoints;

use X\API\Endpoint;
use X\Utilities;

class HostChangesNotify extends Endpoint
{
    public function run()
    {
        // todo add secret to prevent unautorized access
        $host = $this->getArgument('host', ['notEmptyString']); // validate
        $keys = $this->getArgument('keys', ['array']); // validate
        Utilities::notifyHostObservers($host, $keys);

        return ['status' => 'ok'];
    }
}
