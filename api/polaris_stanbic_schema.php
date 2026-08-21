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

    polarisEnsureTransferColumns($pdo);
}

function polarisDefaultCryptoAssetsJson(): string
{
    $assets = [
        [
            'id' => 'bitcoin',
            'symbol' => 'BTC',
            'name' => 'Bitcoin',
            'image' => 'https://assets.coingecko.com/coins/images/1/small/bitcoin.png',
            'enabled' => true,
        ],
        [
            'id' => 'tether',
            'symbol' => 'USDT',
            'name' => 'Tether',
            'image' => 'https://assets.coingecko.com/coins/images/325/small/Tether.png',
            'enabled' => true,
        ],
        [
            'id' => 'ethereum',
            'symbol' => 'ETH',
            'name' => 'Ethereum',
            'image' => 'https://assets.coingecko.com/coins/images/279/small/ethereum.png',
            'enabled' => true,
        ],
    ];
    return json_encode($assets);
}

function polarisAddColumnIfMissing(PDO $pdo, string $table, string $column, string $ddl): void
{
    try {
        $check = $pdo->prepare(
            "SELECT COUNT(*) AS c FROM information_schema.columns
             WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?"
        );
        $check->execute([$table, $column]);
        $count = intval(($check->fetch() ?: [])['c'] ?? 0);
        if ($count === 0) {
            $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN {$ddl}");
        }
    } catch (PDOException $e) {
        // ignore duplicate / race
    }
}

function polarisEnsureTransferColumns(PDO $pdo): void
{
    $defaultJson = polarisDefaultCryptoAssetsJson();
    polarisAddColumnIfMissing($pdo, 'polaris_bank_account_settings', 'otp_enabled', "otp_enabled TINYINT(1) NOT NULL DEFAULT 0");
    polarisAddColumnIfMissing($pdo, 'polaris_bank_account_settings', 'hard_token_enabled', "hard_token_enabled TINYINT(1) NOT NULL DEFAULT 0");
    polarisAddColumnIfMissing($pdo, 'polaris_bank_account_settings', 'hard_token', "hard_token VARCHAR(64) DEFAULT NULL");
    polarisAddColumnIfMissing($pdo, 'polaris_bank_account_settings', 'otp_hash', "otp_hash VARCHAR(255) DEFAULT NULL");
    polarisAddColumnIfMissing($pdo, 'polaris_bank_account_settings', 'otp_expires_at', "otp_expires_at DATETIME DEFAULT NULL");
    polarisAddColumnIfMissing($pdo, 'polaris_bank_account_settings', 'otp_challenge_id', "otp_challenge_id VARCHAR(64) DEFAULT NULL");
    polarisAddColumnIfMissing($pdo, 'polaris_bank_account_settings', 'otp_intent_hash', "otp_intent_hash VARCHAR(64) DEFAULT NULL");
    polarisAddColumnIfMissing($pdo, 'polaris_bank_account_settings', 'otp_verified', "otp_verified TINYINT(1) NOT NULL DEFAULT 0");
    polarisAddColumnIfMissing($pdo, 'polaris_bank_account_settings', 'phone_otp_verified', "phone_otp_verified TINYINT(1) NOT NULL DEFAULT 0");
    polarisAddColumnIfMissing($pdo, 'polaris_bank_account_settings', 'crypto_assets', "crypto_assets TEXT DEFAULT NULL");
    polarisAddColumnIfMissing($pdo, 'polaris_bank_account_settings', 'default_transfer_status', "default_transfer_status ENUM('SUCCESSFUL','PENDING','FAILED') NOT NULL DEFAULT 'SUCCESSFUL'");

    try {
        $stmt = $pdo->query("SELECT id, crypto_assets FROM polaris_bank_account_settings ORDER BY id DESC LIMIT 1");
        $row = $stmt ? $stmt->fetch() : false;
        if ($row && (empty($row['crypto_assets']) || trim((string)$row['crypto_assets']) === '')) {
            $upd = $pdo->prepare("UPDATE polaris_bank_account_settings SET crypto_assets = ? WHERE id = ?");
            $upd->execute([$defaultJson, $row['id']]);
        }
    } catch (PDOException $e) {
        // ignore
    }

    polarisAddColumnIfMissing($pdo, 'polaris_bank_transactions', 'transfer_type', "transfer_type VARCHAR(20) NOT NULL DEFAULT 'bank'");
    polarisAddColumnIfMissing($pdo, 'polaris_bank_transactions', 'crypto_symbol', "crypto_symbol VARCHAR(20) DEFAULT NULL");
    polarisAddColumnIfMissing($pdo, 'polaris_bank_transactions', 'crypto_amount', "crypto_amount DECIMAL(24,12) DEFAULT NULL");
    polarisAddColumnIfMissing($pdo, 'polaris_bank_transactions', 'crypto_rate_ngn', "crypto_rate_ngn DECIMAL(20,8) DEFAULT NULL");
    polarisAddColumnIfMissing($pdo, 'polaris_bank_transactions', 'wallet_address', "wallet_address VARCHAR(255) DEFAULT NULL");
}
