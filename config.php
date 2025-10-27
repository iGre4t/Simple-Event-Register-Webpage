<?php
// Zarinpal integration configuration

// TODO: Replace with your real merchant ID from Zarinpal panel
define('ZARINPAL_MERCHANT_ID', 'xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx');

// Optionally force a callback URL. If empty, it will be auto-generated
// from the current request host + path to `verify.php`.
define('ZARINPAL_CALLBACK_URL', '');

// Currency for Zarinpal amount. Use 'IRR' for Rial (default) or 'IRT' for Toman.
define('ZARINPAL_CURRENCY', 'IRR');

// API endpoints
define('ZARINPAL_REQUEST_URL', 'https://payment.zarinpal.com/pg/v4/payment/request.json');
define('ZARINPAL_VERIFY_URL',  'https://payment.zarinpal.com/pg/v4/payment/verify.json');

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
