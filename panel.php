<?php
session_start();

// Very simple credentials per request
$ADMIN_USER = 'admin';
$ADMIN_PASS = '12345';

// Handle logout
if (isset($_GET['logout'])) {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params['path'], $params['domain'],
            $params['secure'], $params['httponly']
        );
    }
    session_destroy();
    header('Location: panel.php');
    exit;
}

// Handle login post
$loginError = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['username'], $_POST['password'])) {
    $u = trim((string)$_POST['username']);
    $p = (string)$_POST['password'];
    if ($u === $ADMIN_USER && $p === $ADMIN_PASS) {
        $_SESSION['is_admin'] = true;
        header('Location: panel.php');
        exit;
    } else {
        $loginError = 'Ù†Ø§Ù… Ú©Ø§Ø±Ø¨Ø±ÛŒ ÛŒØ§ Ø±Ù…Ø² Ø¹Ø¨ÙˆØ± Ø§Ø´ØªØ¨Ø§Ù‡ Ø§Ø³Øª';
    }
}

// Helper to read CSV rows from storage for 1..4 ticket groups
function read_participants(): array {
    $base = __DIR__ . DIRECTORY_SEPARATOR . 'storage';
    $all = [];
    for ($n = 1; $n <= 4; $n++) {
        $file = $base . DIRECTORY_SEPARATOR . $n . ' tickets.csv';
        if (!is_file($file)) { continue; }
        if (($fh = fopen($file, 'r')) === false) { continue; }
        $lineNo = 0;
        $header = [];
        while (($row = fgetcsv($fh)) !== false) {
            $lineNo++;
            if ($lineNo === 1 && isset($row[0]) && strtolower((string)$row[0]) === 'tag') {
                $header = array_map('strtolower', $row);
                continue;
            }
            if (!empty($header)) {
                $idx = array_flip($header);
                $tag    = (string)($row[$idx['tag'] ?? -1] ?? '');
                $name   = (string)($row[$idx['fullname'] ?? -1] ?? '');
                $mobile = (string)($row[$idx['mobile'] ?? -1] ?? '');
                $total  = (int)($row[$idx['total'] ?? -1] ?? 0);
                $refId  = (string)($row[$idx['ref_id'] ?? -1] ?? '');
                $created= (string)($row[$idx['created_at'] ?? -1] ?? '');
                $paid   = (string)($row[$idx['paid_at'] ?? -1] ?? '');
                $authority = (string)($row[$idx['authority'] ?? -1] ?? '');
            } else {
                // Legacy without header
                $tag    = (string)($row[0] ?? '');
                $name   = (string)($row[1] ?? '');
                $mobile = (string)($row[2] ?? '');
                $total  = (int)($row[3] ?? 0);
                $refId  = (string)($row[4] ?? '');
                $created= '';
                $paid   = '';
                $authority = '';
            }
            $ts = 0;
            foreach ([$created, $paid] as $d) { if ($d) { $t = strtotime($d); if ($t) { $ts = $t; break; } } }
            $all[] = [
                'tickets'   => $n,
                'tag'       => $tag,
                'fullname'  => $name,
                'mobile'    => $mobile,
                'total'     => $total,
                'ref_id'    => $refId,
                'created_at'=> $created,
                'paid_at'   => $paid,
                'authority' => $authority,
                'ts'        => $ts,
            ];
        }
        fclose($fh);
    }
    return $all;
}

// If not logged in, show login form
if (!($_SESSION['is_admin'] ?? false)) {
    ?>
    <!DOCTYPE html>
    <html lang="fa" dir="rtl">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <title>پنل ثبت نام مسابقات - ÙˆØ±ÙˆØ¯</title>
        <link rel="stylesheet" href="css/style.css">
        <style>
            .login-card { max-width: 420px; }
            .error { color: #b91c1c; background:#fee2e2; border:1px solid #fecaca; padding:10px 12px; border-radius:10px; margin-bottom:10px; }
        </style>
    </head>
    <body>
        <div class="wrap">
            <form class="card login-card" method="post" action="panel.php">
                <h1 class="title">ÙˆØ±ÙˆØ¯ Ø¨Ù‡ پنل ثبت نام مسابقات</h1>
                <p class="sub">Ù„Ø·ÙØ§ Ù†Ø§Ù… Ú©Ø§Ø±Ø¨Ø±ÛŒ Ùˆ Ø±Ù…Ø² Ø¹Ø¨ÙˆØ± Ø±Ø§ ÙˆØ§Ø±Ø¯ Ú©Ù†ÛŒØ¯.</p>
                <?php if ($loginError !== ''): ?><div class="error"><?php echo htmlspecialchars($loginError, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
                <label for="username">Ù†Ø§Ù… Ú©Ø§Ø±Ø¨Ø±ÛŒ</label>
                <input class="ctrl" type="text" id="username" name="username" required placeholder="admin" autocomplete="username" />
                <label for="password" style="margin-top:12px">Ø±Ù…Ø² Ø¹Ø¨ÙˆØ±</label>
                <input class="ctrl" type="password" id="password" name="password" required placeholder="â€¢â€¢â€¢â€¢â€¢" autocomplete="current-password" />
                <button class="btn" type="submit" style="margin-top:16px">ÙˆØ±ÙˆØ¯</button>
                <footer>Ù¾Ù†Ù„ Ø³Ø§Ø¯Ù‡ Ù…Ø¯ÛŒØ±ÛŒØª Ø´Ø±Ú©Øªâ€ŒÚ©Ù†Ù†Ø¯Ù‡â€ŒÙ‡Ø§</footer>
            </form>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Logged in: compute data for participants tab
$participants = read_participants();
$countTotal = count($participants);

// Filters: search and sort
$q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
$sort = isset($_GET['sort']) ? (string)$_GET['sort'] : 'date_desc';

if ($q !== '') {
    $qNorm = mb_strtolower($q, 'UTF-8');
    $participants = array_values(array_filter($participants, function($r) use ($qNorm){
        $name = mb_strtolower((string)($r['fullname'] ?? ''), 'UTF-8');
        $mob  = preg_replace('/\D+/', '', (string)($r['mobile'] ?? ''));
        $qDigits = preg_replace('/\D+/', '', $qNorm);
        return (strpos($name, $qNorm) !== false) || ($qDigits !== '' && strpos($mob, $qDigits) !== false);
    }));
}

usort($participants, function($a, $b) use ($sort){
    switch ($sort) {
        case 'date_asc':
            return ($a['ts'] <=> $b['ts']) ?: strnatcasecmp($a['fullname'], $b['fullname']);
        case 'name':
            return strnatcasecmp($a['fullname'], $b['fullname']);
        case 'mobile':
            return strnatcasecmp((string)$a['mobile'], (string)$b['mobile']);
        case 'date_desc':
        default:
            return ($b['ts'] <=> $a['ts']) ?: strnatcasecmp($a['fullname'], $b['fullname']);
    }
});

$count = count($participants);

// Render admin panel with sidebar and first tab
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>پنل ثبت نام مسابقات</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        @font-face { font-family:'PeydaWebFaNum'; src:url('/fonts/PeydaWebFaNum-Regular.woff2') format('woff2'); font-weight:400; font-style:normal; font-display:swap; }
        @font-face { font-family:'PeydaWebFaNum'; src:url('/fonts/PeydaWebFaNum-Bold.woff2') format('woff2'); font-weight:700; font-style:normal; font-display:swap; }
        :root {
            --sidebar:#0f172a;
            --sidebar-text:#f1f5f9;
            --sidebar-muted:#94a3b8;
            --brand:#C63437;
        }
        body { min-height: 100svh; margin:0; background:#f1f5f9; font-family:'PeydaWebFaNum', sans-serif; }
        .app { display: grid; grid-template-columns: 240px 1fr; min-height: 100svh; }
        .sidebar {
            background: var(--sidebar);
            color: var(--sidebar-text);
            padding: 24px 16px;
        }
        .side-title { font-weight:800; margin: 0 0 16px; font-size:18px; }
        .side-nav a { display:block; padding:10px 12px; border-radius:10px; color:var(--sidebar-text); text-decoration:none; font-weight:700; }
        .side-nav a.active { background:#111827; color:#fff; }
        .side-bottom { position: sticky; bottom: 0; margin-top: 24px; padding-top: 12px; border-top: 1px solid #1f2937; }
        .content { padding: 24px; }
        .content .card { max-width: none; }
        .header-row { display:flex; gap:12px; justify-content: space-between; align-items: center; margin-bottom: 12px; flex-wrap: wrap; }
        .tag { display:inline-block; background:#fee2e2; border:1px solid #fecaca; color:#991b1b; padding:4px 10px; border-radius:999px; font-size:12px; }
        table { width:100%; border-collapse: collapse; }
        th, td { text-align:right; border-bottom:1px solid #f1f5f9; padding:10px 8px; font-size:14px; }
        th { background:#f8fafc; font-weight:800; }
        .muted { color:#6b7280; }
        .logout { color:#fca5a5; }
        .count-box { display:flex; gap:10px; align-items:center; background:#fff7ed; border:1px solid #fed7aa; padding:10px 12px; border-radius:12px; font-weight:800; }
        .csv-hint { font-size:12px; color:#64748b; margin-top:8px; }
        .filters { display:flex; gap:8px; align-items:center; flex-wrap: wrap; margin-top:12px; }
        .filters .ctrl { width:auto; min-width:200px; }
        .copy { cursor:pointer; user-select:none; }
        @media (max-width: 820px){ .app { grid-template-columns: 1fr; } .sidebar { position: sticky; top:0; z-index:2; } }
    </style>
</head>
<body>
    <div class="app">
        <aside class="sidebar">
            <h2 class="side-title">پنل ثبت نام مسابقات</h2>
            <nav class="side-nav">
                <a href="#participants" class="active">لیست ثبت نامی ها</a>
            </nav>
            <div class="side-bottom">
                <a class="side-nav__link logout" href="panel.php?logout=1">خروج از حساب</a>
            </div>
        </aside>
        <main class="content">
            <div class="card">
                <div class="header-row">
                    <h1 class="title" style="margin:0">لیست ثبت نامی ها</h1>
                    <div class="count-box">
                        <span>تعداد ثبت نامی ها</span>
                        <b><?php echo number_format($count); ?></b>
                    </div>
                </div>
                <div class="csv-hint">اطلاعات از فایل‌های CSV در مسیر <code>storage</code> خوانده می‌شود: <code>1 tickets.csv</code> تا <code>4 tickets.csv</code>. شروع بازه از «تاریخ ثبت» و پایان بازه از «تاریخ پرداخت» محاسبه می‌شود.</div>
                <div class="filters">
                    <form method="get" style="display:flex; gap:8px; align-items:center; flex-wrap: wrap;"><input type="hidden" value="1" />
                        <input class="ctrl" type="search" name="q" value="<?php echo htmlspecialchars($q, ENT_QUOTES, 'UTF-8'); ?>" placeholder="بر اساس تلفن ">
                        <select class="ctrl" name="sort">
                            <option value="date_desc" <?php if($sort==='date_desc') echo 'selected'; ?>>بر اساس تاریخ آخرین تا اولین</option>
                            <option value="date_asc"  <?php if($sort==='date_asc')  echo 'selected'; ?>>بر اساس تاریخ اولین تا آخرین</option>
                            <option value="name"      <?php if($sort==='name')      echo 'selected'; ?>>بر اساس اسم</option>
                            <option value="mobile"    <?php if($sort==='mobile')    echo 'selected'; ?>>بر اساس تلفن همراه</option>
                        </select>
                        <button class="btn" style="width:auto; padding:10px 14px">فیلتر کردن</button>
                    </form>
                    <form action="export.php" method="get" style="display:flex; gap:8px; align-items:center; margin-inline-start:auto; flex-wrap: wrap;">
                        <select class="ctrl" name="tickets">
                            <option value="">فیلتر تعداد سهم</option>
                            <option value="1">1 سهم</option>
                            <option value="2">2 سهم</option>
                            <option value="3">3 سهم</option>
                            <option value="4">4 سهم</option>
                        </select>
                        <input class="ctrl" type="date" name="from" placeholder="Ø§Ø² ØªØ§Ø±ÛŒØ®">
                        <input class="ctrl" type="date" name="to" placeholder="ØªØ§ ØªØ§Ø±ÛŒØ®">
                        <button class="btn" style="width:auto; padding:10px 14px">اکسپورت</button>
                    </form>
                </div>
                <div style="overflow:auto; margin-top:12px;">
                    <table>
                        <thead>
                            <tr>
                                <th>ثبت نامی</th>
                                <th>تلفن همراه</th>
                                <th>تعداد سهم</th>
                                <th>پرداخت شده</th>
                                <th>کد رهگیری داخلی</th>
                                <th>کد رهگیری زرین پال</th>
                                <th>تاریخ ثبت نام</th>
                            </tr>
                        </thead>
                        <tbody id="rowsBody">
                        <?php if (empty($participants)): ?>
                            <tr>
                                <td colspan="5" class="muted">Ù‡Ù†ÙˆØ² Ø´Ø±Ú©Øªâ€ŒÚ©Ù†Ù†Ø¯Ù‡â€ŒØ§ÛŒ Ø«Ø¨Øª Ù†Ø´Ø¯Ù‡ Ø§Ø³Øª.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($participants as $row): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['fullname'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td dir="ltr"><?php echo htmlspecialchars($row['mobile'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo (int)$row['tickets']; ?></td>
                                <td><?php echo number_format((int)$row['total']); ?></td>
                                <td><span class="tag copy" data-copy="<?php echo htmlspecialchars($row['tag'], ENT_QUOTES, 'UTF-8'); ?>" title="Ø¨Ø±Ø§ÛŒ Ú©Ù¾ÛŒ Ú©Ù„ÛŒÚ© Ú©Ù†ÛŒØ¯"><?php echo htmlspecialchars($row['tag'], ENT_QUOTES, 'UTF-8'); ?></span></td>
                                <td>
                                  <?php if (!empty($row['ref_id'])): ?>
                                    <span class="tag copy" data-copy="<?php echo htmlspecialchars($row['ref_id'], ENT_QUOTES, 'UTF-8'); ?>" title="Ú©Ù¾ÛŒ Ø±Ù‡Ú¯ÛŒØ±ÛŒ Ø²Ø±ÛŒÙ†â€ŒÙ¾Ø§Ù„"><?php echo htmlspecialchars($row['ref_id'], ENT_QUOTES, 'UTF-8'); ?></span>
                                  <?php else: ?>
                                    <span class="muted">-</span>
                                  <?php endif; ?>
                                </td>
                                <td>
                                  <?php
                                    if (!empty($row['created_at'])) {
                                        echo htmlspecialchars(date('Y-m-d H:i', strtotime($row['created_at'])), ENT_QUOTES, 'UTF-8');
                                    } elseif (!empty($row['paid_at'])) {
                                        echo htmlspecialchars(date('Y-m-d H:i', strtotime($row['paid_at'])), ENT_QUOTES, 'UTF-8');
                                    } else {
                                        echo '<span class="muted">-</span>';
                                    }
                                  ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
    <script>
      document.addEventListener('click', function(e){
        var el = e.target.closest('.copy');
        if(!el) return;
        var val = el.getAttribute('data-copy') || '';
        if(!val) return;
        if (navigator.clipboard && navigator.clipboard.writeText) {
          navigator.clipboard.writeText(val).then(function(){
            var old = el.textContent;
            el.textContent = 'Ú©Ù¾ÛŒ Ø´Ø¯';
            setTimeout(function(){ el.textContent = old; }, 1000);
          });
        }
      });
      // Validate export form dates
      (function(){
        var exp = document.querySelector('.filters form + form');
        if(!exp) return;
        exp.addEventListener('submit', function(ev){
          var from = exp.querySelector('input[name="from"]');
          var to = exp.querySelector('input[name="to"]');
          if(from && to && from.value && to.value && new Date(from.value) > new Date(to.value)){
            ev.preventDefault(); alert('تاریخ شروع نمی‌تواند بعد از تاریخ پایان باشد.');
          }
        });
      })();
    </script>
    <script>
      (function(){
        var tbody = document.getElementById('rowsBody');
        var filtersWrap = document.querySelector('.filters');
        if(!filtersWrap || !tbody) return;
        var formFilter = filtersWrap.querySelector('form');
        var formExport = filtersWrap.querySelector('form + form');
        var q = formFilter.querySelector('input[name="q"]');
        var sort = formFilter.querySelector('select[name="sort"]');
        var tickets = formExport.querySelector('select[name="tickets"]');
        var from = formExport.querySelector('input[name="from"]');
        var to = formExport.querySelector('input[name="to"]');
        var countBox = document.querySelector('.count-box b');

        function params(){
          var p = new URLSearchParams();
          if(q && q.value.trim()!=='') p.set('q', q.value.trim());
          if(sort && sort.value) p.set('sort', sort.value);
          if(tickets && tickets.value) p.set('tickets', tickets.value);
          if(from && from.value) p.set('from', from.value);
          if(to && to.value) p.set('to', to.value);
          return p;
        }
        function validDates(){ if(!from.value || !to.value) return true; return new Date(from.value) <= new Date(to.value); }
        async function refresh(){
          if(!validDates()){ tbody.innerHTML = '<tr><td colspan="8" class="muted">تاریخ شروع نمی‌تواند بعد از تاریخ پایان باشد.</td></tr>'; return; }
          var res = await fetch('panel_data.php?' + params().toString(), {cache:'no-store'});
          var j = await res.json();
          if(j && j.ok){ tbody.innerHTML = j.rows_html; if(countBox) countBox.textContent = j.count; ensureHeader(); }
        }
        var t; if(q){ q.addEventListener('input', function(){ clearTimeout(t); t=setTimeout(refresh, 200); }); }
        if(sort){ sort.addEventListener('change', refresh); }
        if(tickets){ tickets.addEventListener('change', refresh); }
        if(from){ from.addEventListener('change', refresh); }
        if(to){ to.addEventListener('change', refresh); }

        // Add export Excel button
        var xBtn = document.createElement('button'); xBtn.type='button'; xBtn.className='btn'; xBtn.style.cssText='width:auto; padding:10px 14px; background:#1d4ed8; box-shadow:none;'; xBtn.textContent='خروجی Excel';
        formExport.appendChild(xBtn);
        function openExport(url){ window.location.href = url + '?' + params().toString(); }
        xBtn.addEventListener('click', function(){ openExport('export_excel.php'); });

        // Archive action
        document.addEventListener('click', async function(e){
          var btn = e.target.closest('button[data-archive]');
          if(!btn) return;
          var tag = btn.getAttribute('data-archive');
          if(!confirm('این مورد به آرشیو منتقل شود؟')) return;
          var fd = new FormData(); fd.set('action','archive'); fd.set('tag', tag);
          var r = await fetch('panel_data.php', {method:'POST', body: fd});
          var j = await r.json(); if(j && j.ok) refresh(); else alert('خطا در آرشیو');
        });

        function ensureHeader(){
          var thead = document.querySelector('table thead tr');
          if(!thead) return;
          var hasTools = Array.from(thead.children).some(function(th){ var s=(th.textContent||'').trim(); return s==='ابزار' || s==='اقدامات'; });
          if(!hasTools){ var th=document.createElement('th'); th.textContent='ابزار'; thead.appendChild(th); }
        }
        ensureHeader();
        refresh();
      })();
    </script>
</body>
</html>


