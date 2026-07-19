<?php

require_once __DIR__ . '/bootstrap.php';
require_once MEMBER_AREA_PRIVATE_PATH . '/modules.php';
require_once MEMBER_AREA_PRIVATE_PATH . '/news.php';
require_once MEMBER_AREA_PRIVATE_PATH . '/view.php';

member_area_require_module('news');

$memberPosts = member_area_news_list('members');
$allPosts = member_area_news_all_posts();

member_area_page_start('News');
?>
<section class="page-intro">
  <h1>News</h1>
  <p><a class="button" href="/mitglieder/news-edit.php">Neuen Beitrag verfassen</a></p>
</section>

<section class="news-list" aria-label="Aktive News">
  <?php foreach ($memberPosts as $post): ?>
    <article class="news-card <?= $post['image_path'] ? 'with-image' : '' ?>">
      <?php if ($post['image_path']): ?>
        <img class="news-image" src="<?= member_area_escape($post['image_path']) ?>" alt="">
      <?php endif; ?>
      <div>
        <p class="news-meta">
          Von <?= member_area_escape($post['author_username']) ?> · sichtbar bis <?= member_area_escape($post['visible_until']) ?>
        </p>
        <h2><?= member_area_escape($post['title']) ?></h2>
        <div class="news-content"><?= $post['body_html'] ?></div>
      </div>
    </article>
  <?php endforeach; ?>

  <?php if (!$memberPosts): ?>
    <div class="panel">
      <p>Aktuell sind keine News für den Mitgliederbereich sichtbar.</p>
    </div>
  <?php endif; ?>
</section>

<section class="panel" style="margin-top:28px">
  <h2>Beiträge verwalten</h2>
  <table>
    <thead>
      <tr>
        <th>Titel</th>
        <th>Sichtbar bis</th>
        <th>Anzeige</th>
        <th>Autor</th>
        <th>Aktion</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($allPosts as $post): ?>
        <tr>
          <td><?= member_area_escape($post['title']) ?></td>
          <td><?= member_area_escape($post['visible_until']) ?></td>
          <td>
            <span class="visibility-list">
              <?= (int)$post['show_public'] === 1 ? 'Hauptseite' : '' ?>
              <?= (int)$post['show_members'] === 1 ? 'Mitglieder' : '' ?>
            </span>
          </td>
          <td><?= member_area_escape($post['author_username']) ?></td>
          <td><a href="/mitglieder/news-edit.php?id=<?= (int)$post['id'] ?>">Bearbeiten</a></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$allPosts): ?>
        <tr>
          <td colspan="5">Noch keine Beiträge vorhanden.</td>
        </tr>
      <?php endif; ?>
    </tbody>
  </table>
</section>
<?php
member_area_page_end();
