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
      <span class="password-field">
        <input id="login_password" name="password" type="password" autocomplete="current-password" required>
        <button type="button" class="password-toggle" data-password-toggle="login_password" aria-label="Passwort anzeigen" aria-pressed="false">
          <span class="material-symbols-outlined" aria-hidden="true">visibility_off</span>
        </button>
      </span>
    </label>
    <button type="submit">Einloggen</button>
  </form>
</div>
<script>
  document.querySelectorAll("[data-password-toggle]").forEach((button) => {
    const input = document.getElementById(button.dataset.passwordToggle);
    const icon = button.querySelector(".material-symbols-outlined");

    if (!input) {
      return;
    }

    input.type = "password";
    button.setAttribute("aria-label", "Passwort anzeigen");
    button.setAttribute("aria-pressed", "false");

    if (icon) {
      icon.textContent = "visibility_off";
    }

    button.addEventListener("click", () => {
      const showPassword = input.type === "password";

      input.type = showPassword ? "text" : "password";
      button.setAttribute("aria-label", showPassword ? "Passwort verbergen" : "Passwort anzeigen");
      button.setAttribute("aria-pressed", showPassword ? "true" : "false");

      if (icon) {
        icon.textContent = showPassword ? "visibility" : "visibility_off";
      }
    });
  });
</script>
<?php
member_area_page_end();
