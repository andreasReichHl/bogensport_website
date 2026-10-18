<?php

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/jwt.php';

const MEMBER_ROLES = ['superadmin', 'admin', 'member'];
const MEMBER_AREA_LOGIN_WINDOW_MINUTES = 15;
const MEMBER_AREA_LOGIN_MAX_FAILURES_PER_IDENTIFIER = 5;
const MEMBER_AREA_LOGIN_MAX_FAILURES_PER_IP = 20;

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
    // Never echo the exception message: a raw PDOException carries SQL state, table and
    // database names. The real cause only goes to the server log.
    error_log('Member area database error: ' . $error->getMessage());
    echo '<p class="error">' . member_area_escape(member_area_database_unavailable_message()) . '</p>';
    echo '<p class="form-actions"><a class="button secondary" href="' . member_area_escape($refreshUrl) . '"><span class="material-symbols-outlined" aria-hidden="true">refresh</span> Aktualisieren</a></p>';
    echo '</div>';
    member_area_page_end();
    exit;
}

function member_area_login_attempt_hash(string $value): string
{
    // Keyed hash so the table never holds raw IP addresses or login names.
    return hash_hmac('sha256', $value, member_area_config()['jwt']['secret']);
}

function member_area_ensure_login_attempts_table(): void
{
    static $ensured = false;

    if ($ensured) {
        return;
    }

    member_area_db()->exec(
        "CREATE TABLE IF NOT EXISTS login_attempts (
          id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          identifier_hash CHAR(64) NOT NULL,
          ip_hash CHAR(64) NOT NULL,
          attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          INDEX idx_login_attempts_identifier (identifier_hash, attempted_at),
          INDEX idx_login_attempts_ip (ip_hash, attempted_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    $ensured = true;
}

function member_area_login_is_locked(string $identifierHash, string $ipHash): bool
{
    $statement = member_area_db()->prepare(
        'SELECT
           SUM(identifier_hash = ?) AS identifier_failures,
           SUM(ip_hash = ?) AS ip_failures
         FROM login_attempts
         WHERE (identifier_hash = ? OR ip_hash = ?)
           AND attempted_at >= DATE_SUB(NOW(), INTERVAL ' . MEMBER_AREA_LOGIN_WINDOW_MINUTES . ' MINUTE)'
    );
    $statement->execute([$identifierHash, $ipHash, $identifierHash, $ipHash]);
    $counts = $statement->fetch() ?: [];

    return (int)($counts['identifier_failures'] ?? 0) >= MEMBER_AREA_LOGIN_MAX_FAILURES_PER_IDENTIFIER
        || (int)($counts['ip_failures'] ?? 0) >= MEMBER_AREA_LOGIN_MAX_FAILURES_PER_IP;
}

function member_area_record_failed_login(string $identifierHash, string $ipHash): void
{
    member_area_db()->exec('DELETE FROM login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 1 DAY)');
    member_area_db()
        ->prepare('INSERT INTO login_attempts (identifier_hash, ip_hash) VALUES (?, ?)')
        ->execute([$identifierHash, $ipHash]);
}

/**
 * @return string 'ok', 'invalid' or 'locked'
 */
function member_area_login(string $identifier, string $password): string
{
    $identifier = trim($identifier);

    if ($identifier === '') {
        return 'invalid';
    }

    $normalizedIdentifier = strtolower($identifier);

    member_area_ensure_login_attempts_table();
    $identifierHash = member_area_login_attempt_hash('id|' . $normalizedIdentifier);
    $ipHash = member_area_login_attempt_hash('ip|' . (string)($_SERVER['REMOTE_ADDR'] ?? ''));

    if (member_area_login_is_locked($identifierHash, $ipHash)) {
        return 'locked';
    }

    $statement = member_area_db()->prepare(
        'SELECT id, username, email, password_hash, role, name, is_active, token_version
         FROM users
         WHERE username = ? OR email = ?
         ORDER BY CASE WHEN username = ? THEN 0 ELSE 1 END
         LIMIT 1'
    );
    $statement->execute([$normalizedIdentifier, $normalizedIdentifier, $normalizedIdentifier]);
    $user = $statement->fetch();

    // Always run password_verify, even for unknown users, so the response time does
    // not reveal which login names exist.
    $passwordHash = $user ? (string)$user['password_hash'] : password_hash('timing-dummy', PASSWORD_DEFAULT);
    $passwordMatches = password_verify($password, $passwordHash);

    if (!$user || !(int)$user['is_active'] || !$passwordMatches) {
        member_area_record_failed_login($identifierHash, $ipHash);
        return 'invalid';
    }

    member_area_db()
        ->prepare('DELETE FROM login_attempts WHERE identifier_hash = ?')
        ->execute([$identifierHash]);
    member_area_set_auth_cookie($user);

    return 'ok';
}

function member_area_logout(): void
{
    try {
        $user = member_area_current_user();

        if ($user) {
            // Bumping token_version invalidates every JWT issued so far, so a copied
            // cookie stops working the moment the user logs out.
            member_area_db()
                ->prepare('UPDATE users SET token_version = token_version + 1 WHERE id = ?')
                ->execute([(int)$user['id']]);
        }
    } catch (Throwable $error) {
        if (!member_area_is_database_exception($error)) {
            throw $error;
        }

        error_log('Member area logout could not invalidate token: ' . $error->getMessage());
    }

    member_area_clear_auth_cookie();
}

function member_area_csrf_token(): string
{
    $config = member_area_config()['jwt'];

    // Bound to the current auth cookie: it changes with every new login and cannot be
    // computed without the server secret, so no extra session storage is needed.
    return hash_hmac('sha256', 'csrf|' . (string)($_COOKIE[$config['cookie_name']] ?? ''), $config['secret']);
}

function member_area_csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(member_area_csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

function member_area_require_csrf(): void
{
    $postedToken = $_POST['csrf_token'] ?? '';

    if (is_string($postedToken) && $postedToken !== '' && hash_equals(member_area_csrf_token(), $postedToken)) {
        return;
    }

    http_response_code(400);
    require_once __DIR__ . '/view.php';
    member_area_page_start('Anfrage abgelehnt');
    echo '<div class="panel">';
    echo '<h1>Anfrage abgelehnt</h1>';
    echo '<p class="error">Das Formular ist abgelaufen oder ungültig. Bitte lade die Seite neu und versuche es erneut.</p>';
    echo '<p class="form-actions"><a class="button secondary" href="/mitglieder/dashboard.php">Zum Dashboard</a></p>';
    echo '</div>';
    member_area_page_end();
    exit;
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

    // Every authenticated POST goes through here, so CSRF is enforced centrally and a
    // new form cannot forget it.
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        member_area_require_csrf();
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
