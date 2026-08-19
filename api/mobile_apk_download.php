<?php
/**
 * Admin-only APK download. Streams private file — not a public static URL.
 */
define('MOBILE_SKIP_JSON_HEADERS', true);
require_once 'config.php';

validateAdminSession();

$dir = defined('MOBILE_APK_PRIVATE_DIR') ? MOBILE_APK_PRIVATE_DIR : (__DIR__ . '/private_mobile');
$filename = defined('MOBILE_APK_FILENAME') ? MOBILE_APK_FILENAME : 'banking-companion.apk';
$path = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . $filename;

if (!is_readable($path) || filesize($path) < 10000) {
    // Late JSON error (session already validated)
    header('Content-Type: application/json; charset=utf-8');
    handleError('No published APK available yet. Build and publish a successful release first.', 404);
}

header('Content-Type: application/vnd.android.package-archive');
header('Content-Length: ' . filesize($path));
header('Content-Disposition: attachment; filename="banking-companion.apk"');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('X-Content-Type-Options: nosniff');

readfile($path);
exit();
