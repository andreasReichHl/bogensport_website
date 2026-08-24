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

    if (!in_array($user['role'] ?? '', ['superadmin', 'admin'], true) && (int)$post['author_id'] !== (int)$user['id']) {
        http_response_code(403);
        echo 'Zugriff verweigert.';
        exit;
    }
}

$title = $post['title'] ?? '';
$bodyHtml = $post['body_html'] ?? '<p></p>';
$visibleUntil = $post['visible_until'] ?? date('Y-m-d', strtotime('+14 days'));
$showPublic = (int)($post['show_public'] ?? 0) === 1;
$imagePath = $post['image_path'] ?? null;
$galleryImages = $postId > 0 ? member_area_news_images_for_post($postId, $imagePath) : [];
$imagePath = $galleryImages[0]['image_path'] ?? null;
$galleryImagesJson = json_encode(
    array_map(
        static fn(array $image): array => [
            'id' => $image['id'],
            'image_path' => $image['image_path'],
        ],
        $galleryImages
    ),
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
) ?: '[]';
$galleryManifestJson = json_encode(
    array_map(
        static fn(array $image): array => [
            'type' => 'existing',
            'path' => $image['image_path'],
        ],
        $galleryImages
    ),
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
) ?: '[]';
$createdAt = member_area_format_date($post['created_at'] ?? date('Y-m-d'));
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $bodyHtml = member_area_news_sanitize_html($_POST['body_html'] ?? '');
    $visibleUntil = $_POST['visible_until'] ?? '';
    $showPublic = isset($_POST['show_public']);
    $returnToEdit = ($_POST['return_to_edit'] ?? '') === '1';

    if ($title === '') {
        $error = 'Bitte einen Titel eingeben.';
    } elseif ($bodyHtml === '') {
        $error = 'Bitte einen Beitragstext eingeben.';
    } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $visibleUntil)) {
        $error = 'Bitte ein gültiges Sichtbarkeitsdatum wählen.';
    } else {
        $pdo = null;
        $uploadedImagePaths = [];

        try {
            $galleryManifest = member_area_news_parse_gallery_manifest($_POST['gallery_manifest'] ?? '');
            $uploadedImagePaths = member_area_news_upload_gallery_images($_FILES['gallery_images'] ?? []);
            $isExistingPost = $postId > 0;
            member_area_news_ensure_images_table();
            $pdo = member_area_db();
            $pdo->beginTransaction();

            if ($isExistingPost) {
                $statement = $pdo->prepare(
                    'UPDATE news_posts
                     SET title = ?, body_html = ?, show_public = ?, show_members = ?,
                         visible_until = ?, updated_at = CURRENT_TIMESTAMP
                     WHERE id = ?'
                );
                $statement->execute([
                    $title,
                    $bodyHtml,
                    $showPublic ? 1 : 0,
                    1,
                    $visibleUntil,
                    $postId,
                ]);
            } else {
                $statement = $pdo->prepare(
                    'INSERT INTO news_posts
                       (author_id, title, body_html, image_path, show_public, show_members, visible_until)
                     VALUES (?, ?, ?, NULL, ?, ?, ?)'
                );
                $statement->execute([
                    (int)$user['id'],
                    $title,
                    $bodyHtml,
                    $showPublic ? 1 : 0,
                    1,
                    $visibleUntil,
                ]);
                $postId = (int)$pdo->lastInsertId();
            }

            $galleryResult = member_area_news_replace_post_images($postId, $galleryManifest, $uploadedImagePaths, $imagePath);
            $imagePath = $galleryResult['primary_path'];
            $pdo->commit();

            foreach ($galleryResult['delete_paths'] as $deletePath) {
                member_area_news_delete_image($deletePath);
            }

            member_area_set_flash($isExistingPost ? 'Beitrag gespeichert.' : 'Beitrag erstellt.');

            if ($returnToEdit && $postId > 0) {
                header('Location: /mitglieder/news-edit.php?id=' . $postId);
            } else {
                header('Location: /mitglieder/news.php');
            }
            exit;
        } catch (Throwable $errorObject) {
            if ($pdo instanceof PDO && $pdo->inTransaction()) {
                $pdo->rollBack();
            }

            foreach ($uploadedImagePaths as $uploadedImagePath) {
                member_area_news_delete_image($uploadedImagePath);
            }

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

  <form method="post" action="/mitglieder/news-edit.php" enctype="multipart/form-data" data-created-at="<?= member_area_escape($createdAt) ?>" data-max-gallery-images="<?= MEMBER_AREA_NEWS_MAX_IMAGES ?>">
    <input type="hidden" name="id" value="<?= (int)$postId ?>">
    <input type="hidden" id="body_html" name="body_html" value="<?= member_area_escape($bodyHtml) ?>">
    <input type="hidden" id="return_to_edit" name="return_to_edit" value="0">
    <input type="hidden" id="gallery_manifest" name="gallery_manifest" value="<?= member_area_escape($galleryManifestJson) ?>">
    <script type="application/json" id="initial_gallery_images"><?= $galleryImagesJson ?></script>

    <label>
      Titel
      <input name="title" value="<?= member_area_escape($title) ?>" maxlength="190" required>
    </label>

    <div class="editor-field">
      <span class="field-label">Beitrag</span>
      <span id="editor_toolbar" class="editor-toolbar" aria-label="Formatierung">
        <details class="editor-menu">
          <summary aria-label="Textstil" title="Textstil">
            <span class="toolbar-text-icon" aria-hidden="true">T</span>
          </summary>
          <span class="editor-menu-panel">
            <button type="button" class="ql-size" value="small"><span>T<sub>s</sub></span>Kleiner Text</button>
            <button type="button" class="ql-header" value="4"><span>G</span>Große Überschrift</button>
            <button type="button" class="ql-header" value="5"><span>M</span>Mittlere Überschrift</button>
            <button type="button" class="ql-header" value="6"><span>K</span>Kleine Überschrift</button>
            <button type="button" class="ql-blockquote"><span class="material-symbols-outlined" aria-hidden="true">format_quote</span>Zitat</button>
          </span>
        </details>
        <button type="button" class="ql-paragraph" aria-label="Absatz" title="Absatz">
          <span class="material-symbols-outlined" aria-hidden="true">notes</span>
        </button>
        <span class="editor-button-group">
          <button type="button" class="ql-bold" aria-label="Fett" title="Fett">
            <span class="toolbar-text-icon" aria-hidden="true">B</span>
          </button>
          <details class="editor-menu editor-menu-arrow-only">
            <summary aria-label="Text formatieren" title="Text formatieren">
              <span class="toolbar-chevron" aria-hidden="true"></span>
            </summary>
            <span class="editor-menu-panel">
              <button type="button" class="ql-bold"><span class="material-symbols-outlined" aria-hidden="true">format_bold</span>Fett</button>
              <button type="button" class="ql-italic"><span class="material-symbols-outlined" aria-hidden="true">format_italic</span>Kursiv</button>
              <button type="button" class="ql-underline"><span class="material-symbols-outlined" aria-hidden="true">format_underlined</span>Unterstrichen</button>
              <button type="button" class="ql-strike"><span class="material-symbols-outlined" aria-hidden="true">strikethrough_s</span>Durchgestrichen</button>
              <span class="editor-menu-separator" aria-hidden="true"></span>
              <button type="button" class="ql-clean"><span class="material-symbols-outlined" aria-hidden="true">format_clear</span>Formatierung löschen</button>
            </span>
          </details>
        </span>
        <span class="editor-button-group">
          <button type="button" class="ql-list" value="bullet" aria-label="Liste mit Aufzählungszeichen" title="Liste mit Aufzählungszeichen">
            <svg class="toolbar-svg-icon" viewBox="0 0 24 24" aria-hidden="true">
              <path d="M8 7h12M8 12h12M8 17h12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
              <circle cx="4" cy="7" r="1.4" fill="currentColor"/>
              <circle cx="4" cy="12" r="1.4" fill="currentColor"/>
              <circle cx="4" cy="17" r="1.4" fill="currentColor"/>
            </svg>
          </button>
          <details class="editor-menu editor-menu-arrow-only">
            <summary aria-label="Listen" title="Listen">
              <span class="toolbar-chevron" aria-hidden="true"></span>
            </summary>
            <span class="editor-menu-panel">
              <button type="button" class="ql-list" value="bullet"><span class="material-symbols-outlined" aria-hidden="true">format_list_bulleted</span>Liste mit Aufzählungszeichen</button>
              <button type="button" class="ql-list" value="ordered"><span class="material-symbols-outlined" aria-hidden="true">format_list_numbered</span>Nummerierte Liste</button>
            </span>
          </details>
        </span>
      </span>
      <div id="rich_editor" class="rich-editor news-content"><?= $bodyHtml ?></div>
    </div>

    <label>
      Bilder
      <span id="image_dropzone" class="image-dropzone">
        <input id="image_input" name="gallery_images[]" type="file" accept="image/jpeg,image/png,image/webp" multiple>
        <span class="image-dropzone-text">
          <span id="image_file_name">Bilder hier ablegen oder auswählen</span>
          <span class="image-dropzone-hint">JPG, PNG oder WebP bis 5 MB pro Bild, maximal <?= MEMBER_AREA_NEWS_MAX_IMAGES ?> Bilder</span>
        </span>
      </span>
    </label>

    <div class="image-gallery" id="image_gallery" <?= $galleryImages ? '' : 'hidden' ?>>
      <div class="image-gallery-header">
        <span class="field-label">Galerie</span>
        <span class="field-hint">Bilder können per Ziehen verschoben werden. Das erste Bild ist das Titelbild.</span>
      </div>
      <div id="gallery_list" class="image-gallery-list" aria-label="Ausgewählte Bilder"></div>
      <div class="image-gallery-edit-actions">
        <button type="button" id="edit_gallery_images" class="button secondary">
          <span class="material-symbols-outlined" aria-hidden="true">photo_library</span>
          Bilder bearbeiten
        </button>
      </div>
    </div>

    <div id="remove_image_modal" class="modal-backdrop" hidden>
      <div class="modal-dialog" role="dialog" aria-modal="true" aria-labelledby="remove_image_title" aria-describedby="remove_image_description">
        <h2 id="remove_image_title">Bild löschen?</h2>
        <p id="remove_image_description">Soll dieses Bild aus der Galerie entfernt werden?</p>
        <div class="modal-actions">
          <button type="button" id="confirm_remove_image" class="danger-button">Ja</button>
          <button type="button" id="cancel_remove_image" class="button secondary">Nein</button>
        </div>
      </div>
    </div>

    <div id="crop_image_modal" class="modal-backdrop crop-modal" hidden>
      <div class="modal-dialog wide crop-dialog" role="dialog" aria-modal="true" aria-labelledby="crop_image_title" aria-describedby="crop_image_description crop_help">
        <div class="crop-dialog-header">
          <h2 id="crop_image_title">Bild zuschneiden</h2>
          <p id="crop_image_description">Ausschnitt im Querformat ausrichten und Zoom anpassen.</p>
        </div>
        <div class="crop-workspace">
          <div class="crop-stage">
            <div id="crop_frame" class="crop-frame">
              <img id="crop_image" src="" alt="">
            </div>
            <div id="crop_gallery_list" class="crop-gallery-list" aria-label="Bild zum Zuschneiden auswählen"></div>
          </div>
          <div class="crop-settings">
            <label class="crop-controls">
              <span class="crop-control-label"><span class="material-symbols-outlined" aria-hidden="true">zoom_in</span>Zoom</span>
              <input id="crop_zoom" type="range" min="1" max="2.2" step="0.01" value="1">
            </label>
            <div id="crop_help" class="crop-help">
              <h3>Bild bearbeiten</h3>
              <ul>
                <li>Motiv passend in den Rahmen ziehen.</li>
                <li>Nur bei Bedarf leicht heranzoomen.</li>
                <li>Danach den Ausschnitt übernehmen.</li>
              </ul>
            </div>
          </div>
        </div>
        <div class="modal-actions">
          <button type="button" id="apply_crop_image">Übernehmen</button>
          <button type="button" id="cancel_crop_image" class="button secondary">Abbrechen</button>
        </div>
      </div>
    </div>

    <label>
      Sichtbar bis
      <input name="visible_until" type="date" value="<?= member_area_escape($visibleUntil) ?>" required>
    </label>

    <label class="checkbox-label">
      <input type="checkbox" name="show_public" value="1" <?= $showPublic ? 'checked' : '' ?>>
      Auf der Hauptseite anzeigen
    </label>

    <label class="checkbox-label">
      <input type="hidden" name="show_members" value="1">
      <input type="checkbox" value="1" checked disabled>
      Im Mitgliederbereich anzeigen
    </label>

    <div class="form-actions">
      <button type="submit">Speichern</button>
      <button type="button" id="open_preview_modal" class="button secondary">Vorschau</button>
      <a class="button secondary" href="/mitglieder/news.php">Zurück</a>
    </div>

    <div id="preview_modal" class="modal-backdrop" hidden>
      <div class="modal-dialog wide" role="dialog" aria-modal="true" aria-labelledby="preview_title">
        <h2 id="preview_title">Vorschau</h2>
        <article id="preview_card" class="preview-card">
          <img id="preview_image" class="preview-image" src="" alt="" hidden>
          <div>
            <p id="preview_meta" class="news-meta"></p>
            <h2 id="preview_post_title"></h2>
            <div id="preview_post_body" class="news-content"></div>
          </div>
        </article>
        <div class="modal-actions">
          <button type="button" id="close_preview_modal" class="button secondary">Schließen</button>
        </div>
      </div>
    </div>
  </form>
</div>

<script src="<?= member_area_escape(member_area_asset_url('/mitglieder/assets/vendor/quill/quill.js')) ?>" defer></script>
<script src="<?= member_area_escape(member_area_asset_url('/mitglieder/assets/vendor/cropper/cropper.min.js')) ?>" defer></script>
<script src="<?= member_area_escape(member_area_asset_url('/mitglieder/assets/news-editor.js')) ?>" defer></script>
<?php
member_area_page_end();
