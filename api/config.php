<?php
/**
 * Database Configuration
 * Update these values with your MySQL database credentials
 */

// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'u502532383_uba');
define('DB_USER', 'u502532383_uba');
define('DB_PASS', 'Secretpass0721//');
define('DB_CHARSET', 'utf8mb4');

// Application settings
define('APP_NAME', 'UBA Dashboard');
define('APP_URL', 'http://localhost');

// Firebase Cloud Messaging (HTTP v1) — path to service-account JSON, never paste JSON here
define('FCM_PROJECT_ID', ''); // e.g. 'my-firebase-project'
define('FCM_SERVICE_ACCOUNT_JSON', __DIR__ . '/secrets/firebase-service-account.json');

// Private APK store (served only via authenticated mobile_apk_download.php)
define('MOBILE_APK_PRIVATE_DIR', __DIR__ . '/private_mobile');
define('MOBILE_APK_FILENAME', 'banking-companion.apk');
define('MOBILE_APK_META_FILENAME', 'apk-meta.json');

// Admin session settings
define('SESSION_LIFETIME', 3600 * 24); // 24 hours in seconds
define('SESSION_NAME', 'UBA_ADMIN_SESSION');

// CORS settings (for React frontend)
// Only set headers if not already sent. Binary endpoints may set MOBILE_SKIP_JSON_HEADERS.
if (!headers_sent()) {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
    header('Access-Control-Allow-Credentials: true');
    if (!defined('MOBILE_SKIP_JSON_HEADERS') || !MOBILE_SKIP_JSON_HEADERS) {
        header('Content-Type: application/json; charset=utf-8');
    }
}

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Database connection function
function getDBConnection() {
    try {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        return $pdo;
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Database connection failed: ' . $e->getMessage()
        ]);
        exit();
    }
}

// Response helper function
function sendResponse($success, $data = null, $message = '', $statusCode = 200) {
    header('Cache-Control: no-store, private');
    header('Pragma: no-cache');
    http_response_code($statusCode);
    $response = [
        'success' => $success,
    ];
    
    if ($message) {
        $response['message'] = $message;
    }
    
    if ($data !== null) {
        $response['data'] = $data;
    }
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit();
}

// Error handler
function handleError($message, $statusCode = 400) {
    sendResponse(false, null, $message, $statusCode);
}

// Get JSON input
function getJsonInput() {
    $json = file_get_contents('php://input');
    return json_decode($json, true);
}

// Validate admin session
function validateAdminSession() {
    session_name(SESSION_NAME);
    session_start();
    
    if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_username'])) {
        handleError('Unauthorized. Please login.', 401);
    }
    
    // Check session expiry
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_LIFETIME)) {
        session_destroy();
        handleError('Session expired. Please login again.', 401);
    }
    
    $_SESSION['last_activity'] = time();
    return $_SESSION['admin_id'];
}

