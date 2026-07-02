<?php
require_once __DIR__ . '/db.php';
$event = db_event();
return [
    'blocked' => !(bool)$event['registration_enabled'],
    'auto_date' => $event['registration_starts_at'] !== null || $event['registration_ends_at'] !== null,
    'start_date' => $event['registration_starts_at'] ? date('Y-m-d', strtotime($event['registration_starts_at'])) : '',
    'start_time' => $event['registration_starts_at'] ? date('H:i', strtotime($event['registration_starts_at'])) : '',
    'end_date' => $event['registration_ends_at'] ? date('Y-m-d', strtotime($event['registration_ends_at'])) : '',
    'end_time' => $event['registration_ends_at'] ? date('H:i', strtotime($event['registration_ends_at'])) : '',
];
