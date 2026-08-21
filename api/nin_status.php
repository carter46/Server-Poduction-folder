<?php
/**
 * NIN Status API
 * Active/Inactive for Bank Verify NIN field (when NIN display is enabled).
 */

require_once 'config.php';

$method = $_SERVER['REQUEST_METHOD'];
$pdo = getDBConnection();

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS nin_status (
        id INT AUTO_INCREMENT PRIMARY KEY,
        status ENUM('active', 'inactive') DEFAULT 'active',
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");

    $stmt = $pdo->query("SELECT COUNT(*) as count FROM nin_status");
    $result = $stmt->fetch();
    if ($result['count'] == 0) {
        $pdo->exec("INSERT INTO nin_status (status) VALUES ('active')");
    }
} catch (PDOException $e) {
    // Table might already exist
}

switch ($method) {
    case 'GET':
        try {
            $stmt = $pdo->query("SELECT status FROM nin_status ORDER BY id DESC LIMIT 1");
            $status = $stmt->fetch();

            if (!$status) {
                $pdo->exec("INSERT INTO nin_status (status) VALUES ('active')");
                $stmt = $pdo->query("SELECT status FROM nin_status ORDER BY id DESC LIMIT 1");
                $status = $stmt->fetch();
            }

            sendResponse(true, ['status' => $status['status']]);
        } catch (PDOException $e) {
            handleError('Failed to fetch NIN status: ' . $e->getMessage(), 500);
        }
        break;

    case 'PUT':
        validateAdminSession();

        $input = getJsonInput();

        if (!isset($input['status']) || !in_array($input['status'], ['active', 'inactive'])) {
            handleError('Invalid status. Must be "active" or "inactive".');
        }

        try {
            $stmt = $pdo->query("SELECT id FROM nin_status ORDER BY id DESC LIMIT 1");
            $existing = $stmt->fetch();

            if (!$existing) {
                $stmt = $pdo->prepare("INSERT INTO nin_status (status) VALUES (?)");
                $stmt->execute([$input['status']]);
            } else {
                $stmt = $pdo->prepare("UPDATE nin_status SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                $stmt->execute([$input['status'], $existing['id']]);
            }

            sendResponse(true, ['status' => $input['status']], 'NIN status updated successfully');
        } catch (PDOException $e) {
            handleError('Failed to update NIN status: ' . $e->getMessage(), 500);
        }
        break;

    default:
        handleError('Method not allowed', 405);
        break;
}
