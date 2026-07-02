<?php
// Handle ticket purchase: validate input, create Zarinpal payment request, redirect to gateway.

require_once __DIR__ . '/security.php';
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}
csrf_validate_request();

$currentEvent = db_event();
$registrationSettings = [
    'blocked' => !(bool)$currentEvent['registration_enabled'],
    'auto_date' => $currentEvent['registration_starts_at'] !== null || $currentEvent['registration_ends_at'] !== null,
    'start_date' => $currentEvent['registration_starts_at'] ? date('Y-m-d', strtotime($currentEvent['registration_starts_at'])) : '',
    'start_time' => $currentEvent['registration_starts_at'] ? date('H:i', strtotime($currentEvent['registration_starts_at'])) : '',
    'end_date' => $currentEvent['registration_ends_at'] ? date('Y-m-d', strtotime($currentEvent['registration_ends_at'])) : '',
    'end_time' => $currentEvent['registration_ends_at'] ? date('H:i', strtotime($currentEvent['registration_ends_at'])) : '',
];
$registrationAutoDate = !empty($registrationSettings['auto_date']);
$registrationBlocked = !empty($registrationSettings['blocked']);
$registrationWindowActive = false;
$registrationStart = trim((string)($registrationSettings['start_date'] ?? ''));
$registrationEnd = trim((string)($registrationSettings['end_date'] ?? ''));
$registrationStartTime = trim((string)($registrationSettings['start_time'] ?? ''));
$registrationEndTime = trim((string)($registrationSettings['end_time'] ?? ''));
if ($registrationAutoDate && $registrationStart !== '' && $registrationStartTime !== '' && $registrationEnd !== '' && $registrationEndTime !== '') {
    $startTs = strtotime($registrationStart . ' ' . $registrationStartTime);
    $endTs = strtotime($registrationEnd . ' ' . $registrationEndTime);
    if ($startTs !== false && $endTs !== false && $startTs <= $endTs) {
        $now = time();
        $registrationWindowActive = $now >= $startTs && $now <= $endTs;
    }
}
$registrationClosed = $registrationBlocked || ($registrationAutoDate && !$registrationWindowActive);
if ($registrationClosed) {
    fail_redirect('registration_closed');
}

function fail_redirect(string $reason = ''): void
{
    $target = 'fail.php';
    if ($reason !== '') {
        $target .= '?reason=' . urlencode($reason);
    }
    header('Location: ' . $target);
    exit;
}

$fullname = trim($_POST['fullname'] ?? '');
$mobileLocal = preg_replace('/\D+/', '', $_POST['mobile_local'] ?? '');
$mobileLocal = ltrim($mobileLocal); // normalize whitespace just in case

// Normalize mobile to 10 digits without the leading 0.
// Accept inputs like 09XXXXXXXXX or 9XXXXXXXXX (client currently sends 09...).
if (preg_match('/^09\d{9}$/', $mobileLocal)) {
    $mobileLocal = substr($mobileLocal, 1); // strip leading 0 -> 9XXXXXXXXX
}
$qty = (int)($_POST['qty'] ?? 0);

if ($fullname === '' || $mobileLocal === '' || !preg_match('/^9\d{9}$/', $mobileLocal)) {
    fail_redirect('invalid_input');
}

if ($qty < 1 || $qty > 4) {
    fail_redirect('invalid_quantity');
}

$sharePrices = db_ticket_prices((int)$currentEvent['id']);
$totalExpected = $sharePrices[$qty];
$unitPrice = (int) floor($totalExpected / $qty);
$mobileFull = '+98' . $mobileLocal;

function generate_tag(PDO $pdo): string
{
    $pdo->beginTransaction();
    try {
        $row = $pdo->query(
            "SELECT counter_value FROM system_counters
             WHERE counter_name = 'registration_tracking_code' FOR UPDATE"
        )->fetch();
        $number = (int)($row['counter_value'] ?? 0) + 1;
        $stmt = $pdo->prepare(
            "INSERT INTO system_counters (counter_name, counter_value)
             VALUES ('registration_tracking_code', ?)
             ON DUPLICATE KEY UPDATE counter_value = VALUES(counter_value)"
        );
        $stmt->execute([$number]);
        $pdo->commit();
        return sprintf('%05d', $number);
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
}

$tag = generate_tag(db());

// Build Zarinpal request payload
$payload = [
    'merchant_id' => ZARINPAL_MERCHANT_ID,
    'amount' => $totalExpected,
    'callback_url' => zarinpal_build_callback_url(),
    // Put internal tag + attendee full name in Zarinpal description
    // Example: "00002 | علی رضایی"
    'description' => trim($tag . ' | ' . $fullname),
    'metadata' => [
        'mobile' => '0' . $mobileLocal,
        'order_id' => $tag,
        // Include full name so it appears in Zarinpal (if supported)
        'name' => $fullname,
    ],
];
if (ZARINPAL_CURRENCY) {
    $payload['currency'] = ZARINPAL_CURRENCY;
}

// Call Zarinpal payment request API
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => ZARINPAL_REQUEST_URL,
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
    db_log('payment', 'Zarinpal request transport failure', 'error', [
        'curl_error' => $curlErr,
        'tracking_code' => $tag,
    ]);
    fail_redirect('request_failed');
}

$response = json_decode($responseBody, true);
if (!is_array($response)) {
    db_log('payment', 'Zarinpal returned invalid JSON', 'error', [
        'http_body' => substr((string)$responseBody, 0, 2000),
        'tracking_code' => $tag,
    ]);
    fail_redirect('bad_response');
}

$code = $response['data']['code'] ?? null;
$authority = $response['data']['authority'] ?? null;
if ($code !== 100 || !$authority) {
    $errorCode = $response['errors']['code']
        ?? $response['errors'][0]['code']
        ?? $code
        ?? 'unknown';
    $message = $response['errors']['message']
        ?? $response['errors'][0]['message']
        ?? $response['data']['message']
        ?? 'request_denied';
    db_log('payment', 'Zarinpal payment request denied', 'error', [
        'provider_code' => $errorCode,
        'provider_message' => $message,
        'tracking_code' => $tag,
        'response' => $response,
    ]);
    $safeMessage = preg_replace('/[^A-Za-z0-9_\-]/', '_', (string)$message);
    fail_redirect('zarinpal_' . preg_replace('/[^A-Za-z0-9_\-]/', '', (string)$errorCode) . '_' . trim((string)$safeMessage, '_'));
}

$pdo = db();
$pdo->beginTransaction();
try {
    $localMobile = '0' . $mobileLocal;
    $stmt = $pdo->prepare(
        'INSERT INTO participants (full_name, mobile) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE full_name = VALUES(full_name), id = LAST_INSERT_ID(id)'
    );
    $stmt->execute([$fullname, $localMobile]);
    $participantId = (int)$pdo->lastInsertId();
    $stmt = $pdo->prepare(
        'INSERT INTO registrations
         (event_id, participant_id, tracking_code, quantity, unit_price, total_amount, status)
         VALUES (?, ?, ?, ?, ?, ?, "pending_payment")'
    );
    $stmt->execute([(int)$currentEvent['id'], $participantId, $tag, $qty, $unitPrice, $totalExpected]);
    $registrationId = (int)$pdo->lastInsertId();
    $stmt = $pdo->prepare(
        'INSERT INTO payments
         (registration_id, provider, authority, amount, status, request_payload, response_payload)
         VALUES (?, "zarinpal", ?, ?, "requested", ?, ?)'
    );
    $stmt->execute([
        $registrationId,
        $authority,
        $totalExpected,
        json_encode($payload, JSON_UNESCAPED_UNICODE),
        json_encode($response, JSON_UNESCAPED_UNICODE),
    ]);
    $pdo->commit();
} catch (Throwable $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fail_redirect('database_error');
}

// Redirect user to Zarinpal payment page
header('Location: https://payment.zarinpal.com/pg/StartPay/' . $authority);
exit;
