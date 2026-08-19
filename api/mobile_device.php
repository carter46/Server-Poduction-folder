<?php
/**
 * Register native FCM device token for the authenticated mobile session.
 * Expo push tokens (ExponentPushToken[...]) are rejected.
 */
require_once 'config.php';
require_once 'mobile_auth_helper.php';
require_once 'mobile_fcm_helper.php';

$pdo = getDBConnection();
mobileEnsureSchema($pdo);

if ($_SERVER['REQUEST_METHOD'] !== 'PUT' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    handleError('Method not allowed', 405);
}

$session = mobileValidateSession($pdo);
$input = getJsonInput() ?: [];
$fcmToken = isset($input['fcm_token']) ? trim((string)$input['fcm_token']) : '';
if ($fcmToken === '') {
    handleError('fcm_token is required');
}

if (mobileIsExpoPushToken($fcmToken)) {
    handleError('Expo push tokens are not accepted. Register a native FCM device token.');
}

if (!mobileIsNativeFcmToken($fcmToken)) {
    handleError('Invalid native FCM registration token.');
}

$stmt = $pdo->prepare("
    INSERT INTO mobile_device_tokens (bank_code, account_number, fcm_token, updated_at)
    VALUES (?, ?, ?, NOW())
    ON DUPLICATE KEY UPDATE fcm_token = VALUES(fcm_token), updated_at = NOW()
");
$stmt->execute([$session['bank_code'], $session['account_number'], $fcmToken]);

sendResponse(true, [
    'bank_code' => $session['bank_code'],
    'account_number' => $session['account_number'],
    'registered' => true,
    'token_type' => 'fcm_native',
], 'Device token registered');
