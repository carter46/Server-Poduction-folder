<?php
/**
 * Transfer Uptime Status API
 * Per-bank transfer success tier shown on the uptime page and verify gate modal.
 *
 * - GET: public — { enabled, banks } or ?bank_code=044 → { enabled, bank_code, tier, percent, message }
 * - PUT: admin only — { enabled? } and/or banks[] / bank_code+tier
 *
 * Tiers (admin selects range; display percent is the top of the range):
 *   bad       → 0–45%   display 45
 *   partial   → 46–85%  display 85
 *   excellent → 86–100% display 100
 *
 * Feature master switch (enabled): when off, users do not see uptime UI at all.
 */
require_once 'config.php';

$method = $_SERVER['REQUEST_METHOD'];
$pdo = getDBConnection();

function transferUptimeEnsureSchema(PDO $pdo): void
{
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS transfer_uptime_status (
            bank_code VARCHAR(32) NOT NULL PRIMARY KEY,
            tier ENUM('bad','partial','excellent') NOT NULL DEFAULT 'excellent',
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )");
    } catch (PDOException $e) {
        // continue
    }

    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS transfer_uptime_feature (
            id INT PRIMARY KEY,
            enabled TINYINT(1) NOT NULL DEFAULT 1,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )");
    } catch (PDOException $e) {
        // continue
    }

    try {
        $stmt = $pdo->query("SELECT id FROM transfer_uptime_feature WHERE id = 1 LIMIT 1");
        if (!$stmt || !$stmt->fetch()) {
            $pdo->exec("INSERT INTO transfer_uptime_feature (id, enabled) VALUES (1, 1)");
        }
    } catch (PDOException $e) {
        // continue
    }
}

/** Known catalog codes (keep in sync with src/banking/bankCatalog.ts). */
function transferUptimeKnownCodes(): array
{
    return [
        '044', '070', '011', '058', '214', '030', '301', '082', '232', '032',
        '050', '033', '215', '035', '076', '221', '057', '50211', '999992', '090405', '100033',
    ];
}

function transferUptimeKnownCodeSet(): array
{
    static $set = null;
    if ($set === null) {
        $set = array_fill_keys(transferUptimeKnownCodes(), true);
    }
    return $set;
}

function transferUptimeSanitizeBankCode($raw): string
{
    $code = trim((string)$raw);
    if ($code === '' || strlen($code) > 32) {
        return '';
    }
    if (!preg_match('/^[0-9]{2,32}$/', $code)) {
        return '';
    }
    return $code;
}

function transferUptimeNormalizeTier($raw): string
{
    $v = strtolower(trim((string)$raw));
    if ($v === 'bad' || $v === 'partial' || $v === 'excellent') {
        return $v;
    }
    return 'excellent';
}

function transferUptimePercentForTier(string $tier): int
{
    if ($tier === 'bad') return 45;
    if ($tier === 'partial') return 85;
    return 100;
}

function transferUptimeMessageForTier(string $tier): string
{
    if ($tier === 'bad') return 'Network is bad';
    if ($tier === 'partial') return 'Network is partial';
    return 'Network is excellent';
}

function transferUptimeRow(string $bankCode, string $tier): array
{
    $tier = transferUptimeNormalizeTier($tier);
    return [
        'bank_code' => $bankCode,
        'tier' => $tier,
        'percent' => transferUptimePercentForTier($tier),
        'message' => transferUptimeMessageForTier($tier),
    ];
}

function transferUptimeFeatureEnabled(PDO $pdo): bool
{
    try {
        $stmt = $pdo->query("SELECT enabled FROM transfer_uptime_feature WHERE id = 1 LIMIT 1");
        $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
        if (!$row) return true;
        return (int)($row['enabled'] ?? 1) === 1;
    } catch (PDOException $e) {
        return true;
    }
}

function transferUptimeSetFeatureEnabled(PDO $pdo, bool $enabled): void
{
    $stmt = $pdo->prepare(
        "INSERT INTO transfer_uptime_feature (id, enabled) VALUES (1, ?)
         ON DUPLICATE KEY UPDATE enabled = VALUES(enabled), updated_at = CURRENT_TIMESTAMP"
    );
    $stmt->execute([$enabled ? 1 : 0]);
}

transferUptimeEnsureSchema($pdo);

switch ($method) {
    case 'GET':
        try {
            $enabled = transferUptimeFeatureEnabled($pdo);
            $bankCode = transferUptimeSanitizeBankCode($_GET['bank_code'] ?? '');
            $stmt = $pdo->query("SELECT bank_code, tier, updated_at FROM transfer_uptime_status");
            $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
            $byCode = [];
            $known = transferUptimeKnownCodeSet();
            foreach ($rows as $row) {
                $code = transferUptimeSanitizeBankCode($row['bank_code'] ?? '');
                if ($code === '' || !isset($known[$code])) {
                    continue;
                }
                $byCode[$code] = transferUptimeRow($code, $row['tier'] ?? 'excellent');
                $byCode[$code]['updated_at'] = $row['updated_at'] ?? null;
            }

            if ($bankCode !== '') {
                if (isset($byCode[$bankCode])) {
                    $row = $byCode[$bankCode];
                } else {
                    $row = transferUptimeRow($bankCode, 'excellent');
                }
                $row['enabled'] = $enabled;
                sendResponse(true, $row);
            }

            $out = [];
            foreach (transferUptimeKnownCodes() as $code) {
                $out[$code] = $byCode[$code] ?? transferUptimeRow($code, 'excellent');
            }
            sendResponse(true, ['enabled' => $enabled, 'banks' => $out]);
        } catch (PDOException $e) {
            handleError('Failed to fetch transfer uptime status: ' . $e->getMessage(), 500);
        }
        break;

    case 'PUT':
        validateAdminSession();
        $input = getJsonInput();
        $known = transferUptimeKnownCodeSet();
        $didSomething = false;
        $response = [];

        if (array_key_exists('enabled', $input)) {
            $raw = $input['enabled'];
            if (is_bool($raw)) {
                $enabled = $raw;
            } elseif (is_int($raw) || is_float($raw)) {
                $enabled = ((int)$raw) === 1;
            } else {
                $v = strtolower(trim((string)$raw));
                if (!in_array($v, ['on', 'off', '1', '0', 'true', 'false'], true)) {
                    handleError('Invalid enabled. Expected true/false or on/off.', 400);
                }
                $enabled = in_array($v, ['on', '1', 'true'], true);
            }
            try {
                transferUptimeSetFeatureEnabled($pdo, $enabled);
                $response['enabled'] = $enabled;
                $didSomething = true;
            } catch (PDOException $e) {
                handleError('Failed to update uptime feature flag: ' . $e->getMessage(), 500);
            }
        }

        $updates = [];
        if (isset($input['banks']) && is_array($input['banks'])) {
            foreach ($input['banks'] as $item) {
                if (!is_array($item)) continue;
                $code = transferUptimeSanitizeBankCode($item['bank_code'] ?? '');
                if ($code === '' || !isset($known[$code])) {
                    handleError('Invalid bank_code. Must be a known catalog bank.', 400);
                }
                if (!isset($item['tier']) || !in_array((string)$item['tier'], ['bad', 'partial', 'excellent'], true)) {
                    handleError('Invalid tier for bank ' . $code . '. Expected bad, partial, or excellent.', 400);
                }
                $updates[] = ['bank_code' => $code, 'tier' => transferUptimeNormalizeTier($item['tier'])];
            }
        } elseif (isset($input['bank_code']) || isset($input['tier'])) {
            $code = transferUptimeSanitizeBankCode($input['bank_code'] ?? '');
            if ($code === '' || !isset($known[$code])) {
                handleError('Invalid bank_code. Must be a known catalog bank.', 400);
            }
            if (!isset($input['tier']) || !in_array((string)$input['tier'], ['bad', 'partial', 'excellent'], true)) {
                handleError('Invalid tier. Expected bad, partial, or excellent.', 400);
            }
            $updates[] = ['bank_code' => $code, 'tier' => transferUptimeNormalizeTier($input['tier'])];
        }

        if (count($updates) > 0) {
            $deduped = [];
            foreach ($updates as $u) {
                $deduped[$u['bank_code']] = $u;
            }
            $updates = array_values($deduped);

            try {
                $stmt = $pdo->prepare(
                    "INSERT INTO transfer_uptime_status (bank_code, tier) VALUES (?, ?)
                     ON DUPLICATE KEY UPDATE tier = VALUES(tier), updated_at = CURRENT_TIMESTAMP"
                );
                $saved = [];
                foreach ($updates as $u) {
                    $stmt->execute([$u['bank_code'], $u['tier']]);
                    $saved[] = transferUptimeRow($u['bank_code'], $u['tier']);
                }
                $response['updated'] = $saved;
                $didSomething = true;
            } catch (PDOException $e) {
                handleError('Failed to update transfer uptime status: ' . $e->getMessage(), 500);
            }
        }

        if (!$didSomething) {
            handleError('Provide enabled and/or bank_code+tier / banks[{bank_code, tier}, ...].', 400);
        }

        if (!isset($response['enabled'])) {
            $response['enabled'] = transferUptimeFeatureEnabled($pdo);
        }

        sendResponse(true, $response, 'Transfer uptime settings updated successfully');
        break;

    default:
        handleError('Method not allowed', 405);
        break;
}
