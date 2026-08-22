<?php
/**
 * Temporary server diagnostic — upload, open /api/ping.php in browser, then delete.
 */
header('Content-Type: application/json; charset=utf-8');

$result = [
    'php_version' => PHP_VERSION,
    'config_loaded' => false,
    'db_connected' => false,
    'tables' => [],
];

try {
    require_once __DIR__ . '/config.php';
    $result['config_loaded'] = true;

    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET,
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
    $result['db_connected'] = true;

    foreach (['uba_account_settings', 'admin_users', 'license_settings'] as $table) {
        $stmt = $pdo->query('SHOW TABLES LIKE ' . $pdo->quote($table));
        $result['tables'][$table] = $stmt->rowCount() > 0;
    }

    echo json_encode(['success' => true, 'data' => $result], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
} catch (Throwable $e) {
    http_response_code(500);
    $result['error'] = $e->getMessage();
    echo json_encode(
        ['success' => false, 'message' => $e->getMessage(), 'data' => $result],
        JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
    );
}
