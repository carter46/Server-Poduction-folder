<?php
/**
 * Database Configuration (example)
 * Copy this file to config.php on the server and fill in local values.
 * config.php is gitignored and must not be committed.
 */

define('DB_HOST', 'localhost');
define('DB_NAME', 'your_database_name');
define('DB_USER', 'your_database_user');
define('DB_PASS', 'your_database_password');
define('DB_CHARSET', 'utf8mb4');

define('APP_NAME', 'UBA Dashboard');
// Public site origin allowed to call this API from a browser (must match live domain).
define('APP_URL', 'https://yourdomain.com');
// Optional extra origins (comma-separated). Native mobile clients send no Origin and are allowed.
if (!defined('ALLOWED_ORIGINS')) {
    define('ALLOWED_ORIGINS', '');
}

define('FCM_PROJECT_ID', '');
define('FCM_SERVICE_ACCOUNT_JSON', __DIR__ . '/secrets/firebase-service-account.json');

define('MOBILE_APK_PRIVATE_DIR', __DIR__ . '/private_mobile');
define('MOBILE_APK_FILENAME', 'banking-companion.apk');
define('MOBILE_APK_META_FILENAME', 'apk-meta.json');

define('SESSION_LIFETIME', 3600 * 24);
define('SESSION_NAME', 'UBA_ADMIN_SESSION');

/**
 * When false, the Dashboard Mode card is hidden in admin Global Settings and
 * dashboard_mode cannot be changed via the API. The current DB mode stays in effect.
 * Set in config.php (not committed). Default true when omitted.
 *
 * define('DASHBOARD_MODE_ADMIN_EDITABLE', false);
 */
if (!defined('DASHBOARD_MODE_ADMIN_EDITABLE')) {
    define('DASHBOARD_MODE_ADMIN_EDITABLE', true);
}

if (!function_exists('isDashboardModeAdminEditable')) {
    function isDashboardModeAdminEditable()
    {
        if (!defined('DASHBOARD_MODE_ADMIN_EDITABLE')) {
            return true;
        }
        $v = DASHBOARD_MODE_ADMIN_EDITABLE;
        if (is_bool($v)) {
            return $v;
        }
        if (is_int($v) || is_float($v)) {
            return ((int)$v) !== 0;
        }
        $s = strtolower(trim((string)$v));
        return !in_array($s, ['0', 'false', 'off', 'no', ''], true);
    }
}

function corsAllowedOrigins(): array
{
    $list = [];
    $app = defined('APP_URL') ? trim((string)APP_URL) : '';
    if ($app !== '') {
        $list[] = rtrim($app, '/');
    }
    $extra = defined('ALLOWED_ORIGINS') ? (string)ALLOWED_ORIGINS : '';
    foreach (preg_split('/\s*,\s*/', $extra) ?: [] as $part) {
        $part = rtrim(trim($part), '/');
        if ($part !== '') {
            $list[] = $part;
        }
    }
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? '';
    if ($host !== '') {
        $list[] = $scheme . '://' . $host;
    }
    return array_values(array_unique($list));
}

function corsApplyHeaders(): void
{
    if (headers_sent()) {
        return;
    }
    $origin = isset($_SERVER['HTTP_ORIGIN']) ? trim((string)$_SERVER['HTTP_ORIGIN']) : '';
    $allowed = corsAllowedOrigins();

    if ($origin !== '') {
        $originNorm = rtrim($origin, '/');
        if (!in_array($originNorm, $allowed, true)) {
            http_response_code(403);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => false,
                'error' => 'Origin not allowed for this API. Update APP_URL / ALLOWED_ORIGINS (or the mobile API base) to the connected site domain.',
            ], JSON_UNESCAPED_UNICODE);
            exit();
        }
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Vary: Origin');
        header('Access-Control-Allow-Credentials: true');
    }
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
    if (!defined('MOBILE_SKIP_JSON_HEADERS') || !MOBILE_SKIP_JSON_HEADERS) {
        header('Content-Type: application/json; charset=utf-8');
    }
}

corsApplyHeaders();

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

function getDBConnection() {
    try {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        return new PDO($dsn, DB_USER, DB_PASS, $options);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Database connection failed: ' . $e->getMessage()
        ]);
        exit();
    }
}

function sendResponse($success, $data = null, $message = '', $statusCode = 200) {
    header('Cache-Control: no-store, private');
    header('Pragma: no-cache');
    http_response_code($statusCode);
    $response = ['success' => $success];
    if ($message) {
        $response['message'] = $message;
    }
    if ($data !== null) {
        $response['data'] = $data;
    }
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit();
}

function handleError($message, $statusCode = 400) {
    sendResponse(false, null, $message, $statusCode);
}

function getJsonInput() {
    $json = file_get_contents('php://input');
    return json_decode($json, true);
}

function validateAdminSession() {
    session_name(SESSION_NAME);
    session_start();

    if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_username'])) {
        handleError('Unauthorized. Please login.', 401);
    }

    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_LIFETIME)) {
        session_destroy();
        handleError('Session expired. Please login again.', 401);
    }

    $_SESSION['last_activity'] = time();
    return $_SESSION['admin_id'];
}
