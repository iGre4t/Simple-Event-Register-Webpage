-- Snapshot of live application data captured 2026-07-02 (Asia/Tehran).
-- No participant CSV, pending payment, or completed payment records existed.
START TRANSACTION;

INSERT INTO events
    (id, slug, name, currency, registration_enabled,
     registration_starts_at, registration_ends_at)
VALUES
    (1, 'sicily-super-cup-2026', 'Sicily Super Cup', 'IRR', TRUE,
     '2026-07-01 23:11:00', '2026-07-03 23:11:00')
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    currency = VALUES(currency),
    registration_enabled = VALUES(registration_enabled),
    registration_starts_at = VALUES(registration_starts_at),
    registration_ends_at = VALUES(registration_ends_at);

INSERT INTO ticket_prices (event_id, quantity, total_amount) VALUES
    (1, 1, 100000),
    (1, 2, 200000),
    (1, 3, 300000),
    (1, 4, 400000)
ON DUPLICATE KEY UPDATE total_amount = VALUES(total_amount);

INSERT INTO system_counters (counter_name, counter_value)
VALUES ('registration_tracking_code', 1)
ON DUPLICATE KEY UPDATE counter_value =
    GREATEST(counter_value, VALUES(counter_value));

INSERT INTO payment_providers
    (provider, enabled, configuration, secret_env_key)
VALUES
    ('zarinpal', TRUE,
     JSON_OBJECT(
       'currency', 'IRR',
       'callback_url', '',
       'request_url', 'https://payment.zarinpal.com/pg/v4/payment/request.json',
       'verify_url', 'https://payment.zarinpal.com/pg/v4/payment/verify.json'
     ),
     'ZARINPAL_MERCHANT_ID')
ON DUPLICATE KEY UPDATE
    enabled = VALUES(enabled),
    configuration = VALUES(configuration),
    secret_env_key = VALUES(secret_env_key);

INSERT INTO notification_channels
    (channel, name, enabled, configuration, secret_env_key)
VALUES
    ('sms', 'sms_ir', TRUE,
     JSON_OBJECT(
       'mode', 'bulk',
       'template_id', 0,
       'admin_mobile', '09102024292',
       'line_number', '300021150920',
       'parameter_name', 'DETAILS',
       'buyer_template_id', 0,
       'buyer_parameter_name', 'DETAILS',
       'sandbox', FALSE
     ),
     'SMSIR_API_KEY'),
    ('telegram', 'admin_bot', TRUE,
     JSON_OBJECT('admin_chat_id', '6442613822'),
     'TELEGRAM_BOT_TOKEN')
ON DUPLICATE KEY UPDATE
    enabled = VALUES(enabled),
    configuration = VALUES(configuration),
    secret_env_key = VALUES(secret_env_key);

COMMIT;
