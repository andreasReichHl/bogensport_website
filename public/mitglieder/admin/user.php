<?php

require_once __DIR__ . '/../bootstrap.php';
require_once MEMBER_AREA_PRIVATE_PATH . '/auth.php';
require_once MEMBER_AREA_PRIVATE_PATH . '/db.php';
require_once MEMBER_AREA_PRIVATE_PATH . '/modules.php';
require_once MEMBER_AREA_PRIVATE_PATH . '/view.php';

member_area_require_role(['superadmin']);

$userId = (int)($_GET['id'] ?? $_POST['id'] ?? 0);

if ($userId <= 0) {
    http_response_code(404);
    echo 'Benutzer nicht gefunden.';
    exit;
}

$statement = member_area_db()->prepare(
    'SELECT id, username, email, role, name, is_active FROM users WHERE id = ? LIMIT 1'
);
$statement->execute([$userId]);
$editedUser = $statement->fetch();

if (!$editedUser) {
    http_response_code(404);
    echo 'Benutzer nicht gefunden.';
    exit;
}

$grantableModules = member_area_grantable_modules();
$selectedModules = member_area_user_module_keys($userId);
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $role = $_POST['role'] ?? '';
    $isActive = isset($_POST['is_active']) ? 1 : 0;
    $postedModules = $_POST['modules'] ?? [];
    $allowedModuleKeys = array_keys($grantableModules);
    $moduleKeys = array_values(array_intersect($allowedModuleKeys, $postedModules));

    if (!in_array($role, MEMBER_ROLES, true)) {
        $error = 'Bitte eine gültige Rolle auswählen.';
    } else {
        $pdo = member_area_db();
        $pdo->beginTransaction();

        try {
            $updateUser = $pdo->prepare(
                'UPDATE users
                 SET role = ?, is_active = ?, token_version = token_version + 1, updated_at = CURRENT_TIMESTAMP
                 WHERE id = ?'
            );
            $updateUser->execute([$role, $isActive, $userId]);

            $deletePermissions = $pdo->prepare('DELETE FROM user_module_permissions WHERE user_id = ?');
            $deletePermissions->execute([$userId]);

            $insertPermission = $pdo->prepare(
                'INSERT INTO user_module_permissions (user_id, module_key) VALUES (?, ?)'
            );

            foreach ($moduleKeys as $moduleKey) {
                $insertPermission->execute([$userId, $moduleKey]);
            }

            $pdo->commit();
            $message = 'Benutzer wurde gespeichert.';
            $selectedModules = $moduleKeys;

            $statement->execute([$userId]);
            $editedUser = $statement->fetch();
        } catch (Throwable $errorObject) {
            $pdo->rollBack();
            $error = 'Benutzer konnte nicht gespeichert werden.';
        }
    }
}

member_area_page_start('Benutzer bearbeiten');
?>
<div class="panel">
  <h1>Benutzer bearbeiten</h1>
  <p><?= member_area_escape($editedUser['username']) ?> · <?= member_area_escape($editedUser['email']) ?></p>

  <?php if ($message): ?>
    <p><?= member_area_escape($message) ?></p>
  <?php endif; ?>

  <?php if ($error): ?>
    <p class="error"><?= member_area_escape($error) ?></p>
  <?php endif; ?>

  <form method="post" action="/mitglieder/admin/user.php">
    <input type="hidden" name="id" value="<?= (int)$editedUser['id'] ?>">

    <label>
      Rolle
      <select name="role" required>
        <?php foreach (MEMBER_ROLES as $role): ?>
          <option value="<?= member_area_escape($role) ?>" <?= $editedUser['role'] === $role ? 'selected' : '' ?>>
            <?= member_area_escape($role) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </label>

    <label class="checkbox-label">
      <input type="checkbox" name="is_active" value="1" <?= (int)$editedUser['is_active'] === 1 ? 'checked' : '' ?>>
      Benutzer ist aktiv
    </label>

    <h2>Freischaltungen</h2>
    <?php foreach ($grantableModules as $moduleKey => $module): ?>
      <label class="checkbox-label">
        <input
          type="checkbox"
          name="modules[]"
          value="<?= member_area_escape($moduleKey) ?>"
          <?= in_array($moduleKey, $selectedModules, true) ? 'checked' : '' ?>
        >
        <span>
          <strong><?= member_area_escape($module['label']) ?></strong><br>
          <?= member_area_escape($module['description']) ?>
        </span>
      </label>
    <?php endforeach; ?>

    <div class="form-actions">
      <button type="submit">Speichern</button>
      <a class="button secondary" href="/mitglieder/admin/users.php">Zurück</a>
    </div>
  </form>
</div>
<?php
member_area_page_end();
