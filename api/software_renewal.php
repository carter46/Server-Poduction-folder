<?php
/**
 * Software Renewal Upload API (Public — used before login)
 * Validates structured renewal PHP package and marks software as activated
 */

require_once 'config.php';

$method = $_SERVER['REQUEST_METHOD'];
$pdo = getDBConnection();

function ensureLicenseSettingsSchemaForRenewal(PDO $pdo) {
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

/**
 * Safely extract renewal fields without executing uploaded PHP.
 */
function parseRenewalPhpArray($content) {
    $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
    // Normalize line endings
    $content = str_replace(["\r\n", "\r"], "\n", $content);

    $data = [];

    if (preg_match('/[\'"]software[\'"]\s*=>\s*[\'"]([^\'"]+)[\'"]/', $content, $m)) {
        $data['software'] = trim($m[1]);
    }
    if (preg_match('/[\'"]expires[\'"]\s*=>\s*[\'"]([^\'"]+)[\'"]/', $content, $m)) {
        $data['expires'] = trim($m[1]);
    }
    if (preg_match('/[\'"]signature[\'"]\s*=>\s*[\'"]([^\'"]+)[\'"]/', $content, $m)) {
        $data['signature'] = trim($m[1]);
    }
    if (preg_match('/[\'"]checksum[\'"]\s*=>\s*[\'"]([^\'"]+)[\'"]/', $content, $m)) {
        $data['checksum'] = trim($m[1]);
    }

    if (empty($data['software']) || empty($data['expires']) || empty($data['signature'])) {
        return null;
    }

    return $data;
}

ensureLicenseSettingsSchemaForRenewal($pdo);

if ($method !== 'POST') {
    handleError('Method not allowed', 405);
}

if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    handleError('Please upload a valid renewal file');
}

$file = $_FILES['file'];
$originalName = $file['name'] ?? '';
$tmpPath = $file['tmp_name'] ?? '';
$size = (int)($file['size'] ?? 0);

$ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
if ($ext !== 'php') {
    handleError('Only .php renewal files are accepted');
}

if ($size <= 0 || $size > 50 * 1024) {
    handleError('Renewal file must be 50KB or less');
}

$content = @file_get_contents($tmpPath);
if ($content === false) {
    handleError('Could not read uploaded file');
}

// Reject dangerous executable patterns (word-boundary safe — does not match "requires")
if (preg_match('/\b(eval|exec|system|shell_exec|passthru|proc_open|popen|assert|create_function)\s*\(/i', $content)) {
    handleError('Invalid renewal file content');
}
if (preg_match('/\b(include|require|include_once|require_once)\s*[\!(]/i', $content)) {
    handleError('Invalid renewal file content');
}

if (!preg_match('/return\s*\[/s', $content) && !preg_match('/[\'"]software[\'"]\s*=>/', $content)) {
    handleError('Invalid renewal file format');
}

$data = parseRenewalPhpArray($content);
if (!is_array($data)) {
    handleError('Invalid renewal file structure');
}

$software = isset($data['software']) ? trim((string)$data['software']) : '';
$expires = isset($data['expires']) ? trim((string)$data['expires']) : '';
$signature = isset($data['signature']) ? trim((string)$data['signature']) : '';

if ($software !== 'Secure Banking Encryption Suite') {
    handleError('Renewal file software name is invalid');
}

$expiresTs = strtotime($expires);
if (!$expiresTs) {
    handleError('Renewal file expiry date is invalid');
}

if ($expiresTs < strtotime(date('Y-m-d'))) {
    handleError('Renewal file has expired');
}

try {
    $stmt = $pdo->prepare("SELECT expected_signature FROM license_settings WHERE id = 1");
    $stmt->execute();
    $settings = $stmt->fetch();
    $expected = $settings && !empty($settings['expected_signature'])
        ? $settings['expected_signature']
        : 'UBA-RENEWAL-SIG-A8829F0D11D992A';
} catch (PDOException $e) {
    handleError('Failed to validate renewal signature', 500);
}

if (!hash_equals($expected, $signature)) {
    handleError('Renewal file signature is invalid');
}

if (isset($data['checksum']) && $data['checksum'] !== '') {
    $expectedChecksum = hash('sha256', $software . '|' . $expires . '|' . $signature);
    if (!hash_equals($expectedChecksum, (string)$data['checksum'])) {
        handleError('Renewal file checksum is invalid');
    }
}

try {
    $stmt = $pdo->prepare("UPDATE license_settings SET software_activated = 'yes', updated_at = NOW() WHERE id = 1");
    $stmt->execute();

    sendResponse(true, [
        'software_activated' => 'yes',
    ], 'Software renewal activated successfully');
} catch (PDOException $e) {
    handleError('Failed to activate software: ' . $e->getMessage(), 500);
}
