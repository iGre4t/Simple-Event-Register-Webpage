<?php
// ------- تنظیمات ساده -------
$TICKET_PRICE = 100000; // هر سهم ۱۰۰,۰۰۰ ریال
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>ثبت نام مسابقات سوپرکاپ سیسیلی</title>
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <!-- اگر فونت سفارشی داری، بعداً استایل زیر را در style.css فعال کن -->
  <link rel="stylesheet" href="css/style.css">
  <style>
    @font-face {
      font-family: "Peyda";
      src: url("fonts/PeydaWebFaNum-Regular.woff2") format("woff2");
      font-weight: 400;
      font-style: normal;
      font-display: swap;
    }
    @font-face {
      font-family: "Peyda";
      src: url("fonts/PeydaWebFaNum-Bold.woff2") format("woff2");
      font-weight: 700;
      font-style: normal;
      font-display: swap;
    }
    :root {
      --bg:#121212;
      --bg-glow:rgba(255,255,255,0.04);
      --card:#fcfcfd;
      --card-border:#ededed;
      --text:#1f1f1f;
      --muted:#8d8d92;
      --control:#ffffff;
      --control-border:#e3e3e3;
      --shadow:0 32px 60px rgba(0,0,0,0.35);
      --radius:28px;
    }
    * { box-sizing: border-box; }
    body {
      min-height: 100svh;
      margin: 0;
      font-family: "Peyda", "IRANSans", -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
      background: var(--bg);
      color: var(--text);
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 40px 16px;
      position: relative;
      overflow: hidden;
    }
    body::before,
    body::after {
      content: "";
      position: absolute;
      border-radius: 50%;
      filter: blur(120px);
      z-index: 0;
      opacity: 0.7;
    }
    body::before {
      width: 320px;
      height: 320px;
      background: #ffffff0d;
      top: -120px;
      right: -80px;
    }
    body::after {
      width: 280px;
      height: 280px;
      background: #ffffff12;
      bottom: -120px;
      left: -100px;
    }
    .wrap {
      width: 100%;
      max-width: 560px;
      position: relative;
      z-index: 1;
    }
    .card {
      background: var(--card);
      border: 1px solid var(--card-border);
      border-radius: var(--radius);
      box-shadow: var(--shadow);
      padding: 36px 40px 40px;
      display: flex;
      flex-direction: column;
      gap: 18px;
    }
    .card header {
      text-align: center;
      display: flex;
      flex-direction: column;
      gap: 10px;
    }
    .title {
      margin: 0;
      font-size: 26px;
      font-weight: 700;
      letter-spacing: -0.01em;
      line-height: 1.5;
    }
    .sub {
      margin: 0;
      font-size: 14px;
      color: var(--muted);
      line-height: 1.8;
    }
    .field {
      display: flex;
      flex-direction: column;
      gap: 8px;
    }
    .sr-only {
      position: absolute;
      width: 1px;
      height: 1px;
      padding: 0;
      margin: -1px;
      overflow: hidden;
      clip: rect(0, 0, 0, 0);
      border: 0;
    }
    .input-wrap {
      display: flex;
      align-items: center;
      background: #f9f9fb;
      border: 1px solid var(--control-border);
      border-radius: 18px;
      padding: 0 18px;
      gap: 12px;
      transition: border-color .2s ease, box-shadow .2s ease;
    }
    .input-wrap:focus-within {
      border-color: #bdbdbd;
      box-shadow: 0 0 0 4px rgba(0,0,0,0.04);
    }
    .icon {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 36px;
      height: 36px;
      border-radius: 50%;
      background: #f1f1f3;
      color: #555;
      font-size: 18px;
      flex-shrink: 0;
    }
    .icon svg {
      width: 18px;
      height: 18px;
      fill: currentColor;
    }
    .ctrl {
      border: none;
      background: transparent;
      flex: 1;
      font-family: inherit;
      font-size: 16px;
      color: var(--text);
      padding: 16px 0;
      outline: none;
      text-align: right;
    }
    .ctrl--mobile {
      font-family: "Peyda", "IRANSans", sans-serif;
      font-variant-numeric: normal;
      text-align: center;
      letter-spacing: 0.4px;
    }
    .ctrl::placeholder {
      color: #aaaaaf;
    }
    select.ctrl {
      appearance: none;
      -webkit-appearance: none;
      cursor: pointer;
      padding-right: 0;
      padding-left: 28px;
      background-image: url('data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" width="12" height="8" viewBox="0 0 12 8"%3E%3Cpath fill="%23666" d="M10.59.59 6 5.17 1.41.59 0 2l6 6 6-6z"/%3E%3C/svg%3E');
      background-repeat: no-repeat;
      background-position: left 4px center;
    }
    .prefix {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      background: #efeff1;
      color: #3f3f3f;
      border-radius: 12px;
      padding: 8px 14px;
      font-size: 14px;
      font-weight: 600;
    }
    .hint {
      font-size: 12px;
      color: var(--muted);
      line-height: 1.6;
    }
    .total {
      margin-top: 6px;
      background: #f3f3f5;
      border: none;
      border-radius: 16px;
      padding: 16px 18px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      font-size: 15px;
      color: #3a3a3a;
    }
    .total b {
      font-size: 17px;
      font-weight: 700;
    }
    .btn {
      border: none;
      border-radius: 20px;
      background: #C63437;
      color: #ffffff;
      font-weight: 700;
      font-size: 16px;
      padding: 18px;
      cursor: pointer;
      box-shadow: 0 22px 40px rgba(198, 52, 55, 0.32);
      transition: transform .2s ease, box-shadow .2s ease, background .2s ease;
    }
    .btn:hover:not(:disabled) {
      transform: translateY(-1px);
      box-shadow: 0 26px 46px rgba(198, 52, 55, 0.42);
      background: #ac2d30;
    }
    .btn:focus-visible {
      outline: none;
      box-shadow: 0 0 0 4px rgba(198, 52, 55, 0.18);
    }
    .btn:disabled {
      background: #d7d7dc;
      color: #f7f7f8;
      box-shadow: none;
      cursor: not-allowed;
      transform: none;
    }
    footer {
      margin-top: 28px;
      text-align: center;
      color: #9f9f9f;
      font-size: 12px;
    }
    @media (max-width: 420px) {
      .card {
        padding: 30px 24px 32px;
        border-radius: 22px;
      }
      .title {
        font-size: 23px;
      }
      .input-wrap {
        padding: 0 14px;
      }
    }
  </style>

</head>
<body>
  <div class="wrap">
    <form class="card" method="post" action="purchase.php" id="regForm" novalidate>
      <header>
        <h1 class="title">ثبت نام مسابقات سوپرکاپ سیسیلی</h1>
        <p class="sub">لطفاً اطلاعات خود را با دقت کامل کنید.</p>
      </header>

      <!-- نام و نام خانوادگی -->
      <div class="field">
        <label class="sr-only" for="fullname">نام و نام خانوادگی</label>
        <div class="input-wrap">
          <span class="icon" aria-hidden="true">
            <svg viewBox="0 0 24 24">
              <path d="M12 12a4 4 0 1 0-4-4 4 4 0 0 0 4 4Zm0 2c-3.33 0-6 1.34-6 3v1h12v-1c0-1.66-2.67-3-6-3Z" />
            </svg>
          </span>
          <input class="ctrl" id="fullname" name="fullname" type="text" required
                 placeholder="نام و نام خانوادگی" autocomplete="name" />
        </div>
        <div class="hint">وارد کردن نام واقعی برای صدور رسید الزامی است.</div>
      </div>

      <!-- شماره تلفن همراه -->
      <div class="field" style="margin-top:16px">
        <label class="sr-only" for="mobile">شماره تلفن همراه ۱۱ رقمی</label>
        <div class="input-wrap">
          <span class="icon" aria-hidden="true">
            <svg viewBox="0 0 24 24">
              <path d="M6.62 10.79a15 15 0 0 0 6.59 6.59l2.2-2.2a1 1 0 0 1 1.01-.24 11.36 11.36 0 0 0 3.56.57 1 1 0 0 1 1 1v3.61a1 1 0 0 1-1 1A17.79 17.79 0 0 1 3 6a1 1 0 0 1 1-1h3.61a1 1 0 0 1 1 1 11.36 11.36 0 0 0 .57 3.56 1 1 0 0 1-.24 1.01Z" />
            </svg>
          </span>
<<<<<<< ours
          <input class="ctrl" id="mobile" name="mobile_local" type="tel" inputmode="numeric"
                 pattern="^09\d{9}$" minlength="11" maxlength="11" required placeholder="۰۹XXXXXXXXX" />
=======
          <input class="ctrl ctrl--mobile" id="mobile" name="mobile_local" type="tel" inputmode="numeric" dir="ltr"
                 pattern="^(09|۰۹)[0-9۰-۹]{9}$" minlength="11" maxlength="11" required placeholder="۰۹XXXXXXXXX" />
>>>>>>> theirs
        </div>
        <div class="hint">فقط موبایل ایران؛ ۱۱ رقم و با ۰۹ شروع می‌شود (نمونه: ۰۹۱۲XXXXXXX).</div>
      </div>

      <!-- تعداد سهم 1..4 -->
      <div class="field" style="margin-top:16px">
        <label class="sr-only" for="qty">تعداد سهم</label>
        <div class="input-wrap">
          <span class="icon" aria-hidden="true">
            <svg viewBox="0 0 24 24">
              <path d="M21 7a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v3a2 2 0 0 1 0 4v3a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-3a2 2 0 0 1 0-4Zm-4 7h-2v-2h2Zm0-4h-2V8h2Z" />
            </svg>
          </span>
          <select class="ctrl" id="qty" name="qty" required>
            <option value="1">۱ سهم</option>
            <option value="2">۲ سهم</option>
            <option value="3">۳ سهم</option>
            <option value="4">۴ سهم</option>
          </select>
        </div>
        <div class="hint">حداقل ۱ و حداکثر ۴ سهم.</div>
      </div>
      <!-- مجموع قیمت -->
      <div class="total" id="totalBox" aria-live="polite" style="margin-top:16px">
        <span>مجموع قیمت</span>
        <b id="totalText">۰ ریال</b>
      </div>
      <input type="hidden" name="unit_price" value="<?php echo (int)$TICKET_PRICE; ?>">
      <input type="hidden" name="total_price" id="total_price" value="">

      <!-- دکمه -->
      <button class="btn" type="submit" id="submitBtn" disabled aria-disabled="true">پرداخت و تکمیل ثبت‌نام</button>

      <footer>© <?php echo date('Y'); ?> Sicily Exports Complex. All Rights Reserved.</footer>
    </form>
  </div>

  <script>
    // قیمت واحد از سرور
    const UNIT = <?php echo (int)$TICKET_PRICE; ?>;

    const $qty = document.getElementById('qty');
    const $fullname = document.getElementById('fullname');
    const $totalText = document.getElementById('totalText');
    const $totalPrice = document.getElementById('total_price');
    const $mobileLocal = document.getElementById('mobile');
    const $form = document.getElementById('regForm');
    const $submit = document.getElementById('submitBtn');

    const PERSIAN_DIGITS = ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'];
    const EN_DIGITS = ['0','1','2','3','4','5','6','7','8','9'];

    function toEnglishDigits(str){
      return str.replace(/[۰-۹]/g, d => EN_DIGITS[PERSIAN_DIGITS.indexOf(d)] ?? d);
    }

    function toPersianDigits(n){
      return (n+'').replace(/\d/g, d=>'۰۱۲۳۴۵۶۷۸۹'[d]);
    }
    function formatRial(n){
      return toPersianDigits(n.toLocaleString('fa-IR')) + ' ریال';
    }
    function renderMobileDigits(digits){
      $mobileLocal.value = digits.replace(/\d/g, d => PERSIAN_DIGITS[d]);
    }
    function getMobileDigits(){
      const normalized = toEnglishDigits($mobileLocal.value).replace(/\D/g, '');
      return normalized.slice(0, 11);
    }
    function sanitizeMobile(){
      const digits = getMobileDigits();
      renderMobileDigits(digits);
      return digits;
    }
    function updateTotal(){
      const q = parseInt($qty.value || '1',10);
      const total = q * UNIT;
      $totalText.textContent = formatRial(total);
      $totalPrice.value = total;
    }
    function toggleSubmit(mobileDigits){
      const digits = mobileDigits ?? sanitizeMobile();
      const fullnameOk = $fullname.value.trim().length > 0;
      const mobileOk = /^09\d{9}$/.test(digits);
      const qtyOk = Boolean($qty.value);
      const enable = fullnameOk && mobileOk && qtyOk;
      $submit.disabled = !enable;
      $submit.setAttribute('aria-disabled', String(!enable));
    }
    updateTotal();
    $qty.addEventListener('change', updateTotal);
<<<<<<< ours

    // اعتبارسنجی ساده در ارسال
    $form.addEventListener('submit', function(e){
      // چک شماره: باید ۱۱ رقم و با ۰۹ شروع شود
      if(!/^09\d{9}$/.test($mobileLocal.value)){
=======
    $qty.addEventListener('change', () => toggleSubmit());
    $fullname.addEventListener('input', () => toggleSubmit());
    $mobileLocal.addEventListener('input', () => {
      const digits = sanitizeMobile();
      toggleSubmit(digits);
    });
    toggleSubmit();

    // اعتبارسنجی ساده در ارسال
    $form.addEventListener('submit', function(e){
      const digits = sanitizeMobile();
      // چک شماره: باید ۱۱ رقم و با ۰۹ شروع شود
      if(!/^09\d{9}$/.test(digits)){
>>>>>>> theirs
        e.preventDefault();
        alert('لطفاً شماره همراه را به‌صورت ۰۹XXXXXXXXX وارد کنید.');
        $mobileLocal.focus();
        return false;
      }
      $mobileLocal.value = digits;
      // مقدار total هم آپدیت باشد
      updateTotal();
    });
  </script>
</body>
</html>