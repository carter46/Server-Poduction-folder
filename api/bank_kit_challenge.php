<?php
require_once 'config.php';
require_once 'bank_kit.php';
require_once 'dashboard_flow.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    handleError('Method not allowed', 405);
}

$pdo = getDBConnection();
bankKitEnsure($pdo);
$input = getJsonInput() ?: [];
$bank = bankKitResolve(trim((string)($input['bank_code'] ?? '')));
$table = $bank['account_table'];
$action = strtolower(trim((string)($input['action'] ?? 'send')));
$account = bankKitAccountRow($pdo, $bank);
if (!$account) {
    handleError($bank['name'] . ' account not configured', 500);
}

if ($action === 'verify_token') {
    $global = globalTransferSettingsGet($pdo);
    if (empty($global['hard_token_enabled'])) {
        sendResponse(true, ['verified' => true], 'Hard token not required');
    }
    $posted = trim((string)($input['hard_token'] ?? ''));
    $stored = trim((string)($global['hard_token'] ?? ''));
    if ($stored === '' || $posted === '' || !hash_equals($stored, $posted)) {
        handleError('Hard token is incorrect');
    }
    sendResponse(true, ['verified' => true], 'Hard token verified');
}

$global = globalTransferSettingsGet($pdo);

if ($action === 'send_phone') {
    if (empty($global['phone_otp_enabled'])) {
        handleError('Phone OTP is not enabled');
    }
    $pdo->prepare("UPDATE `{$table}` SET phone_otp_verified = 0, updated_at = NOW() WHERE id = ?")->execute([$account['id']]);
    sendResponse(true, ['ok' => true], 'Phone OTP ready');
}

if ($action === 'verify_phone') {
    if (empty($global['phone_otp_enabled'])) {
        sendResponse(true, ['verified' => true], 'Phone OTP not required');
    }
    $otp = preg_replace('/\D/', '', (string)($input['otp'] ?? ''));
    if (!is_string($otp) || strlen($otp) !== 6) {
        handleError('Invalid OTP');
    }
    $pdo->prepare("UPDATE `{$table}` SET phone_otp_verified = 1, updated_at = NOW() WHERE id = ?")->execute([$account['id']]);
    sendResponse(true, ['verified' => true], 'Phone OTP verified');
}

if (empty($global['otp_enabled'])) {
    handleError('OTP is not enabled');
}

$transferType = strtolower(trim((string)($input['transfer_type'] ?? '')));
$destination = trim((string)($input['destination'] ?? ''));
$amount = trim((string)($input['amount'] ?? ''));
if (!in_array($transferType, ['bank', 'crypto'], true) || $destination === '' || !is_numeric($amount) || floatval($amount) <= 0) {
    handleError('Invalid transfer attempt details');
}
$intentHash = polarisIntentHash($transferType, $destination, $amount);

if ($action === 'send') {
    $otp = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $challengeId = bin2hex(random_bytes(16));
    $expires = date('Y-m-d H:i:s', time() + 600);
    $hash = password_hash($otp, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("UPDATE `{$table}` SET otp_hash = ?, otp_expires_at = ?, otp_challenge_id = ?, otp_intent_hash = ?, otp_verified = 0, phone_otp_verified = 0, updated_at = NOW() WHERE id = ?");
    $stmt->execute([$hash, $expires, $challengeId, $intentHash, $account['id']]);

    $clearOtp = function () use ($pdo, $table, $account) {
        $pdo->prepare("UPDATE `{$table}` SET otp_hash = NULL, otp_expires_at = NULL, otp_challenge_id = NULL, otp_intent_hash = NULL, otp_verified = 0, updated_at = NOW() WHERE id = ?")->execute([$account['id']]);
    };

    $emails = polarisPurchaseEmails($pdo);
    if (empty($emails)) {
        $clearOtp();
        handleError('Purchase email is not configured');
    }
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
    $html = bankKitOtpEmailHtml($bank, $otp, bankKitLogoSrc($bank));
    $sent = polarisSendHtmlMail($pdo, $emails, $bank['name'] . ' Transfer Authorization', $html, $challengeId);
    $via = $sent['sent_via'] ?? null;
    if (empty($sent['ok']) || ($via !== 'phpmailer' && $via !== 'brevo')) {
        $clearOtp();
        $msg = trim((string)($sent['message'] ?? ''));
        handleError($msg !== '' ? $msg : 'Could not send OTP email. Check email configuration.');
    }
    sendResponse(true, [
        'challenge_id' => $challengeId,
        'expires_in' => 600,
        'sent_via' => $via,
        'phpmailer_status' => $sent['phpmailer_status'] ?? '',
        'phpmailer_error' => $sent['phpmailer_error'] ?? '',
        'brevo_status' => $sent['brevo_status'] ?? '',
        'brevo_error' => $sent['brevo_error'] ?? '',
    ], 'OTP sent');
}

if ($action === 'verify') {
    $challengeId = trim((string)($input['challenge_id'] ?? ''));
    $otp = preg_replace('/\D/', '', (string)($input['otp'] ?? ''));
    if ($challengeId === '' || strlen($otp) !== 6) {
        handleError('Invalid OTP');
    }
    $stmt = $pdo->prepare("SELECT * FROM `{$table}` WHERE id = ? LIMIT 1");
    $stmt->execute([$account['id']]);
    $row = $stmt->fetch();
    if (!$row) {
        handleError($bank['name'] . ' account not configured', 500);
    }
    if ((string)($row['otp_challenge_id'] ?? '') === '' || !hash_equals((string)$row['otp_challenge_id'], $challengeId) || !hash_equals((string)($row['otp_intent_hash'] ?? ''), $intentHash)) {
        handleError('OTP does not match this transfer attempt');
    }
    if ((string)($row['otp_expires_at'] ?? '') === '' || strtotime((string)$row['otp_expires_at']) < time()) {
        handleError('OTP has expired');
    }
    if ((string)($row['otp_hash'] ?? '') === '' || !password_verify($otp, (string)$row['otp_hash'])) {
        handleError('Incorrect OTP');
    }
    $pdo->prepare("UPDATE `{$table}` SET otp_verified = 1, updated_at = NOW() WHERE id = ?")->execute([$account['id']]);
    sendResponse(true, ['challenge_id' => $challengeId, 'verified' => true], 'OTP verified');
}

handleError('Unknown action');
