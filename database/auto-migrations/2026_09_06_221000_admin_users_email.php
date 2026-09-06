<?php
/**
 * Ensure admin_users.email exists (nullable) for email-or-username login.
 */

return [
    'id' => '2026_09_06_221000_admin_users_email',
    'description' => 'admin_users.email column for email login support',
    'up' => function (PDO $pdo) {
        DatabaseAutoMigrate::ensureColumn(
            $pdo,
            'admin_users',
            'email',
            '`email` VARCHAR(255) DEFAULT NULL'
        );
    },
];
