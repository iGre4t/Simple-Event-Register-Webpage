<?php
// Zarinpal integration configuration
require_once __DIR__ . '/db.php';
$zarinpalProvider = db_payment_provider('zarinpal');
$zarinpalConfig = $zarinpalProvider['config'];

// Merchant ID from Zarinpal panel
define('ZARINPAL_MERCHANT_ID', (string)$zarinpalProvider['secret_value']);

// Optionally force a callback URL. If empty, it will be auto-generated
// from the current request host + path to `verify.php`.
define('ZARINPAL_CALLBACK_URL', (string)($zarinpalConfig['callback_url'] ?? ''));

// Currency for Zarinpal amount. Use 'IRR' for Rial (default) or 'IRT' for Toman.
define('ZARINPAL_CURRENCY', (string)($zarinpalConfig['currency'] ?? 'IRR'));

// API endpoints
define('ZARINPAL_REQUEST_URL', (string)($zarinpalConfig['request_url'] ?? 'https://payment.zarinpal.com/pg/v4/payment/request.json'));
define('ZARINPAL_VERIFY_URL',  (string)($zarinpalConfig['verify_url'] ?? 'https://payment.zarinpal.com/pg/v4/payment/verify.json'));

// Build absolute callback URL to verify.php within the same directory as the caller file
function zarinpal_build_callback_url(): string {
    if (ZARINPAL_CALLBACK_URL !== '') {
        return ZARINPAL_CALLBACK_URL;
    }
    $isHttps = isset($_SERVER['HTTPS']) && (strtolower((string)$_SERVER['HTTPS']) === 'on' || $_SERVER['HTTPS'] === '1');
    $scheme = $isHttps ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $basePath = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
    return sprintf('%s://%s%s/verify.php', $scheme, $host, $basePath);
}
