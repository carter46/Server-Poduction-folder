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

// Create table if it doesn't exist and ensure row id=1 exists
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS platform_status (
        id INT AUTO_INCREMENT PRIMARY KEY,
        status ENUM('on','off') NOT NULL DEFAULT 'on',
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");

    $stmt = $pdo->query("SELECT COUNT(*) as count FROM platform_status");
    $result = $stmt->fetch();
    if ($result && (int)$result['count'] === 0) {
        $insertStmt = $pdo->prepare("INSERT INTO platform_status (id, status) VALUES (1, 'on')");
        $insertStmt->execute();
    } else {
        // Ensure id=1 exists even if table has rows
        $pdo->exec("INSERT INTO platform_status (id, status) VALUES (1, 'on')
            ON DUPLICATE KEY UPDATE id = id");
    }
} catch (PDOException $e) {
    // Table might already exist, continue
}

switch ($method) {
    case 'GET':
        try {
            $stmt = $pdo->query("SELECT status, updated_at FROM platform_status WHERE id = 1 LIMIT 1");
            $row = $stmt->fetch();
            if (!$row) {
                // Fallback
                sendResponse(true, ['status' => 'on']);
            }
            sendResponse(true, [
                'status' => $row['status'],
                'updated_at' => $row['updated_at'],
            ]);
        } catch (PDOException $e) {
            handleError('Failed to fetch platform status: ' . $e->getMessage(), 500);
        }
        break;

    case 'PUT':
        validateAdminSession();

        $input = getJsonInput();
        $status = isset($input['status']) ? $input['status'] : null;

        if (!in_array($status, ['on', 'off'], true)) {
            handleError('Invalid status. Expected "on" or "off".', 400);
        }

        try {
            $stmt = $pdo->prepare("UPDATE platform_status SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = 1");
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

