<?php

require_once __DIR__ . '/db.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

try {
    $health = member_area_db_health_check();

    echo 'Database connection: OK' . PHP_EOL;
    echo 'Users table: ' . ($health['users_table_ok'] ? 'OK' : 'MISSING') . PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR, 'Database connection: FAILED' . PHP_EOL);
    fwrite(STDERR, $error->getMessage() . PHP_EOL);
    exit(1);
}
