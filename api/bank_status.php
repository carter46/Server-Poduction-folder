<?php
/**
 * Bank Status API — DEPRECATED
 * Runtime log status is license_settings.log_status (Global Settings).
 * This endpoint is read-only for legacy inspection; writes are rejected.
 */

require_once 'config.php';

$method = $_SERVER['REQUEST_METHOD'];
$pdo = getDBConnection();

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
    '090405' => 'Moniepoint',
    '999992' => 'OPay',
    '100033' => 'PalmPay',
];

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS bank_status (
        id INT AUTO_INCREMENT PRIMARY KEY,
        bank_code VARCHAR(20) UNIQUE NOT NULL,
        bank_name VARCHAR(100) NOT NULL,
        status ENUM('full_logs', 'weak_logs', 'pending_request', 'post_no_debit', 'fixed_account') DEFAULT 'full_logs',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
} catch (PDOException $e) {
    // Table might already exist
}

switch ($method) {
    case 'GET':
        try {
            if (isset($_GET['bank_code'])) {
                $bankCode = $_GET['bank_code'];
                $stmt = $pdo->prepare("SELECT * FROM bank_status WHERE bank_code = ?");
                $stmt->execute([$bankCode]);
                $status = $stmt->fetch();
                if (!$status) {
                    sendResponse(true, null, 'No legacy bank_status row; use Global Settings log_status');
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
    case 'POST':
    case 'PATCH':
    case 'DELETE':
        validateAdminSession();
        handleError('bank_status writes are retired. Set Global Log Status in Global Settings (license_settings.log_status).', 410);
        break;

    default:
        handleError('Method not allowed', 405);
        break;
}
