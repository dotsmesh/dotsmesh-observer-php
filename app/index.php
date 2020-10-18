<?php

/*
 * Dots Mesh Observer (PHP)
 * https://github.com/dotsmesh/dotsmesh-observer-php
 * Free to use under the GPL-3.0 license.
 */

/**
Observer data structure
u/[id] - user data
o/h/[host] - observed host data
o/u/[id] - user observer data
*/

use BearFramework\App;
use X\API;
use X\API\EndpointError;
use X\Utilities;

if (!defined('DOTSMESH_OBSERVER_DEV_MODE')) {
    define('DOTSMESH_OBSERVER_DEV_MODE', false);
}

if (!defined('DOTSMESH_OBSERVER_LOG_TYPES')) {
    define('DOTSMESH_OBSERVER_LOG_TYPES', []); // 'host-changes-subscription', 'user-push-notification'
}

if (!defined('DOTSMESH_OBSERVER_DATA_DIR')) {
    http_response_code(503);
    echo 'The DOTSMESH_OBSERVER_DATA_DIR constant is required!';
    exit;
}

if (!defined('DOTSMESH_OBSERVER_LOGS_DIR')) {
    http_response_code(503);
    echo 'The DOTSMESH_OBSERVER_LOGS_DIR constant is required!';
    exit;
}

if (!defined('DOTSMESH_OBSERVER_HOSTS')) {
    http_response_code(503);
    echo 'The DOTSMESH_OBSERVER_HOSTS constant is required!';
    exit;
}

require __DIR__ . '/../vendor/autoload.php';

$app = new App();

$host = $app->request->host;
$host = substr($host, 0, 9) === 'dotsmesh.' ? strtolower(substr($host, 9)) : null;

if (array_search($host, DOTSMESH_OBSERVER_HOSTS) === false) {
    http_response_code(503);
    echo 'Unsupported host!';
    exit;
}

define('DOTSMESH_OBSERVER_HOST_INTERNAL', $host);

$app->enableErrorHandler(['logErrors' => true, 'displayErrors' => DOTSMESH_OBSERVER_DEV_MODE]);

$dataDir = DOTSMESH_OBSERVER_DATA_DIR . '/' . md5($host);
if (!is_dir($dataDir)) {
    mkdir($dataDir);
}
$app->data->useFileDriver($dataDir);

$app->logs->useFileLogger(DOTSMESH_OBSERVER_LOGS_DIR);

$app->addons
    ->add('ivopetkov/locks-bearframework-addon');

$app->classes
    ->add('X\API\*', __DIR__ . '/classes/API/*.php')
    ->add('X\Utilities', __DIR__ . '/classes/Utilities.php');

$app->routes
    ->add('OPTIONS /', function (App\Request $request) {
        $method = strtoupper($request->headers->getValue('Access-Control-Request-Method'));
        if ($method === 'POST') {
            $response = new App\Response();
            $response->headers->set($response->headers->make('Access-Control-Allow-Origin', '*'));
            $response->headers->set($response->headers->make('Access-Control-Allow-Methods', 'POST,GET,OPTIONS'));
            $response->headers->set($response->headers->make('Access-Control-Allow-Headers', 'Content-Type,Cache-Control,Accept'));
            $response->headers->set($response->headers->make('Access-Control-Max-Age', '864000'));
            $response->headers->set($response->headers->make('X-Robots-Tag', 'noindex,nofollow'));
            return $response;
        }
    })
    ->add('POST /', function (App\Request $request) {
        if ($request->query->exists('api')) {
            $response = null;
            $body = file_get_contents('php://input');
            $requestData = json_decode($body, true);
            if (is_array($requestData) && isset($requestData['method'], $requestData['args'], $requestData['options']) && is_string($requestData['method']) && is_array($requestData['args']) && is_array($requestData['options'])) {
                try {
                    $methods = [
                        'user.changes.signup' => API\Endpoints\UserChangesSignup::class,
                        'user.changes.addPushSubscription' => API\Endpoints\UserChangesAddPushSubscription::class,
                        'user.changes.delete' => API\Endpoints\UserChangesDelete::class,
                        'user.changes.updateSubscriptions' => API\Endpoints\UserChangesUpdateSubscriptions::class,
                        'host.changes.notify' => API\Endpoints\HostChangesNotify::class,
                        'utilities.getPushKeys' => API\Endpoints\UtilitiesGetPushKeys::class
                    ];
                    $method = $requestData['method'];
                    if (isset($methods[$method])) {
                        $class = $methods[$method];
                        $result = (new $class($requestData['args'], $requestData['options']))->run();
                        $response = new App\Response\JSON(json_encode([
                            'status' => 'ok',
                            'result' => $result
                        ]));
                    } else {
                        $response = new App\Response\JSON(json_encode([
                            'status' => 'error',
                            'code' => 'invalidEndpoint',
                            'message' => 'Invalid method!'
                        ]));
                    }
                } catch (EndpointError $e) {
                    $response = new App\Response\JSON(json_encode([
                        'status' => 'error',
                        'code' => $e->code,
                        'message' => $e->message
                    ]));
                }
            } else {
                $response = new App\Response\JSON(json_encode([
                    'status' => 'invalidRequestData'
                ]));
            }
            if ($response !== null) {
                $response->headers->set($response->headers->make('Access-Control-Allow-Origin', '*'));
                $response->headers->set($response->headers->make('X-Robots-Tag', 'noindex,nofollow'));
                return $response;
            }
        }
    });

$app->addEventListener('sendResponse', function () {
    Utilities::sendQueuedPushNotifications();
});

$app->run();
