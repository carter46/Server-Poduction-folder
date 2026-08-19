<?php
/**
 * Bank Status API
 * Handles bank status management (Full Logs, Weak Logs, Pending Request)
 */

require_once 'config.php';

$method = $_SERVER['REQUEST_METHOD'];
$pdo = getDBConnection();

// Bank name mapping
$bankNames = [
    '033' => 'UBA',
    '011' => 'First Bank',
    '044' => 'Access Bank',
    '070' => 'Fidelity Bank',
    '058' => 'Guaranty Trust Bank',
    '030' => 'Heritage Bank',
    '301' => 'Jaiz Bank',
    '082' => 'Keystone Bank',
    '232' => 'Sterling Bank',
    '032' => 'Union Bank',
    '215' => 'Unity Bank',
    '035' => 'Wema Bank',
    '076' => 'Polaris Bank',
    '221' => 'Stanbic IBTC Bank',
    '057' => 'Zenith Bank',
    '50211' => 'Kuda Bank',
    '50515' => 'Moniepoint',
    '999992' => 'OPay',
    '100033' => 'PalmPay',
];

// Create table if it doesn't exist
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS bank_status (
        id INT AUTO_INCREMENT PRIMARY KEY,
        bank_code VARCHAR(20) UNIQUE NOT NULL,
        bank_name VARCHAR(100) NOT NULL,
        status ENUM('full_logs', 'weak_logs', 'pending_request', 'post_no_debit', 'fixed_account') DEFAULT 'full_logs',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
    
    // Initialize with default values if table is empty
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM bank_status");
    $result = $stmt->fetch();
    if ($result['count'] == 0) {
        $insertStmt = $pdo->prepare("INSERT INTO bank_status (bank_code, bank_name, status) VALUES (?, ?, 'full_logs')");
        foreach ($bankNames as $code => $name) {
            $insertStmt->execute([$code, $name]);
        }
    }
} catch (PDOException $e) {
    // Table might already exist, continue
}

switch ($method) {
    case 'GET':
        // Get all bank statuses or specific bank by code
        try {
            if (isset($_GET['bank_code'])) {
                $bankCode = $_GET['bank_code'];
                $stmt = $pdo->prepare("SELECT * FROM bank_status WHERE bank_code = ?");
                $stmt->execute([$bankCode]);
                $status = $stmt->fetch();
                
                if (!$status) {
                    // Create default entry if doesn't exist
                    $bankName = $bankNames[$bankCode] ?? 'Unknown Bank';
                    $stmt = $pdo->prepare("INSERT INTO bank_status (bank_code, bank_name, status) VALUES (?, ?, 'full_logs')");
                    $stmt->execute([$bankCode, $bankName]);
                    
                    $stmt = $pdo->prepare("SELECT * FROM bank_status WHERE bank_code = ?");
                    $stmt->execute([$bankCode]);
                    $status = $stmt->fetch();
                }
                
                sendResponse(true, $status);
            } else {
                $stmt = $pdo->query("SELECT * FROM bank_status ORDER BY bank_name");
                $statuses = $stmt->fetchAll();
                sendResponse(true, $statuses);
            }
        } catch (PDOException $e) {
            handleError('Failed to fetch bank statuses: ' . $e->getMessage(), 500);
        }
        break;
        
    case 'PUT':
        // Update bank statuses (Admin only)
        validateAdminSession();
        
        $input = getJsonInput();
        
        if (!isset($input['statuses']) || !is_array($input['statuses'])) {
            handleError('Invalid input. Expected array of statuses.');
        }
        
        try {
            $pdo->beginTransaction();
            
            $updateStmt = $pdo->prepare("UPDATE bank_status SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE bank_code = ?");
            $insertStmt = $pdo->prepare("INSERT INTO bank_status (bank_code, bank_name, status) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE status = ?, updated_at = CURRENT_TIMESTAMP");
            
            foreach ($input['statuses'] as $statusData) {
                if (!isset($statusData['bank_code']) || !isset($statusData['status'])) {
                    continue;
                }
                
                $bankCode = $statusData['bank_code'];
                $status = $statusData['status'];
                $bankName = $bankNames[$bankCode] ?? 'Unknown Bank';
                
                // Validate status
                if (!in_array($status, ['full_logs', 'weak_logs', 'pending_request', 'post_no_debit', 'fixed_account'])) {
                    continue;
                }
                
                $insertStmt->execute([$bankCode, $bankName, $status, $status]);
            }
            
            $pdo->commit();
            
            // Return updated statuses
            $stmt = $pdo->query("SELECT * FROM bank_status ORDER BY bank_name");
            $statuses = $stmt->fetchAll();
            
            sendResponse(true, $statuses, 'Bank statuses updated successfully');
        } catch (PDOException $e) {
            $pdo->rollBack();
            handleError('Failed to update bank statuses: ' . $e->getMessage(), 500);
        }
        break;
        
    default:
        handleError('Method not allowed', 405);
        break;
}

