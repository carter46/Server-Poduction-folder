<?php
/**
 * Mobile transaction history — session-scoped beneficiary rows only.
 */
require_once 'config.php';
require_once 'mobile_auth_helper.php';

$pdo = getDBConnection();
mobileEnsureSchema($pdo);

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    handleError('Method not allowed', 405);
}

$session = mobileValidateSession($pdo);
$limit = isset($_GET['limit']) ? max(1, min(100, intval($_GET['limit']))) : 50;
$offset = isset($_GET['offset']) ? max(0, intval($_GET['offset'])) : 0;

$rows = mobileFindBeneficiaryTransactions($pdo, $session['bank_code'], $session['account_number'], $limit, $offset);
$all = mobileFindBeneficiaryTransactions($pdo, $session['bank_code'], $session['account_number'], 10000, 0);

sendResponse(true, [
    'transactions' => array_map('mobileBuildReceiptDto', $rows),
    'total' => count($all),
    'limit' => $limit,
    'offset' => $offset,
    'bank_code' => $session['bank_code'],
    'account_number' => $session['account_number'],
], 'OK');
