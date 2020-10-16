<?php

/*
 * Dots Mesh Observer (PHP)
 * https://github.com/dotsmesh/dotsmesh-observer-php
 * Free to use under the GPL-3.0 license.
 */

namespace X\API;

use X\Utilities;

class UserEndpoint extends Endpoint
{

    /**
     * 
     * @param boolean $mustExist
     * @return string
     */
    protected function requireValidUserID(bool $mustExist = true): string
    {
        $id = $this->getOption('userID', ['notEmptyString']);
        if ($mustExist) {
            if (!Utilities::userExists($id)) {
                throw new EndpointError('userNotFound', 'The user specified does not exists!');
            }
        }
        return $id;
    }
}
