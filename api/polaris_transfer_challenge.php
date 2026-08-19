<?php
/**
 * Polar transfer OTP challenge. Bound to a specific transfer attempt.
 */
require_once 'config.php';
require_once 'polaris_stanbic_schema.php';
require_once 'polaris_transfer_helpers.php';

$method = $_SERVER['REQUEST_METHOD'];
if ($method !== 'POST') {
    handleError('Method not allowed', 405);
}

$pdo = getDBConnection();
ensurePolarisStanbicSchema($pdo);

$input = getJsonInput() ?: [];
$action = strtolower(trim((string)($input['action'] ?? 'send')));

$account = polarisAccountRow($pdo);
if (!$account) {
    handleError('Polaris account not configured', 500);
}

if ($action === 'verify_token') {
    $tokenEnabled = intval($account['hard_token_enabled'] ?? 0) === 1;
    if (!$tokenEnabled) {
        sendResponse(true, ['verified' => true], 'Hard token not required');
    }
    $posted = trim((string)($input['hard_token'] ?? ''));
    $stored = trim((string)($account['hard_token'] ?? ''));
    if ($stored === '' || $posted === '' || !hash_equals($stored, $posted)) {
        handleError('Hard token is incorrect');
    }
    sendResponse(true, ['verified' => true], 'Hard token verified');
}

$otpEnabled = intval($account['otp_enabled'] ?? 0) === 1;
if (!$otpEnabled) {
    handleError('OTP is not enabled for this account');
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

    $stmt = $pdo->prepare(
        "UPDATE polaris_bank_account_settings
         SET otp_hash = ?, otp_expires_at = ?, otp_challenge_id = ?, otp_intent_hash = ?, otp_verified = 0, updated_at = NOW()
         WHERE id = ?"
    );
    $stmt->execute([$hash, $expires, $challengeId, $intentHash, $account['id']]);

    $emails = polarisPurchaseEmails($pdo);
    if (empty($emails)) {
        handleError('Purchase email is not configured');
    }
    $html = polarisOtpEmailHtml($otp, polarisLogoSrc());
    $sent = polarisSendHtmlMail($pdo, $emails, 'Polaris Transfer Authorization', $html);
    if (empty($sent['ok'])) {
        $msg = trim((string)($sent['message'] ?? ''));
        handleError($msg !== '' ? $msg : 'Could not send OTP email. Check email configuration.');
    }

    sendResponse(true, [
        'challenge_id' => $challengeId,
        'expires_in' => 600,
    ], 'OTP sent');
}

if ($action === 'verify') {
    $challengeId = trim((string)($input['challenge_id'] ?? ''));
    $otp = preg_replace('/\D/', '', (string)($input['otp'] ?? ''));
    if ($challengeId === '' || strlen($otp) !== 6) {
        handleError('Invalid OTP');
    }

    $stmt = $pdo->prepare("SELECT * FROM polaris_bank_account_settings WHERE id = ? LIMIT 1");
    $stmt->execute([$account['id']]);
    $row = $stmt->fetch();
    if (!$row) {
        handleError('Polaris account not configured', 500);
    }

    $storedChallenge = (string)($row['otp_challenge_id'] ?? '');
    $storedIntent = (string)($row['otp_intent_hash'] ?? '');
    $storedHash = (string)($row['otp_hash'] ?? '');
    $expiresAt = (string)($row['otp_expires_at'] ?? '');

    if ($storedChallenge === '' || !hash_equals($storedChallenge, $challengeId) || !hash_equals($storedIntent, $intentHash)) {
        handleError('OTP does not match this transfer attempt');
    }
    if ($expiresAt === '' || strtotime($expiresAt) < time()) {
        handleError('OTP has expired');
    }
    if ($storedHash === '' || !password_verify($otp, $storedHash)) {
        handleError('Incorrect OTP');
    }

    $upd = $pdo->prepare("UPDATE polaris_bank_account_settings SET otp_verified = 1, updated_at = NOW() WHERE id = ?");
    $upd->execute([$account['id']]);

    sendResponse(true, ['challenge_id' => $challengeId, 'verified' => true], 'OTP verified');
}

handleError('Unknown action');
