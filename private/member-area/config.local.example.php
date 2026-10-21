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
    // false until the online application goes live (beitritt-*.php then answer 404).
    'enabled' => true,
    'satzung_version' => '2009-10-02',
    'data_key' => 'hhNYcvJDKvdNznPL26gvxFw3PgWVxCOEM+mKvGMg96c=',
    'signature_key' => '1ENls6HHtfzqWFStDBZ9bOC1/prc1doHM/Re5poMb2s=',
    'confirm_ttl_hours' => 48,
    'minimum_age' => 8,
    // easyVerein group id => Aufnahmegebühr in EUR (Stand: Mitgliedsantrag-PDF).
    'admission_fees' => 
    array (
      545567211 => 50.00, // Aktive Erwachsene
      545568324 => 30.00, // Kinder & Jugendliche
      545572428 => 30.00, // Auszubildende & Studierende
      545587002 => 0.00,  // Passive Mitglieder (Aufnahmegebühr entfällt)
    ),
    'creditor_id' => 'DE66ZZZ00001047545',
    // Vite dev server; links in mails point here locally.
    'site_url' => 'http://localhost:5173',
    // Receives a copy of every confirmed application (with PDF).
    'board_email' => 'vorstand@badoldesloer-bogensport.de',
    // Receives the Probetraining requests from the website (sent via the same mailbox as below).
    'trial_training_email' => 'info@badoldesloer-bogensport.de',
    // Locally only dry-run: nothing is written to the (production!) easyVerein. 'live' in production.
    'easyverein_sync' => 'dry-run',
    'sync_max_attempts' => 5,
    'retention_days' => 90,
    // Locally: Mailpit from docker-compose (no auth, no TLS) - mails show up at
    // http://localhost:8025 instead of being delivered. Production: the Strato mailbox
    // beitritt@badoldesloer-bogensport.de - 'smtp_host' => 'smtp.strato.de', 'smtp_port' => 465,
    // 'smtp_secure' => 'ssl', 'smtp_user' => the full address, 'smtp_password' => its password.
    'mail' => 
    array (
      'from' => 'beitritt@badoldesloer-bogensport.de',
      'from_name' => 'Bad Oldesloer Bogensport e.V.',
      'smtp_host' => '127.0.0.1',
      'smtp_port' => 1025,
      'smtp_secure' => '',
      'smtp_user' => '',
      'smtp_password' => '',
    ),
  ),
);
