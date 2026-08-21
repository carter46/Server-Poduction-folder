<?php
/**
 * Global dashboard mode + global transfer config on license_settings + per-bank verify session.
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

function globalTransferEnsureColumns(PDO $pdo): void
{
    dashboardEnsureModeColumn($pdo);
    $columns = [
        'otp_enabled' => "ALTER TABLE license_settings ADD COLUMN otp_enabled TINYINT(1) NOT NULL DEFAULT 0",
        'hard_token_enabled' => "ALTER TABLE license_settings ADD COLUMN hard_token_enabled TINYINT(1) NOT NULL DEFAULT 0",
        'hard_token' => "ALTER TABLE license_settings ADD COLUMN hard_token VARCHAR(64) DEFAULT NULL",
        'default_transfer_status' => "ALTER TABLE license_settings ADD COLUMN default_transfer_status ENUM('SUCCESSFUL','PENDING','FAILED') NOT NULL DEFAULT 'SUCCESSFUL'",
        'transfer_restriction' => "ALTER TABLE license_settings ADD COLUMN transfer_restriction TINYINT(1) NOT NULL DEFAULT 0",
        'risky_transaction' => "ALTER TABLE license_settings ADD COLUMN risky_transaction TINYINT(1) NOT NULL DEFAULT 0",
        'nin_verification' => "ALTER TABLE license_settings ADD COLUMN nin_verification TINYINT(1) NOT NULL DEFAULT 0",
        'log_status' => "ALTER TABLE license_settings ADD COLUMN log_status ENUM('full_logs','weak_logs','pending_request','post_no_debit','fixed_account') NOT NULL DEFAULT 'full_logs'",
    ];
    foreach ($columns as $name => $sql) {
        try {
            $check = $pdo->query("SHOW COLUMNS FROM license_settings LIKE " . $pdo->quote($name));
            if ($check && $check->rowCount() === 0) {
                $pdo->exec($sql);
            }
        } catch (PDOException $e) {
        }
    }
}

/**
 * Fresh read of global transfer fields from license_settings id=1.
 * @return array{
 *   otp_enabled:bool,
 *   hard_token_enabled:bool,
 *   hard_token:string,
 *   default_transfer_status:string,
 *   transfer_restriction:bool,
 *   risky_transaction:bool,
 *   nin_verification:bool,
 *   log_status:string
 * }
 */
function globalTransferSettingsGet(PDO $pdo): array
{
    globalTransferEnsureColumns($pdo);
    $defaults = [
        'otp_enabled' => false,
        'hard_token_enabled' => false,
        'hard_token' => '',
        'default_transfer_status' => 'SUCCESSFUL',
        'transfer_restriction' => false,
        'risky_transaction' => false,
        'nin_verification' => false,
        'log_status' => 'full_logs',
    ];
    try {
        $stmt = $pdo->query(
            "SELECT otp_enabled, hard_token_enabled, hard_token, default_transfer_status,
                    transfer_restriction, risky_transaction, nin_verification, log_status
             FROM license_settings WHERE id = 1 LIMIT 1"
        );
        $row = $stmt ? $stmt->fetch() : false;
        if (!$row) {
            return $defaults;
        }
        $outcome = strtoupper(trim((string)($row['default_transfer_status'] ?? 'SUCCESSFUL')));
        if (!in_array($outcome, ['SUCCESSFUL', 'PENDING', 'FAILED'], true)) {
            $outcome = 'SUCCESSFUL';
        }
        $log = strtolower(trim((string)($row['log_status'] ?? 'full_logs')));
        if (!in_array($log, ['full_logs', 'weak_logs', 'pending_request', 'post_no_debit', 'fixed_account'], true)) {
            $log = 'full_logs';
        }
        return [
            'otp_enabled' => intval($row['otp_enabled'] ?? 0) === 1,
            'hard_token_enabled' => intval($row['hard_token_enabled'] ?? 0) === 1,
            'hard_token' => trim((string)($row['hard_token'] ?? '')),
            'default_transfer_status' => $outcome,
            'transfer_restriction' => intval($row['transfer_restriction'] ?? 0) === 1,
            'risky_transaction' => intval($row['risky_transaction'] ?? 0) === 1,
            'nin_verification' => intval($row['nin_verification'] ?? 0) === 1,
            'log_status' => $log,
        ];
    } catch (PDOException $e) {
        return $defaults;
    }
}

/** Public-safe subset (no hard_token, no default_transfer_status). */
function globalTransferPublicFlags(PDO $pdo): array
{
    $g = globalTransferSettingsGet($pdo);
    return [
        'otp_enabled' => $g['otp_enabled'],
        'hard_token_enabled' => $g['hard_token_enabled'],
        'transfer_restriction' => $g['transfer_restriction'],
        'risky_transaction' => $g['risky_transaction'],
        'nin_verification' => $g['nin_verification'],
        'log_status' => $g['log_status'],
    ];
}

/**
 * Reject create if a global restriction is ON. Priority: Restriction → Risky → NIN.
 */
function globalTransferEnforceRestrictions(array $g): void
{
    if (!empty($g['transfer_restriction'])) {
        handleError('This transfer cannot be completed due to a transfer restriction.', 403, 'GLOBAL_TRANSFER_RESTRICTION');
    }
    if (!empty($g['risky_transaction'])) {
        handleError('This transfer cannot be completed due to a risky transaction block.', 403, 'GLOBAL_RISKY_TRANSACTION');
    }
    if (!empty($g['nin_verification'])) {
        handleError('This transfer cannot be completed due to NIN verification requirements.', 403, 'GLOBAL_NIN_VERIFICATION');
    }
}

/**
 * Reject create when global log status is a non-creating status.
 */
function globalTransferEnforceLogStatus(array $g): void
{
    $blocking = ['weak_logs', 'pending_request', 'post_no_debit', 'fixed_account'];
    $log = $g['log_status'] ?? 'full_logs';
    if (in_array($log, $blocking, true)) {
        handleError(
            'This transfer cannot be completed for the current account status',
            403,
            'GLOBAL_LOG_STATUS',
            ['log_status' => $log]
        );
    }
}

function globalTransferGenerateHardToken(): string
{
    return str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
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

function dashboardNormalizeAccount(string $accountNumber): string
{
    $digits = preg_replace('/\D/', '', $accountNumber);
    return is_string($digits) ? $digits : '';
}

function dashboardMarkBankVerified(string $bankCode, string $accountNumber = ''): void
{
    if (!dashboardUserSessionStart()) {
        handleError('Unauthorized. Please login.', 401);
    }
    if (!isset($_SESSION['df_verified']) || !is_array($_SESSION['df_verified'])) {
        $_SESSION['df_verified'] = [];
    }
    $digits = dashboardNormalizeAccount($accountNumber);
    $existing = $_SESSION['df_verified'][$bankCode] ?? null;
    $prevAcct = '';
    if (is_array($existing)) {
        $prevAcct = dashboardNormalizeAccount((string)($existing['account_number'] ?? ''));
    }
    $store = strlen($digits) === 10 ? $digits : (strlen($prevAcct) === 10 ? $prevAcct : '');
    $_SESSION['df_verified'][$bankCode] = [
        'bank_code' => $bankCode,
        'account_number' => $store,
        'at' => time(),
    ];
}

function dashboardVerifiedAt(string $bankCode)
{
    if (!isset($_SESSION['df_verified'][$bankCode])) {
        return 0;
    }
    $row = $_SESSION['df_verified'][$bankCode];
    if (is_array($row)) {
        return intval($row['at'] ?? 0);
    }
    return intval($row);
}

function dashboardBankVerified(string $bankCode): bool
{
    if (!dashboardUserSessionStart()) {
        return false;
    }
    if (!dashboardBankAllowed($bankCode)) {
        return false;
    }
    $at = dashboardVerifiedAt($bankCode);
    return $at > 0 && (time() - $at) < 28800;
}

function dashboardVerifiedAccountNumber(string $bankCode): ?string
{
    if (!dashboardBankVerified($bankCode)) {
        return null;
    }
    $row = $_SESSION['df_verified'][$bankCode] ?? null;
    if (!is_array($row)) {
        return null;
    }
    $digits = dashboardNormalizeAccount((string)($row['account_number'] ?? ''));
    return strlen($digits) === 10 ? $digits : null;
}

/** Clear Mode OFF prefill after a successful create (one-shot). Never related to OTP. */
function dashboardConsumeVerifiedPrefill(string $bankCode): void
{
    if (!dashboardUserSessionStart()) {
        return;
    }
    if (isset($_SESSION['df_verified']) && is_array($_SESSION['df_verified'])) {
        unset($_SESSION['df_verified'][$bankCode]);
    }
    // Legacy cleanup if old sessions still hold df_otp
    if (isset($_SESSION['df_otp']) && is_array($_SESSION['df_otp'])) {
        unset($_SESSION['df_otp'][$bankCode]);
    }
}

/** @deprecated alias — clears prefill only; does not authorize OTP */
function dashboardConsumePreTransferOtp(string $bankCode): void
{
    dashboardConsumeVerifiedPrefill($bankCode);
}

function dashboardBankOtpEnabled(PDO $pdo, string $bankCode): bool
{
    // Global OTP only (bankCode kept for call-site compatibility)
    unset($bankCode);
    $g = globalTransferSettingsGet($pdo);
    return !empty($g['otp_enabled']);
}

if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    require_once 'config.php';
    $pdo = getDBConnection();
    dashboardEnsureModeColumn($pdo);
    $method = $_SERVER['REQUEST_METHOD'];

    if ($method === 'GET') {
        $bankCode = trim((string)($_GET['bank_code'] ?? ''));
        $known = $bankCode !== '' && dashboardBankAllowed($bankCode);
        $mode = dashboardModeGet($pdo);
        $verified = $known && dashboardBankVerified($bankCode);
        $accountNumber = null;
        if ($mode === 'off' && $verified) {
            $accountNumber = dashboardVerifiedAccountNumber($bankCode);
        }
        sendResponse(true, [
            'dashboard_mode' => $mode,
            'bank_verified' => $verified,
            'account_number' => $accountNumber,
        ]);
    }

    if ($method === 'POST') {
        dashboardRequireUser();
        $input = getJsonInput() ?: [];
        $action = strtolower(trim((string)($input['action'] ?? '')));
        $bankCode = trim((string)($input['bank_code'] ?? ''));
        dashboardRequireKnownBank($bankCode);
        if ($action === 'mark_verified') {
            $digits = dashboardNormalizeAccount((string)($input['account_number'] ?? ''));
            if (strlen($digits) !== 10) {
                handleError('A valid 10-digit account number is required');
            }
            dashboardMarkBankVerified($bankCode, $digits);
            $mode = dashboardModeGet($pdo);
            sendResponse(true, [
                'dashboard_mode' => $mode,
                'bank_verified' => dashboardBankVerified($bankCode),
                'account_number' => ($mode === 'off' && dashboardBankVerified($bankCode)) ? dashboardVerifiedAccountNumber($bankCode) : null,
            ], 'Bank verification recorded');
        }
        handleError('Unknown action');
    }

    handleError('Method not allowed', 405);
}
