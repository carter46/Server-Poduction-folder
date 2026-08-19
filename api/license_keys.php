<?php
/**
 * License Key Management API
 * Admin only - handles license key generation and settings
 */

require_once 'config.php';

$method = $_SERVER['REQUEST_METHOD'];
$pdo = getDBConnection();

function ensureLicenseSettingsSchemaAdmin(PDO $pdo) {
    $columns = [
        'renewal_gate' => "ALTER TABLE license_settings ADD COLUMN renewal_gate ENUM('off','on') NOT NULL DEFAULT 'off' AFTER purchase_email",
        'software_activated' => "ALTER TABLE license_settings ADD COLUMN software_activated ENUM('no','yes') NOT NULL DEFAULT 'no' AFTER renewal_gate",
        'normal_delay_seconds' => "ALTER TABLE license_settings ADD COLUMN normal_delay_seconds INT NOT NULL DEFAULT 15 AFTER software_activated",
        'renewal_delay_seconds' => "ALTER TABLE license_settings ADD COLUMN renewal_delay_seconds INT NOT NULL DEFAULT 25 AFTER normal_delay_seconds",
        'expected_signature' => "ALTER TABLE license_settings ADD COLUMN expected_signature VARCHAR(255) NOT NULL DEFAULT 'UBA-RENEWAL-SIG-A8829F0D11D992A' AFTER renewal_delay_seconds",
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

function fetchLicenseSettingsRow(PDO $pdo) {
    $stmt = $pdo->prepare("SELECT id, purchase_email, renewal_gate, software_activated, normal_delay_seconds, renewal_delay_seconds, expected_signature, updated_at FROM license_settings WHERE id = 1");
    $stmt->execute();
    $settings = $stmt->fetch();

    if (!$settings) {
        $pdo->exec("INSERT INTO license_settings (id, purchase_email) VALUES (1, 'support@ubadashboard.com')");
        $stmt->execute();
        $settings = $stmt->fetch();
    }

    return [
        'id' => (int)$settings['id'],
        'purchase_email' => $settings['purchase_email'] ?: 'support@ubadashboard.com',
        'renewal_gate' => $settings['renewal_gate'] ?: 'off',
        'software_activated' => $settings['software_activated'] ?: 'no',
        'normal_delay_seconds' => (int)($settings['normal_delay_seconds'] ?? 15),
        'renewal_delay_seconds' => (int)($settings['renewal_delay_seconds'] ?? 25),
        'expected_signature' => $settings['expected_signature'] ?: 'UBA-RENEWAL-SIG-A8829F0D11D992A',
        'updated_at' => $settings['updated_at'] ?? date('Y-m-d H:i:s'),
    ];
}

ensureLicenseSettingsSchemaAdmin($pdo);

// Validate admin session
$adminId = validateAdminSession();

switch ($method) {
    case 'GET':
        try {
            $stmt = $pdo->prepare("SELECT id, license_key, is_active, created_at FROM license_keys WHERE is_active = 1 ORDER BY created_at DESC LIMIT 1");
            $stmt->execute();
            $activeLicenseKey = $stmt->fetch();

            $settings = fetchLicenseSettingsRow($pdo);

            sendResponse(true, [
                'active_license_key' => $activeLicenseKey ?: null,
                'settings' => $settings,
            ]);
        } catch (PDOException $e) {
            handleError('Failed to fetch license key: ' . $e->getMessage(), 500);
        }
        break;

    case 'POST':
        $input = getJsonInput() ?: [];
        $action = $input['action'] ?? 'generate';

        if ($action === 'reset_activation') {
            try {
                $stmt = $pdo->prepare("UPDATE license_settings SET software_activated = 'no', updated_at = NOW() WHERE id = 1");
                $stmt->execute();
                $settings = fetchLicenseSettingsRow($pdo);
                sendResponse(true, $settings, 'Software activation reset successfully');
            } catch (PDOException $e) {
                handleError('Failed to reset activation: ' . $e->getMessage(), 500);
            }
            break;
        }

        // Generate new license key (deactivates old ones and updates all users)
        try {
            $stmt = $pdo->prepare("UPDATE license_keys SET is_active = 0");
            $stmt->execute();

            $licenseKey = bin2hex(random_bytes(32));

            $stmt = $pdo->prepare("INSERT INTO license_keys (license_key, is_active) VALUES (?, 1)");
            $stmt->execute([$licenseKey]);

            $licenseKeyId = $pdo->lastInsertId();

            $stmt = $pdo->prepare("UPDATE users SET license_key_id = ?");
            $stmt->execute([$licenseKeyId]);

            $stmt = $pdo->prepare("SELECT id, license_key, is_active, created_at FROM license_keys WHERE id = ?");
            $stmt->execute([$licenseKeyId]);
            $newLicenseKey = $stmt->fetch();

            sendResponse(true, $newLicenseKey, 'New license key generated successfully. All existing users have been updated to use the new license key.');
        } catch (PDOException $e) {
            handleError('Failed to generate license key: ' . $e->getMessage(), 500);
        }
        break;

    case 'PUT':
        $input = getJsonInput();

        $hasAny = isset($input['purchase_email'])
            || isset($input['renewal_gate'])
            || isset($input['normal_delay_seconds'])
            || isset($input['renewal_delay_seconds'])
            || isset($input['expected_signature']);

        if (!$hasAny) {
            handleError('No update data provided');
        }

        if (isset($input['purchase_email']) && !filter_var($input['purchase_email'], FILTER_VALIDATE_EMAIL)) {
            handleError('Invalid email format');
        }

        if (isset($input['renewal_gate']) && !in_array($input['renewal_gate'], ['off', 'on'], true)) {
            handleError('Invalid renewal_gate. Must be "off" or "on".');
        }

        if (isset($input['normal_delay_seconds'])) {
            $n = (int)$input['normal_delay_seconds'];
            if ($n < 1 || $n > 300) {
                handleError('Normal delay must be between 1 and 300 seconds');
            }
        }

        if (isset($input['renewal_delay_seconds'])) {
            $n = (int)$input['renewal_delay_seconds'];
            if ($n < 1 || $n > 300) {
                handleError('Renewal delay must be between 1 and 300 seconds');
            }
        }

        try {
            $current = fetchLicenseSettingsRow($pdo);

            $purchaseEmail = isset($input['purchase_email']) ? $input['purchase_email'] : $current['purchase_email'];
            $renewalGate = isset($input['renewal_gate']) ? $input['renewal_gate'] : $current['renewal_gate'];
            $normalDelay = isset($input['normal_delay_seconds']) ? (int)$input['normal_delay_seconds'] : $current['normal_delay_seconds'];
            $renewalDelay = isset($input['renewal_delay_seconds']) ? (int)$input['renewal_delay_seconds'] : $current['renewal_delay_seconds'];
            $expectedSignature = isset($input['expected_signature']) && $input['expected_signature'] !== ''
                ? trim($input['expected_signature'])
                : $current['expected_signature'];

            $stmt = $pdo->prepare("UPDATE license_settings SET
                purchase_email = ?,
                renewal_gate = ?,
                normal_delay_seconds = ?,
                renewal_delay_seconds = ?,
                expected_signature = ?,
                updated_at = NOW()
                WHERE id = 1");
            $stmt->execute([$purchaseEmail, $renewalGate, $normalDelay, $renewalDelay, $expectedSignature]);

            $settings = fetchLicenseSettingsRow($pdo);
            sendResponse(true, $settings, 'License settings updated successfully');
        } catch (PDOException $e) {
            handleError('Failed to update settings: ' . $e->getMessage(), 500);
        }
        break;

    default:
        handleError('Method not allowed', 405);
}
