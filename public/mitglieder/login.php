<?php

require_once __DIR__ . '/bootstrap.php';
require_once MEMBER_AREA_PRIVATE_PATH . '/auth.php';
require_once MEMBER_AREA_PRIVATE_PATH . '/view.php';

if (member_area_current_user()) {
    header('Location: /mitglieder/dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = $_POST['identifier'] ?? '';
    $password = $_POST['password'] ?? '';

    if (member_area_login($identifier, $password)) {
        header('Location: /mitglieder/dashboard.php');
        exit;
    }

    $error = 'Loginname oder Passwort ist ungültig.';
}

member_area_page_start('Login');
?>
<div class="panel">
  <h1>Mitgliederbereich</h1>
  <?php if ($error): ?>
    <p class="error"><?= member_area_escape($error) ?></p>
  <?php endif; ?>
  <form method="post" action="/mitglieder/login.php">
    <label>
      Benutzername oder E-Mail
      <input name="identifier" autocomplete="username" required>
    </label>
    <label>
      Passwort
      <input name="password" type="password" autocomplete="current-password" required>
    </label>
    <button type="submit">Einloggen</button>
  </form>
</div>
<?php
member_area_page_end();
