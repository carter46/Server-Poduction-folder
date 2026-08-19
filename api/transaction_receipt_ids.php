<?php
/**
 * Session ID / Reference ID helpers for transfer receipts.
 * Never assume session_id / reference_id columns exist until production ALTER has run.
 */

function txSanitizeTableName($table) {
    $table = strtolower((string)$table);
    if (!preg_match('/^[a-z0-9_]+$/', $table)) {
        return '';
    }
    return $table;
}

/**
 * True only when BOTH session_id and reference_id exist on the table.
 */
function txReceiptIdsColumnsExist(PDO $pdo, $table) {
    static $cache = [];
    $table = txSanitizeTableName($table);
    if ($table === '') {
        return false;
    }
    if (array_key_exists($table, $cache)) {
        return $cache[$table];
    }
    try {
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) AS c FROM information_schema.columns
             WHERE table_schema = DATABASE()
               AND table_name = ?
               AND column_name IN ('session_id', 'reference_id')"
        );
        $stmt->execute([$table]);
        $count = intval(($stmt->fetch() ?: [])['c'] ?? 0);
        $cache[$table] = ($count >= 2);
    } catch (Exception $e) {
        $cache[$table] = false;
    }
    return $cache[$table];
}

function txGeneratePersistedSessionId() {
    $rand = '';
    for ($i = 0; $i < 12; $i++) {
        $rand .= (string)random_int(0, 9);
    }
    return '000015' . date('ymdHis') . $rand;
}

function txGeneratePersistedReferenceId() {
    $digits = '';
    for ($i = 0; $i < 16; $i++) {
        $digits .= (string)random_int(0, 9);
    }
    return 'EXTTRF|' . $digits;
}

function txDeterministicDigitString($seed, $length) {
    $hex = hash('sha256', $seed) . hash('sha256', $seed . '|b');
    $digits = preg_replace('/[^0-9]/', '', $hex);
    if (strlen($digits) < $length) {
        $digits .= preg_replace('/[^0-9]/', '', hash('md5', $seed));
    }
    return str_pad(substr($digits, 0, $length), $length, '0');
}

function txDeterministicSessionId($id, $date, $reference) {
    $seed = 'sid|' . intval($id) . '|' . (string)$date . '|' . (string)$reference;
    return '000015' . txDeterministicDigitString($seed, 24);
}

function txDeterministicReferenceId($id, $date, $reference) {
    $seed = 'rid|' . intval($id) . '|' . (string)$date . '|' . (string)$reference;
    return 'EXTTRF|' . txDeterministicDigitString($seed, 16);
}

/**
 * Prefer stored IDs; otherwise stable IDs from id + date + reference.
 */
function txResolveReceiptIds($row) {
    $id = isset($row['source_id']) ? intval($row['source_id']) : intval($row['id'] ?? 0);
    $date = (string)($row['transaction_date'] ?? '');
    $ref = (string)($row['reference'] ?? '');
    $session = trim((string)($row['session_id'] ?? ''));
    $referenceId = trim((string)($row['reference_id'] ?? ''));
    if ($session === '') {
        $session = txDeterministicSessionId($id, $date, $ref);
    }
    if ($referenceId === '') {
        $referenceId = txDeterministicReferenceId($id, $date, $ref);
    }
    return [$session, $referenceId];
}

function txEnrichTransactionRow($row) {
    if (!is_array($row)) {
        return $row;
    }
    list($sessionId, $referenceId) = txResolveReceiptIds($row);
    $row['session_id'] = $sessionId;
    $row['reference_id'] = $referenceId;
    return $row;
}

/**
 * Insert a standard bank transfer row. Adds session_id/reference_id only when columns exist.
 * $fields: reference, amount, currency, beneficiary_name, beneficiary_bank,
 *          beneficiary_account, sender_account, sender_name, purpose, status
 */
function txInsertBankTransaction(PDO $pdo, $table, array $fields) {
    $table = txSanitizeTableName($table);
    if ($table === '') {
        throw new InvalidArgumentException('Invalid transaction table');
    }

    $baseCols = 'reference, amount, currency, beneficiary_name, beneficiary_bank, beneficiary_account, sender_account, sender_name, purpose, status, transaction_date';
    $basePlaceholders = '?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW()';
    $params = [
        $fields['reference'],
        floatval($fields['amount']),
        $fields['currency'] ?? 'NGN',
        $fields['beneficiary_name'],
        $fields['beneficiary_bank'],
        $fields['beneficiary_account'],
        $fields['sender_account'],
        $fields['sender_name'],
        $fields['purpose'] ?? null,
        $fields['status'] ?? 'SUCCESSFUL',
    ];

    if (txReceiptIdsColumnsExist($pdo, $table)) {
        $sql = "INSERT INTO `{$table}` ({$baseCols}, session_id, reference_id) VALUES ({$basePlaceholders}, ?, ?)";
        $params[] = txGeneratePersistedSessionId();
        $params[] = txGeneratePersistedReferenceId();
    } else {
        $sql = "INSERT INTO `{$table}` ({$baseCols}) VALUES ({$basePlaceholders})";
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return intval($pdo->lastInsertId());
}
