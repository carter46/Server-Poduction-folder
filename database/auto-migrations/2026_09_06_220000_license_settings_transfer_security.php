<?php
/**
 * Ensure license_settings transfer-security / Mode columns exist.
 * Idempotent — safe if columns were already added by older ensure* helpers.
 */

return [
    'id' => '2026_09_06_220000_license_settings_transfer_security',
    'description' => 'license_settings: dashboard_mode, OTP, phone OTP number, hard token, crypto, restrictions, log status',
    'up' => function (PDO $pdo) {
        DatabaseAutoMigrate::execOrFail(
            $pdo,
            "CREATE TABLE IF NOT EXISTS `license_settings` (
                `id` INT PRIMARY KEY,
                `purchase_email` VARCHAR(255) DEFAULT 'support@ubadashboard.com',
                `renewal_gate` ENUM('off','on') NOT NULL DEFAULT 'off',
                `software_activated` ENUM('no','yes') NOT NULL DEFAULT 'no',
                `normal_delay_seconds` INT NOT NULL DEFAULT 15,
                `renewal_delay_seconds` INT NOT NULL DEFAULT 25,
                `expected_signature` VARCHAR(255) NOT NULL DEFAULT 'UBA-RENEWAL-SIG-A8829F0D11D992A',
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            'Create license_settings'
        );

        $cols = [
            'dashboard_mode' => "`dashboard_mode` ENUM('on','off') NOT NULL DEFAULT 'on'",
            'otp_enabled' => '`otp_enabled` TINYINT(1) NOT NULL DEFAULT 0',
            'hard_token_enabled' => '`hard_token_enabled` TINYINT(1) NOT NULL DEFAULT 0',
            'hard_token' => '`hard_token` VARCHAR(64) DEFAULT NULL',
            'default_transfer_status' => "`default_transfer_status` ENUM('SUCCESSFUL','PENDING','FAILED') NOT NULL DEFAULT 'SUCCESSFUL'",
            'transfer_restriction' => '`transfer_restriction` TINYINT(1) NOT NULL DEFAULT 0',
            'risky_transaction' => '`risky_transaction` TINYINT(1) NOT NULL DEFAULT 0',
            'nin_verification' => '`nin_verification` TINYINT(1) NOT NULL DEFAULT 0',
            'log_status' => "`log_status` ENUM('full_logs','weak_logs','pending_request','post_no_debit','fixed_account') NOT NULL DEFAULT 'full_logs'",
            'crypto_mode' => "`crypto_mode` ENUM('on','off') NOT NULL DEFAULT 'on'",
            'phone_otp_enabled' => '`phone_otp_enabled` TINYINT(1) NOT NULL DEFAULT 0',
            'phone_otp_number' => "`phone_otp_number` VARCHAR(32) NOT NULL DEFAULT ''",
        ];

        foreach ($cols as $name => $definition) {
            DatabaseAutoMigrate::ensureColumn($pdo, 'license_settings', $name, $definition);
        }

        $stmt = $pdo->query('SELECT id FROM license_settings WHERE id = 1 LIMIT 1');
        if ($stmt && !$stmt->fetch()) {
            $pdo->exec("INSERT INTO license_settings (id, purchase_email) VALUES (1, 'support@ubadashboard.com')");
        }
    },
];
