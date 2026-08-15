<?php

require_once __DIR__ . '/../bootstrap.php';
require_once MEMBER_AREA_PRIVATE_PATH . '/auth.php';
require_once MEMBER_AREA_PRIVATE_PATH . '/db.php';
require_once MEMBER_AREA_PRIVATE_PATH . '/view.php';

$user = member_area_require_role(['superadmin', 'admin']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'disable_user') {
    if ($user['role'] !== 'superadmin') {
        http_response_code(403);
        echo 'Zugriff verweigert.';
        exit;
    }

    $targetUserId = (int)($_POST['user_id'] ?? 0);

    if ($targetUserId === (int)$user['id']) {
        member_area_set_flash('Der eigene Benutzer kann nicht deaktiviert werden.', 'error');
        header('Location: /mitglieder/admin/users.php');
        exit;
    }

    $targetStatement = member_area_db()->prepare('SELECT id, username, role, is_active FROM users WHERE id = ? LIMIT 1');
    $targetStatement->execute([$targetUserId]);
    $targetUser = $targetStatement->fetch();

    if (!$targetUser) {
        member_area_set_flash('Benutzer nicht gefunden.', 'error');
        header('Location: /mitglieder/admin/users.php');
        exit;
    }

    if ((int)$targetUser['is_active'] !== 1) {
        member_area_set_flash('Benutzer ist bereits deaktiviert.');
        header('Location: /mitglieder/admin/users.php');
        exit;
    }

    if ($targetUser['role'] === 'superadmin') {
        $activeSuperadminCount = (int)member_area_db()
            ->query("SELECT COUNT(*) FROM users WHERE role = 'superadmin' AND is_active = 1")
            ->fetchColumn();

        if ($activeSuperadminCount <= 1) {
            member_area_set_flash('Der letzte aktive Superadmin kann nicht deaktiviert werden.', 'error');
            header('Location: /mitglieder/admin/users.php');
            exit;
        }
    }

    $disableStatement = member_area_db()->prepare(
        'UPDATE users
         SET is_active = 0, token_version = token_version + 1, updated_at = CURRENT_TIMESTAMP
         WHERE id = ?'
    );
    $disableStatement->execute([$targetUserId]);

    member_area_set_flash('Benutzer wurde deaktiviert.');
    header('Location: /mitglieder/admin/users.php');
    exit;
}

$statement = member_area_db()->query(
    'SELECT id, username, email, role, name, is_active, created_at FROM users ORDER BY created_at DESC, id DESC'
);
$users = $statement->fetchAll();

member_area_page_start('Benutzer');
?>
<div class="panel">
  <h1>Benutzer</h1>
  <p>Angemeldet als <?= member_area_escape($user['username']) ?>.</p>
  <?php if ($user['role'] === 'superadmin'): ?>
    <p><a class="button" href="/mitglieder/admin/user.php">Neuen Benutzer anlegen</a></p>
  <?php endif; ?>
  <table>
    <thead>
      <tr>
        <th>Benutzername</th>
        <th>Name</th>
        <th>E-Mail</th>
        <th>Rolle</th>
        <th>Status</th>
        <th>Erstellt</th>
        <?php if ($user['role'] === 'superadmin'): ?>
          <th>Aktion</th>
        <?php endif; ?>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($users as $listedUser): ?>
        <tr>
          <td data-label="Benutzername"><?= member_area_escape($listedUser['username']) ?></td>
          <td data-label="Name"><?= member_area_escape($listedUser['name']) ?></td>
          <td data-label="E-Mail"><?= member_area_escape($listedUser['email']) ?></td>
          <td data-label="Rolle"><?= member_area_escape($listedUser['role']) ?></td>
          <td data-label="Status"><?= (int)$listedUser['is_active'] === 1 ? 'Aktiv' : 'Gesperrt' ?></td>
          <td data-label="Erstellt"><?= member_area_escape(member_area_format_date($listedUser['created_at'])) ?></td>
          <?php if ($user['role'] === 'superadmin'): ?>
            <td data-label="Aktion">
              <span class="table-actions user-actions">
                <a
                  class="button secondary icon-button manage-edit-link"
                  href="/mitglieder/admin/user.php?id=<?= (int)$listedUser['id'] ?>"
                  aria-label="Benutzer bearbeiten"
                  title="Bearbeiten"
                >
                  <svg class="edit-icon" viewBox="2 2 20 20" aria-hidden="true"><path d="M4 20l4.8-1.1L19.2 8.5a2.1 2.1 0 0 0 0-3l-.7-.7a2.1 2.1 0 0 0-3 0L5.1 15.2 4 20Z" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"/><path d="M14.2 6.1l3.7 3.7" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round"/></svg>
                </a>
                <?php if ((int)$listedUser['is_active'] === 1 && (int)$listedUser['id'] !== (int)$user['id']): ?>
                  <button
                    type="button"
                    class="icon-button danger-button"
                    data-disable-user
                    data-user-id="<?= (int)$listedUser['id'] ?>"
                    data-username="<?= member_area_escape($listedUser['username']) ?>"
                    aria-label="Benutzer deaktivieren"
                    title="Deaktivieren"
                  >
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 6h18M8 6V4h8v2M6 6l1 14h10l1-14M10 11v5M14 11v5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                  </button>
                <?php endif; ?>
              </span>
            </td>
          <?php endif; ?>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<div id="disable_user_modal" class="modal-backdrop" hidden>
  <div class="modal-dialog" role="dialog" aria-modal="true" aria-labelledby="disable_user_title" aria-describedby="disable_user_description">
    <h2 id="disable_user_title">Benutzer deaktivieren?</h2>
    <p id="disable_user_description">Soll dieser Benutzer wirklich deaktiviert werden?</p>
    <form method="post" action="/mitglieder/admin/users.php">
      <input type="hidden" name="action" value="disable_user">
      <input type="hidden" id="disable_user_id" name="user_id" value="">
      <div class="modal-actions">
        <button type="submit" class="danger-button">Ja</button>
        <button type="button" id="cancel_disable_user" class="button secondary">Nein</button>
      </div>
    </form>
  </div>
</div>

<script>
  const disableUserModal = document.getElementById("disable_user_modal");
  const disableUserId = document.getElementById("disable_user_id");
  const disableUserDescription = document.getElementById("disable_user_description");
  const cancelDisableUser = document.getElementById("cancel_disable_user");
  let lastFocusedElement = null;

  function closeDisableUserModal() {
    disableUserModal.hidden = true;

    if (lastFocusedElement) {
      lastFocusedElement.focus();
    }
  }

  document.querySelectorAll("[data-disable-user]").forEach((button) => {
    button.addEventListener("click", () => {
      lastFocusedElement = button;
      disableUserId.value = button.dataset.userId;
      disableUserDescription.textContent = `Soll der Benutzer "${button.dataset.username}" wirklich deaktiviert werden?`;
      disableUserModal.hidden = false;
      cancelDisableUser.focus();
    });
  });

  cancelDisableUser.addEventListener("click", closeDisableUserModal);

  disableUserModal.addEventListener("click", (event) => {
    if (event.target === disableUserModal) {
      closeDisableUserModal();
    }
  });

  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape" && !disableUserModal.hidden) {
      closeDisableUserModal();
    }
  });
</script>
<?php
member_area_page_end();
