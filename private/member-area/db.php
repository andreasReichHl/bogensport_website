<?php

require_once __DIR__ . '/config.php';

class MemberAreaDatabaseException extends RuntimeException
{
}

function member_area_database_unavailable_message(): string
{
    return 'Die Datenbank ist aktuell nicht erreichbar. Bitte versuche es später erneut.';
}

function member_area_is_database_exception(Throwable $error): bool
{
    return $error instanceof MemberAreaDatabaseException || $error instanceof PDOException;
}

function member_area_db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $config = member_area_config()['db'];
    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=%s',
        $config['host'],
        $config['port'],
        $config['name'],
        $config['charset']
    );

    try {
        $pdo = new PDO($dsn, $config['user'], $config['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    } catch (Throwable $error) {
        error_log('Member area database connection failed: ' . $error->getMessage());
        throw new MemberAreaDatabaseException(member_area_database_unavailable_message(), 0, $error);
    }

    return $pdo;
}

function member_area_db_health_check(): array
{
    $statement = member_area_db()->query('SELECT 1 AS connection_ok');
    $result = $statement->fetch();

    if ((int)($result['connection_ok'] ?? 0) !== 1) {
        throw new RuntimeException('Unexpected database response');
    }

    $tableStatement = member_area_db()->query("SHOW TABLES LIKE 'users'");

    return [
        'connection_ok' => true,
        'users_table_ok' => (bool)$tableStatement->fetch(),
    ];
}
