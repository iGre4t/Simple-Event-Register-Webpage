<?php
return [
    // API key from your SMS.ir panel (Production or Sandbox)
    'api_key' => 'nbHQt4bwJx9NU9Z9xsn6F8to4myJlhI3GxshaR8qJhnd0FPk',

    // Default: use Bulk (dedicated line). Options: 'bulk', 'verify', 'both'
    'mode' => 'bulk',

    // For Verify method (service line templates)
    'template_id' => 0,

    // Admin mobile to receive notifications
    'admin_mobile' => '09102024292',
    // Optional: second admin mobile to receive notifications
    'admin_mobile_2' => '',

    // Dedicated line number for Bulk API (from SMS.ir panel)
    'line_number' => '300021150920',

    // Template parameter name used in Verify method
    'parameter_name' => 'DETAILS',

    // Optional: buyer-specific template config. If left as defaults,
    // the admin template_id/parameter_name will be reused for buyer SMS.
    'buyer_template_id' => 0,
    'buyer_parameter_name' => 'DETAILS',

    // Set true if you are using a Sandbox API key (Verify templateId = 123456 with name "Code")
    'sandbox' => false,
];
