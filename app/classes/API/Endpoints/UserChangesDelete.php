<?php

/*
 * Dots Mesh Observer (PHP)
 * https://github.com/dotsmesh/dotsmesh-observer-php
 * Free to use under the GPL-3.0 license.
 */

namespace X\API\Endpoints;

use X\API\UserEndpoint;
use X\Utilities;

class UserChangesDelete extends UserEndpoint
{
    public function run()
    {
        $userID = $this->requireValidUserID();

        Utilities::deleteUser($userID);

        return ['status' => 'ok'];
    }
}
