<?php
/**
 * User Authentication API
 * Handles user login with username, password, and license key
 */

require_once 'config.php';

$method = $_SERVER['REQUEST_METHOD'];
$pdo = getDBConnection();

switch ($method) {
    case 'POST':
        $input = getJsonInput();
        $action = $input['action'] ?? 'login';
        
        if ($action === 'login') {
            // User login
            if (!isset($input['username']) || !isset($input['password']) || !isset($input['license_key'])) {
                handleError('Username, password, and license key are required');
            }
            
            try {
                // First, check if license key is valid and active
                $stmt = $pdo->prepare("SELECT id, license_key, is_active FROM license_keys WHERE license_key = ?");
                $stmt->execute([$input['license_key']]);
                $licenseKey = $stmt->fetch();
                
                if (!$licenseKey) {
                    handleError('Invalid license key', 401);
                }
                
                if (!$licenseKey['is_active']) {
                    handleError('License key has expired (72 hours exceeded). Please renew or purchase a new key', 401);
                }
                
                // Check user credentials
                $stmt = $pdo->prepare("SELECT id, username, password, license_key_id, password_changed_at FROM users WHERE username = ?");
                $stmt->execute([$input['username']]);
                $user = $stmt->fetch();
                
                if (!$user || $input['password'] !== $user['password']) {
                    handleError('Invalid username or password', 401);
                }
                
                // Verify license key matches user's license key
                if ($user['license_key_id'] != $licenseKey['id']) {
                    handleError('License key does not match this user account', 401);
                }
                
                // Create session
                session_name('UBA_USER_SESSION');
                session_start();
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['last_activity'] = time();
                // Store password_changed_at in session to detect password changes
                $_SESSION['password_changed_at'] = $user['password_changed_at'] ?? null;
                
                // Update last login
                $stmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                $stmt->execute([$user['id']]);
                
                sendResponse(true, [
                    'user_id' => $user['id'],
                    'username' => $user['username']
                ], 'Login successful');
            } catch (PDOException $e) {
                handleError('Login failed: ' . $e->getMessage(), 500);
            }
        } elseif ($action === 'logout') {
            // User logout
            session_name('UBA_USER_SESSION');
            session_start();
            session_destroy();
            sendResponse(true, null, 'Logout successful');
        } elseif ($action === 'check') {
            // Check if user is logged in
            session_name('UBA_USER_SESSION');
            session_start();
            
            if (isset($_SESSION['user_id']) && isset($_SESSION['username'])) {
                // Check session expiry
                if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_LIFETIME)) {
                    session_destroy();
                    sendResponse(false, null, 'Session expired', 401);
                }
                
                // Check if password was changed after login
                $userId = $_SESSION['user_id'];
                $stmt = $pdo->prepare("SELECT password_changed_at FROM users WHERE id = ?");
                $stmt->execute([$userId]);
                $user = $stmt->fetch();
                
                if ($user) {
                    $dbPasswordChangedAt = $user['password_changed_at'];
                    $sessionPasswordChangedAt = $_SESSION['password_changed_at'] ?? null;
                    
                    // If password was changed (timestamps don't match), invalidate session
                    if ($dbPasswordChangedAt !== $sessionPasswordChangedAt) {
                        session_destroy();
                        sendResponse(false, null, 'Password has been changed. Please login again.', 401);
                    }
                }
                
                $_SESSION['last_activity'] = time();
                sendResponse(true, [
                    'user_id' => $_SESSION['user_id'],
                    'username' => $_SESSION['username']
                ]);
            } else {
                sendResponse(false, null, 'Not authenticated', 401);
            }
        } else {
            handleError('Invalid action');
        }
        break;
        
    default:
        handleError('Method not allowed', 405);
}

