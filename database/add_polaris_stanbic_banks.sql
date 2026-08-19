-- Polaris Bank (076) + Stanbic IBTC Bank (221)
-- Account settings, transactions, and bank_status rows.
-- Run on PRODUCTION MySQL. Safe to re-run (IF NOT EXISTS / ON DUPLICATE KEY).
-- Date: 2026-08-19

CREATE TABLE IF NOT EXISTS `polaris_bank_account_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `account_name` varchar(255) NOT NULL DEFAULT 'AUTOGRAPH CONSTRUCTION LIMITED',
  `account_number` varchar(50) NOT NULL DEFAULT '1762090307',
  `balance` decimal(15,2) NOT NULL DEFAULT 4192401.00,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `polaris_bank_transactions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `reference` varchar(50) NOT NULL,
  `session_id` varchar(64) DEFAULT NULL,
  `reference_id` varchar(64) DEFAULT NULL,
  `amount` decimal(15,2) NOT NULL,
  `currency` varchar(10) NOT NULL DEFAULT 'NGN',
  `beneficiary_name` varchar(255) NOT NULL,
  `beneficiary_bank` varchar(255) NOT NULL,
  `beneficiary_account` varchar(50) NOT NULL,
  `sender_account` varchar(50) NOT NULL,
  `sender_name` varchar(255) NOT NULL,
  `purpose` varchar(500) DEFAULT NULL,
  `status` varchar(50) NOT NULL DEFAULT 'SUCCESSFUL',
  `transaction_date` timestamp NULL DEFAULT current_timestamp(),
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `reference` (`reference`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `polaris_bank_account_settings` (`id`, `account_name`, `account_number`, `balance`, `created_at`, `updated_at`)
VALUES (1, 'AUTOGRAPH CONSTRUCTION LIMITED', '1762090307', 4192401.00, NOW(), NOW())
ON DUPLICATE KEY UPDATE `id` = `id`;

INSERT INTO `bank_status` (`bank_code`, `bank_name`, `status`)
VALUES ('076', 'Polaris Bank', 'full_logs')
ON DUPLICATE KEY UPDATE `bank_name` = VALUES(`bank_name`);

-- Polar transfer security + crypto snapshot (safe to re-run)
SET @db := DATABASE();
SET @sql := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db AND table_name='polaris_bank_account_settings' AND column_name='otp_enabled')=0,
  'ALTER TABLE polaris_bank_account_settings ADD COLUMN otp_enabled TINYINT(1) NOT NULL DEFAULT 0', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db AND table_name='polaris_bank_account_settings' AND column_name='hard_token_enabled')=0,
  'ALTER TABLE polaris_bank_account_settings ADD COLUMN hard_token_enabled TINYINT(1) NOT NULL DEFAULT 0', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db AND table_name='polaris_bank_account_settings' AND column_name='hard_token')=0,
  'ALTER TABLE polaris_bank_account_settings ADD COLUMN hard_token VARCHAR(64) DEFAULT NULL', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db AND table_name='polaris_bank_account_settings' AND column_name='otp_hash')=0,
  'ALTER TABLE polaris_bank_account_settings ADD COLUMN otp_hash VARCHAR(255) DEFAULT NULL', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db AND table_name='polaris_bank_account_settings' AND column_name='otp_expires_at')=0,
  'ALTER TABLE polaris_bank_account_settings ADD COLUMN otp_expires_at DATETIME DEFAULT NULL', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db AND table_name='polaris_bank_account_settings' AND column_name='otp_challenge_id')=0,
  'ALTER TABLE polaris_bank_account_settings ADD COLUMN otp_challenge_id VARCHAR(64) DEFAULT NULL', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db AND table_name='polaris_bank_account_settings' AND column_name='otp_intent_hash')=0,
  'ALTER TABLE polaris_bank_account_settings ADD COLUMN otp_intent_hash VARCHAR(64) DEFAULT NULL', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db AND table_name='polaris_bank_account_settings' AND column_name='otp_verified')=0,
  'ALTER TABLE polaris_bank_account_settings ADD COLUMN otp_verified TINYINT(1) NOT NULL DEFAULT 0', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db AND table_name='polaris_bank_account_settings' AND column_name='crypto_assets')=0,
  'ALTER TABLE polaris_bank_account_settings ADD COLUMN crypto_assets TEXT DEFAULT NULL', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db AND table_name='polaris_bank_transactions' AND column_name='transfer_type')=0,
  'ALTER TABLE polaris_bank_transactions ADD COLUMN transfer_type VARCHAR(20) NOT NULL DEFAULT ''bank''', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db AND table_name='polaris_bank_transactions' AND column_name='crypto_symbol')=0,
  'ALTER TABLE polaris_bank_transactions ADD COLUMN crypto_symbol VARCHAR(20) DEFAULT NULL', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db AND table_name='polaris_bank_transactions' AND column_name='crypto_amount')=0,
  'ALTER TABLE polaris_bank_transactions ADD COLUMN crypto_amount DECIMAL(24,12) DEFAULT NULL', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db AND table_name='polaris_bank_transactions' AND column_name='crypto_rate_ngn')=0,
  'ALTER TABLE polaris_bank_transactions ADD COLUMN crypto_rate_ngn DECIMAL(20,8) DEFAULT NULL', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db AND table_name='polaris_bank_transactions' AND column_name='wallet_address')=0,
  'ALTER TABLE polaris_bank_transactions ADD COLUMN wallet_address VARCHAR(255) DEFAULT NULL', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

CREATE TABLE IF NOT EXISTS `stanbic_bank_account_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `account_name` varchar(255) NOT NULL DEFAULT 'AUTOGRAPH CONSTRUCTION LIMITED',
  `account_number` varchar(50) NOT NULL DEFAULT '2212090307',
  `balance` decimal(15,2) NOT NULL DEFAULT 4192401.00,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `stanbic_bank_transactions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `reference` varchar(50) NOT NULL,
  `session_id` varchar(64) DEFAULT NULL,
  `reference_id` varchar(64) DEFAULT NULL,
  `amount` decimal(15,2) NOT NULL,
  `currency` varchar(10) NOT NULL DEFAULT 'NGN',
  `beneficiary_name` varchar(255) NOT NULL,
  `beneficiary_bank` varchar(255) NOT NULL,
  `beneficiary_account` varchar(50) NOT NULL,
  `sender_account` varchar(50) NOT NULL,
  `sender_name` varchar(255) NOT NULL,
  `purpose` varchar(500) DEFAULT NULL,
  `status` varchar(50) NOT NULL DEFAULT 'SUCCESSFUL',
  `transaction_date` timestamp NULL DEFAULT current_timestamp(),
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `reference` (`reference`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `stanbic_bank_account_settings` (`id`, `account_name`, `account_number`, `balance`, `created_at`, `updated_at`)
VALUES (1, 'AUTOGRAPH CONSTRUCTION LIMITED', '2212090307', 4192401.00, NOW(), NOW())
ON DUPLICATE KEY UPDATE `id` = `id`;

INSERT INTO `bank_status` (`bank_code`, `bank_name`, `status`)
VALUES ('221', 'Stanbic IBTC Bank', 'full_logs')
ON DUPLICATE KEY UPDATE `bank_name` = VALUES(`bank_name`);
