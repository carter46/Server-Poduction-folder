<?php
/**
 * License Settings API (Public)
 * Returns purchase email, software renewal gate settings, and public global transfer flags.
 * Never exposes hard_token or default_transfer_status.
 *
 * Self-contained: does not require dashboard_flow.php so this endpoint still returns
 * valid JSON when that helper is missing or broken on the host.
 */

require_once 'config.php';

$method = $_SERVER['REQUEST_METHOD'];
$pdo = getDBConnection();

/**
 * Ensure license_settings table has renewal/activation/transfer flag columns
 */
function ensureLicenseSettingsSchema(PDO $pdo) {
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS license_settings (
            id INT PRIMARY KEY,
            purchase_email VARCHAR(255) DEFAULT 'support@ubadashboard.com',
            renewal_gate ENUM('off','on') NOT NULL DEFAULT 'off',
            software_activated ENUM('no','yes') NOT NULL DEFAULT 'no',
            normal_delay_seconds INT NOT NULL DEFAULT 15,
            renewal_delay_seconds INT NOT NULL DEFAULT 25,
            expected_signature VARCHAR(255) NOT NULL DEFAULT 'UBA-RENEWAL-SIG-A8829F0D11D992A',
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )");
    } catch (PDOException $e) {
        // continue
    }

    $columns = [
        'renewal_gate' => "ALTER TABLE license_settings ADD COLUMN renewal_gate ENUM('off','on') NOT NULL DEFAULT 'off' AFTER purchase_email",
        'software_activated' => "ALTER TABLE license_settings ADD COLUMN software_activated ENUM('no','yes') NOT NULL DEFAULT 'no' AFTER renewal_gate",
        'normal_delay_seconds' => "ALTER TABLE license_settings ADD COLUMN normal_delay_seconds INT NOT NULL DEFAULT 15 AFTER software_activated",
        'renewal_delay_seconds' => "ALTER TABLE license_settings ADD COLUMN renewal_delay_seconds INT NOT NULL DEFAULT 25 AFTER normal_delay_seconds",
        'expected_signature' => "ALTER TABLE license_settings ADD COLUMN expected_signature VARCHAR(255) NOT NULL DEFAULT 'UBA-RENEWAL-SIG-A8829F0D11D992A' AFTER renewal_delay_seconds",
        'dashboard_mode' => "ALTER TABLE license_settings ADD COLUMN dashboard_mode ENUM('on','off') NOT NULL DEFAULT 'on' AFTER renewal_gate",
        'otp_enabled' => "ALTER TABLE license_settings ADD COLUMN otp_enabled TINYINT(1) NOT NULL DEFAULT 0",
        'hard_token_enabled' => "ALTER TABLE license_settings ADD COLUMN hard_token_enabled TINYINT(1) NOT NULL DEFAULT 0",
        'transfer_restriction' => "ALTER TABLE license_settings ADD COLUMN transfer_restriction TINYINT(1) NOT NULL DEFAULT 0",
        'risky_transaction' => "ALTER TABLE license_settings ADD COLUMN risky_transaction TINYINT(1) NOT NULL DEFAULT 0",
        'compliance_kyc' => "ALTER TABLE license_settings ADD COLUMN compliance_kyc TINYINT(1) NOT NULL DEFAULT 0",
        'suspicious_transaction_pattern' => "ALTER TABLE license_settings ADD COLUMN suspicious_transaction_pattern TINYINT(1) NOT NULL DEFAULT 0",
        'technical_network_problems' => "ALTER TABLE license_settings ADD COLUMN technical_network_problems TINYINT(1) NOT NULL DEFAULT 0",
        'bank_security_rules' => "ALTER TABLE license_settings ADD COLUMN bank_security_rules TINYINT(1) NOT NULL DEFAULT 0",
        'do_not_honor' => "ALTER TABLE license_settings ADD COLUMN do_not_honor TINYINT(1) NOT NULL DEFAULT 0",
        'incorrect_account_details' => "ALTER TABLE license_settings ADD COLUMN incorrect_account_details TINYINT(1) NOT NULL DEFAULT 0",
        'transaction_limit_exceeded' => "ALTER TABLE license_settings ADD COLUMN transaction_limit_exceeded TINYINT(1) NOT NULL DEFAULT 0",
        'suspected_fraud' => "ALTER TABLE license_settings ADD COLUMN suspected_fraud TINYINT(1) NOT NULL DEFAULT 0",
        'nin_verification' => "ALTER TABLE license_settings ADD COLUMN nin_verification TINYINT(1) NOT NULL DEFAULT 0",
        'log_status' => "ALTER TABLE license_settings ADD COLUMN log_status VARCHAR(32) NOT NULL DEFAULT 'full_logs'",
        'crypto_mode' => "ALTER TABLE license_settings ADD COLUMN crypto_mode ENUM('on','off') NOT NULL DEFAULT 'on'",
        'phone_otp_enabled' => "ALTER TABLE license_settings ADD COLUMN phone_otp_enabled TINYINT(1) NOT NULL DEFAULT 0",
        'phone_otp_number' => "ALTER TABLE license_settings ADD COLUMN phone_otp_number VARCHAR(32) NOT NULL DEFAULT ''",
        'mode_off_bank_dashboards' => "ALTER TABLE license_settings ADD COLUMN mode_off_bank_dashboards TEXT NULL",
        'site_name' => "ALTER TABLE license_settings ADD COLUMN site_name VARCHAR(80) NOT NULL DEFAULT 'UBAS'",
    ];

    foreach ($columns as $name => $sql) {
        try {
            $check = $pdo->query("SHOW COLUMNS FROM license_settings LIKE " . $pdo->quote($name));
            if ($check->rowCount() == 0) {
                $pdo->exec($sql);
            }
        } catch (PDOException $e) {
            // ignore
        }
    }

    try {
        $stmt = $pdo->query("SELECT id FROM license_settings WHERE id = 1");
        if (!$stmt->fetch()) {
            $pdo->exec("INSERT INTO license_settings (id, purchase_email) VALUES (1, 'support@ubadashboard.com')");
        }
    } catch (PDOException $e) {
        // ignore
    }
}

function licensePublicTransferFlags(PDO $pdo): array {
    $defaults = [
        'otp_enabled' => false,
        'hard_token_enabled' => false,
        'transfer_restriction' => false,
        'risky_transaction' => false,
        'compliance_kyc' => false,
        'suspicious_transaction_pattern' => false,
        'technical_network_problems' => false,
        'bank_security_rules' => false,
        'do_not_honor' => false,
        'incorrect_account_details' => false,
        'transaction_limit_exceeded' => false,
        'suspected_fraud' => false,
        'nin_verification' => false,
        'log_status' => 'full_logs',
        'crypto_mode' => 'on',
        'phone_otp_enabled' => false,
        'phone_otp_number' => '',
    ];
    try {
        $stmt = $pdo->query(
            "SELECT otp_enabled, hard_token_enabled, transfer_restriction, risky_transaction,
                    compliance_kyc, suspicious_transaction_pattern, technical_network_problems,
                    bank_security_rules, do_not_honor, incorrect_account_details,
                    transaction_limit_exceeded, suspected_fraud,
                    nin_verification, log_status, crypto_mode, phone_otp_enabled, phone_otp_number
             FROM license_settings WHERE id = 1 LIMIT 1"
        );
        $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
        if (!$row) {
            return $defaults;
        }
        $log = strtolower(trim((string)($row['log_status'] ?? 'full_logs')));
        if ($log === '') {
            $log = 'full_logs';
        }
        $cryptoMode = strtolower(trim((string)($row['crypto_mode'] ?? 'on')));
        if ($cryptoMode !== 'off') {
            $cryptoMode = 'on';
        }
        $phoneDigits = preg_replace('/\D/', '', (string)($row['phone_otp_number'] ?? ''));
        return [
            'otp_enabled' => intval($row['otp_enabled'] ?? 0) === 1,
            'hard_token_enabled' => intval($row['hard_token_enabled'] ?? 0) === 1,
            'transfer_restriction' => intval($row['transfer_restriction'] ?? 0) === 1,
            'risky_transaction' => intval($row['risky_transaction'] ?? 0) === 1,
            'compliance_kyc' => intval($row['compliance_kyc'] ?? 0) === 1,
            'suspicious_transaction_pattern' => intval($row['suspicious_transaction_pattern'] ?? 0) === 1,
            'technical_network_problems' => intval($row['technical_network_problems'] ?? 0) === 1,
            'bank_security_rules' => intval($row['bank_security_rules'] ?? 0) === 1,
            'do_not_honor' => intval($row['do_not_honor'] ?? 0) === 1,
            'incorrect_account_details' => intval($row['incorrect_account_details'] ?? 0) === 1,
            'transaction_limit_exceeded' => intval($row['transaction_limit_exceeded'] ?? 0) === 1,
            'suspected_fraud' => intval($row['suspected_fraud'] ?? 0) === 1,
            'nin_verification' => intval($row['nin_verification'] ?? 0) === 1,
            'log_status' => $log,
            'crypto_mode' => $cryptoMode,
            'phone_otp_enabled' => intval($row['phone_otp_enabled'] ?? 0) === 1,
            'phone_otp_number' => $phoneDigits ?: '',
        ];
    } catch (PDOException $e) {
        return $defaults;
    }
}

ensureLicenseSettingsSchema($pdo);

if ($method === 'GET') {
    try {
        $stmt = $pdo->prepare("SELECT purchase_email, renewal_gate, dashboard_mode, software_activated, normal_delay_seconds, renewal_delay_seconds FROM license_settings WHERE id = 1");
        $stmt->execute();
        $settings = $stmt->fetch();
        $flags = licensePublicTransferFlags($pdo);

        if (!$settings) {
            sendResponse(true, array_merge([
                'purchase_email' => 'support@ubadashboard.com',
                'renewal_gate' => 'off',
                'dashboard_mode' => 'on',
                'software_activated' => 'no',
                'normal_delay_seconds' => 15,
                'renewal_delay_seconds' => 25,
                'site_name' => 'UBAS',
            ], $flags));
        }

        $siteName = 'UBAS';
        try {
            $sn = $pdo->query("SELECT site_name FROM license_settings WHERE id = 1 LIMIT 1");
            $snRow = $sn ? $sn->fetch(PDO::FETCH_ASSOC) : false;
            $raw = trim((string)($snRow['site_name'] ?? ''));
            $siteName = $raw !== '' ? (function_exists('mb_substr') ? mb_substr($raw, 0, 80) : substr($raw, 0, 80)) : 'UBAS';
        } catch (PDOException $e) {
        }

        sendResponse(true, array_merge([
            'purchase_email' => $settings['purchase_email'] ?: 'support@ubadashboard.com',
            'renewal_gate' => $settings['renewal_gate'] ?: 'off',
            'dashboard_mode' => (($settings['dashboard_mode'] ?? 'on') === 'off') ? 'off' : 'on',
            'software_activated' => $settings['software_activated'] ?: 'no',
            'normal_delay_seconds' => (int)($settings['normal_delay_seconds'] ?? 15),
            'renewal_delay_seconds' => (int)($settings['renewal_delay_seconds'] ?? 25),
            'site_name' => $siteName,
        ], $flags));
    } catch (PDOException $e) {
        handleError('Failed to fetch license settings: ' . $e->getMessage(), 500);
    }
} else {
    handleError('Method not allowed', 405);
}
