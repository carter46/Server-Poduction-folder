<?php
/**
 * Shared CoinGecko proxy for simulated crypto rates (all banks) and admin coin picker.
 */
require_once 'config.php';
require_once 'polaris_stanbic_schema.php';
require_once 'polaris_transfer_helpers.php';

$method = $_SERVER['REQUEST_METHOD'];
if ($method !== 'GET') {
    handleError('Method not allowed', 405);
}

$pdo = getDBConnection();
ensurePolarisStanbicSchema($pdo);

$action = strtolower(trim((string)($_GET['action'] ?? 'rate')));

if ($action === 'rate') {
    $account = polarisAccountRow($pdo);
    $enabled = polarisEnabledCryptoAssets(polarisParseCryptoAssets(is_array($account) ? ($account['crypto_assets'] ?? '') : ''));
    $enabledIds = array_column($enabled, 'id');
    $idsParam = trim((string)($_GET['ids'] ?? ''));
    $requested = array_values(array_filter(array_map('polarisSanitizeCoinId', explode(',', $idsParam))));
    if (empty($requested)) {
        $ids = $enabledIds;
    } else {
        // Explicit ids: fetch those CoinGecko rates directly.
        // Do not intersect with Polaris-only enabled list — BankKit banks
        // have their own crypto_assets and still use this shared rate proxy.
        $ids = $requested;
    }
    $rates = polarisFetchNgnRates($ids);
    if (empty($rates)) {
        handleError('Could not load crypto rates', 502);
    }
    sendResponse(true, ['rates' => $rates, 'vs' => 'ngn']);
}

if ($action === 'search') {
    validateAdminSession();
    $query = trim((string)($_GET['query'] ?? ''));
    if (strlen($query) < 1) {
        handleError('Enter a coin name or symbol to search');
    }
    $data = polarisCoinGeckoGet('/search', ['query' => $query]);
    if (!$data || !isset($data['coins']) || !is_array($data['coins'])) {
        handleError('Could not load CoinGecko coin list', 502);
    }
    $coins = [];
    foreach (array_slice($data['coins'], 0, 40) as $coin) {
        $id = trim((string)($coin['id'] ?? ''));
        if ($id === '' || !preg_match('/^[a-z0-9-]+$/', $id)) {
            continue;
        }
        $coins[] = [
            'id' => $id,
            'name' => (string)($coin['name'] ?? $id),
            'symbol' => strtoupper((string)($coin['symbol'] ?? '')),
            'image' => (string)($coin['large'] ?? $coin['thumb'] ?? ''),
        ];
    }
    sendResponse(true, ['coins' => $coins]);
}

handleError('Unknown action');
