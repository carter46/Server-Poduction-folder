<?php
/**
 * Platform Status API (Global)
 * Controls whether the platform is available (on/off).
 *
 * - GET: public, returns current status
 * - PUT: admin only, updates status
 */
require_once 'config.php';

$method = $_SERVER['REQUEST_METHOD'];
$pdo = getDBConnection();

function platformStatusEnsureSchema(PDO $pdo): void
{
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS platform_status (
            id INT PRIMARY KEY,
            status ENUM('on','off') NOT NULL DEFAULT 'on',
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )");
    } catch (PDOException $e) {
        // continue
    }

    try {
        $stmt = $pdo->query("SELECT id FROM platform_status WHERE id = 1 LIMIT 1");
        if (!$stmt || !$stmt->fetch()) {
            $pdo->exec("INSERT INTO platform_status (id, status) VALUES (1, 'on')");
        }
    } catch (PDOException $e) {
        // continue
    }
}

function platformStatusNormalize($raw): string
{
    $v = strtolower(trim((string)$raw));
    return $v === 'off' ? 'off' : 'on';
}

platformStatusEnsureSchema($pdo);

switch ($method) {
    case 'GET':
        try {
            $stmt = $pdo->query("SELECT status, updated_at FROM platform_status WHERE id = 1 LIMIT 1");
            $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
            if (!$row) {
                sendResponse(true, ['status' => 'on', 'updated_at' => null]);
            }
            sendResponse(true, [
                'status' => platformStatusNormalize($row['status'] ?? 'on'),
                'updated_at' => $row['updated_at'] ?? null,
            ]);
        } catch (PDOException $e) {
            handleError('Failed to fetch platform status: ' . $e->getMessage(), 500);
        }
        break;

    case 'PUT':
        validateAdminSession();

        $input = getJsonInput();
        $status = platformStatusNormalize($input['status'] ?? '');
        if (!isset($input['status']) || !in_array((string)$input['status'], ['on', 'off'], true)) {
            handleError('Invalid status. Expected "on" or "off".', 400);
        }
        $status = platformStatusNormalize($input['status']);

        try {
            $stmt = $pdo->prepare(
                "INSERT INTO platform_status (id, status) VALUES (1, ?)
                 ON DUPLICATE KEY UPDATE status = VALUES(status), updated_at = CURRENT_TIMESTAMP"
            );
            $stmt->execute([$status]);

            sendResponse(true, [
                'status' => $status,
            ], 'Platform status updated successfully');
        } catch (PDOException $e) {
            handleError('Failed to update platform status: ' . $e->getMessage(), 500);
        }
        break;

    default:
        handleError('Method not allowed', 405);
        break;
}
