<?php
/**
 * Mobile companion auth helpers — Bearer sessions tied to bank_code + account_number.
 */

require_once __DIR__ . '/mobile_bank_match_helper.php';

define('MOBILE_SESSION_LIFETIME', 3600 * 24 * 7); // 7 days

function mobileGetBearerToken() {
    $header = '';
    if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $header = $_SERVER['HTTP_AUTHORIZATION'];
    } elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
        $header = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
    } elseif (function_exists('apache_request_headers')) {
        $headers = apache_request_headers();
        foreach ($headers as $k => $v) {
            if (strtolower($k) === 'authorization') {
                $header = $v;
                break;
            }
        }
    }
    if (preg_match('/Bearer\s+(\S+)/i', $header, $m)) {
        return trim($m[1]);
    }
    return null;
}

function mobileCreateSession(PDO $pdo, $bankCode, $accountNumber, $accountName) {
    mobileEnsureSchema($pdo);
    $token = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', time() + MOBILE_SESSION_LIFETIME);
    $stmt = $pdo->prepare("INSERT INTO mobile_sessions (token, bank_code, account_number, account_name_snapshot, expires_at) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([
        $token,
        strtoupper($bankCode),
        mobileNormalizeAccountNumber($accountNumber),
        $accountName,
        $expires,
    ]);
    return [
        'token' => $token,
        'expires_at' => $expires,
        'bank_code' => strtoupper($bankCode),
        'account_number' => mobileNormalizeAccountNumber($accountNumber),
        'account_name' => $accountName,
    ];
}

function mobileValidateSession(PDO $pdo) {
    mobileEnsureSchema($pdo);
    $token = mobileGetBearerToken();
    if (!$token) {
        handleError('Unauthorized. Missing Bearer token.', 401);
    }
    $stmt = $pdo->prepare("SELECT * FROM mobile_sessions WHERE token = ? LIMIT 1");
    $stmt->execute([$token]);
    $session = $stmt->fetch();
    if (!$session) {
        handleError('Unauthorized. Invalid session.', 401);
    }
    if (strtotime($session['expires_at']) < time()) {
        $pdo->prepare("DELETE FROM mobile_sessions WHERE id = ?")->execute([$session['id']]);
        handleError('Session expired. Please login again.', 401);
    }

    // Re-check eligibility: beneficiary rows must still exist
    $exists = mobileBeneficiaryExists($pdo, $session['bank_code'], $session['account_number']);
    if (!$exists) {
        $pdo->prepare("DELETE FROM mobile_sessions WHERE id = ?")->execute([$session['id']]);
        handleError('Account no longer eligible. Transfers to this account were removed.', 401);
    }

    return $session;
}

function mobileDestroySession(PDO $pdo) {
    mobileEnsureSchema($pdo);
    $token = mobileGetBearerToken();
    if ($token) {
        $pdo->prepare("DELETE FROM mobile_sessions WHERE token = ?")->execute([$token]);
    }
}

function mobileGetSharedPasswordHash(PDO $pdo) {
    mobileEnsureSchema($pdo);
    $stmt = $pdo->query("SELECT password_hash FROM mobile_settings WHERE id = 1");
    $row = $stmt->fetch();
    return $row && !empty($row['password_hash']) ? $row['password_hash'] : null;
}

function mobileSetSharedPassword(PDO $pdo, $plainPassword) {
    mobileEnsureSchema($pdo);
    $hash = password_hash($plainPassword, PASSWORD_BCRYPT);
    $stmt = $pdo->prepare("UPDATE mobile_settings SET password_hash = ?, updated_at = NOW() WHERE id = 1");
    $stmt->execute([$hash]);
    // Invalidate all companion sessions so old Bearer tokens cannot be reused after rotate
    $pdo->exec("DELETE FROM mobile_sessions");
    return true;
}
