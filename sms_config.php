<?php
require_once __DIR__ . '/db.php';
$channel = db_notification_channel('sms', 'sms_ir');
if (!$channel) {
    return [];
}
$config = $channel['config'];
$config['api_key'] = (string)$channel['secret_value'];
return $config;
