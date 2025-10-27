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
            if ($apiKey !== '' && $templateId > 0 && $adminMobile !== '' && $parameterName !== '' && extension_loaded('curl')) {
                $details = sprintf(
                    "New paid order%sName: %s%sMobile: %s%sQty: %d%sAmount: %s%sRefID: %s",
                    PHP_EOL . PHP_EOL,
                    (string)($pending['fullname'] ?? ''),
                    PHP_EOL,
                    (string)($pending['mobile'] ?? ''),
                    PHP_EOL,
                    (int)($pending['qty'] ?? 0),
                    PHP_EOL,
                    number_format((int)($pending['total'] ?? 0)),
                    PHP_EOL,
                    (string)$refId
                );
                $smsPayload = [
                    'mobile' => $adminMobile,
                    'templateId' => $templateId,
                    'parameters' => [[
                        'name' => $parameterName,
                        'value' => $details,
                    ]],
                ];
                $ch2 = curl_init('https://api.sms.ir/v1/send/verify');
                if ($ch2 !== false) {
                    curl_setopt_array($ch2, [
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_CUSTOMREQUEST => 'POST',
                        CURLOPT_HTTPHEADER => [
                            'Content-Type: application/json',
                            'Accept: text/plain',
                            'x-api-key: ' . $apiKey,
                        ],
                        CURLOPT_POSTFIELDS => json_encode($smsPayload, JSON_UNESCAPED_UNICODE),
                    ]);
                    curl_exec($ch2);
                    curl_close($ch2);
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

