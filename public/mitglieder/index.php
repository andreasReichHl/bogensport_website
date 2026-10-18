<?php

require_once __DIR__ . '/bootstrap.php';
require_once MEMBER_AREA_PRIVATE_PATH . '/auth.php';

if (member_area_current_user()) {
    header('Location: /mitglieder/dashboard.php');
    exit;
}

header('Location: /mitglieder/login.php');
exit;
