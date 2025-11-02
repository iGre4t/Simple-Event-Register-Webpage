<?php
session_start();
header('Content-Type: application/json; charset=UTF-8');

if (!($_SESSION['is_admin'] ?? false)) {
    http_response_code(403);
    echo json_encode(['ok'=>false,'error'=>'forbidden']);
    exit;
}

function mobile_local(string $raw): string {
    $d = preg_replace('/\D+/', '', $raw);
    if ($d === null) { $d = ''; }
    if (strpos($d, '0098') === 0) { $d = substr($d, 4); }
    if (strpos($d, '98') === 0)   { $d = substr($d, 2); }
    if ($d !== '' && $d[0] !== '0') { if (strlen($d) === 10 && $d[0] === '9') { $d = '0' . $d; } }
    if (strlen($d) === 11 && $d[0] === '0') { return $d; }
    return $d;
}

function mobile_display(string $raw): string {
    $local = mobile_local($raw);
    if (strlen($local) === 11 && $local[0] === '0') {
        $ten = substr($local, 1);
        return '+98 ' . substr($ten, 0, 3) . ' ' . substr($ten, 3, 3) . ' ' . substr($ten, 6, 4);
    }
    return (string)$raw;
}

// Lightweight Gregorian -> Jalali conversion and formatter
function gregorian_to_jalali(int $gy, int $gm, int $gd): array {
    $g_d_m = [0,31,59,90,120,151,181,212,243,273,304,334];
    $gy2 = $gy - 1600;
    $gm2 = $gm - 1;
    $gd2 = $gd - 1;
    $g_day_no = 365*$gy2 + (int)(($gy2+3)/4) - (int)(($gy2+99)/100) + (int)(($gy2+399)/400);
    $g_day_no += $g_d_m[$gm2] + $gd2;
    if ($gm2 > 1 && (($gy%4==0 && $gy%100!=0) || ($gy%400==0))) $g_day_no++;
    $j_day_no = $g_day_no - 79;
    $j_np = (int)($j_day_no / 12053);
    $j_day_no %= 12053;
    $jy = 979 + 33*$j_np + 4*(int)($j_day_no/1461);
    $j_day_no %= 1461;
    if ($j_day_no >= 366) {
        $jy += (int)(($j_day_no-366)/365);
        $j_day_no = ($j_day_no-366)%365;
    }
    for ($jm=0; $jm<11 && $j_day_no >= [31,31,31,31,31,31,30,30,30,30,30,29][$jm]; $jm++) {
        $j_day_no -= [31,31,31,31,31,31,30,30,30,30,30,29][$jm];
    }
    $jm += 1;
    $jd = $j_day_no + 1;
    return [$jy, $jm, $jd];
}

function shamsi_datetime(string $dateString): string {
    $t = strtotime($dateString);
    if (!$t) return '';
    $gy = (int)date('Y', $t);
    $gm = (int)date('n', $t);
    $gd = (int)date('j', $t);
    [$jy,$jm,$jd] = gregorian_to_jalali($gy,$gm,$gd);
    $datePart = sprintf('%04d/%02d/%02d', $jy, $jm, $jd);
    $timePart = date('H:i', $t);
    return $datePart . '  |  ' . $timePart; // clearer separation between date and time
}

function read_archived(): array {
    $file = __DIR__ . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'archiev.csv';
    $out = [];
    if (!is_file($file)) { return $out; }
    if (($fh = fopen($file, 'r')) === false) { return $out; }
    $lineNo = 0; $header = [];
    while (($row = fgetcsv($fh)) !== false) {
        $lineNo++;
        if ($lineNo === 1 && isset($row[0]) && strtolower((string)$row[0]) === 'tickets') {
            $header = array_map(function($h){ return strtolower(trim((string)$h)); }, $row);
            $idx = array_flip($header);
            continue;
        }
        if (!empty($header)) {
            $rec = [
                'tickets'   => (int)($row[$idx['tickets'] ?? -1] ?? 0),
                'tag'       => (string)($row[$idx['tag'] ?? -1] ?? ''),
                'fullname'  => (string)($row[$idx['fullname'] ?? -1] ?? ''),
                'mobile'    => (string)($row[$idx['mobile'] ?? -1] ?? ''),
                'total'     => (int)($row[$idx['total'] ?? -1] ?? 0),
                'ref_id'    => (string)($row[$idx['ref_id'] ?? -1] ?? ''),
                'created_at'=> (string)($row[$idx['created_at'] ?? -1] ?? ''),
                'paid_at'   => (string)($row[$idx['paid_at'] ?? -1] ?? ''),
                'authority' => (string)($row[$idx['authority'] ?? -1] ?? ''),
            ];
        } else {
            $rec = [
                'tickets'=>(int)($row[0]??0),'tag'=>(string)($row[1]??''),'fullname'=>(string)($row[2]??''),
                'mobile'=>(string)($row[3]??''),'total'=>(int)($row[4]??0),'ref_id'=>(string)($row[5]??''),
                'created_at'=>(string)($row[6]??''),'paid_at'=>(string)($row[7]??''),'authority'=>(string)($row[8]??'')
            ];
        }
        $ts = 0; foreach ([$rec['created_at'], $rec['paid_at']] as $d){ if($d){ $t=strtotime($d); if($t){$ts=$t; break;} } }
        $rec['ts'] = $ts; $out[] = $rec;
    }
    fclose($fh);
    return $out;
}

// Mutations for archive: delete/restore single or bulk
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $action = $_POST['action'] ?? '';
    $storage = __DIR__ . DIRECTORY_SEPARATOR . 'storage';
    $arch = $storage . DIRECTORY_SEPARATOR . 'archiev.csv';
    if (!is_file($arch)) { touch($arch); }

    // Load file completely
    $rows = [];
    $header = null; $idx = [];
    if (($fh = fopen($arch, 'r')) !== false) {
        while (($r = fgetcsv($fh)) !== false) {
            if ($header === null && isset($r[0]) && strtolower((string)$r[0]) === 'tickets') { $header = $r; $idx = array_flip(array_map('strtolower', $header)); continue; }
            $rows[] = $r;
        }
        fclose($fh);
    }

    if ($action === 'delete') {
        $tag = trim((string)($_POST['tag'] ?? ''));
        if ($tag === '') { echo json_encode(['ok'=>false,'error'=>'bad_tag']); exit; }
        $new = [];
        foreach ($rows as $r) {
            $rtag = '';
            if ($header) { $rtag = (string)($r[$idx['tag'] ?? -1] ?? ''); } else { $rtag = (string)($r[1] ?? ''); }
            if ($rtag !== $tag) { $new[] = $r; }
        }
        $wf = fopen($arch, 'w');
        if ($header) { fputcsv($wf, $header); }
        foreach ($new as $r) { fputcsv($wf, $r); }
        fclose($wf);
        echo json_encode(['ok'=>true]);
        exit;
    }

    // Restore a single archived record back to participants CSV
    if ($action === 'restore') {
        $tag = trim((string)($_POST['tag'] ?? ''));
        if ($tag === '') { echo json_encode(['ok'=>false,'error'=>'bad_tag']); exit; }
        $new = [];
        $restored = false;
        foreach ($rows as $r) {
            $rtag = '';
            if ($header) { $rtag = (string)($r[$idx['tag'] ?? -1] ?? ''); } else { $rtag = (string)($r[1] ?? ''); }
            if ($rtag === $tag && !$restored) {
                // Append back to appropriate tickets CSV
                $tickets = 0;
                if ($header) { $tickets = (int)($r[$idx['tickets'] ?? -1] ?? 0); } else { $tickets = (int)($r[0] ?? 0); }
                if ($tickets < 1 || $tickets > 4) { $tickets = 1; }
                $dest = $storage . DIRECTORY_SEPARATOR . $tickets . ' tickets.csv';
                $afh = fopen($dest, 'a+');
                $st = fstat($afh); if (($st['size'] ?? 0) === 0) { fputcsv($afh, ['tag','fullname','mobile','total','ref_id','created_at','paid_at','authority']); }
                // Ensure order for destination columns (without tickets column)
                if ($header) {
                    $rowOut = [
                        (string)($r[$idx['tag'] ?? -1] ?? ''),
                        (string)($r[$idx['fullname'] ?? -1] ?? ''),
                        (string)($r[$idx['mobile'] ?? -1] ?? ''),
                        (string)($r[$idx['total'] ?? -1] ?? ''),
                        (string)($r[$idx['ref_id'] ?? -1] ?? ''),
                        (string)($r[$idx['created_at'] ?? -1] ?? ''),
                        (string)($r[$idx['paid_at'] ?? -1] ?? ''),
                        (string)($r[$idx['authority'] ?? -1] ?? ''),
                    ];
                } else {
                    $rowOut = [ (string)($r[1]??''), (string)($r[2]??''), (string)($r[3]??''), (string)($r[4]??''), (string)($r[5]??''), (string)($r[6]??''), (string)($r[7]??''), (string)($r[8]??'') ];
                }
                fputcsv($afh, $rowOut); fclose($afh);
                $restored = true; // skip adding to new (removing from archive)
            } else {
                $new[] = $r;
            }
        }
        // Write remaining archive rows back
        $wf = fopen($arch, 'w'); if ($header) { fputcsv($wf, $header); }
        foreach ($new as $r) { fputcsv($wf, $r); }
        fclose($wf);
        echo json_encode(['ok'=>true, 'restored'=>$restored]);
        exit;
    }

    if ($action === 'bulk') {
        $do = $_POST['do'] ?? '';
        $tags = isset($_POST['tags']) ? (array)$_POST['tags'] : (isset($_POST['tags']) ? (array)$_POST['tags'] : []);
        $tags = array_values(array_map('strval', $tags));
        if (empty($tags)) { echo json_encode(['ok'=>false,'error'=>'bad_request']); exit; }
        $match = array_flip($tags); $new = [];
        $processed = 0;
        // Prepare appenders per tickets for restore
        $appenders = [];
        foreach ($rows as $r) {
            $rtag = '';
            if ($header) { $rtag = (string)($r[$idx['tag'] ?? -1] ?? ''); } else { $rtag = (string)($r[1] ?? ''); }
            if (isset($match[$rtag])) {
                $processed++;
                if ($do === 'delete') {
                    // skip to delete
                    continue;
                } elseif ($do === 'restore') {
                    // append back to tickets file
                    $tickets = 0;
                    if ($header) { $tickets = (int)($r[$idx['tickets'] ?? -1] ?? 0); } else { $tickets = (int)($r[0] ?? 0); }
                    if ($tickets < 1 || $tickets > 4) { $tickets = 1; }
                    if (!isset($appenders[$tickets])) {
                        $dest = $storage . DIRECTORY_SEPARATOR . $tickets . ' tickets.csv';
                        $afh = fopen($dest, 'a+');
                        $st = fstat($afh); if (($st['size'] ?? 0) === 0) { fputcsv($afh, ['tag','fullname','mobile','total','ref_id','created_at','paid_at','authority']); }
                        $appenders[$tickets] = $afh;
                    }
                    if ($header) {
                        $rowOut = [
                            (string)($r[$idx['tag'] ?? -1] ?? ''),
                            (string)($r[$idx['fullname'] ?? -1] ?? ''),
                            (string)($r[$idx['mobile'] ?? -1] ?? ''),
                            (string)($r[$idx['total'] ?? -1] ?? ''),
                            (string)($r[$idx['ref_id'] ?? -1] ?? ''),
                            (string)($r[$idx['created_at'] ?? -1] ?? ''),
                            (string)($r[$idx['paid_at'] ?? -1] ?? ''),
                            (string)($r[$idx['authority'] ?? -1] ?? ''),
                        ];
                    } else {
                        $rowOut = [ (string)($r[1]??''), (string)($r[2]??''), (string)($r[3]??''), (string)($r[4]??''), (string)($r[5]??''), (string)($r[6]??''), (string)($r[7]??''), (string)($r[8]??'') ];
                    }
                    fputcsv($appenders[$tickets], $rowOut);
                    continue; // do not keep in archive
                }
            } else {
                $new[] = $r;
            }
        }
        // Close appenders
        foreach ($appenders as $fh) { fclose($fh); }

        // Write remaining rows back for delete/restore
        $wf = fopen($arch, 'w'); if ($header) { fputcsv($wf, $header); }
        foreach ($new as $r) { fputcsv($wf, $r); }
        fclose($wf);
        echo json_encode(['ok'=>true, 'processed'=>$processed]);
        exit;
    }
}

// Listing
$rows = read_archived();
$q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
$sort = isset($_GET['sort']) ? (string)$_GET['sort'] : 'date_desc';

if ($q !== '') {
    $qNorm = mb_strtolower($q, 'UTF-8');
    $rows = array_values(array_filter($rows, function($r) use ($qNorm){
        $name = mb_strtolower((string)$r['fullname'], 'UTF-8');
        $mob = preg_replace('/\D+/','', (string)$r['mobile']);
        $qd = preg_replace('/\D+/', '', $qNorm);
        return (strpos($name, $qNorm) !== false) || ($qd !== '' && strpos($mob, $qd) !== false);
    }));
}

usort($rows, function($a,$b) use($sort){
    switch ($sort) {
        case 'date_asc': return ($a['ts']<=>$b['ts']) ?: strnatcasecmp($a['fullname'],$b['fullname']);
        case 'name': return strnatcasecmp($a['fullname'],$b['fullname']);
        case 'mobile': return strnatcasecmp((string)$a['mobile'],(string)$b['mobile']);
        case 'date_desc': default: return ($b['ts']<=>$a['ts']) ?: strnatcasecmp($a['fullname'],$b['fullname']);
    }
});

ob_start();
if (empty($rows)) {
    echo '<tr><td colspan="9" class="muted">آیتمی در آرشیو یافت نشد.</td></tr>';
} else {
    foreach ($rows as $row) {
        echo '<tr>';
        echo '<td><input type="checkbox" class="row-check-arch" value="' . htmlspecialchars($row['tag'], ENT_QUOTES, 'UTF-8') . '" /></td>';
        echo '<td>' . htmlspecialchars($row['fullname'], ENT_QUOTES, 'UTF-8') . '</td>';
        $mDisp = mobile_display((string)($row['mobile'] ?? ''));
        $mCopy = mobile_local((string)($row['mobile'] ?? ''));
        echo '<td dir="ltr"><span class="copy" data-copy="' . htmlspecialchars($mCopy, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($mDisp, ENT_QUOTES, 'UTF-8') . '</span></td>';
        echo '<td>' . (int)$row['tickets'] . '</td>';
        echo '<td>' . number_format((int)$row['total']) . '</td>';
        echo '<td><span class="tag copy" data-copy="' . htmlspecialchars($row['tag'], ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($row['tag'], ENT_QUOTES, 'UTF-8') . '</span></td>';
        if (!empty($row['ref_id'])) { echo '<td><span class="tag copy" data-copy="' . htmlspecialchars($row['ref_id'], ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($row['ref_id'], ENT_QUOTES, 'UTF-8') . '</span></td>'; }
        else { echo '<td><span class="muted">-</span></td>'; }
        if (!empty($row['created_at'])) { $d=shamsi_datetime((string)$row['created_at']); echo '<td dir="ltr">' . htmlspecialchars($d, ENT_QUOTES, 'UTF-8') . '</td>'; }
        elseif (!empty($row['paid_at'])) { $d=shamsi_datetime((string)$row['paid_at']); echo '<td dir="ltr">' . htmlspecialchars($d, ENT_QUOTES, 'UTF-8') . '</td>'; }
        else { echo '<td dir="ltr"><span class="muted">-</span></td>'; }
        // tools: restore back to ثبت نامی + delete
        $tag = htmlspecialchars($row['tag'], ENT_QUOTES, 'UTF-8');
        echo '<td>'
           . '<button class="btn" style="width:auto; padding:6px 10px" data-restore="' . $tag . '">بازگردانی</button> '
           . '<button class="btn" style="width:auto; padding:6px 10px" data-delete="' . $tag . '">حذف</button>'
           . '</td>';
        echo '</tr>';
    }
}
$rowsHtml = ob_get_clean();
echo json_encode(['ok'=>true,'count'=>count($rows),'rows_html'=>$rowsHtml], JSON_UNESCAPED_UNICODE);
exit;
