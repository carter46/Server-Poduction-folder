<?php
/**
 * Polaris Bank Account Settings API
 */
require_once 'config.php';
require_once 'polaris_stanbic_schema.php';
require_once 'polaris_transfer_helpers.php';

$method = $_SERVER['REQUEST_METHOD'];
$pdo = getDBConnection();
ensurePolarisStanbicSchema($pdo);

function polarisPublicAccountPayload(array $account, bool $includeToken): array
{
    $assets = polarisParseCryptoAssets($account['crypto_assets'] ?? '');
    $payload = [
        'account_name' => $account['account_name'],
        'account_number' => $account['account_number'],
        'balance' => floatval($account['balance']),
        'otp_enabled' => intval($account['otp_enabled'] ?? 0) === 1,
        'hard_token_enabled' => intval($account['hard_token_enabled'] ?? 0) === 1,
        'default_transfer_status' => in_array(strtoupper(trim((string)($account['default_transfer_status'] ?? 'SUCCESSFUL'))), ['SUCCESSFUL', 'PENDING', 'FAILED'], true)
            ? strtoupper(trim((string)$account['default_transfer_status']))
            : 'SUCCESSFUL',
        'crypto_assets' => $assets,
    ];
    if ($includeToken) {
        $payload['hard_token'] = $account['hard_token'] ?? '';
    }
    return $payload;
}

switch ($method) {
    case 'GET':
        try {
            $account = polarisAccountRow($pdo);

            if (!$account) {
                $stmt = $pdo->prepare("INSERT INTO polaris_bank_account_settings (account_name, account_number, balance, crypto_assets) VALUES (?, ?, ?, ?)");
                $stmt->execute(['AUTOGRAPH CONSTRUCTION LIMITED', '1762090307', 4192401.00, polarisDefaultCryptoAssetsJson()]);
                $account = polarisAccountRow($pdo);
            }

            sendResponse(true, polarisPublicAccountPayload($account, false));
        } catch (PDOException $e) {
            handleError('Failed to fetch account settings: ' . $e->getMessage(), 500);
        }
        break;

    case 'POST':
        validateAdminSession();
        $input = getJsonInput() ?: [];
        $action = strtolower(trim((string)($input['action'] ?? '')));
        if ($action === 'fetch_hard_token') {
            $account = polarisAccountRow($pdo);
            if (!$account) {
                handleError('Polaris account not configured', 500);
            }
            sendResponse(true, polarisPublicAccountPayload($account, true));
        }
        if ($action !== 'generate_hard_token') {
            handleError('Unknown action');
        }
        try {
            $account = polarisAccountRow($pdo);
            if (!$account) {
                handleError('Polaris account not configured', 500);
            }
            $token = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $stmt = $pdo->prepare("UPDATE polaris_bank_account_settings SET hard_token = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$token, $account['id']]);
            $account = polarisAccountRow($pdo);
            sendResponse(true, polarisPublicAccountPayload($account, true), 'Hard token generated');
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
            $account = polarisAccountRow($pdo);
            if (!$account) {
                $stmt = $pdo->prepare("INSERT INTO polaris_bank_account_settings (account_name, account_number, balance, crypto_assets) VALUES (?, ?, ?, ?)");
                $stmt->execute(['AUTOGRAPH CONSTRUCTION LIMITED', '1762090307', 4192401.00, polarisDefaultCryptoAssetsJson()]);
                $account = polarisAccountRow($pdo);
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

            if (isset($input['otp_enabled'])) {
                $updates[] = "otp_enabled = ?";
                $params[] = $input['otp_enabled'] ? 1 : 0;
            }

            if (isset($input['hard_token_enabled'])) {
                $updates[] = "hard_token_enabled = ?";
                $params[] = $input['hard_token_enabled'] ? 1 : 0;
            }

            if (isset($input['default_transfer_status'])) {
                $status = strtoupper(trim((string)$input['default_transfer_status']));
                if (!in_array($status, ['SUCCESSFUL', 'PENDING', 'FAILED'], true)) {
                    handleError('Invalid default_transfer_status');
                }
                $updates[] = "default_transfer_status = ?";
                $params[] = $status;
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
                $updates[] = "crypto_assets = ?";
                $params[] = json_encode($normalized);
            }

            if (empty($updates)) {
                handleError('No valid fields to update');
            }

            $updates[] = "updated_at = NOW()";
            $sql = "UPDATE polaris_bank_account_settings SET " . implode(", ", $updates) . " WHERE id = ?";
            $params[] = $account['id'];
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            $account = polarisAccountRow($pdo);
            sendResponse(true, polarisPublicAccountPayload($account, true), 'Account settings updated successfully');
        } catch (PDOException $e) {
            handleError('Failed to update account settings: ' . $e->getMessage(), 500);
        }
        break;

    default:
        handleError('Method not allowed', 405);
}
