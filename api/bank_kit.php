<?php
/**
 * Shared Polar-style transfer kit: schema, OTP mail branding, create txn.
 * Mail delivery is platform-wide (email_service + purchase_email).
 */
require_once __DIR__ . '/polaris_transfer_helpers.php';
require_once __DIR__ . '/polaris_stanbic_schema.php';

function bankKitRegistry(): array
{
    return [
        '076' => [
            'slug' => 'polaris',
            'name' => 'Polaris Bank',
            'account_table' => 'polaris_bank_account_settings',
            'tx_table' => 'polaris_bank_transactions',
            'fcm' => 'polaris',
            'logo_file' => 'polaris_logo.png',
            'primary' => '#3D1A5C',
            'accent' => '#5B2C8A',
            'prefix' => 'POLXFER',
        ],
        '044' => [
            'slug' => 'access',
            'name' => 'Access Bank',
            'account_table' => 'access_bank_account_settings',
            'tx_table' => 'access_bank_transactions',
            'fcm' => 'access',
            'logo_file' => 'access_bank.jpg',
            'primary' => '#003D79',
            'accent' => '#F58220',
            'prefix' => 'ACCXFER',
        ],
        '011' => [
            'slug' => 'first',
            'name' => 'First Bank',
            'account_table' => 'first_bank_account_settings',
            'tx_table' => 'first_bank_transactions',
            'fcm' => 'first',
            'logo_file' => 'first_bank.jpg',
            'primary' => '#0A1F44',
            'accent' => '#0A1F44',
            'prefix' => 'FBNXFER',
        ],
        '057' => [
            'slug' => 'zenith',
            'name' => 'Zenith Bank',
            'account_table' => 'zenith_bank_account_settings',
            'tx_table' => 'zenith_bank_transactions',
            'fcm' => 'zenith',
            'logo_file' => 'zenith_bank.png',
            'primary' => '#E2010F',
            'accent' => '#E2010F',
            'prefix' => 'ZENXFER',
        ],
        '033' => [
            'slug' => 'uba',
            'name' => 'UBA',
            'account_table' => 'uba_account_settings',
            'tx_table' => 'uba_transactions',
            'fcm' => 'uba',
            'logo_file' => 'uba_logo.png',
            'primary' => '#d61b1b',
            'accent' => '#d61b1b',
            'prefix' => 'UBAXFER',
        ],
        '221' => [
            'slug' => 'stanbic',
            'name' => 'Stanbic IBTC Bank',
            'account_table' => 'stanbic_bank_account_settings',
            'tx_table' => 'stanbic_bank_transactions',
            'fcm' => 'stanbic',
            'logo_file' => 'stanbic_ibtc.png',
            'primary' => '#001F54',
            'accent' => '#003087',
            'prefix' => 'STBXFER',
        ],
        '035' => [
            'slug' => 'wema',
            'name' => 'Wema Bank',
            'account_table' => 'wema_bank_account_settings',
            'tx_table' => 'wema_bank_transactions',
            'fcm' => 'wema',
            'logo_file' => 'wema_alats.png',
            'primary' => '#3b3b3b',
            'accent' => '#7f0025',
            'prefix' => 'WEMXFER',
        ],
        '070' => [
            'slug' => 'fidelity',
            'name' => 'Fidelity Bank',
            'account_table' => 'fidelity_bank_account_settings',
            'tx_table' => 'fidelity_bank_transactions',
            'fcm' => 'fidelity',
            'logo_file' => 'fidelity_bank.jpg',
            'primary' => '#0B2A5B',
            'accent' => '#2E8B32',
            'prefix' => 'FIDXFER',
        ],
        '058' => [
            'slug' => 'gtbank',
            'name' => 'Guaranty Trust Bank',
            'account_table' => 'gtbank_account_settings',
            'tx_table' => 'gtbank_transactions',
            'fcm' => 'gtbank',
            'logo_file' => 'gt_bank.jpg',
            'primary' => '#FF6200',
            'accent' => '#E35600',
            'prefix' => 'GTBXFER',
        ],
        '030' => [
            'slug' => 'heritage',
            'name' => 'Heritage Bank',
            'account_table' => 'heritage_bank_account_settings',
            'tx_table' => 'heritage_bank_transactions',
            'fcm' => 'heritage',
            'logo_file' => 'heritage_bank.jpeg',
            'primary' => '#006B3F',
            'accent' => '#008C52',
            'prefix' => 'HERXFER',
        ],
        '301' => [
            'slug' => 'jaiz',
            'name' => 'Jaiz Bank',
            'account_table' => 'jaiz_bank_account_settings',
            'tx_table' => 'jaiz_bank_transactions',
            'fcm' => 'jaiz',
            'logo_file' => 'jaiz_bank.jpg',
            'primary' => '#006B3F',
            'accent' => '#8CC63F',
            'prefix' => 'JAZXFER',
        ],
        '082' => [
            'slug' => 'keystone',
            'name' => 'Keystone Bank',
            'account_table' => 'keystone_bank_account_settings',
            'tx_table' => 'keystone_bank_transactions',
            'fcm' => 'keystone',
            'logo_file' => 'keystone_bank.jpeg',
            'primary' => '#1B4F72',
            'accent' => '#2874A6',
            'prefix' => 'KEYXFER',
        ],
        '232' => [
            'slug' => 'sterling',
            'name' => 'Sterling Bank',
            'account_table' => 'sterling_bank_account_settings',
            'tx_table' => 'sterling_bank_transactions',
            'fcm' => 'sterling',
            'logo_file' => 'sterling_bank.jpg',
            'primary' => '#E31C23',
            'accent' => '#B31218',
            'prefix' => 'STLXFER',
        ],
        '032' => [
            'slug' => 'union',
            'name' => 'Union Bank',
            'account_table' => 'union_bank_account_settings',
            'tx_table' => 'union_bank_transactions',
            'fcm' => 'union',
            'logo_file' => 'union_bank.png',
            'primary' => '#008751',
            'accent' => '#00A651',
            'prefix' => 'UNIXFER',
        ],
        '215' => [
            'slug' => 'unity',
            'name' => 'Unity Bank',
            'account_table' => 'unity_bank_account_settings',
            'tx_table' => 'unity_bank_transactions',
            'fcm' => 'unity',
            'logo_file' => 'unity_bank.jpg',
            'primary' => '#007A3D',
            'accent' => '#009B4C',
            'prefix' => 'UTYXFER',
        ],
        '50211' => [
            'slug' => 'kuda',
            'name' => 'Kuda Bank',
            'account_table' => 'kuda_bank_account_settings',
            'tx_table' => 'kuda_bank_transactions',
            'fcm' => 'kuda',
            'logo_file' => 'kuda_bank.jpeg',
            'primary' => '#40196D',
            'accent' => '#5B2C8A',
            'prefix' => 'KUDXFER',
        ],
        '999992' => [
            'slug' => 'opay',
            'name' => 'OPay',
            'account_table' => 'opay_account_settings',
            'tx_table' => 'opay_transactions',
            'fcm' => 'opay',
            'logo_file' => 'opay.jpeg',
            'primary' => '#1DCF9A',
            'accent' => '#00B876',
            'prefix' => 'OPYXFER',
        ],
        '090405' => [
            'slug' => 'moniepoint',
            'name' => 'Moniepoint',
            'account_table' => 'moniepoint_account_settings',
            'tx_table' => 'moniepoint_transactions',
            'fcm' => 'moniepoint',
            'logo_file' => 'moniepoint.jpeg',
            'primary' => '#1A1F71',
            'accent' => '#0066F5',
            'prefix' => 'MPTXFER',
        ],
        '100033' => [
            'slug' => 'palmpay',
            'name' => 'PalmPay',
            'account_table' => 'palmpay_account_settings',
            'tx_table' => 'palmpay_transactions',
            'fcm' => 'palmpay',
            'logo_file' => 'palmpay.jpg',
            'primary' => '#6C2BD9',
            'accent' => '#8B5CF6',
            'prefix' => 'PPYXFER',
        ],
    ];
}

function bankKitResolve(?string $code): array
{
    $code = trim((string)$code);
    $reg = bankKitRegistry();
    if (!isset($reg[$code])) {
        handleError('Unknown bank', 400);
    }
    return $reg[$code] + ['code' => $code];
}

function bankKitEnsure(PDO $pdo): void
{
    ensurePolarisStanbicSchema($pdo);

    $seed = $pdo->prepare("INSERT INTO `bank_status` (`bank_code`, `bank_name`, `status`) VALUES (?, ?, 'full_logs') ON DUPLICATE KEY UPDATE `bank_name` = VALUES(`bank_name`)");

    foreach (bankKitRegistry() as $code => $bank) {
        $acc = $bank['account_table'];
        $tx = $bank['tx_table'];
        $defaultAcct = substr(preg_replace('/\D/', '', $code) . '2090307', 0, 20);

        $pdo->exec("CREATE TABLE IF NOT EXISTS `{$acc}` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `account_name` varchar(255) NOT NULL DEFAULT 'AUTOGRAPH CONSTRUCTION LIMITED',
            `account_number` varchar(50) NOT NULL DEFAULT '{$defaultAcct}',
            `balance` decimal(15,2) NOT NULL DEFAULT 4192401.00,
            `created_at` timestamp NULL DEFAULT current_timestamp(),
            `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS `{$tx}` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `reference` varchar(50) NOT NULL,
            `session_id` varchar(64) DEFAULT NULL,
            `reference_id` varchar(64) DEFAULT NULL,
            `amount` decimal(15,2) NOT NULL,
            `currency` varchar(10) NOT NULL DEFAULT 'NGN',
            `beneficiary_name` varchar(255) NOT NULL,
            `beneficiary_bank` varchar(255) NOT NULL,
            `beneficiary_account` varchar(50) NOT NULL,
            `sender_account` varchar(50) NOT NULL,
            `sender_name` varchar(255) NOT NULL,
            `purpose` varchar(500) DEFAULT NULL,
            `status` varchar(50) NOT NULL DEFAULT 'SUCCESSFUL',
            `transaction_date` timestamp NULL DEFAULT current_timestamp(),
            `created_at` timestamp NULL DEFAULT current_timestamp(),
            PRIMARY KEY (`id`),
            UNIQUE KEY `reference` (`reference`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        try {
            $seed->execute([$code, $bank['name']]);
        } catch (PDOException $e) {
        }

        polarisAddColumnIfMissing($pdo, $acc, 'otp_enabled', 'otp_enabled TINYINT(1) NOT NULL DEFAULT 0');
        polarisAddColumnIfMissing($pdo, $acc, 'hard_token_enabled', 'hard_token_enabled TINYINT(1) NOT NULL DEFAULT 0');
        polarisAddColumnIfMissing($pdo, $acc, 'hard_token', 'hard_token VARCHAR(64) DEFAULT NULL');
        polarisAddColumnIfMissing($pdo, $acc, 'otp_hash', 'otp_hash VARCHAR(255) DEFAULT NULL');
        polarisAddColumnIfMissing($pdo, $acc, 'otp_expires_at', 'otp_expires_at DATETIME DEFAULT NULL');
        polarisAddColumnIfMissing($pdo, $acc, 'otp_challenge_id', 'otp_challenge_id VARCHAR(64) DEFAULT NULL');
        polarisAddColumnIfMissing($pdo, $acc, 'otp_intent_hash', 'otp_intent_hash VARCHAR(64) DEFAULT NULL');
        polarisAddColumnIfMissing($pdo, $acc, 'otp_verified', 'otp_verified TINYINT(1) NOT NULL DEFAULT 0');
        polarisAddColumnIfMissing($pdo, $acc, 'phone_otp_verified', 'phone_otp_verified TINYINT(1) NOT NULL DEFAULT 0');
        polarisAddColumnIfMissing($pdo, $acc, 'crypto_assets', 'crypto_assets TEXT DEFAULT NULL');
        polarisAddColumnIfMissing($pdo, $acc, 'default_transfer_status', "default_transfer_status ENUM('SUCCESSFUL','PENDING','FAILED') NOT NULL DEFAULT 'SUCCESSFUL'");
        polarisAddColumnIfMissing($pdo, $tx, 'transfer_type', "transfer_type VARCHAR(20) NOT NULL DEFAULT 'bank'");
        polarisAddColumnIfMissing($pdo, $tx, 'crypto_symbol', 'crypto_symbol VARCHAR(20) DEFAULT NULL');
        polarisAddColumnIfMissing($pdo, $tx, 'crypto_amount', 'crypto_amount DECIMAL(24,12) DEFAULT NULL');
        polarisAddColumnIfMissing($pdo, $tx, 'crypto_rate_ngn', 'crypto_rate_ngn DECIMAL(20,8) DEFAULT NULL');
        polarisAddColumnIfMissing($pdo, $tx, 'wallet_address', 'wallet_address VARCHAR(255) DEFAULT NULL');
        try {
            $stmt = $pdo->query("SELECT id, crypto_assets FROM `{$acc}` ORDER BY id DESC LIMIT 1");
            $row = $stmt ? $stmt->fetch() : false;
            if ($row && (empty($row['crypto_assets']) || trim((string)$row['crypto_assets']) === '')) {
                $upd = $pdo->prepare("UPDATE `{$acc}` SET crypto_assets = ? WHERE id = ?");
                $upd->execute([polarisDefaultCryptoAssetsJson(), $row['id']]);
            }
        } catch (PDOException $e) {
        }
    }
}

function bankKitAccountRow(PDO $pdo, array $bank)
{
    $table = $bank['account_table'];
    $stmt = $pdo->query("SELECT * FROM `{$table}` ORDER BY id DESC LIMIT 1");
    return $stmt ? $stmt->fetch() : false;
}

function bankKitPublicPayload(array $account, bool $includeToken): array
{
    $assets = polarisParseCryptoAssets($account['crypto_assets'] ?? '');
    // Legacy otp/token/outcome columns may still exist; runtime auth uses license_settings only.
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

function bankKitLogoSrc(array $bank): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? '';
    $file = $bank['logo_file'];
    if ($host !== '') {
        return $scheme . '://' . $host . '/api/assets/' . $file;
    }
    $path = __DIR__ . '/assets/' . $file;
    if (is_file($path)) {
        $ext = strtolower(substr($file, strrpos($file, '.') !== false ? strrpos($file, '.') : 0));
        $mime = $ext === '.png' ? 'image/png' : 'image/jpeg';
        return 'data:' . $mime . ';base64,' . base64_encode((string)file_get_contents($path));
    }
    return '';
}

function bankKitOtpEmailHtml(array $bank, string $otp, string $logoSrc): string
{
    $otpEsc = htmlspecialchars($otp, ENT_QUOTES, 'UTF-8');
    $name = htmlspecialchars($bank['name'], ENT_QUOTES, 'UTF-8');
    $accent = htmlspecialchars($bank['accent'], ENT_QUOTES, 'UTF-8');
    $primary = htmlspecialchars($bank['primary'], ENT_QUOTES, 'UTF-8');
    $logoEsc = htmlspecialchars($logoSrc, ENT_QUOTES, 'UTF-8');
    $logoBlock = $logoSrc !== ''
        ? '<img src="' . $logoEsc . '" alt="' . $name . '" style="height:48px;width:auto;display:block;margin:0 auto 16px;" />'
        : '<div style="font-size:22px;font-weight:700;color:' . $accent . ';text-align:center;margin-bottom:16px;">' . $name . '</div>';

    return '<!DOCTYPE html><html><body style="margin:0;padding:0;background:#f4f4f4;font-family:Arial,Helvetica,sans-serif;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f4f4f4;padding:24px 0;">
<tr><td align="center">
<table role="presentation" width="560" cellspacing="0" cellpadding="0" style="background:#ffffff;border-radius:12px;overflow:hidden;max-width:560px;width:100%;">
<tr><td style="background:#ffffff;padding:20px 24px;border-bottom:4px solid ' . $accent . ';">' . $logoBlock . '</td></tr>
<tr><td style="padding:28px 24px 12px;color:#191c1d;">
<p style="margin:0 0 8px;font-size:18px;font-weight:700;color:' . $accent . ';">Transfer Authorization</p>
<p style="margin:0 0 16px;font-size:14px;color:#4A3A5C;line-height:1.5;">Use this one-time code to authorize your simulated ' . $name . ' transfer. Do not share it with anyone.</p>
<div style="text-align:center;margin:24px 0;">
<span style="display:inline-block;letter-spacing:8px;font-size:28px;font-weight:700;color:' . $accent . ';background:#F4F4F4;padding:14px 22px;border-radius:8px;">' . $otpEsc . '</span>
</div>
<p style="margin:0 0 8px;font-size:13px;color:#666666;">This code expires in 10 minutes and is valid only for the current transfer attempt.</p>
<p style="margin:0;font-size:12px;color:#999999;">This is a simulated ' . $name . ' security email. No real funds or crypto are sent.</p>
</td></tr>
<tr><td style="background:' . $primary . ';color:#ffffff;padding:14px 24px;font-size:11px;text-align:center;">' . $name . '</td></tr>
</table>
</td></tr></table>
</body></html>';
}

function bankKitReadCode(): string
{
    $input = getJsonInput() ?: [];
    $code = trim((string)($input['bank_code'] ?? $_GET['bank_code'] ?? $_GET['code'] ?? ''));
    return $code;
}
