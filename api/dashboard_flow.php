<?php
/**
 * Global dashboard mode + per-bank pre-transfer verification session.
 */

function dashboardEnsureModeColumn(PDO $pdo): void
{
    try {
        $check = $pdo->query("SHOW COLUMNS FROM license_settings LIKE 'dashboard_mode'");
        if ($check && $check->rowCount() === 0) {
            $pdo->exec("ALTER TABLE license_settings ADD COLUMN dashboard_mode ENUM('on','off') NOT NULL DEFAULT 'on' AFTER renewal_gate");
        }
    } catch (PDOException $e) {
    }
}

function dashboardModeGet(PDO $pdo): string
{
    dashboardEnsureModeColumn($pdo);
    try {
        $stmt = $pdo->query("SELECT dashboard_mode FROM license_settings WHERE id = 1 LIMIT 1");
        $row = $stmt ? $stmt->fetch() : false;
        $mode = strtolower(trim((string)($row['dashboard_mode'] ?? 'on')));
        return $mode === 'off' ? 'off' : 'on';
    } catch (PDOException $e) {
        return 'on';
    }
}

function dashboardLoadBankKit(): void
{
    if (!function_exists('bankKitRegistry')) {
        require_once __DIR__ . '/bank_kit.php';
    }
}

function dashboardBankAllowed(string $bankCode): bool
{
    $bankCode = trim($bankCode);
    if ($bankCode === '') {
        return false;
    }
    dashboardLoadBankKit();
    return isset(bankKitRegistry()[$bankCode]);
}

function dashboardRequireKnownBank(string $bankCode): array
{
    if (!dashboardBankAllowed($bankCode)) {
        handleError('Unknown bank', 400);
    }
    dashboardLoadBankKit();
    return bankKitResolve($bankCode);
}

function polarisVerifyIntentHash(string $bankCode, string $accountNumber): string
{
    $norm = 'verify|' . trim($bankCode) . '|' . preg_replace('/\D/', '', $accountNumber);
    return hash('sha256', $norm);
}

function dashboardUserSessionStart(): bool
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        if (session_name() !== 'UBA_USER_SESSION') {
            session_write_close();
        } else {
            return isset($_SESSION['user_id']);
        }
    }
    session_name('UBA_USER_SESSION');
    session_start();
    return isset($_SESSION['user_id']);
}

function dashboardRequireUser(): void
{
    if (!dashboardUserSessionStart()) {
        handleError('Unauthorized. Please login.', 401);
    }
}

function dashboardMarkBankVerified(string $bankCode): void
{
    dashboardUserSessionStart();
    if (!isset($_SESSION['df_verified']) || !is_array($_SESSION['df_verified'])) {
        $_SESSION['df_verified'] = [];
    }
    $_SESSION['df_verified'][$bankCode] = time();
}

function dashboardBankVerified(string $bankCode): bool
{
    if (!dashboardUserSessionStart()) {
        return false;
    }
    if (!dashboardBankAllowed($bankCode)) {
        return false;
    }
    $at = intval($_SESSION['df_verified'][$bankCode] ?? 0);
    return $at > 0 && (time() - $at) < 28800;
}

function dashboardMarkPreTransferOtp(string $bankCode, string $challengeId = ''): void
{
    dashboardUserSessionStart();
    if (!isset($_SESSION['df_otp']) || !is_array($_SESSION['df_otp'])) {
        $_SESSION['df_otp'] = [];
    }
    $_SESSION['df_otp'][$bankCode] = [
        'at' => time(),
        'challenge_id' => $challengeId,
    ];
    dashboardMarkBankVerified($bankCode);
}

function dashboardPreTransferOtpOk(string $bankCode): bool
{
    if (!dashboardUserSessionStart()) {
        return false;
    }
    if (!dashboardBankAllowed($bankCode)) {
        return false;
    }
    $row = $_SESSION['df_otp'][$bankCode] ?? null;
    if (!is_array($row)) {
        return false;
    }
    $at = intval($row['at'] ?? 0);
    $challengeId = trim((string)($row['challenge_id'] ?? ''));
    return $at > 0 && $challengeId !== '' && (time() - $at) < 28800;
}

function dashboardPreTransferOtpSatisfies(PDO $pdo, string $bankCode): bool
{
    return dashboardModeGet($pdo) === 'off' && dashboardPreTransferOtpOk($bankCode);
}

function dashboardConsumePreTransferOtp(string $bankCode): void
{
    if (!dashboardUserSessionStart()) {
        return;
    }
    if (isset($_SESSION['df_otp']) && is_array($_SESSION['df_otp'])) {
        unset($_SESSION['df_otp'][$bankCode]);
    }
}

function dashboardBankOtpEnabled(PDO $pdo, string $bankCode): bool
{
    $bank = dashboardRequireKnownBank($bankCode);
    dashboardLoadBankKit();
    bankKitEnsure($pdo);
    $account = bankKitAccountRow($pdo, $bank);
    return intval($account['otp_enabled'] ?? 0) === 1;
}

if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    require_once 'config.php';
    $pdo = getDBConnection();
    dashboardEnsureModeColumn($pdo);
    $method = $_SERVER['REQUEST_METHOD'];

    if ($method === 'GET') {
        $bankCode = trim((string)($_GET['bank_code'] ?? ''));
        $known = $bankCode !== '' && dashboardBankAllowed($bankCode);
        sendResponse(true, [
            'dashboard_mode' => dashboardModeGet($pdo),
            'bank_verified' => $known && dashboardBankVerified($bankCode),
            'pre_transfer_otp' => $known && dashboardPreTransferOtpOk($bankCode),
        ]);
    }

    if ($method === 'POST') {
        dashboardRequireUser();
        $input = getJsonInput() ?: [];
        $action = strtolower(trim((string)($input['action'] ?? '')));
        $bankCode = trim((string)($input['bank_code'] ?? ''));
        dashboardRequireKnownBank($bankCode);
        if ($action === 'mark_verified') {
            dashboardMarkBankVerified($bankCode);
            if (!empty($input['skip_otp'])) {
                if (dashboardModeGet($pdo) !== 'off') {
                    handleError('OTP skip is not allowed while Dashboard Mode is on');
                }
                if (dashboardBankOtpEnabled($pdo, $bankCode)) {
                    handleError('OTP skip is not allowed while OTP is enabled for this bank');
                }
                dashboardMarkPreTransferOtp($bankCode, 'skipped');
            }
            sendResponse(true, [
                'dashboard_mode' => dashboardModeGet($pdo),
                'bank_verified' => true,
                'pre_transfer_otp' => dashboardPreTransferOtpOk($bankCode),
            ], 'Bank verification recorded');
        }
        handleError('Unknown action');
    }

    handleError('Method not allowed', 405);
}
