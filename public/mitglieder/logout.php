<?php

require_once __DIR__ . '/bootstrap.php';
require_once MEMBER_AREA_PRIVATE_PATH . '/auth.php';

// Logout only via POST with CSRF token, so a foreign page cannot log users out with a
// plain link or image tag.
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Location: /mitglieder/dashboard.php');
    exit;
}

member_area_require_user();
member_area_logout();
header('Location: /mitglieder/login.php');
exit;
