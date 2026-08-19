<?php
/**
 * Polar transfer helpers (OTP intent, CoinGecko fetch, mail). Simulation only.
 */

function polarisNormalizeAmount($amount): string
{
    return number_format(floatval($amount), 2, '.', '');
}

function polarisSanitizeCoinId(string $id): string
{
    $id = strtolower(trim($id));
    if ($id === '' || !preg_match('/^[a-z0-9-]+$/', $id)) {
        return '';
    }
    return $id;
}

function polarisIntentHash(string $transferType, string $destination, $amount): string
{
    $norm = strtolower(trim($transferType)) . '|' . trim($destination) . '|' . polarisNormalizeAmount($amount);
    return hash('sha256', $norm);
}

function polarisParseCryptoAssets($raw): array
{
    if (is_array($raw)) {
        return $raw;
    }
    $decoded = json_decode((string)$raw, true);
    return is_array($decoded) ? $decoded : json_decode(polarisDefaultCryptoAssetsJson(), true);
}

function polarisEnabledCryptoAssets(array $assets): array
{
    $out = [];
    foreach ($assets as $asset) {
        if (!is_array($asset)) {
            continue;
        }
        $enabled = $asset['enabled'] ?? true;
        if ($enabled === false || $enabled === 0 || $enabled === '0') {
            continue;
        }
        $id = trim((string)($asset['id'] ?? ''));
        $symbol = strtoupper(trim((string)($asset['symbol'] ?? '')));
        if ($id === '' || $symbol === '') {
            continue;
        }
        $out[] = [
            'id' => $id,
            'symbol' => $symbol,
            'name' => trim((string)($asset['name'] ?? $symbol)),
            'image' => trim((string)($asset['image'] ?? '')),
            'enabled' => true,
        ];
    }
    return $out;
}

function polarisFindAssetById(array $assets, string $coinId): ?array
{
    $coinId = strtolower(trim($coinId));
    foreach (polarisEnabledCryptoAssets($assets) as $asset) {
        if (strtolower($asset['id']) === $coinId) {
            return $asset;
        }
    }
    return null;
}

function polarisCoinGeckoGet(string $path, array $query = []): ?array
{
    $url = 'https://api.coingecko.com/api/v3' . $path;
    if (!empty($query)) {
        $url .= '?' . http_build_query($query);
    }
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 8);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: application/json',
        'User-Agent: polaris-sim-proxy',
    ]);
    $response = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($status < 200 || $status >= 300 || !$response) {
        return null;
    }
    $decoded = json_decode($response, true);
    return is_array($decoded) ? $decoded : null;
}

function polarisFetchNgnRates(array $coinIds): array
{
    $coinIds = array_values(array_unique(array_filter(array_map('polarisSanitizeCoinId', $coinIds))));
    if (empty($coinIds)) {
        return [];
    }
    $data = polarisCoinGeckoGet('/simple/price', [
        'ids' => implode(',', $coinIds),
        'vs_currencies' => 'ngn',
    ]);
    if (!$data) {
        return [];
    }
    $rates = [];
    foreach ($coinIds as $id) {
        $ngn = $data[$id]['ngn'] ?? null;
        if (is_numeric($ngn) && floatval($ngn) > 0) {
            $rates[$id] = floatval($ngn);
        }
    }
    return $rates;
}

function polarisPurchaseEmails(PDO $pdo): array
{
    $emails = [];
    try {
        $stmt = $pdo->query("SELECT purchase_email FROM license_settings WHERE id = 1 LIMIT 1");
        $row = $stmt ? $stmt->fetch() : false;
        $raw = $row ? (string)($row['purchase_email'] ?? '') : '';
        foreach (preg_split('/[;,]+/', $raw) as $part) {
            $email = trim($part);
            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $emails[] = $email;
            }
        }
    } catch (PDOException $e) {
        // ignore
    }
    return array_values(array_unique($emails));
}

function polarisOtpEmailHtml(string $otp, string $logoSrc): string
{
    $otpEsc = htmlspecialchars($otp, ENT_QUOTES, 'UTF-8');
    $logoEsc = htmlspecialchars($logoSrc, ENT_QUOTES, 'UTF-8');
    $logoBlock = $logoSrc !== ''
        ? '<img src="' . $logoEsc . '" alt="Polaris Bank" style="height:48px;width:auto;display:block;margin:0 auto 16px;" />'
        : '<div style="font-size:22px;font-weight:700;color:#5B2C8A;text-align:center;margin-bottom:16px;">Polaris Bank</div>';

    return '<!DOCTYPE html><html><body style="margin:0;padding:0;background:#f4f4f4;font-family:Arial,Helvetica,sans-serif;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f4f4f4;padding:24px 0;">
<tr><td align="center">
<table role="presentation" width="560" cellspacing="0" cellpadding="0" style="background:#ffffff;border-radius:12px;overflow:hidden;max-width:560px;width:100%;">
<tr><td style="background:#ffffff;padding:20px 24px;border-bottom:4px solid #5B2C8A;">' . $logoBlock . '</td></tr>
<tr><td style="padding:28px 24px 12px;color:#191c1d;">
<p style="margin:0 0 8px;font-size:18px;font-weight:700;color:#5B2C8A;">Transfer Authorization</p>
<p style="margin:0 0 16px;font-size:14px;color:#4A3A5C;line-height:1.5;">Use this one-time code to authorize your simulated Polaris transfer. Do not share it with anyone.</p>
<div style="text-align:center;margin:24px 0;">
<span style="display:inline-block;letter-spacing:8px;font-size:28px;font-weight:700;color:#5B2C8A;background:#F4F4F4;padding:14px 22px;border-radius:8px;">' . $otpEsc . '</span>
</div>
<p style="margin:0 0 8px;font-size:13px;color:#666666;">This code expires in 10 minutes and is valid only for the current transfer attempt.</p>
<p style="margin:0;font-size:12px;color:#999999;">This is a simulated Polaris Bank security email. No real funds or crypto are sent.</p>
</td></tr>
<tr><td style="background:#3D1A5C;color:#ffffff;padding:14px 24px;font-size:11px;text-align:center;">Polaris Bank</td></tr>
</table>
</td></tr></table>
</body></html>';
}

function polarisLogoSrc(): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? '';
    if ($host !== '') {
        return $scheme . '://' . $host . '/api/assets/polaris_logo.png';
    }
    $path = __DIR__ . '/assets/polaris_logo.png';
    if (is_file($path)) {
        return 'data:image/png;base64,' . base64_encode((string)file_get_contents($path));
    }
    return '';
}

function polarisSendHtmlMail(PDO $pdo, array $toEmails, string $subject, string $html): array
{
    require_once __DIR__ . '/email_service.php';
    return emailSendHtml($pdo, $toEmails, $subject, $html, false);
}

function polarisAccountRow(PDO $pdo)
{
    $stmt = $pdo->query("SELECT * FROM polaris_bank_account_settings ORDER BY id DESC LIMIT 1");
    return $stmt ? $stmt->fetch() : false;
}

function polarisIsAdminSession(): bool
{
    if (session_status() === PHP_SESSION_NONE) {
        if (defined('SESSION_NAME')) {
            session_name(SESSION_NAME);
        }
        session_start();
    }
    return isset($_SESSION['admin_id']) && isset($_SESSION['admin_username']);
}
