<?php

require_once __DIR__ . '/bootstrap.php';
require_once MEMBER_AREA_PRIVATE_PATH . '/auth.php';
require_once MEMBER_AREA_PRIVATE_PATH . '/password.php';
require_once MEMBER_AREA_PRIVATE_PATH . '/view.php';

$user = member_area_require_user();
$profile = [
    'id' => (int)$user['id'],
    'username' => (string)$user['username'],
    'email' => (string)$user['email'],
    'name' => (string)($user['name'] ?? ''),
    'role' => (string)$user['role'],
    'token_version' => (int)$user['token_version'],
];
$error = '';

function member_area_verify_current_password(int $userId, string $password): bool
{
    if ($password === '') {
        return false;
    }

    $statement = member_area_db()->prepare('SELECT password_hash FROM users WHERE id = ? LIMIT 1');
    $statement->execute([$userId]);
    $passwordHash = $statement->fetchColumn();

    return is_string($passwordHash) && password_verify($password, $passwordHash);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = strtolower(trim($_POST['username'] ?? ''));
    $name = trim($_POST['name'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $newPassword = (string)($_POST['new_password'] ?? '');
    $confirmPassword = (string)($_POST['confirm_password'] ?? '');
    $currentPassword = (string)($_POST['current_password'] ?? '');
    $changesCredentials = $newPassword !== ''
        || $email !== $profile['email']
        || $username !== $profile['username'];

    $profile = array_merge($profile, [
        'username' => $username,
        'name' => $name,
        'email' => $email,
    ]);

    if (!preg_match('/^[a-z0-9._-]{3,40}$/', $username)) {
        $error = 'Bitte einen gültigen Benutzernamen eingeben.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Bitte eine gültige E-Mail-Adresse eingeben.';
    } elseif ($newPassword !== '' && ($passwordError = member_area_validate_password($newPassword)) !== null) {
        $error = $passwordError;
    } elseif ($newPassword !== $confirmPassword) {
        $error = 'Die Passwort-Wiederholung stimmt nicht überein.';
    } elseif ($changesCredentials && !member_area_verify_current_password($profile['id'], $currentPassword)) {
        // Login data can only be changed with the current password, so a briefly
        // unattended session is not enough to take over the account.
        $error = 'Zum Ändern von Benutzername, E-Mail oder Passwort bitte das aktuelle Passwort eingeben.';
    } else {
        $pdo = member_area_db();

        try {
            if ($newPassword !== '') {
                // token_version + 1 logs out all other sessions that still use the old password.
                $statement = $pdo->prepare(
                    'UPDATE users
                     SET username = ?, email = ?, name = ?, password_hash = ?,
                         token_version = token_version + 1, updated_at = CURRENT_TIMESTAMP
                     WHERE id = ?'
                );
                $statement->execute([
                    $username,
                    $email,
                    $name !== '' ? $name : null,
                    password_hash($newPassword, PASSWORD_DEFAULT),
                    $profile['id'],
                ]);
            } else {
                $statement = $pdo->prepare(
                    'UPDATE users
                     SET username = ?, email = ?, name = ?, updated_at = CURRENT_TIMESTAMP
                     WHERE id = ?'
                );
                $statement->execute([
                    $username,
                    $email,
                    $name !== '' ? $name : null,
                    $profile['id'],
                ]);
            }

            $statement = $pdo->prepare(
                'SELECT id, username, email, role, name, is_active, token_version FROM users WHERE id = ? LIMIT 1'
            );
            $statement->execute([$profile['id']]);
            $updatedUser = $statement->fetch();

            if ($updatedUser) {
                member_area_set_auth_cookie($updatedUser);
            }

            member_area_set_flash('Deine Benutzerdaten wurden gespeichert.');
            header('Location: /mitglieder/profil.php');
            exit;
        } catch (PDOException $errorObject) {
            if (($errorObject->errorInfo[0] ?? '') === '23000') {
                $error = 'Benutzername oder E-Mail-Adresse wird bereits verwendet.';
            } else {
                error_log('Profile update failed: ' . $errorObject->getMessage());
                $error = member_area_database_unavailable_message();
            }
        }
    }
}

member_area_page_start('Mein Profil');
?>
<div class="panel">
  <h1>Mein Profil</h1>

  <?php if ($error): ?>
    <p class="error"><?= member_area_escape($error) ?></p>
  <?php endif; ?>

  <form method="post" action="/mitglieder/profil.php">
    <?= member_area_csrf_field() ?>
    <label>
      Benutzername
      <input name="username" value="<?= member_area_escape($profile['username']) ?>" maxlength="40" pattern="[A-Za-z0-9._-]{3,40}" autocomplete="username" required>
    </label>

    <label>
      Name
      <input name="name" value="<?= member_area_escape($profile['name']) ?>" maxlength="190" autocomplete="name">
    </label>

    <label>
      E-Mail
      <input name="email" type="email" value="<?= member_area_escape($profile['email']) ?>" maxlength="190" autocomplete="email" required>
    </label>

    <label>
      Rolle
      <input value="<?= member_area_escape($profile['role']) ?>" disabled>
    </label>

    <h2>Passwort ändern</h2>
    <p>Leer lassen, wenn das Passwort unverändert bleiben soll.</p>

    <label>
      Neues Passwort
      <span class="password-field">
        <input id="new_password" name="new_password" type="password" minlength="8" autocomplete="new-password">
        <button type="button" class="password-toggle" data-password-toggle="new_password" aria-label="Passwort anzeigen" aria-pressed="false">
          <span class="material-symbols-outlined" aria-hidden="true">visibility_off</span>
        </button>
      </span>
      <span class="field-hint"><?= member_area_escape(member_area_password_requirements_text()) ?></span>
    </label>

    <label>
      Neues Passwort wiederholen
      <span class="password-field">
        <input id="confirm_password" name="confirm_password" type="password" minlength="8" autocomplete="new-password">
        <button type="button" class="password-toggle" data-password-toggle="confirm_password" aria-label="Passwort anzeigen" aria-pressed="false">
          <span class="material-symbols-outlined" aria-hidden="true">visibility_off</span>
        </button>
      </span>
    </label>

    <h2>Bestätigung</h2>
    <p>Für Änderungen an Benutzername, E-Mail oder Passwort ist dein aktuelles Passwort nötig.</p>

    <label>
      Aktuelles Passwort
      <span class="password-field">
        <input id="current_password" name="current_password" type="password" autocomplete="current-password">
        <button type="button" class="password-toggle" data-password-toggle="current_password" aria-label="Passwort anzeigen" aria-pressed="false">
          <span class="material-symbols-outlined" aria-hidden="true">visibility_off</span>
        </button>
      </span>
    </label>

    <div class="form-actions">
      <button type="submit">Speichern</button>
      <a class="button secondary" href="/mitglieder/dashboard.php">Zurück</a>
    </div>
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
