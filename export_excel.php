<?php
session_start();
if (!($_SESSION['is_admin'] ?? false)) { http_response_code(403); echo 'Forbidden'; exit; }

function read_participants_export(): array {
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
                $r = [
                    'tickets'=>$n,'tag'=>(string)($row[$idx['tag']??-1]??''),'fullname'=>(string)($row[$idx['fullname']??-1]??''),
                    'mobile'=>(string)($row[$idx['mobile']??-1]??''),'total'=>(int)($row[$idx['total']??-1]??0),'ref_id'=>(string)($row[$idx['ref_id']??-1]??''),
                    'created_at'=>(string)($row[$idx['created_at']??-1]??''),'paid_at'=>(string)($row[$idx['paid_at']??-1]??''),'authority'=>(string)($row[$idx['authority']??-1]??''),
                ];
            } else {
                $r = [ 'tickets'=>$n, 'tag'=>(string)($row[0]??''), 'fullname'=>(string)($row[1]??''), 'mobile'=>(string)($row[2]??''), 'total'=>(int)($row[3]??0), 'ref_id'=>(string)($row[4]??''), 'created_at'=>'','paid_at'=>'','authority'=>'' ];
            }
            $all[] = $r;
        }
        fclose($fh);
    }
    return $all;
}

$tickets = isset($_GET['tickets']) ? trim((string)$_GET['tickets']) : '';
$from = isset($_GET['from']) ? trim((string)$_GET['from']) : '';
$to   = isset($_GET['to'])   ? trim((string)$_GET['to'])   : '';

$rows = read_participants_export();
if ($tickets !== '' && ctype_digit($tickets)) { $t=(int)$tickets; $rows = array_values(array_filter($rows, fn($r)=>(int)$r['tickets']===$t)); }
if ($from !== '' || $to !== '') {
    $fromTs = $from!==''?strtotime($from.' 00:00:00'):null; $toTs=$to!==''?strtotime($to.' 23:59:59'):null;
    $rows = array_values(array_filter($rows,function($r) use($fromTs,$toTs){ $ts=0; foreach ([$r['created_at'],$r['paid_at']] as $d){ if($d){ $t=strtotime($d); if($t){$ts=$t; break;} } } if($fromTs && $ts<$fromTs) return false; if($toTs && $ts>$toTs) return false; return true; }));
}

header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
$fnameParts=['export']; if($tickets!=='') $fnameParts[]=$tickets.'tickets'; if($from!=='') $fnameParts[]='from'.$from; if($to!=='') $fnameParts[]='to'.$to; $filename=implode('_',$fnameParts).'_'.date('Ymd_His').'.xls';
header('Content-Disposition: attachment; filename="'.$filename.'"');
echo "<meta charset='UTF-8'>";
echo "<table border='1'>";
echo '<tr><th>tickets</th><th>tag</th><th>fullname</th><th>mobile</th><th>total</th><th>ref_id</th><th>created_at</th><th>paid_at</th><th>authority</th></tr>';
foreach($rows as $r){
    echo '<tr>';
    echo '<td>'.(int)$r['tickets'].'</td><td>'.htmlspecialchars((string)$r['tag']).'</td><td>'.htmlspecialchars((string)$r['fullname']).'</td><td>'.htmlspecialchars((string)$r['mobile']).'</td><td>'.(int)$r['total'].'</td><td>'.htmlspecialchars((string)($r['ref_id']??'')).'</td><td>'.htmlspecialchars((string)($r['created_at']??'')).'</td><td>'.htmlspecialchars((string)($r['paid_at']??'')).'</td><td>'.htmlspecialchars((string)($r['authority']??'')).'</td>';
    echo '</tr>';
}
echo '</table>';
exit;
