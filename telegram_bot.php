<?php
// Simple Telegram Bot helper for admin notifications

// Bot token and admin chat id
// Provided by user request
if (!defined('TELEGRAM_BOT_TOKEN')) {
    define('TELEGRAM_BOT_TOKEN', '8488319014:AAH26H7GDOtkGdE-Xtoyaem1FqjjlEW9XOM');
}
if (!defined('TELEGRAM_ADMIN_CHAT_ID')) {
    // Admin user_id to receive messages
    define('TELEGRAM_ADMIN_CHAT_ID', '6442613822');
}

/**
 * Append a line to storage/telegram.log for debugging.
 */
function telegram_log(string $line): void
{
    $storageDir = __DIR__ . DIRECTORY_SEPARATOR . 'storage';
    if (!is_dir($storageDir)) {
        @mkdir($storageDir, 0775, true);
    }
    $logFile = $storageDir . DIRECTORY_SEPARATOR . 'telegram.log';
    $timestamp = date('Y-m-d H:i:s');
    @file_put_contents($logFile, "[$timestamp] $line" . PHP_EOL, FILE_APPEND);
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
 * Convenience wrapper: notify configured admin.
 */
function telegram_notify_admin(string $text, array $options = []): array
{
    $res = telegram_send_message(TELEGRAM_ADMIN_CHAT_ID, $text, $options);
    $snippet = substr(json_encode($res['response'] ?? $res, JSON_UNESCAPED_UNICODE), 0, 300);
    telegram_log('notify_admin status=' . ($res['status'] ?? 'n/a') . ' ok=' . (int)($res['ok'] ?? 0) . ' body=' . $snippet);
    return $res;
}

