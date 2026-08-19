<?php
/**
 * Creates Polar / Stanbic tables if they are missing (safe to call on every request).
 */
function ensurePolarisStanbicSchema(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS `polaris_bank_account_settings` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `account_name` varchar(255) NOT NULL DEFAULT 'AUTOGRAPH CONSTRUCTION LIMITED',
        `account_number` varchar(50) NOT NULL DEFAULT '1762090307',
        `balance` decimal(15,2) NOT NULL DEFAULT 4192401.00,
        `created_at` timestamp NULL DEFAULT current_timestamp(),
        `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `polaris_bank_transactions` (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `stanbic_bank_account_settings` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `account_name` varchar(255) NOT NULL DEFAULT 'AUTOGRAPH CONSTRUCTION LIMITED',
        `account_number` varchar(50) NOT NULL DEFAULT '2212090307',
        `balance` decimal(15,2) NOT NULL DEFAULT 4192401.00,
        `created_at` timestamp NULL DEFAULT current_timestamp(),
        `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `stanbic_bank_transactions` (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `bank_status` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `bank_code` VARCHAR(20) UNIQUE NOT NULL,
        `bank_name` VARCHAR(100) NOT NULL,
        `status` ENUM('full_logs', 'weak_logs', 'pending_request', 'post_no_debit', 'fixed_account') DEFAULT 'full_logs',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");

    $seedStatus = $pdo->prepare("INSERT INTO `bank_status` (`bank_code`, `bank_name`, `status`) VALUES (?, ?, 'full_logs') ON DUPLICATE KEY UPDATE `bank_name` = VALUES(`bank_name`)");
    $seedStatus->execute(['076', 'Polaris Bank']);
    $seedStatus->execute(['221', 'Stanbic IBTC Bank']);
}
