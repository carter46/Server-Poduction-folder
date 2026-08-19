<?php
/**
 * First Bank Transactions API
 * Handles transaction creation, retrieval, status update, and deletion for First Bank
 */

require_once 'config.php';
require_once 'transaction_status_helper.php';
require_once 'transaction_receipt_ids.php';

$method = $_SERVER['REQUEST_METHOD'];
$pdo = getDBConnection();

switch ($method) {
    case 'GET':
        $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 50;
        $offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;
        
        try {
            $stmt = $pdo->query("SELECT COUNT(*) as total FROM first_bank_transactions");
            $total = $stmt->fetch()['total'];
            
            $stmt = $pdo->prepare("SELECT * FROM first_bank_transactions ORDER BY transaction_date DESC LIMIT ? OFFSET ?");
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
        
        $required = ['reference', 'amount', 'beneficiary_name', 'beneficiary_bank', 'beneficiary_account', 'sender_account', 'sender_name'];
        foreach ($required as $field) {
            if (!isset($input[$field])) {
                handleError("Missing required field: $field");
            }
        }
        
        try {
            $pdo->beginTransaction();
            
            $transactionId = txInsertBankTransaction($pdo, 'first_bank_transactions', [
                'reference' => $input['reference'],
                'amount' => $input['amount'],
                'currency' => $input['currency'] ?? 'NGN',
                'beneficiary_name' => $input['beneficiary_name'],
                'beneficiary_bank' => $input['beneficiary_bank'],
                'beneficiary_account' => $input['beneficiary_account'],
                'sender_account' => $input['sender_account'],
                'sender_name' => $input['sender_name'],
                'purpose' => $input['purpose'] ?? null,
                'status' => $input['status'] ?? 'SUCCESSFUL',
            ]);
            
            $stmt = $pdo->query("SELECT id FROM first_bank_account_settings ORDER BY id DESC LIMIT 1");
            $accountRow = $stmt->fetch();
            $accountId = $accountRow['id'];
            
            $stmt = $pdo->prepare("UPDATE first_bank_account_settings SET balance = balance - ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([floatval($input['amount']), $accountId]);
            
            $pdo->commit();
            
            $stmt = $pdo->prepare("SELECT * FROM first_bank_transactions WHERE id = ?");
            $stmt->execute([$transactionId]);
            $transaction = txEnrichTransactionRow($stmt->fetch());

            try {
                require_once __DIR__ . '/mobile_fcm_helper.php';
                if ($transaction) {
                    mobileNotifyBeneficiaryCredit($pdo, $transaction, 'first_bank_transactions', 'first');
                }
            } catch (Exception $e) {
            }
            
            sendResponse(true, $transaction, 'Transaction created successfully');
        } catch (PDOException $e) {
            $pdo->rollBack();
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

            $stmt = $pdo->prepare("SELECT id, amount, status FROM first_bank_transactions WHERE id = ?");
            $stmt->execute([$id]);
            $transaction = $stmt->fetch();
            if (!$transaction) {
                $pdo->rollBack();
                handleError('Transaction not found', 404);
            }

            $oldStatus = $transaction['status'] ?? 'SUCCESSFUL';
            if (normalizeTransactionStatus($oldStatus) === $newStatus) {
                $pdo->commit();
                $stmt = $pdo->prepare("SELECT * FROM first_bank_transactions WHERE id = ?");
                $stmt->execute([$id]);
                sendResponse(true, $stmt->fetch(), 'Status unchanged');
                break;
            }

            $stmt = $pdo->prepare("UPDATE first_bank_transactions SET status = ? WHERE id = ?");
            $stmt->execute([$newStatus, $id]);

            $delta = applyTransactionStatusBalanceDelta(
                $pdo,
                'first_bank_account_settings',
                $transaction['amount'],
                $oldStatus,
                $newStatus
            );

            $pdo->commit();

            $stmt = $pdo->prepare("SELECT * FROM first_bank_transactions WHERE id = ?");
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
            
            $stmt = $pdo->prepare("SELECT amount, status FROM first_bank_transactions WHERE id = ?");
            $stmt->execute([$id]);
            $transaction = $stmt->fetch();
            
            if (!$transaction) {
                $pdo->rollBack();
                handleError('Transaction not found', 404);
            }
            
            $stmt = $pdo->prepare("DELETE FROM first_bank_transactions WHERE id = ?");
            $stmt->execute([$id]);

            if (transactionStatusHoldsFunds($transaction['status'] ?? 'SUCCESSFUL')) {
                $stmt = $pdo->query("SELECT id FROM first_bank_account_settings ORDER BY id DESC LIMIT 1");
                $accountRow = $stmt->fetch();
                $accountId = $accountRow['id'];
                
                $stmt = $pdo->prepare("UPDATE first_bank_account_settings SET balance = balance + ?, updated_at = NOW() WHERE id = ?");
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
