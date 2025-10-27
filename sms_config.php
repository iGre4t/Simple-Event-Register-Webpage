<?php
return [
    // کلید API را از پنل SMS.ir دریافت کرده‌اید.
    'api_key' => 'nbHQt4bwJx9NU9Z9xsn6F8to4myJlhI3GxshaR8qJhnd0FPk',

    // شناسه تمپلیت تأیید در سرویس SMS.ir
    // مقدار زیر را با TemplateID صحیح خود جایگزین کنید.
    'template_id' => 0,

    // شماره موبایلی که پیامک باید برای آن ارسال شود.
    'admin_mobile' => '09102024292',

    // نام پارامتری که در تمپلیت برای نمایش متن دلخواه استفاده می‌کنید.
    'parameter_name' => 'DETAILS',

    // Optional: buyer-specific template config. If left as defaults,
    // the admin template_id/parameter_name will be reused for buyer SMS.
    'buyer_template_id' => 0,
    'buyer_parameter_name' => 'DETAILS',
];
