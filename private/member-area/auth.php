<?php

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/jwt.php';

const MEMBER_ROLES = ['superadmin', 'admin', 'member'];

function member_area_cookie_options(int $expires): array
{
    $config = member_area_config()['jwt'];

    return [
        'expires' => $expires,
        'path' => '/mitglieder',
        'secure' => $config['secure_cookie'],
        'httponly' => true,
        'samesite' => 'Lax',
    ];
}

function member_area_set_auth_cookie(array $user): void
{
    $config = member_area_config()['jwt'];
    $now = time();
    $expires = $now + $config['ttl_seconds'];
    $payload = [
        'iss' => $config['issuer'],
        'aud' => $config['audience'],
        'iat' => $now,
        'nbf' => $now,
        'exp' => $expires,
        'sub' => (string)$user['id'],
        'username' => $user['username'],
        'email' => $user['email'],
        'name' => $user['name'] ?? '',
        'role' => $user['role'],
        'token_version' => (int)$user['token_version'],
    ];

    setcookie($config['cookie_name'], jwt_sign($payload), member_area_cookie_options($expires));
}

function member_area_clear_auth_cookie(): void
{
    $config = member_area_config()['jwt'];
    setcookie($config['cookie_name'], '', member_area_cookie_options(time() - 3600));
}

function member_area_render_database_unavailable(Throwable $error): void
{
    if (!member_area_is_database_exception($error)) {
        throw $error;
    }

    http_response_code(503);
    require_once __DIR__ . '/view.php';
    $refreshUrl = (string)($_SERVER['REQUEST_URI'] ?? '/mitglieder/login.php');

    member_area_page_start('Datenbank nicht erreichbar');
    echo '<div class="panel">';
    echo '<h1>Mitgliederbereich nicht erreichbar</h1>';
    echo '<p class="error">' . member_area_escape($error->getMessage()) . '</p>';
    echo '<p class="form-actions"><a class="button secondary" href="' . member_area_escape($refreshUrl) . '"><span class="material-symbols-outlined" aria-hidden="true">refresh</span> Aktualisieren</a></p>';
    echo '</div>';
    member_area_page_end();
    exit;
}

function member_area_login(string $identifier, string $password): bool
{
    $identifier = trim($identifier);

    if ($identifier === '') {
        return false;
    }

    $normalizedIdentifier = strtolower($identifier);

    $statement = member_area_db()->prepare(
        'SELECT id, username, email, password_hash, role, name, is_active, token_version
         FROM users
         WHERE username = ? OR email = ?
         ORDER BY CASE WHEN username = ? THEN 0 ELSE 1 END
         LIMIT 1'
    );
    $statement->execute([$normalizedIdentifier, $normalizedIdentifier, $normalizedIdentifier]);
    $users = $statement->fetchAll();

    if (count($users) === 0) {
        return false;
    }

    $user = $users[0];

    if (!(int)$user['is_active'] || !password_verify($password, $user['password_hash'])) {
        return false;
    }

    member_area_set_auth_cookie($user);

    return true;
}

function member_area_current_user(): ?array
{
    $config = member_area_config()['jwt'];
    $token = $_COOKIE[$config['cookie_name']] ?? '';

    if ($token === '') {
        return null;
    }

    try {
        $payload = jwt_verify($token);
    } catch (Throwable $error) {
        member_area_clear_auth_cookie();
        return null;
    }

    $statement = member_area_db()->prepare(
        'SELECT id, username, email, role, name, is_active, token_version FROM users WHERE id = ? LIMIT 1'
    );
    $statement->execute([(int)$payload['sub']]);
    $user = $statement->fetch();

    if (
        !$user ||
        !(int)$user['is_active'] ||
        (int)$user['token_version'] !== (int)($payload['token_version'] ?? 0) ||
        $user['role'] !== ($payload['role'] ?? '')
    ) {
        member_area_clear_auth_cookie();
        return null;
    }

    return $user;
}

function member_area_require_user(): array
{
    try {
        $user = member_area_current_user();
    } catch (Throwable $error) {
        if (member_area_is_database_exception($error)) {
            member_area_render_database_unavailable($error);
        }

        throw $error;
    }

    if (!$user) {
        header('Location: /mitglieder/login.php');
        exit;
    }

    return $user;
}

function member_area_require_role(array $allowedRoles): array
{
    $user = member_area_require_user();

    if (!in_array($user['role'], $allowedRoles, true)) {
        http_response_code(403);
        echo 'Zugriff verweigert.';
        exit;
    }

    return $user;
}
