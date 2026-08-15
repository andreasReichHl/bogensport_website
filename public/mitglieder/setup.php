<?php

require_once __DIR__ . '/bootstrap.php';
require_once MEMBER_AREA_PRIVATE_PATH . '/auth.php';
require_once MEMBER_AREA_PRIVATE_PATH . '/db.php';
require_once MEMBER_AREA_PRIVATE_PATH . '/password.php';
require_once MEMBER_AREA_PRIVATE_PATH . '/view.php';

$config = member_area_config();
$token = $_GET['token'] ?? $_POST['token'] ?? '';
$tokenHash = $config['setup']['token_hash'] ?? '';

if ($tokenHash === '' || !hash_equals($tokenHash, hash('sha256', $token))) {
    http_response_code(404);
    echo 'Not found';
    exit;
}

$countStatement = member_area_db()->query('SELECT COUNT(*) AS user_count FROM users');
$userCount = (int)($countStatement->fetch()['user_count'] ?? 0);

if ($userCount > 0) {
    http_response_code(404);
    echo 'Setup is disabled.';
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = strtolower(trim($_POST['username'] ?? ''));
    $email = strtolower(trim($_POST['email'] ?? ''));
    $name = trim($_POST['name'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!preg_match('/^[a-z0-9._-]{3,40}$/', $username)) {
        $error = 'Der Benutzername muss 3 bis 40 Zeichen lang sein und darf nur Buchstaben, Zahlen, Punkt, Unterstrich und Bindestrich enthalten.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Bitte eine gültige E-Mail-Adresse eingeben.';
    } elseif (($passwordError = member_area_validate_password($password)) !== null) {
        $error = $passwordError;
    } else {
        $statement = member_area_db()->prepare(
            'INSERT INTO users (username, email, password_hash, role, name) VALUES (?, ?, ?, ?, ?)'
        );
        $statement->execute([
            $username,
            $email,
            password_hash($password, PASSWORD_DEFAULT),
            'superadmin',
            $name,
        ]);

        member_area_login($username, $password);
        header('Location: /mitglieder/dashboard.php');
        exit;
    }
}

member_area_page_start('Setup');
?>
<div class="panel">
  <h1>Ersten Superadmin anlegen</h1>
  <?php if ($error): ?>
    <p class="error"><?= member_area_escape($error) ?></p>
  <?php endif; ?>
  <form method="post" action="/mitglieder/setup.php">
    <input type="hidden" name="token" value="<?= member_area_escape($token) ?>">
    <label>
      Benutzername
      <input name="username" autocomplete="username" pattern="[A-Za-z0-9._-]{3,40}" required>
    </label>
    <label>
      Name
      <input name="name" autocomplete="name" required>
    </label>
    <label>
      E-Mail
      <input name="email" type="email" autocomplete="email" required>
    </label>
    <label>
      Passwort
      <input name="password" type="password" autocomplete="new-password" minlength="8" required>
      <span class="field-hint"><?= member_area_escape(member_area_password_requirements_text()) ?></span>
    </label>
    <button type="submit">Superadmin erstellen</button>
  </form>
</div>
<?php
member_area_page_end();
