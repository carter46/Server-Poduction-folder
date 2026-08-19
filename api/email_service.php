<?php
/**
 * Central HTML email service: PHPMailer SMTP + Brevo transactional API.
 * Never return SMTP passwords or Brevo API keys to the client.
 */

function emailEnsureMailColumns(PDO $pdo): void
{
    $columns = [
        'mail_phpmailer_enabled' => "TINYINT(1) NOT NULL DEFAULT 0",
        'mail_smtp_host' => "VARCHAR(255) DEFAULT NULL",
        'mail_smtp_port' => "INT NOT NULL DEFAULT 587",
        'mail_smtp_username' => "VARCHAR(255) DEFAULT NULL",
        'mail_smtp_password' => "VARCHAR(512) DEFAULT NULL",
        'mail_smtp_encryption' => "VARCHAR(10) NOT NULL DEFAULT 'tls'",
        'mail_from_email' => "VARCHAR(255) DEFAULT NULL",
        'mail_from_name' => "VARCHAR(255) DEFAULT NULL",
        'mail_reply_to' => "VARCHAR(255) DEFAULT NULL",
        'mail_brevo_enabled' => "TINYINT(1) NOT NULL DEFAULT 0",
        'mail_brevo_api_key' => "VARCHAR(512) DEFAULT NULL",
        'mail_brevo_sender_email' => "VARCHAR(255) DEFAULT NULL",
        'mail_brevo_sender_name' => "VARCHAR(255) DEFAULT NULL",
    ];
    foreach ($columns as $name => $ddl) {
        try {
            $check = $pdo->query("SHOW COLUMNS FROM license_settings LIKE " . $pdo->quote($name));
            if ($check && $check->rowCount() === 0) {
                $pdo->exec("ALTER TABLE license_settings ADD COLUMN {$name} {$ddl}");
            }
        } catch (PDOException $e) {
            // ignore
        }
    }
}

function emailSafeError(string $message): string
{
    $message = preg_replace('/xkeysib-[A-Za-z0-9_-]+/i', '[redacted]', $message) ?: $message;
    $message = preg_replace('/api[- ]?key["\s:=]+[^\s"\']+/i', 'api-key [redacted]', $message) ?: $message;
    $message = preg_replace('/password["\s:=]+[^\s"\']+/i', 'password [redacted]', $message) ?: $message;
    return trim($message);
}

function emailLoadMailRow(PDO $pdo)
{
    emailEnsureMailColumns($pdo);
    $stmt = $pdo->query("SELECT * FROM license_settings WHERE id = 1 LIMIT 1");
    return $stmt ? $stmt->fetch() : false;
}

function emailPublicMailFlags(array $row): array
{
    $phpOn = intval($row['mail_phpmailer_enabled'] ?? 0) === 1;
    $brevoOn = intval($row['mail_brevo_enabled'] ?? 0) === 1;
    $warnings = [];
    if ($phpOn) {
        if (trim((string)($row['mail_smtp_host'] ?? '')) === '') {
            $warnings[] = 'PHPMailer is enabled but SMTP host is missing.';
        }
        if (trim((string)($row['mail_from_email'] ?? '')) === '') {
            $warnings[] = 'PHPMailer is enabled but From email is missing.';
        }
    }
    if ($brevoOn) {
        if (trim((string)($row['mail_brevo_api_key'] ?? '')) === '') {
            $warnings[] = 'Brevo is enabled but API key is missing.';
        }
        if (trim((string)($row['mail_brevo_sender_email'] ?? '')) === '') {
            $warnings[] = 'Brevo is enabled but sender email is missing.';
        }
    }
    if (!$phpOn && !$brevoOn) {
        $warnings[] = 'No email provider configured.';
    }
    return [
        'mail_phpmailer_enabled' => $phpOn,
        'mail_smtp_host' => (string)($row['mail_smtp_host'] ?? ''),
        'mail_smtp_port' => intval($row['mail_smtp_port'] ?? 587),
        'mail_smtp_username' => (string)($row['mail_smtp_username'] ?? ''),
        'mail_smtp_encryption' => (string)($row['mail_smtp_encryption'] ?? 'tls'),
        'mail_from_email' => (string)($row['mail_from_email'] ?? ''),
        'mail_from_name' => (string)($row['mail_from_name'] ?? ''),
        'mail_reply_to' => (string)($row['mail_reply_to'] ?? ''),
        'mail_smtp_password_set' => trim((string)($row['mail_smtp_password'] ?? '')) !== '',
        'mail_brevo_enabled' => $brevoOn,
        'mail_brevo_sender_email' => (string)($row['mail_brevo_sender_email'] ?? ''),
        'mail_brevo_sender_name' => (string)($row['mail_brevo_sender_name'] ?? ''),
        'mail_brevo_api_key_set' => trim((string)($row['mail_brevo_api_key'] ?? '')) !== '',
        'mail_config_warnings' => $warnings,
    ];
}

function emailSendViaPhpMailer(array $row, array $toEmails, string $subject, string $html): array
{
    $host = trim((string)($row['mail_smtp_host'] ?? ''));
    $from = trim((string)($row['mail_from_email'] ?? ''));
    if ($host === '' || $from === '') {
        return ['ok' => false, 'error' => 'PHPMailer is incomplete: SMTP host and From email are required.'];
    }
    $phpmailerDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'PHPMailer' . DIRECTORY_SEPARATOR;
    $files = [$phpmailerDir . 'Exception.php', $phpmailerDir . 'PHPMailer.php', $phpmailerDir . 'SMTP.php'];
    foreach ($files as $file) {
        if (!is_file($file)) {
            return ['ok' => false, 'error' => 'PHPMailer files were not found on the server.'];
        }
        require_once $file;
    }
    try {
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = $host;
        $mail->Port = intval($row['mail_smtp_port'] ?? 587) ?: 587;
        $mail->SMTPAuth = trim((string)($row['mail_smtp_username'] ?? '')) !== '';
        $mail->Username = (string)($row['mail_smtp_username'] ?? '');
        $mail->Password = (string)($row['mail_smtp_password'] ?? '');
        $enc = strtolower(trim((string)($row['mail_smtp_encryption'] ?? 'tls')));
        $mail->SMTPSecure = $enc === 'ssl' ? 'ssl' : 'tls';
        $fromName = trim((string)($row['mail_from_name'] ?? ''));
        $mail->setFrom($from, $fromName !== '' ? $fromName : 'Polaris Bank');
        $reply = trim((string)($row['mail_reply_to'] ?? ''));
        if ($reply !== '' && filter_var($reply, FILTER_VALIDATE_EMAIL)) {
            $mail->addReplyTo($reply);
        }
        foreach ($toEmails as $to) {
            $mail->addAddress($to);
        }
        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';
        $mail->Subject = $subject;
        $mail->Body = $html;
        $mail->AltBody = 'This message requires an HTML email client.';
        $mail->send();
        return ['ok' => true, 'error' => ''];
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => emailSafeError($e->getMessage())];
    }
}

function emailSendViaBrevo(array $row, array $toEmails, string $subject, string $html): array
{
    $key = trim((string)($row['mail_brevo_api_key'] ?? ''));
    $senderEmail = trim((string)($row['mail_brevo_sender_email'] ?? ''));
    if ($key === '' || $senderEmail === '') {
        return ['ok' => false, 'error' => 'Brevo is incomplete: API key and sender email are required.'];
    }
    $to = [];
    foreach ($toEmails as $email) {
        $to[] = ['email' => $email];
    }
    $payload = [
        'sender' => [
            'email' => $senderEmail,
            'name' => trim((string)($row['mail_brevo_sender_name'] ?? '')) ?: 'Polaris Bank',
        ],
        'to' => $to,
        'subject' => $subject,
        'htmlContent' => $html,
    ];
    $reply = trim((string)($row['mail_reply_to'] ?? ''));
    if ($reply !== '' && filter_var($reply, FILTER_VALIDATE_EMAIL)) {
        $payload['replyTo'] = ['email' => $reply];
    }
    $ch = curl_init('https://api.brevo.com/v3/smtp/email');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'accept: application/json',
        'content-type: application/json',
        'api-key: ' . $key,
    ]);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    $response = curl_exec($ch);
    $status = intval(curl_getinfo($ch, CURLINFO_HTTP_CODE));
    $curlErr = curl_error($ch);
    curl_close($ch);
    if ($response === false) {
        return ['ok' => false, 'error' => emailSafeError($curlErr !== '' ? $curlErr : 'Brevo request failed')];
    }
    $decoded = json_decode((string)$response, true);
    if ($status >= 200 && $status < 300) {
        return ['ok' => true, 'error' => ''];
    }
    $msg = '';
    if (is_array($decoded)) {
        $msg = (string)($decoded['message'] ?? $decoded['error'] ?? '');
    }
    if ($msg === '') {
        $msg = 'Brevo API error (HTTP ' . $status . ')';
    }
    return ['ok' => false, 'error' => emailSafeError($msg)];
}

function emailProbeBrevo(array $row): array
{
    $key = trim((string)($row['mail_brevo_api_key'] ?? ''));
    if ($key === '') {
        return ['ok' => false, 'error' => 'Brevo API key is missing.'];
    }
    $ch = curl_init('https://api.brevo.com/v3/account');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'accept: application/json',
        'api-key: ' . $key,
    ]);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 8);
    curl_setopt($ch, CURLOPT_TIMEOUT, 12);
    $response = curl_exec($ch);
    $status = intval(curl_getinfo($ch, CURLINFO_HTTP_CODE));
    $curlErr = curl_error($ch);
    curl_close($ch);
    if ($response === false) {
        return ['ok' => false, 'error' => emailSafeError($curlErr !== '' ? $curlErr : 'Brevo account check failed')];
    }
    if ($status >= 200 && $status < 300) {
        return ['ok' => true, 'error' => ''];
    }
    $decoded = json_decode((string)$response, true);
    $msg = is_array($decoded) ? (string)($decoded['message'] ?? '') : '';
    if ($msg === '') {
        $msg = 'Brevo API authentication failed (HTTP ' . $status . ')';
    }
    return ['ok' => false, 'error' => emailSafeError($msg)];
}

/**
 * @param string[] $toEmails
 * @return array{ok:bool,sent_via:?string,phpmailer_status:string,phpmailer_error:string,brevo_status:string,brevo_error:string,message:string}
 */
function emailSendHtml(PDO $pdo, array $toEmails, string $subject, string $html, bool $probeBrevoOnPrimarySuccess = false): array
{
    $row = emailLoadMailRow($pdo);
    if (!$row) {
        return [
            'ok' => false,
            'sent_via' => null,
            'phpmailer_status' => 'skipped',
            'phpmailer_error' => '',
            'brevo_status' => 'skipped',
            'brevo_error' => '',
            'message' => 'No email provider configured.',
        ];
    }
    $phpOn = intval($row['mail_phpmailer_enabled'] ?? 0) === 1;
    $brevoOn = intval($row['mail_brevo_enabled'] ?? 0) === 1;
    $result = [
        'ok' => false,
        'sent_via' => null,
        'phpmailer_status' => $phpOn ? 'pending' : 'skipped',
        'phpmailer_error' => '',
        'brevo_status' => $brevoOn ? 'pending' : 'skipped',
        'brevo_error' => '',
        'message' => '',
    ];
    if (!$phpOn && !$brevoOn) {
        $result['phpmailer_status'] = 'skipped';
        $result['brevo_status'] = 'skipped';
        $result['message'] = 'No email provider configured.';
        return $result;
    }

    if ($phpOn) {
        $php = emailSendViaPhpMailer($row, $toEmails, $subject, $html);
        if ($php['ok']) {
            $result['ok'] = true;
            $result['sent_via'] = 'phpmailer';
            $result['phpmailer_status'] = 'sent';
            $result['message'] = 'Email sent successfully via PHPMailer.';
            if ($brevoOn && $probeBrevoOnPrimarySuccess) {
                $probe = emailProbeBrevo($row);
                if (!$probe['ok']) {
                    $result['brevo_status'] = 'warning';
                    $result['brevo_error'] = $probe['error'];
                    $result['message'] .= ' Brevo configuration warning: ' . $probe['error'];
                } else {
                    $result['brevo_status'] = 'ok';
                }
            } elseif ($brevoOn) {
                $result['brevo_status'] = 'skipped';
            }
            return $result;
        }
        $result['phpmailer_status'] = 'failed';
        $result['phpmailer_error'] = $php['error'];
    }

    if ($brevoOn) {
        $brevo = emailSendViaBrevo($row, $toEmails, $subject, $html);
        if ($brevo['ok']) {
            $result['ok'] = true;
            $result['sent_via'] = 'brevo';
            $result['brevo_status'] = 'sent';
            if ($phpOn && $result['phpmailer_status'] === 'failed') {
                $result['message'] = 'Email sent successfully. Primary provider: PHPMailer — failed. Fallback provider: Brevo — sent.';
            } else {
                $result['message'] = 'Email sent successfully via Brevo.';
            }
            return $result;
        }
        $result['brevo_status'] = 'failed';
        $result['brevo_error'] = $brevo['error'];
    }

    $parts = [];
    if ($phpOn) {
        $parts[] = 'PHPMailer: ' . ($result['phpmailer_error'] !== '' ? $result['phpmailer_error'] : 'failed');
    }
    if ($brevoOn) {
        $parts[] = 'Brevo: ' . ($result['brevo_error'] !== '' ? $result['brevo_error'] : 'failed');
    }
    $result['message'] = 'Email could not be sent. ' . implode(' ', $parts);
    return $result;
}
