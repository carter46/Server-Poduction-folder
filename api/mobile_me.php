<?php
/**
 * Mobile profile / balance — identity from Bearer session only.
 */
require_once 'config.php';
require_once 'mobile_auth_helper.php';

$pdo = getDBConnection();
mobileEnsureSchema($pdo);

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    handleError('Method not allowed', 405);
}

$session = mobileValidateSession($pdo);
$bankCode = $session['bank_code'];
$accountNumber = $session['account_number'];
$balance = mobileSumSuccessfulBalance($pdo, $bankCode, $accountNumber);
$recent = mobileFindBeneficiaryTransactions($pdo, $bankCode, $accountNumber, 5, 0);
$all = mobileFindBeneficiaryTransactions($pdo, $bankCode, $accountNumber, 10000, 0);

sendResponse(true, [
    'bank_code' => $bankCode,
    'account_number' => $accountNumber,
    'account_name' => $session['account_name_snapshot'],
    'balance' => $balance,
    'balance_rule' => 'SUM of SUCCESSFUL beneficiary transfers for this bank+account (simulation; no recipient wallet on web)',
    'transaction_count' => count($all),
    'recent_transactions' => array_map('mobileBuildReceiptDto', $recent),
], 'OK');
