<?php

return array (
  'db' => 
  array (
    'host' => '127.0.0.1',
    'port' => 3306,
    'name' => 'dbs15892878',
    'user' => 'root',
    'password' => '',
    'charset' => 'utf8mb4',
  ),
  'jwt' => 
  array (
    'secret' => 'DEV_ONLY_LOCAL_MEMBER_AREA_SECRET_CHANGE_BEFORE_PRODUCTION_1234567890',
    'issuer' => 'badoldesloer-bogensport.de',
    'audience' => 'badoldesloer-bogensport.de',
    'ttl_seconds' => 7200,
    'cookie_name' => 'bobs_member_token',
    'secure_cookie' => false,
  ),
  'setup' => 
  array (
    'token_hash' => '',
  ),
  'diagnostics' => 
  array (
    'token_hash' => '',
  ),
);
