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
$showMembers = (int)($post['show_members'] ?? 1) === 1;
$imagePath = $post['image_path'] ?? null;
$createdAt = member_area_format_date($post['created_at'] ?? date('Y-m-d'));
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $bodyHtml = member_area_news_sanitize_html($_POST['body_html'] ?? '');
    $visibleUntil = $_POST['visible_until'] ?? '';
    $showPublic = isset($_POST['show_public']);
    $showMembers = true;
    $removeImage = ($_POST['remove_image'] ?? '') === '1';
    $returnToEdit = ($_POST['return_to_edit'] ?? '') === '1';

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
            $newImagePath = $removeImage ? null : member_area_news_upload_image($_FILES['image'] ?? []);

            if ($removeImage || $newImagePath) {
                member_area_news_delete_image($imagePath);
                $imagePath = null;
            }

            if ($newImagePath) {
                $imagePath = $newImagePath;
            }

            $isExistingPost = $postId > 0;

            if ($isExistingPost) {
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

            member_area_set_flash($isExistingPost ? 'Beitrag gespeichert.' : 'Beitrag erstellt.');

            if ($returnToEdit && $postId > 0) {
                header('Location: /mitglieder/news-edit.php?id=' . $postId);
            } else {
                header('Location: /mitglieder/news.php');
            }
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

  <form method="post" action="/mitglieder/news-edit.php" enctype="multipart/form-data" data-created-at="<?= member_area_escape($createdAt) ?>">
    <input type="hidden" name="id" value="<?= (int)$postId ?>">
    <input type="hidden" id="body_html" name="body_html" value="<?= member_area_escape($bodyHtml) ?>">
    <input type="hidden" id="return_to_edit" name="return_to_edit" value="0">

    <label>
      Titel
      <input name="title" value="<?= member_area_escape($title) ?>" maxlength="190" required>
    </label>

    <div class="editor-field">
      <span class="field-label">Beitrag</span>
      <span class="editor-toolbar" aria-label="Formatierung">
        <details class="editor-menu">
          <summary aria-label="Textstil" title="Textstil">
            <span class="toolbar-text-icon" aria-hidden="true">T</span>
          </summary>
          <span class="editor-menu-panel">
            <button type="button" data-inline-style="small"><span>T<sub>s</sub></span>Kleiner Text</button>
            <button type="button" data-block-style="h4"><span>G</span>Große Überschrift</button>
            <button type="button" data-block-style="h5"><span>M</span>Mittlere Überschrift</button>
            <button type="button" data-block-style="h6"><span>K</span>Kleine Überschrift</button>
            <button type="button" data-command="formatBlock" data-value="blockquote" data-block="blockquote"><span class="material-symbols-outlined" aria-hidden="true">format_quote</span>Zitat</button>
          </span>
        </details>
        <span class="editor-button-group">
          <button type="button" data-format="bold" aria-label="Fett" title="Fett">
            <span class="toolbar-text-icon" aria-hidden="true">B</span>
          </button>
          <details class="editor-menu editor-menu-arrow-only">
            <summary aria-label="Text formatieren" title="Text formatieren">
              <span class="toolbar-chevron" aria-hidden="true"></span>
            </summary>
            <span class="editor-menu-panel">
              <button type="button" data-format="bold"><span class="material-symbols-outlined" aria-hidden="true">format_bold</span>Fett</button>
              <button type="button" data-format="italic"><span class="material-symbols-outlined" aria-hidden="true">format_italic</span>Kursiv</button>
              <button type="button" data-format="underline"><span class="material-symbols-outlined" aria-hidden="true">format_underlined</span>Unterstrichen</button>
              <button type="button" data-format="strike"><span class="material-symbols-outlined" aria-hidden="true">strikethrough_s</span>Durchgestrichen</button>
              <span class="editor-menu-separator" aria-hidden="true"></span>
              <button type="button" data-command="clearFormat"><span class="material-symbols-outlined" aria-hidden="true">format_clear</span>Formatierung löschen</button>
            </span>
          </details>
        </span>
        <span class="editor-button-group">
          <button type="button" data-list="ul" aria-label="Liste mit Aufzählungszeichen" title="Liste mit Aufzählungszeichen">
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
              <button type="button" data-list="ul"><span class="material-symbols-outlined" aria-hidden="true">format_list_bulleted</span>Liste mit Aufzählungszeichen</button>
              <button type="button" data-list="ol"><span class="material-symbols-outlined" aria-hidden="true">format_list_numbered</span>Nummerierte Liste</button>
            </span>
          </details>
        </span>
      </span>
      <div id="rich_editor" class="rich-editor news-content" contenteditable="true"><?= $bodyHtml ?></div>
    </div>

    <label>
      Bild
      <span id="image_dropzone" class="image-dropzone">
        <input id="image_input" name="image" type="file" accept="image/jpeg,image/png,image/webp">
        <span class="image-dropzone-text">
          <span id="image_file_name">Bild hier ablegen oder auswählen</span>
          <span class="image-dropzone-hint">JPG, PNG oder WebP bis 5 MB</span>
        </span>
      </span>
    </label>

    <div class="current-image" id="current_image" <?= $imagePath ? '' : 'hidden' ?>>
      <img id="current_image_preview" class="news-image" src="<?= member_area_escape($imagePath) ?>" alt="">
      <div class="image-actions">
        <button type="button" id="open_crop_existing_image" class="icon-button crop-button" aria-label="Bild zuschneiden" title="Bild zuschneiden">
          <span class="material-symbols-outlined" aria-hidden="true">crop</span>
        </button>
        <button type="button" id="open_remove_image_modal" class="icon-button danger-button" aria-label="Bild löschen" title="Bild löschen">
          <span class="material-symbols-outlined" aria-hidden="true">delete</span>
        </button>
      </div>
      <input type="hidden" id="remove_image" name="remove_image" value="0">
    </div>

    <div id="remove_image_modal" class="modal-backdrop" hidden>
      <div class="modal-dialog" role="dialog" aria-modal="true" aria-labelledby="remove_image_title" aria-describedby="remove_image_description">
        <h2 id="remove_image_title">Bild löschen?</h2>
        <p id="remove_image_description">Soll das aktuelle Bild wirklich gelöscht werden?</p>
        <div class="modal-actions">
          <button type="button" id="confirm_remove_image" class="danger-button">Ja</button>
          <button type="button" id="cancel_remove_image" class="button secondary">Nein</button>
        </div>
      </div>
    </div>

    <div id="crop_image_modal" class="modal-backdrop" hidden>
      <div class="modal-dialog wide" role="dialog" aria-modal="true" aria-labelledby="crop_image_title" aria-describedby="crop_image_description">
        <h2 id="crop_image_title">Bild zuschneiden</h2>
        <p id="crop_image_description">Bild verschieben und den gewünschten Ausschnitt wählen.</p>
        <div id="crop_frame" class="crop-frame">
          <img id="crop_image" src="" alt="">
        </div>
        <label class="crop-controls">
          Zoom
          <input id="crop_zoom" type="range" min="1" max="3" step="0.01" value="1">
        </label>
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

<script>
  const editor = document.getElementById("rich_editor");
  const input = document.getElementById("body_html");
  const form = editor.closest("form");
  const createdAt = form.dataset.createdAt;
  const returnToEditInput = document.getElementById("return_to_edit");
  const imageInput = document.getElementById("image_input");
  const imageDropzone = document.getElementById("image_dropzone");
  const imageFileName = document.getElementById("image_file_name");
  const currentImage = document.getElementById("current_image");
  const currentImagePreview = document.getElementById("current_image_preview");
  const originalImageSource = currentImagePreview.getAttribute("src") || "";
  const removeImageInput = document.getElementById("remove_image");
  const removeImageModal = document.getElementById("remove_image_modal");
  const openRemoveImageModal = document.getElementById("open_remove_image_modal");
  const confirmRemoveImage = document.getElementById("confirm_remove_image");
  const cancelRemoveImage = document.getElementById("cancel_remove_image");
  const openCropExistingImage = document.getElementById("open_crop_existing_image");
  const cropImageModal = document.getElementById("crop_image_modal");
  const cropFrame = document.getElementById("crop_frame");
  const cropImage = document.getElementById("crop_image");
  const cropZoom = document.getElementById("crop_zoom");
  const applyCropImage = document.getElementById("apply_crop_image");
  const cancelCropImage = document.getElementById("cancel_crop_image");
  const openPreviewModal = document.getElementById("open_preview_modal");
  const previewModal = document.getElementById("preview_modal");
  const closePreviewModal = document.getElementById("close_preview_modal");
  const previewCard = document.getElementById("preview_card");
  const previewImage = document.getElementById("preview_image");
  const previewMeta = document.getElementById("preview_meta");
  const previewPostTitle = document.getElementById("preview_post_title");
  const previewPostBody = document.getElementById("preview_post_body");
  const cropState = {
    filename: "news-image.jpg",
    source: "",
    baseScale: 1,
    zoom: 1,
    offsetX: 0,
    offsetY: 0,
    pointerX: 0,
    pointerY: 0,
    dragging: false,
    previewUrl: null
  };
  let skipNextCropDialog = false;
  let savedEditorRange = null;
  let ignoreEditorSelectionChanges = false;
  let editorPointerStartX = 0;
  let editorPointerStartY = 0;
  let editorPointerMoved = false;
  const getPoint = (event) => event.touches ? event.touches[0] : event;
  const toolbarButtons = Array.from(document.querySelectorAll(".editor-toolbar button"));
  const toolbarMenus = Array.from(document.querySelectorAll(".editor-menu"));
  const inlineFormats = {
    bold: "strong",
    italic: "em",
    underline: "u",
    strike: "s",
    small: "span.news-size-small"
  };

  function selectedRange() {
    const selection = window.getSelection();

    if (!selection || selection.rangeCount === 0) {
      return null;
    }

    const range = selection.getRangeAt(0);

    if (!editor.contains(range.commonAncestorContainer)) {
      return null;
    }

    return range;
  }

  function savedRange() {
    if (!savedEditorRange || !editor.contains(savedEditorRange.commonAncestorContainer)) {
      return null;
    }

    return savedEditorRange;
  }

  function activeEditorRange() {
    return selectedRange() || savedRange();
  }

  function saveEditorSelection(preserveExisting = false) {
    const range = selectedRange();

    if (range && !range.isCollapsed) {
      savedEditorRange = range.cloneRange();
      return;
    }

    if (preserveExisting && savedRange()) {
      return;
    }

    clearSavedEditorSelection();
  }

  function clearToolbarState() {
    toolbarButtons.forEach((button) => {
      button.classList.remove("is-active");
      button.setAttribute("aria-pressed", "false");
    });
  }

  function clearSavedEditorSelection() {
    savedEditorRange = null;
    clearToolbarState();
  }

  function caretRangeFromPoint(clientX, clientY) {
    if (document.caretRangeFromPoint) {
      return document.caretRangeFromPoint(clientX, clientY);
    }

    if (document.caretPositionFromPoint) {
      const position = document.caretPositionFromPoint(clientX, clientY);

      if (!position) {
        return null;
      }

      const range = document.createRange();
      range.setStart(position.offsetNode, position.offset);
      range.collapse(true);
      return range;
    }

    return null;
  }

  function collapseEditorSelectionAtPoint(clientX, clientY) {
    const selection = window.getSelection();

    if (!selection) {
      return;
    }

    let range = caretRangeFromPoint(clientX, clientY);

    if (!range || !editor.contains(range.commonAncestorContainer)) {
      range = document.createRange();
      range.selectNodeContents(editor);
      range.collapse(false);
    }

    selection.removeAllRanges();
    selection.addRange(range);
    clearSavedEditorSelection();
  }

  function restoreEditorSelection() {
    editor.focus();

    if (!savedEditorRange) {
      return;
    }

    const selection = window.getSelection();
    selection.removeAllRanges();
    selection.addRange(savedEditorRange);
  }

  function normalizeEditorHtml(html) {
    const container = document.createElement("div");
    container.innerHTML = html;

    container.querySelectorAll("span").forEach((node) => {
      const sizeClass = node.classList.contains("news-size-small") ? "news-size-small" : "";

      if (!sizeClass) {
        node.replaceWith(...node.childNodes);
        return;
      }

      node.className = sizeClass;
      node.querySelectorAll(".news-size-small").forEach((child) => {
        child.replaceWith(...child.childNodes);
      });
    });

    container.querySelectorAll("strike").forEach((node) => {
      const replacement = document.createElement("s");
      replacement.append(...node.childNodes);
      node.replaceWith(replacement);
    });

    return container.innerHTML;
  }

  function normalizeEditorContent() {
    const html = normalizeEditorHtml(editor.innerHTML);

    if (editor.innerHTML !== html) {
      editor.innerHTML = html;
    }

    input.value = html;
  }

  function syncEditorInput() {
    input.value = normalizeEditorHtml(editor.innerHTML);
  }

  function insertPlainText(text) {
    const range = selectedRange();

    if (!range) {
      return;
    }

    const fragment = document.createDocumentFragment();
    const lines = text.replace(/\r\n?/g, "\n").split("\n");
    const endMarker = document.createTextNode("");

    lines.forEach((line, index) => {
      if (index > 0) {
        fragment.appendChild(document.createElement("br"));
      }

      fragment.appendChild(document.createTextNode(line));
    });

    fragment.appendChild(endMarker);
    range.deleteContents();
    range.insertNode(fragment);

    const nextRange = document.createRange();
    nextRange.setStartAfter(endMarker);
    nextRange.collapse(true);
    endMarker.remove();

    const selection = window.getSelection();
    selection.removeAllRanges();
    selection.addRange(nextRange);
    savedEditorRange = nextRange.cloneRange();
  }

  function elementFromNode(node) {
    return node?.nodeType === Node.TEXT_NODE ? node.parentElement : node;
  }

  function currentBlock(range = activeEditorRange()) {

    if (!range) {
      return null;
    }

    const element = elementFromNode(range.startContainer);
    const block = element?.closest?.("blockquote,li,h4,h5,h6,p,div");

    return block && block !== editor && editor.contains(block) ? block : null;
  }

  function selectedWrapper(range, selector) {
    const start = elementFromNode(range.startContainer)?.closest?.(selector);
    const end = elementFromNode(range.endContainer)?.closest?.(selector);

    if (start && start === end && editor.contains(start)) {
      return start;
    }

    if (
      start &&
      range.endContainer === start.parentNode &&
      range.endOffset === Array.prototype.indexOf.call(start.parentNode.childNodes, start) + 1
    ) {
      return start;
    }

    return null;
  }

  function removeFromFragment(fragment, selector) {
    fragment.querySelectorAll(selector).forEach((node) => {
      node.replaceWith(...node.childNodes);
    });
  }

  function createInlineFormatWrapper(format, selector) {
    const wrapper = format === "small" ? document.createElement("span") : document.createElement(selector);

    if (format === "small") {
      wrapper.className = "news-size-small";
    }

    return wrapper;
  }

  function fragmentContainsBlockContent(fragment) {
    return fragment.querySelector("blockquote,div,h4,h5,h6,li,ol,p,ul") !== null;
  }

  function wrapTextNodes(fragment, format, selector) {
    const walker = document.createTreeWalker(fragment, NodeFilter.SHOW_TEXT);
    const textNodes = [];
    let lastWrapper = null;

    while (walker.nextNode()) {
      if (walker.currentNode.nodeValue.trim() !== "") {
        textNodes.push(walker.currentNode);
      }
    }

    textNodes.forEach((textNode) => {
      const wrapper = createInlineFormatWrapper(format, selector);
      textNode.parentNode.insertBefore(wrapper, textNode);
      wrapper.appendChild(textNode);
      lastWrapper = wrapper;
    });

    return lastWrapper;
  }

  function selectNodeContents(node) {
    const range = document.createRange();
    range.selectNodeContents(node);

    const selection = window.getSelection();
    selection.removeAllRanges();
    selection.addRange(range);
    savedEditorRange = range.cloneRange();

    return range;
  }

  function keepNodeSelectionAndMoveCaretAfter(node) {
    const storedRange = document.createRange();
    storedRange.selectNodeContents(node);
    savedEditorRange = storedRange.cloneRange();

    const caretRange = document.createRange();
    caretRange.setStartAfter(node);
    caretRange.collapse(true);

    const selection = window.getSelection();
    selection.removeAllRanges();
    selection.addRange(caretRange);

    return storedRange;
  }

  function moveCaretToEnd(node) {
    const range = document.createRange();
    range.selectNodeContents(node);
    range.collapse(false);

    const selection = window.getSelection();
    selection.removeAllRanges();
    selection.addRange(range);
    savedEditorRange = null;

    return range;
  }

  function unwrapNode(node) {
    const startMarker = document.createTextNode("");
    const endMarker = document.createTextNode("");
    const parent = node.parentNode;

    parent.insertBefore(startMarker, node);

    while (node.firstChild) {
      parent.insertBefore(node.firstChild, node);
    }

    parent.insertBefore(endMarker, node);
    node.remove();

    const range = document.createRange();
    range.setStartAfter(startMarker);
    range.setEndBefore(endMarker);
    startMarker.remove();
    endMarker.remove();

    const selection = window.getSelection();
    selection.removeAllRanges();
    selection.addRange(range);
    savedEditorRange = range.cloneRange();

    return range;
  }

  function fragmentHasContent(fragment) {
    return Array.from(fragment.childNodes).some((node) =>
      node.nodeType === Node.ELEMENT_NODE || node.textContent !== ""
    );
  }

  function appendWrappedFragment(target, wrapper, fragment) {
    if (!fragmentHasContent(fragment)) {
      return;
    }

    const wrapperClone = wrapper.cloneNode(false);
    wrapperClone.appendChild(fragment);
    target.appendChild(wrapperClone);
  }

  function unwrapSelectedFormatWrapper(wrapper, range, selector) {
    const beforeRange = document.createRange();
    beforeRange.setStart(wrapper, 0);
    beforeRange.setEnd(range.startContainer, range.startOffset);

    const afterRange = document.createRange();
    afterRange.setStart(range.endContainer, range.endOffset);
    afterRange.setEnd(wrapper, wrapper.childNodes.length);

    const beforeFragment = beforeRange.cloneContents();
    const selectedFragment = range.cloneContents();
    const afterFragment = afterRange.cloneContents();
    const replacement = document.createDocumentFragment();
    const endMarker = document.createTextNode("");

    removeFromFragment(selectedFragment, selector);
    appendWrappedFragment(replacement, wrapper, beforeFragment);
    replacement.appendChild(selectedFragment);
    replacement.appendChild(endMarker);
    appendWrappedFragment(replacement, wrapper, afterFragment);
    wrapper.replaceWith(replacement);

    const caretRange = document.createRange();
    caretRange.setStartAfter(endMarker);
    caretRange.collapse(true);
    endMarker.remove();

    const selection = window.getSelection();
    selection.removeAllRanges();
    selection.addRange(caretRange);
    savedEditorRange = null;
  }

  function selectorForInlineFormat(format) {
    return inlineFormats[format] || "";
  }

  function toggleInlineFormat(format) {
    const range = selectedRange();
    const selector = selectorForInlineFormat(format);

    if (!selector || !range || range.isCollapsed) {
      return;
    }

    const existingWrapper = selectedWrapper(range, selector);

    if (existingWrapper) {
      unwrapNode(existingWrapper);
      syncEditorInput();
      return;
    }

    const fragment = range.extractContents();
    removeFromFragment(fragment, selector);

    if (fragmentContainsBlockContent(fragment)) {
      const lastWrapper = wrapTextNodes(fragment, format, selector);
      range.insertNode(fragment);

      if (lastWrapper) {
        const caretRange = document.createRange();
        caretRange.selectNodeContents(lastWrapper);
        caretRange.collapse(false);

        const selection = window.getSelection();
        selection.removeAllRanges();
        selection.addRange(caretRange);
      }
      savedEditorRange = null;
    } else {
      const wrapper = createInlineFormatWrapper(format, selector);
      wrapper.appendChild(fragment);
      range.insertNode(wrapper);
      keepNodeSelectionAndMoveCaretAfter(wrapper);
    }

    syncEditorInput();
  }

  function setBlockStyle(tagName, range = activeEditorRange()) {
    const block = currentBlock(range);

    if (!block || block.tagName.toLowerCase() === "li") {
      return;
    }

    const nextTagName = tagName.toLowerCase();
    const replacement = document.createElement(nextTagName);
    replacement.append(...block.childNodes);
    block.replaceWith(replacement);
    keepNodeSelectionAndMoveCaretAfter(replacement);
    syncEditorInput();
  }

  function clearSelectionFormat() {
    const range = selectedRange();

    if (!range || range.isCollapsed) {
      return;
    }

    const selector = "strong,b,em,i,u,s,strike,.news-size-small";
    const sizeWrapper = selectedWrapper(range, ".news-size-small");

    if (sizeWrapper) {
      unwrapSelectedFormatWrapper(sizeWrapper, range, selector);
      syncEditorInput();
      return;
    }

    document.execCommand("removeFormat", false, null);
    syncEditorInput();
  }

  function toggleQuote(range = activeEditorRange()) {
    const block = currentBlock(range);

    if (!range) {
      return;
    }

    if (!block && !range.collapsed) {
      const quote = document.createElement("blockquote");
      quote.append(range.extractContents());
      range.insertNode(quote);
      moveCaretToEnd(quote);
      syncEditorInput();
      return;
    }

    if (!block) {
      return;
    }

    if (block.tagName.toLowerCase() === "blockquote") {
      const paragraph = document.createElement("p");
      paragraph.append(...block.childNodes);
      block.replaceWith(paragraph);
      moveCaretToEnd(paragraph);
    } else {
      const quote = document.createElement("blockquote");
      quote.append(...block.childNodes);
      block.replaceWith(quote);
      moveCaretToEnd(quote);
    }

    syncEditorInput();
  }

  function toggleList(type) {
    const block = currentBlock();
    const listTagName = type === "ol" ? "ol" : "ul";

    if (!block) {
      const range = selectedRange();

      if (!range) {
        return;
      }

      const list = document.createElement(listTagName);
      const item = document.createElement("li");
      if (range.collapsed) {
        item.appendChild(document.createElement("br"));
      } else {
        item.append(range.extractContents());
      }
      list.appendChild(item);
      if (range.collapsed) {
        range.deleteContents();
      }
      range.insertNode(list);
      moveCaretToEnd(item);
      syncEditorInput();
      return;
    }

    if (block.tagName.toLowerCase() === "li") {
      const list = block.closest("ul,ol");
      const fragment = document.createDocumentFragment();

      list.querySelectorAll("li").forEach((item) => {
        const paragraph = document.createElement("p");
        paragraph.append(...item.childNodes);
        fragment.appendChild(paragraph);
      });

      list.replaceWith(fragment);
    } else {
      const list = document.createElement(listTagName);
      const item = document.createElement("li");
      item.append(...block.childNodes);
      list.appendChild(item);
      block.replaceWith(list);
      moveCaretToEnd(item);
    }

    syncEditorInput();
  }

  function updateToolbarState() {
    const range = activeEditorRange();
    const hasTextSelection = range && !range.isCollapsed;
    const block = currentBlock(range);

    toolbarButtons.forEach((button) => {
      let active = false;

      if (hasTextSelection && button.dataset.format === "italic") {
        active = selectedWrapper(range, "em,i") !== null;
      } else if (hasTextSelection && button.dataset.format) {
        active = selectedWrapper(range, selectorForInlineFormat(button.dataset.format)) !== null;
      } else if (hasTextSelection && button.dataset.inlineStyle === "small") {
        active = selectedWrapper(range, ".news-size-small") !== null;
      } else if (button.dataset.blockStyle) {
        active = block?.tagName.toLowerCase() === button.dataset.blockStyle;
      } else if (button.dataset.list) {
        const list = block?.tagName.toLowerCase() === "li" ? block.closest("ul,ol") : null;
        active = list?.tagName.toLowerCase() === button.dataset.list;
      } else if (button.dataset.command === "insertUnorderedList") {
        active = block?.tagName.toLowerCase() === "li";
      } else if (button.dataset.block === "blockquote") {
        active = block?.tagName.toLowerCase() === "blockquote";
      }

      button.classList.toggle("is-active", active);
      button.setAttribute("aria-pressed", active ? "true" : "false");
    });
  }

  toolbarButtons.forEach((button) => {
    button.addEventListener("mousedown", (event) => {
      event.preventDefault();
    });

    button.addEventListener("click", () => {
      restoreEditorSelection();
      const commandRange = activeEditorRange();

      if (button.dataset.inlineStyle) {
        toggleInlineFormat(button.dataset.inlineStyle);
      } else if (button.dataset.blockStyle) {
        setBlockStyle(button.dataset.blockStyle, commandRange);
      } else if (button.dataset.format) {
        toggleInlineFormat(button.dataset.format);
      } else if (button.dataset.list) {
        toggleList(button.dataset.list);
      } else if (button.dataset.command === "clearFormat") {
        clearSelectionFormat();
      } else if (button.dataset.block === "blockquote") {
        toggleQuote(commandRange);
      }

      syncEditorInput();
      updateToolbarState();
      toolbarMenus.forEach((menu) => {
        menu.open = false;
      });
    });
  });

  document.querySelectorAll(".editor-menu summary").forEach((summary) => {
    summary.addEventListener("mousedown", (event) => {
      event.preventDefault();
      saveEditorSelection(true);
    });
  });

  toolbarMenus.forEach((menu) => {
    menu.addEventListener("toggle", () => {
      if (!menu.open) {
        return;
      }

      toolbarMenus.forEach((candidate) => {
        if (candidate !== menu) {
          candidate.open = false;
        }
      });
      saveEditorSelection(true);
      updateToolbarState();
    });
  });

  document.addEventListener("pointerdown", (event) => {
    if (event.target.closest(".editor-menu")) {
      return;
    }

    toolbarMenus.forEach((menu) => {
      menu.open = false;
    });
  });

  document.addEventListener("keydown", () => {
    toolbarMenus.forEach((menu) => {
      menu.open = false;
    });
  });

  editor.addEventListener("pointerdown", (event) => {
    ignoreEditorSelectionChanges = true;
    editorPointerStartX = event.clientX;
    editorPointerStartY = event.clientY;
    editorPointerMoved = false;
    clearSavedEditorSelection();
  });

  editor.addEventListener("pointermove", (event) => {
    if (Math.abs(event.clientX - editorPointerStartX) > 4 || Math.abs(event.clientY - editorPointerStartY) > 4) {
      editorPointerMoved = true;
    }
  });

  editor.addEventListener("pointerup", (event) => {
    window.setTimeout(() => {
      ignoreEditorSelectionChanges = false;
      if (editorPointerMoved) {
        saveEditorSelection();
        updateToolbarState();
      } else {
        collapseEditorSelectionAtPoint(event.clientX, event.clientY);
      }
    }, 0);
  });

  editor.addEventListener("keydown", (event) => {
    if (!(event.metaKey || event.ctrlKey)) {
      return;
    }

    const key = event.key.toLowerCase();
    const shift = event.shiftKey;
    const alt = event.altKey;

    if (alt && ["4", "5", "6"].includes(key)) {
      event.preventDefault();
      setBlockStyle(`h${key}`);
    } else if (alt && key === "7") {
      event.preventDefault();
      toggleInlineFormat("small");
    } else if (!alt && !shift && ["b", "i", "u"].includes(key)) {
      event.preventDefault();
      toggleInlineFormat({b: "bold", i: "italic", u: "underline"}[key]);
    } else if (shift && key === "s") {
      event.preventDefault();
      toggleInlineFormat("strike");
    } else if (shift && key === "8") {
      event.preventDefault();
      toggleList("ul");
    } else if (shift && key === "7") {
      event.preventDefault();
      toggleList("ol");
    } else if (key === "\\") {
      event.preventDefault();
      clearSelectionFormat();
    }

    syncEditorInput();
    updateToolbarState();
  });

  editor.addEventListener("paste", (event) => {
    const text = event.clipboardData?.getData("text/plain") ?? "";

    if (!text) {
      return;
    }

    event.preventDefault();
    insertPlainText(text);
    syncEditorInput();
    updateToolbarState();
  });

  ["input", "keyup"].forEach((eventName) => {
    editor.addEventListener(eventName, () => {
      syncEditorInput();
      saveEditorSelection();
      updateToolbarState();
    });
  });

  document.addEventListener("selectionchange", () => {
    if (ignoreEditorSelectionChanges) {
      return;
    }

    const range = selectedRange();

    if (range && !range.isCollapsed) {
      savedEditorRange = range.cloneRange();
      updateToolbarState();
    } else if (range) {
      clearSavedEditorSelection();
    }
  });

  form.addEventListener("submit", normalizeEditorContent);
  normalizeEditorContent();
  updateToolbarState();

  function updateImageFileName() {
    imageFileName.textContent = imageInput.files.length
      ? imageInput.files[0].name
      : "Bild hier ablegen oder auswählen";
  }

  function setImageInputFile(file) {
    const transfer = new DataTransfer();
    transfer.items.add(file);
    skipNextCropDialog = true;
    imageInput.files = transfer.files;
    updateImageFileName();
  }

  function showImagePreview(source) {
    currentImagePreview.src = source;
    currentImage.hidden = false;
    removeImageInput.value = "0";
  }

  function hideImagePreview() {
    currentImagePreview.removeAttribute("src");
    currentImage.hidden = true;
  }

  function clearImageInputFile() {
    imageInput.value = "";

    if (cropState.previewUrl) {
      URL.revokeObjectURL(cropState.previewUrl);
      cropState.previewUrl = null;
    }

    cropState.source = "";
    updateImageFileName();
  }

  function resetSelectedImage() {
    clearImageInputFile();

    if (originalImageSource && removeImageInput.value !== "1") {
      showImagePreview(originalImageSource);
    } else {
      hideImagePreview();
    }
  }

  function clampCropOffsets() {
    const frameWidth = cropFrame.clientWidth;
    const frameHeight = cropFrame.clientHeight;
    const imageWidth = cropImage.naturalWidth * cropState.baseScale * cropState.zoom;
    const imageHeight = cropImage.naturalHeight * cropState.baseScale * cropState.zoom;
    const maxX = Math.max(0, (imageWidth - frameWidth) / 2);
    const maxY = Math.max(0, (imageHeight - frameHeight) / 2);

    cropState.offsetX = Math.min(maxX, Math.max(-maxX, cropState.offsetX));
    cropState.offsetY = Math.min(maxY, Math.max(-maxY, cropState.offsetY));
  }

  function renderCropImage() {
    clampCropOffsets();
    cropImage.style.transform = `translate(calc(-50% + ${cropState.offsetX}px), calc(-50% + ${cropState.offsetY}px)) scale(${cropState.baseScale * cropState.zoom})`;
  }

  function initCropImage() {
    const frameWidth = cropFrame.clientWidth;
    const frameHeight = cropFrame.clientHeight;

    cropState.baseScale = Math.max(
      frameWidth / cropImage.naturalWidth,
      frameHeight / cropImage.naturalHeight
    );
    cropState.zoom = 1;
    cropState.offsetX = 0;
    cropState.offsetY = 0;
    cropZoom.value = "1";
    renderCropImage();
  }

  function openCropDialog(source, filename) {
    cropState.source = source;
    cropState.filename = filename || "news-image.jpg";
    cropImageModal.hidden = false;
    cropImage.onload = initCropImage;
    cropImage.src = source;
    applyCropImage.focus();
  }

  function openCropDialogForFile(file) {
    const reader = new FileReader();

    reader.addEventListener("load", () => {
      const source = String(reader.result);

      showImagePreview(source);
      cropState.source = source;
      openCropDialog(source, file.name);
    });

    reader.readAsDataURL(file);
  }

  function closeCropDialog() {
    cropImageModal.hidden = true;
    cropImage.removeAttribute("src");
  }

  function formatDate(value) {
    if (!value) {
      return "nicht gesetzt";
    }

    const [year, month, day] = value.split("-");

    if (!year || !month || !day) {
      return value;
    }

    return `${day}.${month}.${year}`;
  }

  function currentPreviewImageSource() {
    if (imageInput.files.length) {
      if (cropState.previewUrl) {
        return cropState.previewUrl;
      }

      if (cropState.source) {
        return cropState.source;
      }
    }

    if (!currentImage.hidden && currentImagePreview.src && removeImageInput.value !== "1") {
      return currentImagePreview.src;
    }

    return "";
  }

  function openPreview() {
    const titleInput = form.elements.title;
    const visibleUntilInput = form.elements.visible_until;
    const title = titleInput.value.trim() || "Ohne Titel";
    const visibleUntil = formatDate(visibleUntilInput.value);
    const imageSource = currentPreviewImageSource();

    syncEditorInput();
    previewMeta.textContent = `Vorschau · vom ${createdAt} · sichtbar bis ${visibleUntil}`;
    previewPostTitle.textContent = title;
    previewPostBody.innerHTML = input.value || "<p></p>";

    if (imageSource) {
      previewImage.src = imageSource;
      previewImage.hidden = false;
      previewCard.classList.add("with-image");
    } else {
      previewImage.removeAttribute("src");
      previewImage.hidden = true;
      previewCard.classList.remove("with-image");
    }

    previewModal.hidden = false;
    closePreviewModal.focus();
  }

  function closePreview() {
    previewModal.hidden = true;
    openPreviewModal.focus();
  }

  imageInput.addEventListener("change", () => {
    updateImageFileName();

    if (skipNextCropDialog) {
      skipNextCropDialog = false;
      return;
    }

    if (imageInput.files.length) {
      openCropDialogForFile(imageInput.files[0]);
    }
  });

  ["dragenter", "dragover"].forEach((eventName) => {
    imageDropzone.addEventListener(eventName, (event) => {
      event.preventDefault();
      imageDropzone.classList.add("is-dragover");
    });
  });

  ["dragleave", "drop"].forEach((eventName) => {
    imageDropzone.addEventListener(eventName, () => {
      imageDropzone.classList.remove("is-dragover");
    });
  });

  imageDropzone.addEventListener("drop", (event) => {
    event.preventDefault();

    const file = Array.from(event.dataTransfer.files).find((candidate) =>
      ["image/jpeg", "image/png", "image/webp"].includes(candidate.type)
    );

    if (!file) {
      return;
    }

    setImageInputFile(file);
    openCropDialogForFile(file);
  });

  cropZoom.addEventListener("input", () => {
    cropState.zoom = Number(cropZoom.value);
    renderCropImage();
  });

  cropFrame.addEventListener("pointerdown", (event) => {
    event.preventDefault();
    cropState.dragging = true;
    cropState.pointerX = event.clientX;
    cropState.pointerY = event.clientY;
    cropFrame.classList.add("is-dragging");
  });

  function dragCropImage(event) {
    if (!cropState.dragging) {
      return;
    }

    event.preventDefault();

    const point = getPoint(event);

    cropState.offsetX += point.clientX - cropState.pointerX;
    cropState.offsetY += point.clientY - cropState.pointerY;
    cropState.pointerX = point.clientX;
    cropState.pointerY = point.clientY;
    renderCropImage();
  }

  function stopDraggingCropImage() {
    cropState.dragging = false;
    cropFrame.classList.remove("is-dragging");
  }

  document.addEventListener("pointermove", dragCropImage, {passive: false});
  document.addEventListener("pointerup", stopDraggingCropImage);
  document.addEventListener("pointercancel", stopDraggingCropImage);

  cancelCropImage.addEventListener("click", closeCropDialog);

  cropImageModal.addEventListener("click", (event) => {
    if (event.target === cropImageModal) {
      closeCropDialog();
    }
  });

  applyCropImage.addEventListener("click", () => {
    const frameWidth = cropFrame.clientWidth;
    const frameHeight = cropFrame.clientHeight;
    const scale = cropState.baseScale * cropState.zoom;
    const sourceWidth = frameWidth / scale;
    const sourceHeight = frameHeight / scale;
    const sourceCenterX = cropImage.naturalWidth / 2 - cropState.offsetX / scale;
    const sourceCenterY = cropImage.naturalHeight / 2 - cropState.offsetY / scale;
    const sourceX = Math.max(0, Math.min(cropImage.naturalWidth - sourceWidth, sourceCenterX - sourceWidth / 2));
    const sourceY = Math.max(0, Math.min(cropImage.naturalHeight - sourceHeight, sourceCenterY - sourceHeight / 2));
    const canvas = document.createElement("canvas");
    const outputWidth = 1280;
    const outputHeight = 720;

    canvas.width = outputWidth;
    canvas.height = outputHeight;
    canvas.getContext("2d").drawImage(
      cropImage,
      sourceX,
      sourceY,
      sourceWidth,
      sourceHeight,
      0,
      0,
      outputWidth,
      outputHeight
    );

    canvas.toBlob((blob) => {
      if (!blob) {
        return;
      }

      const filename = cropState.filename.replace(/\.[^.]+$/, "") + "-ausschnitt.jpg";
      const file = new File([blob], filename, {type: "image/jpeg"});

      setImageInputFile(file);

      if (currentImagePreview) {
        if (cropState.previewUrl) {
          URL.revokeObjectURL(cropState.previewUrl);
        }

        cropState.previewUrl = URL.createObjectURL(file);
        currentImagePreview.src = cropState.previewUrl;
      }

      if (removeImageInput) {
        removeImageInput.value = "0";
      }

      closeCropDialog();
    }, "image/jpeg", 0.9);
  });

  if (openCropExistingImage && currentImagePreview) {
    openCropExistingImage.addEventListener("click", () => {
      if (!currentImage.hidden && currentImagePreview.src) {
        openCropDialog(currentImagePreview.src, "news-image.jpg");
      }
    });
  }

  openPreviewModal.addEventListener("click", openPreview);
  closePreviewModal.addEventListener("click", closePreview);

  previewModal.addEventListener("click", (event) => {
    if (event.target === previewModal) {
      closePreview();
    }
  });

  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape" && !cropImageModal.hidden) {
      closeCropDialog();
    }

    if (event.key === "Escape" && !previewModal.hidden) {
      closePreview();
    }
  });

  function closeRemoveImageModal() {
    removeImageModal.hidden = true;
    openRemoveImageModal.focus();
  }

  if (removeImageModal) {
    openRemoveImageModal.addEventListener("click", () => {
      removeImageModal.hidden = false;
      confirmRemoveImage.focus();
    });

    cancelRemoveImage.addEventListener("click", closeRemoveImageModal);

    confirmRemoveImage.addEventListener("click", () => {
      if (imageInput.files.length) {
        resetSelectedImage();
        closeRemoveImageModal();
        return;
      }

      removeImageInput.value = "1";
      returnToEditInput.value = "1";
      clearImageInputFile();
      closeRemoveImageModal();
      form.requestSubmit();
    });

    removeImageModal.addEventListener("click", (event) => {
      if (event.target === removeImageModal) {
        closeRemoveImageModal();
      }
    });

    document.addEventListener("keydown", (event) => {
      if (event.key === "Escape" && !removeImageModal.hidden) {
        closeRemoveImageModal();
      }
    });
  }
</script>
<?php
member_area_page_end();
