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
        'compliance_kyc' => "ALTER TABLE license_settings ADD COLUMN compliance_kyc TINYINT(1) NOT NULL DEFAULT 0",
        'suspicious_transaction_pattern' => "ALTER TABLE license_settings ADD COLUMN suspicious_transaction_pattern TINYINT(1) NOT NULL DEFAULT 0",
        'technical_network_problems' => "ALTER TABLE license_settings ADD COLUMN technical_network_problems TINYINT(1) NOT NULL DEFAULT 0",
        'bank_security_rules' => "ALTER TABLE license_settings ADD COLUMN bank_security_rules TINYINT(1) NOT NULL DEFAULT 0",
        'do_not_honor' => "ALTER TABLE license_settings ADD COLUMN do_not_honor TINYINT(1) NOT NULL DEFAULT 0",
        'incorrect_account_details' => "ALTER TABLE license_settings ADD COLUMN incorrect_account_details TINYINT(1) NOT NULL DEFAULT 0",
        'transaction_limit_exceeded' => "ALTER TABLE license_settings ADD COLUMN transaction_limit_exceeded TINYINT(1) NOT NULL DEFAULT 0",
        'suspected_fraud' => "ALTER TABLE license_settings ADD COLUMN suspected_fraud TINYINT(1) NOT NULL DEFAULT 0",
        'nin_verification' => "ALTER TABLE license_settings ADD COLUMN nin_verification TINYINT(1) NOT NULL DEFAULT 0",
        'log_status' => "ALTER TABLE license_settings ADD COLUMN log_status ENUM('full_logs','weak_logs','pending_request','post_no_debit','fixed_account') NOT NULL DEFAULT 'full_logs'",
        'crypto_mode' => "ALTER TABLE license_settings ADD COLUMN crypto_mode ENUM('on','off') NOT NULL DEFAULT 'on'",
        'phone_otp_enabled' => "ALTER TABLE license_settings ADD COLUMN phone_otp_enabled TINYINT(1) NOT NULL DEFAULT 0",
        'phone_otp_number' => "ALTER TABLE license_settings ADD COLUMN phone_otp_number VARCHAR(32) NOT NULL DEFAULT ''",
        // JSON map bank_code => bool — Mode OFF dashboard (account + history) per bank. Missing key = enabled.
        'mode_off_bank_dashboards' => "ALTER TABLE license_settings ADD COLUMN mode_off_bank_dashboards TEXT NULL",
        'site_name' => "ALTER TABLE license_settings ADD COLUMN site_name VARCHAR(80) NOT NULL DEFAULT 'UBAS'",
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

/** Normalize site display name. Empty → UBAS. Max 80 chars. */
function siteNameNormalize($raw): string
{
    $name = trim(preg_replace('/\s+/u', ' ', (string)$raw) ?? '');
    if ($name === '') {
        return 'UBAS';
    }
    if (function_exists('mb_substr')) {
        return mb_substr($name, 0, 80);
    }
    return substr($name, 0, 80);
}

function siteNameGet(PDO $pdo): string
{
    globalTransferEnsureColumns($pdo);
    try {
        $stmt = $pdo->query("SELECT site_name FROM license_settings WHERE id = 1 LIMIT 1");
        $row = $stmt ? $stmt->fetch() : false;
        return siteNameNormalize($row['site_name'] ?? 'UBAS');
    } catch (PDOException $e) {
        return 'UBAS';
    }
}

function siteNameSave(PDO $pdo, $raw): string
{
    $name = siteNameNormalize($raw);
    globalTransferEnsureColumns($pdo);
    $stmt = $pdo->prepare('UPDATE license_settings SET site_name = ?, updated_at = NOW() WHERE id = 1');
    $stmt->execute([$name]);
    return $name;
}

/** Allowlisted Mode OFF bank codes (must stay in sync with src/banking/modeOffBanks.ts). */
function modeOffBankCodes(): array
{
    // Must stay in sync with src/banking/modeOffBanks.ts
    return ['044', '070', '033', '076', '221', '057', '011', '058', '035', '214', '032', '050'];
}

function modeOffCoerceBool($value): bool
{
    if (is_bool($value)) {
        return $value;
    }
    if (is_int($value) || is_float($value)) {
        return ((int)$value) !== 0;
    }
    if (is_string($value)) {
        $s = strtolower(trim($value));
        if (in_array($s, ['0', 'false', 'off', 'no', ''], true)) {
            return false;
        }
        if (in_array($s, ['1', 'true', 'on', 'yes'], true)) {
            return true;
        }
    }
    return !empty($value);
}

/**
 * @return array<string,bool> bank_code => dashboard enabled
 */
function modeOffBankDashboardsGet(PDO $pdo): array
{
    globalTransferEnsureColumns($pdo);
    $defaults = [];
    foreach (modeOffBankCodes() as $code) {
        $defaults[$code] = true;
    }
    try {
        $stmt = $pdo->query('SELECT mode_off_bank_dashboards FROM license_settings WHERE id = 1 LIMIT 1');
        $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
        if (!$row) {
            return $defaults;
        }
        $raw = trim((string)($row['mode_off_bank_dashboards'] ?? ''));
        if ($raw === '') {
            return $defaults;
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return $defaults;
        }
        foreach (modeOffBankCodes() as $code) {
            if (array_key_exists($code, $decoded)) {
                $defaults[$code] = modeOffCoerceBool($decoded[$code]);
            }
        }
    } catch (Throwable $e) {
        // keep defaults
    }
    return $defaults;
}

function modeOffBankDashboardEnabled(PDO $pdo, string $bankCode): bool
{
    $map = modeOffBankDashboardsGet($pdo);
    if (!array_key_exists($bankCode, $map)) {
        return true;
    }
    return modeOffCoerceBool($map[$bankCode]);
}

/**
 * @param array<string,mixed> $inputMap
 * @return array<string,bool>
 */
function modeOffBankDashboardsNormalize(array $inputMap): array
{
    $out = [];
    foreach (modeOffBankCodes() as $code) {
        if (array_key_exists($code, $inputMap)) {
            $out[$code] = modeOffCoerceBool($inputMap[$code]);
        } else {
            $out[$code] = true;
        }
    }
    return $out;
}

/**
 * Merge patch onto current map so a single-bank admin save cannot reset other banks to true.
 * @param array<string,mixed> $inputMap
 * @return array<string,bool>
 */
function modeOffBankDashboardsSave(PDO $pdo, array $inputMap): array
{
    globalTransferEnsureColumns($pdo);
    $current = modeOffBankDashboardsGet($pdo);
    foreach ($inputMap as $code => $value) {
        $code = trim((string)$code);
        if ($code === '' || !in_array($code, modeOffBankCodes(), true)) {
            continue;
        }
        $current[$code] = modeOffCoerceBool($value);
    }
    $normalized = modeOffBankDashboardsNormalize($current);
    $json = json_encode($normalized, JSON_UNESCAPED_UNICODE);
    $stmt = $pdo->prepare('UPDATE license_settings SET mode_off_bank_dashboards = ?, updated_at = NOW() WHERE id = 1');
    $stmt->execute([$json]);
    return $normalized;
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
 *   log_status:string,
 *   crypto_mode:string,
 *   phone_otp_enabled:bool,
 *   phone_otp_number:string
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
        'compliance_kyc' => false,
        'suspicious_transaction_pattern' => false,
        'technical_network_problems' => false,
        'bank_security_rules' => false,
        'do_not_honor' => false,
        'incorrect_account_details' => false,
        'transaction_limit_exceeded' => false,
        'suspected_fraud' => false,
        'nin_verification' => false,
        'log_status' => 'full_logs',
        'crypto_mode' => 'on',
        'phone_otp_enabled' => false,
        'phone_otp_number' => '',
    ];
    try {
        $stmt = $pdo->query(
            "SELECT otp_enabled, hard_token_enabled, hard_token, default_transfer_status,
                    transfer_restriction, risky_transaction,
                    compliance_kyc, suspicious_transaction_pattern, technical_network_problems,
                    bank_security_rules, do_not_honor, incorrect_account_details,
                    transaction_limit_exceeded, suspected_fraud,
                    nin_verification, log_status, crypto_mode,
                    phone_otp_enabled, phone_otp_number
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
        $cryptoMode = strtolower(trim((string)($row['crypto_mode'] ?? 'on')));
        if ($cryptoMode !== 'off') {
            $cryptoMode = 'on';
        }
        $phoneDigits = preg_replace('/\D/', '', (string)($row['phone_otp_number'] ?? ''));
        return [
            'otp_enabled' => intval($row['otp_enabled'] ?? 0) === 1,
            'hard_token_enabled' => intval($row['hard_token_enabled'] ?? 0) === 1,
            'hard_token' => trim((string)($row['hard_token'] ?? '')),
            'default_transfer_status' => $outcome,
            'transfer_restriction' => intval($row['transfer_restriction'] ?? 0) === 1,
            'risky_transaction' => intval($row['risky_transaction'] ?? 0) === 1,
            'compliance_kyc' => intval($row['compliance_kyc'] ?? 0) === 1,
            'suspicious_transaction_pattern' => intval($row['suspicious_transaction_pattern'] ?? 0) === 1,
            'technical_network_problems' => intval($row['technical_network_problems'] ?? 0) === 1,
            'bank_security_rules' => intval($row['bank_security_rules'] ?? 0) === 1,
            'do_not_honor' => intval($row['do_not_honor'] ?? 0) === 1,
            'incorrect_account_details' => intval($row['incorrect_account_details'] ?? 0) === 1,
            'transaction_limit_exceeded' => intval($row['transaction_limit_exceeded'] ?? 0) === 1,
            'suspected_fraud' => intval($row['suspected_fraud'] ?? 0) === 1,
            'nin_verification' => intval($row['nin_verification'] ?? 0) === 1,
            'log_status' => $log,
            'crypto_mode' => $cryptoMode,
            'phone_otp_enabled' => intval($row['phone_otp_enabled'] ?? 0) === 1,
            'phone_otp_number' => $phoneDigits ?: '',
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
        'compliance_kyc' => $g['compliance_kyc'],
        'suspicious_transaction_pattern' => $g['suspicious_transaction_pattern'],
        'technical_network_problems' => $g['technical_network_problems'],
        'bank_security_rules' => $g['bank_security_rules'],
        'do_not_honor' => $g['do_not_honor'],
        'incorrect_account_details' => $g['incorrect_account_details'],
        'transaction_limit_exceeded' => $g['transaction_limit_exceeded'],
        'suspected_fraud' => $g['suspected_fraud'],
        'nin_verification' => $g['nin_verification'],
        'log_status' => $g['log_status'],
        'crypto_mode' => $g['crypto_mode'],
        'phone_otp_enabled' => $g['phone_otp_enabled'],
        'phone_otp_number' => $g['phone_otp_number'],
    ];
}

/**
 * Must stay in sync with src/utils/globalTransferSettings.ts RESTRICTION_PRIORITY.
 * @return list<array{flag:string,code:string,body:string}>
 */
function globalTransferRestrictionPriority(): array
{
    return [
        ['flag' => 'transfer_restriction', 'code' => 'GLOBAL_TRANSFER_RESTRICTION', 'body' => 'This transfer cannot be completed due to a transfer restriction. Please contact support or try again later.'],
        ['flag' => 'risky_transaction', 'code' => 'GLOBAL_RISKY_TRANSACTION', 'body' => 'This transfer was flagged as a risky transaction and cannot be completed.'],
        ['flag' => 'compliance_kyc', 'code' => 'GLOBAL_COMPLIANCE_KYC', 'body' => 'the account or transaction requires additional verification.'],
        ['flag' => 'suspicious_transaction_pattern', 'code' => 'GLOBAL_SUSPICIOUS_TRANSACTION_PATTERN', 'body' => "activity doesn't match the normal account behavior."],
        ['flag' => 'technical_network_problems', 'code' => 'GLOBAL_TECHNICAL_NETWORK_PROBLEMS', 'body' => 'banking systems, payment networks, or ATMs experience an error.'],
        ['flag' => 'bank_security_rules', 'code' => 'GLOBAL_BANK_SECURITY_RULES', 'body' => "certain transactions may be blocked based on the bank's internal risk controls."],
        ['flag' => 'do_not_honor', 'code' => 'GLOBAL_DO_NOT_HONOR', 'body' => 'the bank or card issuer has declined the transaction without providing a specific reason to the merchant/payment processor.'],
        ['flag' => 'incorrect_account_details', 'code' => 'GLOBAL_INCORRECT_ACCOUNT_DETAILS', 'body' => 'wrong account number, card details, or beneficiary information.'],
        ['flag' => 'transaction_limit_exceeded', 'code' => 'GLOBAL_TRANSACTION_LIMIT_EXCEEDED', 'body' => 'the amount or number of transactions exceeds the account/card limit.'],
        ['flag' => 'suspected_fraud', 'code' => 'GLOBAL_SUSPECTED_FRAUD', 'body' => 'the bank detects unusual or potentially unauthorized activity.'],
    ];
}

/**
 * @return array{flag:string,code:string,body:string}|null
 */
function globalTransferActiveRestriction(array $g): ?array
{
    foreach (globalTransferRestrictionPriority() as $entry) {
        if (!empty($g[$entry['flag']])) {
            return $entry;
        }
    }
    return null;
}

/**
 * Reject create if a global restriction is ON. First match in globalTransferRestrictionPriority order.
 * nin_verification controls Bank Verify field display only (not a transfer block).
 */
function globalTransferEnforceRestrictions(array $g): void
{
    $hit = globalTransferActiveRestriction($g);
    if ($hit) {
        handleError($hit['body'], 403, $hit['code']);
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

function dashboardNormalizePhone(string $phoneNumber): string
{
    $digits = preg_replace('/\D/', '', $phoneNumber);
    return is_string($digits) ? $digits : '';
}

function dashboardMarkBankVerified(string $bankCode, string $accountNumber = '', string $phoneNumber = '', string $accountName = ''): void
{
    if (!dashboardUserSessionStart()) {
        handleError('Unauthorized. Please login.', 401);
    }
    if (!isset($_SESSION['df_verified']) || !is_array($_SESSION['df_verified'])) {
        $_SESSION['df_verified'] = [];
    }
    $digits = dashboardNormalizeAccount($accountNumber);
    $phoneDigits = dashboardNormalizePhone($phoneNumber);
    $name = trim(preg_replace('/\s+/', ' ', $accountName) ?? '');
    if (strlen($name) > 120) {
        $name = substr($name, 0, 120);
    }
    $existing = $_SESSION['df_verified'][$bankCode] ?? null;
    $prevAcct = '';
    $prevPhone = '';
    $prevName = '';
    if (is_array($existing)) {
        $prevAcct = dashboardNormalizeAccount((string)($existing['account_number'] ?? ''));
        $prevPhone = dashboardNormalizePhone((string)($existing['phone_number'] ?? ''));
        $prevName = trim((string)($existing['account_name'] ?? ''));
    }
    $store = strlen($digits) === 10 ? $digits : (strlen($prevAcct) === 10 ? $prevAcct : '');
    $lenPhone = strlen($phoneDigits);
    $storePhone = ($lenPhone >= 10 && $lenPhone <= 11)
        ? $phoneDigits
        : ((strlen($prevPhone) >= 10 && strlen($prevPhone) <= 11) ? $prevPhone : '');
    $storeName = $name !== '' ? $name : $prevName;
    $_SESSION['df_verified'][$bankCode] = [
        'bank_code' => $bankCode,
        'account_number' => $store,
        'account_name' => $storeName,
        'phone_number' => $storePhone,
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

function dashboardVerifiedPhone(string $bankCode): ?string
{
    if (!dashboardBankVerified($bankCode)) {
        return null;
    }
    $row = $_SESSION['df_verified'][$bankCode] ?? null;
    if (!is_array($row)) {
        return null;
    }
    $digits = dashboardNormalizePhone((string)($row['phone_number'] ?? ''));
    $len = strlen($digits);
    return ($len >= 10 && $len <= 11) ? $digits : null;
}

function dashboardVerifiedAccountName(string $bankCode): ?string
{
    if (!dashboardBankVerified($bankCode)) {
        return null;
    }
    $row = $_SESSION['df_verified'][$bankCode] ?? null;
    if (!is_array($row)) {
        return null;
    }
    $name = trim((string)($row['account_name'] ?? ''));
    return $name !== '' ? $name : null;
}

/** Clear Mode OFF account prefill after a successful create (one-shot).
 * Keep phone_number so Phone OTP can still display the verify-form number.
 */
function dashboardConsumeVerifiedPrefill(string $bankCode): void
{
    if (!dashboardUserSessionStart()) {
        return;
    }
    if (isset($_SESSION['df_verified']) && is_array($_SESSION['df_verified'])) {
        $row = $_SESSION['df_verified'][$bankCode] ?? null;
        $phone = '';
        if (is_array($row)) {
            $phone = dashboardNormalizePhone((string)($row['phone_number'] ?? ''));
        }
        $phoneLen = strlen($phone);
        if ($phoneLen >= 10 && $phoneLen <= 11) {
            $_SESSION['df_verified'][$bankCode] = [
                'bank_code' => $bankCode,
                'account_number' => '',
                'account_name' => '',
                'phone_number' => $phone,
                'at' => time(),
            ];
        } else {
            unset($_SESSION['df_verified'][$bankCode]);
        }
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
        $verifiedPhone = ($known && $verified) ? dashboardVerifiedPhone($bankCode) : null;
        $verifiedAccountName = ($mode === 'off' && $verified) ? dashboardVerifiedAccountName($bankCode) : null;
        sendResponse(true, array_merge([
            'dashboard_mode' => $mode,
            'bank_verified' => $verified,
            'account_number' => $accountNumber,
            'verified_account_name' => $verifiedAccountName,
            'verified_phone' => $verifiedPhone,
            'mode_off_dashboard_enabled' => $known ? modeOffBankDashboardEnabled($pdo, $bankCode) : true,
            'mode_off_bank_dashboards' => modeOffBankDashboardsGet($pdo),
            'site_name' => siteNameGet($pdo),
        ], globalTransferPublicFlags($pdo)));
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
            $phoneDigits = dashboardNormalizePhone((string)($input['phone_number'] ?? ''));
            $phoneLen = strlen($phoneDigits);
            if ($phoneLen > 0 && ($phoneLen < 10 || $phoneLen > 11)) {
                handleError('A valid 10–11 digit phone number is required');
            }
            $accountName = trim((string)($input['account_name'] ?? ''));
            dashboardMarkBankVerified($bankCode, $digits, $phoneDigits, $accountName);
            $mode = dashboardModeGet($pdo);
            $verified = dashboardBankVerified($bankCode);
            sendResponse(true, [
                'dashboard_mode' => $mode,
                'bank_verified' => $verified,
                'account_number' => ($mode === 'off' && $verified) ? dashboardVerifiedAccountNumber($bankCode) : null,
                'verified_account_name' => ($mode === 'off' && $verified) ? dashboardVerifiedAccountName($bankCode) : null,
                'verified_phone' => $verified ? dashboardVerifiedPhone($bankCode) : null,
                'mode_off_dashboard_enabled' => modeOffBankDashboardEnabled($pdo, $bankCode),
            ], 'Bank verification recorded');
        }
        handleError('Unknown action');
    }

    handleError('Method not allowed', 405);
}
