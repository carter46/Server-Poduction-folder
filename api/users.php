<?php
/**
 * User Management API
 * Admin only - handles CRUD operations for users
 */

require_once 'config.php';

$method = $_SERVER['REQUEST_METHOD'];
$pdo = getDBConnection();

// Validate admin session
$adminId = validateAdminSession();

switch ($method) {
    case 'GET':
        // Get all users
        try {
            $stmt = $pdo->query("
                SELECT u.id, u.username, u.created_at, u.last_login, 
                       lk.license_key, lk.is_active as license_active
                FROM users u
                LEFT JOIN license_keys lk ON u.license_key_id = lk.id
                ORDER BY u.created_at DESC
            ");
            $users = $stmt->fetchAll();
            sendResponse(true, $users);
        } catch (PDOException $e) {
            handleError('Failed to fetch users: ' . $e->getMessage(), 500);
        }
        break;
        
    case 'POST':
        // Create new user
        $input = getJsonInput();
        
        if (!isset($input['username']) || !isset($input['password'])) {
            handleError('Username and password are required');
        }
        
        try {
            // Check if username already exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->execute([$input['username']]);
            if ($stmt->fetch()) {
                handleError('Username already exists');
            }
            
            // Get the active license key
            $stmt = $pdo->prepare("SELECT id FROM license_keys WHERE is_active = 1 ORDER BY created_at DESC LIMIT 1");
            $stmt->execute();
            $activeLicenseKey = $stmt->fetch();
            
            if (!$activeLicenseKey) {
                handleError('No active license key available. Please generate a license key first.');
            }
            
            // Create user
            $stmt = $pdo->prepare("INSERT INTO users (username, password, license_key_id, password_changed_at) VALUES (?, ?, ?, NOW())");
            $stmt->execute([$input['username'], $input['password'], $activeLicenseKey['id']]);
            
            $userId = $pdo->lastInsertId();
            
            // Fetch created user
            $stmt = $pdo->prepare("
                SELECT u.id, u.username, u.created_at, u.last_login, 
                       lk.license_key, lk.is_active as license_active
                FROM users u
                LEFT JOIN license_keys lk ON u.license_key_id = lk.id
                WHERE u.id = ?
            ");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();
            
            sendResponse(true, $user, 'User created successfully');
        } catch (PDOException $e) {
            handleError('Failed to create user: ' . $e->getMessage(), 500);
        }
        break;
        
    case 'PUT':
        // Update user
        $input = getJsonInput();
        $userId = $_GET['id'] ?? null;
        
        if (!$userId) {
            handleError('User ID is required');
        }
        
        try {
            $updates = [];
            $params = [];
            
            if (isset($input['username'])) {
                // Check if username already exists (excluding current user)
                $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
                $stmt->execute([$input['username'], $userId]);
                if ($stmt->fetch()) {
                    handleError('Username already exists');
                }
                $updates[] = "username = ?";
                $params[] = $input['username'];
            }
            
            if (isset($input['password'])) {
                $updates[] = "password = ?";
                $params[] = $input['password'];
                // Update password_changed_at timestamp when password is changed
                $updates[] = "password_changed_at = NOW()";
            }
            
            if (empty($updates)) {
                handleError('No fields to update');
            }
            
            $params[] = $userId;
            $sql = "UPDATE users SET " . implode(", ", $updates) . " WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            
            // Fetch updated user
            $stmt = $pdo->prepare("
                SELECT u.id, u.username, u.created_at, u.last_login, 
                       lk.license_key, lk.is_active as license_active
                FROM users u
                LEFT JOIN license_keys lk ON u.license_key_id = lk.id
                WHERE u.id = ?
            ");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();
            
            sendResponse(true, $user, 'User updated successfully');
        } catch (PDOException $e) {
            handleError('Failed to update user: ' . $e->getMessage(), 500);
        }
        break;
        
    case 'DELETE':
        // Delete user
        $userId = $_GET['id'] ?? null;
        
        if (!$userId) {
            handleError('User ID is required');
        }
        
        try {
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            sendResponse(true, null, 'User deleted successfully');
        } catch (PDOException $e) {
            handleError('Failed to delete user: ' . $e->getMessage(), 500);
        }
        break;
        
    default:
        handleError('Method not allowed', 405);
}

