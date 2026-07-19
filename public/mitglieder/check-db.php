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
    $statement = member_area_db()->query('SELECT 1 AS connection_ok');
    $result = $statement->fetch();

    if ((int)($result['connection_ok'] ?? 0) !== 1) {
        throw new RuntimeException('Unexpected database response');
    }

    $tableStatement = member_area_db()->query("SHOW TABLES LIKE 'users'");
    $hasUsersTable = (bool)$tableStatement->fetch();

    echo "Database connection: OK\n";
    echo 'Users table: ' . ($hasUsersTable ? 'OK' : 'MISSING') . "\n";
} catch (Throwable $error) {
    http_response_code(500);
    echo "Database connection: FAILED\n";
    echo $error->getMessage() . "\n";
}
