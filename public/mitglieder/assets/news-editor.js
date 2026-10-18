/* global Quill, Cropper */
(() => {
  const editorElement = document.getElementById("rich_editor");
  const input = document.getElementById("body_html");
  const form = editorElement?.closest("form");
  const toolbar = document.getElementById("editor_toolbar");

  if (!editorElement || !input || !form || !toolbar || typeof Quill === "undefined" || typeof Cropper === "undefined") {
    return;
  }

  const createdAt = form.dataset.createdAt;
  const imageInput = document.getElementById("image_input");
  const imageDropzone = document.getElementById("image_dropzone");
  const imageFileName = document.getElementById("image_file_name");
  const imageGallery = document.getElementById("image_gallery");
  const galleryList = document.getElementById("gallery_list");
  const editGalleryImagesButton = document.getElementById("edit_gallery_images");
  const galleryManifestInput = document.getElementById("gallery_manifest");
  const initialGalleryImages = document.getElementById("initial_gallery_images");
  const removeImageModal = document.getElementById("remove_image_modal");
  const confirmRemoveImage = document.getElementById("confirm_remove_image");
  const cancelRemoveImage = document.getElementById("cancel_remove_image");
  const cropImageModal = document.getElementById("crop_image_modal");
  const cropImage = document.getElementById("crop_image");
  const cropZoom = document.getElementById("crop_zoom");
  const cropGalleryList = document.getElementById("crop_gallery_list");
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
  const CROP_ASPECT_RATIO = 16 / 9;
  const CROP_OUTPUT_WIDTH = 1280;
  const CROP_OUTPUT_HEIGHT = 720;
  const CROP_MIN_ZOOM = 1;
  const CROP_MIN_AVAILABLE_MAX_ZOOM = 1.6;
  const CROP_MAX_ZOOM = 2.2;
  const CROP_ZOOM_STEP = 0.01;
  const CROP_QUALITY_SOURCE_SCALE = 0.7;
  const MAX_GALLERY_IMAGES = Math.max(1, Number(form.dataset.maxGalleryImages) || 8);
  const cropState = {
    itemId: null,
    filename: "news-image.jpg",
    initialCanvasRatio: 1,
    zoom: 1
  };
  let galleryItems = [];
  let galleryIdCounter = 0;
  let draggedGalleryItemId = null;
  let galleryDragState = null;
  let galleryDragGhost = null;
  let pendingDeleteItemId = null;
  let pendingDeleteTrigger = null;
  let cropper = null;
  let isSubmittingForm = false;

  function htmlForQuill(html) {
    const container = document.createElement("div");
    container.innerHTML = html || "<p></p>";

    container.querySelectorAll("span.news-size-small").forEach((node) => {
      node.classList.remove("news-size-small");
      node.classList.add("ql-size-small");
    });

    return container.innerHTML;
  }

  function normalizeQuillLists(container) {
    container.querySelectorAll("li .ql-ui").forEach((node) => {
      node.remove();
    });

    container.querySelectorAll("ol").forEach((list) => {
      const items = Array.from(list.children).filter((child) => child.tagName?.toLowerCase() === "li");
      const hasTypedItems = items.some((item) => item.hasAttribute("data-list"));

      if (!hasTypedItems) {
        return;
      }

      let currentList = null;
      let currentTagName = "";

      items.forEach((item) => {
        const type = item.getAttribute("data-list");
        const tagName = type === "bullet" ? "ul" : "ol";

        item.removeAttribute("data-list");

        if (!currentList || currentTagName !== tagName) {
          currentList = document.createElement(tagName);
          currentTagName = tagName;
          list.parentNode.insertBefore(currentList, list);
        }

        currentList.appendChild(item);
      });

      list.remove();
    });
  }

  function normalizeEditorHtml(html) {
    const container = document.createElement("div");
    container.innerHTML = html || "";

    normalizeQuillLists(container);

    container.querySelectorAll("[contenteditable], [spellcheck], [role], [tabindex]").forEach((node) => {
      node.removeAttribute("contenteditable");
      node.removeAttribute("spellcheck");
      node.removeAttribute("role");
      node.removeAttribute("tabindex");
    });

    container.querySelectorAll("span").forEach((node) => {
      if (node.classList.contains("ql-size-small") || node.classList.contains("news-size-small")) {
        node.className = "news-size-small";
        return;
      }

      node.replaceWith(...node.childNodes);
    });

    container.querySelectorAll("strike").forEach((node) => {
      const replacement = document.createElement("s");
      replacement.append(...node.childNodes);
      node.replaceWith(replacement);
    });

    container.querySelectorAll("[class]").forEach((node) => {
      if (node.tagName.toLowerCase() !== "span" || node.className !== "news-size-small") {
        node.removeAttribute("class");
      }
    });

    return container.innerHTML.replace(/\u00a0/g, " ").trim();
  }

  editorElement.innerHTML = htmlForQuill(editorElement.innerHTML);

  const quill = new Quill(editorElement, {
    formats: ["bold", "italic", "underline", "strike", "size", "header", "blockquote", "list"],
    modules: {
      toolbar: false
    },
    placeholder: "Beitrag schreiben ...",
    theme: "snow"
  });

  function syncEditorInput() {
    input.value = normalizeEditorHtml(quill.root.innerHTML);
  }

  function closeToolbarMenus() {
    document.querySelectorAll(".editor-menu[open]").forEach((menu) => {
      menu.open = false;
    });
  }

  function activeRange() {
    const range = quill.getSelection(true);

    if (range) {
      return range;
    }

    quill.focus();
    return quill.getSelection(true);
  }

  function activeFormats(range) {
    return range ? quill.getFormat(range) : {};
  }

  function formatLine(range, format, value) {
    quill.formatLine(range.index, Math.max(range.length, 1), format, value, "user");
  }

  function clearBlockFormats(range) {
    formatLine(range, "blockquote", false);
    formatLine(range, "header", false);
    formatLine(range, "list", false);
  }

  function applyToolbarButton(button) {
    const range = activeRange();

    if (!range) {
      return;
    }

    const formats = activeFormats(range);

    if (button.classList.contains("ql-bold")) {
      quill.format("bold", !formats.bold, "user");
    } else if (button.classList.contains("ql-italic")) {
      quill.format("italic", !formats.italic, "user");
    } else if (button.classList.contains("ql-underline")) {
      quill.format("underline", !formats.underline, "user");
    } else if (button.classList.contains("ql-strike")) {
      quill.format("strike", !formats.strike, "user");
    } else if (button.classList.contains("ql-size")) {
      const size = button.value || "small";
      quill.format("size", formats.size === size ? false : size, "user");
    } else if (button.classList.contains("ql-header")) {
      const header = Number(button.value);
      formatLine(range, "header", formats.header === header ? false : header);
    } else if (button.classList.contains("ql-blockquote")) {
      formatLine(range, "blockquote", !formats.blockquote);
    } else if (button.classList.contains("ql-list")) {
      const list = button.value;
      formatLine(range, "list", formats.list === list ? false : list);
    } else if (button.classList.contains("ql-clean")) {
      quill.removeFormat(range.index, Math.max(range.length, 1), "user");
    } else if (button.classList.contains("ql-paragraph")) {
      clearBlockFormats(range);
    }

    syncEditorInput();
  }

  function updateToolbarState() {
    const range = quill.getSelection();
    const formats = activeFormats(range);

    toolbar.querySelectorAll("button").forEach((button) => {
      let active = false;

      if (button.classList.contains("ql-bold")) {
        active = !!formats.bold;
      } else if (button.classList.contains("ql-italic")) {
        active = !!formats.italic;
      } else if (button.classList.contains("ql-underline")) {
        active = !!formats.underline;
      } else if (button.classList.contains("ql-strike")) {
        active = !!formats.strike;
      } else if (button.classList.contains("ql-size")) {
        active = formats.size === (button.value || "small");
      } else if (button.classList.contains("ql-header")) {
        active = formats.header === Number(button.value);
      } else if (button.classList.contains("ql-blockquote")) {
        active = !!formats.blockquote;
      } else if (button.classList.contains("ql-list")) {
        active = formats.list === button.value;
      }

      button.classList.toggle("is-active", active);
      button.setAttribute("aria-pressed", active ? "true" : "false");
    });
  }

  toolbar.querySelectorAll("button").forEach((button) => {
    button.addEventListener("mousedown", (event) => {
      event.preventDefault();
    });

    button.addEventListener("click", () => {
      applyToolbarButton(button);
      updateToolbarState();
      window.requestAnimationFrame(closeToolbarMenus);
    });
  });

  toolbar.addEventListener("click", (event) => {
    if (event.target.closest("button")) {
      window.requestAnimationFrame(closeToolbarMenus);
    }
  });

  document.querySelectorAll(".editor-menu").forEach((menu) => {
    menu.addEventListener("toggle", () => {
      if (!menu.open) {
        return;
      }

      document.querySelectorAll(".editor-menu[open]").forEach((candidate) => {
        if (candidate !== menu) {
          candidate.open = false;
        }
      });
    });
  });

  document.addEventListener("pointerdown", (event) => {
    if (!event.target.closest(".editor-menu")) {
      closeToolbarMenus();
    }
  });

  document.addEventListener("keydown", () => {
    closeToolbarMenus();
  });

  quill.on("text-change", () => {
    syncEditorInput();
    updateToolbarState();
  });
  quill.on("selection-change", updateToolbarState);
  form.addEventListener("submit", syncEditorInput);
  syncEditorInput();
  updateToolbarState();

  function filenameFromPath(path) {
    const filename = String(path || "").split("/").pop() || "news-image.jpg";

    try {
      return decodeURIComponent(filename);
    } catch {
      return filename;
    }
  }

  function loadInitialGalleryItems() {
    let initialImages;

    try {
      initialImages = JSON.parse(initialGalleryImages?.textContent || "[]");
    } catch {
      initialImages = [];
    }

    galleryItems = Array.isArray(initialImages)
      ? initialImages
        .filter((image) => image && image.image_path)
        .map((image) => ({
          filename: filenameFromPath(image.image_path),
          id: `existing-${image.id || galleryIdCounter++}`,
          objectUrl: "",
          path: image.image_path,
          source: image.image_path,
          type: "existing",
          uploadFile: null
        }))
      : [];
  }

  function updateImageFileName() {
    if (galleryItems.length === 0) {
      imageFileName.textContent = "Bilder hier ablegen oder auswählen";
      return;
    }

    imageFileName.textContent = "Bilder hinzugefügt";
  }

  function gallerySubmissionData() {
    const manifest = [];
    const uploadFiles = [];
    let fileIndex = 0;

    galleryItems.forEach((item) => {
      if (item.type === "existing" && item.path) {
        manifest.push({
          type: "existing",
          path: item.path
        });
        return;
      }

      if (item.uploadFile) {
        uploadFiles.push(item.uploadFile);
        manifest.push({
          type: "upload",
          file_index: fileIndex
        });
        fileIndex += 1;
      }
    });

    return {manifest, uploadFiles};
  }

  function syncGallerySubmission() {
    const {manifest, uploadFiles} = gallerySubmissionData();
    const canSyncFiles = typeof DataTransfer !== "undefined";
    const transfer = canSyncFiles ? new DataTransfer() : null;

    uploadFiles.forEach((file) => {
      transfer?.items.add(file);
    });

    if (transfer) {
      imageInput.files = transfer.files;
    }

    galleryManifestInput.value = JSON.stringify(manifest);
  }

  function applyGalleryFormData(formData) {
    const {manifest, uploadFiles} = gallerySubmissionData();

    formData.set("gallery_manifest", JSON.stringify(manifest));
    formData.delete("gallery_images[]");

    uploadFiles.forEach((file) => {
      formData.append("gallery_images[]", file, file.name);
    });
  }

  function revokeObjectUrl(item) {
    if (item.objectUrl) {
      URL.revokeObjectURL(item.objectUrl);
      item.objectUrl = "";
    }
  }

  function createIconButton(icon, label, className = "icon-button secondary-icon-button") {
    const button = document.createElement("button");
    const iconElement = document.createElement("span");

    button.type = "button";
    button.className = className;
    button.setAttribute("aria-label", label);
    button.title = label;
    iconElement.className = "material-symbols-outlined";
    iconElement.setAttribute("aria-hidden", "true");
    iconElement.textContent = icon;
    button.appendChild(iconElement);

    return button;
  }

  function moveGalleryItem(fromIndex, toIndex) {
    if (fromIndex === toIndex || fromIndex < 0 || toIndex < 0 || fromIndex >= galleryItems.length) {
      return;
    }

    const previousRects = captureGalleryCardRects();
    const [item] = galleryItems.splice(fromIndex, 1);
    galleryItems.splice(Math.min(toIndex, galleryItems.length), 0, item);
    renderGallery();
    animateGalleryCardsFrom(previousRects);
  }

  function captureGalleryCardRects() {
    return new Map(
      Array.from(galleryList.querySelectorAll(".gallery-image-card")).map((card) => [
        card.dataset.itemId,
        card.getBoundingClientRect()
      ])
    );
  }

  function animateGalleryCardsFrom(previousRects) {
    window.requestAnimationFrame(() => {
      galleryList.querySelectorAll(".gallery-image-card").forEach((card) => {
        const previousRect = previousRects.get(card.dataset.itemId);

        if (!previousRect) {
          return;
        }

        const nextRect = card.getBoundingClientRect();
        const deltaX = previousRect.left - nextRect.left;
        const deltaY = previousRect.top - nextRect.top;

        if (Math.abs(deltaX) < 1 && Math.abs(deltaY) < 1) {
          return;
        }

        card.style.transition = "none";
        card.style.transform = `translate3d(${deltaX}px, ${deltaY}px, 0)`;
        card.getBoundingClientRect();

        window.requestAnimationFrame(() => {
          card.style.transition = "";
          card.style.transform = "";
        });
      });
    });
  }

  function shouldInsertAfterGalleryCard(clientX, clientY, card) {
    const bounds = card.getBoundingClientRect();
    const rowTolerance = Math.min(24, bounds.height / 4);
    const isSameRow = clientY >= bounds.top - rowTolerance && clientY <= bounds.bottom + rowTolerance;

    if (isSameRow) {
      return clientX > bounds.left + bounds.width / 2;
    }

    return clientY > bounds.top + bounds.height / 2;
  }

  function galleryCardAtPoint(clientX, clientY) {
    const element = document.elementFromPoint(clientX, clientY);
    const card = element?.closest?.(".gallery-image-card");

    if (card && galleryList.contains(card)) {
      return card.dataset.itemId === draggedGalleryItemId ? null : card;
    }

    return Array.from(galleryList.querySelectorAll(".gallery-image-card"))
      .filter((candidate) => candidate.dataset.itemId !== draggedGalleryItemId)
      .map((candidate) => {
        const bounds = candidate.getBoundingClientRect();
        const centerX = bounds.left + bounds.width / 2;
        const centerY = bounds.top + bounds.height / 2;

        return {
          card: candidate,
          distance: Math.hypot(clientX - centerX, clientY - centerY)
        };
      })
      .sort((left, right) => left.distance - right.distance)[0]?.card || null;
  }

  function reorderGalleryItemForPointer(clientX, clientY) {
    if (!draggedGalleryItemId) {
      return;
    }

    const targetCard = galleryCardAtPoint(clientX, clientY);

    if (!targetCard) {
      return;
    }

    const fromIndex = galleryItems.findIndex((candidate) => candidate.id === draggedGalleryItemId);
    const targetIndex = galleryItems.findIndex((candidate) => candidate.id === targetCard.dataset.itemId);

    if (fromIndex < 0 || targetIndex < 0) {
      return;
    }

    const insertAfter = shouldInsertAfterGalleryCard(clientX, clientY, targetCard);
    let nextIndex = targetIndex + (insertAfter ? 1 : 0);

    if (fromIndex < nextIndex) {
      nextIndex -= 1;
    }

    moveGalleryItem(fromIndex, nextIndex);
  }

  function finishGalleryDrag() {
    if (!galleryDragState) {
      return;
    }

    const wasActive = galleryDragState.active;

    galleryDragState = null;
    draggedGalleryItemId = null;
    removeGalleryDragGhost();
    galleryList.classList.remove("is-sorting");

    if (wasActive) {
      renderGallery();
    }
  }

  function removeGalleryDragGhost() {
    galleryDragGhost?.remove();
    galleryDragGhost = null;
  }

  function galleryCardForItem(itemId) {
    return Array.from(galleryList.querySelectorAll(".gallery-image-card"))
      .find((card) => card.dataset.itemId === itemId) || null;
  }

  function createGalleryDragGhost(itemId, clientX, clientY) {
    const sourceCard = galleryCardForItem(itemId);

    if (!sourceCard) {
      return;
    }

    const bounds = sourceCard.getBoundingClientRect();

    removeGalleryDragGhost();
    galleryDragGhost = sourceCard.cloneNode(true);
    galleryDragGhost.classList.add("gallery-drag-ghost");
    galleryDragGhost.style.width = `${bounds.width}px`;
    galleryDragGhost.style.height = `${bounds.height}px`;
    document.body.appendChild(galleryDragGhost);

    galleryDragState.offsetX = clientX - bounds.left;
    galleryDragState.offsetY = clientY - bounds.top;
    moveGalleryDragGhost(clientX, clientY);
  }

  function moveGalleryDragGhost(clientX, clientY) {
    if (!galleryDragGhost || !galleryDragState) {
      return;
    }

    galleryDragGhost.style.left = `${clientX - galleryDragState.offsetX}px`;
    galleryDragGhost.style.top = `${clientY - galleryDragState.offsetY}px`;
  }

  function startGalleryPointerDrag(event, itemId) {
    if (event.button !== 0 || event.target.closest(".gallery-image-delete")) {
      return;
    }

    event.preventDefault();
    galleryDragState = {
      itemId,
      pointerId: event.pointerId,
      startX: event.clientX,
      startY: event.clientY,
      offsetX: 0,
      offsetY: 0,
      active: false
    };
  }

  function moveGalleryPointerDrag(event) {
    if (!galleryDragState || event.pointerId !== galleryDragState.pointerId) {
      return;
    }

    const distance = Math.hypot(event.clientX - galleryDragState.startX, event.clientY - galleryDragState.startY);

    if (!galleryDragState.active && distance < 6) {
      return;
    }

    event.preventDefault();

    if (!galleryDragState.active) {
      galleryDragState.active = true;
      draggedGalleryItemId = galleryDragState.itemId;
      galleryList.classList.add("is-sorting");
      createGalleryDragGhost(galleryDragState.itemId, event.clientX, event.clientY);
      renderGallery();
    }

    moveGalleryDragGhost(event.clientX, event.clientY);
    reorderGalleryItemForPointer(event.clientX, event.clientY);
  }

  function removeGalleryItem(itemId) {
    const index = galleryItems.findIndex((item) => item.id === itemId);

    if (index < 0) {
      return;
    }

    revokeObjectUrl(galleryItems[index]);
    galleryItems.splice(index, 1);

    if (cropState.itemId === itemId) {
      closeCropDialog();
    }

    renderGallery();
  }

  function replaceGalleryItemFile(item, file) {
    revokeObjectUrl(item);

    const objectUrl = URL.createObjectURL(file);

    item.filename = file.name;
    item.objectUrl = objectUrl;
    item.path = "";
    item.source = objectUrl;
    item.type = "local";
    item.uploadFile = file;
  }

  function addFiles(files) {
    const remainingSlots = Math.max(0, MAX_GALLERY_IMAGES - galleryItems.length);
    const validFiles = Array.from(files).filter((file) =>
      ["image/jpeg", "image/png", "image/webp"].includes(file.type)
    );
    const acceptedFiles = validFiles.slice(0, remainingSlots);

    if (remainingSlots === 0) {
      imageFileName.textContent = `Maximal ${MAX_GALLERY_IMAGES} Bilder pro Beitrag`;
      return;
    }

    if (!acceptedFiles.length) {
      return;
    }

    const newItems = acceptedFiles.map((file) => {
      const objectUrl = URL.createObjectURL(file);

      return {
        filename: file.name,
        id: `local-${Date.now()}-${galleryIdCounter++}`,
        objectUrl,
        path: "",
        source: objectUrl,
        type: "local",
        uploadFile: file
      };
    });

    galleryItems.push(...newItems);
    renderGallery();
    openCropDialogForItem(newItems[0].id);

    if (validFiles.length > acceptedFiles.length) {
      imageFileName.textContent = `Maximal ${MAX_GALLERY_IMAGES} Bilder pro Beitrag`;
    }
  }

  function renderCropGallery() {
    if (!cropGalleryList) {
      return;
    }

    cropGalleryList.innerHTML = "";

    galleryItems.forEach((item, index) => {
      const button = document.createElement("button");
      const image = document.createElement("img");

      button.type = "button";
      button.className = "crop-gallery-item";
      button.classList.toggle("is-active", item.id === cropState.itemId);
      button.setAttribute("aria-label", `Bild ${index + 1} zuschneiden`);
      image.src = item.source;
      image.alt = "";
      button.append(image);
      button.addEventListener("click", () => {
        openCropDialogForItem(item.id);
      });
      cropGalleryList.appendChild(button);
    });
  }

  function renderGallery() {
    imageGallery.hidden = galleryItems.length === 0;
    galleryList.innerHTML = "";

    galleryItems.forEach((item, index) => {
      const card = document.createElement("div");
      const image = document.createElement("img");
      const coverBadge = document.createElement("span");
      const actions = document.createElement("div");
      const deleteButton = createIconButton("delete", "Bild löschen", "gallery-image-delete danger-button");
      const isCoverImage = index === 0;

      card.className = "gallery-image-card";
      card.classList.toggle("is-title-image", isCoverImage);
      card.classList.toggle("is-drop-placeholder", item.id === draggedGalleryItemId && galleryDragState?.active);
      card.dataset.itemId = item.id;
      card.setAttribute("aria-label", isCoverImage ? "Titelbild" : `Galeriebild ${index + 1}`);
      image.src = item.source;
      image.alt = "";
      image.draggable = false;
      coverBadge.className = "gallery-image-cover-badge";
      coverBadge.textContent = "Titelbild";
      actions.className = "gallery-image-actions";
      actions.append(deleteButton);
      card.append(image);

      if (isCoverImage) {
        card.append(coverBadge);
      }

      card.append(actions);

      deleteButton.addEventListener("click", () => {
        pendingDeleteItemId = item.id;
        pendingDeleteTrigger = deleteButton;
        removeImageModal.hidden = false;
        confirmRemoveImage.focus();
      });

      card.addEventListener("pointerdown", (event) => startGalleryPointerDrag(event, item.id));

      galleryList.appendChild(card);
    });

    updateImageFileName();
    syncGallerySubmission();
    renderCropGallery();

    if (editGalleryImagesButton) {
      editGalleryImagesButton.disabled = galleryItems.length === 0;
    }
  }

  function cropOutputSize() {
    return {
      outputWidth: CROP_OUTPUT_WIDTH,
      outputHeight: CROP_OUTPUT_HEIGHT
    };
  }

  function resetCropZoom() {
    cropState.initialCanvasRatio = 1;
    cropState.zoom = CROP_MIN_ZOOM;
    cropZoom.min = String(CROP_MIN_ZOOM);
    cropZoom.max = String(CROP_MAX_ZOOM);
    cropZoom.step = String(CROP_ZOOM_STEP);
    cropZoom.value = String(CROP_MIN_ZOOM);
  }

  function cropZoomMax() {
    return Number(cropZoom.max) || CROP_MIN_ZOOM;
  }

  function cropperRatioForZoom(zoom) {
    return cropState.initialCanvasRatio * zoom;
  }

  function setCropZoomValue(zoom) {
    const maxZoom = cropZoomMax();
    const nextZoom = Math.min(maxZoom, Math.max(CROP_MIN_ZOOM, zoom));

    cropState.zoom = nextZoom;
    cropZoom.value = nextZoom.toFixed(2);
  }

  function configureCropZoomLimit() {
    if (!cropper) {
      return;
    }

    const cropData = cropper.getData();
    const canvasData = cropper.getCanvasData();
    const imageData = cropper.getImageData();
    const resolutionComfortZoom = Math.min(
      cropData.width / (CROP_OUTPUT_WIDTH * CROP_QUALITY_SOURCE_SCALE),
      cropData.height / (CROP_OUTPUT_HEIGHT * CROP_QUALITY_SOURCE_SCALE)
    );
    const imageBasedMaxZoom = Number.isFinite(resolutionComfortZoom) && resolutionComfortZoom > 0
      ? resolutionComfortZoom
      : CROP_MAX_ZOOM;
    const maxZoom = Math.max(
      CROP_MIN_AVAILABLE_MAX_ZOOM,
      Math.min(CROP_MAX_ZOOM, imageBasedMaxZoom)
    );

    cropState.initialCanvasRatio = imageData.naturalWidth
      ? canvasData.width / imageData.naturalWidth
      : 1;
    cropZoom.max = maxZoom.toFixed(2);
    setCropZoomValue(CROP_MIN_ZOOM);
  }

  function destroyCropper() {
    if (!cropper) {
      return;
    }

    cropper.destroy();
    cropper = null;
  }

  function initCropper() {
    destroyCropper();
    resetCropZoom();

    cropper = new Cropper(cropImage, {
      aspectRatio: CROP_ASPECT_RATIO,
      autoCropArea: 0.82,
      background: false,
      cropBoxMovable: true,
      cropBoxResizable: false,
      dragMode: "move",
      movable: true,
      responsive: true,
      restore: false,
      toggleDragModeOnDblclick: false,
      viewMode: 1,
      zoomOnTouch: true,
      zoomOnWheel: true,
      zoomable: true,
      ready() {
        configureCropZoomLimit();
        applyCropImage.focus();
      }
    });
  }

  function currentCropItem() {
    return galleryItems.find((item) => item.id === cropState.itemId) || null;
  }

  function openCropDialogForItem(itemId) {
    const item = galleryItems.find((candidate) => candidate.id === itemId);

    if (!item) {
      return;
    }

    cropState.itemId = item.id;
    cropState.filename = item.filename || "news-image.jpg";
    cropImageModal.hidden = false;
    destroyCropper();
    renderCropGallery();
    cropImage.removeAttribute("src");
    cropImage.onload = () => {
      initCropper();
      cropImage.onload = null;
    };
    cropImage.src = item.source;
  }

  function closeCropDialog() {
    destroyCropper();
    cropImageModal.hidden = true;
    cropState.itemId = null;
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
    return galleryItems[0]?.source || "";
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

  form.addEventListener("submit", async (event) => {
    event.preventDefault();

    if (isSubmittingForm) {
      return;
    }

    syncEditorInput();
    const formData = new FormData(form);
    const submitter = event.submitter instanceof HTMLButtonElement || event.submitter instanceof HTMLInputElement
      ? event.submitter
      : null;

    applyGalleryFormData(formData);

    isSubmittingForm = true;

    if (submitter) {
      submitter.disabled = true;
    }

    try {
      const response = await fetch(form.action, {
        body: formData,
        credentials: "same-origin",
        method: form.method || "POST"
      });

      if (response.redirected) {
        window.location.href = response.url;
        return;
      }

      const html = await response.text();
      document.open();
      document.write(html);
      document.close();
    } catch {
      syncGallerySubmission();
      form.submit();
    } finally {
      isSubmittingForm = false;

      if (submitter) {
        submitter.disabled = false;
      }
    }
  });
  form.addEventListener("formdata", (event) => {
    applyGalleryFormData(event.formData);
  });
  loadInitialGalleryItems();
  renderGallery();

  imageInput.addEventListener("change", () => {
    if (imageInput.files.length) {
      addFiles(imageInput.files);
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

    addFiles(event.dataTransfer.files);
  });

  document.addEventListener("pointermove", moveGalleryPointerDrag, {passive: false});
  document.addEventListener("pointerup", finishGalleryDrag);
  document.addEventListener("pointercancel", finishGalleryDrag);

  if (editGalleryImagesButton) {
    editGalleryImagesButton.addEventListener("click", () => {
      if (galleryItems.length === 0) {
        return;
      }

      openCropDialogForItem(galleryItems[0].id);
    });
  }

  cropZoom.addEventListener("input", () => {
    if (!cropper) {
      return;
    }

    const nextZoom = Math.min(cropZoomMax(), Math.max(CROP_MIN_ZOOM, Number(cropZoom.value)));

    cropper.zoomTo(cropperRatioForZoom(nextZoom));
    setCropZoomValue(nextZoom);
  });

  cropImage.addEventListener("zoom", (event) => {
    if (!cropper || !event.detail) {
      return;
    }

    const maxZoom = cropZoomMax();
    const maxRatio = cropperRatioForZoom(maxZoom);

    if (event.detail.ratio > maxRatio) {
      event.preventDefault();
      cropper.zoomTo(maxRatio);
      setCropZoomValue(maxZoom);
      return;
    }

    setCropZoomValue(event.detail.ratio / cropState.initialCanvasRatio);
  });

  cancelCropImage.addEventListener("click", closeCropDialog);

  cropImageModal.addEventListener("click", (event) => {
    if (event.target === cropImageModal) {
      closeCropDialog();
    }
  });

  applyCropImage.addEventListener("click", () => {
    const item = currentCropItem();

    if (!cropper || !item) {
      return;
    }

    const {outputWidth, outputHeight} = cropOutputSize();
    const canvas = cropper.getCroppedCanvas({
      fillColor: "#fff",
      height: outputHeight,
      imageSmoothingEnabled: true,
      imageSmoothingQuality: "high",
      width: outputWidth
    });

    if (!canvas) {
      return;
    }

    canvas.toBlob((blob) => {
      if (!blob) {
        return;
      }

      const filename = cropState.filename.replace(/\.[^.]+$/, "") + "-ausschnitt.jpg";
      const file = new File([blob], filename, {type: "image/jpeg"});

      replaceGalleryItemFile(item, file);
      renderGallery();
      closeCropDialog();
    }, "image/jpeg", 0.9);
  });

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

    if (pendingDeleteTrigger) {
      pendingDeleteTrigger.focus();
    }

    pendingDeleteItemId = null;
    pendingDeleteTrigger = null;
  }

  if (removeImageModal) {
    cancelRemoveImage.addEventListener("click", closeRemoveImageModal);

    confirmRemoveImage.addEventListener("click", () => {
      removeGalleryItem(pendingDeleteItemId);
      closeRemoveImageModal();
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

  window.addEventListener("pagehide", () => {
    galleryItems.forEach(revokeObjectUrl);
  });
})();
