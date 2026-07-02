<?php
require_once __DIR__ . '/db.php';
$event = db_event();
return db_ticket_prices((int)$event['id']);
