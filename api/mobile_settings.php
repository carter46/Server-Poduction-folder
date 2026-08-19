<?php
/**
 * Admin: mobile shared password + FCM ops status (no credential editing).
 */
require_once 'config.php';
require_once 'mobile_auth_helper.php';
require_once 'mobile_fcm_helper.php';

$pdo = getDBConnection();
mobileEnsureSchema($pdo);
$method = $_SERVER['REQUEST_METHOD'];

function mobileApkMetaPublic() {
    $dir = defined('MOBILE_APK_PRIVATE_DIR') ? MOBILE_APK_PRIVATE_DIR : (__DIR__ . '/private_mobile');
    $metaFile = $dir . '/' . (defined('MOBILE_APK_META_FILENAME') ? MOBILE_APK_META_FILENAME : 'apk-meta.json');
    $apkFile = $dir . '/' . (defined('MOBILE_APK_FILENAME') ? MOBILE_APK_FILENAME : 'banking-companion.apk');
    $available = is_readable($apkFile) && filesize($apkFile) > 10000;
    $meta = [
        'available' => $available,
        'version' => null,
        'built_at' => null,
        'file_size' => $available ? filesize($apkFile) : null,
    ];
    if (is_readable($metaFile)) {
        $decoded = json_decode(file_get_contents($metaFile), true);
        if (is_array($decoded)) {
            $meta['version'] = $decoded['version'] ?? null;
            $meta['built_at'] = $decoded['built_at'] ?? null;
            if (!empty($decoded['file_size'])) {
                $meta['file_size'] = intval($decoded['file_size']);
            }
        }
    }
    return $meta;
}

if ($method === 'GET') {
    validateAdminSession();
    $hash = mobileGetSharedPasswordHash($pdo);
    $action = isset($_GET['action']) ? trim((string)$_GET['action']) : '';

    if ($action === 'apk_meta') {
        sendResponse(true, mobileApkMetaPublic(), 'OK');
    }

    sendResponse(true, [
        'has_password' => !empty($hash),
        'firebase_connected' => mobileFcmIsConfigured(),
        'registered_devices' => mobileCountRegisteredDevices($pdo),
        'apk' => mobileApkMetaPublic(),
        'updated_at' => null,
        'balance_rule' => 'Mobile balance = SUM(SUCCESSFUL beneficiary transfers) for bank+account. No recipient wallet on web.',
        'login_rule' => 'Account number must exist as beneficiary_account under selected bank. Shared password set here. Changing password invalidates all mobile sessions.',
        'push_rule' => 'Native FCM tokens only. Server uses FCM HTTP v1 with service-account path from config.php. Notify only on SUCCESSFUL credits.',
    ], 'OK');
}

if ($method === 'PUT' || $method === 'POST') {
    validateAdminSession();
    $input = getJsonInput() ?: [];
    $action = isset($input['action']) ? trim((string)$input['action']) : '';

    if ($action === 'test_notification') {
        $result = mobileSendTestNotification($pdo);
        if (empty($result['ok'])) {
            handleError($result['error'] ?? 'Test notification failed');
        }
        sendResponse(true, $result, 'Test notification sent');
    }

    if (isset($input['password']) && trim((string)$input['password']) !== '') {
        $password = trim((string)$input['password']);
        if (strlen($password) < 4) {
            handleError('Password must be at least 4 characters');
        }
        mobileSetSharedPassword($pdo, $password);
        $hash = mobileGetSharedPasswordHash($pdo);
        sendResponse(true, [
            'has_password' => !empty($hash),
            'firebase_connected' => mobileFcmIsConfigured(),
            'registered_devices' => mobileCountRegisteredDevices($pdo),
        ], 'Mobile password updated; all sessions invalidated');
    }

    handleError('Nothing to update. Send password or action=test_notification.');
}

handleError('Method not allowed', 405);
