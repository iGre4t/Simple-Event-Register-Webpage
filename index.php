<?php
// ------- تنظیمات ساده -------
$TICKET_PRICE = 100000; // هر بلیت ۱۰۰,۰۰۰ ریال
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>ثبت‌نام رویداد</title>
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <!-- اگر فونت سفارشی داری، بعداً استایل زیر را در style.css فعال کن -->
  <link rel="stylesheet" href="css/style.css">
  <style>
    /* حداقل استایل برای اینکه بدون فایل خارجی هم کار کند */
    :root{
      --brand:#FF7A00;
      --text:#222;
      --muted:#6b7280;
      --bg:#f8fafc;
      --card:#fff;
      --radius:16px;
      --app-font: system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, "Noto Sans", "Apple Color Emoji","Segoe UI Emoji";
    }
    *{box-sizing:border-box}
    html,body{background:var(--bg);margin:0;font-family:var(--app-font);color:var(--text)}
    .wrap{min-height:100svh;display:grid;place-items:center;padding:24px}
    .card{background:var(--card);width:100%;max-width:620px;border-radius:var(--radius);box-shadow:0 10px 30px rgba(0,0,0,.08);padding:28px}
    .title{font-size:22px;font-weight:800;margin:0 0 8px}
    .sub{color:var(--muted);margin:0 0 20px;font-size:14px}
    .row{display:flex;gap:12px;align-items:stretch}
    .field{flex:1}
    label{display:block;font-size:14px;margin-bottom:8px}
    .ctrl{width:100%;padding:14px 16px;border:1px solid #e5e7eb;border-radius:12px;background:#fff;font-size:16px;outline:none}
    .ctrl:focus{border-color:#d1d5db;box-shadow:0 0 0 4px #f3f4f6}
    .input-group{display:flex;align-items:center}
    .prefix{background:#f3f4f6;border:1px solid #e5e7eb;border-radius:12px 0 0 12px;padding:14px 12px;color:#111;font-weight:700}
    .ctrl.norad-left{border-radius:0 12px 12px 0;border-right:0}
    .hint{font-size:12px;color:var(--muted);margin-top:6px}
    .total{display:flex;justify-content:space-between;align-items:center;background:#fff7ed;border:1px solid #fed7aa;padding:14px 16px;border-radius:12px;margin:14px 0 4px}
    .total b{font-size:18px}
    .btn{display:inline-flex;justify-content:center;align-items:center;gap:8px;width:100%;padding:14px 16px;background:var(--brand);color:#fff;border:none;border-radius:12px;font-size:16px;font-weight:800;cursor:pointer}
    .btn:focus{outline:3px solid #fed7aa}
    .btn:disabled{opacity:.6;cursor:not-allowed}
    footer{margin-top:20px;text-align:center;color:#9ca3af;font-size:12px}
    @media (max-width:480px){
      .row{flex-direction:column}
    }
  </style>
</head>
<body>
  <div class="wrap">
    <form class="card" method="post" action="purchase.php" id="regForm" novalidate>
      <h1 class="title">ثبت‌نام رویداد</h1>
      <p class="sub">لطفاً اطلاعات را به‌درستی وارد کنید.</p>

      <!-- نام و نام خانوادگی -->
      <div class="field">
        <label for="fullname">نام و نام خانوادگی</label>
        <input class="ctrl" id="fullname" name="fullname" type="text" required
               placeholder="مثلاً: علی تهرانی" autocomplete="name" />
        <div class="hint">وارد کردن نام واقعی برای صدور رسید الزامی است.</div>
      </div>

      <!-- شماره همراه +98 -->
      <div class="field" style="margin-top:16px">
        <label for="mobile">شماره همراه</label>
        <div class="input-group">
          <div class="prefix">+98</div>
          <input class="ctrl norad-left" id="mobile" name="mobile_local" type="tel" inputmode="numeric"
                 pattern="^9\d{9}$" maxlength="10" required placeholder="9XXXXXXXXX" />
        </div>
        <div class="hint">فقط موبایل ایران؛ ۱۰ رقم و با 9 شروع می‌شود (نمونه: ۹۱۲XXXXXXX).</div>
        <!-- تلفن نهایی ترکیبی در ارسال فرم -->
        <input type="hidden" name="mobile_full" id="mobile_full">
      </div>

      <!-- تعداد بلیت 1..4 -->
      <div class="field" style="margin-top:16px">
        <label for="qty">تعداد بلیت</label>
        <select class="ctrl" id="qty" name="qty" required>
          <option value="1">۱ بلیت</option>
          <option value="2">۲ بلیت</option>
          <option value="3">۳ بلیت</option>
          <option value="4">۴ بلیت</option>
        </select>
        <div class="hint">حداقل ۱ و حداکثر ۴ بلیت.</div>
      </div>

      <!-- مجموع قیمت -->
      <div class="total" id="totalBox" aria-live="polite" style="margin-top:16px">
        <span>مجموع قیمت</span>
        <b id="totalText">۰ ریال</b>
      </div>
      <input type="hidden" name="unit_price" value="<?php echo (int)$TICKET_PRICE; ?>">
      <input type="hidden" name="total_price" id="total_price" value="">

      <!-- دکمه -->
      <button class="btn" type="submit" id="submitBtn">پرداخت و تکمیل ثبت‌نام</button>

      <footer>© <?php echo date('Y'); ?> رویداد شما. همه حقوق محفوظ است.</footer>
    </form>
  </div>

  <script>
    // قیمت واحد از سرور
    const UNIT = <?php echo (int)$TICKET_PRICE; ?>;

    const $qty = document.getElementById('qty');
    const $totalText = document.getElementById('totalText');
    const $totalPrice = document.getElementById('total_price');
    const $mobileLocal = document.getElementById('mobile');
    const $mobileFull = document.getElementById('mobile_full');
    const $form = document.getElementById('regForm');

    function toPersianDigits(n){
      return (n+'').replace(/\d/g, d=>'۰۱۲۳۴۵۶۷۸۹'[d]);
    }
    function formatRial(n){
      return toPersianDigits(n.toLocaleString('fa-IR')) + ' ریال';
    }
    function updateTotal(){
      const q = parseInt($qty.value || '1',10);
      const total = q * UNIT;
      $totalText.textContent = formatRial(total);
      $totalPrice.value = total;
    }
    updateTotal();
    $qty.addEventListener('change', updateTotal);

    // سرهم کردن شماره کامل با +98
    function buildFullMobile(){
      const local = ($mobileLocal.value || '').replace(/\D/g,''); // فقط ارقام
      $mobileFull.value = '+98' + local;
    }
    $mobileLocal.addEventListener('input', buildFullMobile);
    buildFullMobile();

    // اعتبارسنجی ساده در ارسال
    $form.addEventListener('submit', function(e){
      buildFullMobile();
      // چک شماره: باید 10 رقم و با 9 شروع شود
      if(!/^9\d{9}$/.test($mobileLocal.value)){
        e.preventDefault();
        alert('لطفاً شماره همراه را به‌صورت 9XXXXXXXXX وارد کنید.');
        $mobileLocal.focus();
        return false;
      }
      // مقدار total هم آپدیت باشد
      updateTotal();
    });
  </script>
</body>
</html>
