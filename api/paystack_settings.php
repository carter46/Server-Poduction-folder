<?php
/**
 * Paystack Settings API
 * Handles Paystack API key management (Test and Live keys)
 */

require_once 'config.php';

$method = $_SERVER['REQUEST_METHOD'];
$pdo = getDBConnection();

// Create table if it doesn't exist and migrate old structure
// Only run migrations if table doesn't exist or needs migration (check once per request)
try {
    // Check if table exists and has correct structure
    $tableExists = false;
    $needsMigration = false;
    try {
        $pdo->query("SELECT 1 FROM paystack_settings LIMIT 1");
        $tableExists = true;
        
        // Check if new columns exist
        $checkColumns = $pdo->query("SHOW COLUMNS FROM paystack_settings LIKE 'test_secret_key'");
        if ($checkColumns->rowCount() == 0) {
            $needsMigration = true;
        }
    } catch (PDOException $e) {
        $tableExists = false;
        $needsMigration = true;
    }
    
    // Only run migration if needed
    if ($needsMigration && $tableExists) {
        // Check if old structure exists (has test_key or live_key columns)
        $hasOldStructure = false;
        try {
            $checkColumns = $pdo->query("SHOW COLUMNS FROM paystack_settings LIKE 'test_key'");
            $hasOldStructure = $checkColumns->rowCount() > 0;
        } catch (PDOException $e) {
            $hasOldStructure = false;
        }
        
        if ($hasOldStructure) {
            // Migrate from old structure to new structure
            // Check and add new columns one by one
            try {
                $pdo->exec("ALTER TABLE paystack_settings ADD COLUMN test_public_key VARCHAR(255) DEFAULT NULL AFTER id");
            } catch (PDOException $e) {
                // Column might already exist
            }
            
            try {
                $pdo->exec("ALTER TABLE paystack_settings ADD COLUMN test_secret_key VARCHAR(255) DEFAULT NULL AFTER test_public_key");
            } catch (PDOException $e) {
                // Column might already exist
            }
            
            try {
                $pdo->exec("ALTER TABLE paystack_settings ADD COLUMN live_public_key VARCHAR(255) DEFAULT NULL AFTER test_secret_key");
            } catch (PDOException $e) {
                // Column might already exist
            }
            
            try {
                $pdo->exec("ALTER TABLE paystack_settings ADD COLUMN live_secret_key VARCHAR(255) DEFAULT NULL AFTER live_public_key");
            } catch (PDOException $e) {
                // Column might already exist
            }
            
            // Migrate existing data
            try {
                $pdo->exec("UPDATE paystack_settings SET live_secret_key = live_key WHERE live_secret_key IS NULL AND live_key IS NOT NULL");
            } catch (PDOException $e) {
                // Ignore errors
            }
            
            try {
                $pdo->exec("UPDATE paystack_settings SET test_secret_key = test_key WHERE test_secret_key IS NULL AND test_key IS NOT NULL");
            } catch (PDOException $e) {
                // Ignore errors
            }
        } else {
            // Table exists but missing new columns - add them
            try {
                $pdo->exec("ALTER TABLE paystack_settings ADD COLUMN test_public_key VARCHAR(255) DEFAULT NULL");
            } catch (PDOException $e) {
                // Column might already exist, ignore
            }
            
            try {
                $pdo->exec("ALTER TABLE paystack_settings ADD COLUMN test_secret_key VARCHAR(255) DEFAULT NULL");
            } catch (PDOException $e) {
                // Column might already exist, ignore
            }
            
            try {
                $pdo->exec("ALTER TABLE paystack_settings ADD COLUMN live_public_key VARCHAR(255) DEFAULT NULL");
            } catch (PDOException $e) {
                // Column might already exist, ignore
            }
            
            try {
                $pdo->exec("ALTER TABLE paystack_settings ADD COLUMN live_secret_key VARCHAR(255) DEFAULT NULL");
            } catch (PDOException $e) {
                // Column might already exist, ignore
            }
        }
    } elseif (!$tableExists) {
        // Create new table structure
        $pdo->exec("CREATE TABLE IF NOT EXISTS paystack_settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            test_public_key VARCHAR(255) DEFAULT NULL,
            test_secret_key VARCHAR(255) DEFAULT NULL,
            live_public_key VARCHAR(255) DEFAULT NULL,
            live_secret_key VARCHAR(255) DEFAULT NULL,
            use_live TINYINT(1) DEFAULT 0,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )");
        
        // Initialize with default values if table is empty
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM paystack_settings");
        $result = $stmt->fetch();
        if ($result['count'] == 0) {
            $pdo->exec("INSERT INTO paystack_settings (use_live) VALUES (0)");
        }
    }
} catch (PDOException $e) {
    // Log error but don't break the API - table might already exist with correct structure
    // Only log if it's a real error, not just "table already exists"
    if (strpos($e->getMessage(), 'already exists') === false && strpos($e->getMessage(), 'Duplicate column') === false) {
        error_log("Paystack settings table setup: " . $e->getMessage());
    }
    // Continue execution - don't break other API calls
}

switch ($method) {
    case 'GET':
        // Get Paystack settings (public endpoint - no auth required for frontend)
        try {
            // Try new structure first
            $stmt = $pdo->query("SELECT test_public_key, test_secret_key, live_public_key, live_secret_key, use_live FROM paystack_settings ORDER BY id DESC LIMIT 1");
            $settings = $stmt->fetch();
            
            // If no results, try old structure for migration
            if (!$settings) {
                $stmt = $pdo->query("SELECT test_key, live_key, use_live FROM paystack_settings ORDER BY id DESC LIMIT 1");
                $oldSettings = $stmt->fetch();
                
                if ($oldSettings) {
                    // Migrate old data
                    $pdo->exec("UPDATE paystack_settings SET 
                        live_secret_key = '" . addslashes($oldSettings['live_key']) . "',
                        test_secret_key = '" . addslashes($oldSettings['test_key']) . "'
                        WHERE id = (SELECT id FROM (SELECT id FROM paystack_settings ORDER BY id DESC LIMIT 1) AS temp)");
                    
                    $stmt = $pdo->query("SELECT test_public_key, test_secret_key, live_public_key, live_secret_key, use_live FROM paystack_settings ORDER BY id DESC LIMIT 1");
                    $settings = $stmt->fetch();
                } else {
                    // Initialize with default
                    $pdo->exec("INSERT INTO paystack_settings (use_live) VALUES (0)");
                    $stmt = $pdo->query("SELECT test_public_key, test_secret_key, live_public_key, live_secret_key, use_live FROM paystack_settings ORDER BY id DESC LIMIT 1");
                    $settings = $stmt->fetch();
                }
            }
            
            // Return the active keys based on use_live flag
            $activePublicKey = $settings['use_live'] ? $settings['live_public_key'] : $settings['test_public_key'];
            $activeSecretKey = $settings['use_live'] ? $settings['live_secret_key'] : $settings['test_secret_key'];
            
            sendResponse(true, [
                'test_public_key' => $settings['test_public_key'],
                'test_secret_key' => $settings['test_secret_key'],
                'live_public_key' => $settings['live_public_key'],
                'live_secret_key' => $settings['live_secret_key'],
                'use_live' => (bool)$settings['use_live'],
                'active_public_key' => $activePublicKey,
                'active_secret_key' => $activeSecretKey
            ]);
        } catch (PDOException $e) {
            handleError('Failed to fetch Paystack settings: ' . $e->getMessage(), 500);
        }
        break;
        
    case 'POST':
        // Test Paystack API key (Admin only)
        validateAdminSession();
        
        $input = getJsonInput();
        
        if (!isset($input['api_key']) || empty(trim($input['api_key']))) {
            handleError('API key is required');
        }
        
        $apiKey = trim($input['api_key']);
        
        // Test the API key by making a real request to Paystack
        $ch = curl_init();
        
        curl_setopt_array($ch, [
            CURLOPT_URL => 'https://api.paystack.co/bank',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $apiKey,
                'Cache-Control: no-cache'
            ],
            CURLOPT_TIMEOUT => 10,
            CURLOPT_CONNECTTIMEOUT => 5
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        
        curl_close($ch);
        
        // Handle connection errors
        if ($curlError) {
            sendResponse(false, [
                'valid' => false,
                'message' => 'Connection error while reaching Paystack: ' . $curlError
            ], 'Could not connect to Paystack', 500);
        }
        
        // Check HTTP status code
        if ($httpCode === 200) {
            // Valid key - parse response to confirm
            $data = json_decode($response, true);
            if ($data && isset($data['status']) && $data['status'] === true) {
                sendResponse(true, [
                    'valid' => true,
                    'message' => 'Paystack API key is valid and authorized'
                ], 'API key is valid');
            } else {
                sendResponse(true, [
                    'valid' => false,
                    'message' => 'Invalid response from Paystack'
                ], 'Invalid API key');
            }
        } elseif ($httpCode === 401) {
            // Invalid or unauthorized key - return success=true so frontend can read the data
            sendResponse(true, [
                'valid' => false,
                'message' => 'Invalid or unauthorized Paystack secret key'
            ], 'Invalid API key - Authentication failed (401)');
        } elseif ($httpCode === 403) {
            // Forbidden - return success=true so frontend can read the data
            sendResponse(true, [
                'valid' => false,
                'message' => 'API key forbidden - Check key permissions'
            ], 'API key forbidden (403)');
        } else {
            // Other error - return success=true so frontend can read the data
            sendResponse(true, [
                'valid' => false,
                'message' => 'Paystack returned HTTP ' . $httpCode
            ], 'API key validation failed');
        }
        break;
        
    case 'PUT':
        // Update Paystack settings (Admin only)
        validateAdminSession();
        
        $input = getJsonInput();
        
        // Check if any valid field is provided
        $validFields = ['test_public_key', 'test_secret_key', 'live_public_key', 'live_secret_key', 'use_live'];
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
            // Get or create settings row
            $stmt = $pdo->query("SELECT id FROM paystack_settings ORDER BY id DESC LIMIT 1");
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
            
            if (isset($input['live_public_key'])) {
                $updates[] = "live_public_key = ?";
                $params[] = $input['live_public_key'] ?: null;
            }
            
            if (isset($input['live_secret_key'])) {
                $updates[] = "live_secret_key = ?";
                $params[] = $input['live_secret_key'] ?: null;
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
                // Create new row
                $sql = "INSERT INTO paystack_settings SET " . implode(", ", $updates);
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
            } else {
                // Update existing row
                $sql = "UPDATE paystack_settings SET " . implode(", ", $updates) . " WHERE id = ?";
                $params[] = $existing['id'];
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
            }
            
            // Return updated settings
            $stmt = $pdo->query("SELECT test_public_key, test_secret_key, live_public_key, live_secret_key, use_live FROM paystack_settings ORDER BY id DESC LIMIT 1");
            $settings = $stmt->fetch();
            $activePublicKey = $settings['use_live'] ? $settings['live_public_key'] : $settings['test_public_key'];
            $activeSecretKey = $settings['use_live'] ? $settings['live_secret_key'] : $settings['test_secret_key'];
            
            sendResponse(true, [
                'test_public_key' => $settings['test_public_key'],
                'test_secret_key' => $settings['test_secret_key'],
                'live_public_key' => $settings['live_public_key'],
                'live_secret_key' => $settings['live_secret_key'],
                'use_live' => (bool)$settings['use_live'],
                'active_public_key' => $activePublicKey,
                'active_secret_key' => $activeSecretKey
            ], 'Paystack settings updated successfully');
        } catch (PDOException $e) {
            handleError('Failed to update Paystack settings: ' . $e->getMessage(), 500);
        }
        break;
        
    default:
        handleError('Method not allowed', 405);
        break;
}
