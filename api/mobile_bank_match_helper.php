<?php
/**
 * Mobile companion — bank alias normalization + beneficiary lookup across all bank tx tables.
 *
 * Balance simulation rule (documented):
 *   balance = SUM(amount) WHERE beneficiary matches (bank, account) AND status = 'SUCCESSFUL'
 * Pending visible in history but not counted. FAILED/REVERSED excluded from balance.
 */

require_once __DIR__ . '/transaction_receipt_ids.php';

function mobileNormalizeAccountNumber($account) {
    return preg_replace('/\D+/', '', (string)$account);
}

function mobileCanonicalBankCodes() {
    return ['UBA', 'FIRST', 'ZENITH', 'ACCESS', 'WEMA'];
}

/**
 * Map free-text beneficiary_bank (or code) to canonical UBA|FIRST|ZENITH|ACCESS|WEMA|null
 */
function mobileCanonicalBankCode($bankNameOrCode) {
    $raw = strtoupper(trim((string)$bankNameOrCode));
    $raw = preg_replace('/\s+/', ' ', $raw);
    $compact = preg_replace('/[^A-Z0-9]/', '', $raw);

    if ($raw === 'UBA' || $raw === '033' || $compact === '033' || strpos($compact, 'UNITEDBANKFORAFRICA') !== false || $compact === 'UBA') {
        return 'UBA';
    }
    if (
        $raw === '011' || $compact === '011' ||
        $raw === 'FBN' || $compact === 'FBN' ||
        strpos($compact, 'FIRSTBANK') !== false ||
        strpos($compact, 'FIRSTBANKOFNIGERIA') !== false ||
        $raw === 'FIRST' ||
        preg_match('/\bFIRST\b/', $raw) ||
        preg_match('/\bFBN\b/', $raw)
    ) {
        return 'FIRST';
    }
    if (
        $raw === '057' || $compact === '057' ||
        strpos($compact, 'ZENITH') !== false ||
        $raw === 'ZENITH'
    ) {
        return 'ZENITH';
    }
    if (
        $raw === '035' || $compact === '035' ||
        $raw === 'WEMA' || $compact === 'WEMA' || $compact === 'WEMABANK' ||
        strpos($compact, 'WEMABANK') !== false ||
        strpos($compact, 'ALAT') !== false ||
        strpos($compact, 'ALATBYWEMA') !== false
    ) {
        return 'WEMA';
    }
    if (
        $raw === '044' || $compact === '044' ||
        strpos($compact, 'ACCESS') !== false ||
        $raw === 'ACCESS'
    ) {
        return 'ACCESS';
    }

    return null;
}

function mobileEnsureSchema(PDO $pdo) {
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    $pdo->exec("CREATE TABLE IF NOT EXISTS mobile_settings (
        id INT(11) NOT NULL PRIMARY KEY DEFAULT 1,
        password_hash VARCHAR(255) DEFAULT NULL,
        fcm_server_key VARCHAR(512) DEFAULT NULL,
        updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS mobile_sessions (
        id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
        token VARCHAR(128) NOT NULL,
        bank_code VARCHAR(20) NOT NULL,
        account_number VARCHAR(50) NOT NULL,
        account_name_snapshot VARCHAR(255) DEFAULT NULL,
        expires_at DATETIME NOT NULL,
        created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_mobile_session_token (token),
        KEY idx_mobile_session_account (bank_code, account_number)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS mobile_device_tokens (
        id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
        bank_code VARCHAR(20) NOT NULL,
        account_number VARCHAR(50) NOT NULL,
        fcm_token VARCHAR(512) NOT NULL,
        updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_mobile_device (bank_code, account_number)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $stmt = $pdo->query("SELECT id FROM mobile_settings WHERE id = 1");
    if (!$stmt->fetch()) {
        $pdo->exec("INSERT INTO mobile_settings (id, password_hash) VALUES (1, NULL)");
    }

    mobileEnsureBeneficiaryIndexes($pdo);
}

/**
 * Add beneficiary_account indexes once per table (ignore if already present).
 */
function mobileEnsureBeneficiaryIndexes(PDO $pdo) {
    static $indexed = false;
    if ($indexed) {
        return;
    }
    $indexed = true;

    foreach (mobileTransactionSources() as $src) {
        $table = $src['table'];
        $indexName = 'idx_ben_acct_' . substr(md5($table), 0, 8);
        try {
            $check = $pdo->prepare(
                "SELECT 1 FROM information_schema.statistics
                 WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ? LIMIT 1"
            );
            $check->execute([$table, $indexName]);
            if ($check->fetch()) {
                continue;
            }
            $pdo->exec("CREATE INDEX `{$indexName}` ON `{$table}` (`beneficiary_account`)");
        } catch (PDOException $e) {
            // Table may not exist yet or index creation may fail — non-fatal
        }
    }
}

function mobileTransactionSources() {
    return [
        ['table' => 'uba_transactions', 'source_bank' => 'uba'],
        ['table' => 'first_bank_transactions', 'source_bank' => 'first'],
        ['table' => 'zenith_bank_transactions', 'source_bank' => 'zenith'],
        ['table' => 'access_bank_transactions', 'source_bank' => 'access'],
        ['table' => 'wema_bank_transactions', 'source_bank' => 'wema'],
    ];
}

/**
 * SQL digits-only expression for account columns (MySQL).
 */
function mobileSqlDigitsOnlyAccountExpr($column = 'beneficiary_account') {
    return "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(`{$column}`, ' ', ''), '-', ''), '.', ''), '/', ''), '_', '')";
}

/**
 * Returns matching beneficiary rows for canonical bank + account across all tables.
 * Filters by account in SQL, then bank aliases in PHP. Each row includes source_table, source_bank, source_id.
 */
function mobileFindBeneficiaryTransactions(PDO $pdo, $canonicalBankCode, $accountNumber, $limit = 100, $offset = 0) {
    $canonicalBankCode = strtoupper(trim((string)$canonicalBankCode));
    $accountNumber = mobileNormalizeAccountNumber($accountNumber);
    if (!$canonicalBankCode || !$accountNumber) {
        return [];
    }

    $digitsExpr = mobileSqlDigitsOnlyAccountExpr('beneficiary_account');
    $all = [];
    foreach (mobileTransactionSources() as $src) {
        $table = $src['table'];
        try {
            $sql = "SELECT * FROM `{$table}`
                    WHERE {$digitsExpr} = ?
                    ORDER BY transaction_date DESC
                    LIMIT 500";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$accountNumber]);
            $rows = $stmt->fetchAll();
        } catch (PDOException $e) {
            continue;
        }
        foreach ($rows as $row) {
            $benBank = mobileCanonicalBankCode($row['beneficiary_bank'] ?? '');
            if ($benBank === $canonicalBankCode) {
                $row['source_table'] = $table;
                $row['source_bank'] = $src['source_bank'];
                $row['source_id'] = intval($row['id']);
                $row['bank_code'] = $canonicalBankCode;
                $all[] = $row;
            }
        }
    }

    usort($all, function ($a, $b) {
        return strcmp((string)($b['transaction_date'] ?? ''), (string)($a['transaction_date'] ?? ''));
    });

    return array_slice($all, $offset, $limit);
}

function mobileBeneficiaryExists(PDO $pdo, $canonicalBankCode, $accountNumber) {
    $canonicalBankCode = strtoupper(trim((string)$canonicalBankCode));
    $accountNumber = mobileNormalizeAccountNumber($accountNumber);
    if (!$canonicalBankCode || !$accountNumber) {
        return null;
    }

    $digitsExpr = mobileSqlDigitsOnlyAccountExpr('beneficiary_account');
    foreach (mobileTransactionSources() as $src) {
        $table = $src['table'];
        try {
            $sql = "SELECT * FROM `{$table}` WHERE {$digitsExpr} = ? ORDER BY transaction_date DESC LIMIT 20";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$accountNumber]);
            $rows = $stmt->fetchAll();
        } catch (PDOException $e) {
            continue;
        }
        foreach ($rows as $row) {
            if (mobileCanonicalBankCode($row['beneficiary_bank'] ?? '') === $canonicalBankCode) {
                $row['source_table'] = $table;
                $row['source_bank'] = $src['source_bank'];
                $row['source_id'] = intval($row['id']);
                $row['bank_code'] = $canonicalBankCode;
                return $row;
            }
        }
    }
    return null;
}

function mobileSumSuccessfulBalance(PDO $pdo, $canonicalBankCode, $accountNumber) {
    $canonicalBankCode = strtoupper(trim((string)$canonicalBankCode));
    $accountNumber = mobileNormalizeAccountNumber($accountNumber);
    if (!$canonicalBankCode || !$accountNumber) {
        return 0.0;
    }

    $digitsExpr = mobileSqlDigitsOnlyAccountExpr('beneficiary_account');
    $sum = 0.0;
    foreach (mobileTransactionSources() as $src) {
        $table = $src['table'];
        try {
            $sql = "SELECT amount, beneficiary_bank, status FROM `{$table}`
                    WHERE {$digitsExpr} = ?
                      AND UPPER(TRIM(COALESCE(status, 'SUCCESSFUL'))) IN ('SUCCESSFUL', 'SUCCESS', '')";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$accountNumber]);
            $rows = $stmt->fetchAll();
        } catch (PDOException $e) {
            continue;
        }
        foreach ($rows as $row) {
            if (mobileCanonicalBankCode($row['beneficiary_bank'] ?? '') === $canonicalBankCode) {
                $sum += floatval($row['amount']);
            }
        }
    }
    return round($sum, 2);
}

function mobileFindTransactionBySource(PDO $pdo, $sourceTable, $sourceId) {
    $allowed = [];
    foreach (mobileTransactionSources() as $src) {
        $allowed[$src['table']] = $src['source_bank'];
    }
    if (!isset($allowed[$sourceTable])) {
        return null;
    }
    $stmt = $pdo->prepare("SELECT * FROM `{$sourceTable}` WHERE id = ?");
    $stmt->execute([intval($sourceId)]);
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }
    $row['source_table'] = $sourceTable;
    $row['source_bank'] = $allowed[$sourceTable];
    $row['source_id'] = intval($row['id']);
    $row['bank_code'] = mobileCanonicalBankCode($row['beneficiary_bank'] ?? '');
    return $row;
}

function mobileSenderBankLabel($sourceBank) {
    $map = [
        'uba' => 'UBA',
        'first' => 'First Bank',
        'zenith' => 'Zenith Bank',
        'access' => 'Access Bank',
        'wema' => 'Wema Bank',
    ];
    $key = strtolower((string)$sourceBank);
    return $map[$key] ?? null;
}

function mobileBuildReceiptDto($row) {
    $status = strtoupper(trim((string)($row['status'] ?? 'SUCCESSFUL')));
    if ($status === 'SUCCESS') {
        $status = 'SUCCESSFUL';
    }
    $sourceBank = $row['source_bank'] ?? null;
    list($sessionId, $referenceId) = txResolveReceiptIds(is_array($row) ? $row : []);
    return [
        'source_table' => $row['source_table'] ?? null,
        'source_bank' => $sourceBank,
        'source_id' => isset($row['source_id']) ? intval($row['source_id']) : intval($row['id'] ?? 0),
        'transaction_id' => ($row['source_table'] ?? 'tx') . ':' . (isset($row['source_id']) ? intval($row['source_id']) : intval($row['id'] ?? 0)),
        'bank_code' => $row['bank_code'] ?? mobileCanonicalBankCode($row['beneficiary_bank'] ?? ''),
        'reference' => $row['reference'] ?? null,
        'session_id' => $sessionId,
        'reference_id' => $referenceId,
        'amount' => floatval($row['amount'] ?? 0),
        'currency' => $row['currency'] ?? 'NGN',
        'status' => $status,
        'purpose' => $row['purpose'] ?? null,
        'transaction_date' => $row['transaction_date'] ?? null,
        'beneficiary_name' => $row['beneficiary_name'] ?? null,
        'beneficiary_bank' => $row['beneficiary_bank'] ?? null,
        'beneficiary_account' => $row['beneficiary_account'] ?? null,
        'sender_name' => $row['sender_name'] ?? null,
        'sender_account' => $row['sender_account'] ?? null,
        'sender_bank' => mobileSenderBankLabel($sourceBank),
        'direction' => 'credit',
    ];
}
