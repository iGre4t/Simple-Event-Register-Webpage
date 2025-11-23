<?php
// AJAX endpoint: check if a mobile number has already been registered.

header('Content-Type: application/json; charset=UTF-8');

/**
 * Normalize phone numbers to local 11-digit format starting with 09XXXXXXXXX.
 */
function normalize_mobile_local09(string $input): string
{
    $digits = preg_replace('/\D+/', '', $input);
    if ($digits === null) {
        return '';
    }
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

/**
 * Scan a CSV file for a participant with the given normalized mobile.
 */
function file_has_mobile(string $filePath, string $mobileLocal09): bool
{
    if (!is_file($filePath)) {
        return false;
    }
    $fh = fopen($filePath, 'r');
    if ($fh === false) {
        return false;
    }
    $line = 0;
    $mobileIndex = 2; // default position for legacy rows: tag, fullname, mobile, ...

    while (($row = fgetcsv($fh)) !== false) {
        $line++;
        if ($line === 1) {
            if (isset($row[0]) && strtolower((string)$row[0]) === 'tag') {
                // Header row with named columns
                $mobileIndex = null;
                foreach ($row as $idx => $col) {
                    if (strtolower(trim((string)$col)) === 'mobile') {
                        $mobileIndex = $idx;
                        break;
                    }
                }
                if ($mobileIndex === null) {
                    $mobileIndex = 2;
                }
                continue; // skip header
            }
        }
        $cell = (string)($row[$mobileIndex] ?? '');
        $local = normalize_mobile_local09($cell);
        if ($local !== '' && $local === $mobileLocal09) {
            fclose($fh);
            return true;
        }
    }

    fclose($fh);
    return false;
}

$raw = $_GET['mobile'] ?? '';
$mobileLocal = normalize_mobile_local09($raw);

if ($mobileLocal === '') {
    echo json_encode(['ok' => false, 'error' => 'invalid_mobile'], JSON_UNESCAPED_UNICODE);
    exit;
}

$storageDir = __DIR__ . DIRECTORY_SEPARATOR . 'storage';
$exists = false;

if (is_dir($storageDir)) {
    // Active participants (per-quantity CSVs 1..4)
    for ($n = 1; $n <= 4 && !$exists; $n++) {
        $file = $storageDir . DIRECTORY_SEPARATOR . $n . ' tickets.csv';
        if (file_has_mobile($file, $mobileLocal)) {
            $exists = true;
            break;
        }
    }

    // Archived participants (archiev.csv), if present
    if (!$exists) {
        $archived = $storageDir . DIRECTORY_SEPARATOR . 'archiev.csv';
        if (file_has_mobile($archived, $mobileLocal)) {
            $exists = true;
        }
    }
}

echo json_encode(
    [
        'ok'     => true,
        'exists' => $exists,
    ],
    JSON_UNESCAPED_UNICODE
);

