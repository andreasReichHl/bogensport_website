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
  // Online-Mitgliedsbeitritt (private/membership/). DEV-ONLY keys - generate own ones for
  // production: php -r 'echo base64_encode(random_bytes(32)), PHP_EOL;'
  'membership' => 
  array (
    'satzung_version' => '2009-10-02',
    'data_key' => 'hhNYcvJDKvdNznPL26gvxFw3PgWVxCOEM+mKvGMg96c=',
    'signature_key' => '1ENls6HHtfzqWFStDBZ9bOC1/prc1doHM/Re5poMb2s=',
    'confirm_ttl_hours' => 48,
    'minimum_age' => 8,
    'creditor_id' => '',
    'board_email' => 'beitritt@badoldesloer-bogensport.de',
    'mail' => 
    array (
      'from' => 'beitritt@badoldesloer-bogensport.de',
      'from_name' => 'Bad Oldesloer Bogensport e.V.',
      'smtp_host' => '',
      'smtp_port' => 587,
      'smtp_user' => 'beitritt@badoldesloer-bogensport.de',
      'smtp_password' => '',
    ),
  ),
);
