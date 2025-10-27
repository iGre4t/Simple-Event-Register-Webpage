<?php
$tag = isset($_GET['tag']) ? preg_replace('/[^A-Za-z0-9\-]/', '', $_GET['tag']) : '';
$refId = isset($_GET['ref_id']) ? preg_replace('/[^0-9]/', '', $_GET['ref_id']) : '';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>موفقیت پرداخت</title>
  <link rel="stylesheet" href="css/style.css" />
  <style>
    body {
      font-family: "Peyda", "IRANSans", -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
      background: #f4f6fb;
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
      box-shadow: 0 20px 50px rgba(0, 0, 0, 0.08);
    }
    h1 {
      margin-top: 0;
      color: #1b5e20;
      font-size: 24px;
    }
    p {
      color: #424242;
      line-height: 1.8;
      margin-bottom: 24px;
    }
    .tag {
      display: inline-block;
      background: #e8f5e9;
      color: #1b5e20;
      padding: 8px 16px;
      border-radius: 999px;
      font-weight: 700;
      letter-spacing: 0.03em;
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
  <div class="box">
    <h1>پرداخت با موفقیت انجام شد</h1>
    <p>سفارش شما ثبت شد. اطلاعات پرداخت در سیستم ذخیره شد.</p>
    <?php if ($tag !== ''): ?>
      <div class="tag">کد پیگیری داخلی: <?php echo htmlspecialchars($tag, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>
    <?php if ($refId !== ''): ?>
      <p style="margin-top:12px;color:#2e7d32;font-size:13px">شناسه تراکنش زرین‌پال: <?php echo htmlspecialchars($refId, ENT_QUOTES, 'UTF-8'); ?></p>
    <?php endif; ?>
    <a href="index.php">بازگشت به صفحه اصلی</a>
  </div>
</body>
</html>

