-- Wema Bank (035) account settings + transactions
-- Run on PRODUCTION MySQL. Safe to re-run (IF NOT EXISTS / ON DUPLICATE KEY).
-- Date: 2026-08-18

CREATE TABLE IF NOT EXISTS `wema_bank_account_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `account_name` varchar(255) NOT NULL DEFAULT 'AUTOGRAPH CONSTRUCTION LIMITED',
  `account_number` varchar(50) NOT NULL DEFAULT '1022090307',
  `balance` decimal(15,2) NOT NULL DEFAULT 4192401.00,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `wema_bank_transactions` (
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

INSERT INTO `wema_bank_account_settings` (`id`, `account_name`, `account_number`, `balance`, `created_at`, `updated_at`)
VALUES (1, 'AUTOGRAPH CONSTRUCTION LIMITED', '1022090307', 4192401.00, NOW(), NOW())
ON DUPLICATE KEY UPDATE `id` = `id`;
