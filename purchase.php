<?php
// Handle ticket purchase submission and persist attendee information.

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

$totalExpected = $unitPrice * $qty;
$mobileFull = '+98' . $mobileLocal;

// All purchases are considered successful for now.
$storageDir = __DIR__ . DIRECTORY_SEPARATOR . 'storage';
if (!is_dir($storageDir)) {
    if (!mkdir($storageDir, 0775, true) && !is_dir($storageDir)) {
        fail_redirect('storage_error');
    }
}

/**
 * Generate a unique tag composed of a timestamp and a queue number.
 */
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

$fileName = sprintf('%d tickets.csv', $qty);
$filePath = $storageDir . DIRECTORY_SEPARATOR . $fileName;
$fileHandle = fopen($filePath, 'a');
if ($fileHandle === false) {
    fail_redirect('file_error');
}

if (!flock($fileHandle, LOCK_EX)) {
    fclose($fileHandle);
    fail_redirect('file_lock');
}

try {
    $stats = fstat($fileHandle);
    $needsHeader = $stats !== false && ($stats['size'] ?? 0) === 0;
    if ($needsHeader) {
        fputcsv($fileHandle, ['tag', 'نام و نام خانوادگی', 'شماره تلفن همراه', 'مجموع مبلغ']);
    }

    fputcsv($fileHandle, [$tag, $fullname, $mobileFull, $totalExpected]);
} finally {
    fflush($fileHandle);
    flock($fileHandle, LOCK_UN);
    fclose($fileHandle);
}

header('Location: success.php?tag=' . urlencode($tag));
exit;
