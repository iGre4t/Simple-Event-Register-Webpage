<?php
declare(strict_types=1);

function load_local_environment(): void
{
    static $loaded = false;
    if ($loaded) {
        return;
    }
    $loaded = true;
    $path = __DIR__ . '/.env.local';
    if (!is_readable($path)) {
        return;
    }
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) {
            continue;
        }
        [$name, $value] = explode('=', $line, 2);
        $name = trim($name);
        if ($name !== '' && getenv($name) === false) {
            putenv($name . '=' . trim($value));
        }
    }
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    load_local_environment();
    $host = getenv('DB_HOST') ?: '127.0.0.1';
    $port = getenv('DB_PORT') ?: '3306';
    $name = getenv('DB_NAME') ?: 'simple_event_register';
    $user = getenv('DB_USER') ?: 'root';
    $pass = getenv('DB_PASSWORD') ?: '';
    $pdo = new PDO(
        "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4",
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
    return $pdo;
}

function db_event(): array
{
    $row = db()->query('SELECT * FROM events ORDER BY id LIMIT 1')->fetch();
    if (!$row) {
        throw new RuntimeException('No event is configured.');
    }
    return $row;
}

function db_ticket_prices(int $eventId): array
{
    $stmt = db()->prepare('SELECT quantity, total_amount FROM ticket_prices WHERE event_id = ? ORDER BY quantity');
    $stmt->execute([$eventId]);
    $prices = [];
    foreach ($stmt as $row) {
        $prices[(int)$row['quantity']] = (int)$row['total_amount'];
    }
    return $prices;
}

function db_notification_channel(string $channel, string $name): ?array
{
    $stmt = db()->prepare('SELECT * FROM notification_channels WHERE channel = ? AND name = ? AND enabled = 1');
    $stmt->execute([$channel, $name]);
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }
    $config = json_decode((string)$row['configuration'], true);
    $row['config'] = is_array($config) ? $config : [];
    return $row;
}

function db_payment_provider(string $provider): array
{
    $stmt = db()->prepare('SELECT * FROM payment_providers WHERE provider = ? AND enabled = 1');
    $stmt->execute([$provider]);
    $row = $stmt->fetch();
    if (!$row) {
        throw new RuntimeException('Payment provider is not configured.');
    }
    $config = json_decode((string)$row['configuration'], true);
    $row['config'] = is_array($config) ? $config : [];
    return $row;
}

function db_audit(string $action, ?string $type = null, ?string $entityId = null, $before = null, $after = null): void
{
    $stmt = db()->prepare(
        'INSERT INTO audit_logs (admin_user_id, action, entity_type, entity_id, before_data, after_data, ip_address)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $_SESSION['admin_user_id'] ?? null,
        $action,
        $type,
        $entityId,
        $before === null ? null : json_encode($before, JSON_UNESCAPED_UNICODE),
        $after === null ? null : json_encode($after, JSON_UNESCAPED_UNICODE),
        $_SERVER['REMOTE_ADDR'] ?? null,
    ]);
}

function db_log(string $channel, string $message, string $level = 'info', ?array $context = null): void
{
    if (!in_array($level, ['info', 'warning', 'error'], true)) {
        $level = 'info';
    }
    $stmt = db()->prepare(
        'INSERT INTO application_logs (channel, level, message, context) VALUES (?, ?, ?, ?)'
    );
    $stmt->execute([
        substr($channel, 0, 50),
        $level,
        $message,
        $context === null ? null : json_encode($context, JSON_UNESCAPED_UNICODE),
    ]);
}
