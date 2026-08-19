-- Mobile Companion migration
-- Run against the same MySQL database as the web app.
-- Balance rule (simulation): SUM(SUCCESSFUL beneficiary amounts) for (bank, account) — no recipient wallet table.

CREATE TABLE IF NOT EXISTS `mobile_settings` (
  `id` INT(11) NOT NULL PRIMARY KEY DEFAULT 1,
  `password_hash` VARCHAR(255) DEFAULT NULL,
  `fcm_server_key` VARCHAR(512) DEFAULT NULL COMMENT 'Legacy FCM server key (optional)',
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `mobile_settings` (`id`, `password_hash`)
VALUES (1, NULL)
ON DUPLICATE KEY UPDATE `id` = `id`;

CREATE TABLE IF NOT EXISTS `mobile_sessions` (
  `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `token` VARCHAR(128) NOT NULL,
  `bank_code` VARCHAR(20) NOT NULL COMMENT 'UBA|FIRST|ZENITH|ACCESS|WEMA',
  `account_number` VARCHAR(50) NOT NULL,
  `account_name_snapshot` VARCHAR(255) DEFAULT NULL,
  `expires_at` DATETIME NOT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uniq_mobile_session_token` (`token`),
  KEY `idx_mobile_session_account` (`bank_code`, `account_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `mobile_device_tokens` (
  `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `bank_code` VARCHAR(20) NOT NULL,
  `account_number` VARCHAR(50) NOT NULL,
  `fcm_token` VARCHAR(512) NOT NULL COMMENT 'Expo push token (ExponentPushToken[...]) or native FCM token',
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uniq_mobile_device` (`bank_code`, `account_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Beneficiary lookup indexes (also auto-created by mobileEnsureSchema)
-- CREATE INDEX idx_ben_acct ON each *_transactions (beneficiary_account) if missing.
