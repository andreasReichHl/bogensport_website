<?php

return [
    'db' => [
        'host' => 'rdbms.strato.de',
        'port' => 3306,
        'name' => 'DB_NAME',
        'user' => 'DB_USER',
        'password' => 'DB_PASSWORD',
        'charset' => 'utf8mb4',
    ],
    'jwt' => [
        'secret' => 'CHANGE_ME_TO_A_LONG_RANDOM_SECRET',
        'issuer' => 'badoldesloer-bogensport.de',
        'audience' => 'badoldesloer-bogensport.de',
        'ttl_seconds' => 7200,
        'cookie_name' => 'bobs_member_token',
        'secure_cookie' => true,
    ],
    'setup' => [
        'token_hash' => '',
    ],
    'diagnostics' => [
        'token_hash' => '',
    ],
];
