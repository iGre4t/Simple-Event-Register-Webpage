<?php
require_once __DIR__ . '/db.php';
$channel = db_notification_channel('telegram', 'admin_bot');
if (!$channel) {
    return [];
}
$config = $channel['config'];
$config['bot_token'] = (string)$channel['secret_value'];
return $config;
