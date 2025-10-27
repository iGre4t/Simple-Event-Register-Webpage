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

// Normalize phone numbers to local 11-digit format starting with 09XXXXXXXXX
function normalize_mobile_local09(string $input): string
{
    $digits = preg_replace('/\D+/', '', $input);
    if ($digits === null) { return ''; }
    if (preg_match('/^98(9\d{9})$/', $digits, $m)) {
        return '0' . $m[1];
    }
    if (preg_match('/^09\d{9}$/', $digits)) {
        return $digits;
    }
    if (preg_match('/^(9\d{9})$/', $digits, $m)) {
        return '0' . $m[1];
    }
    return '';
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

// Bulk sender via dedicated line (SMS.ir v1/send/bulk)
function smsir_send_bulk(string $apiKey, string $lineNumber, string $messageText, array $mobiles): array
{
    $ln = ctype_digit($lineNumber) ? (int)$lineNumber : $lineNumber;
    $payload = [
        'lineNumber'   => $ln,
        'messageText'  => $messageText,
        'mobiles'      => array_values($mobiles),
        'sendDateTime' => null,
    ];
    $ch = curl_init('https://api.sms.ir/v1/send/bulk');
    if ($ch === false) {
        return ['ok' => false, 'error' => 'curl_init_failed'];
    }
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => 'POST',
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Accept: application/json',
            'X-API-KEY: ' . $apiKey,
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
    return ['ok' => $err === '' && $code >= 200 && $code < 300, 'status' => $code, 'response' => $json ?? $body, 'raw' => $body];
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
            fputcsv($fh, ['tag', 'fullname', 'mobile', 'total', 'ref_id']);
        }
        fputcsv($fh, [
            (string)($pending['tag'] ?? ''),
            (string)($pending['fullname'] ?? ''),
            (string)($pending['mobile'] ?? ''),
            (int)($pending['total'] ?? 0),
            (string)$refId,
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

    // SMS notification(s) via SMS.ir according to configured mode
    $smsConfigPath = __DIR__ . DIRECTORY_SEPARATOR . 'sms_config.php';
    if (is_readable($smsConfigPath)) {
        $smsConfig = require $smsConfigPath;
        if (is_array($smsConfig)) {
            $mode            = strtolower((string)($smsConfig['mode'] ?? 'bulk'));
            $apiKey          = trim((string)($smsConfig['api_key'] ?? ''));
            $templateId      = (int)($smsConfig['template_id'] ?? 0);
            $parameterName   = trim((string)($smsConfig['parameter_name'] ?? ''));
            $lineNumber      = trim((string)($smsConfig['line_number'] ?? ''));
            $adminMobileRaw  = (string)($smsConfig['admin_mobile'] ?? '');
            $sandbox         = (bool)($smsConfig['sandbox'] ?? false);

            $adminMobile = normalize_mobile_local09($adminMobileRaw);
            $buyerMobile = normalize_mobile_local09((string)($pending['mobile'] ?? ''));

            // Compose messages
            $fullname = (string)($pending['fullname'] ?? '');
            $qtyVal   = (int)($pending['qty'] ?? 0);
            $totalVal = (int)($pending['total'] ?? 0);
            $totalFmt = number_format($totalVal);
            $tagVal   = (string)($pending['tag'] ?? '');
            $refVal   = (string)$refId;

            // Persian SMS frames
            $buyerText = $fullname . ' ثبت نام شما در سوپرکاپ ششم سیسیلی تکمیل شد! 🏆' . "\n\n"
                        . 'لطفا برای اطلاع از زمان مسابقات به کانال تلگرام سیسیلی به آدرس @SicilyClub مراجعه کنید.' . "\n\n"
                        . '🎟 تعداد سهم شما ' . $qtyVal . "\n"
                        . '✅ مجموع مبلغ سهم های شما: ' . $totalFmt . "\n\n"
                        . 'کد رهگیری داخلی: ' . $tagVal . "\n"
                        . 'کد رهگیری پرداخت: ' . $refVal;

            $adminText = $fullname . ' در مسابقات ثبت نام کرد' . "\n\n"
                        . 'تعداد سهم: ' . $qtyVal . "\n"
                        . 'مجموع مبلغ پرداخت: ' . $totalFmt . "\n\n"
                        . 'کد رهگیری داخلی: ' . $tagVal . "\n"
                        . 'کد رهگیری پرداخت: ' . $refVal;

            // Admin recipients: configured + extra fixed number
            $extraAdmin = normalize_mobile_local09('09220463874');
            $adminMobiles = [];
            if ($adminMobile !== '') { $adminMobiles[] = $adminMobile; }
            if ($extraAdmin !== '' && !in_array($extraAdmin, $adminMobiles, true)) { $adminMobiles[] = $extraAdmin; }

            if ($apiKey === '' || !extension_loaded('curl')) {
                sms_log('SMS skipped: missing api_key or curl extension');
            } else {
                $doVerify = ($mode === 'verify' || $mode === 'both');
                $doBulk   = ($mode === 'bulk' || $mode === 'both');

                if ($doVerify) {
                    $tplId = $templateId;
                    $paramName = $parameterName;
                    if ($sandbox) { $tplId = 123456; $paramName = 'Code'; }

                    if ($tplId > 0 && $paramName !== '') {
                        if (!empty($adminMobiles)) {
                            foreach ($adminMobiles as $am) {
                                $res = smsir_send_template($apiKey, $am, $tplId, $paramName, $adminText);
                                $snippet = substr((string)($res['raw'] ?? json_encode($res['response'] ?? '')), 0, 300);
                                sms_log('SMS.ir verify (admin) ' . $am . ' status=' . ($res['status'] ?? 'n/a') . ' ok=' . (int)($res['ok'] ?? 0) . ' body=' . $snippet);
                            }
                        } else {
                            sms_log('Verify admin SMS skipped: invalid admin mobile(s)');
                        }
                        if ($buyerMobile !== '') {
                            $resB = smsir_send_template($apiKey, $buyerMobile, $tplId, $paramName, $buyerText);
                            $snippetB = substr((string)($resB['raw'] ?? json_encode($resB['response'] ?? '')), 0, 300);
                            sms_log('SMS.ir verify (buyer) status=' . ($resB['status'] ?? 'n/a') . ' ok=' . (int)($resB['ok'] ?? 0) . ' body=' . $snippetB);
                        } else {
                            sms_log('Verify buyer SMS skipped: invalid buyer mobile');
                        }
                    } else {
                        sms_log('Verify skipped: template_id/parameter_name not set');
                    }
                }

                if ($doBulk) {
                    if ($lineNumber !== '') {
                        if (!empty($adminMobiles)) {
                            $res = smsir_send_bulk($apiKey, $lineNumber, $adminText, $adminMobiles);
                            $snippet = substr((string)($res['raw'] ?? json_encode($res['response'] ?? '')), 0, 300);
                            sms_log('SMS.ir bulk (admin) status=' . ($res['status'] ?? 'n/a') . ' ok=' . (int)($res['ok'] ?? 0) . ' body=' . $snippet);
                        } else {
                            sms_log('Bulk admin SMS skipped: invalid admin mobile(s)');
                        }
                        if ($buyerMobile !== '') {
                            $resB = smsir_send_bulk($apiKey, $lineNumber, $buyerText, [$buyerMobile]);
                            $snippetB = substr((string)($resB['raw'] ?? json_encode($resB['response'] ?? '')), 0, 300);
                            sms_log('SMS.ir bulk (buyer) status=' . ($resB['status'] ?? 'n/a') . ' ok=' . (int)($resB['ok'] ?? 0) . ' body=' . $snippetB);
                        } else {
                            sms_log('Bulk buyer SMS skipped: invalid buyer mobile');
                        }
                    } else {
                        sms_log('Bulk skipped: line_number not set');
                    }
                }
            }
        }
    }
}

// Redirect to local success page with tracking tag and ref_id
$tag = (string)($pending['tag'] ?? '');
$qs = http_build_query(['tag' => $tag, 'ref_id' => (string)$refId]);
header('Location: success.php?' . $qs);
exit;
