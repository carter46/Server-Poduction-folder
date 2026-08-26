<?php
/**
 * Shared helpers for transaction status updates and balance adjustments.
 * SUCCESSFUL / PENDING = funds deducted (held)
 * FAILED / REVERSED = funds returned to balance
 */

function normalizeTransactionStatus($status) {
    $s = strtoupper(trim((string)$status));
    if ($s === 'SUCCESS') {
        $s = 'SUCCESSFUL';
    }
    $allowed = ['SUCCESSFUL', 'PENDING', 'FAILED', 'REVERSED'];
    if (!in_array($s, $allowed, true)) {
        return null;
    }
    return $s;
}

function transactionStatusHoldsFunds($status) {
    $s = normalizeTransactionStatus($status);
    return $s === 'SUCCESSFUL' || $s === 'PENDING';
}

function transactionStatusAllowedAccountTables(): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    $cache = [
        'uba_account_settings',
        'first_bank_account_settings',
        'zenith_bank_account_settings',
        'access_bank_account_settings',
        'wema_bank_account_settings',
        'polaris_bank_account_settings',
        'stanbic_bank_account_settings',
        'fidelity_bank_account_settings',
        // Restored Bank Kit banks (Sterling, GTBank, etc.)
        'gtbank_account_settings',
        'heritage_bank_account_settings',
        'jaiz_bank_account_settings',
        'keystone_bank_account_settings',
        'sterling_bank_account_settings',
        'union_bank_account_settings',
        'unity_bank_account_settings',
        'kuda_bank_account_settings',
        'opay_account_settings',
        'moniepoint_account_settings',
        'palmpay_account_settings',
    ];

    $kitPath = __DIR__ . '/bank_kit.php';
    if (is_file($kitPath) && !function_exists('bankKitRegistry')) {
        require_once $kitPath;
    }
    if (function_exists('bankKitRegistry')) {
        foreach (bankKitRegistry() as $bank) {
            if (!empty($bank['account_table'])) {
                $cache[] = $bank['account_table'];
            }
        }
    }

    $cache = array_values(array_unique($cache));
    return $cache;
}

/**
 * Adjust account balance when status changes between held <-> returned.
 * $accountTable must be a trusted constant from our code (not user input).
 * @return float Delta applied to balance (+ refund, - deduct, 0 none)
 */
function applyTransactionStatusBalanceDelta(PDO $pdo, $accountTable, $amount, $oldStatus, $newStatus) {
    if (!in_array($accountTable, transactionStatusAllowedAccountTables(), true)) {
        throw new Exception('Invalid account table');
    }

    $oldHolds = transactionStatusHoldsFunds($oldStatus);
    $newHolds = transactionStatusHoldsFunds($newStatus);
    if ($oldHolds === $newHolds) {
        return 0.0;
    }

    $stmt = $pdo->query("SELECT id FROM {$accountTable} ORDER BY id DESC LIMIT 1 FOR UPDATE");
    $accountRow = $stmt->fetch();
    if (!$accountRow) {
        throw new Exception('Account settings not found');
    }
    $accountId = $accountRow['id'];
    $amount = floatval($amount);

    if ($oldHolds && !$newHolds) {
        $stmt = $pdo->prepare("UPDATE {$accountTable} SET balance = balance + ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$amount, $accountId]);
        return $amount;
    }

    $stmt = $pdo->prepare("UPDATE {$accountTable} SET balance = balance - ?, updated_at = NOW() WHERE id = ?");
    $stmt->execute([$amount, $accountId]);
    return -$amount;
}
