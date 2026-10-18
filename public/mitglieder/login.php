<?php

require_once __DIR__ . '/bootstrap.php';
require_once MEMBER_AREA_PRIVATE_PATH . '/auth.php';
require_once MEMBER_AREA_PRIVATE_PATH . '/view.php';

$error = '';
$databaseUnavailable = false;

try {
    if (member_area_current_user()) {
        header('Location: /mitglieder/dashboard.php');
        exit;
    }
} catch (Throwable $errorObject) {
    if (!member_area_is_database_exception($errorObject)) {
        throw $errorObject;
    }

    error_log('Member area login check failed: ' . $errorObject->getMessage());
    http_response_code(503);
    $error = member_area_database_unavailable_message();
    $databaseUnavailable = true;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = $_POST['identifier'] ?? '';
    $password = $_POST['password'] ?? '';

    try {
        $loginResult = member_area_login((string)$identifier, (string)$password);

        if ($loginResult === 'ok') {
            header('Location: /mitglieder/dashboard.php');
            exit;
        }

        if ($loginResult === 'locked') {
            http_response_code(429);
            $error = 'Zu viele fehlgeschlagene Anmeldeversuche. Bitte warte ' . MEMBER_AREA_LOGIN_WINDOW_MINUTES . ' Minuten und versuche es dann erneut.';
        } else {
            $error = 'Loginname oder Passwort ist ungültig.';
        }
    } catch (Throwable $errorObject) {
        if (!member_area_is_database_exception($errorObject)) {
            throw $errorObject;
        }

        error_log('Member area login failed: ' . $errorObject->getMessage());
        http_response_code(503);
        $error = member_area_database_unavailable_message();
        $databaseUnavailable = true;
    }
}

member_area_page_start('Login');
?>
<div class="panel">
  <h1>Mitgliederbereich</h1>
  <?php if ($error): ?>
    <p class="error"><?= member_area_escape($error) ?></p>
    <?php if ($databaseUnavailable): ?>
      <p class="form-actions">
        <a class="button secondary" href="/mitglieder/login.php">
          <span class="material-symbols-outlined" aria-hidden="true">refresh</span>
          Aktualisieren
        </a>
      </p>
    <?php endif; ?>
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
