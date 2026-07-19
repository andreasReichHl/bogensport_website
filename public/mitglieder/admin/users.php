<?php

require_once __DIR__ . '/../bootstrap.php';
require_once MEMBER_AREA_PRIVATE_PATH . '/auth.php';
require_once MEMBER_AREA_PRIVATE_PATH . '/db.php';
require_once MEMBER_AREA_PRIVATE_PATH . '/view.php';

$user = member_area_require_role(['superadmin', 'admin']);
$statement = member_area_db()->query(
    'SELECT id, username, email, role, name, is_active, created_at FROM users ORDER BY created_at DESC, id DESC'
);
$users = $statement->fetchAll();

member_area_page_start('Benutzer');
?>
<div class="panel">
  <h1>Benutzer</h1>
  <p>Angemeldet als <?= member_area_escape($user['username']) ?>.</p>
  <table>
    <thead>
      <tr>
        <th>ID</th>
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
          <td><?= (int)$listedUser['id'] ?></td>
          <td><?= member_area_escape($listedUser['username']) ?></td>
          <td><?= member_area_escape($listedUser['name']) ?></td>
          <td><?= member_area_escape($listedUser['email']) ?></td>
          <td><?= member_area_escape($listedUser['role']) ?></td>
          <td><?= (int)$listedUser['is_active'] === 1 ? 'Aktiv' : 'Gesperrt' ?></td>
          <td><?= member_area_escape($listedUser['created_at']) ?></td>
          <?php if ($user['role'] === 'superadmin'): ?>
            <td><a href="/mitglieder/admin/user.php?id=<?= (int)$listedUser['id'] ?>">Bearbeiten</a></td>
          <?php endif; ?>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php
member_area_page_end();
