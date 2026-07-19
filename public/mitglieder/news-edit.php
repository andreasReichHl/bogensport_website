<?php

require_once __DIR__ . '/bootstrap.php';
require_once MEMBER_AREA_PRIVATE_PATH . '/modules.php';
require_once MEMBER_AREA_PRIVATE_PATH . '/news.php';
require_once MEMBER_AREA_PRIVATE_PATH . '/view.php';

$user = member_area_require_module('news');
$postId = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$post = null;

if ($postId > 0) {
    $statement = member_area_db()->prepare('SELECT * FROM news_posts WHERE id = ? LIMIT 1');
    $statement->execute([$postId]);
    $post = $statement->fetch();

    if (!$post) {
        http_response_code(404);
        echo 'Beitrag nicht gefunden.';
        exit;
    }

    if (($user['role'] ?? '') !== 'superadmin' && (int)$post['author_id'] !== (int)$user['id']) {
        http_response_code(403);
        echo 'Zugriff verweigert.';
        exit;
    }
}

$title = $post['title'] ?? '';
$bodyHtml = $post['body_html'] ?? '<p></p>';
$visibleUntil = $post['visible_until'] ?? date('Y-m-d', strtotime('+14 days'));
$showPublic = (int)($post['show_public'] ?? 0) === 1;
$showMembers = (int)($post['show_members'] ?? 1) === 1;
$imagePath = $post['image_path'] ?? null;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $bodyHtml = member_area_news_sanitize_html($_POST['body_html'] ?? '');
    $visibleUntil = $_POST['visible_until'] ?? '';
    $showPublic = isset($_POST['show_public']);
    $showMembers = isset($_POST['show_members']);
    $removeImage = isset($_POST['remove_image']);

    if ($title === '') {
        $error = 'Bitte einen Titel eingeben.';
    } elseif ($bodyHtml === '') {
        $error = 'Bitte einen Beitragstext eingeben.';
    } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $visibleUntil)) {
        $error = 'Bitte ein gültiges Sichtbarkeitsdatum wählen.';
    } elseif (!$showPublic && !$showMembers) {
        $error = 'Bitte mindestens einen Anzeigeort auswählen.';
    } else {
        try {
            $newImagePath = member_area_news_upload_image($_FILES['image'] ?? []);

            if ($removeImage || $newImagePath) {
                member_area_news_delete_image($imagePath);
                $imagePath = null;
            }

            if ($newImagePath) {
                $imagePath = $newImagePath;
            }

            if ($postId > 0) {
                $statement = member_area_db()->prepare(
                    'UPDATE news_posts
                     SET title = ?, body_html = ?, image_path = ?, show_public = ?, show_members = ?,
                         visible_until = ?, updated_at = CURRENT_TIMESTAMP
                     WHERE id = ?'
                );
                $statement->execute([
                    $title,
                    $bodyHtml,
                    $imagePath,
                    $showPublic ? 1 : 0,
                    $showMembers ? 1 : 0,
                    $visibleUntil,
                    $postId,
                ]);
            } else {
                $statement = member_area_db()->prepare(
                    'INSERT INTO news_posts
                       (author_id, title, body_html, image_path, show_public, show_members, visible_until)
                     VALUES (?, ?, ?, ?, ?, ?, ?)'
                );
                $statement->execute([
                    (int)$user['id'],
                    $title,
                    $bodyHtml,
                    $imagePath,
                    $showPublic ? 1 : 0,
                    $showMembers ? 1 : 0,
                    $visibleUntil,
                ]);
            }

            header('Location: /mitglieder/news.php');
            exit;
        } catch (Throwable $errorObject) {
            $error = $errorObject->getMessage();
        }
    }
}

member_area_page_start($postId > 0 ? 'News bearbeiten' : 'News verfassen');
?>
<div class="panel">
  <h1><?= $postId > 0 ? 'News bearbeiten' : 'News verfassen' ?></h1>

  <?php if ($error): ?>
    <p class="error"><?= member_area_escape($error) ?></p>
  <?php endif; ?>

  <form method="post" action="/mitglieder/news-edit.php" enctype="multipart/form-data">
    <input type="hidden" name="id" value="<?= (int)$postId ?>">
    <input type="hidden" id="body_html" name="body_html" value="<?= member_area_escape($bodyHtml) ?>">

    <label>
      Titel
      <input name="title" value="<?= member_area_escape($title) ?>" maxlength="190" required>
    </label>

    <label>
      Beitrag
      <span class="editor-toolbar" aria-label="Formatierung">
        <button type="button" data-command="formatBlock" data-value="p">Absatz</button>
        <button type="button" data-command="bold">Fett</button>
        <button type="button" data-command="italic">Kursiv</button>
        <button type="button" data-command="formatBlock" data-value="h2">Überschrift</button>
        <button type="button" data-command="insertUnorderedList">Liste</button>
        <button type="button" data-command="formatBlock" data-value="blockquote">Zitat</button>
      </span>
      <div id="rich_editor" class="rich-editor news-content" contenteditable="true"><?= $bodyHtml ?></div>
    </label>

    <label>
      Bild
      <input name="image" type="file" accept="image/jpeg,image/png,image/webp">
    </label>

    <?php if ($imagePath): ?>
      <p><img class="news-image" src="<?= member_area_escape($imagePath) ?>" alt=""></p>
      <label class="checkbox-label">
        <input type="checkbox" name="remove_image" value="1">
        Bild entfernen
      </label>
    <?php endif; ?>

    <label>
      Sichtbar bis
      <input name="visible_until" type="date" value="<?= member_area_escape($visibleUntil) ?>" required>
    </label>

    <label class="checkbox-label">
      <input type="checkbox" name="show_public" value="1" <?= $showPublic ? 'checked' : '' ?>>
      Auf der Hauptseite anzeigen
    </label>

    <label class="checkbox-label">
      <input type="checkbox" name="show_members" value="1" <?= $showMembers ? 'checked' : '' ?>>
      Im Mitgliederbereich anzeigen
    </label>

    <div class="form-actions">
      <button type="submit">Speichern</button>
      <a class="button secondary" href="/mitglieder/news.php">Zurück</a>
    </div>
  </form>
</div>

<script>
  const editor = document.getElementById("rich_editor");
  const input = document.getElementById("body_html");

  document.querySelectorAll("[data-command]").forEach((button) => {
    button.addEventListener("click", () => {
      const value = button.dataset.value || null;
      document.execCommand(button.dataset.command, false, value);
      editor.focus();
      input.value = editor.innerHTML;
    });
  });

  editor.addEventListener("input", () => {
    input.value = editor.innerHTML;
  });

  editor.closest("form").addEventListener("submit", () => {
    input.value = editor.innerHTML;
  });
</script>
<?php
member_area_page_end();
