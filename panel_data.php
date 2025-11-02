<?php
session_start();
header('Content-Type: application/json; charset=UTF-8');

if (!($_SESSION['is_admin'] ?? false)) {
    http_response_code(403);
    echo json_encode(['ok'=>false,'error'=>'forbidden']);
    exit;
}

// Normalize a mobile number to 11-digit local format (e.g., 09000000000)
function mobile_local(string $raw): string {
    $d = preg_replace('/\D+/', '', $raw);
    if ($d === null) { $d = ''; }
    if (strpos($d, '0098') === 0) { $d = substr($d, 4); }
    if (strpos($d, '98') === 0)   { $d = substr($d, 2); }
    if ($d !== '' && $d[0] !== '0') {
        if (strlen($d) === 10 && $d[0] === '9') { $d = '0' . $d; }
    }
    if (strlen($d) === 11 && $d[0] === '0') { return $d; }
    return $d;
}

// Human-readable display: +98 9xx xxx xxxx
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
    $gy2 = $gy - 1600; $gm2 = $gm - 1; $gd2 = $gd - 1;
    $g_day_no = 365*$gy2 + (int)(($gy2+3)/4) - (int)(($gy2+99)/100) + (int)(($gy2+399)/400);
    $g_day_no += $g_d_m[$gm2] + $gd2;
    if ($gm2 > 1 && (($gy%4==0 && $gy%100!=0) || ($gy%400==0))) $g_day_no++;
    $j_day_no = $g_day_no - 79;
    $j_np = (int)($j_day_no / 12053);
    $j_day_no %= 12053;
    $jy = 979 + 33*$j_np + 4*(int)($j_day_no/1461);
    $j_day_no %= 1461;
    if ($j_day_no >= 366) { $jy += (int)(($j_day_no-366)/365); $j_day_no = ($j_day_no-366)%365; }
    for ($jm=0; $jm<11 && $j_day_no >= [31,31,31,31,31,31,30,30,30,30,30,29][$jm]; $jm++) {
        $j_day_no -= [31,31,31,31,31,31,30,30,30,30,30,29][$jm];
    }
    $jm += 1; $jd = $j_day_no + 1; return [$jy, $jm, $jd];
}

function shamsi_datetime(string $dateString): string {
    $t = strtotime($dateString);
    if (!$t) return '';
    $gy = (int)date('Y', $t); $gm = (int)date('n', $t); $gd = (int)date('j', $t);
    [$jy,$jm,$jd] = gregorian_to_jalali($gy,$gm,$gd);
    $datePart = sprintf('%04d/%02d/%02d', $jy, $jm, $jd);
    $timePart = date('H:i', $t);
    return $datePart . '  |  ' . $timePart; // clearer separation between date and time
}

// Reader for participants across 1..4 ticket CSV files
function read_participants(): array {
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
                $header = array_map(function($h){ return strtolower(trim((string)$h)); }, $row);
                $idxAlias = [];
                foreach ($header as $i => $h) {
                    $k = preg_replace('/[^a-z0-9]+/', '_', $h);
                    if ($k === 'refid' || $k === 'reference_id' || $k === 'referenceid' || $k === 'ref_id') { $k = 'ref_id'; }
                    if ($k === 'name') { $k = 'fullname'; }
                    $idxAlias[$k] = $i;
                }
                continue;
            }
            if (!empty($header)) {
                $idx = isset($idxAlias) && is_array($idxAlias) ? $idxAlias : array_flip($header);
                $rec = [
                    'tickets'   => $n,
                    'tag'       => (string)($row[$idx['tag'] ?? -1] ?? ''),
                    'fullname'  => (string)($row[$idx['fullname'] ?? -1] ?? ''),
                    'mobile'    => (string)($row[$idx['mobile'] ?? -1] ?? ''),
                    'total'     => (int)($row[$idx['total'] ?? -1] ?? 0),
                    'ref_id'    => (string)($row[$idx['ref_id'] ?? -1] ?? ''),
                    'created_at'=> (string)($row[$idx['created_at'] ?? -1] ?? ''),
                    'paid_at'   => (string)($row[$idx['paid_at'] ?? -1] ?? ''),
                ];
                if ($rec['ref_id'] === '' && count($row) >= 5) { $rec['ref_id'] = (string)$row[4]; }
                if ($rec['created_at'] === '' && count($row) >= 6) { $rec['created_at'] = (string)$row[5]; }
                if ($rec['paid_at'] === '' && count($row) >= 7)    { $rec['paid_at']    = (string)$row[6]; }
                $rec['authority'] = (string)($row[$idx['authority'] ?? -1] ?? '');
                if ($rec['authority'] === '' && count($row) >= 8)  { $rec['authority']  = (string)$row[7]; }
                if ($rec['ref_id'] === '' && $rec['authority'] !== '') {
                    $completed = __DIR__ . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'completed' . DIRECTORY_SEPARATOR . $rec['authority'] . '.json';
                    if (is_file($completed)) {
                        $cj = json_decode(@file_get_contents($completed), true);
                        if (is_array($cj) && !empty($cj['ref_id'])) { $rec['ref_id'] = (string)$cj['ref_id']; }
                    }
                }
            } else {
                $rec = [
                    'tickets'=>$n,'tag'=>(string)($row[0]??''),'fullname'=>(string)($row[1]??''),
                    'mobile'=>(string)($row[2]??''),'total'=>(int)($row[3]??0),'ref_id'=>(string)($row[4]??''),
                    'created_at'=>'','paid_at'=>''
                ];
            }
            $ts = 0; foreach ([$rec['created_at'], $rec['paid_at']] as $d){ if($d){ $t=strtotime($d); if($t){$ts=$t; break;} } }
            $rec['ts'] = $ts; $all[] = $rec;
        }
        fclose($fh);
    }
    return $all;
}

// Mutations
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $action = $_POST['action'] ?? '';
    // Single archive
    if ($action === 'archive') {
        $tag = trim((string)($_POST['tag'] ?? ''));
        if ($tag === '') { echo json_encode(['ok'=>false,'error'=>'bad_tag']); exit; }

        $rows = read_participants();
        $found = null; $tickets = null; $srcFile = null;
        foreach ($rows as $r) { if ($r['tag'] === $tag) { $found = $r; $tickets = (int)$r['tickets']; break; } }
        if (!$found) { echo json_encode(['ok'=>false,'error'=>'not_found']); exit; }
        $storage = __DIR__ . DIRECTORY_SEPARATOR . 'storage';
        $srcFile = $storage . DIRECTORY_SEPARATOR . $tickets . ' tickets.csv';
        // Rewrite source without the row
        $all = [];
        if (($fh = fopen($srcFile, 'r')) !== false) {
            $header = fgetcsv($fh);
            $hasHeader = is_array($header) && strtolower((string)$header[0]) === 'tag';
            if ($hasHeader) { $all[] = $header; }
            while (($row = fgetcsv($fh)) !== false) { if (($row[0] ?? '') !== $tag) { $all[] = $row; } }
            fclose($fh);
        }
        $wf = fopen($srcFile, 'w'); foreach ($all as $r) { fputcsv($wf, $r); } fclose($wf);

        // Append to archive file
        $arch = $storage . DIRECTORY_SEPARATOR . 'archiev.csv';
        $afh = fopen($arch, 'a+');
        $stats = fstat($afh); if (($stats['size'] ?? 0) === 0) { fputcsv($afh, ['tickets','tag','fullname','mobile','total','ref_id','created_at','paid_at','authority']); }
        fputcsv($afh, [ (int)$found['tickets'], $found['tag'],$found['fullname'],$found['mobile'],(int)$found['total'], (string)($found['ref_id']??''),(string)($found['created_at']??''),(string)($found['paid_at']??''), '' ]);
        fclose($afh);
        echo json_encode(['ok'=>true]);
        exit;
    }

    // Single hard delete (no archive)
    if ($action === 'delete') {
        $tag = trim((string)($_POST['tag'] ?? ''));
        if ($tag === '') { echo json_encode(['ok'=>false,'error'=>'bad_tag']); exit; }
        $rows = read_participants();
        $found = null; $tickets = null;
        foreach ($rows as $r) { if ($r['tag'] === $tag) { $found = $r; $tickets = (int)$r['tickets']; break; } }
        if (!$found) { echo json_encode(['ok'=>false,'error'=>'not_found']); exit; }
        $storage = __DIR__ . DIRECTORY_SEPARATOR . 'storage';
        $srcFile = $storage . DIRECTORY_SEPARATOR . $tickets . ' tickets.csv';
        $all = [];
        if (($fh = fopen($srcFile, 'r')) !== false) {
            $header = fgetcsv($fh);
            $hasHeader = is_array($header) && strtolower((string)$header[0]) === 'tag';
            if ($hasHeader) { $all[] = $header; }
            while (($row = fgetcsv($fh)) !== false) { if (($row[0] ?? '') !== $tag) { $all[] = $row; } }
            fclose($fh);
        }
        $wf = fopen($srcFile, 'w'); foreach ($all as $r) { fputcsv($wf, $r); } fclose($wf);
        echo json_encode(['ok'=>true]);
        exit;
    }

    // Bulk operations: archive or delete
    if ($action === 'bulk') {
        $do = $_POST['do'] ?? '';
        $tags = [];
        if (isset($_POST['tags']) && is_array($_POST['tags'])) { $tags = array_map('strval', $_POST['tags']); }
        elseif (isset($_POST['tags'])) {
            $raw = (string)$_POST['tags'];
            $tags = array_filter(array_map('trim', preg_split('/[\s,]+/', $raw)));
        }
        $tags = array_values(array_unique(array_filter($tags, fn($t)=>$t!=='')));
        if (empty($tags)) { echo json_encode(['ok'=>false,'error'=>'no_tags']); exit; }

        $rows = read_participants();
        $byTag = [];
        foreach ($rows as $r) { $byTag[$r['tag']] = $r; }
        $storage = __DIR__ . DIRECTORY_SEPARATOR . 'storage';
        $changedFiles = [];
        $archData = [];
        foreach ($tags as $tag) {
            if (!isset($byTag[$tag])) { continue; }
            $rec = $byTag[$tag]; $tickets=(int)$rec['tickets'];
            $srcFile = $storage . DIRECTORY_SEPARATOR . $tickets . ' tickets.csv';
            if (!isset($changedFiles[$srcFile])) {
                $changedFiles[$srcFile] = ['header'=>null,'rows'=>[]];
                if (($fh=fopen($srcFile,'r'))!==false) {
                    $header = fgetcsv($fh); $hasHeader = is_array($header) && strtolower((string)$header[0])==='tag';
                    if ($hasHeader) { $changedFiles[$srcFile]['header']=$header; }
                    while(($row=fgetcsv($fh))!==false){ $changedFiles[$srcFile]['rows'][]=$row; }
                    fclose($fh);
                }
            }
            // Remove this tag from the cached rows
            $changedFiles[$srcFile]['rows'] = array_values(array_filter($changedFiles[$srcFile]['rows'], function($rr) use($tag){ return ($rr[0]??'') !== $tag; }));
            if ($do === 'archive') {
                $archData[] = [ (int)$rec['tickets'], $rec['tag'],$rec['fullname'],$rec['mobile'],(int)$rec['total'], (string)($rec['ref_id']??''),(string)($rec['created_at']??''),(string)($rec['paid_at']??''), '' ];
            }
        }
        // Write back changed CSV files
        foreach ($changedFiles as $file => $data) {
            $wf = fopen($file, 'w');
            if ($data['header']) { fputcsv($wf, $data['header']); }
            foreach ($data['rows'] as $r) { fputcsv($wf, $r); }
            fclose($wf);
        }
        // Append archived rows
        if ($do === 'archive' && !empty($archData)) {
            $arch = $storage . DIRECTORY_SEPARATOR . 'archiev.csv';
            $afh = fopen($arch, 'a+');
            $stats = fstat($afh); if (($stats['size'] ?? 0) === 0) { fputcsv($afh, ['tickets','tag','fullname','mobile','total','ref_id','created_at','paid_at','authority']); }
            foreach ($archData as $r) { fputcsv($afh, $r); }
            fclose($afh);
        }
        echo json_encode(['ok'=>true, 'processed'=>count($tags)]);
        exit;
    }
}

// Listing
$participants = read_participants();
$q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
$sort = isset($_GET['sort']) ? (string)$_GET['sort'] : 'date_desc';
$tickets = isset($_GET['tickets']) ? trim((string)$_GET['tickets']) : '';
$from = isset($_GET['from']) ? trim((string)$_GET['from']) : '';
$to   = isset($_GET['to'])   ? trim((string)$_GET['to'])   : '';

if ($q !== '') {
    $qNorm = mb_strtolower($q, 'UTF-8');
    $participants = array_values(array_filter($participants, function($r) use ($qNorm){
        $name = mb_strtolower((string)$r['fullname'], 'UTF-8');
        $mob = preg_replace('/\D+/','', (string)$r['mobile']);
        $qd = preg_replace('/\D+/', '', $qNorm);
        return (strpos($name, $qNorm) !== false) || ($qd !== '' && strpos($mob, $qd) !== false);
    }));
}

if ($tickets !== '' && ctype_digit($tickets)) {
    $t = (int)$tickets; $participants = array_values(array_filter($participants, fn($r)=>(int)$r['tickets']===$t));
}

if ($from !== '' || $to !== '') {
    $fromTs = $from !== '' ? strtotime($from.' 00:00:00') : null;
    $toTs = $to !== '' ? strtotime($to.' 23:59:59') : null;
    if ($fromTs && $toTs && $fromTs > $toTs) {
        echo json_encode(['ok'=>true,'count'=>0,'rows_html'=>'<tr><td colspan="9" class="muted">بازه تاریخ نامعتبر است؛ ابتدا باید از تاریخ کمتر باشد.</td></tr>']);
        exit;
    }
    $participants = array_values(array_filter($participants, function($r) use ($fromTs,$toTs){
        $ts = (int)($r['ts'] ?? 0);
        if ($fromTs && $ts < $fromTs) return false; if ($toTs && $ts > $toTs) return false; return true;
    }));
}

usort($participants, function($a,$b) use($sort){
    switch ($sort) {
        case 'date_asc': return ($a['ts']<=>$b['ts']) ?: strnatcasecmp($a['fullname'],$b['fullname']);
        case 'name': return strnatcasecmp($a['fullname'],$b['fullname']);
        case 'mobile': return strnatcasecmp((string)$a['mobile'],(string)$b['mobile']);
        case 'date_desc': default: return ($b['ts']<=>$a['ts']) ?: strnatcasecmp($a['fullname'],$b['fullname']);
    }
});

// Build rows HTML (includes selection + tools)
ob_start();
if (empty($participants)) {
    echo '<tr><td colspan="9" class="muted">رکوردی پیدا نشد.</td></tr>';
} else {
    foreach ($participants as $row) {
        echo '<tr>';
        // selection checkbox
        echo '<td><input type="checkbox" class="row-check" value="' . htmlspecialchars($row['tag'], ENT_QUOTES, 'UTF-8') . '" /></td>';
        echo '<td>' . htmlspecialchars($row['fullname'], ENT_QUOTES, 'UTF-8') . '</td>';
        $mDisp = mobile_display((string)($row['mobile'] ?? ''));
        $mCopy = mobile_local((string)($row['mobile'] ?? ''));
        echo '<td dir="ltr"><span class="copy" data-copy="' . htmlspecialchars($mCopy, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($mDisp, ENT_QUOTES, 'UTF-8') . '</span></td>';
        echo '<td>' . (int)$row['tickets'] . '</td>';
        echo '<td>' . number_format((int)$row['total']) . '</td>';
        echo '<td><span class="tag copy" data-copy="' . htmlspecialchars($row['tag'], ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($row['tag'], ENT_QUOTES, 'UTF-8') . '</span></td>';
        if (!empty($row['ref_id'])) {
            echo '<td><span class="tag copy" data-copy="' . htmlspecialchars($row['ref_id'], ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($row['ref_id'], ENT_QUOTES, 'UTF-8') . '</span></td>';
        } else { echo '<td><span class="muted">-</span></td>'; }
        if (!empty($row['created_at'])) {
            $d = shamsi_datetime((string)$row['created_at']);
            echo '<td dir="ltr">' . htmlspecialchars($d, ENT_QUOTES, 'UTF-8') . '</td>';
        } elseif (!empty($row['paid_at'])) {
            $d = shamsi_datetime((string)$row['paid_at']);
            echo '<td dir="ltr">' . htmlspecialchars($d, ENT_QUOTES, 'UTF-8') . '</td>';
        } else { echo '<td dir="ltr"><span class="muted">-</span></td>'; }
        // tools: archive + delete
        $tag = htmlspecialchars($row['tag'], ENT_QUOTES, 'UTF-8');
        echo '<td>'
           . '<button class="btn" style="width:auto; padding:6px 10px" data-archive="' . $tag . '">آرشیو</button> '
           . '<button class="btn" style="width:auto; padding:6px 10px" data-delete="' . $tag . '">حذف</button>'
           . '</td>';
        echo '</tr>';
    }
}
$rowsHtml = ob_get_clean();

echo json_encode(['ok'=>true,'count'=>count($participants),'rows_html'=>$rowsHtml], JSON_UNESCAPED_UNICODE);
exit;
