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
        if ($action === 'fetch_hard_token' || $action === 'generate_hard_token') {
            handleError('Per-bank hard tokens are retired. Manage Hard Token in Global Settings (license_settings).', 410);
        }
        handleError('Unknown action');
        break;
    case 'PUT':
        validateAdminSession();
        $input = getJsonInput();
        if (empty($input)) {
            handleError('No update data provided');
        }
        if (isset($input['otp_enabled']) || isset($input['hard_token_enabled']) || isset($input['default_transfer_status']) || array_key_exists('hard_token', $input)) {
            handleError('Per-bank OTP, Hard Token, and default transfer status are retired. Use Global Settings (license_settings).', 410);
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
            sendResponse(true, bankKitPublicPayload(bankKitAccountRow($pdo, $bank), false), 'Account settings updated successfully');
        } catch (PDOException $e) {
            handleError('Failed to update account settings: ' . $e->getMessage(), 500);
        }
        break;
    default:
        handleError('Method not allowed', 405);
}
