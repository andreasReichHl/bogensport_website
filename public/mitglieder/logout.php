<?php

require_once __DIR__ . '/bootstrap.php';
require_once MEMBER_AREA_PRIVATE_PATH . '/auth.php';

member_area_clear_auth_cookie();
header('Location: /mitglieder/login.php');
exit;
