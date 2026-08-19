<?php
require_once 'config.php';
require_once 'bank_kit.php';

$method = $_SERVER['REQUEST_METHOD'];
$pdo = getDBConnection();
bankKitEnsure($pdo);
$bank = bankKitResolve(bankKitReadCode());
$table = $bank['account_table'];

function bankKitNeedAccount(PDO $pdo, array $bank)
{
    $row = bankKitAccountRow($pdo, $bank);
    if ($row) {
        return $row;
    }
    $stmt = $pdo->prepare("INSERT INTO `{$bank['account_table']}` (account_name, account_number, balance, crypto_assets) VALUES (?, ?, ?, ?)");
    $stmt->execute(['AUTOGRAPH CONSTRUCTION LIMITED', $bank['code'] . '2090307', 4192401.00, polarisDefaultCryptoAssetsJson()]);
    return bankKitAccountRow($pdo, $bank);
}

switch ($method) {
    case 'GET':
        try {
            $account = bankKitNeedAccount($pdo, $bank);
            sendResponse(true, bankKitPublicPayload($account, false));
        } catch (PDOException $e) {
            handleError('Failed to fetch account settings: ' . $e->getMessage(), 500);
        }
        break;
    case 'POST':
        validateAdminSession();
        $input = getJsonInput() ?: [];
        $action = strtolower(trim((string)($input['action'] ?? '')));
        $account = bankKitNeedAccount($pdo, $bank);
        if ($action === 'fetch_hard_token') {
            sendResponse(true, bankKitPublicPayload($account, true));
        }
        if ($action !== 'generate_hard_token') {
            handleError('Unknown action');
        }
        try {
            $token = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $stmt = $pdo->prepare("UPDATE `{$table}` SET hard_token = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$token, $account['id']]);
            sendResponse(true, bankKitPublicPayload(bankKitAccountRow($pdo, $bank), true), 'Hard token generated');
        } catch (PDOException $e) {
            handleError('Failed to generate hard token: ' . $e->getMessage(), 500);
        }
        break;
    case 'PUT':
        validateAdminSession();
        $input = getJsonInput();
        if (empty($input)) {
            handleError('No update data provided');
        }
        try {
            $account = bankKitNeedAccount($pdo, $bank);
            $updates = [];
            $params = [];
            if (isset($input['account_name'])) {
                $updates[] = 'account_name = ?';
                $params[] = $input['account_name'];
            }
            if (isset($input['account_number'])) {
                $updates[] = 'account_number = ?';
                $params[] = $input['account_number'];
            }
            if (isset($input['balance'])) {
                $updates[] = 'balance = ?';
                $params[] = floatval($input['balance']);
            }
            if (isset($input['otp_enabled'])) {
                $updates[] = 'otp_enabled = ?';
                $params[] = $input['otp_enabled'] ? 1 : 0;
            }
            if (isset($input['hard_token_enabled'])) {
                $updates[] = 'hard_token_enabled = ?';
                $params[] = $input['hard_token_enabled'] ? 1 : 0;
            }
            if (isset($input['crypto_assets'])) {
                if (!is_array($input['crypto_assets'])) {
                    handleError('crypto_assets must be a list');
                }
                $normalized = [];
                foreach ($input['crypto_assets'] as $asset) {
                    if (!is_array($asset)) {
                        continue;
                    }
                    $id = polarisSanitizeCoinId((string)($asset['id'] ?? ''));
                    $symbol = strtoupper(trim((string)($asset['symbol'] ?? '')));
                    if ($id === '' || $symbol === '') {
                        continue;
                    }
                    $normalized[] = [
                        'id' => $id,
                        'symbol' => $symbol,
                        'name' => trim((string)($asset['name'] ?? $symbol)),
                        'image' => trim((string)($asset['image'] ?? '')),
                        'enabled' => !empty($asset['enabled']),
                    ];
                }
                $updates[] = 'crypto_assets = ?';
                $params[] = json_encode($normalized);
            }
            if (empty($updates)) {
                handleError('No valid fields to update');
            }
            $updates[] = 'updated_at = NOW()';
            $params[] = $account['id'];
            $sql = "UPDATE `{$table}` SET " . implode(', ', $updates) . ' WHERE id = ?';
            $pdo->prepare($sql)->execute($params);
            sendResponse(true, bankKitPublicPayload(bankKitAccountRow($pdo, $bank), true), 'Account settings updated successfully');
        } catch (PDOException $e) {
            handleError('Failed to update account settings: ' . $e->getMessage(), 500);
        }
        break;
    default:
        handleError('Method not allowed', 405);
}
