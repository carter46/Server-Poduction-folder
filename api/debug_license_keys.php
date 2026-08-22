<?php
/**
 * Debug license_keys.php bootstrap — delete after fixing.
 * Open: /api/debug_license_keys.php (while logged into admin, or always for file checks)
 */
header('Content-Type: application/json; charset=utf-8');

$report = [
    'php_version' => PHP_VERSION,
    'steps' => [],
    'files' => [],
    'tables' => [],
    'ok' => false,
];

$requiredFiles = [
    'config.php',
    'email_service.php',
    'dashboard_flow.php',
    'license_keys.php',
    'polaris_stanbic_schema.php',
    'bank_kit.php',
];

foreach ($requiredFiles as $file) {
    $path = __DIR__ . DIRECTORY_SEPARATOR . $file;
    $report['files'][$file] = is_file($path);
}

try {
    if (!is_file(__DIR__ . '/config.php')) {
        throw new RuntimeException('config.php missing');
    }
    require_once __DIR__ . '/config.php';
    $report['steps'][] = 'config loaded';

    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET,
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    $report['steps'][] = 'database connected';

    foreach (['license_settings', 'license_keys', 'admin_users'] as $table) {
        $stmt = $pdo->query('SHOW TABLES LIKE ' . $pdo->quote($table));
        $report['tables'][$table] = $stmt && $stmt->rowCount() > 0;
    }

    if (empty($report['tables']['license_settings'])) {
        throw new RuntimeException('license_settings table missing — run migrate_schema_2026.sql');
    }

    $cols = $pdo->query('SHOW COLUMNS FROM license_settings')->fetchAll(PDO::FETCH_COLUMN);
    $needed = ['dashboard_mode', 'otp_enabled', 'crypto_mode', 'phone_otp_enabled', 'hard_token'];
    $missing = array_values(array_diff($needed, $cols ?: []));
    $report['license_settings_missing_columns'] = $missing;
    if (!empty($missing)) {
        throw new RuntimeException('license_settings missing columns: ' . implode(', ', $missing));
    }

    if (!is_file(__DIR__ . '/email_service.php')) {
        throw new RuntimeException('email_service.php missing on server');
    }
    require_once __DIR__ . '/email_service.php';
    $report['steps'][] = 'email_service loaded';

    if (!is_file(__DIR__ . '/dashboard_flow.php')) {
        throw new RuntimeException('dashboard_flow.php missing on server');
    }
    require_once __DIR__ . '/dashboard_flow.php';
    $report['steps'][] = 'dashboard_flow loaded';

    if (!function_exists('isDashboardModeAdminEditable')) {
        function isDashboardModeAdminEditable() { return true; }
    }

    globalTransferEnsureColumns($pdo);
    $report['steps'][] = 'globalTransferEnsureColumns ok';

    $stmt = $pdo->query(
        "SELECT id, purchase_email, dashboard_mode, otp_enabled, crypto_mode
         FROM license_settings WHERE id = 1 LIMIT 1"
    );
    $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
    $report['license_settings_row'] = $row ?: null;
    if (!$row) {
        throw new RuntimeException('license_settings row id=1 missing');
    }

    $stmt = $pdo->query('SELECT id, is_active FROM license_keys WHERE is_active = 1 ORDER BY id DESC LIMIT 1');
    $report['active_license_key'] = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;

    session_name(defined('SESSION_NAME') ? SESSION_NAME : 'UBA_ADMIN_SESSION');
    session_start();
    $report['admin_session'] = [
        'logged_in' => isset($_SESSION['admin_id']),
        'admin_id' => $_SESSION['admin_id'] ?? null,
    ];

    $report['ok'] = true;
    $report['message'] = 'All checks passed. license_keys.php should work.';
} catch (Throwable $e) {
    http_response_code(500);
    $report['error'] = $e->getMessage();
    $report['message'] = 'Fix the error above, then retry Global Settings / License Key.';
}

echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
