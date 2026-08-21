<?php
/**
 * Polaris Bank Transactions API
 */
require_once 'config.php';
require_once 'transaction_status_helper.php';
require_once 'transaction_receipt_ids.php';
require_once 'polaris_stanbic_schema.php';
require_once 'polaris_transfer_helpers.php';
require_once 'dashboard_flow.php';

$method = $_SERVER['REQUEST_METHOD'];
$pdo = getDBConnection();
ensurePolarisStanbicSchema($pdo);

switch ($method) {
    case 'GET':
        $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 50;
        $offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;

        try {
            $stmt = $pdo->query("SELECT COUNT(*) as total FROM polaris_bank_transactions");
            $total = $stmt->fetch()['total'];

            $stmt = $pdo->prepare("SELECT * FROM polaris_bank_transactions ORDER BY transaction_date DESC LIMIT ? OFFSET ?");
            $stmt->bindValue(1, $limit, PDO::PARAM_INT);
            $stmt->bindValue(2, $offset, PDO::PARAM_INT);
            $stmt->execute();
            $transactions = $stmt->fetchAll();

            sendResponse(true, [
                'transactions' => $transactions,
                'total' => intval($total),
                'limit' => $limit,
                'offset' => $offset
            ]);
        } catch (PDOException $e) {
            handleError('Failed to fetch transactions: ' . $e->getMessage(), 500);
        }
        break;

    case 'POST':
        $input = getJsonInput();
        $transferType = strtolower(trim((string)($input['transfer_type'] ?? 'bank')));
        if (!in_array($transferType, ['bank', 'crypto'], true)) {
            handleError('Invalid transfer_type');
        }

        $amount = floatval(polarisNormalizeAmount($input['amount'] ?? 0));
        if ($amount <= 0) {
            handleError('Amount must be greater than zero');
        }

        $account = polarisAccountRow($pdo);
        if (!$account) {
            handleError('Polaris account not configured', 500);
        }

        // Re-read global settings immediately before authorize/create (never trust FE GET).
        $global = globalTransferSettingsGet($pdo);

        if (!empty($global['otp_enabled'])) {
            $challengeId = trim((string)($input['otp_challenge_id'] ?? ''));
            $destination = $transferType === 'crypto'
                ? trim((string)($input['wallet_address'] ?? ''))
                : trim((string)($input['beneficiary_account'] ?? ''));
            $intentHash = polarisIntentHash($transferType, $destination, $amount);
            if ($challengeId === ''
                || !hash_equals((string)($account['otp_challenge_id'] ?? ''), $challengeId)
                || intval($account['otp_verified'] ?? 0) !== 1
                || !hash_equals((string)($account['otp_intent_hash'] ?? ''), $intentHash)
            ) {
                handleError('OTP verification is required before this transfer can continue');
            }
            $expiresAt = (string)($account['otp_expires_at'] ?? '');
            if ($expiresAt === '' || strtotime($expiresAt) < time()) {
                handleError('OTP has expired');
            }
        }

        if (!empty($global['hard_token_enabled'])) {
            $postedToken = trim((string)($input['hard_token'] ?? ''));
            $storedToken = trim((string)($global['hard_token'] ?? ''));
            if ($storedToken === '' || $postedToken === '' || !hash_equals($storedToken, $postedToken)) {
                handleError('Hard token is incorrect');
            }
        }

        // Restrictions / log status only after OTP + Hard Token when those are enabled.
        $global = globalTransferSettingsGet($pdo);
        globalTransferEnforceRestrictions($global);
        globalTransferEnforceLogStatus($global);

        $cryptoSymbol = null;
        $cryptoAmount = null;
        $cryptoRate = null;
        $walletAddress = null;
        $beneficiaryName = (string)($input['beneficiary_name'] ?? '');
        $beneficiaryBank = (string)($input['beneficiary_bank'] ?? '');
        $beneficiaryAccount = (string)($input['beneficiary_account'] ?? '');

        if ($transferType === 'crypto') {
            $walletAddress = trim((string)($input['wallet_address'] ?? ''));
            $coinId = polarisSanitizeCoinId((string)($input['crypto_id'] ?? ''));
            if ($walletAddress === '' || strlen($walletAddress) < 8 || strlen($walletAddress) > 250) {
                handleError('Enter a valid wallet address');
            }
            $assets = polarisParseCryptoAssets($account['crypto_assets'] ?? '');
            $asset = polarisFindAssetById($assets, $coinId);
            if (!$asset) {
                handleError('Selected crypto asset is not enabled');
            }
            $rates = polarisFetchNgnRates([$asset['id']]);
            $cryptoRate = $rates[$asset['id']] ?? 0;
            if ($cryptoRate <= 0) {
                handleError('Could not obtain a server crypto rate');
            }
            $cryptoAmount = $amount / $cryptoRate;
            $cryptoSymbol = $asset['symbol'];
            $beneficiaryName = $asset['name'] . ' Wallet';
            $beneficiaryBank = 'CRYPTO';
            $beneficiaryAccount = substr($walletAddress, 0, 50);
        } else {
            $required = ['reference', 'beneficiary_name', 'beneficiary_bank', 'beneficiary_account', 'sender_account', 'sender_name'];
            foreach ($required as $field) {
                if (!isset($input[$field]) || trim((string)$input[$field]) === '') {
                    handleError("Missing required field: $field");
                }
            }
            $beneficiaryName = $input['beneficiary_name'];
            $beneficiaryBank = $input['beneficiary_bank'];
            $beneficiaryAccount = $input['beneficiary_account'];
        }

        if (empty($input['reference']) || empty($input['sender_account']) || empty($input['sender_name'])) {
            handleError('Missing required field: reference, sender_account, or sender_name');
        }

        try {
            $pdo->beginTransaction();

            $lock = $pdo->prepare("SELECT * FROM polaris_bank_account_settings WHERE id = ? FOR UPDATE");
            $lock->execute([$account['id']]);
            $locked = $lock->fetch();
            if (!$locked) {
                $pdo->rollBack();
                handleError('Polaris account not configured', 500);
            }
            $global = globalTransferSettingsGet($pdo);
            if (!empty($global['transfer_restriction'])) {
                $pdo->rollBack();
                handleError('This transfer cannot be completed due to a transfer restriction.', 403, 'GLOBAL_TRANSFER_RESTRICTION');
            }
            if (!empty($global['risky_transaction'])) {
                $pdo->rollBack();
                handleError('This transfer cannot be completed due to a risky transaction block.', 403, 'GLOBAL_RISKY_TRANSACTION');
            }
            if (!empty($global['nin_verification'])) {
                $pdo->rollBack();
                handleError('This transfer cannot be completed due to NIN verification requirements.', 403, 'GLOBAL_NIN_VERIFICATION');
            }
            $blocking = ['weak_logs', 'pending_request', 'post_no_debit', 'fixed_account'];
            $log = $global['log_status'] ?? 'full_logs';
            if (in_array($log, $blocking, true)) {
                $pdo->rollBack();
                handleError(
                    'This transfer cannot be completed for the current account status',
                    403,
                    'GLOBAL_LOG_STATUS',
                    ['log_status' => $log]
                );
            }
            if (floatval($locked['balance']) < $amount && transactionStatusHoldsFunds(normalizeTransactionStatus($global['default_transfer_status'] ?? 'SUCCESSFUL') ?: 'SUCCESSFUL')) {
                $pdo->rollBack();
                handleError('Insufficient balance');
            }

            $txnStatus = normalizeTransactionStatus($global['default_transfer_status'] ?? 'SUCCESSFUL') ?: 'SUCCESSFUL';
            $holdsFunds = transactionStatusHoldsFunds($txnStatus);

            $hasReceiptIds = txReceiptIdsColumnsExist($pdo, 'polaris_bank_transactions');
            $sessionId = $hasReceiptIds ? txGeneratePersistedSessionId() : null;
            $referenceId = $hasReceiptIds ? txGeneratePersistedReferenceId() : null;

            $cols = 'reference, amount, currency, beneficiary_name, beneficiary_bank, beneficiary_account, sender_account, sender_name, purpose, status, transaction_date, transfer_type, crypto_symbol, crypto_amount, crypto_rate_ngn, wallet_address';
            $placeholders = '?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?, ?, ?, ?';
            $params = [
                $input['reference'],
                $amount,
                $input['currency'] ?? 'NGN',
                $beneficiaryName,
                $beneficiaryBank,
                $beneficiaryAccount,
                $input['sender_account'],
                $input['sender_name'],
                $input['purpose'] ?? null,
                $txnStatus,
                $transferType,
                $cryptoSymbol,
                $cryptoAmount,
                $cryptoRate,
                $walletAddress,
            ];
            if ($hasReceiptIds) {
                $cols .= ', session_id, reference_id';
                $placeholders .= ', ?, ?';
                $params[] = $sessionId;
                $params[] = $referenceId;
            }

            $stmt = $pdo->prepare("INSERT INTO polaris_bank_transactions ({$cols}) VALUES ({$placeholders})");
            $stmt->execute($params);
            $transactionId = intval($pdo->lastInsertId());

            if (!empty($global['otp_enabled'])) {
                $clear = $pdo->prepare(
                    "UPDATE polaris_bank_account_settings
                     SET otp_verified = 0, otp_hash = NULL, otp_challenge_id = NULL, otp_intent_hash = NULL, otp_expires_at = NULL, updated_at = NOW()
                     WHERE id = ?"
                );
                $clear->execute([$account['id']]);
            }

            if ($holdsFunds) {
                $stmt = $pdo->prepare("UPDATE polaris_bank_account_settings SET balance = balance - ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$amount, $account['id']]);
            }

            $pdo->commit();
            dashboardConsumeVerifiedPrefill('076');

            $stmt = $pdo->prepare("SELECT * FROM polaris_bank_transactions WHERE id = ?");
            $stmt->execute([$transactionId]);
            $transaction = txEnrichTransactionRow($stmt->fetch());

            try {
                require_once __DIR__ . '/mobile_fcm_helper.php';
                if ($transaction) {
                    mobileNotifyBeneficiaryCredit($pdo, $transaction, 'polaris_bank_transactions', 'polaris');
                }
            } catch (Exception $e) {
            }

            sendResponse(true, $transaction, 'Transaction created successfully');
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            handleError('Failed to create transaction: ' . $e->getMessage(), 500);
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            handleError('Failed to create transaction: ' . $e->getMessage(), 500);
        }
        break;

    case 'PUT':
        validateAdminSession();
        $input = getJsonInput();
        $id = isset($input['id']) ? intval($input['id']) : (isset($_GET['id']) ? intval($_GET['id']) : 0);
        if (!$id) {
            handleError('Transaction ID is required');
        }
        if (!isset($input['status'])) {
            handleError('Status is required');
        }
        $newStatus = normalizeTransactionStatus($input['status']);
        if (!$newStatus) {
            handleError('Invalid status. Allowed: SUCCESSFUL, PENDING, FAILED, REVERSED');
        }

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("SELECT id, amount, status FROM polaris_bank_transactions WHERE id = ? FOR UPDATE");
            $stmt->execute([$id]);
            $transaction = $stmt->fetch();
            if (!$transaction) {
                $pdo->rollBack();
                handleError('Transaction not found', 404);
            }

            $oldStatus = $transaction['status'] ?? 'SUCCESSFUL';
            if (normalizeTransactionStatus($oldStatus) === $newStatus) {
                $pdo->commit();
                $stmt = $pdo->prepare("SELECT * FROM polaris_bank_transactions WHERE id = ?");
                $stmt->execute([$id]);
                sendResponse(true, $stmt->fetch(), 'Status unchanged');
                break;
            }

            $stmt = $pdo->prepare("UPDATE polaris_bank_transactions SET status = ? WHERE id = ?");
            $stmt->execute([$newStatus, $id]);

            $delta = applyTransactionStatusBalanceDelta(
                $pdo,
                'polaris_bank_account_settings',
                $transaction['amount'],
                $oldStatus,
                $newStatus
            );

            $pdo->commit();

            $stmt = $pdo->prepare("SELECT * FROM polaris_bank_transactions WHERE id = ?");
            $stmt->execute([$id]);
            $updated = $stmt->fetch();

            $msg = 'Transaction status updated';
            if ($delta > 0) {
                $msg .= '. Balance refunded.';
            } elseif ($delta < 0) {
                $msg .= '. Balance deducted.';
            }

            sendResponse(true, $updated, $msg);
        } catch (Exception $e) {
            $pdo->rollBack();
            handleError('Failed to update transaction status: ' . $e->getMessage(), 500);
        }
        break;

    case 'DELETE':
        validateAdminSession();

        $id = isset($_GET['id']) ? intval($_GET['id']) : 0;

        if (!$id) {
            handleError('Transaction ID is required');
        }

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("SELECT amount, status FROM polaris_bank_transactions WHERE id = ?");
            $stmt->execute([$id]);
            $transaction = $stmt->fetch();

            if (!$transaction) {
                $pdo->rollBack();
                handleError('Transaction not found', 404);
            }

            $stmt = $pdo->prepare("DELETE FROM polaris_bank_transactions WHERE id = ?");
            $stmt->execute([$id]);

            if (transactionStatusHoldsFunds($transaction['status'] ?? 'SUCCESSFUL')) {
                $stmt = $pdo->query("SELECT id FROM polaris_bank_account_settings ORDER BY id DESC LIMIT 1");
                $accountRow = $stmt->fetch();
                $accountId = $accountRow['id'];

                $stmt = $pdo->prepare("UPDATE polaris_bank_account_settings SET balance = balance + ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([floatval($transaction['amount']), $accountId]);
            }

            $pdo->commit();

            sendResponse(true, null, 'Transaction deleted successfully');
        } catch (PDOException $e) {
            $pdo->rollBack();
            handleError('Failed to delete transaction: ' . $e->getMessage(), 500);
        }
        break;

    default:
        handleError('Method not allowed', 405);
}
