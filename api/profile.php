<?php
/**
 * Admin Profile API
 * Handles admin profile updates (password, email, username)
 */

require_once 'config.php';

$method = $_SERVER['REQUEST_METHOD'];
$pdo = getDBConnection();

switch ($method) {
    case 'GET':
        // Get admin profile (Admin only)
        validateAdminSession();
        
        try {
            $adminId = $_SESSION['admin_id'];
            $stmt = $pdo->prepare("SELECT id, username, email, created_at, last_login FROM admin_users WHERE id = ?");
            $stmt->execute([$adminId]);
            $admin = $stmt->fetch();
            
            if (!$admin) {
                handleError('Admin not found', 404);
            }
            
            // Don't return password
            unset($admin['password']);
            sendResponse(true, $admin);
        } catch (PDOException $e) {
            handleError('Failed to fetch profile: ' . $e->getMessage(), 500);
        }
        break;
        
    case 'PUT':
        // Update admin profile (Admin only)
        validateAdminSession();
        
        $input = getJsonInput();
        $adminId = $_SESSION['admin_id'];
        
        if (!isset($input['password']) && !isset($input['email']) && !isset($input['username'])) {
            handleError('At least one field (password, email, or username) is required');
        }
        
        try {
            $updates = [];
            $params = [];
            
            if (isset($input['password'])) {
                if (empty($input['password'])) {
                    handleError('Password cannot be empty');
                }
                $updates[] = "password = ?";
                $params[] = $input['password']; // Plain text password
            }
            
            if (isset($input['email'])) {
                $email = trim($input['email']);
                if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    handleError('Invalid email format');
                }
                $updates[] = "email = ?";
                $params[] = $email ?: null;
            }

            if (isset($input['username'])) {
                $newUsername = trim((string)$input['username']);
                if ($newUsername === '') {
                    handleError('Username cannot be empty');
                }
                if (strlen($newUsername) > 100) {
                    handleError('Username is too long');
                }
                $dup = $pdo->prepare("SELECT id FROM admin_users WHERE username = ? AND id != ? LIMIT 1");
                $dup->execute([$newUsername, $adminId]);
                if ($dup->fetch()) {
                    handleError('Username is already taken');
                }
                $updates[] = "username = ?";
                $params[] = $newUsername;
            }

            if (empty($updates)) {
                handleError('No valid fields to update');
            }
            
            $params[] = $adminId;
            
            $sql = "UPDATE admin_users SET " . implode(", ", $updates) . " WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            // Get updated profile
            $stmt = $pdo->prepare("SELECT id, username, email, created_at, last_login FROM admin_users WHERE id = ?");
            $stmt->execute([$adminId]);
            $admin = $stmt->fetch();

            if ($admin && isset($input['username'])) {
                $_SESSION['admin_username'] = $admin['username'];
            }
            
            sendResponse(true, $admin, 'Profile updated successfully');
        } catch (PDOException $e) {
            handleError('Failed to update profile: ' . $e->getMessage(), 500);
        }
        break;
        
    default:
        handleError('Method not allowed', 405);
}

