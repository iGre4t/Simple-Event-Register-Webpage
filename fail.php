<?php
// Send UTF-8 so Persian text displays correctly
header('Content-Type: text/html; charset=UTF-8');
$reason = $_GET['reason'] ?? '';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>خطا در پردازش</title>
  <link rel="stylesheet" href="css/style.css" />
  <style>
    body {
      font-family: "Peyda", "IRANSans", -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
      background: #fff1f0;
      min-height: 100vh;
      margin: 0;
      display: flex;
      align-items: center;
      justify-content: center;
    }
    .box {
      background: #ffffff;
      border-radius: 18px;
      padding: 32px 36px;
      max-width: 420px;
      width: 90%;
      text-align: center;
      box-shadow: 0 20px 50px rgba(244, 67, 54, 0.1);
    }
    h1 {
      margin-top: 0;
      color: #c62828;
      font-size: 24px;
    }
    p {
      color: #424242;
      line-height: 1.8;
      margin-bottom: 24px;
    }
    a {
      display: inline-block;
      margin-top: 20px;
      text-decoration: none;
      color: #1976d2;
    }
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
    <h1>خطا در ثبت اطلاعات</h1>
    <p>در پردازش پرداخت شما مشکلی رخ داد. لطفاً دوباره تلاش کنید.</p>
    <?php if ($reason !== ''): ?>
      <p style="font-size: 13px; color: #757575;">کد خطا: <?php echo htmlspecialchars($reason, ENT_QUOTES, 'UTF-8'); ?></p>
    <?php endif; ?>
    <a href="index.php">بازگشت به فرم ثبت‌نام</a>
  </div>
</body>
</html>
