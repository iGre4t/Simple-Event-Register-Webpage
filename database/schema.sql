-- MySQL 8.0+/MariaDB 10.4+ schema for Simple Event Register.
-- Import this file into the database selected by your hosting account.
SET NAMES utf8mb4;
SET time_zone = '+00:00';

CREATE TABLE events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(100) NOT NULL UNIQUE,
    name VARCHAR(200) NOT NULL,
    currency CHAR(3) NOT NULL DEFAULT 'IRR',
    registration_enabled BOOLEAN NOT NULL DEFAULT TRUE,
    registration_starts_at DATETIME NULL,
    registration_ends_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE ticket_prices (
    event_id BIGINT UNSIGNED NOT NULL,
    quantity TINYINT UNSIGNED NOT NULL,
    total_amount BIGINT UNSIGNED NOT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (event_id, quantity),
    CONSTRAINT fk_ticket_prices_event
        FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE participants (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(150) NOT NULL,
    mobile VARCHAR(11) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_participants_mobile (mobile)
) ENGINE=InnoDB;

CREATE TABLE registrations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_id BIGINT UNSIGNED NOT NULL,
    participant_id BIGINT UNSIGNED NOT NULL,
    tracking_code VARCHAR(32) NOT NULL,
    quantity TINYINT UNSIGNED NOT NULL,
    unit_price BIGINT UNSIGNED NOT NULL,
    total_amount BIGINT UNSIGNED NOT NULL,
    status ENUM('pending_payment','paid','failed','cancelled','archived')
        NOT NULL DEFAULT 'pending_payment',
    source VARCHAR(30) NOT NULL DEFAULT 'web',
    legacy_source_file VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    paid_at DATETIME NULL,
    archived_at DATETIME NULL,
    UNIQUE KEY uq_registration_tracking (event_id, tracking_code),
    KEY idx_registration_participant (event_id, participant_id),
    KEY idx_registration_status_created (event_id, status, created_at),
    CONSTRAINT fk_registrations_event
        FOREIGN KEY (event_id) REFERENCES events(id),
    CONSTRAINT fk_registrations_participant
        FOREIGN KEY (participant_id) REFERENCES participants(id)
) ENGINE=InnoDB;

CREATE TABLE payments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    registration_id BIGINT UNSIGNED NOT NULL,
    provider VARCHAR(30) NOT NULL DEFAULT 'zarinpal',
    authority VARCHAR(100) NULL,
    reference_id VARCHAR(100) NULL,
    amount BIGINT UNSIGNED NOT NULL,
    status ENUM('created','requested','verified','failed','cancelled')
        NOT NULL DEFAULT 'created',
    request_payload JSON NULL,
    response_payload JSON NULL,
    failure_code VARCHAR(100) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    verified_at DATETIME NULL,
    UNIQUE KEY uq_payments_authority (provider, authority),
    UNIQUE KEY uq_payments_reference (provider, reference_id),
    KEY idx_payments_registration (registration_id, status),
    CONSTRAINT fk_payments_registration
        FOREIGN KEY (registration_id) REFERENCES registrations(id)
) ENGINE=InnoDB;

CREATE TABLE payment_providers (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    provider VARCHAR(30) NOT NULL UNIQUE,
    enabled BOOLEAN NOT NULL DEFAULT TRUE,
    configuration JSON NOT NULL,
    secret_env_key VARCHAR(100) NULL,
    secret_value TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE notification_channels (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    channel ENUM('sms','telegram') NOT NULL,
    name VARCHAR(100) NOT NULL,
    enabled BOOLEAN NOT NULL DEFAULT TRUE,
    configuration JSON NOT NULL,
    secret_env_key VARCHAR(100) NULL,
    secret_value TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_notification_channel_name (channel, name)
) ENGINE=InnoDB;

CREATE TABLE notification_jobs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    registration_id BIGINT UNSIGNED NULL,
    channel_id BIGINT UNSIGNED NOT NULL,
    recipient VARCHAR(150) NOT NULL,
    template_name VARCHAR(100) NULL,
    payload JSON NOT NULL,
    status ENUM('queued','processing','sent','failed','cancelled')
        NOT NULL DEFAULT 'queued',
    attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
    available_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    sent_at DATETIME NULL,
    last_error TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_notification_queue (status, available_at),
    CONSTRAINT fk_notification_jobs_registration
        FOREIGN KEY (registration_id) REFERENCES registrations(id) ON DELETE SET NULL,
    CONSTRAINT fk_notification_jobs_channel
        FOREIGN KEY (channel_id) REFERENCES notification_channels(id)
) ENGINE=InnoDB;

CREATE TABLE admin_users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin','viewer') NOT NULL DEFAULT 'admin',
    enabled BOOLEAN NOT NULL DEFAULT TRUE,
    last_login_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE audit_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    admin_user_id BIGINT UNSIGNED NULL,
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(100) NULL,
    entity_id VARCHAR(100) NULL,
    before_data JSON NULL,
    after_data JSON NULL,
    ip_address VARCHAR(45) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_audit_created (created_at),
    CONSTRAINT fk_audit_admin
        FOREIGN KEY (admin_user_id) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE application_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    channel VARCHAR(50) NOT NULL,
    level ENUM('info','warning','error') NOT NULL DEFAULT 'info',
    message TEXT NOT NULL,
    context JSON NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_application_logs_channel_created (channel, created_at)
) ENGINE=InnoDB;

CREATE TABLE system_counters (
    counter_name VARCHAR(100) PRIMARY KEY,
    counter_value BIGINT UNSIGNED NOT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE migration_imports (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    source_key VARCHAR(255) NOT NULL UNIQUE,
    source_hash CHAR(64) NOT NULL,
    imported_rows INT UNSIGNED NOT NULL DEFAULT 0,
    imported_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
