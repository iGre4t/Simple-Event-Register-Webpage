<?php
session_start();
// Ensure UTF-8 output to avoid mojibake
header('Content-Type: text/html; charset=UTF-8');

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

// Convert Latin digits to Persian digits for UI display
function fa_digits(string $value): string {
    static $map = [
        '0' => '۰','1' => '۱','2' => '۲','3' => '۳','4' => '۴',
        '5' => '۵','6' => '۶','7' => '۷','8' => '۸','9' => '۹'
    ];
    return strtr($value, $map);
}

// Normalize a mobile number to 11‑digit local format (e.g., 09000000000)
function mobile_local(string $raw): string {
    $d = preg_replace('/\D+/', '', $raw);
    if ($d === null) { $d = ''; }
    // Strip common Iran country code prefixes
    if (strpos($d, '0098') === 0) { $d = substr($d, 4); }
    if (strpos($d, '98') === 0)   { $d = substr($d, 2); }
    // Ensure leading 0 for local format
    if ($d !== '' && $d[0] !== '0') {
        if (strlen($d) === 10 && $d[0] === '9') {
            $d = '0' . $d;
        }
    }
    // Return only if it looks like a valid local mobile (11 digits starting with 0)
    if (strlen($d) === 11 && $d[0] === '0') {
        return $d;
    }
    // Fallback: return the raw digits (may be empty)
    return $d;
}

// Human‑readable display: +98 9xx xxx xxxx (from a raw input)
function mobile_display(string $raw): string {
    $local = mobile_local($raw);
    if (strlen($local) === 11 && $local[0] === '0') {
        $ten = substr($local, 1); // strip leading 0
        return '+98 ' . substr($ten, 0, 3) . ' ' . substr($ten, 3, 3) . ' ' . substr($ten, 6, 4);
    }
    // Fallback to original value when format is unknown
    return (string)$raw;
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
    <!-- Persian datepicker assets -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/persian-datepicker@1.2.0/dist/css/persian-datepicker.min.css">
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/persian-date@1.1.0/dist/persian-date.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/persian-datepicker@1.2.0/dist/js/persian-datepicker.min.js"></script>
    <!-- Minimal icon set -->
    <script src="https://unpkg.com/feather-icons"></script>
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
    <!-- Persian datepicker assets for admin panel -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/persian-datepicker@1.2.0/dist/css/persian-datepicker.min.css">
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/persian-date@1.1.0/dist/persian-date.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/persian-datepicker@1.2.0/dist/js/persian-datepicker.min.js"></script>
    <style>
        @font-face {
            font-family:'PeydaWebFaNum';
            src:
              url('fonts/PeydaWebFaNum-Regular.woff2') format('woff2'),
              url('../fonts/PeydaWebFaNum-Regular.woff2') format('woff2'),
              url('/fonts/PeydaWebFaNum-Regular.woff2') format('woff2');
            font-weight:400; font-style:normal; font-display:swap;
        }
        @font-face {
            font-family:'PeydaWebFaNum';
            src:
              url('fonts/PeydaWebFaNum-Bold.woff2') format('woff2'),
              url('../fonts/PeydaWebFaNum-Bold.woff2') format('woff2'),
              url('/fonts/PeydaWebFaNum-Bold.woff2') format('woff2');
            font-weight:700; font-style:normal; font-display:swap;
        }
        :root {
            --sidebar:#0f172a;
            --sidebar-text:#f1f5f9;
            --sidebar-muted:#94a3b8;
            --brand:#C63437;
        }
        body { min-height: 100svh; margin:0; background:#f1f5f9; font-family:'Peyda', 'PeydaWebFaNum', 'IRANSans', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; }
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
        /* Hide the redundant filter button in the first form */
        .filters form:not([action]) button.btn { display: none; }
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
                        <b><?php echo fa_digits(number_format($count)); ?></b>
                    </div>
                </div>
                <!--
                <div class="csv-hint">اطلاعات از فایل‌های CSV در مسیر <code>storage</code> خوانده می‌شود: <code>1 tickets.csv</code> تا <code>4 tickets.csv</code>. شروع بازه از «تاریخ ثبت» و پایان بازه از «تاریخ پرداخت» محاسبه می‌شود.</div>
                -->
                <div class="filters">
                    <form method="get" style="display:flex; gap:8px; align-items:center; flex-wrap: wrap;"><input type="hidden" value="1" />
                        <input class="ctrl" type="search" name="q" value="<?php echo htmlspecialchars($q, ENT_QUOTES, 'UTF-8'); ?>" placeholder="جستجو ">
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
                        <button class="btn" style="width:auto; padding:10px 14px">خروجی CSV</button>
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
                                <td dir="ltr">
                                  <?php
                                    $mDisp = mobile_display((string)($row['mobile'] ?? ''));
                                    $mCopy = mobile_local((string)($row['mobile'] ?? ''));
                                  ?>
                                  <span class="copy" data-copy="<?php echo htmlspecialchars($mCopy, ENT_QUOTES, 'UTF-8'); ?>" title="کپی شماره ۱۱ رقمی">
                                    <?php echo htmlspecialchars($mDisp, ENT_QUOTES, 'UTF-8'); ?>
                                  </span>
                                </td>
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
        // JS helpers to render Persian digits in the UI
        function toFaDigits(str){
          if(!str) return str;
          return String(str).replace(/\d/g, function(d){ return '۰۱۲۳۴۵۶۷۸۹'.charAt(parseInt(d,10)); });
        }
        function convertTreeToFa(root){
          if(!root) return;
          var walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT, null);
          var node; var changes=[];
          while((node = walker.nextNode())){
            var t = node.nodeValue;
            if(t && /\d/.test(t)) changes.push(node);
          }
          changes.forEach(function(n){ n.nodeValue = toFaDigits(n.nodeValue); });
        }
        var tbody = document.getElementById('rowsBody');
        var filtersWrap = document.querySelector('.filters');
        if(!filtersWrap || !tbody) return;
        var formFilter = filtersWrap.querySelector('form');
        var formExport = filtersWrap.querySelector('form + form');
        var q = formFilter.querySelector('input[name="q"]');
        var sort = formFilter.querySelector('select[name="sort"]');
        var tickets = formExport.querySelector('select[name="tickets"]');
        // Enhance date fields: add Shamsi inputs and hidden Gregorian values
        (function enhanceShamsi(){
          if(!formExport) return;
          var oldFrom = formExport.querySelector('input[type="date"][name="from"]');
          var oldTo   = formExport.querySelector('input[type="date"][name="to"]');
          if(!oldFrom || !oldTo) return; // already enhanced
          var hFrom = document.createElement('input'); hFrom.type='hidden'; hFrom.name='from';
          var hTo   = document.createElement('input'); hTo.type='hidden';   hTo.name='to';
          var lFrom = document.createElement('label'); lFrom.htmlFor='from_sh'; lFrom.textContent='تاریخ شروع'; lFrom.style.fontWeight='700';
          var vFrom = document.createElement('input'); vFrom.type='text'; vFrom.id='from_sh'; vFrom.name='from_sh'; vFrom.className='ctrl shamsi'; vFrom.placeholder='مثال: ۱۴۰۳/۰۸/۱۱'; vFrom.autocomplete='off'; vFrom.setAttribute('inputmode','numeric');
          var lTo   = document.createElement('label'); lTo.htmlFor='to_sh';   lTo.textContent='تاریخ پایان'; lTo.style.fontWeight='700';
          var vTo   = document.createElement('input'); vTo.type='text'; vTo.id='to_sh';   vTo.name='to_sh';   vTo.className='ctrl shamsi'; vTo.placeholder='مثال: ۱۴۰۳/۰۸/۱۲'; vTo.autocomplete='off'; vTo.setAttribute('inputmode','numeric');
          formExport.insertBefore(lFrom, oldFrom);
          formExport.insertBefore(vFrom, oldFrom);
          formExport.insertBefore(hFrom, oldFrom);
          formExport.insertBefore(lTo, oldTo);
          formExport.insertBefore(vTo, oldTo);
          formExport.insertBefore(hTo, oldTo);
          oldFrom.name = 'from_old'; oldFrom.style.display='none';
          oldTo.name   = 'to_old';   oldTo.style.display='none';
        })();
        var from = formExport.querySelector('input[name="from"]');
        var to = formExport.querySelector('input[name="to"]');
        var fromSh = formExport.querySelector('input[name="from_sh"]');
        var toSh = formExport.querySelector('input[name="to_sh"]');
        var fromOld = formExport.querySelector('input[name="from_old"]');
        var toOld   = formExport.querySelector('input[name="to_old"]');
        // Open calendar on click/focus for Shamsi fields
        function wireOpenCalendar(input){
          if(!input) return;
          try {
            input.addEventListener('focus', function(){ if(window.jQuery){ try{ jQuery(input).trigger('click'); }catch(e){} } });
            input.addEventListener('click', function(){ if(window.jQuery){ try{ jQuery(input).trigger('click'); }catch(e){} } });
          } catch(e){}
        }
        wireOpenCalendar(fromSh);
        wireOpenCalendar(toSh);
        // When user clears Shamsi fields manually, clear hidden Gregorian values and refresh
        function syncClearOnEmpty(shInput, hiddenInput){
          if(!shInput || !hiddenInput) return;
          function check(){
            var v = (shInput.value || '').trim();
            if(v === ''){
              hiddenInput.value = '';
              hiddenInput.dispatchEvent(new Event('change', {bubbles:true}));
              try { refresh(); } catch(e){}
            }
          }
          shInput.addEventListener('input', check);
          shInput.addEventListener('change', check);
          shInput.addEventListener('keyup', function(e){ if(e.key === 'Backspace' || e.key === 'Delete') check(); });
        }
        syncClearOnEmpty(fromSh, from);
        syncClearOnEmpty(toSh, to);
        // For native date inputs, attempt to open the picker on focus/click
        function wireNativeDatePicker(input){
          if(!input) return;
          function open(){ try{ if(typeof input.showPicker === 'function'){ input.showPicker(); } }catch(e){} }
          input.addEventListener('focus', open);
          input.addEventListener('click', open);
        }
        wireNativeDatePicker(from);
        wireNativeDatePicker(to);
        // Keep end >= start whenever hidden Gregorian values change
        function enforceOrder(){
          if (from && to && from.value && to.value) {
            var f = new Date(from.value); var t = new Date(to.value);
            if (!isNaN(f) && !isNaN(t) && f.getTime() > t.getTime()) {
              to.value = from.value;
              if (toSh && window.persianDate) {
                try { toSh.value = new persianDate(f.getTime()).toCalendar('persian').toLocale('fa').format('YYYY/MM/DD'); } catch(e){}
              }
            }
          }
        }
        if (from) from.addEventListener('change', enforceOrder);
        if (to)   to.addEventListener('change',   enforceOrder);
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
        function decorateArchiveButtons(){
          var btns = document.querySelectorAll('button[data-archive]');
          btns.forEach(function(b){
            b.classList.add('btn-minimal','btn-red','btn-sm');
            b.innerHTML = '<i data-feather="archive"></i><span>آرشیو</span>';
          });
          try { if (window.feather) { feather.replace({width:16,height:16}); } } catch(e){}
        }
        async function refresh(){
          if(!validDates()){ tbody.innerHTML = '<tr><td colspan="8" class="muted">تاریخ شروع نمی‌تواند بعد از تاریخ پایان باشد.</td></tr>'; return; }
          var res = await fetch('panel_data.php?' + params().toString(), {cache:'no-store'});
          var j = await res.json();
          if(j && j.ok){
            tbody.innerHTML = j.rows_html;
            convertTreeToFa(tbody);
            if(countBox) countBox.textContent = toFaDigits(j.count);
            ensureHeader();
            decorateArchiveButtons();
          }
        }
        var t; if(q){ q.addEventListener('input', function(){ clearTimeout(t); t=setTimeout(refresh, 200); }); }
        if(sort){ sort.addEventListener('change', refresh); }
        if(tickets){ tickets.addEventListener('change', refresh); }
        if(from){ from.addEventListener('change', refresh); }
        if(to){ to.addEventListener('change', refresh); }
        // Initialize Persian datepickers and keep hidden Gregorian in sync
        try {
          if (window.jQuery && window.persianDate && jQuery.fn.persianDatepicker) {
            function setHiddenFromUnix(targetHidden, unix){
              try {
                var g = new persianDate(unix).toCalendar('gregorian').toLocale('en').format('YYYY-MM-DD');
                targetHidden.value = g;
                targetHidden.dispatchEvent(new Event('change', {bubbles:true}));
              } catch(e){}
            }
            if (fromSh) {
              jQuery(fromSh).persianDatepicker({
                initialValue: false,
                format: 'YYYY/MM/DD',
                autoClose: true,
                calendar: { persian: { locale: 'fa' } },
                onSelect: function(unix){
                  try { if (fromSh) fromSh.value = new persianDate(unix).toCalendar('persian').toLocale('fa').format('YYYY/MM/DD'); } catch(e){}
                  setHiddenFromUnix(from, unix); enforceOrder();
                }
              });
            }
            if (toSh) {
              jQuery(toSh).persianDatepicker({
                initialValue: false,
                format: 'YYYY/MM/DD',
                autoClose: true,
                calendar: { persian: { locale: 'fa' } },
                onSelect: function(unix){
                  try { if (toSh) toSh.value = new persianDate(unix).toCalendar('persian').toLocale('fa').format('YYYY/MM/DD'); } catch(e){}
                  setHiddenFromUnix(to, unix); enforceOrder();
                }
              });
            }
            // Prefill visible Shamsi if Gregorian values already present (e.g., via URL)
            try {
              if (from && from.value && fromSh) {
                var fx = new Date(from.value + 'T00:00:00');
                if(!isNaN(fx)) fromSh.value = new persianDate(fx.getTime()).toCalendar('persian').toLocale('fa').format('YYYY/MM/DD');
              }
              if (to && to.value && toSh) {
                var tx = new Date(to.value + 'T00:00:00');
                if(!isNaN(tx)) toSh.value = new persianDate(tx.getTime()).toCalendar('persian').toLocale('fa').format('YYYY/MM/DD');
              }
            } catch(e){}
          }
        } catch(e){}

        // Add export Excel button
        var xBtn = document.createElement('button'); xBtn.type='button'; xBtn.className='btn btn-minimal'; xBtn.style.cssText=''; xBtn.textContent='خروجی Excel';
        formExport.appendChild(xBtn);
        function openExport(url){ window.location.href = url + '?' + params().toString(); }
        xBtn.addEventListener('click', function(){ openExport('export_excel.php'); });
        // Enhance export buttons with icons and unify style
        try { if (xBtn) { xBtn.innerHTML = '<i data-feather="download"></i><span>خروجی Excel</span>'; } } catch(e){}
        var csvBtn = formExport.querySelector('button[type="submit"], button.btn');
        if (csvBtn) {
          csvBtn.classList.add('btn-minimal');
          csvBtn.innerHTML = '<i data-feather="download"></i><span>خروجی CSV</span>';
        }
        try { if (window.feather) { feather.replace({width:16,height:16}); } } catch(e){}

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
        convertTreeToFa(document.body);
        refresh();
      })();
    </script>
</body>
</html>


