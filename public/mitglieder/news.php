<?php

require_once __DIR__ . '/bootstrap.php';
require_once MEMBER_AREA_PRIVATE_PATH . '/modules.php';
require_once MEMBER_AREA_PRIVATE_PATH . '/news.php';
require_once MEMBER_AREA_PRIVATE_PATH . '/view.php';

$user = member_area_require_module('news');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_post') {
    member_area_news_delete_post((int)($_POST['post_id'] ?? 0), $user);

    header('Location: /mitglieder/news.php');
    exit;
}

$memberPosts = member_area_news_list('members');
$allPosts = member_area_news_all_posts();

member_area_page_start('News');
?>
<section class="page-intro">
  <h1>News</h1>
  <p><a class="button" href="/mitglieder/news-edit.php">Neuen Beitrag verfassen</a></p>
</section>

<section class="panel">
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
        <?php $canManagePost = in_array($user['role'] ?? '', ['superadmin', 'admin'], true) || (int)$post['author_id'] === (int)$user['id']; ?>
        <tr>
          <td data-label="Titel"><?= member_area_escape($post['title']) ?></td>
          <td data-label="Sichtbar bis"><?= member_area_escape(member_area_format_date($post['visible_until'])) ?></td>
          <td data-label="Anzeige">
            <span class="visibility-list">
              <?= (int)$post['show_public'] === 1 ? 'Hauptseite' : '' ?>
              <?= (int)$post['show_members'] === 1 ? 'Mitglieder' : '' ?>
            </span>
          </td>
          <td data-label="Autor"><?= member_area_escape($post['author_username']) ?></td>
          <td data-label="Aktion">
            <span class="table-actions">
              <button
                type="button"
                class="icon-button secondary-icon-button"
                data-preview-post
                data-title="<?= member_area_escape($post['title']) ?>"
                data-image="<?= member_area_escape($post['image_path']) ?>"
                data-author="<?= member_area_escape($post['author_username']) ?>"
                data-created-at="<?= member_area_escape(member_area_format_date($post['created_at'])) ?>"
                data-visible-until="<?= member_area_escape(member_area_format_date($post['visible_until'])) ?>"
                data-body-id="post_body_<?= (int)$post['id'] ?>"
                aria-label="Beitrag anzeigen"
                title="Vorschau"
              >
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/><circle cx="12" cy="12" r="3" fill="none" stroke="currentColor" stroke-width="2"/></svg>
              </button>
              <?php if ($canManagePost): ?>
                <a
                  class="button secondary icon-button manage-edit-link"
                  href="/mitglieder/news-edit.php?id=<?= (int)$post['id'] ?>"
                  aria-label="Beitrag bearbeiten"
                  title="Bearbeiten"
                >
                  <svg class="edit-icon" viewBox="2 2 18 18" aria-hidden="true"><path d="M4 20l4.8-1.1L19.2 8.5a2.1 2.1 0 0 0 0-3l-.7-.7a2.1 2.1 0 0 0-3 0L5.1 15.2 4 20Z" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"/><path d="M14.2 6.1l3.7 3.7" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round"/></svg>
                </a>
                <button
                  type="button"
                  class="icon-button danger-button"
                  data-delete-post
                  data-post-id="<?= (int)$post['id'] ?>"
                  data-title="<?= member_area_escape($post['title']) ?>"
                  aria-label="Beitrag löschen"
                  title="Löschen"
                >
                  <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 6h18M8 6V4h8v2M6 6l1 14h10l1-14M10 11v5M14 11v5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </button>
              <?php else: ?>
                <span
                  class="button secondary icon-button manage-edit-link is-disabled"
                  aria-disabled="true"
                  aria-label="Beitrag bearbeiten nicht erlaubt"
                  title="Bearbeiten nicht erlaubt"
                >
                  <svg class="edit-icon" viewBox="2 2 20 20" aria-hidden="true"><path d="M4 20l4.8-1.1L19.2 8.5a2.1 2.1 0 0 0 0-3l-.7-.7a2.1 2.1 0 0 0-3 0L5.1 15.2 4 20Z" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"/><path d="M14.2 6.1l3.7 3.7" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round"/></svg>
                </span>
                <button
                  type="button"
                  class="icon-button danger-button"
                  disabled
                  aria-label="Beitrag löschen nicht erlaubt"
                  title="Löschen nicht erlaubt"
                >
                  <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 6h18M8 6V4h8v2M6 6l1 14h10l1-14M10 11v5M14 11v5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </button>
              <?php endif; ?>
            </span>
            <template id="post_body_<?= (int)$post['id'] ?>"><?= $post['body_html'] ?></template>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$allPosts): ?>
        <tr>
          <td colspan="5" data-label="Beiträge">Noch keine Beiträge vorhanden.</td>
        </tr>
      <?php endif; ?>
    </tbody>
  </table>
</section>

<div id="manage_preview_modal" class="modal-backdrop" hidden>
  <div class="modal-dialog wide" role="dialog" aria-modal="true" aria-labelledby="manage_preview_title">
    <h2 id="manage_preview_title">Vorschau</h2>
    <article id="manage_preview_card" class="preview-card">
      <img id="manage_preview_image" class="preview-image" src="" alt="" hidden>
      <div>
        <p id="manage_preview_meta" class="news-meta"></p>
        <h2 id="manage_preview_post_title"></h2>
        <div id="manage_preview_post_body" class="news-content"></div>
      </div>
    </article>
    <div class="modal-actions">
      <button type="button" id="close_manage_preview_modal" class="button secondary">Schließen</button>
    </div>
  </div>
</div>

<div id="delete_post_modal" class="modal-backdrop" hidden>
  <div class="modal-dialog" role="dialog" aria-modal="true" aria-labelledby="delete_post_title" aria-describedby="delete_post_description">
    <h2 id="delete_post_title">Beitrag löschen?</h2>
    <p id="delete_post_description">Soll dieser Beitrag wirklich gelöscht werden?</p>
    <form method="post" action="/mitglieder/news.php">
      <input type="hidden" name="action" value="delete_post">
      <input type="hidden" id="delete_post_id" name="post_id" value="">
      <div class="modal-actions">
        <button type="submit" class="danger-button">Ja</button>
        <button type="button" id="cancel_delete_post" class="button secondary">Nein</button>
      </div>
    </form>
  </div>
</div>

<section class="news-list" aria-label="Aktive News">
  <?php foreach ($memberPosts as $post): ?>
    <article class="news-card <?= $post['image_path'] ? 'with-image' : '' ?>">
      <?php if ($post['image_path']): ?>
        <img class="news-image" src="<?= member_area_escape($post['image_path']) ?>" alt="">
      <?php endif; ?>
      <div>
        <p class="news-meta">
          Von <?= member_area_escape($post['author_username']) ?> · sichtbar bis <?= member_area_escape(member_area_format_date($post['visible_until'])) ?>
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

<script>
  const previewModal = document.getElementById("manage_preview_modal");
  const previewCard = document.getElementById("manage_preview_card");
  const previewImage = document.getElementById("manage_preview_image");
  const previewMeta = document.getElementById("manage_preview_meta");
  const previewTitle = document.getElementById("manage_preview_post_title");
  const previewBody = document.getElementById("manage_preview_post_body");
  const closePreviewModal = document.getElementById("close_manage_preview_modal");
  const deleteModal = document.getElementById("delete_post_modal");
  const deletePostId = document.getElementById("delete_post_id");
  const deleteDescription = document.getElementById("delete_post_description");
  const cancelDeletePost = document.getElementById("cancel_delete_post");
  let lastFocusedElement = null;

  function closeModal(modal) {
    modal.hidden = true;

    if (lastFocusedElement) {
      lastFocusedElement.focus();
    }
  }

  document.querySelectorAll("[data-preview-post]").forEach((button) => {
    button.addEventListener("click", () => {
      const bodyTemplate = document.getElementById(button.dataset.bodyId);
      const imagePath = button.dataset.image || "";

      lastFocusedElement = button;
      previewTitle.textContent = button.dataset.title || "Ohne Titel";
      previewMeta.textContent = `Vom ${button.dataset.createdAt || ""} · Von ${button.dataset.author || ""} · sichtbar bis ${button.dataset.visibleUntil || ""}`;
      previewBody.innerHTML = bodyTemplate ? bodyTemplate.innerHTML : "";

      if (imagePath) {
        previewImage.src = imagePath;
        previewImage.hidden = false;
        previewCard.classList.add("with-image");
      } else {
        previewImage.removeAttribute("src");
        previewImage.hidden = true;
        previewCard.classList.remove("with-image");
      }

      previewModal.hidden = false;
      closePreviewModal.focus();
    });
  });

  document.querySelectorAll("[data-delete-post]").forEach((button) => {
    button.addEventListener("click", () => {
      lastFocusedElement = button;
      deletePostId.value = button.dataset.postId;
      deleteDescription.textContent = `Soll der Beitrag "${button.dataset.title}" wirklich gelöscht werden?`;
      deleteModal.hidden = false;
      cancelDeletePost.focus();
    });
  });

  closePreviewModal.addEventListener("click", () => closeModal(previewModal));
  cancelDeletePost.addEventListener("click", () => closeModal(deleteModal));

  [previewModal, deleteModal].forEach((modal) => {
    modal.addEventListener("click", (event) => {
      if (event.target === modal) {
        closeModal(modal);
      }
    });
  });

  document.addEventListener("keydown", (event) => {
    if (event.key !== "Escape") {
      return;
    }

    if (!previewModal.hidden) {
      closeModal(previewModal);
    }

    if (!deleteModal.hidden) {
      closeModal(deleteModal);
    }
  });
</script>
<?php
member_area_page_end();
