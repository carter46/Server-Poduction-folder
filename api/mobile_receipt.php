<?php
/**
 * Mobile receipt DTO — session must own the beneficiary account for this tx.
 * Query: ?id=source_table:source_id  OR  ?source_table=&source_id=
 */
require_once 'config.php';
require_once 'mobile_auth_helper.php';

$pdo = getDBConnection();
mobileEnsureSchema($pdo);

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    handleError('Method not allowed', 405);
}

$session = mobileValidateSession($pdo);

$sourceTable = isset($_GET['source_table']) ? trim($_GET['source_table']) : '';
$sourceId = isset($_GET['source_id']) ? intval($_GET['source_id']) : 0;
$idParam = isset($_GET['id']) ? trim($_GET['id']) : '';

if ($idParam !== '' && strpos($idParam, ':') !== false) {
    list($sourceTable, $idPart) = explode(':', $idParam, 2);
    $sourceId = intval($idPart);
}

if (!$sourceTable || !$sourceId) {
    handleError('id (source_table:source_id) or source_table + source_id required');
}

$row = mobileFindTransactionBySource($pdo, $sourceTable, $sourceId);
if (!$row) {
    handleError('Transaction not found', 404);
}

$benAcct = mobileNormalizeAccountNumber($row['beneficiary_account'] ?? '');
$benBank = mobileCanonicalBankCode($row['beneficiary_bank'] ?? '');
if ($benAcct !== $session['account_number'] || $benBank !== $session['bank_code']) {
    handleError('Forbidden', 403);
}

sendResponse(true, mobileBuildReceiptDto($row), 'OK');
