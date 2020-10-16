<?php

/*
 * Dots Mesh Observer (PHP)
 * https://github.com/dotsmesh/dotsmesh-observer-php
 * Free to use under the GPL-3.0 license.
 */

namespace X\API\Endpoints;

use X\API\UserEndpoint;
use X\Utilities;

class UserChangesAddPushSubscription extends UserEndpoint
{
    public function run()
    {
        $userID = $this->requireValidUserID();

        $sessionID = $this->getArgument('sessionID', ['notEmptyString']);
        $pushSubscription = $this->getArgument('pushSubscription', ['notEmptyString']);
        Utilities::addUserPushSubscription($userID, $sessionID, $pushSubscription);

        return ['status' => 'ok'];
    }
}
