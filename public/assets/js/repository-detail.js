// Inicio de interacciones del detalle del repositorio
const repositoryDetailSkeleton = document.querySelector("#repositoryDetailSkeleton");
const repositoryDetailContent = document.querySelector("#repositoryDetailContent");
const repositoryDetailFavorite = document.querySelector("#repositoryDetailFavorite");
const repositoryDetailToast = document.querySelector("#repositoryDetailToast");
const repositoryExplorerBreadcrumb = document.querySelector("#repositoryExplorerBreadcrumb");
const repositoryFileList = document.querySelector("#repositoryFileList");
const repositoryFileRows = document.querySelector("#repositoryFileRows");
const repositoryExplorerState = document.querySelector("#repositoryExplorerState");
const repositoryPreview = document.querySelector("#repositoryPreview");
const repositoryPreviewBack = document.querySelector("#repositoryPreviewBack");
const repositoryPreviewType = document.querySelector("#repositoryPreviewType");
const repositoryPreviewTitle = document.querySelector("#repositoryPreviewTitle");
const repositoryPreviewMeta = document.querySelector("#repositoryPreviewMeta");
const repositoryPreviewDownload = document.querySelector("#repositoryPreviewDownload");
const repositoryPreviewExpand = document.querySelector("#repositoryPreviewExpand");
const repositoryPreviewModal = document.querySelector("#repositoryPreviewModal");
const repositoryPreviewModalBody = document.querySelector("#repositoryPreviewModalBody");
const repositoryPreviewModalTitle = document.querySelector("#repositoryPreviewModalTitle");
const repositoryPreviewModalClose = document.querySelector("#repositoryPreviewModalClose");
const repositoryPreviewMessage = document.querySelector("#repositoryPreviewMessage");
const repositoryPreviewState = document.querySelector("#repositoryPreviewState");
const repositoryPreviewPdf = document.querySelector("#repositoryPreviewPdf");
const repositoryPreviewImageShell = document.querySelector("#repositoryPreviewImageShell");
const repositoryPreviewImage = document.querySelector("#repositoryPreviewImage");
const repositoryPreviewText = document.querySelector("#repositoryPreviewText");
const repositoryPreviewCode = document.querySelector("#repositoryPreviewCode");
const repositoryPreviewDocx = document.querySelector("#repositoryPreviewDocx");
const repositoryImageZoomOut = document.querySelector("#repositoryImageZoomOut");
const repositoryImageZoomReset = document.querySelector("#repositoryImageZoomReset");
const repositoryImageZoomIn = document.querySelector("#repositoryImageZoomIn");
let repositoryDetailToastTimer = null;
let repositoryArchiveRequest = null;
let repositoryPreviewRequest = null;
let repositoryImageZoom = 1;
let repositoryPreviewReturnFocus = null;
let repositoryPreviewModalPanel = null;
let repositoryPreviewModalPlaceholder = null;

setTimeout(() => {
    if (repositoryDetailSkeleton) repositoryDetailSkeleton.hidden = true;
    if (repositoryDetailContent) {
        repositoryDetailContent.style.display = "block";
        requestAnimationFrame(() => repositoryDetailContent.classList.add("is-loaded"));
    }
}, 0);

function showRepositoryDetailToast(message) {
    if (!repositoryDetailToast) return;

    window.clearTimeout(repositoryDetailToastTimer);
    repositoryDetailToast.textContent = message;
    repositoryDetailToast.hidden = false;
    requestAnimationFrame(() => repositoryDetailToast.classList.add("show"));

    repositoryDetailToastTimer = window.setTimeout(() => {
        repositoryDetailToast.classList.remove("show");
        window.setTimeout(() => {
            repositoryDetailToast.hidden = true;
        }, 220);
    }, 2400);
}

function setRepositoryExplorerState(status, message = "") {
    if (!repositoryExplorerState || !repositoryFileList) return;

    repositoryExplorerState.className = `repository-explorer-state repository-explorer-state--${status}`;
    const icon = repositoryExplorerState.querySelector("i");
    const text = repositoryExplorerState.querySelector("p");
    const iconByStatus = {
        loading: "fa-spinner fa-spin",
        empty: "fa-folder-open",
        not_found: "fa-folder-open",
        unreadable: "fa-triangle-exclamation",
        invalid_path: "fa-shield-halved",
        error: "fa-triangle-exclamation",
    };

    if (icon) icon.className = `fa-solid ${iconByStatus[status] ?? iconByStatus.error}`;
    if (text) text.textContent = message;
    repositoryExplorerState.hidden = status === "ready";
    repositoryFileList.hidden = status !== "ready";
    repositoryExplorerState.closest(".repository-explorer")?.setAttribute("aria-busy", String(status === "loading"));
}

function renderRepositoryBreadcrumbs(breadcrumbs, currentPath) {
    if (!repositoryExplorerBreadcrumb) return;
    repositoryExplorerBreadcrumb.replaceChildren();

    breadcrumbs.forEach((breadcrumb, index) => {
        if (index > 0) {
            const separator = document.createElement("i");
            separator.className = "fa-solid fa-chevron-right";
            separator.setAttribute("aria-hidden", "true");
            repositoryExplorerBreadcrumb.append(separator);
        }

        const button = document.createElement("button");
        button.type = "button";
        button.dataset.archivePath = breadcrumb.path;
        if (breadcrumb.path === currentPath) button.setAttribute("aria-current", "page");
        if (index === 0) {
            const icon = document.createElement("i");
            icon.className = "fa-solid fa-box-archive";
            icon.setAttribute("aria-hidden", "true");
            button.append(icon);
        }
        button.append(document.createTextNode(breadcrumb.label));
        repositoryExplorerBreadcrumb.append(button);
    });
}

function renderRepositoryFileRows(items) {
    if (!repositoryFileRows) return;
    repositoryFileRows.replaceChildren();

    items.forEach((item) => {
        const row = document.createElement("div");
        row.className = "repository-file-row";
        row.setAttribute("role", "row");

        const nameCell = document.createElement("button");
        nameCell.className = "repository-file-name repository-file-entry";
        nameCell.setAttribute("role", "cell");
        nameCell.type = "button";
        if (item.kind === "folder") {
            nameCell.dataset.folderPath = item.path;
        } else {
            nameCell.dataset.filePath = item.path;
        }
        const icon = document.createElement("i");
        icon.className = `fa-solid ${item.icon} repository-file-icon--${item.kind}`;
        icon.setAttribute("aria-hidden", "true");
        const name = document.createElement("strong");
        name.textContent = item.name;
        nameCell.append(icon, name);

        const typeCell = document.createElement("span");
        typeCell.setAttribute("role", "cell");
        typeCell.textContent = item.type;
        const sizeCell = document.createElement("span");
        sizeCell.setAttribute("role", "cell");
        sizeCell.textContent = item.size;
        const actionCell = document.createElement("span");
        actionCell.className = "repository-file-action";
        actionCell.setAttribute("role", "cell");
        if (item.kind === "file") {
            const previewButton = document.createElement("button");
            previewButton.type = "button";
            previewButton.className = "repository-file-preview-action";
            previewButton.dataset.filePath = item.path;
            previewButton.setAttribute("aria-label", `Ver ${item.name}`);
            previewButton.innerHTML = '<i class="fa-solid fa-eye" aria-hidden="true"></i><span>Ver</span>';
            const link = document.createElement("a");
            const downloadUrl = repositoryDetailContent?.dataset.fileDownloadUrl ?? "";
            const projectId = repositoryDetailContent?.dataset.projectId ?? "";
            link.href = `${downloadUrl}&id=${encodeURIComponent(projectId)}&path=${encodeURIComponent(item.path)}`;
            link.setAttribute("aria-label", `Descargar ${item.name}`);
            const downloadIcon = document.createElement("i");
            downloadIcon.className = "fa-solid fa-download";
            downloadIcon.setAttribute("aria-hidden", "true");
            link.append(downloadIcon);
            actionCell.append(previewButton, link);
        }
        row.append(nameCell, typeCell, sizeCell, actionCell);
        repositoryFileRows.append(row);
    });
}

function resetRepositoryPreviewPanels() {
    closeRepositoryPreviewModal(false);
    [repositoryPreviewPdf, repositoryPreviewImageShell, repositoryPreviewText, repositoryPreviewCode, repositoryPreviewDocx].forEach((panel) => {
        if (panel) panel.hidden = true;
    });
    if (repositoryPreviewPdf) repositoryPreviewPdf.removeAttribute("src");
    if (repositoryPreviewImage) repositoryPreviewImage.removeAttribute("src");
    if (repositoryPreviewText) repositoryPreviewText.textContent = "";
    if (repositoryPreviewCode) repositoryPreviewCode.replaceChildren();
    if (repositoryPreviewDocx) repositoryPreviewDocx.replaceChildren();
    if (repositoryPreviewMessage) {
        repositoryPreviewMessage.hidden = true;
        repositoryPreviewMessage.textContent = "";
    }
    if (repositoryPreviewExpand) repositoryPreviewExpand.disabled = true;
}

function setRepositoryPreviewState(message, iconClass = "fa-spinner fa-spin") {
    if (!repositoryPreviewState) return;
    const icon = repositoryPreviewState.querySelector("i");
    const text = repositoryPreviewState.querySelector("p");
    if (icon) icon.className = `fa-solid ${iconClass}`;
    if (text) text.textContent = message;
    repositoryPreviewState.hidden = false;
}

function renderRepositoryCode(content, language) {
    if (!repositoryPreviewCode) return;
    repositoryPreviewCode.replaceChildren();
    const keywordPattern = /("(?:\\.|[^"\\])*"|'(?:\\.|[^'\\])*'|\/\/.*$|#.*$|\b(?:class|function|final|public|private|protected|const|let|var|return|if|else|foreach|for|while|new|true|false|null|echo|declare|namespace|use|import|from|def|SELECT|FROM|WHERE|CREATE|TABLE|INSERT|UPDATE|DELETE)\b|\b\d+(?:\.\d+)?\b)/gim;

    content.split("\n").forEach((line, index) => {
        const lineElement = document.createElement("div");
        lineElement.className = "repository-code-line";
        const number = document.createElement("span");
        number.className = "repository-code-number";
        number.textContent = String(index + 1);
        const code = document.createElement("code");
        code.dataset.language = language;
        let lastIndex = 0;
        for (const match of line.matchAll(keywordPattern)) {
            code.append(document.createTextNode(line.slice(lastIndex, match.index)));
            const token = document.createElement("span");
            const value = match[0];
            token.className = /^['"]/.test(value)
                ? "repository-code-token--string"
                : /^(\/\/|#)/.test(value)
                    ? "repository-code-token--comment"
                    : /^\d/.test(value)
                        ? "repository-code-token--number"
                        : "repository-code-token--keyword";
            token.textContent = value;
            code.append(token);
            lastIndex = (match.index ?? 0) + value.length;
        }
        code.append(document.createTextNode(line.slice(lastIndex)));
        lineElement.append(number, code);
        repositoryPreviewCode.append(lineElement);
    });
}

function updateRepositoryImageZoom(nextZoom) {
    repositoryImageZoom = Math.min(3, Math.max(.5, nextZoom));
    if (repositoryPreviewImage) repositoryPreviewImage.style.transform = `scale(${repositoryImageZoom})`;
    if (repositoryImageZoomReset) repositoryImageZoomReset.textContent = `${Math.round(repositoryImageZoom * 100)}%`;
    if (repositoryImageZoomOut) repositoryImageZoomOut.disabled = repositoryImageZoom <= .5;
    if (repositoryImageZoomIn) repositoryImageZoomIn.disabled = repositoryImageZoom >= 3;
}

function openRepositoryPreviewModal() {
    if (!repositoryPreviewModal || !repositoryPreviewModalBody) return;
    const panels = [repositoryPreviewPdf, repositoryPreviewImageShell, repositoryPreviewText, repositoryPreviewCode, repositoryPreviewDocx];
    const activePanel = panels.find((panel) => panel && !panel.hidden);
    if (!activePanel) return;

    repositoryPreviewModalPanel = activePanel;
    repositoryPreviewModalPlaceholder = document.createComment("repository-preview-position");
    activePanel.before(repositoryPreviewModalPlaceholder);
    if (repositoryPreviewModal.parentElement !== document.body) document.body.append(repositoryPreviewModal);
    repositoryPreviewModalBody.append(activePanel);
    repositoryPreviewModal.classList.toggle("repository-preview-modal--pdf", activePanel === repositoryPreviewPdf);
    repositoryPreviewModal.classList.toggle("repository-preview-modal--image", activePanel === repositoryPreviewImageShell);
    if (repositoryPreviewModalTitle) repositoryPreviewModalTitle.textContent = repositoryPreviewTitle?.textContent?.trim() || "Vista ampliada";
    repositoryPreviewModal.hidden = false;
    document.body.classList.add("repository-preview-modal-open");
    repositoryPreviewModalClose?.focus();
}

function closeRepositoryPreviewModal(restoreFocus = true) {
    if (!repositoryPreviewModal) return;
    if (repositoryPreviewModalPanel && repositoryPreviewModalPlaceholder?.parentNode) {
        repositoryPreviewModalPlaceholder.before(repositoryPreviewModalPanel);
        repositoryPreviewModalPlaceholder.remove();
    }
    repositoryPreviewModal.hidden = true;
    repositoryPreviewModal.classList.remove("repository-preview-modal--pdf", "repository-preview-modal--image");
    document.body.classList.remove("repository-preview-modal-open");
    repositoryPreviewModalBody?.replaceChildren();
    repositoryPreviewModalPanel = null;
    repositoryPreviewModalPlaceholder = null;
    if (restoreFocus) repositoryPreviewExpand?.focus();
}

function renderRepositoryDocx(blocks) {
    if (!repositoryPreviewDocx) return;
    repositoryPreviewDocx.replaceChildren();

    blocks.forEach((block) => {
        if (block.type === "table" && Array.isArray(block.rows)) {
            const shell = document.createElement("div");
            shell.className = "repository-docx-table-shell";
            const table = document.createElement("table");
            block.rows.forEach((row, rowIndex) => {
                const tableRow = document.createElement("tr");
                row.forEach((cell) => {
                    const element = document.createElement(rowIndex === 0 ? "th" : "td");
                    element.textContent = String(cell);
                    tableRow.append(element);
                });
                table.append(tableRow);
            });
            shell.append(table);
            repositoryPreviewDocx.append(shell);
            return;
        }

        let element;
        if (block.type === "heading") {
            const level = Math.min(6, Math.max(2, Number(block.level) + 1 || 2));
            element = document.createElement(`h${level}`);
        } else {
            element = document.createElement("p");
            if (block.type === "list") element.className = "repository-docx-list-item";
        }
        element.textContent = String(block.text ?? "");
        repositoryPreviewDocx.append(element);
    });
}

async function loadRepositoryPreview(path, trigger = null) {
    const actionUrl = repositoryDetailContent?.dataset.previewUrl ?? "";
    const projectId = repositoryDetailContent?.dataset.projectId ?? "";
    if (!actionUrl || !projectId || !repositoryPreview) return;

    repositoryPreviewRequest?.abort();
    repositoryPreviewRequest = new AbortController();
    repositoryPreviewReturnFocus = trigger;
    resetRepositoryPreviewPanels();
    repositoryPreview.hidden = false;
    repositoryPreview.setAttribute("aria-busy", "true");
    repositoryPreviewBack?.focus({ preventScroll: true });
    if (repositoryFileList) repositoryFileList.hidden = true;
    if (repositoryExplorerState) repositoryExplorerState.hidden = true;
    setRepositoryPreviewState("Preparando vista previa...");

    try {
        const url = `${actionUrl}&id=${encodeURIComponent(projectId)}&path=${encodeURIComponent(path)}`;
        const response = await fetch(url, { signal: repositoryPreviewRequest.signal });
        const result = await response.json();
        const preview = result.data?.preview;
        if (!response.ok || !result.success || !preview) {
            throw new Error(result.message || "No fue posible leer este archivo.");
        }

        if (repositoryPreviewType) repositoryPreviewType.textContent = preview.type_label;
        if (repositoryPreviewTitle) {
            repositoryPreviewTitle.textContent = preview.name;
            repositoryPreviewTitle.title = preview.name;
        }
        if (repositoryPreviewMeta) repositoryPreviewMeta.textContent = `${preview.type_label} · ${preview.size}${preview.language ? ` · ${preview.language}` : ""}`;
        if (repositoryPreviewDownload) repositoryPreviewDownload.href = preview.download_url;
        if (repositoryPreviewMessage && preview.message) {
            repositoryPreviewMessage.textContent = preview.message;
            repositoryPreviewMessage.hidden = false;
        }

        if (preview.status !== "ready") {
            const icon = preview.status === "empty" ? "fa-file-circle-xmark" : preview.status === "too_large" ? "fa-file-arrow-down" : "fa-eye-slash";
            setRepositoryPreviewState(preview.message, icon);
            repositoryPreview.setAttribute("aria-busy", "false");
            return;
        }

        if (repositoryPreviewState) repositoryPreviewState.hidden = true;
        repositoryPreview.setAttribute("aria-busy", "false");
        if (repositoryPreviewExpand) repositoryPreviewExpand.disabled = false;
        if (preview.preview_type === "pdf" && repositoryPreviewPdf) {
            repositoryPreviewPdf.src = preview.content_url;
            repositoryPreviewPdf.hidden = false;
        } else if (preview.preview_type === "image" && repositoryPreviewImageShell && repositoryPreviewImage) {
            repositoryPreviewImage.alt = `Vista previa de ${preview.name}`;
            repositoryPreviewImage.src = preview.content_url;
            repositoryPreviewImageShell.hidden = false;
            updateRepositoryImageZoom(1);
        } else if (preview.preview_type === "text" && repositoryPreviewText) {
            repositoryPreviewText.textContent = preview.content;
            repositoryPreviewText.hidden = false;
        } else if (preview.preview_type === "code" && repositoryPreviewCode) {
            renderRepositoryCode(preview.content, preview.language);
            repositoryPreviewCode.hidden = false;
        } else if (preview.preview_type === "docx" && repositoryPreviewDocx) {
            renderRepositoryDocx(Array.isArray(preview.blocks) ? preview.blocks : []);
            repositoryPreviewDocx.hidden = false;
        }
    } catch (error) {
        if (error instanceof DOMException && error.name === "AbortError") return;
        repositoryPreview.setAttribute("aria-busy", "false");
        setRepositoryPreviewState(error instanceof Error ? error.message : "No fue posible leer este archivo.", "fa-triangle-exclamation");
    }
}

function closeRepositoryPreview(restoreFocus = false) {
    repositoryPreviewRequest?.abort();
    closeRepositoryPreviewModal(false);
    if (repositoryPreview) repositoryPreview.hidden = true;
    resetRepositoryPreviewPanels();
    setRepositoryExplorerState("ready", "");
    if (restoreFocus && repositoryPreviewReturnFocus instanceof HTMLElement) repositoryPreviewReturnFocus.focus();
    repositoryPreviewReturnFocus = null;
}

async function loadRepositoryFolder(path) {
    const actionUrl = repositoryDetailContent?.dataset.filesUrl ?? "";
    const projectId = repositoryDetailContent?.dataset.projectId ?? "";
    if (!actionUrl || !projectId) return;

    repositoryArchiveRequest?.abort();
    closeRepositoryPreview(false);
    repositoryArchiveRequest = new AbortController();
    setRepositoryExplorerState("loading", "Cargando contenido...");

    try {
        const url = `${actionUrl}&id=${encodeURIComponent(projectId)}&path=${encodeURIComponent(path)}`;
        const response = await fetch(url, { signal: repositoryArchiveRequest.signal });
        const result = await response.json();
        const archive = result.data?.archive;

        if (!response.ok || !result.success || !archive) {
            throw new Error(result.message || "No fue posible abrir el contenido del proyecto.");
        }

        renderRepositoryBreadcrumbs(archive.breadcrumbs, archive.path);
        renderRepositoryFileRows(archive.items);
        setRepositoryExplorerState(archive.status, archive.message);
    } catch (error) {
        if (error instanceof DOMException && error.name === "AbortError") return;
        setRepositoryExplorerState("error", error instanceof Error ? error.message : "No fue posible abrir el contenido del proyecto.");
    }
}

repositoryFileRows?.addEventListener("click", (event) => {
    const folderButton = event.target.closest("[data-folder-path]");
    if (folderButton) loadRepositoryFolder(folderButton.dataset.folderPath ?? "");
    const fileButton = event.target.closest("[data-file-path]");
    if (fileButton) loadRepositoryPreview(fileButton.dataset.filePath ?? "", fileButton);
});

repositoryExplorerBreadcrumb?.addEventListener("click", (event) => {
    const breadcrumbButton = event.target.closest("[data-archive-path]");
    if (breadcrumbButton) loadRepositoryFolder(breadcrumbButton.dataset.archivePath ?? "");
});

repositoryPreviewBack?.addEventListener("click", () => closeRepositoryPreview(true));
repositoryPreviewExpand?.addEventListener("click", openRepositoryPreviewModal);
repositoryPreviewModalClose?.addEventListener("click", () => closeRepositoryPreviewModal(true));
repositoryPreviewModal?.addEventListener("click", (event) => {
    if (event.target === repositoryPreviewModal) closeRepositoryPreviewModal(true);
});
repositoryImageZoomOut?.addEventListener("click", () => updateRepositoryImageZoom(repositoryImageZoom - .25));
repositoryImageZoomReset?.addEventListener("click", () => updateRepositoryImageZoom(1));
repositoryImageZoomIn?.addEventListener("click", () => updateRepositoryImageZoom(repositoryImageZoom + .25));

document.addEventListener("keydown", (event) => {
    if (event.key === "Escape" && repositoryPreviewModal && !repositoryPreviewModal.hidden) {
        closeRepositoryPreviewModal(true);
    }
});

repositoryDetailFavorite?.addEventListener("click", async () => {
    const actionUrl = repositoryDetailContent?.dataset.favoriteUrl ?? "";
    const csrfToken = repositoryDetailContent?.dataset.favoriteCsrf ?? "";
    const projectId = repositoryDetailContent?.dataset.projectId ?? "";

    repositoryDetailFavorite.disabled = true;
    repositoryDetailFavorite.setAttribute("aria-busy", "true");

    try {
        const response = await fetch(actionUrl, {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8" },
            body: new URLSearchParams({ project_id: projectId, csrf_token: csrfToken }),
        });
        const result = await response.json();

        if (!response.ok || !result.success) {
            throw new Error(result.message || "No fue posible completar la acción.");
        }

        const isFavorite = Boolean(result.data.favorite);
        const icon = repositoryDetailFavorite.querySelector("i");
        const label = repositoryDetailFavorite.querySelector("span");
        repositoryDetailFavorite.classList.toggle("is-favorite", isFavorite);
        repositoryDetailFavorite.setAttribute("aria-pressed", String(isFavorite));
        icon?.classList.toggle("fa-solid", isFavorite);
        icon?.classList.toggle("fa-regular", !isFavorite);
        if (label) label.textContent = isFavorite ? "Guardado en favoritos" : "Guardar en favoritos";
        showRepositoryDetailToast(result.message);
    } catch (error) {
        showRepositoryDetailToast(error instanceof Error ? error.message : "No fue posible completar la acción.");
    } finally {
        repositoryDetailFavorite.disabled = false;
        repositoryDetailFavorite.removeAttribute("aria-busy");
    }
});

// Final de interacciones del detalle del repositorio

// Inicio de interacciones visuales neutrales del Expediente Digital
const digitalRecord = document.querySelector("[data-digital-record]");
const digitalRecordMenu = digitalRecord?.querySelector("[data-record-menu]");
const digitalRecordMenuTrigger = digitalRecordMenu?.querySelector("[data-record-menu-trigger]");
const digitalRecordMenuPanel = digitalRecordMenu?.querySelector("[data-record-menu-panel]");

function closeDigitalRecordMenu(restoreFocus = false) {
    if (!digitalRecordMenuPanel || !digitalRecordMenuTrigger) return;
    digitalRecordMenuPanel.hidden = true;
    digitalRecordMenuTrigger.setAttribute("aria-expanded", "false");
    if (restoreFocus) digitalRecordMenuTrigger.focus();
}

digitalRecordMenuTrigger?.addEventListener("click", () => {
    const willOpen = digitalRecordMenuPanel?.hidden ?? false;
    if (!digitalRecordMenuPanel) return;
    digitalRecordMenuPanel.hidden = !willOpen;
    digitalRecordMenuTrigger.setAttribute("aria-expanded", String(willOpen));
    if (willOpen) digitalRecordMenuPanel.querySelector("button:not([disabled])")?.focus();
});

document.addEventListener("click", (event) => {
    if (digitalRecordMenu && !digitalRecordMenu.contains(event.target)) closeDigitalRecordMenu();
});

const neutralFileList = digitalRecord?.querySelector("[data-record-files]");
const neutralFileButtons = [...(neutralFileList?.querySelectorAll("[data-record-file]") ?? [])];
const neutralViewer = neutralFileList?.querySelector("[data-record-viewer]");
const neutralViewerName = neutralViewer?.querySelector("[data-viewer-name]");
const neutralViewerMeta = neutralViewer?.querySelector("[data-viewer-meta]");
const neutralViewerBody = neutralViewer?.querySelector("[data-viewer-body]");
const neutralBackToFiles = neutralViewer?.querySelector("[data-back-to-files]");

neutralFileButtons.forEach((button) => button.addEventListener("click", () => {
    neutralFileButtons.forEach((item) => {
        const selected = item === button;
        item.classList.toggle("is-selected", selected);
        item.setAttribute("aria-pressed", String(selected));
    });
    if (neutralViewerName) neutralViewerName.textContent = button.dataset.fileName ?? "Archivo";
    if (neutralViewerMeta) neutralViewerMeta.textContent = `${button.dataset.fileType ?? "Archivo"} · ${button.dataset.fileSize ?? "Tamaño no disponible"}`;
    neutralViewer?.scrollIntoView({ behavior: "smooth", block: "nearest" });
    neutralViewerBody?.focus({ preventScroll: true });
}));

neutralBackToFiles?.addEventListener("click", () => {
    neutralFileButtons.find((button) => button.getAttribute("aria-pressed") === "true")?.focus();
});

document.addEventListener("keydown", (event) => {
    if (event.key === "Escape" && digitalRecordMenuPanel && !digitalRecordMenuPanel.hidden) {
        closeDigitalRecordMenu(true);
    }
});
// Final de interacciones visuales neutrales del Expediente Digital

// Inicio del modo de edición neutral del Expediente Digital
const recordForm = digitalRecord?.querySelector("[data-record-form]");
const recordErrorSummary = digitalRecord?.querySelector("[data-record-error-summary]");
const recordErrorMessage = recordErrorSummary?.querySelector("[data-record-error-message]");
const recordFormStatus = digitalRecord?.querySelector("[data-record-form-status]");
const recordDirtyMessage = digitalRecord?.querySelector("[data-record-dirty-message]");
const recordDiscardDialog = document.querySelector("[data-record-discard-dialog]");
const recordSaveDialog = document.querySelector("[data-record-save-dialog]");
if (recordDiscardDialog && recordDiscardDialog.parentElement !== document.body) document.body.append(recordDiscardDialog);
if (recordSaveDialog && recordSaveDialog.parentElement !== document.body) document.body.append(recordSaveDialog);
const recordContinueButton = recordDiscardDialog?.querySelector("[data-record-continue]");
const recordDiscardButton = recordDiscardDialog?.querySelector("[data-record-discard]");
const recordSaveContinueButton = recordSaveDialog?.querySelector("[data-record-save-continue]");
const recordSaveConfirmButton = recordSaveDialog?.querySelector("[data-record-save-confirm]");
const recordSaveLabel = recordSaveDialog?.querySelector("[data-record-save-label]");
let recordPendingNavigation = "";
let recordDialogReturnFocus = null;
let recordIsDirty = false;
let recordIsSubmitting = false;
let recordDiscardConfirmed = false;
let recordBackgroundState = [];
let recordBodyOverflow = "";
let recordBodyPaddingRight = "";
let recordHtmlOverflow = "";

function recordFormEntries() {
    if (!recordForm) return [];
    return [...recordForm.elements]
        .filter((control) => control.name && !["_csrf", "id"].includes(control.name) && !control.disabled)
        .map((control) => [control.name, String(control.value ?? "").trim().replace(/\r\n/g, "\n")]);
}

let recordInitialValues = JSON.stringify(recordFormEntries());

function recordFormIsDirty() {
    return Boolean(recordForm) && JSON.stringify(recordFormEntries()) !== recordInitialValues;
}

function updateRecordDirtyState() {
    const dirty = recordFormIsDirty();
    recordIsDirty = dirty;
    if (recordForm) recordForm.dataset.dirty = String(dirty);
    if (recordDirtyMessage) recordDirtyMessage.textContent = dirty ? "Tienes cambios sin guardar." : "Sin cambios pendientes.";
    return dirty;
}

function clearRecordErrors() {
    recordErrorSummary?.setAttribute("hidden", "");
    recordForm?.querySelectorAll("[aria-invalid]").forEach((field) => field.removeAttribute("aria-invalid"));
    recordForm?.querySelectorAll("[data-field-error]").forEach((message) => { message.textContent = ""; });
}

function setRecordFieldError(field, message) {
    if (!(field instanceof HTMLElement)) return;
    field.setAttribute("aria-invalid", "true");
    const error = recordForm?.querySelector(`[data-field-error="${field.getAttribute("name")}"]`);
    if (error) error.textContent = message;
}

function showRecordError(message, fieldName = "") {
    if (recordErrorMessage) recordErrorMessage.textContent = message;
    recordErrorSummary?.removeAttribute("hidden");
    if (fieldName) setRecordFieldError(recordForm?.elements.namedItem(fieldName), message);
    recordErrorSummary?.focus();
}

function fieldForServerMessage(message) {
    const normalized = message.toLocaleLowerCase("es");
    if (normalized.includes("título")) return "title";
    if (normalized.includes("categoría")) return "category_id";
    if (normalized.includes("tipo de material")) return "material_type";
    if (normalized.includes("descripción corta")) return "description";
    if (normalized.includes("descripción completa")) return "full_description";
    if (normalized.includes("responsable")) return "publisher";
    if (normalized.includes("fecha de publicación")) return "publication_date";
    return "";
}

function lockRecordBackground(activeDialog, trigger) {
    const scrollbarWidth = Math.max(0, window.innerWidth - document.documentElement.clientWidth);
    recordDialogReturnFocus = trigger;
    recordBodyOverflow = document.body.style.overflow;
    recordBodyPaddingRight = document.body.style.paddingRight;
    recordHtmlOverflow = document.documentElement.style.overflow;
    recordBackgroundState = [...document.body.children]
        .filter((element) => element !== activeDialog && !["SCRIPT", "STYLE"].includes(element.tagName))
        .map((element) => ({
            element,
            inert: element.inert,
            ariaHidden: element.getAttribute("aria-hidden"),
        }));
    recordBackgroundState.forEach(({ element }) => {
        element.inert = true;
        element.setAttribute("aria-hidden", "true");
    });
    document.body.classList.add("dialog-open");
    document.documentElement.classList.add("dialog-open");
    document.body.style.overflow = "hidden";
    document.documentElement.style.overflow = "hidden";
    if (scrollbarWidth > 0) document.body.style.paddingRight = `${scrollbarWidth}px`;
}

function unlockRecordBackground(restoreFocus = true) {
    recordBackgroundState.forEach(({ element, inert, ariaHidden }) => {
        element.inert = inert;
        if (ariaHidden === null) element.removeAttribute("aria-hidden");
        else element.setAttribute("aria-hidden", ariaHidden);
    });
    recordBackgroundState = [];
    document.body.classList.remove("dialog-open");
    document.documentElement.classList.remove("dialog-open");
    document.body.style.overflow = recordBodyOverflow;
    document.body.style.paddingRight = recordBodyPaddingRight;
    document.documentElement.style.overflow = recordHtmlOverflow;
    if (restoreFocus && recordDialogReturnFocus instanceof HTMLElement) recordDialogReturnFocus.focus();
    recordDialogReturnFocus = null;
}

function openRecordDiscardDialog(url, trigger) {
    if (!recordDiscardDialog) return;
    recordPendingNavigation = url;
    recordDiscardConfirmed = false;
    lockRecordBackground(recordDiscardDialog, trigger);
    recordDiscardDialog.hidden = false;
    recordContinueButton?.focus();
}

function closeRecordDiscardDialog(restoreFocus = true) {
    if (!recordDiscardDialog) return;
    recordDiscardDialog.hidden = true;
    unlockRecordBackground(restoreFocus);
    recordPendingNavigation = "";
}

function openRecordSaveDialog(trigger) {
    if (!recordSaveDialog || recordIsSubmitting) return;
    lockRecordBackground(recordSaveDialog, trigger);
    recordSaveDialog.hidden = false;
    recordSaveContinueButton?.focus();
}

function closeRecordSaveDialog(restoreFocus = true) {
    if (!recordSaveDialog) return;
    recordSaveDialog.hidden = true;
    unlockRecordBackground(restoreFocus);
}

recordForm?.addEventListener("input", updateRecordDirtyState);
recordForm?.addEventListener("change", updateRecordDirtyState);
updateRecordDirtyState();

recordForm?.addEventListener("submit", (event) => {
    event.preventDefault();
    if (recordIsSubmitting) return;
    clearRecordErrors();
    if (recordFormStatus) recordFormStatus.hidden = true;

    if (!updateRecordDirtyState()) {
        if (recordFormStatus) {
            recordFormStatus.textContent = "No se detectaron cambios para guardar.";
            recordFormStatus.hidden = false;
            recordFormStatus.focus?.();
        }
        return;
    }

    if (!recordForm.checkValidity()) {
        const invalidFields = [...recordForm.querySelectorAll(":invalid")];
        invalidFields.forEach((field) => setRecordFieldError(field, field.validationMessage));
        showRecordError("Revisa los campos señalados antes de guardar.");
        return;
    }

    openRecordSaveDialog(recordForm.querySelector('[type="submit"]'));
});

async function submitRecordForm() {
    if (!recordForm || recordIsSubmitting) return;
    const submitButton = recordForm.querySelector('[type="submit"]');
    if (submitButton) submitButton.disabled = true;
    if (recordSaveConfirmButton) recordSaveConfirmButton.disabled = true;
    if (recordSaveContinueButton) recordSaveContinueButton.disabled = true;
    if (recordSaveLabel) recordSaveLabel.textContent = "Guardando…";
    recordIsSubmitting = true;
    recordDiscardConfirmed = false;
    try {
        const response = await fetch(recordForm.action, { method: "POST", body: new FormData(recordForm) });
        const result = await response.json();
        if (!response.ok || !result.success) throw new Error(result.message || "No fue posible guardar los cambios.");
        if (result.data?.no_changes) {
            recordInitialValues = JSON.stringify(recordFormEntries());
            updateRecordDirtyState();
            recordIsSubmitting = false;
            closeRecordSaveDialog(false);
            if (recordFormStatus) {
                recordFormStatus.textContent = result.message;
                recordFormStatus.hidden = false;
            }
            submitButton?.focus();
            return;
        }
        recordIsDirty = false;
        recordForm.dataset.dirty = "false";
        window.location.assign(recordForm.dataset.successUrl);
    } catch (error) {
        recordIsSubmitting = false;
        updateRecordDirtyState();
        closeRecordSaveDialog(false);
        const message = error instanceof Error ? error.message : "No fue posible guardar los cambios.";
        showRecordError(message, fieldForServerMessage(message));
    } finally {
        if (submitButton) submitButton.disabled = false;
        if (recordSaveConfirmButton) recordSaveConfirmButton.disabled = false;
        if (recordSaveContinueButton) recordSaveContinueButton.disabled = false;
        if (recordSaveLabel) recordSaveLabel.textContent = "Guardar cambios";
    }
}

digitalRecord?.addEventListener("click", (event) => {
    const link = event.target.closest("a[href]");
    if (!recordForm || !link || recordIsSubmitting || !updateRecordDirtyState()) return;
    if (link.target === "_blank" || link.hasAttribute("download")) return;
    event.preventDefault();
    openRecordDiscardDialog(link.href, link);
});

recordContinueButton?.addEventListener("click", () => closeRecordDiscardDialog(true));
recordSaveContinueButton?.addEventListener("click", () => closeRecordSaveDialog(true));
recordSaveConfirmButton?.addEventListener("click", submitRecordForm);
recordDiscardButton?.addEventListener("click", () => {
    const target = recordPendingNavigation;
    recordDiscardConfirmed = true;
    recordIsDirty = false;
    if (recordForm) recordForm.dataset.dirty = "false";
    closeRecordDiscardDialog(false);
    if (target) window.location.assign(target);
});
recordDiscardDialog?.addEventListener("click", (event) => {
    if (event.target === recordDiscardDialog) closeRecordDiscardDialog(true);
});
recordSaveDialog?.addEventListener("click", (event) => {
    if (event.target === recordSaveDialog && !recordIsSubmitting) closeRecordSaveDialog(true);
});

window.addEventListener("beforeunload", (event) => {
    if (!recordIsDirty || recordIsSubmitting || recordDiscardConfirmed) return;
    event.preventDefault();
    event.returnValue = "";
});

document.addEventListener("keydown", (event) => {
    if (event.key === "Escape" && recordDiscardDialog && !recordDiscardDialog.hidden) {
        closeRecordDiscardDialog(true);
    }
    if (event.key === "Escape" && recordSaveDialog && !recordSaveDialog.hidden && !recordIsSubmitting) {
        closeRecordSaveDialog(true);
    }
    const activeDialog = recordDiscardDialog && !recordDiscardDialog.hidden
        ? recordDiscardDialog
        : (recordSaveDialog && !recordSaveDialog.hidden ? recordSaveDialog : null);
    if (event.key === "Tab" && activeDialog) {
        const controls = [...activeDialog.querySelectorAll("button:not(:disabled)")];
        if (!controls.length) return;
        const currentIndex = controls.indexOf(document.activeElement);
        const nextIndex = event.shiftKey
            ? (currentIndex <= 0 ? controls.length - 1 : currentIndex - 1)
            : (currentIndex === controls.length - 1 ? 0 : currentIndex + 1);
        event.preventDefault();
        controls[nextIndex].focus();
    }
});
// Final del modo de edición neutral del Expediente Digital

// Inicio del historial administrativo del Expediente Digital
const recordHistoryTrigger = digitalRecordMenuPanel?.querySelector("[data-record-history-trigger]");
const recordHistoryOverlay = document.querySelector("[data-record-history-overlay]");
if (recordHistoryOverlay && recordHistoryOverlay.parentElement !== document.body) document.body.append(recordHistoryOverlay);
const recordHistoryCleanupDialog = document.querySelector("[data-record-history-cleanup-dialog]");
if (recordHistoryCleanupDialog && recordHistoryCleanupDialog.parentElement !== document.body) document.body.append(recordHistoryCleanupDialog);
const recordHistoryDrawer = recordHistoryOverlay?.querySelector("[data-record-history-drawer]");
const recordHistoryBody = recordHistoryOverlay?.querySelector("[data-admin-history-drawer-body]");
const recordHistoryClose = recordHistoryOverlay?.querySelector("[data-record-history-close]");
const recordHistoryTitle = recordHistoryOverlay?.querySelector("#recordHistoryTitle");
const recordHistoryLoadingState = recordHistoryOverlay?.querySelector("[data-record-history-loading]");
const recordHistoryEmptyState = recordHistoryOverlay?.querySelector("[data-record-history-empty]");
const recordHistoryErrorState = recordHistoryOverlay?.querySelector("[data-record-history-error]");
const recordHistoryNotice = recordHistoryOverlay?.querySelector("[data-record-history-notice]");
const recordHistoryList = recordHistoryOverlay?.querySelector("[data-record-history-list]");
const recordHistoryFooter = recordHistoryOverlay?.querySelector("[data-record-history-footer]");
const recordHistoryMore = recordHistoryOverlay?.querySelector("[data-record-history-more]");
const recordHistoryMoreLabel = recordHistoryOverlay?.querySelector("[data-record-history-more-label]");
const recordHistoryCleanup = recordHistoryOverlay?.querySelector("[data-record-history-cleanup]");
const recordHistoryIncompleteCount = recordHistoryOverlay?.querySelector("[data-record-history-incomplete-count]");
const recordHistoryCleanupOpen = recordHistoryOverlay?.querySelector("[data-record-history-cleanup-open]");
const recordHistoryCleanupCount = recordHistoryCleanupDialog?.querySelector("[data-record-history-cleanup-count]");
const recordHistoryCleanupInput = recordHistoryCleanupDialog?.querySelector("[data-record-history-cleanup-input]");
const recordHistoryCleanupCancel = recordHistoryCleanupDialog?.querySelector("[data-record-history-cleanup-cancel]");
const recordHistoryCleanupConfirm = recordHistoryCleanupDialog?.querySelector("[data-record-history-cleanup-confirm]");
const recordHistoryCleanupConfirmLabel = recordHistoryCleanupDialog?.querySelector("[data-record-history-cleanup-confirm-label]");
const recordHistoryCleanupError = recordHistoryCleanupDialog?.querySelector("[data-record-history-cleanup-error]");
let recordHistoryOffset = 0;
let recordHistoryLoaded = false;
let recordHistoryLoading = false;
let recordHistoryHasMore = false;
let recordHistoryIncompleteTotal = 0;
let recordHistoryCleanupLoading = false;

function historyTextValue(value, field) {
    if (Array.isArray(value)) return value.length ? value.join(", ") : "Sin valor";
    if (value === null || value === undefined || String(value).trim() === "") return "Sin valor";
    if (field === "publication_date" && /^\d{4}-\d{2}-\d{2}$/.test(String(value))) {
        const [year, month, day] = String(value).split("-");
        return `${day}/${month}/${year}`;
    }
    return String(value);
}

function historyElement(tag, className, text = "") {
    const element = document.createElement(tag);
    if (className) element.className = className;
    element.textContent = text;
    return element;
}

function renderHistoryItem(item) {
    const article = historyElement("article", "ed-history-item");
    article.setAttribute("role", "listitem");
    if (item.legacy_without_details) article.classList.add("is-legacy");
    article.append(historyElement("p", "ed-history-actor", item.actor?.name || "Usuario no disponible"));
    const identity = [item.actor?.email, item.actor?.role].filter(Boolean).join(" · ");
    if (identity) article.append(historyElement("p", "ed-history-identity", identity));
    article.append(historyElement("p", "ed-history-action", item.action_label || "Se actualizó el material"));
    const time = historyElement("time", "ed-history-date", item.created_at_label || "");
    if (item.created_at) time.dateTime = item.created_at;
    article.append(time);

    if (item.action === "support_material.history_cleaned" && item.cleanup) {
        const deletedCount = Math.max(0, Number(item.cleanup.deleted_count || 0));
        const reasonLabels = {
            legacy_events_without_change_details: "Registros antiguos sin detalle de cambios",
        };
        const reason = reasonLabels[item.cleanup.reason] || item.cleanup.reason || "Motivo no especificado";
        const details = historyElement("div", "ed-history-changes");
        const block = historyElement("section", "ed-history-change");
        [["Registros eliminados", `${deletedCount} ${deletedCount === 1 ? "registro eliminado" : "registros eliminados"}`], ["Motivo", reason]].forEach(([label, value]) => {
            const wrapper = historyElement("div", "ed-history-value");
            wrapper.append(historyElement("strong", "", label));
            wrapper.append(historyElement("span", "", value));
            block.append(wrapper);
        });
        details.append(block);
        article.append(details);
    } else if (Array.isArray(item.changes) && item.changes.length) {
        const changes = historyElement("div", "ed-history-changes");
        item.changes.forEach((change) => {
            const block = historyElement("section", "ed-history-change");
            block.append(historyElement("h3", "", change.label || change.field || "Campo"));
            [["Anterior:", change.old], ["Nuevo:", change.new]].forEach(([label, value]) => {
                const wrapper = historyElement("div", "ed-history-value");
                wrapper.append(historyElement("strong", "", label));
                wrapper.append(historyElement("span", "", historyTextValue(value, change.field)));
                block.append(wrapper);
            });
            changes.append(block);
        });
        article.append(changes);
    } else if (item.legacy_without_details) {
        const legacy = historyElement("div", "ed-history-legacy");
        legacy.append(historyElement("strong", "", "Registro antiguo"));
        legacy.append(historyElement("span", "", "Este evento fue registrado antes de habilitar el detalle de cambios."));
        article.append(legacy);
    } else {
        article.append(historyElement("p", "ed-history-empty-detail", "No existen detalles adicionales para esta acción."));
    }
    return article;
}

function setHistoryState(state) {
    const states = {
        loading: recordHistoryLoadingState,
        empty: recordHistoryEmptyState,
        error: recordHistoryErrorState,
    };
    Object.values(states).forEach((element) => {
        if (element) element.hidden = true;
    });
    if (recordHistoryList) recordHistoryList.hidden = state !== "loaded";
    if (recordHistoryMore) recordHistoryMore.hidden = !recordHistoryHasMore;
    if (recordHistoryCleanup) recordHistoryCleanup.hidden = recordHistoryIncompleteTotal < 1;
    if (recordHistoryFooter) recordHistoryFooter.hidden = state !== "loaded" || (!recordHistoryHasMore && recordHistoryIncompleteTotal < 1);
    if (states[state]) states[state].hidden = false;
}

async function loadRecordHistory(reset = false) {
    if (!recordHistoryOverlay || recordHistoryLoading) return;
    const initialLoad = reset || recordHistoryOffset === 0;
    if (reset) {
        recordHistoryOffset = 0;
        recordHistoryHasMore = false;
        recordHistoryList?.replaceChildren();
    }
    recordHistoryLoading = true;
    if (initialLoad) {
        setHistoryState("loading");
    } else {
        if (recordHistoryMore) recordHistoryMore.disabled = true;
        if (recordHistoryMoreLabel) recordHistoryMoreLabel.textContent = "Cargando más…";
    }
    try {
        const endpoint = new URL(recordHistoryOverlay.dataset.endpoint, window.location.href);
        endpoint.searchParams.set("offset", String(recordHistoryOffset));
        const response = await fetch(endpoint, { headers: { Accept: "application/json" } });
        const result = await response.json();
        if (!response.ok || !result.success) throw new Error(result.message);
        const items = Array.isArray(result.data?.items) ? result.data.items : [];
        if (initialLoad) recordHistoryList?.replaceChildren();
        const nextOffset = Number(result.data?.next_offset || recordHistoryOffset + items.length);
        recordHistoryHasMore = Boolean(result.data?.has_more);
        recordHistoryIncompleteTotal = Math.max(0, Number(result.data?.incomplete_count || 0));
        if (recordHistoryIncompleteCount) {
            recordHistoryIncompleteCount.textContent = `${recordHistoryIncompleteTotal} ${recordHistoryIncompleteTotal === 1 ? "registro antiguo sin detalle" : "registros antiguos sin detalle"}`;
        }
        setHistoryState(nextOffset > 0 ? "loaded" : "empty");
        items.forEach((item) => recordHistoryList?.append(renderHistoryItem(item)));
        recordHistoryOffset = nextOffset;
        recordHistoryLoaded = true;
    } catch {
        if (initialLoad) {
            recordHistoryLoaded = false;
            setHistoryState("error");
        } else {
            setHistoryState("loaded");
            if (recordHistoryMoreLabel) recordHistoryMoreLabel.textContent = "No fue posible cargar más. Reintentar";
        }
    } finally {
        recordHistoryLoading = false;
        if (recordHistoryMore) recordHistoryMore.disabled = false;
        if (recordHistoryMoreLabel && recordHistoryMoreLabel.textContent === "Cargando más…") {
            recordHistoryMoreLabel.textContent = "Ver más";
        }
    }
}

function openRecordHistory() {
    if (!recordHistoryOverlay || !recordHistoryTrigger) return;
    closeDigitalRecordMenu();
    lockRecordBackground(recordHistoryOverlay, recordHistoryTrigger);
    document.documentElement.classList.add("history-drawer-open");
    document.body.classList.add("history-drawer-open");
    recordHistoryOverlay.hidden = false;
    recordHistoryTitle?.focus();
    if (!recordHistoryLoaded && !recordHistoryLoading) loadRecordHistory(true);
}

function closeRecordHistory() {
    if (!recordHistoryOverlay || recordHistoryOverlay.hidden) return;
    if (recordHistoryCleanupDialog && !recordHistoryCleanupDialog.hidden) closeHistoryCleanupDialog();
    recordHistoryOverlay.hidden = true;
    document.documentElement.classList.remove("history-drawer-open");
    document.body.classList.remove("history-drawer-open");
    unlockRecordBackground(true);
}

function openHistoryCleanupDialog() {
    if (!recordHistoryCleanupDialog || recordHistoryIncompleteTotal < 1 || recordHistoryCleanupLoading) return;
    recordHistoryDrawer.inert = true;
    recordHistoryCleanupDialog.inert = false;
    recordHistoryCleanupDialog.removeAttribute("aria-hidden");
    recordHistoryCleanupDialog.hidden = false;
    if (recordHistoryCleanupCount) recordHistoryCleanupCount.textContent = `Se eliminarán ${recordHistoryIncompleteTotal} ${recordHistoryIncompleteTotal === 1 ? "registro" : "registros"}.`;
    if (recordHistoryCleanupInput) recordHistoryCleanupInput.value = "";
    if (recordHistoryCleanupConfirm) recordHistoryCleanupConfirm.disabled = true;
    if (recordHistoryCleanupError) {
        recordHistoryCleanupError.hidden = true;
        recordHistoryCleanupError.textContent = "";
    }
    recordHistoryCleanupInput?.focus();
}

function closeHistoryCleanupDialog() {
    if (!recordHistoryCleanupDialog || recordHistoryCleanupDialog.hidden || recordHistoryCleanupLoading) return;
    recordHistoryCleanupDialog.hidden = true;
    recordHistoryCleanupDialog.inert = true;
    recordHistoryCleanupDialog.setAttribute("aria-hidden", "true");
    recordHistoryDrawer.inert = false;
    recordHistoryCleanupOpen?.focus();
}

async function cleanupIncompleteHistory() {
    if (!recordHistoryOverlay || !recordHistoryCleanupDialog || recordHistoryCleanupLoading
        || recordHistoryCleanupInput?.value !== "ELIMINAR") return;
    recordHistoryCleanupLoading = true;
    if (recordHistoryCleanupCancel) recordHistoryCleanupCancel.disabled = true;
    if (recordHistoryCleanupConfirm) recordHistoryCleanupConfirm.disabled = true;
    if (recordHistoryCleanupInput) recordHistoryCleanupInput.disabled = true;
    if (recordHistoryCleanupConfirmLabel) recordHistoryCleanupConfirmLabel.textContent = "Eliminando…";
    if (recordHistoryCleanupError) recordHistoryCleanupError.hidden = true;
    try {
        const body = new FormData();
        body.set("_csrf", recordHistoryOverlay.dataset.csrf || "");
        body.set("id", recordHistoryOverlay.dataset.materialId || "");
        body.set("confirmation", recordHistoryCleanupInput.value);
        const response = await fetch(recordHistoryOverlay.dataset.cleanupEndpoint, { method: "POST", body });
        const result = await response.json();
        if (!response.ok || !result.success) throw new Error(result.message || "No fue posible eliminar los registros antiguos.");
        const successMessage = result.message || "Los registros antiguos sin detalle fueron eliminados.";
        recordHistoryIncompleteTotal = 0;
        recordHistoryCleanupLoading = false;
        closeHistoryCleanupDialog();
        recordHistoryLoaded = false;
        await loadRecordHistory(true);
        if (recordHistoryNotice) {
            recordHistoryNotice.textContent = successMessage;
            recordHistoryNotice.hidden = false;
        }
    } catch (error) {
        if (recordHistoryCleanupError) {
            recordHistoryCleanupError.textContent = error instanceof Error ? error.message : "No fue posible eliminar los registros antiguos.";
            recordHistoryCleanupError.hidden = false;
        }
    } finally {
        recordHistoryCleanupLoading = false;
        if (recordHistoryCleanupCancel) recordHistoryCleanupCancel.disabled = false;
        if (recordHistoryCleanupInput) recordHistoryCleanupInput.disabled = false;
        if (recordHistoryCleanupConfirm) recordHistoryCleanupConfirm.disabled = recordHistoryCleanupInput?.value !== "ELIMINAR";
        if (recordHistoryCleanupConfirmLabel) recordHistoryCleanupConfirmLabel.textContent = "Eliminar registros incompletos";
    }
}

recordHistoryTrigger?.addEventListener("click", openRecordHistory);
recordHistoryClose?.addEventListener("click", closeRecordHistory);
recordHistoryMore?.addEventListener("click", () => loadRecordHistory(false));
recordHistoryCleanupOpen?.addEventListener("click", openHistoryCleanupDialog);
recordHistoryCleanupCancel?.addEventListener("click", closeHistoryCleanupDialog);
recordHistoryCleanupInput?.addEventListener("input", () => {
    if (recordHistoryCleanupConfirm) recordHistoryCleanupConfirm.disabled = recordHistoryCleanupInput.value !== "ELIMINAR" || recordHistoryCleanupLoading;
});
recordHistoryCleanupConfirm?.addEventListener("click", cleanupIncompleteHistory);
recordHistoryCleanupDialog?.addEventListener("click", (event) => {
    if (event.target === recordHistoryCleanupDialog) closeHistoryCleanupDialog();
});
recordHistoryOverlay?.addEventListener("click", (event) => {
    if (event.target === recordHistoryOverlay && (recordHistoryCleanupDialog?.hidden ?? true)) closeRecordHistory();
});
document.addEventListener("keydown", (event) => {
    if (!recordHistoryOverlay || recordHistoryOverlay.hidden) return;
    if (recordHistoryCleanupDialog && !recordHistoryCleanupDialog.hidden) {
        if (event.key === "Escape") {
            event.preventDefault();
            closeHistoryCleanupDialog();
            return;
        }
        if (event.key === "Tab") {
            const cleanupControls = [recordHistoryCleanupInput, recordHistoryCleanupCancel, recordHistoryCleanupConfirm].filter(
                (control) => control && !control.disabled
            );
            const currentIndex = cleanupControls.indexOf(document.activeElement);
            const nextIndex = event.shiftKey
                ? (currentIndex <= 0 ? cleanupControls.length - 1 : currentIndex - 1)
                : (currentIndex === cleanupControls.length - 1 ? 0 : currentIndex + 1);
            event.preventDefault();
            cleanupControls[nextIndex]?.focus();
        }
        return;
    }
    if (event.key === "Escape") {
        event.preventDefault();
        closeRecordHistory();
        return;
    }
    if (event.key !== "Tab") return;
    const controls = [recordHistoryClose, recordHistoryBody, ...recordHistoryDrawer.querySelectorAll("button:not([disabled]):not([hidden])")].filter(
        (control, index, items) => control && items.indexOf(control) === index
    );
    if (!controls.length) return;
    const currentIndex = controls.indexOf(document.activeElement);
    const nextIndex = event.shiftKey
        ? (currentIndex <= 0 ? controls.length - 1 : currentIndex - 1)
        : (currentIndex === controls.length - 1 ? 0 : currentIndex + 1);
    event.preventDefault();
    controls[nextIndex].focus();
});
// Final del historial administrativo del Expediente Digital
