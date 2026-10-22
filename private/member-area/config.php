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
    // Deliberately independent of any request data (Host header, SERVER_NAME, etc.):
    // those are attacker-controllable on many hosting setups, and using them here would
    // let a spoofed request switch a production deployment onto config.local.example.php,
    // whose JWT secret is committed to version control. PHP_SAPI is set by how the process
    // was started and cannot be influenced by an incoming HTTP request.
    if (PHP_SAPI === 'cli-server') {
        return true;
    }

    // The CLI also runs in production (the Strato cron job), so it only counts as local in
    // a development checkout. The deployment holds dist/ and private/, never package.json.
    return PHP_SAPI === 'cli' && is_file(dirname(__DIR__, 2) . '/package.json');
}
