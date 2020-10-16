<?php

/*
 * Dots Mesh Observer (PHP)
 * https://github.com/dotsmesh/dotsmesh-observer-php
 * Free to use under the GPL-3.0 license.
 */

namespace X\API\Endpoints;

use X\API\UserEndpoint;
use X\Utilities;

class UserChangesSignup extends UserEndpoint
{
    public function run()
    {
        $userID = $this->requireValidUserID(false);
        $subscriptions = $this->getArgument('subscriptions', ['array']); // todo validate
        $sessionID = $this->getArgument('sessionID', ['notEmptyString']); // todo validate
        $pushSubscription = $this->getArgument('pushSubscription', ['notEmptyString']); // todo validate

        Utilities::addUser($userID, $subscriptions, $sessionID, $pushSubscription);

        return ['status' => 'ok'];
    }
}
