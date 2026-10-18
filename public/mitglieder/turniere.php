<?php

require_once __DIR__ . '/bootstrap.php';
require_once MEMBER_AREA_PRIVATE_PATH . '/modules.php';
require_once MEMBER_AREA_PRIVATE_PATH . '/view.php';

$user = member_area_require_module('tournaments');

member_area_page_start('Turniere');
?>
<div class="panel">
  <h1>Turniere</h1>
  <p>Dieses Modul ist für <?= member_area_escape($user['username']) ?> freigeschaltet.</p>
  <p>Hier können später Turniere, Ausschreibungen und Meldungen verwaltet werden.</p>
</div>
<?php
member_area_page_end();
