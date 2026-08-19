<?php
/**
 * BVN Status API
 * Handles BVN field status management (Active/Inactive)
 */

require_once 'config.php';

$method = $_SERVER['REQUEST_METHOD'];
$pdo = getDBConnection();

// Create table if it doesn't exist
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS bvn_status (
        id INT AUTO_INCREMENT PRIMARY KEY,
        status ENUM('active', 'inactive') DEFAULT 'active',
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
    
    // Initialize with default active status if table is empty
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM bvn_status");
    $result = $stmt->fetch();
    if ($result['count'] == 0) {
        $pdo->exec("INSERT INTO bvn_status (status) VALUES ('active')");
    }
} catch (PDOException $e) {
    // Table might already exist, continue
}

switch ($method) {
    case 'GET':
        // Get BVN status (public endpoint - no auth required)
        try {
            $stmt = $pdo->query("SELECT status FROM bvn_status ORDER BY id DESC LIMIT 1");
            $status = $stmt->fetch();
            
            if (!$status) {
                // Initialize with default
                $pdo->exec("INSERT INTO bvn_status (status) VALUES ('active')");
                $stmt = $pdo->query("SELECT status FROM bvn_status ORDER BY id DESC LIMIT 1");
                $status = $stmt->fetch();
            }
            
            sendResponse(true, ['status' => $status['status']]);
        } catch (PDOException $e) {
            handleError('Failed to fetch BVN status: ' . $e->getMessage(), 500);
        }
        break;
        
    case 'PUT':
        // Update BVN status (Admin only)
        validateAdminSession();
        
        $input = getJsonInput();
        
        if (!isset($input['status']) || !in_array($input['status'], ['active', 'inactive'])) {
            handleError('Invalid status. Must be "active" or "inactive".');
        }
        
        try {
            // Get or create status row
            $stmt = $pdo->query("SELECT id FROM bvn_status ORDER BY id DESC LIMIT 1");
            $existing = $stmt->fetch();
            
            if (!$existing) {
                $stmt = $pdo->prepare("INSERT INTO bvn_status (status) VALUES (?)");
                $stmt->execute([$input['status']]);
            } else {
                $stmt = $pdo->prepare("UPDATE bvn_status SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                $stmt->execute([$input['status'], $existing['id']]);
            }
            
            sendResponse(true, ['status' => $input['status']], 'BVN status updated successfully');
        } catch (PDOException $e) {
            handleError('Failed to update BVN status: ' . $e->getMessage(), 500);
        }
        break;
        
    default:
        handleError('Method not allowed', 405);
        break;
}
