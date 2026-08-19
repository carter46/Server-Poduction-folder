<?php
/**
 * Stanbic IBTC Bank Account Settings API
 */
require_once 'config.php';

$method = $_SERVER['REQUEST_METHOD'];
$pdo = getDBConnection();

switch ($method) {
    case 'GET':
        try {
            $stmt = $pdo->query("SELECT account_name, account_number, balance FROM stanbic_bank_account_settings ORDER BY id DESC LIMIT 1");
            $account = $stmt->fetch();

            if (!$account) {
                $stmt = $pdo->prepare("INSERT INTO stanbic_bank_account_settings (account_name, account_number, balance) VALUES (?, ?, ?)");
                $stmt->execute(['AUTOGRAPH CONSTRUCTION LIMITED', '2212090307', 4192401.00]);

                $account = [
                    'account_name' => 'AUTOGRAPH CONSTRUCTION LIMITED',
                    'account_number' => '2212090307',
                    'balance' => 4192401.00
                ];
            }

            if (isset($account['balance'])) {
                $account['balance'] = floatval($account['balance']);
            }

            sendResponse(true, $account);
        } catch (PDOException $e) {
            handleError('Failed to fetch account settings: ' . $e->getMessage(), 500);
        }
        break;

    case 'PUT':
        validateAdminSession();

        $input = getJsonInput();

        if (empty($input)) {
            handleError('No update data provided');
        }

        try {
            $stmt = $pdo->query("SELECT id FROM stanbic_bank_account_settings ORDER BY id DESC LIMIT 1");
            $existing = $stmt->fetch();

            if (!$existing) {
                $stmt = $pdo->prepare("INSERT INTO stanbic_bank_account_settings (account_name, account_number, balance) VALUES (?, ?, ?)");
                $stmt->execute(['AUTOGRAPH CONSTRUCTION LIMITED', '2212090307', 4192401.00]);
            }

            $updates = [];
            $params = [];

            if (isset($input['account_name'])) {
                $updates[] = "account_name = ?";
                $params[] = $input['account_name'];
            }

            if (isset($input['account_number'])) {
                $updates[] = "account_number = ?";
                $params[] = $input['account_number'];
            }

            if (isset($input['balance'])) {
                $updates[] = "balance = ?";
                $params[] = floatval($input['balance']);
            }

            if (empty($updates)) {
                handleError('No valid fields to update');
            }

            $updates[] = "updated_at = NOW()";

            $stmt = $pdo->query("SELECT id FROM stanbic_bank_account_settings ORDER BY id DESC LIMIT 1");
            $accountRow = $stmt->fetch();
            $accountId = $accountRow['id'];

            $sql = "UPDATE stanbic_bank_account_settings SET " . implode(", ", $updates) . " WHERE id = ?";
            $params[] = $accountId;
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            $stmt = $pdo->query("SELECT account_name, account_number, balance FROM stanbic_bank_account_settings ORDER BY id DESC LIMIT 1");
            $account = $stmt->fetch();

            if (isset($account['balance'])) {
                $account['balance'] = floatval($account['balance']);
            }

            sendResponse(true, $account, 'Account settings updated successfully');
        } catch (PDOException $e) {
            handleError('Failed to update account settings: ' . $e->getMessage(), 500);
        }
        break;

    default:
        handleError('Method not allowed', 405);
}
