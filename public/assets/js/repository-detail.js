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
}, 600);

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
            const link = document.createElement("a");
            const downloadUrl = repositoryDetailContent?.dataset.fileDownloadUrl ?? "";
            const projectId = repositoryDetailContent?.dataset.projectId ?? "";
            link.href = `${downloadUrl}&id=${encodeURIComponent(projectId)}&path=${encodeURIComponent(item.path)}`;
            link.setAttribute("aria-label", `Descargar ${item.name}`);
            const downloadIcon = document.createElement("i");
            downloadIcon.className = "fa-solid fa-download";
            downloadIcon.setAttribute("aria-hidden", "true");
            link.append(downloadIcon);
            actionCell.append(link);
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
        if (repositoryPreviewTitle) repositoryPreviewTitle.textContent = preview.name;
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
