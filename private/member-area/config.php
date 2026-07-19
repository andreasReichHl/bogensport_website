<?php

function member_area_config(): array
{
    static $config = null;

    if ($config !== null) {
        return $config;
    }

    $configFile = __DIR__ . '/config.local.php';

    if (!is_file($configFile)) {
        throw new RuntimeException('Missing member area config.local.php');
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
