<?php
// Zarinpal callback endpoint: verify payment and finalize order persistence.

require_once __DIR__ . '/config.php';

function fail_redirect(string $reason = ''): void
{
    $target = 'fail.php';
    if ($reason !== '') {
        $target .= '?reason=' . urlencode($reason);
    }
    header('Location: ' . $target);
    exit;
}

// Append a line to storage/sms.log for debugging SMS behavior
function sms_log(string $line): void
{
    $storageDir = __DIR__ . DIRECTORY_SEPARATOR . 'storage';
    if (!is_dir($storageDir)) {
        @mkdir($storageDir, 0775, true);
    }
    $logFile = $storageDir . DIRECTORY_SEPARATOR . 'sms.log';
    $timestamp = date('Y-m-d H:i:s');
    @file_put_contents($logFile, "[$timestamp] $line" . PHP_EOL, FILE_APPEND);
}

// Minimal helper to call SMS.ir verify (templated) endpoint
function smsir_send_template(string $apiKey, string $mobile, int $templateId, string $parameterName, string $value): array
{
    $payload = [
        'mobile' => $mobile,
        'templateId' => $templateId,
        'parameters' => [[
            'name' => $parameterName,
            'value' => $value,
        ]],
    ];
    $ch = curl_init('https://api.sms.ir/v1/send/verify');
    if ($ch === false) {
        return ['ok' => false, 'error' => 'curl_init_failed'];
    }
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Accept: application/json',
            'x-api-key: ' . $apiKey,
        ],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
    ]);
    $body = curl_exec($ch);
    $err = curl_error($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($body === false) {
        return ['ok' => false, 'status' => $code, 'error' => $err ?: 'request_failed'];
    }
    $json = json_decode($body, true);
    return ['ok' => $err === '' && $code >= 200 && $code < 300, 'status' => $code, 'response' => $json ?? $body];
}

$status = $_GET['Status'] ?? '';
$authority = $_GET['Authority'] ?? '';

if (strtoupper((string)$status) !== 'OK') {
    fail_redirect('payment_canceled');
}

if ($authority === '' || !preg_match('/^[A-Za-z0-9]+$/', $authority)) {
    fail_redirect('invalid_authority');
}

$storageDir = __DIR__ . DIRECTORY_SEPARATOR . 'storage';
$pendingFile = $storageDir . DIRECTORY_SEPARATOR . 'pending' . DIRECTORY_SEPARATOR . $authority . '.json';
if (!is_file($pendingFile)) {
    fail_redirect('unknown_authority');
}

$pending = json_decode((string)file_get_contents($pendingFile), true);
if (!is_array($pending)) {
    fail_redirect('pending_corrupt');
}

$amount = (int)($pending['total'] ?? 0);
if ($amount <= 0) {
    fail_redirect('pending_bad_amount');
}

// Call Zarinpal verify API
$payload = [
    'merchant_id' => ZARINPAL_MERCHANT_ID,
    'amount' => $amount,
    'authority' => $authority,
];

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => ZARINPAL_VERIFY_URL,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => '',
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => 'POST',
    CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Accept: application/json',
    ],
]);

$responseBody = curl_exec($ch);
$curlErr = curl_error($ch);
curl_close($ch);

if ($responseBody === false || $curlErr) {
    fail_redirect('verify_failed');
}

$response = json_decode($responseBody, true);
if (!is_array($response)) {
    fail_redirect('verify_bad_response');
}

$code = $response['data']['code'] ?? null;
$refId = $response['data']['ref_id'] ?? null;
$cardPan = $response['data']['card_pan'] ?? null;

if ($code !== 100 && $code !== 101) {
    $msg = $response['errors'][0]['message'] ?? ($response['data']['message'] ?? 'not_verified');
    fail_redirect('code_' . (string)$code . '_' . preg_replace('/[^A-Za-z0-9_\-]/', '', (string)$msg));
}

// Idempotent finalize: write CSV once, then mark as completed.
$completedDir = $storageDir . DIRECTORY_SEPARATOR . 'completed';
if (!is_dir($completedDir)) {
    @mkdir($completedDir, 0775, true);
}
$completedMarker = $completedDir . DIRECTORY_SEPARATOR . $authority . '.json';

if (!is_file($completedMarker)) {
    // Append to the same CSV format used previously
    $qty = (int)($pending['qty'] ?? 0);
    $fileName = sprintf('%d tickets.csv', max(1, min(4, $qty)));
    $filePath = $storageDir . DIRECTORY_SEPARATOR . $fileName;
    $fh = fopen($filePath, 'a');
    if ($fh === false) {
        fail_redirect('file_error');
    }
    if (!flock($fh, LOCK_EX)) {
        fclose($fh);
        fail_redirect('file_lock');
    }
    try {
        $stats = fstat($fh);
        $needsHeader = $stats !== false && ($stats['size'] ?? 0) === 0;
        if ($needsHeader) {
            fputcsv($fh, ['tag', 'fullname', 'mobile', 'total']);
        }
        fputcsv($fh, [
            (string)($pending['tag'] ?? ''),
            (string)($pending['fullname'] ?? ''),
            (string)($pending['mobile'] ?? ''),
            (int)($pending['total'] ?? 0),
        ]);
    } finally {
        fflush($fh);
        flock($fh, LOCK_UN);
        fclose($fh);
    }

    // Mark as completed
    $completedPayload = [
        'authority' => $authority,
        'ref_id' => $refId,
        'code' => $code,
        'card_pan' => $cardPan,
        'pending' => $pending,
        'completed_at' => date('c'),
    ];
    file_put_contents($completedMarker, json_encode($completedPayload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

    // Remove pending record
    @unlink($pendingFile);

    // Optional: notify admin via SMS if configured
    $smsConfigPath = __DIR__ . DIRECTORY_SEPARATOR . 'sms_config.php';
    if (is_readable($smsConfigPath)) {
        $smsConfig = require $smsConfigPath;
        if (is_array($smsConfig)) {
            // Minimal inline sender (same payload style as before)
            $apiKey = trim((string)($smsConfig['api_key'] ?? ''));
            $templateId = (int)($smsConfig['template_id'] ?? 0);
            $adminMobile = preg_replace('/\D+/', '', (string)($smsConfig['admin_mobile'] ?? ''));
            $parameterName = trim((string)($smsConfig['parameter_name'] ?? ''));
            if ($apiKey !== '' && $adminMobile !== '' && extension_loaded('curl')) {
                if ($templateId > 0 && $parameterName !== '') {
                    // Persian admin notification text
                    $details = "ثبت سفارش جدید" . PHP_EOL .
                               'نام: ' . (string)($pending['fullname'] ?? '') . PHP_EOL .
                               'موبایل: ' . (string)($pending['mobile'] ?? '') . PHP_EOL .
                               'تعداد: ' . (int)($pending['qty'] ?? 0) . PHP_EOL .
                               'مبلغ: ' . number_format((int)($pending['total'] ?? 0)) . ' ریال' . PHP_EOL .
                               'RefID: ' . (string)$refId . PHP_EOL .
                               'برچسب: ' . (string)($pending['tag'] ?? '');

                    $res = smsir_send_template($apiKey, $adminMobile, $templateId, $parameterName, $details);
                    sms_log('SMS.ir verify send -> mobile=' . $adminMobile . ' status=' . ($res['status'] ?? 'n/a') . ' ok=' . (int)($res['ok'] ?? 0));
                } else {
                    sms_log('SMS skipped: template_id/parameter_name not set');
                }
                // Send confirmation SMS to buyer
                $buyerTemplateId = (int)($smsConfig['buyer_template_id'] ?? 0);
                $buyerParameterName = trim((string)($smsConfig['buyer_parameter_name'] ?? ''));
                // Fallback to admin template config if buyer-specific not set
                if ($buyerTemplateId <= 0) { $buyerTemplateId = $templateId; }
                if ($buyerParameterName === '') { $buyerParameterName = $parameterName; }

                // Normalize buyer mobile to 09xxxxxxxxx for SMS.ir
                $buyerMobile = '';
                $savedMobile = (string)($pending['mobile'] ?? '');
                $digits = preg_replace('/\D+/', '', $savedMobile);
                if (preg_match('/^98(9\d{9})$/', $digits, $m)) {
                    $buyerMobile = '0' . $m[1];
                } elseif (preg_match('/^09\d{9}$/', $savedMobile)) {
                    $buyerMobile = $savedMobile;
                } elseif (preg_match('/^(9\d{9})$/', $digits, $m)) {
                    $buyerMobile = '0' . $m[1];
                }

                if ($buyerMobile !== '' && $buyerTemplateId > 0 && $buyerParameterName !== '') {
                    $buyerMsg = "پرداخت شما با موفقیت ثبت شد" . PHP_EOL .
                                'نام: ' . (string)($pending['fullname'] ?? '') . PHP_EOL .
                                'تعداد: ' . (int)($pending['qty'] ?? 0) . PHP_EOL .
                                'مبلغ: ' . number_format((int)($pending['total'] ?? 0)) . ' ریال' . PHP_EOL .
                                'کدرهگیری: ' . (string)$refId . PHP_EOL .
                                'برچسب: ' . (string)($pending['tag'] ?? '');
                    $resBuyer = smsir_send_template($apiKey, $buyerMobile, $buyerTemplateId, $buyerParameterName, $buyerMsg);
                    sms_log('SMS.ir verify send (buyer) -> mobile=' . $buyerMobile . ' status=' . ($resBuyer['status'] ?? 'n/a') . ' ok=' . (int)($resBuyer['ok'] ?? 0));
                } else {
                    sms_log('Buyer SMS skipped: mobile/template unset or invalid');
                }
            } else {
                sms_log('SMS skipped: missing api_key/admin_mobile or curl not loaded');
            }
        }
    }
}

// Redirect to local success page with tracking tag and ref_id
$tag = (string)($pending['tag'] ?? '');
$qs = http_build_query(['tag' => $tag, 'ref_id' => (string)$refId]);
header('Location: success.php?' . $qs);
exit;
