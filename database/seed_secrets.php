<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/db.php';
load_local_environment();
$pdo = db();

$pdo->beginTransaction();
try {
    $stmt = $pdo->prepare(
        'UPDATE payment_providers SET secret_value = ? WHERE provider = ?'
    );
    $stmt->execute([(string)getenv('ZARINPAL_MERCHANT_ID'), 'zarinpal']);

    $stmt = $pdo->prepare(
        'UPDATE notification_channels SET secret_value = ? WHERE channel = ? AND name = ?'
    );
    $stmt->execute([(string)getenv('SMSIR_API_KEY'), 'sms', 'sms_ir']);
    $stmt->execute([(string)getenv('TELEGRAM_BOT_TOKEN'), 'telegram', 'admin_bot']);

    $username = getenv('EVENT_ADMIN_USER') ?: 'admin';
    $password = getenv('EVENT_ADMIN_PASSWORD');
    if (!is_string($password) || $password === '') {
        throw new RuntimeException('EVENT_ADMIN_PASSWORD is missing.');
    }
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare(
        'INSERT INTO admin_users (username, password_hash, role, enabled)
         VALUES (?, ?, "admin", 1)
         ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash), enabled = 1'
    );
    $stmt->execute([$username, $hash]);
    $pdo->commit();
    echo "Database credentials and admin account seeded.\n";
} catch (Throwable $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $error;
}
