<?php

require_once __DIR__ . '/bootstrap.php';
require_once MEMBER_AREA_PRIVATE_PATH . '/auth.php';
require_once MEMBER_AREA_PRIVATE_PATH . '/modules.php';
require_once MEMBER_AREA_PRIVATE_PATH . '/view.php';

$user = member_area_require_user();
$modules = member_area_accessible_modules($user);

member_area_page_start('Dashboard');
?>
<section class="page-intro">
  <h1>Dashboard</h1>
</section>

<section class="tile-grid" aria-label="Module">
  <?php foreach ($modules as $module): ?>
    <a class="tile" href="<?= member_area_escape($module['href']) ?>">
      <span class="tile-title"><?= member_area_escape($module['label']) ?></span>
      <span class="tile-description"><?= member_area_escape($module['description']) ?></span>
    </a>
  <?php endforeach; ?>

  <?php if (!$modules): ?>
    <div class="panel">
      <p>Für deinen Benutzer sind aktuell keine Module freigeschaltet.</p>
    </div>
  <?php endif; ?>
</section>
<?php
member_area_page_end();
