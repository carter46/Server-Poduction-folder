<?php
/**
 * license_settings.mode_off_bank_dashboards — per-bank Mode OFF dashboard toggles (JSON map).
 */

return [
    'id' => '2026_09_07_140000_mode_off_bank_dashboards',
    'description' => 'license_settings.mode_off_bank_dashboards JSON map for Mode OFF dashboards',
    'up' => function (PDO $pdo) {
        DatabaseAutoMigrate::ensureColumn(
            $pdo,
            'license_settings',
            'mode_off_bank_dashboards',
            '`mode_off_bank_dashboards` TEXT NULL'
        );
    },
];
