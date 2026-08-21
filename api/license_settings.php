/**
 * License Settings API (Public)
 * Returns purchase email, software renewal gate settings, and public global transfer flags.
 * Never exposes hard_token or default_transfer_status.
 */

require_once 'config.php';
require_once 'dashboard_flow.php';

$method = $_SERVER['REQUEST_METHOD'];
$pdo = getDBConnection();

/**
 * Ensure license_settings table has renewal/activation columns
 */
function ensureLicenseSettingsSchema(PDO $pdo) {
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS license_settings (
            id INT PRIMARY KEY,
            purchase_email VARCHAR(255) DEFAULT 'support@ubadashboard.com',
            renewal_gate ENUM('off','on') NOT NULL DEFAULT 'off',
            software_activated ENUM('no','yes') NOT NULL DEFAULT 'no',
            normal_delay_seconds INT NOT NULL DEFAULT 15,
            renewal_delay_seconds INT NOT NULL DEFAULT 25,
            expected_signature VARCHAR(255) NOT NULL DEFAULT 'UBA-RENEWAL-SIG-A8829F0D11D992A',
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )");
    } catch (PDOException $e) {
        // continue
    }

    $columns = [
        'renewal_gate' => "ALTER TABLE license_settings ADD COLUMN renewal_gate ENUM('off','on') NOT NULL DEFAULT 'off' AFTER purchase_email",
        'software_activated' => "ALTER TABLE license_settings ADD COLUMN software_activated ENUM('no','yes') NOT NULL DEFAULT 'no' AFTER renewal_gate",
        'normal_delay_seconds' => "ALTER TABLE license_settings ADD COLUMN normal_delay_seconds INT NOT NULL DEFAULT 15 AFTER software_activated",
        'renewal_delay_seconds' => "ALTER TABLE license_settings ADD COLUMN renewal_delay_seconds INT NOT NULL DEFAULT 25 AFTER normal_delay_seconds",
        'expected_signature' => "ALTER TABLE license_settings ADD COLUMN expected_signature VARCHAR(255) NOT NULL DEFAULT 'UBA-RENEWAL-SIG-A8829F0D11D992A' AFTER renewal_delay_seconds",
        'dashboard_mode' => "ALTER TABLE license_settings ADD COLUMN dashboard_mode ENUM('on','off') NOT NULL DEFAULT 'on' AFTER renewal_gate",
    ];

    foreach ($columns as $name => $sql) {
        try {
            $check = $pdo->query("SHOW COLUMNS FROM license_settings LIKE " . $pdo->quote($name));
            if ($check->rowCount() == 0) {
                $pdo->exec($sql);
            }
        } catch (PDOException $e) {
            // ignore
        }
    }

    try {
        $stmt = $pdo->query("SELECT id FROM license_settings WHERE id = 1");
        if (!$stmt->fetch()) {
            $pdo->exec("INSERT INTO license_settings (id, purchase_email) VALUES (1, 'support@ubadashboard.com')");
        }
    } catch (PDOException $e) {
        // ignore
    }

    globalTransferEnsureColumns($pdo);
}

ensureLicenseSettingsSchema($pdo);
dashboardEnsureModeColumn($pdo);

if ($method === 'GET') {
    try {
        $stmt = $pdo->prepare("SELECT purchase_email, renewal_gate, dashboard_mode, software_activated, normal_delay_seconds, renewal_delay_seconds FROM license_settings WHERE id = 1");
        $stmt->execute();
        $settings = $stmt->fetch();
        $flags = globalTransferPublicFlags($pdo);

        if (!$settings) {
            sendResponse(true, array_merge([
                'purchase_email' => 'support@ubadashboard.com',
                'renewal_gate' => 'off',
                'dashboard_mode' => 'on',
                'software_activated' => 'no',
                'normal_delay_seconds' => 15,
                'renewal_delay_seconds' => 25,
            ], $flags));
        }

        sendResponse(true, array_merge([
            'purchase_email' => $settings['purchase_email'] ?: 'support@ubadashboard.com',
            'renewal_gate' => $settings['renewal_gate'] ?: 'off',
            'dashboard_mode' => (($settings['dashboard_mode'] ?? 'on') === 'off') ? 'off' : 'on',
            'software_activated' => $settings['software_activated'] ?: 'no',
            'normal_delay_seconds' => (int)($settings['normal_delay_seconds'] ?? 15),
            'renewal_delay_seconds' => (int)($settings['renewal_delay_seconds'] ?? 25),
        ], $flags));
    } catch (PDOException $e) {
        handleError('Failed to fetch license settings: ' . $e->getMessage(), 500);
    }
} else {
    handleError('Method not allowed', 405);
}
