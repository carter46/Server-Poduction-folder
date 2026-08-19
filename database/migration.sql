-- Migration: Paystack Settings + Zenith Bank + Access Bank (Merged)
-- Date: 2026-02-05
-- Description:
--   - Creates/updates `paystack_settings` to support Test/Live Public + Secret keys
--   - Creates Zenith Bank (057) + Access Bank (044) account + transactions tables
-- Notes:
--   - No API keys are hardcoded in this migration.
--   - Designed to be safe to run multiple times.

-- =========================================================
-- 1) Paystack Settings
-- =========================================================

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

-- Add missing columns for older installations (MySQL/MariaDB-safe via INFORMATION_SCHEMA)
SET @ps_tbl := 'paystack_settings';

SET @sql := IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @ps_tbl AND COLUMN_NAME = 'test_public_key') = 0,
  'ALTER TABLE `paystack_settings` ADD COLUMN `test_public_key` varchar(255) DEFAULT NULL AFTER `id`;',
  'SELECT 1;'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @ps_tbl AND COLUMN_NAME = 'test_secret_key') = 0,
  'ALTER TABLE `paystack_settings` ADD COLUMN `test_secret_key` varchar(255) DEFAULT NULL AFTER `test_public_key`;',
  'SELECT 1;'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @ps_tbl AND COLUMN_NAME = 'live_public_key') = 0,
  'ALTER TABLE `paystack_settings` ADD COLUMN `live_public_key` varchar(255) DEFAULT NULL AFTER `test_secret_key`;',
  'SELECT 1;'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @ps_tbl AND COLUMN_NAME = 'live_secret_key') = 0,
  'ALTER TABLE `paystack_settings` ADD COLUMN `live_secret_key` varchar(255) DEFAULT NULL AFTER `live_public_key`;',
  'SELECT 1;'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @ps_tbl AND COLUMN_NAME = 'use_live') = 0,
  'ALTER TABLE `paystack_settings` ADD COLUMN `use_live` tinyint(1) NOT NULL DEFAULT 0 AFTER `live_secret_key`;',
  'SELECT 1;'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @ps_tbl AND COLUMN_NAME = 'updated_at') = 0,
  'ALTER TABLE `paystack_settings` ADD COLUMN `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() AFTER `use_live`;',
  'SELECT 1;'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Migrate legacy secret keys if legacy columns exist (test_key/live_key → test_secret_key/live_secret_key)
SET @has_test_key := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @ps_tbl AND COLUMN_NAME = 'test_key');
SET @has_live_key := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @ps_tbl AND COLUMN_NAME = 'live_key');

SET @sql := IF(
  @has_live_key > 0,
  'UPDATE `paystack_settings` SET `live_secret_key` = `live_key` WHERE `live_secret_key` IS NULL AND `live_key` IS NOT NULL;',
  'SELECT 1;'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
  @has_test_key > 0,
  'UPDATE `paystack_settings` SET `test_secret_key` = `test_key` WHERE `test_secret_key` IS NULL AND `test_key` IS NOT NULL;',
  'SELECT 1;'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Ensure row id=1 exists (no secrets are inserted)
INSERT INTO `paystack_settings` (`id`, `test_public_key`, `test_secret_key`, `live_public_key`, `live_secret_key`, `use_live`, `updated_at`)
VALUES (1, NULL, NULL, NULL, NULL, 0, NOW())
ON DUPLICATE KEY UPDATE `id` = `id`;

-- =========================================================
-- 2) Zenith Bank (057)
-- =========================================================

CREATE TABLE IF NOT EXISTS `zenith_bank_account_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `account_name` varchar(255) NOT NULL DEFAULT 'AUTOGRAPH CONSTRUCTION LIMITED',
  `account_number` varchar(50) NOT NULL DEFAULT '1022090307',
  `balance` decimal(15,2) NOT NULL DEFAULT 4192401.00,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `zenith_bank_transactions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `reference` varchar(50) NOT NULL,
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

INSERT INTO `zenith_bank_account_settings` (`id`, `account_name`, `account_number`, `balance`, `created_at`, `updated_at`)
VALUES (1, 'AUTOGRAPH CONSTRUCTION LIMITED', '1022090307', 4192401.00, NOW(), NOW())
ON DUPLICATE KEY UPDATE `updated_at` = NOW();

-- =========================================================
-- 3) Access Bank (044)
-- =========================================================

CREATE TABLE IF NOT EXISTS `access_bank_account_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `account_name` varchar(255) NOT NULL DEFAULT 'AUTOGRAPH CONSTRUCTION LIMITED',
  `account_number` varchar(50) NOT NULL DEFAULT '1022090307',
  `balance` decimal(15,2) NOT NULL DEFAULT 4192401.00,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `access_bank_transactions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `reference` varchar(50) NOT NULL,
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

INSERT INTO `access_bank_account_settings` (`id`, `account_name`, `account_number`, `balance`, `created_at`, `updated_at`)
VALUES (1, 'AUTOGRAPH CONSTRUCTION LIMITED', '1022090307', 4192401.00, NOW(), NOW())
ON DUPLICATE KEY UPDATE `updated_at` = NOW();

-- =========================================================
-- 4) Platform Status (Global)
-- =========================================================

CREATE TABLE IF NOT EXISTS `platform_status` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `status` enum('on','off') NOT NULL DEFAULT 'on',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ensure row id=1 exists
INSERT INTO `platform_status` (`id`, `status`, `updated_at`)
VALUES (1, 'on', NOW())
ON DUPLICATE KEY UPDATE `id` = `id`;

