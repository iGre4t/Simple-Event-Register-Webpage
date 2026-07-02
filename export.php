<?php
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/db.php';
security_session_start();

if (!($_SESSION['is_admin'] ?? false)) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

function read_participants_export(): array {
    return db()->query(
        'SELECT r.quantity AS tickets, r.tracking_code AS tag, p.full_name AS fullname,
                p.mobile, r.total_amount AS total, pay.reference_id AS ref_id,
                r.created_at, r.paid_at, pay.authority
         FROM registrations r
         JOIN participants p ON p.id = r.participant_id
         LEFT JOIN payments pay ON pay.registration_id = r.id AND pay.status = "verified"
         WHERE r.status = "paid" ORDER BY r.created_at DESC'
    )->fetchAll();
    /*
    $base = __DIR__ . DIRECTORY_SEPARATOR . 'storage';
    $all = [];
    for ($n = 1; $n <= 4; $n++) {
        $file = $base . DIRECTORY_SEPARATOR . $n . ' tickets.csv';
        if (!is_file($file)) { continue; }
        if (($fh = fopen($file, 'r')) === false) { continue; }
        $lineNo = 0; $header = [];
        while (($row = fgetcsv($fh)) !== false) {
            $lineNo++;
            if ($lineNo === 1 && isset($row[0]) && strtolower((string)$row[0]) === 'tag') {
                $header = array_map('strtolower', $row); continue;
            }
            if (!empty($header)) {
                $idx = array_flip($header);
                $r = [
                    'tickets'    => $n,
                    'tag'        => (string)($row[$idx['tag'] ?? -1] ?? ''),
                    'fullname'   => (string)($row[$idx['fullname'] ?? -1] ?? ''),
                    'mobile'     => (string)($row[$idx['mobile'] ?? -1] ?? ''),
                    'total'      => (int)($row[$idx['total'] ?? -1] ?? 0),
                    'ref_id'     => (string)($row[$idx['ref_id'] ?? -1] ?? ''),
                    'created_at' => (string)($row[$idx['created_at'] ?? -1] ?? ''),
                    'paid_at'    => (string)($row[$idx['paid_at'] ?? -1] ?? ''),
                    'authority'  => (string)($row[$idx['authority'] ?? -1] ?? ''),
                ];
            } else {
                $r = [
                    'tickets'    => $n,
                    'tag'        => (string)($row[0] ?? ''),
                    'fullname'   => (string)($row[1] ?? ''),
                    'mobile'     => (string)($row[2] ?? ''),
                    'total'      => (int)($row[3] ?? 0),
                    'ref_id'     => (string)($row[4] ?? ''),
                    'created_at' => '',
                    'paid_at'    => '',
                    'authority'  => '',
                ];
            }
            $all[] = $r;
        }
        fclose($fh);
    }
    return $all; */
}

$tickets = isset($_GET['tickets']) ? trim((string)$_GET['tickets']) : '';
$from = isset($_GET['from']) ? trim((string)$_GET['from']) : '';
$to   = isset($_GET['to'])   ? trim((string)$_GET['to'])   : '';

$rows = read_participants_export();

// Filter by tickets count
if ($tickets !== '' && ctype_digit($tickets)) {
    $t = (int)$tickets;
    $rows = array_values(array_filter($rows, function($r) use ($t){ return (int)$r['tickets'] === $t; }));
}

// Filter by date range (uses created_at then paid_at)
if ($from !== '' || $to !== '') {
    $fromTs = $from !== '' ? strtotime($from . ' 00:00:00') : null;
    $toTs   = $to   !== '' ? strtotime($to . ' 23:59:59')   : null;
    $rows = array_values(array_filter($rows, function($r) use ($fromTs, $toTs){
        $ts = 0; foreach ([$r['created_at'] ?? '', $r['paid_at'] ?? ''] as $d){ if($d){ $t=strtotime($d); if($t){$ts=$t; break;} } }
        if ($fromTs !== null && $ts < $fromTs) return false;
        if ($toTs   !== null && $ts > $toTs)   return false;
        return true;
    }));
}

// Output CSV
header('Content-Type: text/csv; charset=UTF-8');
$fnameParts = ['export'];
if ($tickets !== '' && ctype_digit($tickets)) { $fnameParts[] = $tickets . 'tickets'; }
if ($from !== '') { $fnameParts[] = 'from' . $from; }
if ($to   !== '') { $fnameParts[] = 'to' . $to; }
$filename = implode('_', $fnameParts) . '_' . date('Ymd_His') . '.csv';
header('Content-Disposition: attachment; filename="' . $filename . '"');

$out = fopen('php://output', 'w');
fputcsv($out, ['tickets','tag','fullname','mobile','total','ref_id','created_at','paid_at','authority']);
foreach ($rows as $r) {
    fputcsv($out, [
        (int)$r['tickets'],
        (string)$r['tag'],
        (string)$r['fullname'],
        (string)$r['mobile'],
        (int)$r['total'],
        (string)($r['ref_id'] ?? ''),
        (string)($r['created_at'] ?? ''),
        (string)($r['paid_at'] ?? ''),
        (string)($r['authority'] ?? ''),
    ]);
}
fclose($out);
exit;

