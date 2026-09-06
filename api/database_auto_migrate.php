<?php
/**
 * Idempotent database auto-migrator (Tranzit).
 *
 * Runs pending PHP migrations from database/auto-migrations/ when an admin
 * logs in or hits any admin-gated API (validateAdminSession).
 *
 * Add a new file under database/auto-migrations/ named like:
 *   2026_09_06_220000_short_name.php
 * Returning:
 *   ['id' => '...', 'description' => '...', 'up' => function (PDO $pdo) { ... }]
 *
 * Inspired by Axion Trust Bank's admin-gated auto-migrate pattern; adapted for PDO.
 */

class DatabaseAutoMigrate
{
    private static $ranThisRequest = false;

    /** @var PDO */
    private $pdo;

    /** @var string */
    private $dir;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->dir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'auto-migrations';
    }

    /**
     * Run once per request. Returns summary array.
     *
     * @param int|null $appliedBy admin_users.id
     * @return array{ran:bool,applied:array,failed:array,skipped:int,errors:array}
     */
    public function run($appliedBy = null)
    {
        if (self::$ranThisRequest) {
            return $_SESSION['auto_migration_last_result'] ?? [
                'ran' => false,
                'applied' => [],
                'failed' => [],
                'skipped' => 0,
                'errors' => [],
            ];
        }
        self::$ranThisRequest = true;

        $result = [
            'ran' => true,
            'applied' => [],
            'failed' => [],
            'skipped' => 0,
            'errors' => [],
        ];

        try {
            $this->ensureTrackingTable();
            $migrations = $this->discoverMigrations();
            $appliedIds = $this->getSuccessfullyAppliedIds();

            foreach ($migrations as $migration) {
                $id = (string)$migration['id'];
                if (isset($appliedIds[$id])) {
                    $result['skipped']++;
                    continue;
                }

                try {
                    $up = $migration['up'];
                    if (!is_callable($up)) {
                        throw new Exception('Migration up() is not callable');
                    }
                    $up($this->pdo);
                    $this->recordSuccess($id, $migration['description'] ?? $id, $appliedBy);
                    $result['applied'][] = [
                        'id' => $id,
                        'description' => $migration['description'] ?? $id,
                    ];
                } catch (Throwable $e) {
                    $msg = $e->getMessage();
                    $this->recordFailure($id, $migration['description'] ?? $id, $msg, $appliedBy);
                    $result['failed'][] = [
                        'id' => $id,
                        'description' => $migration['description'] ?? $id,
                        'error' => $msg,
                    ];
                    $result['errors'][] = "{$id}: {$msg}";
                    error_log("Auto-migration failed [{$id}]: {$msg}");
                }
            }
        } catch (Throwable $e) {
            $result['errors'][] = 'Migrator bootstrap failed: ' . $e->getMessage();
            error_log('DatabaseAutoMigrate bootstrap error: ' . $e->getMessage());
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['auto_migration_last_result'] = $result;
            if (!empty($result['failed']) || !empty($result['errors'])) {
                $_SESSION['auto_migration_errors'] = $result['errors'];
            } else {
                unset($_SESSION['auto_migration_errors']);
            }
            if (!empty($result['applied'])) {
                $_SESSION['auto_migration_success'] = array_map(static function ($row) {
                    return ($row['description'] ?? $row['id']) . ' (' . $row['id'] . ')';
                }, $result['applied']);
            }
        }

        return $result;
    }

    private function ensureTrackingTable()
    {
        $this->pdo->exec(
            "CREATE TABLE IF NOT EXISTS `auto_migrations` (
                `id` varchar(191) NOT NULL,
                `description` varchar(255) DEFAULT NULL,
                `status` enum('success','failed') NOT NULL DEFAULT 'success',
                `error_message` text DEFAULT NULL,
                `applied_by` int(11) DEFAULT NULL,
                `applied_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_status` (`status`),
                KEY `idx_applied_at` (`applied_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    private function getSuccessfullyAppliedIds()
    {
        $ids = [];
        $stmt = $this->pdo->query("SELECT id FROM auto_migrations WHERE status = 'success'");
        if ($stmt === false) {
            throw new Exception('Failed reading auto_migrations');
        }
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $ids[$row['id']] = true;
        }
        return $ids;
    }

    private function discoverMigrations()
    {
        if (!is_dir($this->dir)) {
            @mkdir($this->dir, 0755, true);
        }

        $files = glob($this->dir . DIRECTORY_SEPARATOR . '*.php') ?: [];
        sort($files, SORT_STRING);

        $migrations = [];
        foreach ($files as $file) {
            if (basename($file) === 'index.php') {
                continue;
            }
            $data = include $file;
            if (!is_array($data) || empty($data['id']) || !isset($data['up'])) {
                throw new Exception('Invalid migration file: ' . basename($file));
            }
            $migrations[] = $data;
        }
        return $migrations;
    }

    private function recordSuccess($id, $description, $appliedBy)
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO auto_migrations (id, description, status, error_message, applied_by, applied_at)
             VALUES (?, ?, 'success', NULL, ?, NOW())
             ON DUPLICATE KEY UPDATE
                description = VALUES(description),
                status = 'success',
                error_message = NULL,
                applied_by = VALUES(applied_by),
                applied_at = NOW()"
        );
        $stmt->execute([$id, $description, $appliedBy]);
    }

    private function recordFailure($id, $description, $error, $appliedBy)
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO auto_migrations (id, description, status, error_message, applied_by, applied_at)
             VALUES (?, ?, 'failed', ?, ?, NOW())
             ON DUPLICATE KEY UPDATE
                description = VALUES(description),
                status = 'failed',
                error_message = VALUES(error_message),
                applied_by = VALUES(applied_by),
                updated_at = NOW()"
        );
        $stmt->execute([$id, $description, $error, $appliedBy]);
    }

    /** Add a column if missing. Returns true if added. */
    public static function ensureColumn(PDO $pdo, $table, $column, $definitionSql)
    {
        $table = preg_replace('/[^a-zA-Z0-9_]/', '', (string)$table);
        $column = preg_replace('/[^a-zA-Z0-9_]/', '', (string)$column);

        $stmt = $pdo->prepare(
            "SELECT COUNT(*) AS cnt
             FROM information_schema.columns
             WHERE table_schema = DATABASE()
               AND table_name = ?
               AND column_name = ?"
        );
        $stmt->execute([$table, $column]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!empty($row['cnt'])) {
            return false;
        }
        $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN {$definitionSql}");
        return true;
    }

    /** Run SQL and throw on PDOException (already throws) or false exec. */
    public static function execOrFail(PDO $pdo, $sql, $label = null)
    {
        try {
            $ok = $pdo->exec($sql);
            if ($ok === false) {
                $err = $pdo->errorInfo();
                throw new Exception(($label ?: 'Query failed') . ': ' . ($err[2] ?? substr($sql, 0, 120)));
            }
        } catch (PDOException $e) {
            throw new Exception(($label ?: 'Query failed') . ': ' . $e->getMessage(), 0, $e);
        }
        return true;
    }
}

/**
 * Run auto-migrations for the current admin session.
 * Safe to call multiple times per request (once-per-request guard).
 *
 * @param PDO $pdo
 * @param int|null $adminUserId
 * @return array|null
 */
function runAdminDatabaseAutoMigrations(PDO $pdo, $adminUserId = null)
{
    try {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            // Caller may not have started session yet; migrator still works without session banners.
        }
        $adminUserId = $adminUserId ?? ($_SESSION['admin_id'] ?? null);
        $migrator = new DatabaseAutoMigrate($pdo);
        return $migrator->run($adminUserId ? (int)$adminUserId : null);
    } catch (Throwable $e) {
        error_log('runAdminDatabaseAutoMigrations error: ' . $e->getMessage());
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['auto_migration_errors'] = ['Migrator error: ' . $e->getMessage()];
        }
        return [
            'ran' => false,
            'applied' => [],
            'failed' => [],
            'skipped' => 0,
            'errors' => [$e->getMessage()],
        ];
    }
}
