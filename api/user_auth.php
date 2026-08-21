<?php
/**
 * User Authentication API
 * Handles user login with username, password, and license key.
 * Login and session check both require the user's license key to be active.
 */

require_once 'config.php';

$method = $_SERVER['REQUEST_METHOD'];
$pdo = getDBConnection();

function userAuthLicenseIsActive($raw): bool
{
    return intval($raw) === 1;
}

function userAuthRequireActiveLicense(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare(
        "SELECT u.id, u.username, u.password_changed_at, u.license_key_id,
                lk.license_key, lk.is_active
         FROM users u
         LEFT JOIN license_keys lk ON lk.id = u.license_key_id
         WHERE u.id = ?
         LIMIT 1"
    );
    $stmt->execute([$userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        handleError('User account not found', 401);
    }
    if (empty($row['license_key_id']) || empty($row['license_key']) || !userAuthLicenseIsActive($row['is_active'] ?? 0)) {
        handleError('License key is inactive or invalid. Please login again with an active license key.', 401);
    }
    return $row;
}

switch ($method) {
    case 'POST':
        $input = getJsonInput() ?: [];
        $action = $input['action'] ?? 'login';

        if ($action === 'login') {
            $username = trim((string)($input['username'] ?? ''));
            $password = (string)($input['password'] ?? '');
            $licenseKeyInput = trim((string)($input['license_key'] ?? ''));

            if ($username === '' || $password === '' || $licenseKeyInput === '') {
                handleError('Username, password, and license key are required');
            }

            try {
                // Must have an active platform license configured.
                $activeStmt = $pdo->query(
                    "SELECT id, license_key, is_active FROM license_keys WHERE is_active = 1 ORDER BY created_at DESC LIMIT 1"
                );
                $activeLicense = $activeStmt ? $activeStmt->fetch(PDO::FETCH_ASSOC) : false;
                if (!$activeLicense || !userAuthLicenseIsActive($activeLicense['is_active'] ?? 0)) {
                    handleError('No active license key is configured. Please contact support.', 401);
                }

                // Submitted key must exist and be the current active key.
                $stmt = $pdo->prepare("SELECT id, license_key, is_active FROM license_keys WHERE license_key = ? LIMIT 1");
                $stmt->execute([$licenseKeyInput]);
                $licenseKey = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$licenseKey) {
                    handleError('Invalid license key', 401);
                }

                if (!userAuthLicenseIsActive($licenseKey['is_active'] ?? 0)) {
                    handleError('License key is inactive. Please use the current active license key.', 401);
                }

                if (intval($licenseKey['id']) !== intval($activeLicense['id'])) {
                    handleError('License key is inactive. Please use the current active license key.', 401);
                }

                $stmt = $pdo->prepare("SELECT id, username, password, license_key_id, password_changed_at FROM users WHERE username = ? LIMIT 1");
                $stmt->execute([$username]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$user || !hash_equals((string)$user['password'], $password)) {
                    handleError('Invalid username or password', 401);
                }

                if (intval($user['license_key_id'] ?? 0) !== intval($licenseKey['id'])) {
                    handleError('License key does not match this user account', 401);
                }

                session_name('UBA_USER_SESSION');
                session_start();
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['license_key_id'] = intval($licenseKey['id']);
                $_SESSION['last_activity'] = time();
                $_SESSION['password_changed_at'] = $user['password_changed_at'] ?? null;

                $stmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                $stmt->execute([$user['id']]);

                sendResponse(true, [
                    'user_id' => $user['id'],
                    'username' => $user['username'],
                ], 'Login successful');
            } catch (PDOException $e) {
                handleError('Login failed: ' . $e->getMessage(), 500);
            }
        } elseif ($action === 'logout') {
            session_name('UBA_USER_SESSION');
            session_start();
            session_destroy();
            sendResponse(true, null, 'Logout successful');
        } elseif ($action === 'check') {
            session_name('UBA_USER_SESSION');
            session_start();

            if (!isset($_SESSION['user_id']) || !isset($_SESSION['username'])) {
                sendResponse(false, null, 'Not authenticated', 401);
            }

            if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_LIFETIME)) {
                session_destroy();
                sendResponse(false, null, 'Session expired', 401);
            }

            try {
                $userId = intval($_SESSION['user_id']);
                $row = userAuthRequireActiveLicense($pdo, $userId);

                $dbPasswordChangedAt = $row['password_changed_at'] ?? null;
                $sessionPasswordChangedAt = $_SESSION['password_changed_at'] ?? null;
                if ($dbPasswordChangedAt !== $sessionPasswordChangedAt) {
                    session_destroy();
                    sendResponse(false, null, 'Password has been changed. Please login again.', 401);
                }

                // Ensure session still points at the same active license.
                $sessionLicenseId = intval($_SESSION['license_key_id'] ?? 0);
                if ($sessionLicenseId > 0 && $sessionLicenseId !== intval($row['license_key_id'])) {
                    session_destroy();
                    sendResponse(false, null, 'License key is inactive or invalid. Please login again with an active license key.', 401);
                }

                $_SESSION['license_key_id'] = intval($row['license_key_id']);
                $_SESSION['last_activity'] = time();
                sendResponse(true, [
                    'user_id' => intval($row['id']),
                    'username' => $row['username'],
                ]);
            } catch (PDOException $e) {
                handleError('Session check failed: ' . $e->getMessage(), 500);
            }
        } else {
            handleError('Invalid action');
        }
        break;

    default:
        handleError('Method not allowed', 405);
}
