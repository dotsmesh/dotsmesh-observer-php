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
n/ - push notifications keys
 */

use BearFramework\App;
use X\API;
use X\API\EndpointError;
use X\Utilities;

if (!defined('DOTSMESH_OBSERVER_DEV_MODE')) {
    define('DOTSMESH_OBSERVER_DEV_MODE', false);
}

if (!defined('DOTSMESH_OBSERVER_LOG_TYPES')) {
    define('DOTSMESH_OBSERVER_LOG_TYPES', []); // 'host-changes-subscribe', 'user-push-notification'
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

$host = strtolower(substr($app->request->host, 9)); // remove dotsmesh.

if (array_search($host, DOTSMESH_OBSERVER_HOSTS) === false) {
    http_response_code(503);
    echo 'Unsupported host!';
    exit;
}

define('DOTSMESH_OBSERVER_HOST_INTERNAL', $host);

$app->enableErrorHandler(['logErrors' => true, 'displayErrors' => DOTSMESH_OBSERVER_DEV_MODE]);

$hostMD5 = md5($host);

$dataDir = DOTSMESH_OBSERVER_DATA_DIR . '/' . $hostMD5;
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
    ->add('/', function (App\Request $request) {
        if ($request->query->exists('pushkey')) {
            $keys = Utilities::getPushNotificationsVapidKeys();
            $response = new App\Response((string) $keys['publicKey']);
            $response->headers->set($response->headers->make('Content-Type', 'text/plain'));
            $response->headers->set($response->headers->make('Access-Control-Allow-Origin', '*'));
            $response->headers->set($response->headers->make('X-Robots-Tag', 'noindex,nofollow'));
            return $response;
        }
        if ($request->query->exists('ffff')) {
            $auth = [
                'VAPID' => [
                    'subject' => 'dotsmesh.' . DOTSMESH_OBSERVER_HOST_INTERNAL,
                    'publicKey' => 'BPT6YRpv8Ttkcn+H5823wXLydOBjBqy6t8EJ9gOO691Unep4uDgdFQ8yWS5Z96AfgbKQIipzp1cHy1y09Q4t4DA=',
                    'privateKey' => 'MIGHAgEAMBMGByqGSM49AgEGCCqGSM49AwEHBG0wawIBAQQgiTzsEFCKqm2HJlXhCwMHq00dX7D9hz11zGR11YKoGkihRANCAAT0+mEab/E7ZHJ/h+fNt8Fy8nTgYwasurfBCfYDjuvdVJ3qeLg4HRUPMlkuWfegH4GykCIqc6dXB8tctPUOLeAw'
                ],
            ];
            $webPush = new \Minishlink\WebPush\WebPush($auth);
            $subscription = '{"endpoint":"https://fcm.googleapis.com/fcm/send/cLS0qiQtjBg:APA91bGWhG3uZWoyO--v2JxJ2is7lYS_sOEvcuaThbylkLyKAMVdO0QIDNfe2D9b0MNdgOudXg-ywvmlE52FebNZZXWAEvER-2FRP1kjddgVqIQ3cD66i4E2aFDkt1fYYpcD_f2-osBX","expirationTime":null,"keys":{"p256dh":"BI4SEny5H7_4OZ6nen5fbXqGabfkYeHlJpv0SclMvXWyhTuCEPy_IJC5vtF9-_09s_GLqg4L8hWyQMVzDY8rlUg","auth":"9V9FYB4KGbpoR87C5NBvvg"}}';
            $webPush->queueNotification(\Minishlink\WebPush\Subscription::create(json_decode($subscription, true)));
            foreach ($webPush->flush() as $index => $report) {
                echo $report->getReason();
            }
            exit;
        }
    })
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
                        'host.changes.notify' => API\Endpoints\HostChangesNotify::class
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
