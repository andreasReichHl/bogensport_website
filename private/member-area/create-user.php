<?php

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

[$script, $username, $email, $password, $role, $name] = array_pad($argv, 6, null);

if (
    !$username ||
    !preg_match('/^[a-z0-9._-]{3,40}$/', strtolower(trim($username))) ||
    !$email ||
    !filter_var($email, FILTER_VALIDATE_EMAIL) ||
    !$password ||
    !$role ||
    !in_array($role, MEMBER_ROLES, true)
) {
    fwrite(STDERR, "Usage: php private/member-area/create-user.php username email password superadmin|admin|member \"Name\"\n");
    exit(1);
}

$statement = member_area_db()->prepare(
    'INSERT INTO users (username, email, password_hash, role, name) VALUES (?, ?, ?, ?, ?)'
);
$statement->execute([
    strtolower(trim($username)),
    strtolower(trim($email)),
    password_hash($password, PASSWORD_DEFAULT),
    $role,
    $name,
]);

echo "User created: {$username}\n";
