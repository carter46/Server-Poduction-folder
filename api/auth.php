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
                require_once __DIR__ . '/database_auto_migrate.php';
                // Bootstrap column needed for username-OR-email lookup (before auth).
                DatabaseAutoMigrate::ensureColumn(
                    $pdo,
                    'admin_users',
                    'email',
                    '`email` VARCHAR(255) DEFAULT NULL'
                );

                $loginId = trim((string)$input['username']);
                // Accept username OR email (existing accounts keep working).
                $stmt = $pdo->prepare(
                    "SELECT id, username, password FROM admin_users
                     WHERE username = ? OR LOWER(TRIM(COALESCE(email, ''))) = LOWER(?)
                     LIMIT 1"
                );
                $stmt->execute([$loginId, $loginId]);
                $admin = $stmt->fetch();
                
                if (!$admin || $input['password'] !== $admin['password']) {
                    handleError('Invalid username or password', 401);
                }
                
                // Create session
                session_name(SESSION_NAME);
                if (session_status() !== PHP_SESSION_ACTIVE) {
                    session_start();
                }
                $_SESSION['admin_id'] = $admin['id'];
                $_SESSION['admin_username'] = $admin['username'];
                $_SESSION['last_activity'] = time();

                // Apply pending versioned migrations after successful admin auth.
                $migration = runAdminDatabaseAutoMigrations($pdo, (int)$admin['id']);
                
                // Update last login
                $stmt = $pdo->prepare("UPDATE admin_users SET last_login = NOW() WHERE id = ?");
                $stmt->execute([$admin['id']]);
                
                sendResponse(true, [
                    'admin_id' => $admin['id'],
                    'username' => $admin['username'],
                    'auto_migration' => [
                        'applied' => $migration['applied'] ?? [],
                        'failed' => $migration['failed'] ?? [],
                        'skipped' => $migration['skipped'] ?? 0,
                        'errors' => $migration['errors'] ?? [],
                    ],
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

                require_once __DIR__ . '/database_auto_migrate.php';
                $migration = runAdminDatabaseAutoMigrations($pdo, (int)$_SESSION['admin_id']);

                sendResponse(true, [
                    'admin_id' => $_SESSION['admin_id'],
                    'username' => $_SESSION['admin_username'],
                    'auto_migration' => [
                        'applied' => $migration['applied'] ?? [],
                        'failed' => $migration['failed'] ?? [],
                        'skipped' => $migration['skipped'] ?? 0,
                        'errors' => $migration['errors'] ?? [],
                    ],
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

