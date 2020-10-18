<?php

/*
 * Dots Mesh Observer (PHP)
 * https://github.com/dotsmesh/dotsmesh-observer-php
 * Free to use under the GPL-3.0 license.
 */

namespace X;

use BearFramework\App;

class Utilities
{
    /**
     * 
     * @var array
     */
    static private $queuedPushNotifications = [];

    /**
     * Makes a request to another Dots Mesh server.
     *
     * @param string $method
     * @param string $url
     * @param array $data
     * @return mixed
     * @throws \Exception
     */
    static function makeServerRequest(string $method, string $url, array $data)
    {
        $ch = curl_init();
        if ($method === 'POST') {
            $json = json_encode($data);
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
            curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Content-Length: ' . strlen($json)]);
        } elseif ($method === 'GET') {
            curl_setopt($ch, CURLOPT_URL, $url . '?' . http_build_query($data));
        } else {
            throw new \Exception('Unsupported method (' . $method . ')!');
        }
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Dots Mesh Observer');
        if (DOTSMESH_OBSERVER_DEV_MODE) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        }
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        $result = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($httpCode === 200) {
            curl_close($ch);
            $resultData = json_decode($result, true);
            if (is_array($resultData) && isset($resultData['status'])) {
                if ($resultData['status'] === 'ok') {
                    return isset($resultData['result']) ? $resultData['result'] : null;
                } else if ($resultData['status'] === 'error') {
                    // todo error
                }
            }
            throw new \Exception('Response error: ' . $result);
        } else {
            $exceptionMessage = $httpCode . ', ' . curl_error($ch);
            curl_close($ch);
            throw new \Exception($exceptionMessage);
        }
    }

    /**
     * Generates a random base 62 string.
     *
     * @param integer $length
     * @return string
     */
    static function generateRandomBase62String(int $length): string
    {
        $chars = array_flip(str_split('qwertyuiopasdfghjklzxcvbnmQWERTYUIOPASDFGHJKLZXCVBNM0123456789'));
        $result = [];
        for ($i = 0; $i < $length; $i++) {
            $result[] = array_rand($chars);
        }
        return implode($result);
    }

    /**
     * Return the hash of the value specified.
     *
     * @param string $type The hash type.
     * @param string $value The value to hash.
     * @return string Returns the hash of the value specified.
     */
    static function getHash(string $type, string $value): string
    {
        if ($type === 'SHA-512') {
            return '0:' . base64_encode(hash('sha512', $value, true));
        } else if ($type === 'SHA-256') {
            return '1:' . base64_encode(hash('sha256', $value, true));
        } else if ($type === 'SHA-512-10') {
            return '2' . substr(base64_encode(hash('sha512', $value, true)), 0, 9); // -1 because of the prefix
        } else {
            throw new \Exception('Unsupported hash type (' . $type . ')!');
        }
    }

    /**
     * Compress a value into a string.
     *
     * @param string $name
     * @param mixed $value
     * @return string
     */
    static function pack(string $name, $value): string
    {
        return $name . ':' . json_encode($value);
    }

    /**
     * Uncompress a value.
     *
     * @param string $value
     * @return array Returns an array in the following format ['name'=>..., 'value'=>...]
     */
    static function unpack(string $value): array
    {
        $parts = explode(':', $value, 2);
        return ['name' => isset($parts[0], $parts[1]) ? $parts[0] : null, 'value' => isset($parts[1]) ? json_decode($parts[1], true) : null];
    }

    /**
     * Checks if the host name provided is valid.
     *
     * @param string $host
     * @return boolean Returns TRUE if the host is valid.
     */
    static function isHost(string $host): bool
    {
        return filter_var('http://' . $host . '/', FILTER_VALIDATE_URL) !== false;
    }

    /**
     * 
     * @param string $userID
     * @return string
     */
    static private function getUserDataKey(string $userID): string
    {
        return 'u/' . md5($userID);
    }

    /**
     * 
     * @param string $userID
     * @return boolean
     */
    static function userExists(string $userID): bool
    {
        $app = App::get();
        return $app->data->exists(self::getUserDataKey($userID));
    }

    /**
     * 
     * @param string $userID
     * @return array|null
     */
    static function getUserData(string $userID): ?array
    {
        $app = App::get();
        $data = $app->data->getValue(self::getUserDataKey($userID));
        if ($data !== null) {
            $data = Utilities::unpack($data);
            if ($data['name'] === 'w') {
                return $data['value'];
            } else {
                throw new \Exception('');
            }
        }
        return null;
    }

    /**
     * 
     * @param string $userID
     * @param array $data
     * @return void
     */
    static function setUserData(string $userID, array $data)
    {
        $app = App::get();
        $app->data->setValue(self::getUserDataKey($userID), Utilities::pack('w', $data));
    }

    /**
     * 
     * @param string $userID
     * @param array $subscriptions
     * @param array $sessionID
     * @param array $pushSubscription
     * @return void
     */
    static function addUser(string $userID, array $subscriptions = [], string $sessionID = '', string $pushSubscription = '')
    {
        self::deleteUser($userID);
        $data = [
            'i' => $userID,
            'd' => time()
        ];
        if (!empty($subscriptions)) {
            $data['s'] = $subscriptions;
        }
        if (strlen($sessionID) > 0 && strlen($pushSubscription) > 0) {
            $data['p'] = [$sessionID => $pushSubscription];
        }
        self::setUserData($userID, $data);
        self::updateUserChangesSubscriptions($userID);
    }

    /**
     * 
     * @param string $userID
     * @return void
     */
    static function deleteUser(string $userID)
    {
        self::modifyUserChangesSubscriptions($userID, [], ['*']);
        $app = App::get();
        $app->data->delete(self::getUserDataKey($userID));
    }

    /**
     * 
     * @param string $userID
     * @param string $sessionID
     * @param string $pushSubscription
     * @return void
     */
    static function addUserPushSubscription(string $userID, string $sessionID, string $pushSubscription)
    {
        $userData = self::getUserData($userID);
        if ($userData !== null) {
            // todo lock
            $hasChange = false;
            if (!isset($userData['p']) || !is_array($userData['p'])) {
                $userData['p'] = [];
            }
            if (!isset($userData['p'][$sessionID]) || $userData['p'][$sessionID] !== $pushSubscription) {
                $userData['p'][$sessionID] = $pushSubscription;
                $hasChange = true;
            }
            if ($hasChange) {
                self::setUserData($userID, $userData);
            }
            // todo unlock
        }
    }

    /**
     * 
     * @param string $userID
     * @param string $sessionID
     * @return void
     */
    static function deleteUserPushSubscription(string $userID, string $sessionID): void
    {
        $userData = self::getUserData($userID);
        if ($userData !== null) {
            // todo lock
            if (isset($userData['p']) && is_array($userData['p']) && isset($userData['p'][$sessionID])) {
                unset($userData['p'][$sessionID]);
                if (empty($userData['p'])) {
                    self::deleteUser($userID);
                } else {
                    self::setUserData($userID, $userData);
                }
            }
            // todo unlock
        }
    }

    /**
     * 
     * @param string $userID
     * @return array
     */
    static function getUserPushSubscriptions(string $userID): array
    {
        $userData = self::getUserData($userID);
        if ($userData !== null) {
            if (isset($userData['p']) && is_array($userData['p'])) {
                return $userData['p'];
            }
        }
        return [];
    }

    /**
     * 
     * @param string $userID
     * @param array $keysToAdd
     * @param array $keysToRemove
     * @return void
     */
    static function modifyUserChangesSubscriptions(string $userID, array $keysToAdd, array $keysToRemove)
    {
        $userData = self::getUserData($userID);
        if ($userData !== null) {
            // todo lock
            $hasChange = false;
            if (!isset($userData['s']) || !is_array($userData['s'])) {
                $userData['s'] = [];
            }
            if (array_search('*', $keysToRemove) !== false) {
                if (!empty($userData['s'])) {
                    $userData['s'] = [];
                    $hasChange = true;
                }
            } else {
                foreach ($keysToRemove as $host => $keys) {
                    if (is_string($host) && is_array($keys)) {
                        $host = trim(strtolower($host));
                        if (self::isHost($host)) {
                            if (isset($userData['s'][$host])) {
                                foreach ($keys as $key) {
                                    if (is_string($key)) {
                                        $index = array_search($key, $userData['s'][$host]);
                                        if ($index !== false) {
                                            unset($userData['s'][$host][$index]);
                                            if (empty($userData['s'][$host])) {
                                                unset($userData['s'][$host]);
                                            } else {
                                                $userData['s'][$host] = array_values(array_unique($userData['s'][$host]));
                                            }
                                            $hasChange = true;
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
            foreach ($keysToAdd as $host => $keys) {
                if (is_string($host) && is_array($keys)) {
                    $host = trim(strtolower($host));
                    if (self::isHost($host)) {
                        foreach ($keys as $key) {
                            if (is_string($key)) {
                                if (!isset($userData['s'][$host])) {
                                    $userData['s'][$host] = [];
                                }
                                $userData['s'][$host][] = $key;
                                $userData['s'][$host] = array_values(array_unique($userData['s'][$host]));
                                $hasChange = true;
                            }
                        }
                    }
                }
            }
            if ($hasChange) {
                self::setUserData($userID, $userData);
                self::updateUserChangesSubscriptions($userID);
            }
            // todo unlock
        }
    }

    /**
     * Add a user id to the push notifications queue.
     *
     * @param string $userID The user id to add.
     * @param mixed $payload The payload to send.
     * @return void
     */
    static function queuePushNotification(string $userID, $payload = null): void
    {
        self::$queuedPushNotifications[] = [$userID, $payload];
    }

    /**
     * Send the queued push notifications.
     * 
     * @return void
     */
    static function sendQueuedPushNotifications(): void
    {
        if (empty(self::$queuedPushNotifications)) {
            return;
        }
        foreach (self::$queuedPushNotifications as $queuedPushNotificationData) {
            $userID = $queuedPushNotificationData[0];
            $payload = $queuedPushNotificationData[1];
            $subscriptions = Utilities::getUserPushSubscriptions($userID);
            foreach ($subscriptions as $sessionID => $subscription) {
                $subscription = self::unpack($subscription);
                if ($subscription['name'] === 'q') {
                    $data = $subscription['value']; // 0 - subscription, 1 - vapid public key, 2 - vapid private key
                    if (isset($data[0], $data[1], $data[2]) && is_array($data[0]) && is_string($data[1]) && is_string($data[2])) {
                        $webPush = new \Minishlink\WebPush\WebPush([
                            'VAPID' => [
                                'subject' => 'dotsmesh.' . DOTSMESH_OBSERVER_HOST_INTERNAL,
                                'publicKey' => $data[1],
                                'privateKey' => $data[2]
                            ]
                        ]);
                        $result = $webPush->sendOneNotification(\Minishlink\WebPush\Subscription::create($data[0]), $payload !== null ? self::pack('', $payload) : null);
                        self::log('user-push-notification', $userID . ' ' . ($result->isSuccess() ? 'success' : 'fail') . ' ' . $result->getReason());
                        if ($result->isSubscriptionExpired()) {
                            self::deleteUserPushSubscription($userID, $sessionID);
                        }
                    }
                }
            }
        }
        self::$queuedPushNotifications = [];
    }

    /**
     * Notifies users that have subscribed to changes from the specific host.
     *
     * @param string $host
     * @param array $keys
     * @return void
     */
    static function notifyHostObservers(string $host, array $keys)
    {
        $hostData = self::getObserverHostData($host);
        $usersToNotify = [];
        foreach ($keys as $key) {
            if (is_string($key)) {
                if (isset($hostData['k'][$key])) {
                    foreach ($hostData['k'][$key] as $userIDIndex) {
                        if (isset($hostData['u'][$userIDIndex])) {
                            $userID = $hostData['u'][$userIDIndex];
                            if (!isset($usersToNotify[$userID])) {
                                $usersToNotify[$userID] = [];
                            }
                            $usersToNotify[$userID][] = $key;
                        }
                    }
                }
            }
        }
        foreach ($usersToNotify as $userID => $userKeys) {
            // todo Maybe send keys to improve performance?
            Utilities::queuePushNotification($userID);
        }
    }

    /**
     * 
     * @param string $userID
     * @return string
     */
    static private function getObserverUserKeysDataKey(string $userID): string
    {
        $app = App::get();
        return 'o/u/' . md5($userID);
    }

    /**
     * 
     * @param string $userID
     * @return array
     */
    static function getObserverUserKeys(string $userID): array
    {
        $app = App::get();
        $data = $app->data->getValue(self::getObserverUserKeysDataKey($userID));
        if ($data === null) {
            return [];
        } else {
            $data = Utilities::unpack($data);
            if ($data['name'] === 'w') {
                return $data['value'];
            }
        }
        throw new \Exception();
    }

    /**
     * 
     * @param string $userID
     * @param array $data
     * @return void
     */
    static function setObserverUserKeys(string $userID, array $data)
    {
        $dataKey = self::getObserverUserKeysDataKey($userID);
        $app = App::get();
        if (empty($data)) {
            $app->data->delete($dataKey);
        } else {
            $app->data->setValue($dataKey, Utilities::pack('w', $data));
        }
    }

    /**
     * 
     * @param string $host
     * @return string
     */
    static private function getObserverHostDataKey(string $host): string
    {
        return 'o/h/' . md5($host);
    }

    /**
     * 
     * @param string $host
     * @return array
     */
    static function getObserverHostData(string $host): array
    {
        $app = App::get();
        $hostData = $app->data->getValue(self::getObserverHostDataKey($host));
        if ($hostData === null) {
            return ['k' => [], 'u' => []]; // k - keys, u - users
        } else {
            $hostData = Utilities::unpack($hostData);
            if ($hostData['name'] === 'q') {
                return $hostData['value'];
            }
        }
        throw new \Exception();
    }

    /**
     * 
     * @param string $host
     * @param array $data
     * @return void
     */
    static function setObserverHostData(string $host, array $data)
    {
        $dataKey = self::getObserverHostDataKey($host);
        $app = App::get();
        if (empty($data) || empty($data['k'])) {
            $app->data->delete($dataKey);
        } else {
            $app->data->setValue($dataKey, Utilities::pack('q', $data));
        }
    }

    /**
     * 
     * @param string $userID
     * @param array $keysToAdd
     * @param array $keysToRemove
     * @return void
     */
    static function updateUserChangesSubscriptions(string $userID)
    {
        $userData = self::getUserData($userID);
        $userKeys = isset($userData['s']) && is_array($userData['s']) ? $userData['s']  : [];

        $observedUserKeys = self::getObserverUserKeys($userID);
        $flattenKeys = function ($keysData) {
            $result = [];
            foreach ($keysData as $host => $keys) {
                foreach ($keys as $key) {
                    $result[] = $host . ':' . $key;
                }
            }
            return $result;
        };
        $unflattenKeys = function ($flattenKeys) {
            $result = [];
            foreach ($flattenKeys as $flattenKey) {
                $parts = explode(':', $flattenKey, 2);
                if (!isset($result[$parts[0]])) {
                    $result[$parts[0]] = [];
                }
                $result[$parts[0]][] = $parts[1];
            }
            return $result;
        };
        $flattenUserKeys = $flattenKeys($userKeys);
        $flattenObservedUserKeys = $flattenKeys($observedUserKeys);
        $addedKeys = $unflattenKeys(array_diff($flattenUserKeys, $flattenObservedUserKeys));
        $removedKeys = $unflattenKeys(array_diff($flattenObservedUserKeys, $flattenUserKeys));

        $notifyAddedKeys = [];
        $notifyRemovedKeys = [];
        if (!empty($addedKeys) || !empty($removedKeys)) {
            $hosts = array_unique(array_merge(array_keys($addedKeys), array_keys($removedKeys)));
            foreach ($hosts as $host) {
                $hasChange = false;
                // todo host lock
                $hostData = self::getObserverHostData($host);
                $userIDIndex = array_search($userID, $hostData['u']);
                if ($userIDIndex === false) {
                    $hostData['u'][] = $userID;
                    $userIDIndex = array_search($userID, $hostData['u']);
                }
                if (isset($addedKeys[$host])) {
                    foreach ($addedKeys[$host] as $addedKey) {
                        if (!isset($hostData['k'][$addedKey])) {
                            $hostData['k'][$addedKey] = [];
                            if (!isset($notifyAddedKeys[$host])) {
                                $notifyAddedKeys[$host] = [];
                            }
                            $notifyAddedKeys[$host][] = $addedKey;
                        }
                        if (array_search($userIDIndex, $hostData['k'][$addedKey]) === false) {
                            $hostData['k'][$addedKey][] = $userIDIndex;
                            $hasChange = true;
                        }
                    }
                }
                if (isset($removedKeys[$host])) {
                    foreach ($removedKeys[$host] as $removedKey) {
                        if (isset($hostData['k'][$removedKey])) {
                            $index = array_search($userIDIndex, $hostData['k'][$removedKey]);
                            if ($index !== false) {
                                $hasChange = true;
                                unset($hostData['k'][$removedKey][$index]);
                                $hostData['k'][$removedKey] = array_values($hostData['k'][$removedKey]);
                            }
                            if (empty($hostData['k'][$removedKey])) {
                                if (!isset($notifyRemovedKeys[$host])) {
                                    $notifyRemovedKeys[$host] = [];
                                }
                                $notifyRemovedKeys[$host][] = $removedKey;
                                unset($hostData['k'][$removedKey]);
                            }
                        }
                    }
                    // Todo clean up $hostData['u'] - maybe in a cleanup task?
                }
                if ($hasChange) {
                    self::setObserverHostData($host, $hostData);
                }
            }
        }

        self::setObserverUserKeys($userID, $userKeys);

        if (!empty($notifyAddedKeys) || !empty($notifyRemovedKeys)) {
            $hosts = array_unique(array_merge(array_keys($notifyAddedKeys), array_keys($notifyRemovedKeys)));
            foreach ($hosts as $host) {
                $keysToAdd = isset($notifyAddedKeys[$host]) ? $notifyAddedKeys[$host] : [];
                $keysToRemove = isset($notifyRemovedKeys[$host]) ? $notifyRemovedKeys[$host] : [];
                $args = [
                    'host' => DOTSMESH_OBSERVER_HOST_INTERNAL,
                    'keysToAdd' => $keysToAdd,
                    'keysToRemove' => $keysToRemove
                ];
                try {
                    $result = self::makeServerRequest('POST', 'https://dotsmesh.' . $host . '/?host&api', ['method' => 'host.changes.subscription', 'args' => $args, 'options' => []]);
                } catch (\Exception $e) {
                    $result = $e->getMessage();
                }
                self::log('host-changes-subscription', $userID . ' ' . $host . ' ' . json_encode($keysToAdd) . ' ' . json_encode($keysToRemove) . ' ' . json_encode($result));
            }
        }
    }

    /**
     * 
     * @param string $type
     * @param string $text
     * @return void
     */
    static function log(string $type, string $text)
    {
        if (array_search($type, DOTSMESH_OBSERVER_LOG_TYPES) !== false) {
            $app = App::get();
            $app->logs->log($type, DOTSMESH_OBSERVER_HOST_INTERNAL . ' | ' . $text);
        }
    }
}
