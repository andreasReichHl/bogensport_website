<?php

require_once __DIR__ . '/db.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
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

    echo 'Database connection: OK' . PHP_EOL;
    echo 'Users table: ' . ($hasUsersTable ? 'OK' : 'MISSING') . PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR, 'Database connection: FAILED' . PHP_EOL);
    fwrite(STDERR, $error->getMessage() . PHP_EOL);
    exit(1);
}
