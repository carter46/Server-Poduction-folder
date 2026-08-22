-- =============================================================================
-- Tranzit schema migration (query-safe — paste into phpMyAdmin SQL tab)
-- Date: 2026-08-22 | Safe to re-run | Not a data dump
--
-- Select your database first, then paste and run this entire script.
-- Skips columns/tables that already exist.
-- =============================================================================

SET NAMES utf8mb4;
SET @db := DATABASE();

-- ---------------------------------------------------------------------------
-- 1) Core admin + license tables
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `admin_users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `last_login` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `license_keys` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `license_key` varchar(255) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `license_key` (`license_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `license_settings` (
  `id` int(11) NOT NULL,
  `purchase_email` varchar(255) NOT NULL DEFAULT 'support@ubadashboard.com',
  `renewal_gate` enum('off','on') NOT NULL DEFAULT 'off',
  `software_activated` enum('no','yes') NOT NULL DEFAULT 'no',
  `normal_delay_seconds` int(11) NOT NULL DEFAULT 15,
  `renewal_delay_seconds` int(11) NOT NULL DEFAULT 25,
  `expected_signature` varchar(255) NOT NULL DEFAULT 'UBA-RENEWAL-SIG-A8829F0D11D992A',
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `license_settings` (`id`, `purchase_email`)
VALUES (1, 'support@ubadashboard.com')
ON DUPLICATE KEY UPDATE `id` = `id`;

-- license_settings: add missing columns (Global Settings / License Key need these)
SET @sql := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db AND table_name='license_settings' AND column_name='renewal_gate')=0,
  'ALTER TABLE license_settings ADD COLUMN renewal_gate ENUM(''off'',''on'') NOT NULL DEFAULT ''off'' AFTER purchase_email', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db AND table_name='license_settings' AND column_name='dashboard_mode')=0,
  'ALTER TABLE license_settings ADD COLUMN dashboard_mode ENUM(''on'',''off'') NOT NULL DEFAULT ''on'' AFTER renewal_gate', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db AND table_name='license_settings' AND column_name='software_activated')=0,
  'ALTER TABLE license_settings ADD COLUMN software_activated ENUM(''no'',''yes'') NOT NULL DEFAULT ''no'' AFTER dashboard_mode', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db AND table_name='license_settings' AND column_name='normal_delay_seconds')=0,
  'ALTER TABLE license_settings ADD COLUMN normal_delay_seconds INT NOT NULL DEFAULT 15', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db AND table_name='license_settings' AND column_name='renewal_delay_seconds')=0,
  'ALTER TABLE license_settings ADD COLUMN renewal_delay_seconds INT NOT NULL DEFAULT 25', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db AND table_name='license_settings' AND column_name='expected_signature')=0,
  'ALTER TABLE license_settings ADD COLUMN expected_signature VARCHAR(255) NOT NULL DEFAULT ''UBA-RENEWAL-SIG-A8829F0D11D992A''', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db AND table_name='license_settings' AND column_name='updated_at')=0,
  'ALTER TABLE license_settings ADD COLUMN updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db AND table_name='license_settings' AND column_name='mail_phpmailer_enabled')=0,
  'ALTER TABLE license_settings ADD COLUMN mail_phpmailer_enabled TINYINT(1) NOT NULL DEFAULT 0', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db AND table_name='license_settings' AND column_name='mail_smtp_host')=0,
  'ALTER TABLE license_settings ADD COLUMN mail_smtp_host VARCHAR(255) DEFAULT NULL', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db AND table_name='license_settings' AND column_name='mail_smtp_port')=0,
  'ALTER TABLE license_settings ADD COLUMN mail_smtp_port INT NOT NULL DEFAULT 587', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db AND table_name='license_settings' AND column_name='mail_smtp_username')=0,
  'ALTER TABLE license_settings ADD COLUMN mail_smtp_username VARCHAR(255) DEFAULT NULL', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db AND table_name='license_settings' AND column_name='mail_smtp_password')=0,
  'ALTER TABLE license_settings ADD COLUMN mail_smtp_password VARCHAR(512) DEFAULT NULL', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db AND table_name='license_settings' AND column_name='mail_smtp_encryption')=0,
  'ALTER TABLE license_settings ADD COLUMN mail_smtp_encryption VARCHAR(10) NOT NULL DEFAULT ''tls''', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db AND table_name='license_settings' AND column_name='mail_from_email')=0,
  'ALTER TABLE license_settings ADD COLUMN mail_from_email VARCHAR(255) DEFAULT NULL', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db AND table_name='license_settings' AND column_name='mail_from_name')=0,
  'ALTER TABLE license_settings ADD COLUMN mail_from_name VARCHAR(255) DEFAULT NULL', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db AND table_name='license_settings' AND column_name='mail_reply_to')=0,
  'ALTER TABLE license_settings ADD COLUMN mail_reply_to VARCHAR(255) DEFAULT NULL', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db AND table_name='license_settings' AND column_name='mail_brevo_enabled')=0,
  'ALTER TABLE license_settings ADD COLUMN mail_brevo_enabled TINYINT(1) NOT NULL DEFAULT 0', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db AND table_name='license_settings' AND column_name='mail_brevo_api_key')=0,
  'ALTER TABLE license_settings ADD COLUMN mail_brevo_api_key VARCHAR(512) DEFAULT NULL', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db AND table_name='license_settings' AND column_name='mail_brevo_sender_email')=0,
  'ALTER TABLE license_settings ADD COLUMN mail_brevo_sender_email VARCHAR(255) DEFAULT NULL', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db AND table_name='license_settings' AND column_name='mail_brevo_sender_name')=0,
  'ALTER TABLE license_settings ADD COLUMN mail_brevo_sender_name VARCHAR(255) DEFAULT NULL', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db AND table_name='license_settings' AND column_name='otp_enabled')=0,
  'ALTER TABLE license_settings ADD COLUMN otp_enabled TINYINT(1) NOT NULL DEFAULT 0', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db AND table_name='license_settings' AND column_name='hard_token_enabled')=0,
  'ALTER TABLE license_settings ADD COLUMN hard_token_enabled TINYINT(1) NOT NULL DEFAULT 0', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db AND table_name='license_settings' AND column_name='hard_token')=0,
  'ALTER TABLE license_settings ADD COLUMN hard_token VARCHAR(64) DEFAULT NULL', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db AND table_name='license_settings' AND column_name='default_transfer_status')=0,
  'ALTER TABLE license_settings ADD COLUMN default_transfer_status ENUM(''SUCCESSFUL'',''PENDING'',''FAILED'') NOT NULL DEFAULT ''SUCCESSFUL''', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db AND table_name='license_settings' AND column_name='transfer_restriction')=0,
  'ALTER TABLE license_settings ADD COLUMN transfer_restriction TINYINT(1) NOT NULL DEFAULT 0', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db AND table_name='license_settings' AND column_name='risky_transaction')=0,
  'ALTER TABLE license_settings ADD COLUMN risky_transaction TINYINT(1) NOT NULL DEFAULT 0', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db AND table_name='license_settings' AND column_name='nin_verification')=0,
  'ALTER TABLE license_settings ADD COLUMN nin_verification TINYINT(1) NOT NULL DEFAULT 0', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db AND table_name='license_settings' AND column_name='log_status')=0,
  'ALTER TABLE license_settings ADD COLUMN log_status ENUM(''full_logs'',''weak_logs'',''pending_request'',''post_no_debit'',''fixed_account'') NOT NULL DEFAULT ''full_logs''', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db AND table_name='license_settings' AND column_name='crypto_mode')=0,
  'ALTER TABLE license_settings ADD COLUMN crypto_mode ENUM(''on'',''off'') NOT NULL DEFAULT ''on''', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db AND table_name='license_settings' AND column_name='phone_otp_enabled')=0,
  'ALTER TABLE license_settings ADD COLUMN phone_otp_enabled TINYINT(1) NOT NULL DEFAULT 0', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- ---------------------------------------------------------------------------
-- 2) Global Settings status tables
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `bvn_status` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `customer_id_status` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `nin_status` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `platform_status` (
  `id` int(11) NOT NULL,
  `status` enum('on','off') NOT NULL DEFAULT 'on',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `bvn_status` (`id`, `status`) VALUES (1, 'active') ON DUPLICATE KEY UPDATE `id` = `id`;
INSERT INTO `customer_id_status` (`id`, `status`) VALUES (1, 'active') ON DUPLICATE KEY UPDATE `id` = `id`;
INSERT INTO `nin_status` (`id`, `status`) VALUES (1, 'active') ON DUPLICATE KEY UPDATE `id` = `id`;
INSERT INTO `platform_status` (`id`, `status`) VALUES (1, 'on') ON DUPLICATE KEY UPDATE `id` = `id`;

-- ---------------------------------------------------------------------------
-- 3) UBA account + users (admin home / customer login)
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `uba_account_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `account_name` varchar(255) NOT NULL DEFAULT 'AUTOGRAPH CONSTRUCTION LIMITED',
  `account_number` varchar(50) NOT NULL DEFAULT '1022090307',
  `balance` decimal(15,2) NOT NULL DEFAULT 670473471.10,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `uba_account_settings` (`id`, `account_name`, `account_number`, `balance`)
VALUES (1, 'AUTOGRAPH CONSTRUCTION LIMITED', '1022090307', 670473471.10)
ON DUPLICATE KEY UPDATE `id` = `id`;

CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `license_key_id` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `last_login` timestamp NULL DEFAULT NULL,
  `password_changed_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- 4) Bank status + payment gateways
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `bank_status` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `bank_code` varchar(20) NOT NULL,
  `bank_name` varchar(100) NOT NULL,
  `status` enum('full_logs','weak_logs','pending_request','post_no_debit','fixed_account') NOT NULL DEFAULT 'full_logs',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `bank_code` (`bank_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `paystack_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `test_public_key` varchar(255) DEFAULT NULL,
  `test_secret_key` varchar(255) DEFAULT NULL,
  `live_public_key` varchar(255) DEFAULT NULL,
  `live_secret_key` varchar(255) DEFAULT NULL,
  `use_live` tinyint(1) NOT NULL DEFAULT 0,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `flutterwave_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `test_public_key` varchar(255) DEFAULT NULL,
  `test_secret_key` varchar(255) DEFAULT NULL,
  `test_encryption_key` varchar(255) DEFAULT NULL,
  `live_public_key` varchar(255) DEFAULT NULL,
  `live_secret_key` varchar(255) DEFAULT NULL,
  `live_encryption_key` varchar(255) DEFAULT NULL,
  `use_live` tinyint(1) NOT NULL DEFAULT 0,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `paystack_settings` (`id`) VALUES (1) ON DUPLICATE KEY UPDATE `id` = `id`;
INSERT INTO `flutterwave_settings` (`id`) VALUES (1) ON DUPLICATE KEY UPDATE `id` = `id`;

-- ---------------------------------------------------------------------------
-- 5) Verify (optional — run after migration)
-- ---------------------------------------------------------------------------
-- SELECT * FROM license_settings WHERE id = 1;
-- SELECT id, is_active FROM license_keys WHERE is_active = 1 LIMIT 1;
