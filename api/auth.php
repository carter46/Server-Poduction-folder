<?php
/**
 * Admin Authentication API
 * Handles admin login and session management
 */

require_once 'config.php';

$method = $_SERVER['REQUEST_METHOD'];
$pdo = getDBConnection();

switch ($method) {
    case 'POST':
        $input = getJsonInput();
        $action = $input['action'] ?? 'login';
        
        if ($action === 'login') {
            // Admin login
            if (!isset($input['username']) || !isset($input['password'])) {
                handleError('Username and password are required');
            }
            
            try {
                $stmt = $pdo->prepare("SELECT id, username, password FROM admin_users WHERE username = ?");
                $stmt->execute([$input['username']]);
                $admin = $stmt->fetch();
                
                if (!$admin || $input['password'] !== $admin['password']) {
                    handleError('Invalid username or password', 401);
                }
                
                // Create session
                session_name(SESSION_NAME);
                session_start();
                $_SESSION['admin_id'] = $admin['id'];
                $_SESSION['admin_username'] = $admin['username'];
                $_SESSION['last_activity'] = time();
                
                // Update last login
                $stmt = $pdo->prepare("UPDATE admin_users SET last_login = NOW() WHERE id = ?");
                $stmt->execute([$admin['id']]);
                
                sendResponse(true, [
                    'admin_id' => $admin['id'],
                    'username' => $admin['username']
                ], 'Login successful');
            } catch (PDOException $e) {
                handleError('Login failed: ' . $e->getMessage(), 500);
            }
        } elseif ($action === 'logout') {
            // Admin logout
            session_name(SESSION_NAME);
            session_start();
            session_destroy();
            sendResponse(true, null, 'Logout successful');
        } elseif ($action === 'check') {
            // Check if admin is logged in
            session_name(SESSION_NAME);
            session_start();
            
            if (isset($_SESSION['admin_id']) && isset($_SESSION['admin_username'])) {
                // Check session expiry
                if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_LIFETIME)) {
                    session_destroy();
                    sendResponse(false, null, 'Session expired', 401);
                }
                
                $_SESSION['last_activity'] = time();
                sendResponse(true, [
                    'admin_id' => $_SESSION['admin_id'],
                    'username' => $_SESSION['admin_username']
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

