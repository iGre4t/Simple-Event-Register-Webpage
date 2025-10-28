<?php
// Send UTF-8 so Persian text displays correctly
header('Content-Type: text/html; charset=UTF-8');
// Success page: show masked internal tracking code (tag + time)
// without changing what we store in lists/CSV.

$tag = isset($_GET['tag']) ? preg_replace('/[^A-Za-z0-9\-]/', '', (string)$_GET['tag']) : '';
$refId = isset($_GET['ref_id']) ? preg_replace('/[^0-9]/', '', (string)$_GET['ref_id']) : '';

// Display-only masking: append current time HHMM
$masked = $tag !== '' ? ($tag . date('Hi')) : '';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>پرداخت با موفقیت انجام شد</title>
  <link rel="stylesheet" href="css/style.css" />
  <style>
    body { font-family: "Peyda", "IRANSans", -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; background:#f4f6fb; min-height:100vh; margin:0; display:flex; align-items:center; justify-content:center; }
    .box { background:#fff; border-radius:18px; padding:32px 36px; max-width:480px; width:92%; text-align:center; box-shadow:0 20px 50px rgba(0,0,0,0.08); }
    h1 { margin:0 0 8px; color:#1b5e20; font-size:24px; }
    p { color:#424242; line-height:1.8; margin:0 0 16px; }
    .tag { display:inline-block; background:#e8f5e9; color:#1b5e20; padding:8px 16px; border-radius:999px; font-weight:700; letter-spacing:.02em; }
    .muted { color:#2e7d32; font-size:13px; margin-top:12px; }
    a { display:inline-block; margin-top:20px; text-decoration:none; color:#1976d2; }
  </style>
</head>
<body>
  <script>
    (function(){
      function looksBroken(s){ return /[ØÙÛ×ÂÃ]/.test(s); }
      function fixText(s){ try{ var t=decodeURIComponent(escape(s)); return /[\u0600-\u06FF]/.test(t)?t:s; }catch(e){ return s; } }
      function traverse(root){ var w=document.createTreeWalker(root, NodeFilter.SHOW_TEXT, null); var n, arr=[]; while((n=w.nextNode())) arr.push(n); arr.forEach(function(nd){ var v=nd.nodeValue; if(!v||!looksBroken(v)) return; var f=fixText(v); if(f!==v) nd.nodeValue=f;}); }
      document.addEventListener('DOMContentLoaded', function(){ traverse(document.body); });
    })();
  </script>
  <div class="box">
    <h1>پرداخت با موفقیت انجام شد</h1>
    <p>سفارش شما ثبت شد. اطلاعات پرداخت در سیستم ذخیره شد.</p>
    <?php if ($masked !== ''): ?>
      <div class="tag">کد پیگیری داخلی: <?php echo htmlspecialchars($masked, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>
    <?php if ($refId !== ''): ?>
      <p class="muted">شناسه تراکنش زرین‌پال: <?php echo htmlspecialchars($refId, ENT_QUOTES, 'UTF-8'); ?></p>
    <?php endif; ?>
    <a href="index.php">بازگشت به صفحه اصلی</a>
  </div>
</body>
</html>
