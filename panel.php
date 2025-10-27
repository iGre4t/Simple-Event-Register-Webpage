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
        $loginError = 'نام کاربری یا رمز عبور اشتباه است';
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
        while (($row = fgetcsv($fh)) !== false) {
            $lineNo++;
            // Skip header if present
            if ($lineNo === 1 && isset($row[0]) && strtolower((string)$row[0]) === 'tag') { continue; }
            $all[] = [
                'tickets'  => $n,
                'tag'      => (string)($row[0] ?? ''),
                'fullname' => (string)($row[1] ?? ''),
                'mobile'   => (string)($row[2] ?? ''),
                'total'    => (int)($row[3] ?? 0),
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
        <title>پنل مدیریت - ورود</title>
        <link rel="stylesheet" href="css/style.css">
        <style>
            .login-card { max-width: 420px; }
            .error { color: #b91c1c; background:#fee2e2; border:1px solid #fecaca; padding:10px 12px; border-radius:10px; margin-bottom:10px; }
        </style>
    </head>
    <body>
        <div class="wrap">
            <form class="card login-card" method="post" action="panel.php">
                <h1 class="title">ورود به پنل مدیریت</h1>
                <p class="sub">لطفا نام کاربری و رمز عبور را وارد کنید.</p>
                <?php if ($loginError !== ''): ?><div class="error"><?php echo htmlspecialchars($loginError, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
                <label for="username">نام کاربری</label>
                <input class="ctrl" type="text" id="username" name="username" required placeholder="admin" autocomplete="username" />
                <label for="password" style="margin-top:12px">رمز عبور</label>
                <input class="ctrl" type="password" id="password" name="password" required placeholder="•••••" autocomplete="current-password" />
                <button class="btn" type="submit" style="margin-top:16px">ورود</button>
                <footer>پنل ساده مدیریت شرکت‌کننده‌ها</footer>
            </form>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Logged in: compute data for participants tab
$participants = read_participants();
$count = count($participants);

// Sort newest last-modified? Keep as read order; optionally sort by tickets desc then name
usort($participants, function($a, $b){
    if ($a['tickets'] === $b['tickets']) { return strnatcasecmp($a['fullname'], $b['fullname']); }
    return $b['tickets'] <=> $a['tickets'];
});

// Render admin panel with sidebar and first tab
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>پنل مدیریت</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        :root {
            --sidebar:#0f172a;
            --sidebar-text:#f1f5f9;
            --sidebar-muted:#94a3b8;
            --brand:#C63437;
        }
        body { min-height: 100svh; margin:0; background:#f1f5f9; }
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
        .header-row { display:flex; gap:12px; justify-content: space-between; align-items: center; margin-bottom: 12px; }
        .tag { display:inline-block; background:#fee2e2; border:1px solid #fecaca; color:#991b1b; padding:4px 10px; border-radius:999px; font-size:12px; }
        table { width:100%; border-collapse: collapse; }
        th, td { text-align:right; border-bottom:1px solid #f1f5f9; padding:10px 8px; font-size:14px; }
        th { background:#f8fafc; font-weight:800; }
        .muted { color:#6b7280; }
        .logout { color:#fca5a5; }
        .count-box { display:flex; gap:10px; align-items:center; background:#fff7ed; border:1px solid #fed7aa; padding:10px 12px; border-radius:12px; font-weight:800; }
        .csv-hint { font-size:12px; color:#64748b; margin-top:8px; }
    </style>
</head>
<body>
    <div class="app">
        <aside class="sidebar">
            <h2 class="side-title">پنل مدیریت</h2>
            <nav class="side-nav">
                <a href="#participants" class="active">شرکت کننده ها</a>
            </nav>
            <div class="side-bottom">
                <a class="side-nav__link logout" href="panel.php?logout=1">خروج</a>
            </div>
        </aside>
        <main class="content">
            <div class="card">
                <div class="header-row">
                    <h1 class="title" style="margin:0">شرکت کننده ها</h1>
                    <div class="count-box">
                        <span>تعداد شرکت کننده ها:</span>
                        <b><?php echo number_format($count); ?></b>
                    </div>
                </div>
                <div class="csv-hint">اطلاعات از فایل‌های CSV در مسیر <code>storage</code> خوانده می‌شود: <code>1 tickets.csv</code> تا <code>4 tickets.csv</code>.</div>
                <div style="overflow:auto; margin-top:12px;">
                    <table>
                        <thead>
                            <tr>
                                <th>نام و نام خانوادگی</th>
                                <th>موبایل</th>
                                <th>تعداد بلیت</th>
                                <th>مبلغ کل (ریال)</th>
                                <th>کد پیگیری</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($participants)): ?>
                            <tr>
                                <td colspan="5" class="muted">هنوز شرکت‌کننده‌ای ثبت نشده است.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($participants as $row): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['fullname'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td dir="ltr"><?php echo htmlspecialchars($row['mobile'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo (int)$row['tickets']; ?></td>
                                <td><?php echo number_format((int)$row['total']); ?></td>
                                <td><span class="tag"><?php echo htmlspecialchars($row['tag'], ENT_QUOTES, 'UTF-8'); ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
</body>
</html>

