<?php
/**
 * Account Settings API
 * Handles account name, account number, and balance management
 */

require_once 'config.php';

$method = $_SERVER['REQUEST_METHOD'];
$pdo = getDBConnection();

switch ($method) {
    case 'GET':
        // Get account settings
        try {
            $stmt = $pdo->query("SELECT account_name, account_number, balance FROM uba_account_settings ORDER BY id DESC LIMIT 1");
            $account = $stmt->fetch();
            
            if (!$account) {
                // Initialize with default values if no account exists
                $stmt = $pdo->prepare("INSERT INTO uba_account_settings (account_name, account_number, balance) VALUES (?, ?, ?)");
                $stmt->execute(['AUTOGRAPH CONSTRUCTION LIMITED', '1022090307', 670473471.10]);
                
                $account = [
                    'account_name' => 'AUTOGRAPH CONSTRUCTION LIMITED',
                    'account_number' => '1022090307',
                    'balance' => 670473471.10
                ];
            }
            
            // Ensure balance is returned as a number (not string)
            if (isset($account['balance'])) {
                $account['balance'] = floatval($account['balance']);
            }
            
            sendResponse(true, $account);
        } catch (PDOException $e) {
            handleError('Failed to fetch account settings: ' . $e->getMessage(), 500);
        }
        break;
        
    case 'PUT':
        // Update account settings (Admin only)
        validateAdminSession();
        
        $input = getJsonInput();
        
        if (empty($input)) {
            handleError('No update data provided');
        }
        
        try {
            // Ensure account settings exist
            $stmt = $pdo->query("SELECT id FROM uba_account_settings ORDER BY id DESC LIMIT 1");
            $existing = $stmt->fetch();
            
            if (!$existing) {
                $stmt = $pdo->prepare("INSERT INTO uba_account_settings (account_name, account_number, balance) VALUES (?, ?, ?)");
                $stmt->execute(['AUTOGRAPH CONSTRUCTION LIMITED', '1022090307', 670473471.10]);
            }
            
            // Build update query
            $updates = [];
            $params = [];
            
            if (isset($input['account_name'])) {
                $updates[] = "account_name = ?";
                $params[] = $input['account_name'];
            }
            
            if (isset($input['account_number'])) {
                $updates[] = "account_number = ?";
                $params[] = $input['account_number'];
            }
            
            if (isset($input['balance'])) {
                $updates[] = "balance = ?";
                $params[] = floatval($input['balance']);
            }
            
            if (empty($updates)) {
                handleError('No valid fields to update');
            }
            
            $updates[] = "updated_at = NOW()";
            // Get the most recent account setting ID first
            $stmt = $pdo->query("SELECT id FROM uba_account_settings ORDER BY id DESC LIMIT 1");
            $accountRow = $stmt->fetch();
            $accountId = $accountRow['id'];
            
            // Update the account setting
            $sql = "UPDATE uba_account_settings SET " . implode(", ", $updates) . " WHERE id = ?";
            $params[] = $accountId;
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            
            // Return updated account
            $stmt = $pdo->query("SELECT account_name, account_number, balance FROM uba_account_settings ORDER BY id DESC LIMIT 1");
            $account = $stmt->fetch();
            
            // Ensure balance is returned as a number (not string)
            if (isset($account['balance'])) {
                $account['balance'] = floatval($account['balance']);
            }
            
            sendResponse(true, $account, 'Account settings updated successfully');
        } catch (PDOException $e) {
            handleError('Failed to update account settings: ' . $e->getMessage(), 500);
        }
        break;
        
    default:
        handleError('Method not allowed', 405);
}

