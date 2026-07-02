<?php
// AJAX endpoint: check if a mobile number has already been registered.

require_once __DIR__ . '/security.php';
require_once __DIR__ . '/db.php';
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

$stmt = db()->prepare(
    'SELECT 1 FROM participants p
     JOIN registrations r ON r.participant_id = p.id
     WHERE p.mobile = ? AND r.status IN ("paid", "archived") LIMIT 1'
);
$stmt->execute([$mobileLocal]);
$exists = (bool)$stmt->fetchColumn();

echo json_encode(
    [
        'ok'     => true,
        'exists' => $exists,
    ],
    JSON_UNESCAPED_UNICODE
);

