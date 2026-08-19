<?php
/**
 * Mobile auth API — login with bank_code + account_number + shared password.
 */
require_once 'config.php';
require_once 'mobile_auth_helper.php';

$pdo = getDBConnection();
mobileEnsureSchema($pdo);

$method = $_SERVER['REQUEST_METHOD'];
if ($method !== 'POST') {
    handleError('Method not allowed', 405);
}

$input = getJsonInput() ?: [];
$action = isset($input['action']) ? strtolower(trim($input['action'])) : 'login';

if ($action === 'login') {
    $bankCode = isset($input['bank_code']) ? strtoupper(trim($input['bank_code'])) : '';
    $accountNumber = isset($input['account_number']) ? mobileNormalizeAccountNumber($input['account_number']) : '';
    $password = isset($input['password']) ? (string)$input['password'] : '';

    if (!$bankCode || !$accountNumber || $password === '') {
        handleError('bank_code, account_number, and password are required');
    }
    if (!in_array($bankCode, mobileCanonicalBankCodes(), true)) {
        handleError('Invalid bank_code. Use UBA, FIRST, ZENITH, ACCESS, or WEMA');
    }

    $hash = mobileGetSharedPasswordHash($pdo);
    if (!$hash) {
        handleError('Mobile login password has not been set by admin', 403);
    }
    if (!password_verify($password, $hash)) {
        handleError('Invalid credentials', 401);
    }

    $match = mobileBeneficiaryExists($pdo, $bankCode, $accountNumber);
    if (!$match) {
        handleError('No transfers found for this account under the selected bank. Login not allowed.', 403);
    }

    $accountName = $match['beneficiary_name'] ?? 'Account Holder';
    $session = mobileCreateSession($pdo, $bankCode, $accountNumber, $accountName);
    $balance = mobileSumSuccessfulBalance($pdo, $bankCode, $accountNumber);

    sendResponse(true, [
        'token' => $session['token'],
        'expires_at' => $session['expires_at'],
        'bank_code' => $bankCode,
        'account_number' => $accountNumber,
        'account_name' => $accountName,
        'balance' => $balance,
        'balance_rule' => 'SUM of SUCCESSFUL beneficiary transfers for this bank+account (simulation; no recipient wallet on web)',
    ], 'Login successful');
}

if ($action === 'logout') {
    mobileDestroySession($pdo);
    sendResponse(true, null, 'Logged out');
}

if ($action === 'check') {
    $session = mobileValidateSession($pdo);
    $balance = mobileSumSuccessfulBalance($pdo, $session['bank_code'], $session['account_number']);
    sendResponse(true, [
        'bank_code' => $session['bank_code'],
        'account_number' => $session['account_number'],
        'account_name' => $session['account_name_snapshot'],
        'balance' => $balance,
        'expires_at' => $session['expires_at'],
    ], 'Session valid');
}

handleError('Unknown action. Use login, logout, or check');
