<?php

/*
 * Dots Mesh Observer (PHP)
 * https://github.com/dotsmesh/dotsmesh-observer-php
 * Free to use under the GPL-3.0 license.
 */

namespace X\API\Endpoints;

use X\API\UserEndpoint;
use X\Utilities;

class UserChangesUpdateSubscriptions extends UserEndpoint
{
    public function run()
    {
        $userID = $this->requireValidUserID();

        $keysToAdd = $this->getArgument('keysToAdd', ['array']); // todo validate
        $keysToRemove = $this->getArgument('keysToRemove', ['array']); // todo validate

        Utilities::modifyUserChangesSubscriptions($userID, $keysToAdd, $keysToRemove);

        return ['status' => 'ok'];
    }
}
