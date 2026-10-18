<?php

require_once __DIR__ . '/bootstrap.php';
require_once MEMBER_AREA_PRIVATE_PATH . '/config.php';
require_once MEMBER_AREA_PRIVATE_PATH . '/db.php';

header('Content-Type: text/plain; charset=utf-8');

$config = member_area_config();
$token = $_GET['token'] ?? '';
$tokenHash = $config['diagnostics']['token_hash'] ?? '';

if ($tokenHash === '' || !hash_equals($tokenHash, hash('sha256', $token))) {
    http_response_code(404);
    echo "Not found\n";
    exit;
}

try {
    $health = member_area_db_health_check();

    echo "Database connection: OK\n";
    echo 'Users table: ' . ($health['users_table_ok'] ? 'OK' : 'MISSING') . "\n";
} catch (Throwable $error) {
    // Log the real error server-side only - this endpoint is reachable over HTTP (behind a
    // token gate), so echoing driver/host details back in the response would hand an
    // attacker who guesses or leaks the token a DB-error-message oracle.
    error_log('check-db.php failed: ' . $error->getMessage());
    http_response_code(500);
    echo "Database connection: FAILED\n";
}
