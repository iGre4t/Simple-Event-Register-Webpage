<?php
require_once __DIR__ . '/security.php';
// Zarinpal callback endpoint: verify payment and finalize order persistence.

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/telegram_bot.php';

function fail_redirect(string $reason = ''): void
{
    $target = 'fail.php';
    if ($reason !== '') {
        $target .= '?reason=' . urlencode($reason);
    }
    header('Location: ' . $target);
    exit;
}

// Persist SMS diagnostics in the database.
function sms_log(string $line): void
{
    db_log('sms', $line);
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

$stmt = db()->prepare(
    'SELECT p.id AS payment_id, p.registration_id, p.amount AS total, p.status AS payment_status,
            r.tracking_code AS tag, r.quantity AS qty, r.unit_price, r.created_at,
            pt.full_name AS fullname, pt.mobile
     FROM payments p
     JOIN registrations r ON r.id = p.registration_id
     JOIN participants pt ON pt.id = r.participant_id
     WHERE p.provider = "zarinpal" AND p.authority = ?'
);
$stmt->execute([$authority]);
$pending = $stmt->fetch();
if (!$pending) {
    fail_redirect('unknown_authority');
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

// Idempotent database finalization.
$shouldNotify = ($pending['payment_status'] ?? '') !== 'verified';
if ($shouldNotify) {
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare(
            'UPDATE payments SET status = "verified", reference_id = ?, response_payload = ?,
             verified_at = NOW() WHERE id = ? AND status <> "verified"'
        );
        $stmt->execute([
            (string)$refId,
            json_encode($response, JSON_UNESCAPED_UNICODE),
            (int)$pending['payment_id'],
        ]);
        $stmt = $pdo->prepare(
            'UPDATE registrations SET status = "paid", paid_at = NOW() WHERE id = ?'
        );
        $stmt->execute([(int)$pending['registration_id']]);
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        fail_redirect('database_finalize_error');
    }

    // SMS notification(s) via SMS.ir according to configured mode
    $smsChannel = db_notification_channel('sms', 'sms_ir');
    if ($smsChannel) {
        $smsConfig = $smsChannel['config'];
        $smsConfig['api_key'] = (string)$smsChannel['secret_value'];
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

            // Telegram admin notification (Persian format requested)
            $tgText = $fullname . " در مسابقات ثبت نام کرد\n\n"
                    . "تعداد سهم: " . $qtyVal . "\n"
                    . "مجموع مبلغ پرداخت: " . $totalFmt . "\n\n"
                    . "کد رهگیری داخلی: " . $tagVal . "\n"
                    . "کد رهگیری پرداخت: " . $refVal;
            // Build formatted Telegram message (HTML parse_mode)
            $fullnameHtml = htmlspecialchars((string)$fullname, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $tagHtml      = htmlspecialchars((string)$tagVal, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $refHtml      = htmlspecialchars((string)$refVal, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $tgText =
                '<b>' . $fullnameHtml . '</b>' . ' در مسابقات ثبت نام کرد ✅' . "\n\n" .
                '🔖 ' . '<b>تعداد سهم:</b> ' . '<b>' . $qtyVal . '</b>' . "\n" .
                '💳 ' . '<b>مجموع مبلغ پرداخت:</b> ' . '<b>' . $totalFmt . ' تومان</b>' . "\n\n" .
                '⬇️ ' . '<b>کد رهگیری داخلی:</b> ' . '<b>' . $tagHtml . '</b>' . "\n" .
                '✴️ ' . '<b>کد رهگیری پرداخت:</b> ' . '<code>' . $refHtml . '</code>';
            telegram_notify_admin($tgText, ['parse_mode' => 'HTML']);

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
