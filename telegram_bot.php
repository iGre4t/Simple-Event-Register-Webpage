<?php
// Simple Telegram Bot helper for admin notifications
require_once __DIR__ . '/db.php';

$telegramChannel = db_notification_channel('telegram', 'admin_bot');
$telegramConfig = $telegramChannel ? $telegramChannel['config'] : [];
$telegramConfig['bot_token'] = $telegramChannel ? (string)$telegramChannel['secret_value'] : '';

// Bot token and admin chat id
// Provided by user request
if (!defined('TELEGRAM_BOT_TOKEN')) {
    $defaultToken = '';
    if (isset($telegramConfig['bot_token']) && $telegramConfig['bot_token'] !== '') {
        $defaultToken = (string)$telegramConfig['bot_token'];
    }
    define('TELEGRAM_BOT_TOKEN', $defaultToken);
}
if (!defined('TELEGRAM_ADMIN_CHAT_IDS')) {
    $adminIds = isset($telegramConfig['admin_chat_ids']) && is_array($telegramConfig['admin_chat_ids'])
        ? $telegramConfig['admin_chat_ids']
        : (!empty($telegramConfig['admin_chat_id']) ? [(string)$telegramConfig['admin_chat_id']] : []);
    $adminIds = array_values(array_unique(array_filter(array_map(
        static fn($id): string => trim((string)$id),
        $adminIds
    ))));
    define('TELEGRAM_ADMIN_CHAT_IDS', $adminIds);
}

/**
 * Persist Telegram diagnostics in the database.
 */
function telegram_log(string $line): void
{
    db_log('telegram', $line);
}

/**
 * Send a message via Telegram Bot API.
 *
 * @param string $chatId   Target chat/user id
 * @param string $text     Message text
 * @param array  $options  Optional fields (parse_mode, disable_web_page_preview, etc.)
 * @param string|null $token Override bot token, if null uses TELEGRAM_BOT_TOKEN
 * @return array{ok:bool,status?:int,response?:mixed,error?:string}
 */
function telegram_send_message(string $chatId, string $text, array $options = [], ?string $token = null): array
{
    $botToken = $token ?: TELEGRAM_BOT_TOKEN;
    if ($botToken === '' || $chatId === '') {
        return ['ok' => false, 'error' => 'missing_token_or_chat_id'];
    }

    $payload = array_merge([
        'chat_id' => $chatId,
        'text' => $text,
        // Avoid parse_mode by default to prevent formatting issues
        'disable_web_page_preview' => true,
    ], $options);

    $url = 'https://api.telegram.org/bot' . $botToken . '/sendMessage';

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        if ($ch === false) {
            return ['ok' => false, 'error' => 'curl_init_failed'];
        }
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => 'POST',
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Accept: application/json',
            ],
            CURLOPT_POSTFIELDS    => json_encode($payload, JSON_UNESCAPED_UNICODE),
        ]);
        $body = curl_exec($ch);
        $err  = curl_error($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($body === false) {
            return ['ok' => false, 'status' => $code, 'error' => $err ?: 'request_failed'];
        }
        $json = json_decode($body, true);
        return ['ok' => $err === '' && $code >= 200 && $code < 300, 'status' => $code, 'response' => $json ?? $body];
    }

    // Fallback without cURL
    $context = stream_context_create([
        'http' => [
            'method'  => 'POST',
            'header'  => "Content-Type: application/json\r\nAccept: application/json\r\n",
            'content' => json_encode($payload, JSON_UNESCAPED_UNICODE),
            'ignore_errors' => true,
        ],
    ]);
    $body = @file_get_contents($url, false, $context);
    $status = 0;
    if (isset($http_response_header) && is_array($http_response_header)) {
        foreach ($http_response_header as $h) {
            if (preg_match('/^HTTP\/\d+\.\d+\s+(\d+)/', $h, $m)) {
                $status = (int)$m[1];
                break;
            }
        }
    }
    if ($body === false) {
        return ['ok' => false, 'status' => $status, 'error' => 'request_failed'];
    }
    $json = json_decode($body, true);
    return ['ok' => $status >= 200 && $status < 300, 'status' => $status, 'response' => $json ?? $body];
}

/**
 * Convenience wrapper: notify all configured admins.
 */
function telegram_notify_admin(string $text, array $options = []): array
{
    $results = [];
    foreach (TELEGRAM_ADMIN_CHAT_IDS as $chatId) {
        $result = telegram_send_message((string)$chatId, $text, $options);
        $results[(string)$chatId] = $result;
        $snippet = substr(json_encode($result['response'] ?? $result, JSON_UNESCAPED_UNICODE), 0, 300);
        telegram_log('notify_admin chat_id=' . $chatId . ' status=' . ($result['status'] ?? 'n/a')
            . ' ok=' . (int)($result['ok'] ?? 0) . ' body=' . $snippet);
    }
    $allOk = $results !== [];
    foreach ($results as $result) {
        $allOk = $allOk && (bool)($result['ok'] ?? false);
    }
    return ['ok' => $allOk, 'results' => $results];
}

