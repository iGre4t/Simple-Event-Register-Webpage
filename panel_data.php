<?php
session_start();
header('Content-Type: application/json; charset=UTF-8');

if (!($_SESSION['is_admin'] ?? false)) {
    http_response_code(403);
    echo json_encode(['ok'=>false,'error'=>'forbidden']);
    exit;
}

// Shared reader copied from panel with enhancements
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
            if ($lineNo === 1 && isset($row[0]) && strtolower((string)$row[0]) === 'tag') { $header = array_map('strtolower',$row); continue; }
            if (!empty($header)) {
                $idx = array_flip($header);
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

// Archive action
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'archive') {
        $tag = trim((string)($_POST['tag'] ?? ''));
        if ($tag === '') { echo json_encode(['ok'=>false,'error'=>'bad_tag']); exit; }
        $rows = read_participants();
        $found = null; $tickets = null; $srcFile = null;
        foreach ($rows as $r) { if ($r['tag'] === $tag) { $found = $r; $tickets = (int)$r['tickets']; break; } }
        if (!$found) { echo json_encode(['ok'=>false,'error'=>'not_found']); exit; }
        $storage = __DIR__ . DIRECTORY_SEPARATOR . 'storage';
        $srcFile = $storage . DIRECTORY_SEPARATOR . $tickets . ' tickets.csv';
        // Re-write source file without the archived row
        $all = [];
        if (($fh = fopen($srcFile, 'r')) !== false) {
            $header = fgetcsv($fh);
            $hasHeader = is_array($header) && strtolower((string)$header[0]) === 'tag';
            if ($hasHeader) { $all[] = $header; }
            while (($row = fgetcsv($fh)) !== false) { if (($row[0] ?? '') !== $tag) { $all[] = $row; } }
            fclose($fh);
        }
        $wf = fopen($srcFile, 'w'); foreach ($all as $r) { fputcsv($wf, $r); } fclose($wf);

        // Append to archiev.csv
        $arch = $storage . DIRECTORY_SEPARATOR . 'archiev.csv';
        $afh = fopen($arch, 'a+');
        $stats = fstat($afh); if (($stats['size'] ?? 0) === 0) { fputcsv($afh, ['tickets','tag','fullname','mobile','total','ref_id','created_at','paid_at','authority']); }
        fputcsv($afh, [ (int)$found['tickets'], $found['tag'],$found['fullname'],$found['mobile'],(int)$found['total'], (string)($found['ref_id']??''),(string)($found['created_at']??''),(string)($found['paid_at']??''), '' ]);
        fclose($afh);
        echo json_encode(['ok'=>true]);
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
        echo json_encode(['ok'=>true,'count'=>0,'rows_html'=>'<tr><td colspan="8" class="muted">تاریخ شروع نمی‌تواند بعد از تاریخ پایان باشد.</td></tr>']);
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

// Build rows HTML
ob_start();
if (empty($participants)) {
    echo '<tr><td colspan="8" class="muted">موردی برای نمایش یافت نشد.</td></tr>';
} else {
    foreach ($participants as $row) {
        echo '<tr>'; 
        echo '<td>' . htmlspecialchars($row['fullname'], ENT_QUOTES, 'UTF-8') . '</td>';
        echo '<td dir="ltr">' . htmlspecialchars($row['mobile'], ENT_QUOTES, 'UTF-8') . '</td>';
        echo '<td>' . (int)$row['tickets'] . '</td>';
        echo '<td>' . number_format((int)$row['total']) . '</td>';
        echo '<td><span class="tag copy" data-copy="' . htmlspecialchars($row['tag'], ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($row['tag'], ENT_QUOTES, 'UTF-8') . '</span></td>';
        if (!empty($row['ref_id'])) {
            echo '<td><span class="tag copy" data-copy="' . htmlspecialchars($row['ref_id'], ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($row['ref_id'], ENT_QUOTES, 'UTF-8') . '</span></td>';
        } else { echo '<td><span class="muted">-</span></td>'; }
        if (!empty($row['created_at'])) {
            echo '<td>' . htmlspecialchars(date('Y-m-d H:i', strtotime($row['created_at'])), ENT_QUOTES, 'UTF-8') . '</td>';
        } elseif (!empty($row['paid_at'])) {
            echo '<td>' . htmlspecialchars(date('Y-m-d H:i', strtotime($row['paid_at'])), ENT_QUOTES, 'UTF-8') . '</td>';
        } else { echo '<td><span class="muted">-</span></td>'; }
        echo '<td><button class="btn" style="width:auto; padding:6px 10px" data-archive="' . htmlspecialchars($row['tag'], ENT_QUOTES, 'UTF-8') . '">آرشیو</button></td>';
        echo '</tr>';
    }
}
$rowsHtml = ob_get_clean();

echo json_encode(['ok'=>true,'count'=>count($participants),'rows_html'=>$rowsHtml], JSON_UNESCAPED_UNICODE);
exit;

