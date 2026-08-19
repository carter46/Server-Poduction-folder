<?php
/**
 * Direct Firebase Cloud Messaging (HTTP v1) for mobile companion.
 * Native FCM device tokens only — Expo Push tokens are rejected.
 * Credentials: FCM_PROJECT_ID + FCM_SERVICE_ACCOUNT_JSON path in config.php.
 */

require_once __DIR__ . '/mobile_bank_match_helper.php';

function mobilePushLog($message) {
    error_log('[mobile_push] ' . $message);
}

function mobileIsExpoPushToken($token) {
    $token = (string)$token;
    return strpos($token, 'ExponentPushToken[') === 0
        || strpos($token, 'ExpoPushToken[') === 0;
}

function mobileIsNativeFcmToken($token) {
    $token = trim((string)$token);
    if ($token === '' || mobileIsExpoPushToken($token)) {
        return false;
    }
    // FCM registration tokens are long opaque strings (typically 140+ chars)
    return strlen($token) >= 80 && preg_match('/^[A-Za-z0-9_:-]+$/', $token);
}

function mobileIsSuccessfulStatus($status) {
    $status = strtoupper(trim((string)$status));
    return $status === 'SUCCESSFUL' || $status === 'SUCCESS' || $status === '';
}

function mobileFcmIsConfigured() {
    if (!defined('FCM_PROJECT_ID') || trim((string)FCM_PROJECT_ID) === '') {
        return false;
    }
    if (!defined('FCM_SERVICE_ACCOUNT_JSON')) {
        return false;
    }
    $path = FCM_SERVICE_ACCOUNT_JSON;
    return is_string($path) && is_readable($path);
}

function mobileFcmLoadServiceAccount() {
    if (!mobileFcmIsConfigured()) {
        return null;
    }
    $raw = file_get_contents(FCM_SERVICE_ACCOUNT_JSON);
    $json = json_decode($raw, true);
    if (!$json || empty($json['client_email']) || empty($json['private_key'])) {
        mobilePushLog('Invalid service account JSON');
        return null;
    }
    return $json;
}

function mobileFcmBase64UrlEncode($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function mobileFcmGetAccessToken() {
    static $cached = null;
    static $expiresAt = 0;
    if ($cached && time() < $expiresAt - 60) {
        return $cached;
    }

    $sa = mobileFcmLoadServiceAccount();
    if (!$sa) {
        return null;
    }

    $now = time();
    $header = mobileFcmBase64UrlEncode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
    $claim = mobileFcmBase64UrlEncode(json_encode([
        'iss' => $sa['client_email'],
        'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
        'aud' => 'https://oauth2.googleapis.com/token',
        'iat' => $now,
        'exp' => $now + 3600,
    ]));
    $unsigned = $header . '.' . $claim;
    $key = openssl_pkey_get_private($sa['private_key']);
    if (!$key) {
        mobilePushLog('openssl_pkey_get_private failed');
        return null;
    }
    $signature = '';
    if (!openssl_sign($unsigned, $signature, $key, OPENSSL_ALGO_SHA256)) {
        mobilePushLog('openssl_sign failed');
        return null;
    }
    $jwt = $unsigned . '.' . mobileFcmBase64UrlEncode($signature);

    $ch = curl_init('https://oauth2.googleapis.com/token');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
        CURLOPT_POSTFIELDS => http_build_query([
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $jwt,
        ]),
        CURLOPT_TIMEOUT => 15,
    ]);
    $result = curl_exec($ch);
    $errno = curl_errno($ch);
    curl_close($ch);
    if ($errno || !$result) {
        mobilePushLog("OAuth curl error={$errno}");
        return null;
    }
    $decoded = json_decode($result, true);
    if (empty($decoded['access_token'])) {
        mobilePushLog('OAuth token missing: ' . substr((string)$result, 0, 200));
        return null;
    }
    $cached = $decoded['access_token'];
    $expiresAt = $now + intval($decoded['expires_in'] ?? 3600);
    return $cached;
}

function mobileSendFcmHttpV1($deviceToken, $title, $body, array $data) {
    if (mobileIsExpoPushToken($deviceToken)) {
        mobilePushLog('Refusing Expo push token');
        return false;
    }
    if (!mobileIsNativeFcmToken($deviceToken)) {
        mobilePushLog('Refusing non-native FCM token');
        return false;
    }
    if (!mobileFcmIsConfigured()) {
        mobilePushLog('FCM not configured in config.php');
        return false;
    }

    $accessToken = mobileFcmGetAccessToken();
    if (!$accessToken) {
        return false;
    }

    $projectId = trim((string)FCM_PROJECT_ID);
    $url = "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send";

    // All data values must be strings for FCM
    $stringData = [];
    foreach ($data as $k => $v) {
        $stringData[(string)$k] = (string)$v;
    }

    $payload = [
        'message' => [
            'token' => $deviceToken,
            'notification' => [
                'title' => $title,
                'body' => $body,
            ],
            'data' => $stringData,
            'android' => [
                'priority' => 'HIGH',
                'notification' => [
                    'channel_id' => 'credits',
                    'sound' => 'default',
                ],
            ],
        ],
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_TIMEOUT => 15,
    ]);
    $result = curl_exec($ch);
    $errno = curl_errno($ch);
    $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($errno || $result === false) {
        mobilePushLog("FCM v1 curl error={$errno}");
        return false;
    }
    if ($http >= 400) {
        mobilePushLog("FCM v1 HTTP {$http}: " . substr((string)$result, 0, 400));
        return false;
    }
    return true;
}

function mobileNotifyBeneficiaryCredit(PDO $pdo, array $txRow, $sourceTable, $sourceBank) {
    try {
        mobileEnsureSchema($pdo);

        if (!mobileIsSuccessfulStatus($txRow['status'] ?? 'SUCCESSFUL')) {
            return false;
        }

        $benAcct = mobileNormalizeAccountNumber($txRow['beneficiary_account'] ?? '');
        $bankCode = mobileCanonicalBankCode($txRow['beneficiary_bank'] ?? '');
        if (!$benAcct || !$bankCode) {
            return false;
        }

        $stmt = $pdo->prepare("SELECT fcm_token FROM mobile_device_tokens WHERE bank_code = ? AND account_number = ? LIMIT 1");
        $stmt->execute([$bankCode, $benAcct]);
        $device = $stmt->fetch();
        if (!$device || empty($device['fcm_token'])) {
            return false;
        }

        $deviceToken = $device['fcm_token'];
        if (mobileIsExpoPushToken($deviceToken)) {
            mobilePushLog('Stored token is Expo format — remove and re-register native FCM token');
            return false;
        }

        $amount = number_format(floatval($txRow['amount'] ?? 0), 2);
        $currency = $txRow['currency'] ?? 'NGN';
        $sender = $txRow['sender_name'] ?? 'Transfer';
        $reference = $txRow['reference'] ?? '';
        $transactionId = $sourceTable . ':' . intval($txRow['id']);

        $title = "{$currency} {$amount} received";
        $body = "From {$sender}" . ($reference ? " • Ref {$reference}" : '');

        $data = [
            'type' => 'credit',
            'transaction_id' => $transactionId,
            'source_table' => $sourceTable,
            'source_id' => (string)intval($txRow['id']),
            'bank_code' => $bankCode,
            'reference' => (string)$reference,
            'amount' => (string)$txRow['amount'],
            'currency' => (string)$currency,
            'open' => 'receipt',
        ];

        return mobileSendFcmHttpV1($deviceToken, $title, $body, $data);
    } catch (Exception $e) {
        mobilePushLog('Exception: ' . $e->getMessage());
        return false;
    }
}

function mobileSendTestNotification(PDO $pdo) {
    mobileEnsureSchema($pdo);
    if (!mobileFcmIsConfigured()) {
        return ['ok' => false, 'error' => 'Firebase is not configured on the server (config.php).'];
    }
    $stmt = $pdo->query("SELECT fcm_token, bank_code, account_number FROM mobile_device_tokens ORDER BY updated_at DESC LIMIT 1");
    $row = $stmt->fetch();
    if (!$row || empty($row['fcm_token'])) {
        return ['ok' => false, 'error' => 'No registered mobile devices.'];
    }
    if (mobileIsExpoPushToken($row['fcm_token'])) {
        return ['ok' => false, 'error' => 'Latest device token is an Expo push token. Re-register with a native FCM token.'];
    }
    $ok = mobileSendFcmHttpV1(
        $row['fcm_token'],
        'Mobile Companion',
        'Firebase notification test successful.',
        [
            'type' => 'test',
            'open' => 'home',
        ]
    );
    if (!$ok) {
        return ['ok' => false, 'error' => 'FCM send failed. Check server error log.'];
    }
    return [
        'ok' => true,
        'bank_code' => $row['bank_code'],
        'account_number' => $row['account_number'],
    ];
}

function mobileCountRegisteredDevices(PDO $pdo) {
    mobileEnsureSchema($pdo);
    $stmt = $pdo->query("SELECT COUNT(*) AS c FROM mobile_device_tokens");
    $row = $stmt->fetch();
    return intval($row['c'] ?? 0);
}
