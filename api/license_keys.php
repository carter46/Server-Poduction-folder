<?php
/**
 * License Key Management API
 * Admin only - handles license key generation and settings
 */

require_once 'config.php';
require_once 'email_service.php';

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

function licenseValidEmailList(string $raw): bool
{
    $parts = preg_split('/[;,]+/', $raw) ?: [];
    $found = false;
    foreach ($parts as $part) {
        $email = trim($part);
        if ($email === '') {
            continue;
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        $found = true;
    }
    return $found;
}

function fetchLicenseSettingsRow(PDO $pdo) {
    emailEnsureMailColumns($pdo);
    $stmt = $pdo->prepare("SELECT id, purchase_email, renewal_gate, software_activated, normal_delay_seconds, renewal_delay_seconds, expected_signature, updated_at FROM license_settings WHERE id = 1");
    $stmt->execute();
    $settings = $stmt->fetch();

    if (!$settings) {
        $pdo->exec("INSERT INTO license_settings (id, purchase_email) VALUES (1, 'support@ubadashboard.com')");
        $stmt->execute();
        $settings = $stmt->fetch();
    }

    $mailRow = emailLoadMailRow($pdo);
    $mail = $mailRow ? emailPublicMailFlags($mailRow) : [];

    return array_merge([
        'id' => (int)$settings['id'],
        'purchase_email' => $settings['purchase_email'] ?: 'support@ubadashboard.com',
        'renewal_gate' => $settings['renewal_gate'] ?: 'off',
        'software_activated' => $settings['software_activated'] ?: 'no',
        'normal_delay_seconds' => (int)($settings['normal_delay_seconds'] ?? 15),
        'renewal_delay_seconds' => (int)($settings['renewal_delay_seconds'] ?? 25),
        'expected_signature' => $settings['expected_signature'] ?: 'UBA-RENEWAL-SIG-A8829F0D11D992A',
        'updated_at' => $settings['updated_at'] ?? date('Y-m-d H:i:s'),
    ], $mail);
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

        if ($action === 'send_test_email') {
            $to = trim((string)($input['test_recipient'] ?? ''));
            if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
                handleError('Enter a valid test recipient email');
            }
            $html = '<p>This is a test message from License Key email settings.</p><p>If you received this, the Email Service is working.</p>';
            $result = emailSendHtml($pdo, [$to], 'License Key test email', $html, true);
            sendResponse(!empty($result['ok']), [
                'sent_via' => $result['sent_via'],
                'phpmailer_status' => $result['phpmailer_status'],
                'phpmailer_error' => $result['phpmailer_error'],
                'brevo_status' => $result['brevo_status'],
                'brevo_error' => $result['brevo_error'],
            ], $result['message']);
            break;
        }

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
            || isset($input['expected_signature'])
            || isset($input['mail_phpmailer_enabled'])
            || isset($input['mail_smtp_host'])
            || isset($input['mail_smtp_port'])
            || isset($input['mail_smtp_username'])
            || isset($input['mail_smtp_password'])
            || isset($input['mail_smtp_encryption'])
            || isset($input['mail_from_email'])
            || isset($input['mail_from_name'])
            || isset($input['mail_reply_to'])
            || isset($input['mail_brevo_enabled'])
            || isset($input['mail_brevo_api_key'])
            || isset($input['mail_brevo_sender_email'])
            || isset($input['mail_brevo_sender_name']);

        if (!$hasAny) {
            handleError('No update data provided');
        }

        if (isset($input['purchase_email']) && !licenseValidEmailList((string)$input['purchase_email'])) {
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

        foreach (['mail_from_email', 'mail_brevo_sender_email', 'mail_reply_to'] as $emailField) {
            if (!isset($input[$emailField])) {
                continue;
            }
            $val = trim((string)$input[$emailField]);
            if ($val !== '' && !filter_var($val, FILTER_VALIDATE_EMAIL)) {
                handleError('Invalid ' . $emailField);
            }
        }

        if (isset($input['mail_smtp_encryption'])) {
            $enc = strtolower(trim((string)$input['mail_smtp_encryption']));
            if (!in_array($enc, ['tls', 'ssl'], true)) {
                handleError('Encryption must be tls or ssl');
            }
        }

        try {
            emailEnsureMailColumns($pdo);
            $current = fetchLicenseSettingsRow($pdo);
            $row = emailLoadMailRow($pdo) ?: [];

            $purchaseEmail = isset($input['purchase_email']) ? $input['purchase_email'] : $current['purchase_email'];
            $renewalGate = isset($input['renewal_gate']) ? $input['renewal_gate'] : $current['renewal_gate'];
            $normalDelay = isset($input['normal_delay_seconds']) ? (int)$input['normal_delay_seconds'] : $current['normal_delay_seconds'];
            $renewalDelay = isset($input['renewal_delay_seconds']) ? (int)$input['renewal_delay_seconds'] : $current['renewal_delay_seconds'];
            $expectedSignature = isset($input['expected_signature']) && $input['expected_signature'] !== ''
                ? trim($input['expected_signature'])
                : $current['expected_signature'];

            $phpOn = isset($input['mail_phpmailer_enabled']) ? ($input['mail_phpmailer_enabled'] ? 1 : 0) : intval($row['mail_phpmailer_enabled'] ?? 0);
            $brevoOn = isset($input['mail_brevo_enabled']) ? ($input['mail_brevo_enabled'] ? 1 : 0) : intval($row['mail_brevo_enabled'] ?? 0);
            $smtpHost = isset($input['mail_smtp_host']) ? trim((string)$input['mail_smtp_host']) : (string)($row['mail_smtp_host'] ?? '');
            $smtpPort = isset($input['mail_smtp_port']) ? intval($input['mail_smtp_port']) : intval($row['mail_smtp_port'] ?? 587);
            if ($smtpPort < 1 || $smtpPort > 65535) {
                handleError('SMTP port is invalid');
            }
            $smtpUser = isset($input['mail_smtp_username']) ? trim((string)$input['mail_smtp_username']) : (string)($row['mail_smtp_username'] ?? '');
            $smtpEnc = isset($input['mail_smtp_encryption']) ? strtolower(trim((string)$input['mail_smtp_encryption'])) : (string)($row['mail_smtp_encryption'] ?? 'tls');
            $fromEmail = isset($input['mail_from_email']) ? trim((string)$input['mail_from_email']) : (string)($row['mail_from_email'] ?? '');
            $fromName = isset($input['mail_from_name']) ? trim((string)$input['mail_from_name']) : (string)($row['mail_from_name'] ?? '');
            $replyTo = isset($input['mail_reply_to']) ? trim((string)$input['mail_reply_to']) : (string)($row['mail_reply_to'] ?? '');
            $brevoSenderEmail = isset($input['mail_brevo_sender_email']) ? trim((string)$input['mail_brevo_sender_email']) : (string)($row['mail_brevo_sender_email'] ?? '');
            $brevoSenderName = isset($input['mail_brevo_sender_name']) ? trim((string)$input['mail_brevo_sender_name']) : (string)($row['mail_brevo_sender_name'] ?? '');

            $smtpPassword = (string)($row['mail_smtp_password'] ?? '');
            if (isset($input['mail_smtp_password']) && trim((string)$input['mail_smtp_password']) !== '') {
                $smtpPassword = (string)$input['mail_smtp_password'];
            }
            $brevoKey = (string)($row['mail_brevo_api_key'] ?? '');
            if (isset($input['mail_brevo_api_key']) && trim((string)$input['mail_brevo_api_key']) !== '') {
                $brevoKey = (string)$input['mail_brevo_api_key'];
            }

            $stmt = $pdo->prepare("UPDATE license_settings SET
                purchase_email = ?,
                renewal_gate = ?,
                normal_delay_seconds = ?,
                renewal_delay_seconds = ?,
                expected_signature = ?,
                mail_phpmailer_enabled = ?,
                mail_smtp_host = ?,
                mail_smtp_port = ?,
                mail_smtp_username = ?,
                mail_smtp_password = ?,
                mail_smtp_encryption = ?,
                mail_from_email = ?,
                mail_from_name = ?,
                mail_reply_to = ?,
                mail_brevo_enabled = ?,
                mail_brevo_api_key = ?,
                mail_brevo_sender_email = ?,
                mail_brevo_sender_name = ?,
                updated_at = NOW()
                WHERE id = 1");
            $stmt->execute([
                $purchaseEmail,
                $renewalGate,
                $normalDelay,
                $renewalDelay,
                $expectedSignature,
                $phpOn,
                $smtpHost !== '' ? $smtpHost : null,
                $smtpPort,
                $smtpUser !== '' ? $smtpUser : null,
                $smtpPassword !== '' ? $smtpPassword : null,
                $smtpEnc,
                $fromEmail !== '' ? $fromEmail : null,
                $fromName !== '' ? $fromName : null,
                $replyTo !== '' ? $replyTo : null,
                $brevoOn,
                $brevoKey !== '' ? $brevoKey : null,
                $brevoSenderEmail !== '' ? $brevoSenderEmail : null,
                $brevoSenderName !== '' ? $brevoSenderName : null,
            ]);

            $settings = fetchLicenseSettingsRow($pdo);
            sendResponse(true, $settings, 'License settings updated successfully');
        } catch (PDOException $e) {
            handleError('Failed to update settings: ' . $e->getMessage(), 500);
        }
        break;

    default:
        handleError('Method not allowed', 405);
}
