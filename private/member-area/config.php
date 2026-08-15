<?php

function member_area_config(): array
{
    static $config = null;

    if ($config !== null) {
        return $config;
    }

    $configuredConfigFile = getenv('MEMBER_AREA_CONFIG_FILE');
    $isLocalEnvironment = member_area_is_local_environment();
    $configFile = $configuredConfigFile ?: __DIR__ . ($isLocalEnvironment ? '/config.local.example.php' : '/config.local.php');

    if (!is_file($configFile)) {
        throw new RuntimeException('Missing member area configuration file');
    }

    $config = require $configFile;

    if (
        empty($config['db']['host']) ||
        empty($config['db']['name']) ||
        empty($config['db']['user']) ||
        empty($config['jwt']['secret']) ||
        $config['jwt']['secret'] === 'CHANGE_ME_TO_A_LONG_RANDOM_SECRET'
    ) {
        throw new RuntimeException('Member area configuration is incomplete');
    }

    $config['db']['port'] = (int)($config['db']['port'] ?? 3306);
    $config['db']['charset'] = $config['db']['charset'] ?? 'utf8mb4';
    $config['jwt']['ttl_seconds'] = (int)($config['jwt']['ttl_seconds'] ?? 7200);
    $config['jwt']['cookie_name'] = $config['jwt']['cookie_name'] ?? 'bobs_member_token';
    $config['jwt']['secure_cookie'] = (bool)($config['jwt']['secure_cookie'] ?? true);
    $config['setup']['token_hash'] = $config['setup']['token_hash'] ?? '';
    $config['diagnostics']['token_hash'] = $config['diagnostics']['token_hash'] ?? '';

    return $config;
}

function member_area_is_local_environment(): bool
{
    $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '';

    if ($host === '') {
        return PHP_SAPI === 'cli';
    }

    $host = strtolower(explode(':', $host)[0]);

    return in_array($host, ['localhost', '127.0.0.1', '::1'], true);
}
