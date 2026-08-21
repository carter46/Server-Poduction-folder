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
    // Legacy otp/token/outcome columns may still exist in DB; runtime auth uses license_settings only.
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
    unset($includeToken); // Per-bank hard_token value is no longer exposed.
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
            sendResponse(true, polarisPublicAccountPayload($account, false), 'Account settings updated successfully');
        } catch (PDOException $e) {
            handleError('Failed to update account settings: ' . $e->getMessage(), 500);
        }
        break;

    default:
        handleError('Method not allowed', 405);
}
