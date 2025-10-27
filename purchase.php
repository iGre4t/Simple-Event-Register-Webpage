<?php
// Handle ticket purchase: validate input, create Zarinpal payment request, redirect to gateway.

require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
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
$qty = (int)($_POST['qty'] ?? 0);
$unitPrice = (int)($_POST['unit_price'] ?? 0);

if ($fullname === '' || $mobileLocal === '' || !preg_match('/^9\d{9}$/', $mobileLocal)) {
    fail_redirect('invalid_input');
}

if ($qty < 1 || $qty > 4) {
    fail_redirect('invalid_quantity');
}

if ($unitPrice <= 0) {
    fail_redirect('invalid_price');
}

$totalExpected = $unitPrice * $qty; // IRR (Rial)
$mobileFull = '+98' . $mobileLocal;

// Storage for pending orders (keyed by Authority)
$storageDir = __DIR__ . DIRECTORY_SEPARATOR . 'storage';
if (!is_dir($storageDir) && !mkdir($storageDir, 0775, true) && !is_dir($storageDir)) {
    fail_redirect('storage_error');
}
if (!is_dir($storageDir . DIRECTORY_SEPARATOR . 'pending')) {
    @mkdir($storageDir . DIRECTORY_SEPARATOR . 'pending', 0775, true);
}

// Generate a local order tag (human-friendly tracking)
function generate_tag(string $storageDir): string
{
    $counterFile = $storageDir . DIRECTORY_SEPARATOR . 'counter.txt';
    $handle = fopen($counterFile, 'c+');
    if ($handle === false) {
        fail_redirect('counter_error');
    }
    try {
        if (!flock($handle, LOCK_EX)) {
            fail_redirect('counter_lock');
        }
        rewind($handle);
        $last = trim(stream_get_contents($handle));
        $number = ($last === '' || !ctype_digit($last)) ? 0 : (int)$last;
        $number++;
        ftruncate($handle, 0);
        rewind($handle);
        fwrite($handle, (string)$number);
        fflush($handle);
        $timestamp = date('YmdHis');
        return sprintf('%s-%05d', $timestamp, $number);
    } finally {
        flock($handle, LOCK_UN);
        fclose($handle);
    }
}

$tag = generate_tag($storageDir);

// Build Zarinpal request payload
$payload = [
    'merchant_id' => ZARINPAL_MERCHANT_ID,
    'amount' => $totalExpected,
    'callback_url' => zarinpal_build_callback_url(),
    'description' => 'Transaction for event tickets',
    'metadata' => [
        'mobile' => '0' . $mobileLocal,
        'order_id' => $tag,
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
    fail_redirect('request_failed');
}

$response = json_decode($responseBody, true);
if (!is_array($response)) {
    fail_redirect('bad_response');
}

$code = $response['data']['code'] ?? null;
$authority = $response['data']['authority'] ?? null;
if ($code !== 100 || !$authority) {
    $msg = $response['errors'][0]['message'] ?? ($response['data']['message'] ?? 'request_denied');
    fail_redirect('code_' . (string)$code . '_' . preg_replace('/[^A-Za-z0-9_\-]/', '', (string)$msg));
}

// Save pending order details for verification step
$pending = [
    'tag' => $tag,
    'fullname' => $fullname,
    'mobile' => $mobileFull,
    'qty' => $qty,
    'unit_price' => $unitPrice,
    'total' => $totalExpected,
    'created_at' => date('c'),
];
$pendingFile = $storageDir . DIRECTORY_SEPARATOR . 'pending' . DIRECTORY_SEPARATOR . $authority . '.json';
file_put_contents($pendingFile, json_encode($pending, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

// Redirect user to Zarinpal payment page
header('Location: https://payment.zarinpal.com/pg/StartPay/' . $authority);
exit;

