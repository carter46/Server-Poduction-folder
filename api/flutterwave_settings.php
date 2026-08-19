<?php
/**
 * Flutterwave Settings API
 * Handles Flutterwave API key management (Test and Live keys)
 */

require_once 'config.php';

$method = $_SERVER['REQUEST_METHOD'];
$pdo = getDBConnection();

// Create table if it doesn't exist and migrate encryption key columns
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS flutterwave_settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        test_public_key VARCHAR(255) DEFAULT NULL,
        test_secret_key VARCHAR(255) DEFAULT NULL,
        test_encryption_key VARCHAR(255) DEFAULT NULL,
        live_public_key VARCHAR(255) DEFAULT NULL,
        live_secret_key VARCHAR(255) DEFAULT NULL,
        live_encryption_key VARCHAR(255) DEFAULT NULL,
        use_live TINYINT(1) DEFAULT 0,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");

    try {
        $pdo->exec("ALTER TABLE flutterwave_settings ADD COLUMN test_encryption_key VARCHAR(255) DEFAULT NULL AFTER test_secret_key");
    } catch (PDOException $e) {
        // Column might already exist
    }

    try {
        $pdo->exec("ALTER TABLE flutterwave_settings ADD COLUMN live_encryption_key VARCHAR(255) DEFAULT NULL AFTER live_secret_key");
    } catch (PDOException $e) {
        // Column might already exist
    }
} catch (PDOException $e) {
    // Table might already exist, continue
}

switch ($method) {
    case 'GET':
        // Get Flutterwave settings (public endpoint - no auth required for frontend)
        try {
            $stmt = $pdo->query("SELECT test_public_key, test_secret_key, test_encryption_key, live_public_key, live_secret_key, live_encryption_key, use_live FROM flutterwave_settings ORDER BY id DESC LIMIT 1");
            $settings = $stmt->fetch();
            
            if (!$settings) {
                $pdo->exec("INSERT INTO flutterwave_settings (use_live) VALUES (0)");
                $stmt = $pdo->query("SELECT test_public_key, test_secret_key, test_encryption_key, live_public_key, live_secret_key, live_encryption_key, use_live FROM flutterwave_settings ORDER BY id DESC LIMIT 1");
                $settings = $stmt->fetch();
            }
            
            $activePublicKey = $settings['use_live'] ? $settings['live_public_key'] : $settings['test_public_key'];
            $activeSecretKey = $settings['use_live'] ? $settings['live_secret_key'] : $settings['test_secret_key'];
            $activeEncryptionKey = $settings['use_live'] ? $settings['live_encryption_key'] : $settings['test_encryption_key'];
            
            sendResponse(true, [
                'test_public_key' => $settings['test_public_key'],
                'test_secret_key' => $settings['test_secret_key'],
                'test_encryption_key' => $settings['test_encryption_key'] ?? null,
                'live_public_key' => $settings['live_public_key'],
                'live_secret_key' => $settings['live_secret_key'],
                'live_encryption_key' => $settings['live_encryption_key'] ?? null,
                'use_live' => (bool)$settings['use_live'],
                'active_public_key' => $activePublicKey,
                'active_secret_key' => $activeSecretKey,
                'active_encryption_key' => $activeEncryptionKey
            ]);
        } catch (PDOException $e) {
            handleError('Failed to fetch Flutterwave settings: ' . $e->getMessage(), 500);
        }
        break;
        
    case 'POST':
        // Test Flutterwave API key (Admin only)
        validateAdminSession();
        
        $input = getJsonInput();
        
        if (!isset($input['api_key']) || empty(trim($input['api_key']))) {
            handleError('API key is required');
        }
        
        $apiKey = trim($input['api_key']);
        
        $ch = curl_init();
        
        curl_setopt_array($ch, [
            CURLOPT_URL => 'https://api.flutterwave.com/v3/banks/NG',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json'
            ],
            CURLOPT_TIMEOUT => 10,
            CURLOPT_CONNECTTIMEOUT => 5
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        
        curl_close($ch);
        
        if ($curlError) {
            sendResponse(false, [
                'valid' => false,
                'message' => 'Connection error while reaching Flutterwave: ' . $curlError
            ], 'Could not connect to Flutterwave', 500);
        }
        
        if ($httpCode === 200) {
            $data = json_decode($response, true);
            if ($data && isset($data['status']) && $data['status'] === 'success') {
                sendResponse(true, [
                    'valid' => true,
                    'message' => 'Flutterwave API key is valid and authorized'
                ], 'API key is valid');
            } else {
                sendResponse(true, [
                    'valid' => false,
                    'message' => 'Invalid response from Flutterwave'
                ], 'Invalid API key');
            }
        } elseif ($httpCode === 401) {
            sendResponse(true, [
                'valid' => false,
                'message' => 'Invalid or unauthorized Flutterwave secret key'
            ], 'Invalid API key - Authentication failed (401)');
        } elseif ($httpCode === 403) {
            sendResponse(true, [
                'valid' => false,
                'message' => 'API key forbidden - Check key permissions'
            ], 'API key forbidden (403)');
        } else {
            sendResponse(true, [
                'valid' => false,
                'message' => 'Flutterwave returned HTTP ' . $httpCode
            ], 'API key validation failed');
        }
        break;
        
    case 'PUT':
        // Update Flutterwave settings (Admin only)
        validateAdminSession();
        
        $input = getJsonInput();
        
        $validFields = ['test_public_key', 'test_secret_key', 'test_encryption_key', 'live_public_key', 'live_secret_key', 'live_encryption_key', 'use_live'];
        $hasValidField = false;
        foreach ($validFields as $field) {
            if (isset($input[$field])) {
                $hasValidField = true;
                break;
            }
        }
        
        if (!$hasValidField) {
            handleError('No update data provided');
        }
        
        try {
            $stmt = $pdo->query("SELECT id FROM flutterwave_settings ORDER BY id DESC LIMIT 1");
            $existing = $stmt->fetch();
            
            $updates = [];
            $params = [];
            
            if (isset($input['test_public_key'])) {
                $updates[] = "test_public_key = ?";
                $params[] = $input['test_public_key'] ?: null;
            }
            
            if (isset($input['test_secret_key'])) {
                $updates[] = "test_secret_key = ?";
                $params[] = $input['test_secret_key'] ?: null;
            }

            if (isset($input['test_encryption_key'])) {
                $updates[] = "test_encryption_key = ?";
                $params[] = $input['test_encryption_key'] ?: null;
            }
            
            if (isset($input['live_public_key'])) {
                $updates[] = "live_public_key = ?";
                $params[] = $input['live_public_key'] ?: null;
            }
            
            if (isset($input['live_secret_key'])) {
                $updates[] = "live_secret_key = ?";
                $params[] = $input['live_secret_key'] ?: null;
            }

            if (isset($input['live_encryption_key'])) {
                $updates[] = "live_encryption_key = ?";
                $params[] = $input['live_encryption_key'] ?: null;
            }
            
            if (isset($input['use_live'])) {
                $updates[] = "use_live = ?";
                $params[] = $input['use_live'] ? 1 : 0;
            }
            
            if (empty($updates)) {
                handleError('No valid fields to update');
            }
            
            $updates[] = "updated_at = NOW()";
            
            if (!$existing) {
                $sql = "INSERT INTO flutterwave_settings SET " . implode(", ", $updates);
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
            } else {
                $sql = "UPDATE flutterwave_settings SET " . implode(", ", $updates) . " WHERE id = ?";
                $params[] = $existing['id'];
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
            }
            
            $stmt = $pdo->query("SELECT test_public_key, test_secret_key, test_encryption_key, live_public_key, live_secret_key, live_encryption_key, use_live FROM flutterwave_settings ORDER BY id DESC LIMIT 1");
            $settings = $stmt->fetch();
            $activePublicKey = $settings['use_live'] ? $settings['live_public_key'] : $settings['test_public_key'];
            $activeSecretKey = $settings['use_live'] ? $settings['live_secret_key'] : $settings['test_secret_key'];
            $activeEncryptionKey = $settings['use_live'] ? $settings['live_encryption_key'] : $settings['test_encryption_key'];
            
            sendResponse(true, [
                'test_public_key' => $settings['test_public_key'],
                'test_secret_key' => $settings['test_secret_key'],
                'test_encryption_key' => $settings['test_encryption_key'] ?? null,
                'live_public_key' => $settings['live_public_key'],
                'live_secret_key' => $settings['live_secret_key'],
                'live_encryption_key' => $settings['live_encryption_key'] ?? null,
                'use_live' => (bool)$settings['use_live'],
                'active_public_key' => $activePublicKey,
                'active_secret_key' => $activeSecretKey,
                'active_encryption_key' => $activeEncryptionKey
            ], 'Flutterwave settings updated successfully');
        } catch (PDOException $e) {
            handleError('Failed to update Flutterwave settings: ' . $e->getMessage(), 500);
        }
        break;
        
    default:
        handleError('Method not allowed', 405);
        break;
}
