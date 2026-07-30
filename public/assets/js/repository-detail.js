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

const recordPersistentTabs = digitalRecord?.dataset.persistentTabs === "true";
const recordTabLinks = [...(digitalRecord?.querySelectorAll("[data-record-tab-link]") ?? [])];
const recordTabPanels = [...(digitalRecord?.querySelectorAll("[data-record-tab-panel]") ?? [])];
let recordTabActivationSequence = 0;
let documentEvolutionNeedsRefresh = false;

function activatePersistentRecordTab(tabId, updateHistory = true) {
    if (!recordPersistentTabs || !["information", "files", "evolution"].includes(tabId)) return;
    const targetPanel = recordTabPanels.find((panel) => panel.dataset.recordTabPanel === tabId);
    const targetLink = recordTabLinks.find((link) => link.dataset.tabId === tabId);
    if (!targetPanel || !targetLink) return;
    recordTabActivationSequence += 1;
    recordTabPanels.forEach((panel) => {
        panel.hidden = panel !== targetPanel;
    });
    recordTabLinks.forEach((link) => {
        const active = link === targetLink;
        link.setAttribute("aria-selected", String(active));
        if (active) link.setAttribute("aria-current", "page");
        else link.removeAttribute("aria-current");
    });
    digitalRecord.dataset.activeTab = tabId;
    if (updateHistory) window.history.pushState({ recordTab: tabId }, "", targetLink.href);
    digitalRecord.dispatchEvent(new CustomEvent("digitalrecord:tabchange", {
        detail: { tab: tabId, sequence: recordTabActivationSequence },
    }));
}

if (recordPersistentTabs) {
    recordTabLinks.forEach((link, index) => {
        link.addEventListener("click", (event) => {
            if (event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
            if (link.dataset.tabId === "evolution" && documentEvolutionNeedsRefresh) return;
            event.preventDefault();
            activatePersistentRecordTab(link.dataset.tabId || "information");
        });
        link.addEventListener("keydown", (event) => {
            if (!["ArrowLeft", "ArrowRight", "Home", "End"].includes(event.key)) return;
            event.preventDefault();
            let nextIndex = index;
            if (event.key === "ArrowLeft") nextIndex = index === 0 ? recordTabLinks.length - 1 : index - 1;
            if (event.key === "ArrowRight") nextIndex = index === recordTabLinks.length - 1 ? 0 : index + 1;
            if (event.key === "Home") nextIndex = 0;
            if (event.key === "End") nextIndex = recordTabLinks.length - 1;
            recordTabLinks[nextIndex]?.focus();
            activatePersistentRecordTab(recordTabLinks[nextIndex]?.dataset.tabId || "information");
        });
    });
    window.addEventListener("popstate", () => {
        const tab = new URL(window.location.href).searchParams.get("tab") || "information";
        activatePersistentRecordTab(tab, false);
    });
}

const neutralFileList = digitalRecord?.querySelector("[data-record-files]");
const neutralFilesPanel = neutralFileList?.closest('[data-record-tab-panel="files"]');
let neutralFileButtons = [...(neutralFileList?.querySelectorAll("[data-record-file]") ?? [])];
const neutralViewer = neutralFileList?.querySelector("[data-record-viewer]");
let neutralPackageDownload = neutralFileList?.querySelector("[data-record-package-download]");
const neutralViewerName = neutralViewer?.querySelector("[data-viewer-name]");
const neutralViewerMeta = neutralViewer?.querySelector("[data-viewer-meta]");
const neutralViewerBody = neutralViewer?.querySelector("[data-viewer-body]");
const neutralBackToFiles = neutralViewer?.querySelector("[data-back-to-files]");
const neutralViewerDownload = neutralViewer?.querySelector("[data-viewer-download]");
const neutralViewerDownloadLabel = neutralViewerDownload?.querySelector("[data-viewer-download-label]");
const neutralViewerState = neutralViewer?.querySelector("[data-viewer-state]");
const neutralViewerDocxNote = neutralViewer?.querySelector("[data-viewer-docx-note]");
const neutralViewerDocxTopScroll = neutralViewer?.querySelector("[data-viewer-docx-top-scroll]");
const neutralViewerDocxTopScrollTrack = neutralViewerDocxTopScroll?.querySelector("[data-viewer-docx-top-scroll-track]");
const neutralViewerPdf = neutralViewer?.querySelector("[data-viewer-pdf]");
const neutralViewerImageShell = neutralViewer?.querySelector("[data-viewer-image-shell]");
const neutralViewerImage = neutralViewer?.querySelector("[data-viewer-image]");
const neutralViewerText = neutralViewer?.querySelector("[data-viewer-text]");
const neutralViewerDocx = neutralViewer?.querySelector("[data-viewer-docx]");
const neutralViewerExpand = neutralViewer?.querySelector("[data-viewer-expand]");
const neutralZoomControls = [...document.querySelectorAll("[data-viewer-zoom-controls]")];
const neutralZoomOutButtons = neutralZoomControls.map((control) => control.querySelector("[data-viewer-zoom-out]")).filter(Boolean);
const neutralZoomResetButtons = neutralZoomControls.map((control) => control.querySelector("[data-viewer-zoom-reset]")).filter(Boolean);
const neutralZoomInButtons = neutralZoomControls.map((control) => control.querySelector("[data-viewer-zoom-in]")).filter(Boolean);
const neutralZoomValues = neutralZoomControls.map((control) => control.querySelector("[data-viewer-zoom-value]")).filter(Boolean);
const neutralZoomStatuses = neutralZoomControls.map((control) => control.querySelector("[data-viewer-zoom-status]")).filter(Boolean);
const neutralViewerOverlay = document.querySelector("[data-record-viewer-overlay]");
if (neutralViewerOverlay && neutralViewerOverlay.parentElement !== document.body) document.body.append(neutralViewerOverlay);
const neutralExpandedViewer = neutralViewerOverlay?.querySelector("[data-record-viewer-expanded]");
const neutralExpandedBody = neutralViewerOverlay?.querySelector("[data-expanded-viewer-body]");
const neutralExpandedClose = neutralViewerOverlay?.querySelector("[data-expanded-viewer-close]");
const neutralExpandedName = neutralViewerOverlay?.querySelector("[data-expanded-viewer-name]");
const neutralExpandedMeta = neutralViewerOverlay?.querySelector("[data-expanded-viewer-meta]");
const neutralExpandedDownload = neutralViewerOverlay?.querySelector("[data-expanded-viewer-download]");
const neutralExpandedDownloadLabel = neutralViewerOverlay?.querySelector("[data-expanded-download-label]");
let neutralPreviewRequest = null;
let neutralPreviewSequence = 0;
let neutralSelectedFileId = "";
let neutralResourceTimer = null;
let neutralViewerPlaceholder = null;
let neutralViewerReturnFocus = null;
let neutralDocxLibrariesPromise = null;
let neutralViewerBackgroundState = [];
let neutralViewerBodyOverflow = "";
let neutralViewerHtmlOverflow = "";
let neutralFilesInitialized = false;
let neutralViewerLayoutSequence = 0;
let neutralPdfLayoutWidth = 0;
let neutralDocxScrollSyncFrame = 0;
let neutralDocxFitFrame = 0;
let neutralDocxHorizontalRatio = 0;
const neutralZipTreeStates = new Map();
const neutralZoomByFile = new Map();
const neutralDocxBaseScaleByContext = new Map();
let neutralZoomPercent = 100;
const neutralZoomMinimum = 75;
const neutralDocxZoomMinimum = 35;
const neutralZoomMaximum = 200;
const neutralZoomStep = 25;

function neutralMinimumZoom() {
    return neutralViewerDocx?.classList.contains("is-docx-preview")
        ? neutralDocxZoomMinimum
        : neutralZoomMinimum;
}

function neutralZoomSupported() {
    return ["image", "text", "code", "docx"].includes(neutralViewer?.dataset.previewType || "")
        && neutralViewer?.dataset.viewerState === "ready";
}

function setNeutralZoomControlsVisibility(isVisible) {
    neutralZoomControls.forEach((control) => {
        control.hidden = !isVisible;
    });
}

function updateNeutralZoomControls(announce = false) {
    const supported = neutralZoomSupported();
    setNeutralZoomControlsVisibility(supported);
    neutralZoomValues.forEach((value) => { value.textContent = `${neutralZoomPercent} %`; });
    neutralZoomOutButtons.forEach((button) => { button.disabled = !supported || neutralZoomPercent <= neutralMinimumZoom(); });
    const resetsDocxFit = neutralViewerDocx?.classList.contains("is-docx-preview");
    neutralZoomResetButtons.forEach((button) => {
        button.disabled = !supported;
        button.setAttribute("aria-label", resetsDocxFit ? "Restablecer zoom DOCX al 100 %" : "Restablecer zoom");
        button.title = "Volver al 100 %";
    });
    neutralZoomInButtons.forEach((button) => { button.disabled = !supported || neutralZoomPercent >= neutralZoomMaximum; });
    neutralZoomControls.forEach((control) => {
        control.title = supported ? `Zoom del documento: ${neutralZoomPercent} %`
            : (neutralViewer?.dataset.previewType === "pdf" ? "Usa los controles del visor PDF para acercar o alejar." : "El zoom no aplica a este contenido.");
    });
    if (announce && supported) neutralZoomStatuses.forEach((status) => { status.textContent = `Zoom ${neutralZoomPercent} por ciento`; });
}

function neutralDocxContextKey(fileId = neutralSelectedFileId) {
    const mode = neutralViewerOverlay && !neutralViewerOverlay.hidden ? "expanded" : "lateral";
    return `${fileId}:${mode}`;
}

function syncNeutralDocxHorizontalScroll(source, target) {
    const sourceMaximum = Math.max(0, source.scrollWidth - source.clientWidth);
    const targetMaximum = Math.max(0, target.scrollWidth - target.clientWidth);
    const nextLeft = sourceMaximum > 0 ? (source.scrollLeft / sourceMaximum) * targetMaximum : 0;
    if (Math.abs(target.scrollLeft - nextLeft) < 1) return;
    target.scrollLeft = nextLeft;
}

function updateNeutralDocxTopScroll() {
    if (!neutralViewerDocxTopScroll || !neutralViewerDocxTopScrollTrack || !neutralViewerDocx) return;
    const isDocxReady = neutralViewer?.dataset.previewType === "docx"
        && neutralViewer?.dataset.viewerState === "ready"
        && neutralViewerDocx.classList.contains("is-docx-preview")
        && !neutralViewerDocx.hidden;
    const hasHorizontalOverflow = isDocxReady
        && neutralViewerDocx.scrollWidth > neutralViewerDocx.clientWidth + 1;
    neutralViewerDocxTopScroll.hidden = !hasHorizontalOverflow;
    if (!hasHorizontalOverflow) {
        neutralViewerDocxTopScrollTrack.style.width = "0";
        neutralViewerDocxTopScroll.scrollLeft = 0;
        return;
    }
    neutralViewerDocxTopScrollTrack.style.width = `${neutralViewerDocx.scrollWidth}px`;
    syncNeutralDocxHorizontalScroll(neutralViewerDocx, neutralViewerDocxTopScroll);
}

function scheduleNeutralDocxTopScrollUpdate() {
    if (neutralDocxScrollSyncFrame) window.cancelAnimationFrame(neutralDocxScrollSyncFrame);
    neutralDocxScrollSyncFrame = window.requestAnimationFrame(() => {
        neutralDocxScrollSyncFrame = 0;
        updateNeutralDocxTopScroll();
    });
}

function applyNeutralZoom(announce = false) {
    const userZoom = neutralZoomPercent / 100;
    if (neutralViewerImage) {
        neutralViewerImage.style.width = `${neutralZoomPercent}%`;
        neutralViewerImage.style.maxWidth = "none";
    }
    if (neutralViewerText) neutralViewerText.style.fontSize = `${13 * userZoom}px`;
    if (neutralViewerDocx) {
        if (neutralViewerDocx.classList.contains("is-docx-preview")) {
            const docxWrapper = neutralViewerDocx.querySelector(".ed-docx-render-wrapper");
            const docxStage = neutralViewerDocx.querySelector(".ed-docx-scale-stage");
            const baseScale = neutralDocxBaseScaleByContext.get(neutralDocxContextKey()) ?? 1;
            const effectiveScale = baseScale * userZoom;
            if (docxWrapper instanceof HTMLElement) {
                const isCompactViewport = window.matchMedia("(max-width: 520px)").matches;
                const isExpandedViewer = Boolean(neutralViewerOverlay && !neutralViewerOverlay.hidden);
                const visiblePageGap = isCompactViewport ? 16 : (isExpandedViewer ? 28 : 24);
                const visibleTailSpace = isCompactViewport ? 10 : (isExpandedViewer ? 16 : 12);
                const safeScale = Math.max(0.01, effectiveScale);
                docxWrapper.style.setProperty("--ed-docx-page-gap", `${visiblePageGap / safeScale}px`);
                docxWrapper.style.setProperty("--ed-docx-page-tail-space", `${visibleTailSpace / safeScale}px`);
                docxWrapper.style.setProperty("--ed-docx-page-shadow-y", `${2 / safeScale}px`);
                docxWrapper.style.setProperty("--ed-docx-page-shadow-blur", `${10 / safeScale}px`);
                docxWrapper.style.zoom = String(effectiveScale);
            }
            if (docxStage instanceof HTMLElement) {
                const naturalWidth = Number(neutralViewerDocx.dataset.docxNaturalWidth || 0);
                docxStage.classList.toggle("is-overflowing", naturalWidth * effectiveScale > docxStage.clientWidth);
            }
            neutralViewerDocx.style.removeProperty("zoom");
            neutralViewerDocx.style.removeProperty("font-size");
        } else {
            neutralViewerDocx.style.fontSize = `${13 * userZoom}px`;
            neutralViewerDocx.style.removeProperty("zoom");
        }
    }
    if (neutralSelectedFileId) neutralZoomByFile.set(neutralSelectedFileId, neutralZoomPercent);
    updateNeutralZoomControls(announce);
    scheduleNeutralDocxTopScrollUpdate();
}

function setNeutralZoom(nextPercent) {
    neutralZoomPercent = Math.max(neutralMinimumZoom(), Math.min(neutralZoomMaximum, nextPercent));
    applyNeutralZoom(true);
}

function fitNeutralDocxForCurrentContext() {
    if (!neutralViewerDocx?.classList.contains("is-docx-preview") || neutralViewerDocx.hidden || !neutralSelectedFileId) return;
    const wrapper = neutralViewerDocx.querySelector(".ed-docx-render-wrapper");
    const stage = neutralViewerDocx.querySelector(".ed-docx-scale-stage");
    if (!(wrapper instanceof HTMLElement) || !(stage instanceof HTMLElement)) return;
    const contextKey = neutralDocxContextKey();
    const naturalWidth = Number(neutralViewerDocx.dataset.docxNaturalWidth || 0);
    if (naturalWidth <= 0) return;
    const viewerStyle = window.getComputedStyle(neutralViewerDocx);
    const viewerPadding = parseFloat(viewerStyle.paddingLeft || "0") + parseFloat(viewerStyle.paddingRight || "0");
    const usefulWidth = Math.max(0, neutralViewerDocx.clientWidth - viewerPadding - 24);
    if (usefulWidth <= 0) return;
    const baseScale = Math.min(1, usefulWidth / naturalWidth);
    neutralDocxBaseScaleByContext.set(contextKey, baseScale);
    applyNeutralZoom();
    const horizontalMaximum = Math.max(0, neutralViewerDocx.scrollWidth - neutralViewerDocx.clientWidth);
    neutralViewerDocx.scrollLeft = horizontalMaximum * neutralDocxHorizontalRatio;
    console.debug("[DOCX viewer] Escala inicial calculada", {
        mode: contextKey.endsWith(":expanded") ? "expanded" : "lateral",
        naturalPageWidth: Number(neutralViewerDocx.dataset.docxNaturalPageWidth || 0),
        naturalWidth,
        usefulWidth,
        baseScale,
        userZoomPercent: neutralZoomPercent,
        effectiveScale: baseScale * (neutralZoomPercent / 100),
        horizontalOverflow: neutralViewerDocx.scrollWidth > neutralViewerDocx.clientWidth,
    });
}

function scheduleNeutralDocxFit() {
    if (neutralDocxFitFrame) window.cancelAnimationFrame(neutralDocxFitFrame);
    neutralDocxFitFrame = window.requestAnimationFrame(() => {
        neutralDocxFitFrame = 0;
        if (
            neutralViewer?.dataset.viewerState !== "ready"
            || !neutralViewerDocx?.classList.contains("is-docx-preview")
            || neutralViewerDocx.hidden
            || neutralViewerDocx.clientWidth <= 0
        ) return;
        fitNeutralDocxForCurrentContext();
    });
}

function resetNeutralZoom() {
    if (neutralViewerDocx?.classList.contains("is-docx-preview") && neutralSelectedFileId) {
        neutralZoomPercent = 100;
        applyNeutralZoom(true);
        return;
    }
    setNeutralZoom(100);
}

function prepareNeutralDocxScale() {
    if (!neutralViewerDocx?.classList.contains("is-docx-preview") || neutralViewerDocx.hidden) return;
    const wrapper = neutralViewerDocx.querySelector(".ed-docx-render-wrapper");
    const pages = [...neutralViewerDocx.querySelectorAll("section.ed-docx-render")];
    if (!(wrapper instanceof HTMLElement) || pages.length === 0) return;
    wrapper.style.removeProperty("zoom");
    const wrapperStyle = window.getComputedStyle(wrapper);
    const wrapperPadding = parseFloat(wrapperStyle.paddingLeft || "0") + parseFloat(wrapperStyle.paddingRight || "0");
    const naturalPageWidth = Math.max(...pages.map((page) => page instanceof HTMLElement ? page.offsetWidth : 0));
    const naturalWidth = naturalPageWidth + wrapperPadding;
    if (naturalWidth <= 0) return;
    wrapper.style.boxSizing = "border-box";
    wrapper.style.width = `${naturalWidth}px`;
    neutralViewerDocx.dataset.docxNaturalWidth = String(naturalWidth);
    neutralViewerDocx.dataset.docxNaturalPageWidth = String(naturalPageWidth);
    fitNeutralDocxForCurrentContext();
    scheduleNeutralDocxTopScrollUpdate();
}

function buildPdfPreviewUrl(url) {
    const source = String(url || "").trim();
    if (!source) return "";
    return `${source.split("#", 1)[0]}#page=1&view=FitH`;
}

function resetNeutralViewer() {
    if (neutralDocxFitFrame) {
        window.cancelAnimationFrame(neutralDocxFitFrame);
        neutralDocxFitFrame = 0;
    }
    if (neutralResourceTimer !== null) {
        window.clearTimeout(neutralResourceTimer);
        neutralResourceTimer = null;
    }
    if (neutralViewerPdf) {
        neutralPdfLayoutWidth = 0;
        neutralViewerPdf.hidden = true;
        neutralViewerPdf.classList.remove("is-preparing");
        neutralViewerPdf.style.removeProperty("width");
        neutralViewerPdf.removeAttribute("src");
    }
    if (neutralViewerImageShell) neutralViewerImageShell.hidden = true;
    if (neutralViewerImage) {
        neutralViewerImage.removeAttribute("src");
        neutralViewerImage.alt = "";
        neutralViewerImage.style.removeProperty("width");
        neutralViewerImage.style.removeProperty("max-width");
    }
    if (neutralViewerText) {
        neutralViewerText.hidden = true;
        neutralViewerText.textContent = "";
        neutralViewerText.style.removeProperty("font-size");
    }
    if (neutralViewerDocx) {
        const docxWrapper = neutralViewerDocx.querySelector(".ed-docx-render-wrapper");
        if (docxWrapper instanceof HTMLElement) {
            docxWrapper.style.removeProperty("zoom");
        }
        neutralViewerDocx.hidden = true;
        neutralViewerDocx.replaceChildren();
        neutralViewerDocx.classList.remove("is-fallback", "is-docx-preview");
        neutralViewerDocx.removeAttribute("data-docx-fallback-reason");
        neutralViewerDocx.removeAttribute("data-docx-natural-width");
        neutralViewerDocx.removeAttribute("data-docx-natural-page-width");
        neutralViewerDocx.style.removeProperty("font-size");
        neutralViewerDocx.style.removeProperty("zoom");
        neutralViewerDocx.scrollLeft = 0;
        neutralViewerDocx.scrollTop = 0;
    }
}

function setNeutralViewerState(state, message = "", retryButton = null) {
    if (!neutralViewerState || !neutralViewer) return;
    if (state !== "loading" && neutralResourceTimer !== null) {
        window.clearTimeout(neutralResourceTimer);
        neutralResourceTimer = null;
    }
    const settings = {
        loading: ["fa-spinner fa-spin", "Preparando vista previa", message || "Estamos cargando el archivo seleccionado."],
        unsupported: ["fa-eye-slash", "Vista previa no disponible para este formato.", message || "Descarga el archivo para consultar su contenido."],
        error: ["fa-triangle-exclamation", "No fue posible cargar la vista previa", message || "Intenta nuevamente o descarga el archivo."],
        missing: ["fa-file-circle-xmark", "El archivo no está disponible", message || "Selecciona otro documento para continuar."],
        empty: ["fa-folder-open", "Selecciona un archivo para visualizarlo", message || "Elige un documento del explorador para abrir su vista previa."],
    };
    const [iconClass, title, description] = settings[state] || settings.error;
    const wrapper = document.createElement("div");
    const icon = document.createElement("i");
    const heading = document.createElement("h3");
    const copy = document.createElement("p");
    icon.className = `fa-solid ${iconClass}`;
    icon.setAttribute("aria-hidden", "true");
    heading.textContent = title;
    copy.textContent = description;
    wrapper.append(icon, heading, copy);
    if (["unsupported", "error", "missing"].includes(state)) {
        const selected = neutralFileButtons.find((button) => button.getAttribute("aria-selected") === "true")
            || document.querySelector('[data-zip-entry-file][aria-selected="true"]');
        if (selected) {
            const name = document.createElement("strong");
            const meta = document.createElement("span");
            name.className = "ed-viewer-state-name";
            name.textContent = selected.dataset.fileName || "Archivo";
            name.title = selected.dataset.fileName || "Archivo";
            meta.className = "ed-viewer-state-meta";
            meta.textContent = [selected.dataset.fileType, selected.dataset.fileSize].filter(Boolean).join(" · ");
            wrapper.insertBefore(name, copy);
            wrapper.insertBefore(meta, copy);
            if (state === "unsupported" && selected.dataset.filePackage === "true") {
                const tag = document.createElement("span");
                tag.className = "ed-viewer-state-tag";
                tag.textContent = "Paquete del material";
                wrapper.insertBefore(tag, copy);
            }
            if (state === "unsupported") {
                const download = document.createElement("a");
                const downloadIcon = document.createElement("i");
                const downloadText = document.createElement("span");
                download.className = "ed-viewer-download";
                download.dataset.recordDownload = "";
                download.setAttribute("download", "");
                download.href = selected.dataset.downloadUrl || "";
                download.setAttribute("aria-label", `Descargar ${selected.dataset.fileName || "archivo"}`);
                downloadIcon.className = "fa-solid fa-download";
                downloadIcon.setAttribute("aria-hidden", "true");
                downloadText.textContent = downloadLabelFor(selected.dataset.fileExtension || "");
                download.append(downloadIcon, downloadText);
                wrapper.append(download);
            }
        }
    }
    if (retryButton) wrapper.append(retryButton);
    neutralViewerState.replaceChildren(wrapper);
    neutralViewerState.hidden = false;
    neutralViewer.dataset.viewerState = state;
    neutralViewer.setAttribute("aria-busy", String(state === "loading"));
    updateNeutralZoomControls();
}

function neutralZipState(button) {
    const fileId = button.dataset.fileId || "";
    if (!neutralZipTreeStates.has(fileId)) {
        neutralZipTreeStates.set(fileId, {
            openPaths: new Set(),
            loadedPaths: new Set(),
            loadingPaths: new Map(),
            selectedPath: "",
        });
    }
    return neutralZipTreeStates.get(fileId);
}

function neutralZipUrl(base, path) {
    if (!base) return "";
    return `${base}${base.includes("?") ? "&" : "?"}path=${encodeURIComponent(path)}`;
}

function neutralZipPreviewType(extension) {
    const normalized = String(extension || "").toLowerCase();
    if (normalized === "pdf") return "pdf";
    if (normalized === "docx") return "docx";
    if (normalized === "txt") return "text";
    if (["jpg", "jpeg", "png", "webp"].includes(normalized)) return "image";
    return "unsupported";
}

function setNeutralZipFolderState(folderButton, children, expanded) {
    folderButton.setAttribute("aria-expanded", String(expanded));
    children.hidden = !expanded;
    const chevron = folderButton.querySelector("[data-zip-chevron]");
    const icon = folderButton.querySelector("[data-zip-folder-icon]");
    if (chevron) chevron.className = `fa-solid ${expanded ? "fa-chevron-down" : "fa-chevron-right"}`;
    if (icon) icon.className = `fa-solid ${expanded ? "fa-folder-open" : "fa-folder"}`;
}

async function loadNeutralZipTreePath(zipButton, path, container, depth) {
    const state = neutralZipState(zipButton);
    if (state.loadedPaths.has(path)) return;
    if (state.loadingPaths.has(path)) return state.loadingPaths.get(path);
    const loading = document.createElement("p");
    loading.className = "ed-zip-tree-status";
    loading.setAttribute("role", "status");
    loading.textContent = "Cargando contenido…";
    container.replaceChildren(loading);
    const request = (async () => {
        try {
            const response = await fetch(neutralZipUrl(zipButton.dataset.zipUrl || "", path), {
                headers: { Accept: "application/json" },
                credentials: "same-origin",
            });
            const result = await response.json().catch(() => null);
            const archive = result?.data?.archive;
            if (!response.ok || !result?.success || !archive) throw new Error(result?.message || "No fue posible leer el paquete.");
            renderNeutralZipTreeItems(zipButton, container, Array.isArray(archive.items) ? archive.items : [], depth);
            state.loadedPaths.add(path);
        } catch (error) {
            const message = document.createElement("p");
            message.className = "ed-zip-tree-status is-error";
            message.setAttribute("role", "alert");
            message.textContent = error instanceof Error ? error.message : "No fue posible leer el paquete.";
            container.replaceChildren(message);
        } finally {
            state.loadingPaths.delete(path);
        }
    })();
    state.loadingPaths.set(path, request);
    return request;
}

function selectNeutralZipEntry(zipButton, entryButton) {
    neutralFileButtons.forEach((item) => {
        item.classList.remove("is-selected");
        item.setAttribute("aria-selected", "false");
    });
    document.querySelectorAll("[data-zip-entry-file].is-selected").forEach((item) => {
        item.classList.remove("is-selected");
        item.setAttribute("aria-selected", "false");
    });
    entryButton.classList.add("is-selected");
    entryButton.setAttribute("aria-selected", "true");
    neutralZipState(zipButton).selectedPath = entryButton.dataset.zipEntryPath || "";
    neutralSelectedFileId = entryButton.dataset.fileId || "";
    const previewType = entryButton.dataset.previewType || "unsupported";
    if (neutralViewer) neutralViewer.dataset.previewType = previewType;
    if (neutralViewerDocxNote) neutralViewerDocxNote.hidden = previewType !== "docx";
    neutralViewerBody?.classList.toggle("is-docx-content", previewType === "docx");
    neutralZoomPercent = previewType === "docx" ? 100 : (neutralZoomByFile.get(neutralSelectedFileId) ?? 100);
    if (neutralViewerName) neutralViewerName.textContent = entryButton.dataset.fileName || "Archivo";
    if (neutralViewerMeta) neutralViewerMeta.textContent = [entryButton.dataset.fileType, entryButton.dataset.fileSize, "Contenido de ZIP"].filter(Boolean).join(" · ");
    if (neutralViewerDownload) {
        neutralViewerDownload.href = entryButton.dataset.downloadUrl || "";
        neutralViewerDownload.setAttribute("download", entryButton.dataset.fileName || "archivo");
        neutralViewerDownload.setAttribute("aria-label", `Descargar ${entryButton.dataset.fileName || "archivo"}`);
    }
    if (neutralViewerDownloadLabel) neutralViewerDownloadLabel.textContent = downloadLabelFor(entryButton.dataset.fileExtension || "");
    loadNeutralPreview(entryButton);
}

function renderNeutralZipTreeItems(zipButton, container, entries, depth) {
    container.replaceChildren();
    if (!entries.length) {
        const empty = document.createElement("p");
        empty.className = "ed-zip-tree-status";
        empty.textContent = "Esta carpeta no contiene archivos.";
        container.append(empty);
        return;
    }
    const state = neutralZipState(zipButton);
    entries.forEach((entry) => {
        const path = String(entry.path || "");
        const row = document.createElement("div");
        row.className = "ed-zip-tree-node";
        row.setAttribute("role", "treeitem");
        row.style.setProperty("--zip-depth", String(depth));
        const button = document.createElement("button");
        button.type = "button";
        button.className = "ed-zip-tree-row";
        const chevron = document.createElement("i");
        const icon = document.createElement("i");
        const copy = document.createElement("span");
        const name = document.createElement("strong");
        const meta = document.createElement("small");
        name.textContent = String(entry.name || "Elemento sin nombre");
        name.title = name.textContent;
        meta.textContent = [entry.type, entry.size].filter(Boolean).join(" · ");
        copy.append(name, meta);
        if (entry.kind === "folder") {
            const children = document.createElement("div");
            children.className = "ed-zip-tree-children";
            children.setAttribute("role", "group");
            children.hidden = true;
            chevron.dataset.zipChevron = "";
            icon.dataset.zipFolderIcon = "";
            button.append(chevron, icon, copy);
            button.setAttribute("aria-expanded", "false");
            button.setAttribute("aria-label", `${name.textContent}, carpeta`);
            const toggle = async (forceOpen = null) => {
                const expanded = forceOpen ?? button.getAttribute("aria-expanded") !== "true";
                if (expanded && !state.loadedPaths.has(path)) await loadNeutralZipTreePath(zipButton, path, children, depth + 1);
                if (expanded) state.openPaths.add(path); else state.openPaths.delete(path);
                setNeutralZipFolderState(button, children, expanded);
            };
            button.addEventListener("click", () => toggle());
            button.addEventListener("keydown", (event) => {
                if (event.key === "ArrowRight") { event.preventDefault(); toggle(true); }
                if (event.key === "ArrowLeft") { event.preventDefault(); toggle(false); }
            });
            row.append(button, children);
            setNeutralZipFolderState(button, children, false);
            container.append(row);
            if (state.openPaths.has(path)) toggle(true);
            return;
        }
        chevron.className = "ed-zip-tree-spacer";
        icon.className = `fa-solid ${String(entry.icon || "fa-file")}`;
        icon.setAttribute("aria-hidden", "true");
        button.append(chevron, icon, copy);
        button.dataset.zipEntryFile = "";
        button.dataset.zipEntryPath = path;
        button.dataset.fileId = `${zipButton.dataset.fileId || "zip"}:${path}`;
        button.dataset.fileName = String(entry.name || "Archivo");
        button.dataset.fileType = String(entry.type || "Archivo");
        button.dataset.fileSize = String(entry.size || "");
        button.dataset.fileExtension = String(entry.extension || "");
        button.dataset.previewType = neutralZipPreviewType(entry.extension);
        button.dataset.previewSupported = String(button.dataset.previewType !== "unsupported");
        button.dataset.previewUrl = neutralZipUrl(zipButton.dataset.zipEntryPreviewUrl || "", path);
        button.dataset.downloadUrl = neutralZipUrl(zipButton.dataset.zipEntryDownloadUrl || "", path);
        button.setAttribute("aria-selected", String(state.selectedPath === path));
        button.setAttribute("aria-label", `${button.dataset.fileName}, ${button.dataset.fileType}, ${button.dataset.fileSize}`);
        if (state.selectedPath === path) button.classList.add("is-selected");
        button.addEventListener("click", () => selectNeutralZipEntry(zipButton, button));
        row.append(button);
        container.append(row);
    });
}

async function toggleNeutralZipTree(zipButton) {
    const item = zipButton.closest("[data-record-file-item]");
    const tree = item?.nextElementSibling?.matches("[data-zip-tree]") ? item.nextElementSibling : null;
    if (!(tree instanceof HTMLElement)) return;
    const state = neutralZipState(zipButton);
    const expanded = zipButton.getAttribute("aria-expanded") !== "true";
    zipButton.setAttribute("aria-expanded", String(expanded));
    tree.hidden = !expanded;
    const icon = zipButton.querySelector(":scope > i");
    icon?.classList.toggle("fa-box-open", expanded);
    icon?.classList.toggle("fa-box-archive", !expanded);
    if (expanded) {
        state.openPaths.add("");
        await loadNeutralZipTreePath(zipButton, "", tree, 0);
    } else {
        state.openPaths.delete("");
    }
}

function downloadLabelFor(extension) {
    const normalized = String(extension).toLowerCase();
    if (["jpg", "jpeg", "png", "webp"].includes(normalized)) return "Descargar imagen";
    const labels = {
        pdf: "Descargar PDF",
        docx: "Descargar DOCX",
        txt: "Descargar TXT",
        xlsx: "Descargar XLSX",
        pptx: "Descargar PPTX",
        zip: "Descargar ZIP",
    };
    return labels[normalized] || "Descargar archivo";
}

function startNeutralResourceTimeout(button, requestSequence) {
    neutralResourceTimer = window.setTimeout(() => {
        if (requestSequence !== neutralPreviewSequence || neutralSelectedFileId !== (button.dataset.fileId ?? "")) return;
        neutralPreviewRequest?.abort();
        neutralPreviewRequest = null;
        resetNeutralViewer();
        const retry = document.createElement("button");
        retry.type = "button";
        retry.className = "ed-viewer-retry";
        retry.textContent = "Reintentar";
        retry.addEventListener("click", () => loadNeutralPreview(button), { once: true });
        setNeutralViewerState("error", "La vista previa tardó demasiado en responder.", retry);
    }, 15000);
}

function showNeutralPreview(panel) {
    if (neutralResourceTimer !== null) {
        window.clearTimeout(neutralResourceTimer);
        neutralResourceTimer = null;
    }
    if (neutralViewerState) neutralViewerState.hidden = true;
    panel?.classList.remove("is-preparing");
    if (panel) panel.hidden = false;
    if (neutralViewer) {
        neutralViewer.dataset.viewerState = "ready";
        neutralViewer.setAttribute("aria-busy", "false");
    }
    applyNeutralZoom();
}

function refreshNeutralPdfLayout() {
    if (!neutralViewerPdf || !neutralViewerBody || neutralViewerPdf.hidden || neutralFilesPanel?.hidden) return;
    const width = Math.floor(neutralViewerBody.getBoundingClientRect().width);
    if (width < 1 || width === neutralPdfLayoutWidth) return;
    neutralPdfLayoutWidth = width;
    neutralViewerPdf.style.width = `${width}px`;
}

function refreshVisibleViewerLayout() {
    if (!neutralViewerPdf || !neutralViewerBody || neutralViewerPdf.hidden || neutralFilesPanel?.hidden) return;
    if (neutralViewer?.dataset.viewerState !== "ready" || neutralViewerPdf.classList.contains("is-preparing")) return;
    const sequence = ++neutralViewerLayoutSequence;
    neutralViewer?.classList.toggle("viewer-expanded", Boolean(neutralViewerOverlay && !neutralViewerOverlay.hidden));
    neutralViewer?.classList.toggle("viewer-normal", !neutralViewerOverlay || neutralViewerOverlay.hidden);
    neutralPdfLayoutWidth = 0;
    window.requestAnimationFrame(() => {
        if (sequence !== neutralViewerLayoutSequence || neutralFilesPanel?.hidden) return;
        refreshNeutralPdfLayout();
    });
}

const neutralViewerResizeObserver = typeof ResizeObserver === "function" && neutralViewerBody
    ? new ResizeObserver(() => window.requestAnimationFrame(refreshNeutralPdfLayout))
    : null;
neutralViewerResizeObserver?.observe(neutralViewerBody);

function renderNeutralDocx(blocks) {
    if (!neutralViewerDocx) return;
    neutralViewerDocx.replaceChildren();
    neutralViewerDocx.classList.remove("is-docx-preview");
    neutralViewerDocx.classList.add("is-fallback");
    blocks.forEach((block) => {
        if (block?.type === "table" && Array.isArray(block.rows)) {
            const shell = document.createElement("div");
            const table = document.createElement("table");
            shell.className = "ed-preview-table-shell";
            block.rows.forEach((row, rowIndex) => {
                const tableRow = document.createElement("tr");
                (Array.isArray(row) ? row : []).forEach((cell) => {
                    const element = document.createElement(rowIndex === 0 ? "th" : "td");
                    element.textContent = String(cell ?? "");
                    tableRow.append(element);
                });
                table.append(tableRow);
            });
            shell.append(table);
            neutralViewerDocx.append(shell);
            return;
        }
        let element;
        if (block?.type === "heading") {
            const level = Math.min(6, Math.max(2, Number(block.level) + 1 || 2));
            element = document.createElement(`h${level}`);
        } else {
            element = document.createElement("p");
            if (block?.type === "list") element.className = "is-list";
        }
        element.textContent = String(block?.text ?? "");
        neutralViewerDocx.append(element);
    });
}

function loadNeutralViewerScript(url, isReady) {
    if (isReady()) {
        console.debug("[DOCX viewer] Biblioteca disponible", { url });
        return Promise.resolve();
    }
    const resolvedUrl = new URL(url, document.baseURI).href;
    const existing = [...document.scripts].find((script) => script.src === resolvedUrl);
    existing?.remove();
    return new Promise((resolve, reject) => {
        const script = document.createElement("script");
        script.addEventListener("load", () => {
            if (isReady()) {
                console.debug("[DOCX viewer] Biblioteca cargada", { url: resolvedUrl });
                resolve();
            } else {
                reject(new Error("La biblioteca del visor no se inicializÃ³ correctamente."));
            }
        }, { once: true });
        script.addEventListener("error", () => reject(new Error("No fue posible cargar la biblioteca local del visor.")), { once: true });
        script.src = resolvedUrl;
        script.defer = true;
        document.head.append(script);
    });
}

function ensureNeutralDocxLibraries() {
    if (window.JSZip && typeof window.docx?.renderAsync === "function") {
        console.debug("[DOCX viewer] Globales disponibles", {
            jszip: typeof window.JSZip,
            docx: typeof window.docx,
            renderAsync: typeof window.docx.renderAsync,
        });
        return Promise.resolve();
    }
    if (neutralDocxLibrariesPromise) return neutralDocxLibrariesPromise;
    const jsZipUrl = neutralViewerDocx?.dataset.jszipScript || "";
    const docxPreviewUrl = neutralViewerDocx?.dataset.docxPreviewScript || "";
    if (!jsZipUrl || !docxPreviewUrl) return Promise.reject(new Error("El visor DOCX local no estÃ¡ configurado."));
    neutralDocxLibrariesPromise = loadNeutralViewerScript(jsZipUrl, () => Boolean(window.JSZip))
        .then(() => loadNeutralViewerScript(docxPreviewUrl, () => typeof window.docx?.renderAsync === "function"))
        .then(() => {
            console.debug("[DOCX viewer] Globales inicializados", {
                jszip: typeof window.JSZip,
                docx: typeof window.docx,
                renderAsync: typeof window.docx?.renderAsync,
            });
        })
        .catch((error) => {
            neutralDocxLibrariesPromise = null;
            throw error;
        });
    return neutralDocxLibrariesPromise;
}

async function renderNeutralDocxPreview(preview, requestSequence, fileId) {
    if (!neutralViewerDocx || !preview.content_url) throw new Error("El contenido DOCX no estÃ¡ disponible.");
    await ensureNeutralDocxLibraries();
    console.debug("[DOCX viewer] Descargando DOCX", {
        fileId,
        url: preview.content_url,
        requestSequence,
    });
    const contentResponse = await fetch(preview.content_url, {
        signal: neutralPreviewRequest?.signal,
        headers: { Accept: "application/vnd.openxmlformats-officedocument.wordprocessingml.document" },
        credentials: "same-origin",
    });
    console.debug("[DOCX viewer] Respuesta DOCX", {
        status: contentResponse.status,
        ok: contentResponse.ok,
        mime: contentResponse.headers.get("content-type"),
        contentLength: contentResponse.headers.get("content-length"),
    });
    if (!contentResponse.ok) {
        const error = new Error("No fue posible obtener el contenido del documento.");
        error.status = contentResponse.status;
        throw error;
    }
    const documentData = await contentResponse.arrayBuffer();
    if (documentData.byteLength === 0) throw new Error("El endpoint protegido devolviÃ³ un DOCX sin contenido.");
    console.debug("[DOCX viewer] DOCX descargado", { bytes: documentData.byteLength });
    const detachedBody = document.createElement("div");
    const detachedStyles = document.createElement("div");
    console.debug("[DOCX viewer] Iniciando renderAsync", { fileId, bytes: documentData.byteLength });
    await window.docx.renderAsync(documentData, detachedBody, detachedStyles, {
        className: "ed-docx-render",
        inWrapper: true,
        ignoreWidth: false,
        ignoreHeight: false,
        ignoreFonts: false,
        breakPages: true,
        ignoreLastRenderedPageBreak: false,
        renderHeaders: true,
        renderFooters: true,
        renderFootnotes: true,
        renderEndnotes: true,
        renderComments: false,
        renderChanges: false,
        useBase64URL: true,
    });
    console.debug("[DOCX viewer] renderAsync completado", {
        fileId,
        pages: detachedBody.querySelectorAll("section.ed-docx-render").length,
        images: detachedBody.querySelectorAll("img").length,
        styles: detachedStyles.querySelectorAll("style").length,
    });
    if (
        requestSequence !== neutralPreviewSequence
        || neutralSelectedFileId !== fileId
        || neutralViewer?.dataset.viewerState !== "loading"
    ) {
        console.warn("[DOCX viewer] Resultado descartado por secuencia o estado", {
            requestSequence,
            currentSequence: neutralPreviewSequence,
            fileId,
            selectedFileId: neutralSelectedFileId,
            viewerState: neutralViewer?.dataset.viewerState,
        });
        return false;
    }
    neutralViewerDocx.replaceChildren(...detachedStyles.childNodes, ...detachedBody.childNodes);
    neutralViewerDocx.classList.remove("is-fallback");
    neutralViewerDocx.classList.add("is-docx-preview");
    const generatedWrapper = neutralViewerDocx.querySelector(".ed-docx-render-wrapper");
    if (generatedWrapper instanceof HTMLElement) {
        const scaleStage = document.createElement("div");
        scaleStage.className = "ed-docx-scale-stage";
        generatedWrapper.before(scaleStage);
        scaleStage.append(generatedWrapper);
    }
    return true;
}

async function loadNeutralPreview(button) {
    const requestSequence = ++neutralPreviewSequence;
    const fileId = button.dataset.fileId ?? "";
    const previewUrl = button.dataset.previewUrl ?? "";
    const previewSupported = button.dataset.previewSupported === "true";
    neutralSelectedFileId = fileId;
    neutralPreviewRequest?.abort();
    neutralPreviewRequest = null;
    resetNeutralViewer();

    if (!previewSupported || !previewUrl) {
        setNeutralViewerState("unsupported");
        return;
    }

    neutralPreviewRequest = new AbortController();
    setNeutralViewerState("loading");
    startNeutralResourceTimeout(button, requestSequence);
    try {
        const response = await fetch(previewUrl, {
            signal: neutralPreviewRequest.signal,
            headers: { Accept: "application/json" },
            credentials: "same-origin",
        });
        let result;
        try {
            result = await response.json();
        } catch {
            throw new Error("El servidor devolvió una respuesta que no pudo procesarse.");
        }
        if (requestSequence !== neutralPreviewSequence || neutralSelectedFileId !== fileId) return;
        const preview = result?.data?.preview;
        if (!response.ok || !result?.success || !preview) {
            const error = new Error(result?.message || "No fue posible leer este archivo.");
            error.status = response.status;
            throw error;
        }
        if (preview.status !== "ready") {
            const state = preview.status === "missing" ? "missing" : "unsupported";
            setNeutralViewerState(state, preview.message || "");
            return;
        }

        if (preview.preview_type === "pdf" && neutralViewerPdf) {
            neutralViewerPdf.hidden = false;
            neutralViewerPdf.classList.add("is-preparing");
            neutralViewerPdf.onload = () => {
                if (requestSequence === neutralPreviewSequence) {
                    showNeutralPreview(neutralViewerPdf);
                    window.requestAnimationFrame(refreshNeutralPdfLayout);
                }
            };
            neutralViewerPdf.onerror = () => {
                if (requestSequence === neutralPreviewSequence) {
                    resetNeutralViewer();
                    setNeutralViewerState("error", "No fue posible mostrar el documento PDF.");
                }
            };
            window.requestAnimationFrame(() => {
                if (requestSequence !== neutralPreviewSequence) return;
                refreshNeutralPdfLayout();
                neutralViewerPdf.src = buildPdfPreviewUrl(preview.content_url);
            });
        } else if (preview.preview_type === "image" && neutralViewerImage && neutralViewerImageShell) {
            neutralViewerImage.alt = `Vista previa de ${button.dataset.fileName || "la imagen"}`;
            neutralViewerImage.onload = () => {
                if (requestSequence === neutralPreviewSequence) showNeutralPreview(neutralViewerImageShell);
            };
            neutralViewerImage.onerror = () => {
                if (requestSequence === neutralPreviewSequence) {
                    resetNeutralViewer();
                    setNeutralViewerState("error", "No fue posible mostrar la imagen.");
                }
            };
            neutralViewerImage.src = preview.content_url;
        } else if ((preview.preview_type === "text" || preview.preview_type === "code") && neutralViewerText) {
            neutralViewerText.textContent = String(preview.content ?? "");
            showNeutralPreview(neutralViewerText);
        } else if (preview.preview_type === "docx" && neutralViewerDocx) {
            try {
                const rendered = await renderNeutralDocxPreview(preview, requestSequence, fileId);
                if (rendered) {
                    showNeutralPreview(neutralViewerDocx);
                    prepareNeutralDocxScale();
                    scheduleNeutralDocxFit();
                    neutralViewerDocx.scrollLeft = 0;
                    neutralViewerDocx.scrollTop = 0;
                }
            } catch (docxError) {
                if (docxError instanceof DOMException && docxError.name === "AbortError") return;
                if (requestSequence !== neutralPreviewSequence || neutralSelectedFileId !== fileId) return;
                const fallbackBlocks = Array.isArray(preview.blocks) ? preview.blocks : [];
                const fallbackReason = docxError instanceof Error ? docxError.message : String(docxError);
                console.error("[DOCX viewer] Error de renderizado", docxError);
                if (fallbackBlocks.length > 0) {
                    console.warn("[DOCX viewer] Respaldo neutralizado activado", {
                        fileId,
                        reason: fallbackReason,
                        blocks: fallbackBlocks.length,
                    });
                    neutralViewerDocx.dataset.docxFallbackReason = fallbackReason;
                    renderNeutralDocx(fallbackBlocks);
                    showNeutralPreview(neutralViewerDocx);
                } else {
                    throw docxError;
                }
            }
        } else {
            setNeutralViewerState("unsupported", preview.message || "");
        }
    } catch (error) {
        if (error instanceof DOMException && error.name === "AbortError") return;
        if (requestSequence !== neutralPreviewSequence || neutralSelectedFileId !== fileId) return;
        const retry = document.createElement("button");
        retry.type = "button";
        retry.className = "ed-viewer-retry";
        retry.textContent = "Reintentar";
        retry.addEventListener("click", () => loadNeutralPreview(button), { once: true });
        const missing = Number(error?.status) === 404;
        setNeutralViewerState(missing ? "missing" : "error", error instanceof Error ? error.message : "", missing ? null : retry);
    }
}

function protectNeutralDownload(link) {
    link?.addEventListener("click", (event) => {
        event.stopPropagation();
    });
}

function openNeutralExpandedViewer() {
    if (!neutralViewerOverlay || !neutralExpandedBody || !neutralViewerBody || !neutralViewerExpand || !neutralViewerOverlay.hidden) return;
    neutralViewerReturnFocus = neutralViewerExpand;
    neutralViewerPlaceholder = document.createComment("record-viewer-position");
    neutralViewerBody.before(neutralViewerPlaceholder);
    neutralExpandedBody.append(neutralViewerBody);
    if (neutralExpandedName) neutralExpandedName.textContent = neutralViewerName?.textContent || "Visor ampliado";
    if (neutralExpandedMeta) neutralExpandedMeta.textContent = neutralViewerMeta?.textContent || "Consulta del archivo seleccionado";
    if (neutralExpandedDownload && neutralViewerDownload) {
        neutralExpandedDownload.href = neutralViewerDownload.href;
        neutralExpandedDownload.setAttribute("download", neutralViewerDownload.getAttribute("download") || "");
        neutralExpandedDownload.setAttribute("aria-label", neutralViewerDownload.getAttribute("aria-label") || "Descargar archivo");
    }
    if (neutralExpandedDownloadLabel) neutralExpandedDownloadLabel.textContent = neutralViewerDownloadLabel?.textContent || "Descargar archivo";
    neutralViewerBodyOverflow = document.body.style.overflow;
    neutralViewerHtmlOverflow = document.documentElement.style.overflow;
    neutralViewerBackgroundState = [...document.body.children]
        .filter((element) => element !== neutralViewerOverlay && !["SCRIPT", "STYLE"].includes(element.tagName))
        .map((element) => ({ element, inert: element.inert, ariaHidden: element.getAttribute("aria-hidden") }));
    neutralViewerBackgroundState.forEach(({ element }) => {
        element.inert = true;
        element.setAttribute("aria-hidden", "true");
    });
    document.documentElement.classList.add("record-viewer-open");
    document.body.classList.add("record-viewer-open");
    document.documentElement.style.overflow = "hidden";
    document.body.style.overflow = "hidden";
    neutralViewerOverlay.hidden = false;
    neutralViewer?.classList.remove("viewer-normal");
    neutralViewer?.classList.add("viewer-expanded");
    window.requestAnimationFrame(() => {
        refreshNeutralPdfLayout();
        scheduleNeutralDocxFit();
    });
    neutralExpandedClose?.focus();
}

function closeNeutralExpandedViewer(restoreFocus = true) {
    if (!neutralViewerOverlay || neutralViewerOverlay.hidden) return;
    if (neutralViewerPlaceholder?.parentNode && neutralViewerBody) {
        neutralViewerPlaceholder.before(neutralViewerBody);
        neutralViewerPlaceholder.remove();
    }
    neutralViewerPlaceholder = null;
    neutralViewerOverlay.hidden = true;
    neutralViewerBackgroundState.forEach(({ element, inert, ariaHidden }) => {
        element.inert = inert;
        if (ariaHidden === null) element.removeAttribute("aria-hidden");
        else element.setAttribute("aria-hidden", ariaHidden);
    });
    neutralViewerBackgroundState = [];
    document.documentElement.classList.remove("record-viewer-open");
    document.body.classList.remove("record-viewer-open");
    document.body.style.overflow = neutralViewerBodyOverflow;
    document.documentElement.style.overflow = neutralViewerHtmlOverflow;
    neutralViewer?.classList.remove("viewer-expanded");
    neutralViewer?.classList.add("viewer-normal");
    refreshVisibleViewerLayout();
    scheduleNeutralDocxFit();
    if (restoreFocus && neutralViewerReturnFocus instanceof HTMLElement) neutralViewerReturnFocus.focus();
    neutralViewerReturnFocus = null;
}

function selectNeutralFile(button) {
    document.querySelectorAll("[data-zip-entry-file].is-selected").forEach((item) => {
        item.classList.remove("is-selected");
        item.setAttribute("aria-selected", "false");
    });
    neutralFileButtons.forEach((item) => {
        const selected = item === button;
        item.classList.toggle("is-selected", selected);
        item.setAttribute("aria-selected", String(selected));
    });
    if (neutralViewerName) neutralViewerName.textContent = button.dataset.fileName ?? "Archivo";
    if (neutralViewer) neutralViewer.dataset.previewType = button.dataset.previewType ?? "unsupported";
    const selectedPreviewType = button.dataset.previewType ?? "unsupported";
    if (neutralViewerDocxNote) neutralViewerDocxNote.hidden = selectedPreviewType !== "docx";
    neutralViewerBody?.classList.toggle("is-docx-content", selectedPreviewType === "docx");
    neutralDocxHorizontalRatio = 0;
    neutralZoomPercent = selectedPreviewType === "docx"
        ? 100
        : (neutralZoomByFile.get(button.dataset.fileId ?? "") ?? 100);
    if (selectedPreviewType === "docx") {
        const selectedId = button.dataset.fileId ?? "";
        neutralZoomByFile.delete(selectedId);
        [...neutralDocxBaseScaleByContext.keys()].forEach((key) => {
            if (key.startsWith(`${selectedId}:`)) neutralDocxBaseScaleByContext.delete(key);
        });
    }
    if (neutralViewerDocxTopScroll) neutralViewerDocxTopScroll.hidden = true;
    setNeutralZoomControlsVisibility(false);
    if (neutralViewerMeta) {
        const labels = [
            button.dataset.filePresentation === "true" ? "Presentación" : "",
            button.dataset.filePackage === "true" ? "Paquete" : "",
        ];
        neutralViewerMeta.textContent = [button.dataset.fileType ?? "Archivo", button.dataset.fileSize ?? "Tamaño no disponible", ...labels].filter(Boolean).join(" · ");
    }
    if (neutralViewerDownload) {
        neutralViewerDownload.href = button.dataset.downloadUrl ?? "";
        neutralViewerDownload.setAttribute("download", button.dataset.fileName ?? "archivo");
        neutralViewerDownload.setAttribute("aria-label", `Descargar ${button.dataset.fileName ?? "archivo"}`);
    }
    if (neutralViewerDownloadLabel) {
        neutralViewerDownloadLabel.textContent = downloadLabelFor(button.dataset.fileExtension ?? "");
    }
    loadNeutralPreview(button);
}

function selectEvolutionVersion(button) {
    if (!(button instanceof HTMLElement)) return;
    document.querySelectorAll("[data-zip-entry-file].is-selected").forEach((item) => {
        item.classList.remove("is-selected");
        item.setAttribute("aria-selected", "false");
    });
    neutralFileButtons.forEach((item) => {
        item.classList.remove("is-selected");
        item.setAttribute("aria-selected", "false");
    });
    if (neutralViewerName) neutralViewerName.textContent = button.dataset.fileName ?? "Versión del archivo";
    if (neutralViewer) neutralViewer.dataset.previewType = button.dataset.previewType ?? "unsupported";
    const selectedPreviewType = button.dataset.previewType ?? "unsupported";
    if (neutralViewerDocxNote) neutralViewerDocxNote.hidden = selectedPreviewType !== "docx";
    neutralViewerBody?.classList.toggle("is-docx-content", selectedPreviewType === "docx");
    neutralDocxHorizontalRatio = 0;
    neutralZoomPercent = selectedPreviewType === "docx"
        ? 100
        : (neutralZoomByFile.get(button.dataset.fileId ?? "") ?? 100);
    if (neutralViewerDocxTopScroll) neutralViewerDocxTopScroll.hidden = true;
    setNeutralZoomControlsVisibility(false);
    if (neutralViewerMeta) {
        neutralViewerMeta.textContent = [
            button.dataset.fileType ?? "Archivo",
            button.dataset.fileSize ?? "Tamaño no disponible",
            button.dataset.versionCurrent === "true" ? "Versión actual" : "Versión histórica",
        ].filter(Boolean).join(" · ");
    }
    if (neutralViewerDownload) {
        neutralViewerDownload.href = button.dataset.downloadUrl ?? "";
        neutralViewerDownload.setAttribute("download", button.dataset.fileName ?? "version");
        neutralViewerDownload.setAttribute("aria-label", `Descargar ${button.dataset.fileName ?? "la versión"}`);
    }
    if (neutralViewerDownloadLabel) {
        neutralViewerDownloadLabel.textContent = downloadLabelFor(button.dataset.fileExtension ?? "");
    }
    activatePersistentRecordTab("files");
    window.requestAnimationFrame(() => loadNeutralPreview(button));
}

const documentEvolution = digitalRecord?.querySelector("[data-document-evolution]");
documentEvolution?.addEventListener("click", (event) => {
    const previewButton = event.target.closest("[data-evolution-preview]");
    if (previewButton instanceof HTMLElement) {
        selectEvolutionVersion(previewButton);
        return;
    }
    const versionButton = event.target.closest("[data-evolution-version]");
    if (!(versionButton instanceof HTMLElement)) return;
    const detail = versionButton.nextElementSibling;
    if (!(detail instanceof HTMLElement) || !detail.matches("[data-evolution-version-detail]")) return;
    const willOpen = detail.hidden;
    versionButton.setAttribute("aria-expanded", String(willOpen));
    detail.hidden = !willOpen;
});

function bindNeutralFileButton(button) {
    if (!(button instanceof HTMLElement) || button.dataset.fileBound === "true") return;
    button.dataset.fileBound = "true";
    if (button.dataset.previewType === "zip" && button.dataset.zipUrl) {
        button.setAttribute("aria-expanded", button.getAttribute("aria-expanded") || "false");
    }
    button.addEventListener("click", () => {
        const checkbox = button.closest("[data-record-file-item]")?.querySelector("[data-file-select]");
        if (fileSelectionMode && checkbox) {
            checkbox.checked = !checkbox.checked;
            updateFileSelectionFromCheckbox(checkbox);
            return;
        }
        if (button.dataset.previewType === "zip" && button.dataset.zipUrl) {
            toggleNeutralZipTree(button);
            return;
        }
        selectNeutralFile(button);
    });
}
neutralFileButtons.forEach(bindNeutralFileButton);
function initializeNeutralFiles() {
    if (neutralFilesInitialized) {
        refreshVisibleViewerLayout();
        return;
    }
    neutralFilesInitialized = true;
    const presentation = neutralFileButtons.find((button) => button.dataset.filePresentation === "true");
    if (presentation) selectNeutralFile(presentation);
    else if (neutralViewer) setNeutralViewerState("empty");
}
if (!neutralFilesPanel || !neutralFilesPanel.hidden) initializeNeutralFiles();
digitalRecord?.addEventListener("digitalrecord:tabchange", (event) => {
    if (event.detail?.tab !== "files") {
        neutralViewerLayoutSequence += 1;
        return;
    }
    initializeNeutralFiles();
    const activationSequence = event.detail?.sequence;
    window.requestAnimationFrame(() => {
        if (activationSequence !== recordTabActivationSequence || neutralFilesPanel?.hidden) return;
        window.requestAnimationFrame(() => {
            if (activationSequence !== recordTabActivationSequence || neutralFilesPanel?.hidden) return;
            refreshVisibleViewerLayout();
        });
    });
});
window.addEventListener("resize", () => {
    refreshVisibleViewerLayout();
    scheduleNeutralDocxFit();
}, { passive: true });

if (typeof ResizeObserver === "function" && neutralViewerDocx) {
    const neutralDocxResizeObserver = new ResizeObserver(() => {
        scheduleNeutralDocxFit();
        scheduleNeutralDocxTopScrollUpdate();
    });
    neutralDocxResizeObserver.observe(neutralViewerDocx);
}

neutralBackToFiles?.addEventListener("click", () => {
    neutralFileButtons.find((button) => button.getAttribute("aria-selected") === "true")?.focus();
});
protectNeutralDownload(neutralViewerDownload);
protectNeutralDownload(neutralExpandedDownload);
protectNeutralDownload(neutralPackageDownload);
neutralViewerBody?.addEventListener("click", (event) => {
    if (event.target.closest("[data-record-download]")) event.stopPropagation();
});
neutralViewerDocxTopScroll?.addEventListener("scroll", () => {
    if (!neutralViewerDocx || neutralViewerDocxTopScroll.hidden) return;
    syncNeutralDocxHorizontalScroll(neutralViewerDocxTopScroll, neutralViewerDocx);
    const maximum = Math.max(0, neutralViewerDocx.scrollWidth - neutralViewerDocx.clientWidth);
    neutralDocxHorizontalRatio = maximum > 0 ? neutralViewerDocx.scrollLeft / maximum : 0;
}, { passive: true });
neutralViewerDocx?.addEventListener("scroll", () => {
    const maximum = Math.max(0, neutralViewerDocx.scrollWidth - neutralViewerDocx.clientWidth);
    neutralDocxHorizontalRatio = maximum > 0 ? neutralViewerDocx.scrollLeft / maximum : 0;
    if (neutralViewerDocxTopScroll && !neutralViewerDocxTopScroll.hidden) {
        syncNeutralDocxHorizontalScroll(neutralViewerDocx, neutralViewerDocxTopScroll);
    }
}, { passive: true });
neutralViewerDocx?.addEventListener("wheel", (event) => {
    if (!neutralViewerDocx.classList.contains("is-docx-preview")) return;
    const atTop = neutralViewerDocx.scrollTop <= 0;
    const atBottom = neutralViewerDocx.scrollTop + neutralViewerDocx.clientHeight >= neutralViewerDocx.scrollHeight - 1;
    const leavesAtTop = event.deltaY < 0 && atTop;
    const leavesAtBottom = event.deltaY > 0 && atBottom;
    if ((!leavesAtTop && !leavesAtBottom) || !neutralViewerOverlay || neutralViewerOverlay.hidden) return;
    const multiplier = event.deltaMode === WheelEvent.DOM_DELTA_LINE
        ? 16
        : (event.deltaMode === WheelEvent.DOM_DELTA_PAGE ? window.innerHeight : 1);
    window.scrollBy({ top: event.deltaY * multiplier, left: 0, behavior: "auto" });
}, { passive: true });
neutralViewerExpand?.addEventListener("click", openNeutralExpandedViewer);
neutralZoomOutButtons.forEach((button) => button.addEventListener("click", () => setNeutralZoom(neutralZoomPercent - neutralZoomStep)));
neutralZoomResetButtons.forEach((button) => button.addEventListener("click", resetNeutralZoom));
neutralZoomInButtons.forEach((button) => button.addEventListener("click", () => setNeutralZoom(neutralZoomPercent + neutralZoomStep)));
neutralExpandedClose?.addEventListener("click", () => closeNeutralExpandedViewer(true));
neutralViewerOverlay?.addEventListener("click", (event) => {
    if (event.target === neutralViewerOverlay) closeNeutralExpandedViewer(true);
});

document.addEventListener("keydown", (event) => {
    if (neutralViewerOverlay && !neutralViewerOverlay.hidden) {
        if (event.key === "Escape") {
            event.preventDefault();
            closeNeutralExpandedViewer(true);
            return;
        }
        if (event.key === "Tab") {
            const controls = [...neutralViewerOverlay.querySelectorAll('a[href],button:not([disabled]),[tabindex="0"]')]
                .filter((control) => !control.hidden && control.getClientRects().length > 0);
            if (!controls.length) return;
            const currentIndex = controls.indexOf(document.activeElement);
            const nextIndex = event.shiftKey
                ? (currentIndex <= 0 ? controls.length - 1 : currentIndex - 1)
                : (currentIndex === controls.length - 1 ? 0 : currentIndex + 1);
            event.preventDefault();
            controls[nextIndex].focus();
            return;
        }
    }
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
const recordDirtyMessageText = recordDirtyMessage?.querySelector("span");
const recordDirtyMessageIcon = recordDirtyMessage?.querySelector("i");
const recordSubmitButton = recordForm?.querySelector('[type="submit"]');
const recordMaterialTypeChoice = recordForm?.elements.namedItem("material_type_choice");
const recordMaterialTypeCustom = recordForm?.elements.namedItem("material_type_custom");
const recordMaterialTypeCustomWrap = recordForm?.querySelector("[data-record-material-type-custom]");
const recordMaterialTypeCount = recordForm?.querySelector("[data-record-material-type-count]");
const recordKeywordSelector = recordForm?.querySelector("[data-record-keyword-selector]");
const recordKeywordTrigger = recordKeywordSelector?.querySelector("[data-record-keyword-trigger]");
const recordKeywordPanel = recordKeywordSelector?.querySelector("[data-record-keyword-panel]");
const recordKeywordSearch = recordKeywordSelector?.querySelector("[data-record-keyword-search]");
const recordKeywordOptionsContainer = recordKeywordSelector?.querySelector("[data-record-keyword-options]");
const recordKeywordOptions = [...(recordKeywordSelector?.querySelectorAll('[name="keywords_selected[]"]') ?? [])];
const recordKeywordChips = recordForm?.querySelector("[data-record-keyword-chips]");
const recordKeywordSummary = recordKeywordSelector?.querySelector("[data-record-keyword-summary]");
const recordKeywordLimit = recordKeywordSelector?.querySelector("[data-record-keyword-limit]");
let recordKeywordPanelPlacement = null;
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

function normalizeRecordValue(control) {
    let value = String(control.value ?? "").replace(/\r\n?/g, "\n").normalize("NFC");
    if (control.name === "keywords") {
        return [...new Set(value.split(/[,;\n]+/u)
            .map((keyword) => keyword.replace(/[\p{Z}\t\f\v]+/gu, " ").replace(/\s+/gu, " ").trim().toLocaleLowerCase("es"))
            .filter(Boolean))]
            .sort((left, right) => left.localeCompare(right, "es"));
    }
    value = value.replace(/[\p{Z}\t\f\v]+/gu, " ");
    if (["description", "full_description"].includes(control.name)) {
        return value.split("\n").map((line) => line.trim()).join("\n").replace(/\n{3,}/g, "\n\n").trim();
    }
    return value.replace(/\s+/gu, " ").trim();
}

function recordFormEntries() {
    if (!recordForm) return [];
    const entries = [...recordForm.elements]
        .filter((control) => control.name
            && !["_csrf", "id"].includes(control.name)
            && !["material_type_choice", "material_type_custom"].includes(control.name)
            && !control.disabled
            && !control.readOnly
            && !["button", "submit", "reset", "hidden"].includes(control.type)
            && (!["checkbox", "radio"].includes(control.type) || control.checked))
        .map((control) => [control.name, normalizeRecordValue(control)])
        .sort(([left], [right]) => left.localeCompare(right));
    if (recordMaterialTypeChoice instanceof HTMLSelectElement) {
        const finalType = recordMaterialTypeChoice.value === "Otros"
            ? normalizeRecordValue(recordMaterialTypeCustom)
            : normalizeRecordValue(recordMaterialTypeChoice);
        entries.push(["material_type", finalType]);
        entries.sort(([left], [right]) => left.localeCompare(right));
    }
    return entries;
}

function closeRecordKeywordSelector(restoreFocus = false) {
    if (!recordKeywordSelector || !recordKeywordPanel || !recordKeywordTrigger) return;
    recordKeywordPanel.hidden = true;
    recordKeywordSelector.classList.remove("is-open");
    recordKeywordSelector.classList.remove("is-open-above");
    recordKeywordPanelPlacement = null;
    recordKeywordTrigger.setAttribute("aria-expanded", "false");
    if (restoreFocus) recordKeywordTrigger.focus();
}

function sizeRecordKeywordPanel() {
    if (!recordKeywordSelector || !recordKeywordPanel || !recordKeywordOptionsContainer || recordKeywordPanel.hidden) return;
    const visibleOptions = [...recordKeywordOptionsContainer.querySelectorAll(".ed-keyword-option:not([hidden])")];
    recordKeywordOptionsContainer.style.removeProperty("height");
    recordKeywordOptionsContainer.style.removeProperty("max-height");
    const viewportHeight = window.visualViewport?.height || window.innerHeight;
    const viewportMargin = 8;
    const panelGap = 6;
    const triggerRect = recordKeywordTrigger.getBoundingClientRect();
    const availableBelow = Math.max(0, viewportHeight - triggerRect.bottom - panelGap - viewportMargin);
    const availableAbove = Math.max(0, triggerRect.top - panelGap - viewportMargin);
    const panelChromeHeight = Math.max(0, recordKeywordPanel.scrollHeight - recordKeywordOptionsContainer.scrollHeight);
    const candidates = visibleOptions.slice(0, 6);
    const desiredOptionsHeight = candidates.reduce(
        (height, option) => height + Math.ceil(option.getBoundingClientRect().height),
        0
    );
    const desiredPanelHeight = panelChromeHeight + desiredOptionsHeight;
    if (recordKeywordPanelPlacement === null) {
        recordKeywordPanelPlacement = availableBelow >= desiredPanelHeight
            ? "down"
            : (availableAbove >= desiredPanelHeight ? "up" : (availableAbove > availableBelow ? "up" : "down"));
    }
    const opensAbove = recordKeywordPanelPlacement === "up";
    recordKeywordSelector.classList.toggle("is-open-above", opensAbove);

    const maximumPanelHeight = Math.max(0, opensAbove ? availableAbove : availableBelow);
    const optionBudget = Math.max(0, maximumPanelHeight - panelChromeHeight);
    let completeHeight = 0;
    let completeRows = 0;
    candidates.forEach((option) => {
        const rowHeight = Math.ceil(option.getBoundingClientRect().height);
        if (completeHeight + rowHeight <= optionBudget) {
            completeHeight += rowHeight;
            completeRows += 1;
        }
    });
    recordKeywordOptionsContainer.style.height = `${Math.max(0, completeHeight)}px`;
    recordKeywordOptionsContainer.style.maxHeight = `${Math.max(0, completeHeight)}px`;
    recordKeywordOptionsContainer.style.overflowY = visibleOptions.length > completeRows ? "auto" : "hidden";
}

function openRecordKeywordSelector() {
    if (!recordKeywordSelector || !recordKeywordPanel || !recordKeywordTrigger) return;
    recordKeywordPanel.hidden = false;
    recordKeywordSelector.classList.add("is-open");
    recordKeywordTrigger.setAttribute("aria-expanded", "true");
    sizeRecordKeywordPanel();
    recordKeywordSearch?.focus();
}

function renderRecordKeywordSelection() {
    const selected = recordKeywordOptions.filter((option) => option.checked);
    const atLimit = selected.length >= 8;
    recordKeywordOptions.forEach((option) => {
        option.disabled = option.dataset.legacyRemoved === "true" || (atLimit && !option.checked);
        option.closest('[role="option"]')?.setAttribute("aria-selected", String(option.checked));
    });
    if (recordKeywordSummary) {
        recordKeywordSummary.textContent = selected.length
            ? `${selected.length} ${selected.length === 1 ? "palabra seleccionada" : "palabras seleccionadas"}`
            : "Selecciona palabras clave";
    }
    if (recordKeywordLimit) recordKeywordLimit.hidden = !atLimit;
    if (!recordKeywordChips) return;
    recordKeywordChips.replaceChildren(...selected.map((option) => {
        const chip = document.createElement("button");
        chip.type = "button";
        chip.className = "ed-keyword-chip";
        chip.setAttribute("aria-label", `Quitar ${option.value}`);
        const label = document.createElement("span");
        label.textContent = option.value;
        const icon = document.createElement("i");
        icon.className = "fa-solid fa-xmark";
        icon.setAttribute("aria-hidden", "true");
        chip.append(label, icon);
        chip.addEventListener("click", () => {
            option.checked = false;
            option.dispatchEvent(new Event("change", { bubbles: true }));
            recordKeywordTrigger?.focus();
        });
        return chip;
    }));
}

recordKeywordTrigger?.addEventListener("click", () => {
    if (recordKeywordPanel?.hidden) openRecordKeywordSelector();
    else closeRecordKeywordSelector(true);
});
recordKeywordTrigger?.addEventListener("keydown", (event) => {
    if (!["ArrowDown", "Enter", " "].includes(event.key)) return;
    event.preventDefault();
    openRecordKeywordSelector();
});
recordKeywordOptions.forEach((option) => option.addEventListener("change", () => {
    if (option.checked && recordKeywordOptions.filter((item) => item.checked).length > 8) {
        option.checked = false;
        if (recordKeywordLimit) recordKeywordLimit.hidden = false;
    }
    if (option.dataset.keywordLegacy === "true" && !option.checked) {
        option.dataset.legacyRemoved = "true";
        option.disabled = true;
        const row = option.closest("[data-keyword-search]");
        if (row) row.hidden = true;
        sizeRecordKeywordPanel();
    }
    renderRecordKeywordSelection();
    updateRecordDirtyState();
}));
recordKeywordSearch?.addEventListener("input", () => {
    const normalizeSearch = (value) => value.normalize("NFD").replace(/\p{Mn}+/gu, "").trim().toLocaleLowerCase("es");
    const query = normalizeSearch(recordKeywordSearch.value);
    recordKeywordOptions.forEach((option) => {
        const row = option.closest("[data-keyword-search]");
        if (row) {
            row.hidden = option.dataset.legacyRemoved === "true"
                || (query !== "" && !normalizeSearch(String(row.dataset.keywordSearch || "")).includes(query));
        }
    });
    sizeRecordKeywordPanel();
});
document.addEventListener("click", (event) => {
    if (recordKeywordSelector && !recordKeywordSelector.contains(event.target)) closeRecordKeywordSelector();
});
document.addEventListener("keydown", (event) => {
    if (event.key === "Escape" && recordKeywordPanel && !recordKeywordPanel.hidden) {
        event.preventDefault();
        closeRecordKeywordSelector(true);
    }
});
renderRecordKeywordSelection();
window.addEventListener("resize", sizeRecordKeywordPanel, { passive: true });
window.visualViewport?.addEventListener("resize", sizeRecordKeywordPanel, { passive: true });
window.addEventListener("scroll", (event) => {
    if (event.target === recordKeywordOptionsContainer || recordKeywordOptionsContainer?.contains(event.target)) return;
    sizeRecordKeywordPanel();
}, { passive: true });

let recordInitialValues = JSON.stringify(recordFormEntries());

function syncRecordMaterialType() {
    if (!(recordMaterialTypeChoice instanceof HTMLSelectElement)
        || !(recordMaterialTypeCustom instanceof HTMLInputElement)
        || !recordMaterialTypeCustomWrap) return;
    const custom = recordMaterialTypeChoice.value === "Otros";
    const embeddedInPanel = Boolean(recordMaterialTypeCustomWrap.closest(".custom-select-panel"));
    recordMaterialTypeCustomWrap.hidden = !custom || !embeddedInPanel;
    recordMaterialTypeCustom.required = custom;
    if (!custom) recordMaterialTypeCustom.value = "";
    if (recordMaterialTypeCount) recordMaterialTypeCount.textContent = String([...recordMaterialTypeCustom.value].length);
}

recordMaterialTypeChoice?.addEventListener("change", () => {
    syncRecordMaterialType();
    updateRecordDirtyState();
    if (recordMaterialTypeChoice.value === "Otros") recordMaterialTypeCustom?.focus();
});
recordMaterialTypeCustom?.addEventListener("input", () => {
    if (recordMaterialTypeCount) recordMaterialTypeCount.textContent = String([...recordMaterialTypeCustom.value].length);
});
recordMaterialTypeCustom?.addEventListener("blur", () => {
    const trimmedValue = recordMaterialTypeCustom.value.trim();
    if (recordMaterialTypeCustom.value !== trimmedValue) {
        recordMaterialTypeCustom.value = trimmedValue;
        recordMaterialTypeCustom.dispatchEvent(new Event("input", { bubbles: true }));
    }
    updateRecordDirtyState();
});
syncRecordMaterialType();

function recordFormIsDirty() {
    return Boolean(recordForm) && JSON.stringify(recordFormEntries()) !== recordInitialValues;
}

function updateRecordDirtyState() {
    const dirty = recordFormIsDirty();
    recordIsDirty = dirty;
    if (recordForm) recordForm.dataset.dirty = String(dirty);
    if (recordSubmitButton) recordSubmitButton.disabled = !dirty || recordIsSubmitting;
    recordDirtyMessage?.classList.toggle("is-dirty", dirty);
    if (recordDirtyMessageText) recordDirtyMessageText.textContent = dirty ? "Hay cambios sin guardar." : "No hay cambios pendientes.";
    if (recordDirtyMessageIcon) recordDirtyMessageIcon.className = `fa-solid ${dirty ? "fa-triangle-exclamation" : "fa-circle-check"}`;
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
    if (fieldName === "keywords_selected") {
        recordKeywordTrigger?.setAttribute("aria-invalid", "true");
        const keywordError = recordForm?.querySelector('[data-field-error="keywords_selected"]');
        if (keywordError) keywordError.textContent = message;
    } else if (fieldName) setRecordFieldError(recordForm?.elements.namedItem(fieldName), message);
    recordErrorSummary?.focus();
}

function fieldForServerMessage(message) {
    const normalized = message.toLocaleLowerCase("es");
    if (normalized.includes("título")) return "title";
    if (normalized.includes("categoría")) return "category_id";
    if (normalized.includes("especifica el tipo") || normalized.includes("personalizado")) return "material_type_custom";
    if (normalized.includes("tipo de material")) return "material_type_choice";
    if (normalized.includes("descripción corta")) return "description";
    if (normalized.includes("descripción completa")) return "full_description";
    if (normalized.includes("palabra clave") || normalized.includes("palabras clave")) return "keywords_selected";
    if (normalized.includes("responsable")) return "publisher";
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

    if (!updateRecordDirtyState()) return;

    if (!recordForm.checkValidity()) {
        const invalidFields = [...recordForm.querySelectorAll(":invalid")];
        invalidFields.forEach((field) => setRecordFieldError(field, field.validationMessage));
        showRecordError("Revisa los campos señalados antes de guardar.");
        if (invalidFields.includes(recordMaterialTypeCustom)) {
            const materialTypeTrigger = recordMaterialTypeChoice?.closest(".custom-select")?.querySelector(".custom-select-trigger");
            if (materialTypeTrigger?.getAttribute("aria-expanded") !== "true") materialTypeTrigger?.click();
            requestAnimationFrame(() => recordMaterialTypeCustom?.focus());
        }
        return;
    }

    const materialTypeTrigger = recordMaterialTypeChoice?.closest(".custom-select")?.querySelector(".custom-select-trigger");
    if (materialTypeTrigger?.getAttribute("aria-expanded") === "true") materialTypeTrigger.click();
    openRecordSaveDialog(recordForm.querySelector('[type="submit"]'));
});

async function submitRecordForm() {
    if (!recordForm || recordIsSubmitting) return;
    const submitButton = recordSubmitButton;
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
            submitButton?.focus();
            return;
        }
        recordInitialValues = JSON.stringify(recordFormEntries());
        recordIsSubmitting = false;
        updateRecordDirtyState();
        sessionStorage.setItem("digitalRecordToast", "Cambios guardados correctamente.");
        window.location.assign(recordForm.dataset.successUrl);
    } catch (error) {
        recordIsSubmitting = false;
        updateRecordDirtyState();
        closeRecordSaveDialog(false);
        const message = error instanceof Error ? error.message : "No fue posible guardar los cambios.";
        showRecordError(message, fieldForServerMessage(message));
    } finally {
        updateRecordDirtyState();
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
            const transition = historyElement("div", "ed-history-transition");
            transition.append(
                historyElement("span", "ed-history-transition__previous", historyTextValue(change.old, change.field)),
                historyElement("i", "fa-solid fa-arrow-right ed-history-transition__arrow"),
                historyElement("span", "ed-history-transition__new", historyTextValue(change.new, change.field))
            );
            block.append(transition);
            changes.append(block);
        });
        article.append(changes);
    } else if (Array.isArray(item.details) && item.details.length) {
        const details = historyElement("div", "ed-history-changes");
        const block = historyElement("section", "ed-history-change");
        const remaining = new Map(item.details.filter(detail => detail?.value).map(detail => [detail.key || detail.label, detail]));
        const appendTransition = (label, previousKeys, newKeys) => {
            const previousKey = previousKeys.find(key => remaining.has(key));
            const newKey = newKeys.find(key => remaining.has(key));
            if (!previousKey || !newKey) return;
            const previous = remaining.get(previousKey);
            const next = remaining.get(newKey);
            const wrapper = historyElement("div", "ed-history-value ed-history-value--transition");
            wrapper.append(historyElement("strong", "", label));
            const transition = historyElement("div", "ed-history-transition");
            transition.append(
                historyElement("span", "ed-history-transition__previous", previous.value),
                historyElement("i", "fa-solid fa-arrow-right ed-history-transition__arrow"),
                historyElement("span", "ed-history-transition__new", next.value)
            );
            wrapper.append(transition);
            block.append(wrapper);
            remaining.delete(previousKey);
            remaining.delete(newKey);
        };
        appendTransition("Estado", ["previous_status"], ["new_status"]);
        appendTransition("Disponibilidad", ["previous_available", "previous_availability"], ["is_available", "new_availability"]);
        appendTransition("Archivo", ["previous_file", "old_file_name"], ["new_file", "new_file_name"]);
        appendTransition("Presentación", ["previous_name", "presentation_previous"], ["new_name", "presentation_new"]);
        remaining.forEach(detail => {
            const wrapper = historyElement("div", "ed-history-value");
            wrapper.append(historyElement("strong", "", detail.label));
            wrapper.append(historyElement("span", "", detail.value));
            block.append(wrapper);
        });
        if (block.childElementCount) {
            details.append(block);
            article.append(details);
        }
    } else if (item.legacy_without_details) {
        const legacy = historyElement("div", "ed-history-legacy");
        legacy.append(historyElement("strong", "", "Registro antiguo"));
        legacy.append(historyElement("span", "", "Este evento fue registrado antes de habilitar el detalle de cambios."));
        article.append(legacy);
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
        if (initialLoad) {
            document.querySelectorAll("[data-record-unread-dot],[data-record-history-unread-dot],[data-record-unread-text]").forEach(element => { element.hidden = true; });
        }
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

// Incorporación de archivos desde el Expediente Digital.
const recordUploadOpen = neutralFileList?.querySelector("[data-record-file-add]");
const recordUploadDialog = document.querySelector("[data-record-file-upload-dialog]");
if (recordUploadDialog && recordUploadDialog.parentElement !== document.body) document.body.append(recordUploadDialog);
const recordUploadForm = recordUploadDialog?.querySelector("[data-record-file-upload-form]");
const recordUploadInput = recordUploadDialog?.querySelector("[data-upload-input]");
const recordUploadList = recordUploadDialog?.querySelector("[data-upload-file-list]");
const recordUploadStatus = recordUploadDialog?.querySelector("[data-upload-status]");
const recordUploadResults = recordUploadDialog?.querySelector("[data-upload-results]");
const recordUploadSubmit = recordUploadDialog?.querySelector("[data-upload-submit]");
const recordUploadSubmitLabel = recordUploadDialog?.querySelector("[data-upload-submit-label]");
const recordUploadCancel = recordUploadDialog?.querySelector("[data-upload-cancel]");
const recordUploadClose = recordUploadDialog?.querySelector("[data-upload-close]");
const recordToastStack = document.querySelector("[data-record-toast-stack]");
if (recordToastStack && recordToastStack.parentElement !== document.body) document.body.append(recordToastStack);
let recordUploadEntries = [];
let recordUploadState = "idle";
let recordUploadReturnFocus = null;
let recordUploadBackgroundState = [];
let recordUploadCloseTimer = null;
const recordToastRegistry = new Map();
const recordToastDurations = { success: 3800, info: 4000, warning: 5000, error: 5600 };

function reflowRecordToasts(mutator) {
    if (!recordToastStack) return;
    const before = new Map(
        [...recordToastStack.children].map((toast) => [toast, toast.getBoundingClientRect().top])
    );
    mutator();
    [...recordToastStack.children].forEach((toast) => {
        const previousTop = before.get(toast);
        if (previousTop === undefined) return;
        const delta = previousTop - toast.getBoundingClientRect().top;
        if (!delta) return;
        toast.style.transition = "none";
        toast.style.transform = `translateY(${delta}px)`;
        window.requestAnimationFrame(() => {
            toast.style.transition = "transform 220ms ease";
            toast.style.transform = "";
            window.setTimeout(() => { toast.style.transition = ""; }, 230);
        });
    });
}

function recordUploadSize(bytes) {
    if (!Number.isFinite(bytes) || bytes < 1) return "0 bytes";
    const units = ["bytes", "KB", "MB", "GB"];
    const index = Math.min(Math.floor(Math.log(bytes) / Math.log(1024)), units.length - 1);
    const value = bytes / (1024 ** index);
    return `${value.toLocaleString("es-EC", { maximumFractionDigits: index ? 1 : 0 })} ${units[index]}`;
}

function setRecordUploadState(state, message = "") {
    recordUploadState = state;
    const busy = state === "uploading";
    recordUploadForm?.setAttribute("aria-busy", String(busy));
    if (recordUploadInput) recordUploadInput.disabled = busy;
    if (recordUploadCancel) recordUploadCancel.disabled = busy;
    if (recordUploadClose) recordUploadClose.disabled = busy;
    if (recordUploadSubmit) recordUploadSubmit.disabled = busy || !recordUploadEntries.some((entry) => entry.valid);
    if (recordUploadSubmitLabel) recordUploadSubmitLabel.textContent = busy ? "Agregando…" : "Agregar archivos";
    if (recordUploadStatus) {
        recordUploadStatus.textContent = message;
        recordUploadStatus.classList.toggle("is-error", state === "error" || state === "partial");
        recordUploadStatus.classList.toggle("is-success", state === "success");
    }
}

function validateRecordUploadFiles(files) {
    const maxFiles = Number(recordUploadForm?.dataset.maxFiles || 5);
    if (files.length > maxFiles) {
        recordUploadEntries = [];
        renderRecordUploadEntries();
        setRecordUploadState("error", `Puedes seleccionar un máximo de ${maxFiles} archivos por operación.`);
        return;
    }
    const allowed = new Set((recordUploadForm?.dataset.extensions || "").split(",").filter(Boolean));
    const maxBytes = Number(recordUploadForm?.dataset.maxBytes || 26214400);
    const maxOperationBytes = Number(recordUploadForm?.dataset.maxOperationBytes || 36700160);
    const maxName = Number(recordUploadForm?.dataset.maxName || 200);
    if (files.reduce((total, file) => total + file.size, 0) > maxOperationBytes) {
        recordUploadEntries = [];
        renderRecordUploadEntries();
        setRecordUploadState("error", "La selección completa supera el límite de 35 MB por operación.");
        return;
    }
    const seen = new Set();
    recordUploadEntries = files.map((file) => {
        const name = String(file.name || "");
        const extension = name.includes(".") ? name.split(".").pop().toLowerCase() : "";
        const duplicateKey = `${name}\u0000${file.size}\u0000${file.lastModified || 0}`;
        let message = "Válido";
        if (!file.size) message = "Archivo vacío";
        else if (file.size > maxBytes) message = "Supera el tamaño máximo";
        else if ([...name].length > maxName) message = "Nombre demasiado largo";
        else if (!allowed.has(extension)) message = "Formato no permitido";
        else if (seen.has(duplicateKey)) message = "Duplicado en la selección";
        seen.add(duplicateKey);
        return { file, valid: message === "Válido", message };
    });
    renderRecordUploadEntries();
    const validCount = recordUploadEntries.filter((entry) => entry.valid).length;
    setRecordUploadState("idle", validCount ? `${validCount} ${validCount === 1 ? "archivo válido" : "archivos válidos"} para agregar.` : "No hay archivos válidos para agregar.");
}

function renderRecordUploadEntries() {
    if (!recordUploadList) return;
    recordUploadList.replaceChildren();
    recordUploadEntries.forEach((entry, index) => {
        const row = document.createElement("div");
        row.className = `ed-upload-item${entry.valid ? "" : " is-invalid"}`;
        const info = document.createElement("div");
        const name = document.createElement("strong");
        name.textContent = entry.file.name;
        name.title = entry.file.name;
        const meta = document.createElement("small");
        const extension = entry.file.name.includes(".") ? entry.file.name.split(".").pop().toUpperCase() : "Sin extensión";
        meta.textContent = `${extension} · ${recordUploadSize(entry.file.size)}`;
        const status = document.createElement("div");
        status.className = "ed-upload-item-status";
        status.textContent = entry.message;
        info.append(name, meta, status);
        const remove = document.createElement("button");
        remove.type = "button";
        remove.className = "ed-upload-remove";
        remove.setAttribute("aria-label", `Quitar ${entry.file.name}`);
        remove.innerHTML = '<i class="fa-solid fa-xmark" aria-hidden="true"></i>';
        remove.addEventListener("click", () => {
            recordUploadEntries.splice(index, 1);
            renderRecordUploadEntries();
            setRecordUploadState("idle", recordUploadEntries.length ? "Selección actualizada." : "Selecciona al menos un archivo.");
        });
        row.append(info, remove);
        recordUploadList.append(row);
    });
}

function openRecordUpload() {
    if (!recordUploadDialog || recordUploadState === "uploading") return;
    if (recordUploadCloseTimer !== null) {
        window.clearTimeout(recordUploadCloseTimer);
        recordUploadCloseTimer = null;
    }
    recordUploadReturnFocus = document.activeElement?.closest?.("[data-file-global-menu]")
        ? globalFileToggle
        : document.activeElement;
    recordUploadEntries = [];
    if (recordUploadInput) recordUploadInput.value = "";
    recordUploadResults?.replaceChildren();
    renderRecordUploadEntries();
    setRecordUploadState("idle", "Selecciona los archivos que deseas agregar.");
    recordUploadBackgroundState = [...document.body.children].filter((element) => element !== recordUploadDialog).map((element) => ({
        element, inert: element.inert, ariaHidden: element.getAttribute("aria-hidden")
    }));
    recordUploadBackgroundState.forEach(({ element }) => {
        element.inert = true;
        element.setAttribute("aria-hidden", "true");
    });
    document.documentElement.classList.add("record-upload-open");
    document.body.classList.add("record-upload-open");
    recordUploadDialog.hidden = false;
    window.requestAnimationFrame(() => recordUploadInput?.focus());
}

function resetRecordUploadDialog() {
    if (recordUploadCloseTimer !== null) {
        window.clearTimeout(recordUploadCloseTimer);
        recordUploadCloseTimer = null;
    }
    recordUploadEntries = [];
    if (recordUploadInput) recordUploadInput.value = "";
    recordUploadList?.replaceChildren();
    recordUploadResults?.replaceChildren();
    setRecordUploadState("idle", "");
}

function closeRecordUpload(reset = false) {
    if (!recordUploadDialog || recordUploadDialog.hidden || recordUploadState === "uploading") return;
    recordUploadDialog.hidden = true;
    recordUploadBackgroundState.forEach(({ element, inert, ariaHidden }) => {
        element.inert = inert;
        if (ariaHidden === null) element.removeAttribute("aria-hidden");
        else element.setAttribute("aria-hidden", ariaHidden);
    });
    recordUploadBackgroundState = [];
    document.documentElement.classList.remove("record-upload-open");
    document.body.classList.remove("record-upload-open");
    if (recordUploadReturnFocus instanceof HTMLElement) recordUploadReturnFocus.focus();
    recordUploadReturnFocus = null;
    if (reset) resetRecordUploadDialog();
}

function getNeutralFileVisualType(extension) {
    const normalized = String(extension || "").trim().toLowerCase().replace(/^\.+/, "");
    const visual = {
        pdf: ["fa-file-lines", "PDF"],
        doc: ["fa-file-pen", "DOC"], docx: ["fa-file-pen", "DOCX"],
        xls: ["fa-table", "XLS"], xlsx: ["fa-table", "XLSX"],
        ppt: ["fa-display", "PPT"], pptx: ["fa-display", "PPTX"],
        txt: ["fa-align-left", "TXT"],
        zip: ["fa-box-archive", "ZIP"], rar: ["fa-box-archive", "RAR"],
        "7z": ["fa-box-archive", "7Z"], tar: ["fa-box-archive", "TAR"], gz: ["fa-box-archive", "GZ"],
        jpg: ["fa-image", "JPG"], jpeg: ["fa-image", "JPEG"],
        png: ["fa-image", "PNG"], webp: ["fa-image", "WEBP"],
        gif: ["fa-image", "GIF"], svg: ["fa-image", "SVG"]
    }[normalized];
    return {
        extension: normalized,
        icon: visual?.[0] || "fa-file",
        label: visual?.[1] || "FILE"
    };
}

function showRecordUploadToast(message, type = "success", options = {}) {
    if (!recordToastStack || !message) return;
    const normalizedType = ["success", "info", "warning", "error"].includes(type) ? type : "info";
    if (normalizedType === "success") window.markRecordHistoryUnread?.();
    const key = `${normalizedType}:${message}`;
    let existing = recordToastRegistry.get(key);
    if (options.freshAttempt && existing?.element?.isConnected) {
        window.clearTimeout(existing.timer);
        reflowRecordToasts(() => existing.element.remove());
        recordToastRegistry.delete(key);
        existing = null;
    }
    if (existing?.element?.isConnected) {
        if (message === "Este archivo ya se encuentra disponible dentro del material.") {
            existing.copy.textContent = message;
            window.clearTimeout(existing.timer);
            existing.timer = window.setTimeout(
                () => dismissRecordToast(key),
                recordToastDurations[normalizedType]
            );
            return;
        }
        existing.count += 1;
        existing.copy.textContent = `${message} ×${existing.count}`;
        window.clearTimeout(existing.timer);
        existing.timer = window.setTimeout(
            () => dismissRecordToast(key),
            recordToastDurations[normalizedType]
        );
        return;
    }
    const element = document.createElement("div");
    const icon = document.createElement("i");
    const copy = document.createElement("span");
    const close = document.createElement("button");
    const icons = {
        success: "fa-circle-check",
        info: "fa-circle-info",
        warning: "fa-triangle-exclamation",
        error: "fa-circle-xmark",
    };
    element.className = `ed-upload-toast is-${normalizedType}`;
    element.setAttribute("role", normalizedType === "error" ? "alert" : "status");
    icon.className = `fa-solid ${icons[normalizedType]}`;
    icon.setAttribute("aria-hidden", "true");
    copy.textContent = message;
    close.type = "button";
    close.setAttribute("aria-label", "Cerrar mensaje");
    close.innerHTML = '<i class="fa-solid fa-xmark" aria-hidden="true"></i>';
    element.append(icon, copy, close);
    recordToastStack.prepend(element);
    const entry = { element, copy, count: 1, timer: null };
    entry.timer = window.setTimeout(
        () => dismissRecordToast(key),
        recordToastDurations[normalizedType]
    );
    recordToastRegistry.set(key, entry);
    close.addEventListener("click", () => dismissRecordToast(key));
}

function dismissRecordToast(key) {
    const entry = recordToastRegistry.get(key);
    if (!entry) return;
    window.clearTimeout(entry.timer);
    entry.element.classList.add("is-leaving");
    window.setTimeout(() => {
        reflowRecordToasts(() => entry.element.remove());
    }, 210);
    recordToastRegistry.delete(key);
}
const pendingDigitalRecordToast = sessionStorage.getItem("digitalRecordToast");
if (pendingDigitalRecordToast) {
    sessionStorage.removeItem("digitalRecordToast");
    showRecordUploadToast(pendingDigitalRecordToast);
}

function ensureNeutralFileGroup(key) {
    let group = neutralFileList?.querySelector(`[data-file-group="${key}"]`);
    if (group) return group.querySelector("[data-file-group-list]");
    const panel = neutralFileList?.querySelector(".ed-files-panel");
    if (!panel) return null;
    group = document.createElement("section");
    group.className = "ed-document-group";
    group.dataset.fileGroup = key;
    const heading = document.createElement("h3");
    heading.textContent = key === "presentation"
        ? "Archivo de presentación"
        : (key === "archives" ? "Archivos comprimidos adicionales" : "Archivos del material");
    const list = document.createElement("div");
    list.className = "ed-document-list";
    list.dataset.fileGroupList = "";
    list.setAttribute("role", "listbox");
    list.setAttribute("aria-label", heading.textContent);
    group.append(heading, list);
    if (key === "presentation") {
        panel.insertBefore(group, panel.querySelector(".ed-document-group"));
    } else {
        panel.append(group);
    }
    return list;
}

function syncNeutralFilesEmptyState() {
    const count = neutralFileButtons.length;
    const emptyState = neutralFileList?.querySelector("[data-record-files-empty]");
    emptyState?.toggleAttribute("hidden", count > 0);
    const counter = neutralFileList?.querySelector("[data-record-file-count]");
    if (counter) counter.textContent = `${count} ${count === 1 ? "archivo disponible" : "archivos disponibles"}`;
    if (fileSelectionToggle) {
        fileSelectionToggle.disabled = !neutralFileList?.querySelector("[data-file-select]");
    }
}

function createPresentationMark() {
    const mark = document.createElement("span");
    mark.className = "ed-file-mark is-presentation";
    mark.dataset.filePresentationMark = "";
    mark.innerHTML = '<i class="fa-solid fa-display" aria-hidden="true"></i> Presentación';
    return mark;
}

function fileMarksContainer(button) {
    let container = button?.querySelector("[data-file-marks]");
    if (!container && button) {
        container = document.createElement("span");
        container.className = "ed-file-marks";
        container.dataset.fileMarks = "";
        button.append(container);
    }
    return container;
}

function createPresentationAction(isPresentation = false) {
    const action = document.createElement("button");
    action.type = "button";
    action.dataset.filePresentationAction = "";
    action.setAttribute("role", "menuitem");
    action.innerHTML = `<i class="fa-solid fa-display" aria-hidden="true"></i>${isPresentation ? "Quitar como archivo de presentación" : "Usar como archivo de presentación"}`;
    return action;
}

function syncPresentationFile(targetButton) {
    neutralFileButtons.forEach((button) => {
        const item = button.closest("[data-record-file-item]");
        const isPresentation = button === targetButton;
        button.dataset.filePresentation = String(isPresentation);
        item?.querySelector("[data-file-presentation-mark]")?.remove();
        if (isPresentation) fileMarksContainer(button)?.prepend(createPresentationMark());
        const menu = item?.querySelector("[data-file-menu]");
        const existingAction = menu?.querySelector("[data-file-presentation-action]");
        existingAction?.remove();
        if (button.dataset.previewSupported === "true"
            && button.dataset.filePackage !== "true" && menu) {
            const action = createPresentationAction(isPresentation);
            menu.insertBefore(action, menu.querySelector("[data-file-remove-action]"));
            bindFilePresentationAction(action, item);
        }
        const destination = ensureNeutralFileGroup(
            isPresentation ? "presentation" : (button.dataset.fileExtension === "zip" ? "archives" : "additional")
        );
        if (destination && item) destination.append(item);
    });
    const presentationGroup = neutralFileList?.querySelector('[data-file-group="presentation"]');
    if (presentationGroup && !presentationGroup.querySelector("[data-record-file]")) presentationGroup.remove();
    syncNeutralFilesEmptyState();
    if (targetButton) {
        selectNeutralFile(targetButton);
    } else {
        neutralSelectedFileId = "";
        neutralPreviewSequence += 1;
        neutralPreviewRequest?.abort();
        neutralPreviewRequest = null;
        resetNeutralViewer();
        if (neutralViewerName) neutralViewerName.textContent = "Archivo";
        if (neutralViewerMeta) neutralViewerMeta.textContent = "";
        setNeutralViewerState("empty");
    }
}

function appendNeutralAddedFiles(files) {
    files.forEach((file) => {
        const visual = getNeutralFileVisualType(file.extension);
        const list = ensureNeutralFileGroup(visual.extension === "zip" || file.is_archive ? "archives" : "additional");
        if (!list) return;
        const button = document.createElement("button");
        button.type = "button";
        button.className = "ed-document-row";
        button.setAttribute("role", "option");
        button.setAttribute("aria-selected", "false");
        Object.assign(button.dataset, {
            recordFile: "", fileId: String(file.id), fileName: file.name, fileType: visual.label,
            fileSize: file.size_label, fileExtension: visual.extension,
            fileSortOrder: String(file.sort_order ?? Number.MAX_SAFE_INTEGER),
            filePresentation: "false",
            filePackage: "false", previewSupported: String(Boolean(file.preview_supported)),
            previewType: file.preview_type || "unsupported", previewUrl: file.preview_url || "",
            zipUrl: file.zip_url || "",
            zipEntryPreviewUrl: file.zip_entry_preview_url || "",
            zipEntryDownloadUrl: file.zip_entry_download_url || "",
            downloadUrl: file.download_url || ""
        });
        button.setAttribute("aria-label", `${file.name}, ${visual.label}, ${file.size_label}`);
        const icon = document.createElement("i");
        icon.className = `fa-solid ${visual.icon}`;
        icon.setAttribute("aria-hidden", "true");
        const info = document.createElement("span");
        const strong = document.createElement("strong");
        strong.textContent = file.name;
        strong.title = file.name;
        const small = document.createElement("small");
        small.textContent = `${visual.label} · ${file.size_label}`;
        info.append(strong, small);
        button.append(icon, info);
        const item = document.createElement("div");
        item.className = "ed-document-item";
        item.dataset.recordFileItem = "";
        const checkbox = document.createElement("input");
        checkbox.type = "checkbox";
        checkbox.className = "ed-file-checkbox";
        checkbox.dataset.fileSelect = "";
        checkbox.value = String(file.id);
        checkbox.setAttribute("aria-label", `Seleccionar ${file.name}`);
        checkbox.hidden = !fileSelectionMode;
        const menuToggle = document.createElement("button");
        menuToggle.type = "button";
        menuToggle.className = "ed-file-menu-toggle";
        menuToggle.dataset.fileMenuToggle = "";
        menuToggle.setAttribute("aria-haspopup", "menu");
        menuToggle.setAttribute("aria-expanded", "false");
        menuToggle.setAttribute("aria-label", `Acciones de ${file.name}`);
        menuToggle.innerHTML = '<i class="fa-solid fa-ellipsis-vertical" aria-hidden="true"></i>';
        const menu = document.createElement("div");
        menu.className = "ed-file-menu";
        menu.dataset.fileMenu = "";
        menu.setAttribute("role", "menu");
        menu.hidden = true;
        const downloadAction = document.createElement("a");
        downloadAction.dataset.fileDownloadAction = "";
        downloadAction.setAttribute("role", "menuitem");
        downloadAction.setAttribute("download", "");
        downloadAction.href = file.download_url || "";
        downloadAction.innerHTML = '<i class="fa-solid fa-download" aria-hidden="true"></i>Descargar';
        const separator = document.createElement("hr");
        const replaceAction = document.createElement("button");
        replaceAction.type = "button";
        replaceAction.dataset.fileReplaceAction = "";
        replaceAction.setAttribute("role", "menuitem");
        replaceAction.innerHTML = '<i class="fa-solid fa-arrows-rotate" aria-hidden="true"></i>Reemplazar archivo';
        const removeAction = document.createElement("button");
        removeAction.type = "button";
        removeAction.dataset.fileRemoveAction = "";
        removeAction.setAttribute("role", "menuitem");
        removeAction.innerHTML = '<i class="fa-solid fa-box-archive" aria-hidden="true"></i>Retirar archivo';
        if (file.preview_supported && visual.extension !== "zip") {
            const presentationAction = document.createElement("button");
            presentationAction.type = "button";
            presentationAction.dataset.filePresentationAction = "";
            presentationAction.setAttribute("role", "menuitem");
            presentationAction.innerHTML = '<i class="fa-solid fa-display" aria-hidden="true"></i>Usar como archivo de presentación';
            menu.append(downloadAction, separator, replaceAction, presentationAction, removeAction);
        } else {
            menu.append(downloadAction, separator, replaceAction, removeAction);
        }
        item.append(checkbox, button, menuToggle, menu);
        list.append(item);
        if (visual.extension === "zip") {
            const tree = document.createElement("div");
            tree.className = "ed-zip-tree";
            tree.dataset.zipTree = "";
            tree.dataset.zipFileId = String(file.id);
            tree.setAttribute("role", "tree");
            tree.setAttribute("aria-label", `Contenido de ${file.name}`);
            tree.hidden = true;
            list.append(tree);
        }
        neutralFileButtons.push(button);
        if (fileSelectionToggle) fileSelectionToggle.disabled = false;
        bindNeutralFileButton(button);
        bindNeutralFileMenu(item);
    });
    syncNeutralFilesEmptyState();
}

function updateNeutralPackage(packageData) {
    if (!packageData?.available) {
        neutralPackageDownload?.remove();
        neutralPackageDownload = null;
        return;
    }
    const actions = neutralFileList?.querySelector("[data-file-global-menu]");
    if (!actions) return;
    if (!neutralPackageDownload) {
        neutralPackageDownload = document.createElement("a");
        neutralPackageDownload.dataset.recordPackageDownload = "";
        neutralPackageDownload.dataset.recordDownload = "";
        neutralPackageDownload.setAttribute("download", "");
        neutralPackageDownload.setAttribute("role", "menuitem");
        neutralPackageDownload.innerHTML = '<i class="fa-solid fa-box-archive" aria-hidden="true"></i><span>Descargar paquete completo<small></small></span>';
        actions.prepend(neutralPackageDownload);
        protectNeutralDownload(neutralPackageDownload);
    }
    neutralPackageDownload.href = packageData.download_url || "";
    neutralPackageDownload.setAttribute("aria-label", `Descargar paquete completo con ${packageData.file_count} archivos`);
    const small = neutralPackageDownload.querySelector("small");
    if (small) small.textContent = `${packageData.file_count} archivos`;
}

async function submitRecordUpload(event) {
    event.preventDefault();
    if (recordUploadState === "uploading" || !recordUploadForm) return;
    const validEntries = recordUploadEntries.filter((entry) => entry.valid);
    if (!validEntries.length) {
        setRecordUploadState("error", "No hay archivos válidos para agregar.");
        return;
    }
    const data = new FormData();
    recordUploadForm.querySelectorAll('input[type="hidden"]').forEach((input) => data.append(input.name, input.value));
    validEntries.forEach((entry) => data.append("files[]", entry.file, entry.file.name));
    recordUploadResults?.replaceChildren();
    setRecordUploadState("uploading", `Agregando ${validEntries.length} ${validEntries.length === 1 ? "archivo" : "archivos"}…`);
    try {
        const endpoint = recordUploadForm.getAttribute("action");
        if (!endpoint) throw new Error("No se configuró el endpoint de carga.");
        const response = await fetch(endpoint, {
            method: "POST", body: data, credentials: "same-origin",
            headers: { Accept: "application/json", "X-Requested-With": "XMLHttpRequest" }
        });
        const contentType = response.headers.get("content-type") || "";
        const responseText = await response.text();
        if (!contentType.toLowerCase().includes("application/json")) {
            console.error("Carga de archivos: respuesta no JSON", {
                status: response.status, contentType, body: responseText.slice(0, 1000)
            });
            throw new Error(response.redirected || response.status === 401 || response.status === 403
                ? "La sesión ya no es válida. Recarga la página e inicia sesión nuevamente."
                : `El servidor devolvió una respuesta no válida (HTTP ${response.status}).`);
        }
        let result;
        try {
            result = JSON.parse(responseText);
        } catch (parseError) {
            console.error("Carga de archivos: JSON inválido", {
                status: response.status, contentType, body: responseText.slice(0, 1000)
            });
            throw new Error(`El servidor devolvió JSON inválido (HTTP ${response.status}).`);
        }
        const payload = result && typeof result.data === "object" && result.data !== null ? result.data : result;
        const added = Array.isArray(payload.added) ? payload.added : [];
        const failed = Array.isArray(payload.failed) ? payload.failed : [];
        const summary = payload && typeof payload.summary === "object" ? payload.summary : {};
        if (added.length) {
            appendNeutralAddedFiles(added);
            updateNeutralPackage(payload.package);
        }
        [...added.map((file) => `${file.name} — agregado`), ...failed.map((file) => `${file.name} — ${file.message}`)].forEach((message) => {
            const item = document.createElement("li");
            item.textContent = message;
            recordUploadResults?.append(item);
        });
        const state = added.length && failed.length ? "partial" : (added.length ? "success" : "error");
        recordUploadEntries = failed.map((failure) => {
            const match = validEntries.find((entry) => entry.file.name === failure.name);
            return match ? { ...match, valid: false, message: failure.message } : null;
        }).filter(Boolean);
        renderRecordUploadEntries();
        const statusMessage = result.message
            || (response.status === 403 ? "La sesión venció o no tienes permiso para realizar esta acción."
                : response.status === 404 ? "El material o el endpoint de carga no está disponible."
                    : response.status === 422 ? "Revisa los archivos seleccionados."
                        : response.status >= 500 ? "El servidor no pudo completar la carga."
                            : added.length ? "Archivos agregados correctamente." : "No se pudo agregar ningún archivo.");
        setRecordUploadState(state, statusMessage);
        const addedCount = Number(summary.added ?? added.length);
        const failedCount = Number(summary.failed ?? failed.length);
        if (addedCount > 0 && failedCount === 0) {
            recordUploadCloseTimer = window.setTimeout(() => {
                recordUploadCloseTimer = null;
                closeRecordUpload(true);
                showRecordUploadToast(statusMessage);
            }, 800);
        }
    } catch (error) {
        console.error("Carga de archivos:", error);
        setRecordUploadState("error", error instanceof TypeError
            ? "No fue posible conectar con el servidor."
            : (error instanceof Error ? error.message : "No se pudo completar la carga."));
    }
}

recordUploadOpen?.addEventListener("click", openRecordUpload);
recordUploadInput?.addEventListener("change", () => validateRecordUploadFiles([...recordUploadInput.files]));
recordUploadForm?.addEventListener("submit", submitRecordUpload);
recordUploadCancel?.addEventListener("click", () => closeRecordUpload(true));
recordUploadClose?.addEventListener("click", () => closeRecordUpload(true));
recordUploadDialog?.addEventListener("click", (event) => {
    if (event.target === recordUploadDialog) closeRecordUpload();
});
document.addEventListener("keydown", (event) => {
    if (!recordUploadDialog || recordUploadDialog.hidden) return;
    if (event.key === "Escape" && recordUploadState !== "uploading") {
        event.preventDefault();
        closeRecordUpload();
        return;
    }
    if (event.key !== "Tab") return;
    const controls = [...recordUploadDialog.querySelectorAll('button:not([disabled]),input:not([disabled]),[href]')];
    if (!controls.length) return;
    const index = controls.indexOf(document.activeElement);
    const next = event.shiftKey ? (index <= 0 ? controls.length - 1 : index - 1) : (index === controls.length - 1 ? 0 : index + 1);
    event.preventDefault();
    controls[next]?.focus();
});

// Retiro lógico de archivos adicionales.
const fileRemoveDialog = document.querySelector("[data-file-remove-dialog]");
if (fileRemoveDialog && fileRemoveDialog.parentElement !== document.body) document.body.append(fileRemoveDialog);
const fileRemoveConfig = fileRemoveDialog?.querySelector("[data-file-remove-config]");
const fileRemoveTitle = fileRemoveDialog?.querySelector("[data-file-remove-title]");
const fileRemoveDescription = fileRemoveDialog?.querySelector("[data-file-remove-description]");
const fileRemoveName = fileRemoveDialog?.querySelector("[data-file-remove-name]");
const fileRemoveList = fileRemoveDialog?.querySelector("[data-file-remove-list]");
const fileRemoveMore = fileRemoveDialog?.querySelector("[data-file-remove-more]");
const fileRemovePresentationWarning = fileRemoveDialog?.querySelector("[data-file-remove-presentation-warning]");
const fileRemoveReplacement = fileRemoveDialog?.querySelector("[data-file-remove-replacement]");
const fileRemoveReplacementSelect = fileRemoveDialog?.querySelector("[data-file-remove-replacement-select]");
const fileRemoveHistoryNote = fileRemoveDialog?.querySelector("[data-file-remove-history-note] span");
const fileRemoveError = fileRemoveDialog?.querySelector("[data-file-remove-error]");
const fileRemoveCancel = fileRemoveDialog?.querySelector("[data-file-remove-cancel]");
const fileRemoveClose = fileRemoveDialog?.querySelector("[data-file-remove-close]");
const fileRemoveConfirm = fileRemoveDialog?.querySelector("[data-file-remove-confirm]");
const presentationConfirmDialog = document.querySelector("[data-presentation-confirm-dialog]");
if (presentationConfirmDialog && presentationConfirmDialog.parentElement !== document.body) document.body.append(presentationConfirmDialog);
const presentationConfirmTitle = presentationConfirmDialog?.querySelector("[data-presentation-confirm-title]");
const presentationConfirmDescription = presentationConfirmDialog?.querySelector("[data-presentation-confirm-description]");
const presentationEstablishDetails = presentationConfirmDialog?.querySelector("[data-presentation-establish-details]");
const presentationSingleLabel = presentationConfirmDialog?.querySelector("[data-presentation-single-label]");
const presentationChangeDetails = presentationConfirmDialog?.querySelector("[data-presentation-change-details]");
const presentationNewName = presentationConfirmDialog?.querySelector("[data-presentation-new-name]");
const presentationNewMeta = presentationConfirmDialog?.querySelector("[data-presentation-new-meta]");
const presentationCurrentName = presentationConfirmDialog?.querySelector("[data-presentation-current-name]");
const presentationChangeName = presentationConfirmDialog?.querySelector("[data-presentation-change-name]");
const presentationHistoryNote = presentationConfirmDialog?.querySelector("[data-presentation-history-note] span");
const presentationConfirmError = presentationConfirmDialog?.querySelector("[data-presentation-confirm-error]");
const presentationConfirmCancel = presentationConfirmDialog?.querySelector("[data-presentation-confirm-cancel]");
const presentationConfirmClose = presentationConfirmDialog?.querySelector("[data-presentation-confirm-close]");
const presentationConfirmSubmit = presentationConfirmDialog?.querySelector("[data-presentation-confirm-submit]");
const fileReplaceInput = document.querySelector("[data-file-replace-input]");
const fileReplaceDialog = document.querySelector("[data-file-replace-dialog]");
if (fileReplaceDialog && fileReplaceDialog.parentElement !== document.body) document.body.append(fileReplaceDialog);
const fileReplaceCurrent = fileReplaceDialog?.querySelector("[data-file-replace-current]");
const fileReplaceNew = fileReplaceDialog?.querySelector("[data-file-replace-new]");
const fileReplaceMeta = fileReplaceDialog?.querySelector("[data-file-replace-meta]");
const fileReplaceError = fileReplaceDialog?.querySelector("[data-file-replace-error]");
const fileReplaceCancel = fileReplaceDialog?.querySelector("[data-file-replace-cancel]");
const fileReplaceClose = fileReplaceDialog?.querySelector("[data-file-replace-close]");
const fileReplaceConfirm = fileReplaceDialog?.querySelector("[data-file-replace-confirm]");
const fileRestoreOpen = neutralFileList?.querySelector("[data-file-restore-open]");
const fileRestoreDialog = document.querySelector("[data-file-restore-dialog]");
if (fileRestoreDialog && fileRestoreDialog.parentElement !== document.body) document.body.append(fileRestoreDialog);
const fileRestoreInitial = document.querySelector("[data-file-restore-initial]");
const fileRestoreBody = fileRestoreDialog?.querySelector(".ed-file-restore-body");
const fileRestoreList = fileRestoreDialog?.querySelector("[data-file-restore-list]");
const fileRestoreNotice = fileRestoreDialog?.querySelector("[data-file-restore-notice]");
const fileRestoreEmpty = fileRestoreDialog?.querySelector("[data-file-restore-empty]");
const fileRestoreConfirmation = fileRestoreDialog?.querySelector("[data-file-restore-confirmation]");
const fileRestoreConfirmTitle = fileRestoreDialog?.querySelector("[data-file-restore-confirm-title]");
const fileRestoreConfirmMessage = fileRestoreDialog?.querySelector("[data-file-restore-confirm-message]");
const fileRestoreOriginal = fileRestoreDialog?.querySelector("[data-file-restore-original]");
const fileRestoreConflictRow = fileRestoreDialog?.querySelector("[data-file-restore-conflict-row]");
const fileRestoreConflict = fileRestoreDialog?.querySelector("[data-file-restore-conflict]");
const fileRestoreFinal = fileRestoreDialog?.querySelector("[data-file-restore-final]");
const filePurgeSummary = fileRestoreDialog?.querySelector("[data-file-purge-summary]");
const fileRestoreError = fileRestoreDialog?.querySelector("[data-file-restore-error]");
const fileRestoreClose = fileRestoreDialog?.querySelector("[data-file-restore-close]");
const fileRestoreCancel = fileRestoreDialog?.querySelector("[data-file-restore-cancel]");
const fileRestoreBack = fileRestoreDialog?.querySelector("[data-file-restore-back]");
const filePurgeOpen = fileRestoreDialog?.querySelector("[data-file-purge-open]");
const fileRestoreConfirm = fileRestoreDialog?.querySelector("[data-file-restore-confirm]");
const fileSelectionToggle = neutralFileList?.querySelector("[data-file-selection-toggle]");
const fileSelectionBar = neutralFileList?.querySelector("[data-file-selection-bar]");
const fileSelectionCount = neutralFileList?.querySelector("[data-file-selection-count]");
const fileSelectionCancel = neutralFileList?.querySelector("[data-file-selection-cancel]");
const fileSelectionRemove = neutralFileList?.querySelector("[data-file-selection-remove]");
const globalFileActions = neutralFileList?.querySelector("[data-file-global-actions]");
const globalFileToggle = neutralFileList?.querySelector("[data-file-global-toggle]");
const globalFileMenu = neutralFileList?.querySelector("[data-file-global-menu]");
let openNeutralFileMenu = null;
let fileRemoveTargets = new Map();
let fileRemoveIsBulk = false;
let fileRemoveReturnFocus = null;
let fileRemoveSubmitting = false;
let fileRemoveBackgroundState = [];
let presentationConfirmTarget = null;
let presentationConfirmOrigin = null;
let presentationConfirmMode = "";
let presentationConfirmSubmitting = false;
let presentationConfirmBackgroundState = [];
let fileReplaceTarget = null;
let fileReplaceOrigin = null;
let fileReplaceSelection = null;
let fileReplaceSubmitting = false;
let fileReplaceBackgroundState = [];
let restorableFiles = [];
let fileRestoreInspection = null;
let fileRestoreSubmitting = false;
let fileRestoreInspecting = false;
let fileRestoreMode = "list";
let selectedRestorableFileIds = new Set();
let fileRestoreListScrollTop = 0;
let fileRestoreReturnFocus = null;
let fileRestoreBackgroundState = [];
let fileSelectionMode = false;
const selectedFileIds = new Set();
try {
    restorableFiles = JSON.parse(fileRestoreInitial?.textContent || "[]");
    if (!Array.isArray(restorableFiles)) restorableFiles = [];
} catch {
    restorableFiles = [];
}
updateRestoreEntryPoint();

function closeNeutralFileMenu(restoreFocus = false) {
    if (!openNeutralFileMenu) return;
    const toggle = openNeutralFileMenu.querySelector("[data-file-menu-toggle]");
    const menu = openNeutralFileMenu.querySelector("[data-file-menu]");
    if (menu) menu.hidden = true;
    menu?.classList.remove("opens-up");
    toggle?.setAttribute("aria-expanded", "false");
    openNeutralFileMenu = null;
    if (restoreFocus) toggle?.focus();
}

function closeGlobalFileMenu(restoreFocus = false) {
    if (!globalFileMenu || globalFileMenu.hidden) return;
    globalFileMenu.hidden = true;
    globalFileMenu.classList.remove("opens-up");
    globalFileToggle?.setAttribute("aria-expanded", "false");
    if (restoreFocus) globalFileToggle?.focus();
}

function positionFileMenu(menu, anchor, boundary = neutralFileList) {
    if (!menu || !anchor) return;
    menu.classList.remove("opens-up");
    const menuRect = menu.getBoundingClientRect();
    const anchorRect = anchor.getBoundingClientRect();
    const boundaryRect = boundary?.getBoundingClientRect();
    const lowerLimit = Math.min(window.innerHeight - 8, boundaryRect?.bottom ?? window.innerHeight - 8);
    const upperLimit = Math.max(8, boundaryRect?.top ?? 8);
    if (menuRect.bottom > lowerLimit && anchorRect.top - menuRect.height - 7 >= upperLimit) {
        menu.classList.add("opens-up");
    }
}

function openGlobalFileMenu() {
    if (!globalFileMenu || !globalFileToggle || fileSelectionMode) return;
    closeNeutralFileMenu();
    globalFileMenu.hidden = false;
    globalFileToggle.setAttribute("aria-expanded", "true");
    positionFileMenu(globalFileMenu, globalFileToggle);
    globalFileMenu.querySelector('[role="menuitem"]:not([disabled])')?.focus();
}

function currentPresentationButton() {
    return neutralFileButtons.find((button) => button.dataset.filePresentation === "true") || null;
}

function closePresentationConfirmDialog(success = false) {
    if (!presentationConfirmDialog || presentationConfirmDialog.hidden || presentationConfirmSubmitting) return;
    presentationConfirmDialog.hidden = true;
    presentationConfirmBackgroundState.forEach(({ element, inert, ariaHidden }) => {
        element.inert = inert;
        if (ariaHidden === null) element.removeAttribute("aria-hidden");
        else element.setAttribute("aria-hidden", ariaHidden);
    });
    presentationConfirmBackgroundState = [];
    document.documentElement.classList.remove("file-remove-open");
    document.body.classList.remove("file-remove-open");
    if (presentationConfirmOrigin?.isConnected) presentationConfirmOrigin.focus();
    presentationConfirmTarget = null;
    presentationConfirmOrigin = null;
    presentationConfirmMode = "";
    if (presentationConfirmError) {
        presentationConfirmError.textContent = "";
        presentationConfirmError.hidden = true;
    }
}

function openPresentationConfirmDialog(target, origin) {
    if (!presentationConfirmDialog || !target || presentationConfirmSubmitting) return;
    const current = currentPresentationButton();
    const removing = target.dataset.filePresentation === "true";
    presentationConfirmMode = removing ? "remove" : (current ? "change" : "establish");
    presentationConfirmTarget = target;
    presentationConfirmOrigin = origin;
    closeNeutralFileMenu();
    if (presentationConfirmError) {
        presentationConfirmError.textContent = "";
        presentationConfirmError.hidden = true;
    }
    if (presentationConfirmMode === "remove") {
        presentationConfirmTitle.textContent = "Quitar archivo de presentación";
        presentationConfirmDescription.textContent = "El expediente dejará de mostrar automáticamente un archivo al ingresar. Los documentos continuarán disponibles y podrán abrirse manualmente desde la lista.";
        presentationConfirmSubmit.textContent = "Quitar presentación";
        if (presentationHistoryNote) presentationHistoryNote.textContent = "La acción de quitar la presentación actual quedará registrada en el historial del expediente y podrá reflejarse en los reportes administrativos.";
    } else if (presentationConfirmMode === "change") {
        presentationConfirmTitle.textContent = "Cambiar archivo de presentación";
        presentationConfirmDescription.textContent = "Este archivo reemplazará al archivo de presentación actual como vista inicial del expediente. El archivo anterior permanecerá disponible dentro de ‘Archivos del material’.";
        presentationConfirmSubmit.textContent = "Cambiar presentación";
        if (presentationHistoryNote) presentationHistoryNote.textContent = "El cambio de la presentación actual quedará registrado en el historial del expediente y podrá reflejarse en los reportes administrativos.";
    } else {
        presentationConfirmTitle.textContent = "Establecer archivo de presentación";
        presentationConfirmDescription.textContent = "Este archivo se mostrará automáticamente cuando una persona ingrese al Expediente Digital. Esta elección no cambia la importancia de los demás archivos.";
        presentationConfirmSubmit.textContent = "Establecer como presentación";
        if (presentationHistoryNote) presentationHistoryNote.textContent = "La selección de este archivo como presentación quedará registrada en el historial del expediente y podrá reflejarse en los reportes administrativos.";
    }
    const showChange = presentationConfirmMode === "change";
    presentationEstablishDetails.hidden = showChange;
    presentationChangeDetails.hidden = !showChange;
    if (presentationSingleLabel) {
        presentationSingleLabel.textContent = presentationConfirmMode === "remove"
            ? "Presentación actual"
            : "Nueva presentación";
    }
    if (showChange) {
        presentationCurrentName.textContent = current?.dataset.fileName || "Archivo actual";
        presentationChangeName.textContent = target.dataset.fileName || "Archivo";
    } else {
        presentationNewName.textContent = target.dataset.fileName || "Archivo";
        presentationNewMeta.textContent = [target.dataset.fileType, target.dataset.fileSize].filter(Boolean).join(" · ");
    }
    presentationConfirmBackgroundState = [...document.body.children]
        .filter((element) => element !== presentationConfirmDialog)
        .map((element) => ({ element, inert: element.inert, ariaHidden: element.getAttribute("aria-hidden") }));
    presentationConfirmBackgroundState.forEach(({ element }) => {
        element.inert = true;
        element.setAttribute("aria-hidden", "true");
    });
    document.documentElement.classList.add("file-remove-open");
    document.body.classList.add("file-remove-open");
    presentationConfirmDialog.hidden = false;
    window.requestAnimationFrame(() => presentationConfirmCancel?.focus());
}

async function submitPresentationChange() {
    if (presentationConfirmSubmitting || !presentationConfirmTarget || !fileRemoveConfig) return;
    const target = presentationConfirmTarget;
    const mode = presentationConfirmMode;
    presentationConfirmSubmitting = true;
    presentationConfirmSubmit.disabled = true;
    presentationConfirmCancel.disabled = true;
    presentationConfirmClose.disabled = true;
    presentationConfirmSubmit.textContent = mode === "remove" ? "Quitando…" : "Guardando…";
    try {
        const data = new FormData();
        data.set("_csrf", fileRemoveConfig.dataset.csrf || "");
        data.set("material_id", fileRemoveConfig.dataset.materialId || "");
        data.set("file_id", target.dataset.fileId || "");
        data.set("action", mode === "remove" ? "unpresentation" : "presentation");
        const response = await fetch(fileRemoveConfig.dataset.endpoint || "", {
            method: "POST", body: data, credentials: "same-origin",
            headers: { Accept: "application/json", "X-Requested-With": "XMLHttpRequest" }
        });
        const result = await response.json();
        if (!response.ok || !result?.success) throw new Error(result?.message || "No fue posible actualizar el archivo de presentación.");
        presentationConfirmSubmitting = false;
        closePresentationConfirmDialog(true);
        syncPresentationFile(mode === "remove" ? null : target);
        const message = mode === "remove"
            ? "Archivo de presentación eliminado."
            : (mode === "change"
                ? "Archivo de presentación actualizado correctamente."
                : "Archivo de presentación establecido correctamente.");
        showRecordUploadToast(message, "success");
    } catch (error) {
        const message = error instanceof Error ? error.message : "No fue posible actualizar el archivo de presentación.";
        presentationConfirmError.textContent = message;
        presentationConfirmError.hidden = false;
        showRecordUploadToast(message, "error");
    } finally {
        presentationConfirmSubmitting = false;
        presentationConfirmSubmit.disabled = false;
        presentationConfirmCancel.disabled = false;
        presentationConfirmClose.disabled = false;
        presentationConfirmSubmit.textContent = mode === "remove"
            ? "Quitar presentación"
            : (mode === "change" ? "Cambiar presentación" : "Establecer como presentación");
    }
}

function bindFilePresentationAction(action, item) {
    if (!action || action.dataset.bound === "true") return;
    action.dataset.bound = "true";
    action.addEventListener("click", () => {
        const button = item.querySelector("[data-record-file]");
        if (!button || action.disabled) return;
        openPresentationConfirmDialog(button, item.querySelector("[data-file-menu-toggle]") || action);
    });
}

function closeFileReplaceDialog(success = false) {
    if (!fileReplaceDialog || fileReplaceDialog.hidden || fileReplaceSubmitting) return;
    fileReplaceDialog.hidden = true;
    fileReplaceBackgroundState.forEach(({ element, inert, ariaHidden }) => {
        element.inert = inert;
        if (ariaHidden === null) element.removeAttribute("aria-hidden");
        else element.setAttribute("aria-hidden", ariaHidden);
    });
    fileReplaceBackgroundState = [];
    document.documentElement.classList.remove("file-remove-open");
    document.body.classList.remove("file-remove-open");
    if (fileReplaceOrigin?.isConnected) fileReplaceOrigin.focus();
    fileReplaceTarget = null;
    fileReplaceOrigin = null;
    fileReplaceSelection = null;
    if (fileReplaceInput) {
        fileReplaceInput.value = "";
        delete fileReplaceInput.dataset.fileId;
    }
    if (fileReplaceError) {
        fileReplaceError.textContent = "";
        fileReplaceError.hidden = true;
    }
}

function openFileReplacePicker(item, origin) {
    const button = item?.querySelector("[data-record-file]");
    if (!button || !fileReplaceInput || fileReplaceSubmitting) return;
    fileReplaceTarget = button;
    fileReplaceOrigin = item.querySelector("[data-file-menu-toggle]") || origin;
    fileReplaceSelection = null;
    fileReplaceInput.value = "";
    fileReplaceInput.dataset.fileId = button.dataset.fileId || "";
    closeNeutralFileMenu();
    fileReplaceInput.click();
}

function validateReplacementSelection(file) {
    if (!(file instanceof File) || !fileReplaceDialog) return "Selecciona un archivo.";
    const maxBytes = Number(fileReplaceDialog.dataset.maxBytes || 26214400);
    const maxName = Number(fileReplaceDialog.dataset.maxName || 200);
    const extensions = String(fileReplaceDialog.dataset.extensions || "")
        .split(",").map((value) => value.trim().toLowerCase()).filter(Boolean);
    const extension = file.name.includes(".") ? file.name.split(".").pop().toLowerCase() : "";
    if (!extension || !extensions.includes(extension)) return "El formato del archivo no está permitido.";
    if (file.name.length > maxName) return `El nombre supera el límite de ${maxName} caracteres.`;
    if (file.size < 1 || file.size > maxBytes) return "El archivo está vacío o supera el límite de 25 MB.";
    if (fileReplaceTarget?.dataset.filePresentation === "true"
        && !["pdf", "docx", "png", "jpg", "jpeg", "webp", "txt"].includes(extension)) {
        return "La presentación solo puede reemplazarse por un archivo compatible con la vista previa.";
    }
    return "";
}

function openFileReplaceConfirmation(file) {
    if (!fileReplaceDialog || !fileReplaceTarget) return;
    if (!fileReplaceInput?.dataset.fileId
        || fileReplaceInput.dataset.fileId !== fileReplaceTarget.dataset.fileId) {
        throw new Error("No fue posible identificar el archivo que se desea reemplazar.");
    }
    const validation = validateReplacementSelection(file);
    if (validation) {
        showRecordUploadToast(validation, "error");
        fileReplaceSelection = null;
        fileReplaceInput.value = "";
        fileReplaceOrigin?.focus();
        return;
    }
    fileReplaceSelection = file;
    fileReplaceCurrent.textContent = fileReplaceTarget.dataset.fileName || "Archivo actual";
    fileReplaceNew.textContent = file.name;
    fileReplaceMeta.textContent = `${getNeutralFileVisualType(file.name.split(".").pop()).label} · ${recordUploadSize(file.size)}`;
    if (fileReplaceError) {
        fileReplaceError.textContent = "";
        fileReplaceError.hidden = true;
    }
    fileReplaceBackgroundState = [...document.body.children]
        .filter((element) => element !== fileReplaceDialog)
        .map((element) => ({ element, inert: element.inert, ariaHidden: element.getAttribute("aria-hidden") }));
    fileReplaceBackgroundState.forEach(({ element }) => {
        element.inert = true;
        element.setAttribute("aria-hidden", "true");
    });
    document.documentElement.classList.add("file-remove-open");
    document.body.classList.add("file-remove-open");
    fileReplaceDialog.hidden = false;
    window.requestAnimationFrame(() => fileReplaceCancel?.focus());
}

function applyFileReplacement(file) {
    if (!fileReplaceTarget || !file) return;
    const button = fileReplaceTarget;
    const item = button.closest("[data-record-file-item]");
    const oldGroup = item?.closest("[data-file-group]");
    const visual = getNeutralFileVisualType(file.extension);
    const wasSelected = button.getAttribute("aria-selected") === "true";
    Object.assign(button.dataset, {
        fileName: file.name,
        fileType: visual.label,
        fileSize: file.size_label,
        fileExtension: visual.extension,
        previewSupported: String(Boolean(file.preview_supported)),
        previewType: file.preview_type || "unsupported",
        previewUrl: file.preview_url || "",
        zipUrl: file.zip_url || "",
        downloadUrl: file.download_url || ""
    });
    button.setAttribute("aria-label", `${file.name}, ${visual.label}, ${file.size_label}`);
    const icon = button.querySelector(":scope > i");
    if (icon) icon.className = `fa-solid ${visual.icon}`;
    const name = button.querySelector(":scope > span > strong");
    const meta = button.querySelector(":scope > span > small");
    if (name) {
        name.textContent = file.name;
        name.title = file.name;
    }
    if (meta) meta.textContent = `${visual.label} · ${file.size_label}`;
    const checkbox = item?.querySelector("[data-file-select]");
    if (checkbox) checkbox.setAttribute("aria-label", `Seleccionar ${file.name}`);
    const toggle = item?.querySelector("[data-file-menu-toggle]");
    if (toggle) toggle.setAttribute("aria-label", `Acciones de ${file.name}`);
    const download = item?.querySelector("[data-file-download-action]");
    if (download) download.href = file.download_url || "";
    const menu = item?.querySelector("[data-file-menu]");
    menu?.querySelector("[data-file-presentation-action]")?.remove();
    if (file.preview_supported && visual.extension !== "zip" && menu) {
        const presentationAction = createPresentationAction(button.dataset.filePresentation === "true");
        menu.insertBefore(presentationAction, menu.querySelector("[data-file-remove-action]"));
        bindFilePresentationAction(presentationAction, item);
    }
    const destination = ensureNeutralFileGroup(
        button.dataset.filePresentation === "true"
            ? "presentation"
            : (visual.extension === "zip" ? "archives" : "additional")
    );
    if (destination && item) destination.append(item);
    if (oldGroup && !oldGroup.querySelector("[data-record-file]")) oldGroup.remove();
    if (wasSelected || button.dataset.filePresentation === "true") selectNeutralFile(button);
}

async function submitFileReplacement() {
    if (fileReplaceSubmitting || !fileReplaceTarget || !fileReplaceSelection || !fileRemoveConfig) return;
    const target = fileReplaceTarget;
    const selected = fileReplaceSelection;
    fileReplaceSubmitting = true;
    fileReplaceConfirm.disabled = true;
    fileReplaceCancel.disabled = true;
    fileReplaceClose.disabled = true;
    fileReplaceConfirm.textContent = "Reemplazando…";
    try {
        const data = new FormData();
        data.set("_csrf", fileRemoveConfig.dataset.csrf || "");
        data.set("material_id", fileRemoveConfig.dataset.materialId || "");
        data.set("file_id", target.dataset.fileId || "");
        data.set("action", "replace");
        data.set("file", selected);
        const response = await fetch(fileRemoveConfig.dataset.endpoint || "", {
            method: "POST", body: data, credentials: "same-origin",
            headers: { Accept: "application/json", "X-Requested-With": "XMLHttpRequest" }
        });
        const result = await response.json();
        if (!response.ok || !result?.success) throw new Error(result?.message || "No fue posible reemplazar el archivo.");
        const payload = result?.data && typeof result.data === "object" ? result.data : {};
        fileReplaceSubmitting = false;
        applyFileReplacement(payload.file);
        updateNeutralPackage(payload.package);
        documentEvolutionNeedsRefresh = true;
        closeFileReplaceDialog(true);
        showRecordUploadToast(result.message || "Archivo reemplazado correctamente.", "success");
    } catch (error) {
        const message = error instanceof Error ? error.message : "No fue posible reemplazar el archivo.";
        fileReplaceError.textContent = message;
        fileReplaceError.hidden = false;
        showRecordUploadToast(message, "error");
    } finally {
        fileReplaceSubmitting = false;
        fileReplaceConfirm.disabled = false;
        fileReplaceCancel.disabled = false;
        fileReplaceClose.disabled = false;
        fileReplaceConfirm.textContent = "Reemplazar archivo";
    }
}

function updateRestoreEntryPoint() {
    const count = restorableFiles.length;
    if (fileRestoreOpen) fileRestoreOpen.hidden = count === 0;
}

function renderRestorableFiles() {
    if (!fileRestoreList) return;
    fileRestoreList.replaceChildren();
    restorableFiles.forEach((file) => {
        const visual = getNeutralFileVisualType(file.extension);
        const item = document.createElement("article");
        item.className = "ed-file-restore-item";
        item.dataset.restoreFileId = String(file.id);
        const checkbox = document.createElement("input");
        checkbox.type = "checkbox";
        checkbox.className = "ed-file-restore-select";
        checkbox.dataset.filePurgeSelect = "";
        checkbox.value = String(file.id);
        checkbox.checked = selectedRestorableFileIds.has(String(file.id));
        checkbox.setAttribute("aria-label", `Seleccionar ${file.name} para eliminación definitiva`);
        const icon = document.createElement("i");
        icon.className = `fa-solid ${visual.icon}`;
        icon.setAttribute("aria-hidden", "true");
        const details = document.createElement("div");
        const name = document.createElement("strong");
        const metadata = document.createElement("small");
        const removal = document.createElement("small");
        const remaining = document.createElement("small");
        name.textContent = file.name;
        name.title = file.name;
        metadata.textContent = `${visual.label} · ${file.size}`;
        removal.textContent = `Retirado: ${file.deleted_at_label} · ${file.deleted_by_name}`;
        remaining.className = "ed-file-restore-remaining";
        remaining.textContent = `Tiempo restante: ${file.remaining_label}`;
        details.append(name, metadata, removal, remaining);
        const restore = document.createElement("button");
        restore.type = "button";
        restore.dataset.fileRestoreInspect = "";
        restore.textContent = "Restaurar";
        item.append(checkbox, icon, details, restore);
        fileRestoreList.append(item);
    });
    if (fileRestoreEmpty) fileRestoreEmpty.hidden = restorableFiles.length > 0;
    selectedRestorableFileIds = new Set(
        [...selectedRestorableFileIds].filter((id) => restorableFiles.some((file) => String(file.id) === id))
    );
    if (filePurgeOpen) filePurgeOpen.disabled = selectedRestorableFileIds.size === 0;
    updateRestoreEntryPoint();
}

async function requestRestoreAction(action, fileId = "", extra = {}) {
    if (!fileRemoveConfig) throw new Error("No se configuró el endpoint de archivos.");
    const data = new FormData();
    data.set("_csrf", fileRemoveConfig.dataset.csrf || "");
    data.set("material_id", fileRemoveConfig.dataset.materialId || "");
    data.set("action", action);
    if (fileId) data.set("file_id", fileId);
    Object.entries(extra).forEach(([key, value]) => {
        if (Array.isArray(value)) value.forEach((item) => data.append(`${key}[]`, String(item)));
        else data.set(key, String(value));
    });
    const response = await fetch(fileRemoveConfig.dataset.endpoint || "", {
        method: "POST", body: data, credentials: "same-origin",
        headers: { Accept: "application/json", "X-Requested-With": "XMLHttpRequest" }
    });
    const result = await response.json();
    if (!response.ok || !result?.success) {
        throw new Error(result?.message || "No fue posible completar la restauración.");
    }
    return result;
}

async function refreshRestorableFiles() {
    const result = await requestRestoreAction("list_restorable");
    const payload = result?.data && typeof result.data === "object" ? result.data : {};
    restorableFiles = Array.isArray(payload.files) ? payload.files : [];
    renderRestorableFiles();
    return restorableFiles;
}

function showRestoreList() {
    const returningFromPurge = fileRestoreMode === "purge";
    fileRestoreInspection = null;
    fileRestoreMode = "list";
    fileRestoreDialog?.classList.remove("is-purge");
    fileRestoreConfirmation?.classList.remove("is-purge");
    if (fileRestoreConfirmation) fileRestoreConfirmation.hidden = true;
    const restoreDetails = fileRestoreConfirmation?.querySelector("dl");
    if (restoreDetails) restoreDetails.hidden = false;
    if (fileRestoreNotice) fileRestoreNotice.hidden = false;
    if (fileRestoreList) fileRestoreList.hidden = false;
    if (fileRestoreEmpty) fileRestoreEmpty.hidden = restorableFiles.length > 0;
    if (fileRestoreBack) fileRestoreBack.hidden = true;
    if (filePurgeOpen) {
        filePurgeOpen.hidden = false;
        filePurgeOpen.disabled = selectedRestorableFileIds.size === 0;
    }
    if (fileRestoreConfirm) fileRestoreConfirm.hidden = true;
    if (fileRestoreCancel) fileRestoreCancel.textContent = "Cerrar";
    if (fileRestoreError) {
        fileRestoreError.textContent = "";
        fileRestoreError.hidden = true;
    }
    if (filePurgeSummary) {
        filePurgeSummary.hidden = true;
        filePurgeSummary.replaceChildren();
    }
    if (returningFromPurge && fileRestoreBody) {
        window.requestAnimationFrame(() => fileRestoreBody.scrollTo({
            top: fileRestoreListScrollTop,
            behavior: "smooth"
        }));
    }
}

async function openFileRestoreDialog() {
    if (!fileRestoreDialog || fileRestoreSubmitting) return;
    closeGlobalFileMenu();
    fileRestoreReturnFocus = globalFileToggle;
    try {
        await refreshRestorableFiles();
        if (!restorableFiles.length) {
            showRecordUploadToast("No existen archivos restaurables en este momento.", "info");
            return;
        }
    } catch (error) {
        console.error("Consulta de archivos restaurables:", error);
        showRecordUploadToast(error instanceof Error ? error.message : "No fue posible consultar los archivos retirados.", "error");
        return;
    }
    showRestoreList();
    fileRestoreBackgroundState = [...document.body.children]
        .filter((element) => element !== fileRestoreDialog)
        .map((element) => ({ element, inert: element.inert, ariaHidden: element.getAttribute("aria-hidden") }));
    fileRestoreBackgroundState.forEach(({ element }) => {
        element.inert = true;
        element.setAttribute("aria-hidden", "true");
    });
    document.documentElement.classList.add("file-remove-open");
    document.body.classList.add("file-remove-open");
    fileRestoreDialog.hidden = false;
    window.requestAnimationFrame(() => fileRestoreClose?.focus());
}

function closeFileRestoreDialog() {
    if (!fileRestoreDialog || fileRestoreDialog.hidden || fileRestoreSubmitting) return;
    fileRestoreDialog.hidden = true;
    fileRestoreBackgroundState.forEach(({ element, inert, ariaHidden }) => {
        element.inert = inert;
        if (ariaHidden === null) element.removeAttribute("aria-hidden");
        else element.setAttribute("aria-hidden", ariaHidden);
    });
    fileRestoreBackgroundState = [];
    document.documentElement.classList.remove("file-remove-open");
    document.body.classList.remove("file-remove-open");
    fileRestoreReturnFocus?.focus();
    fileRestoreReturnFocus = null;
    showRestoreList();
}

async function inspectFileRestore(fileId) {
    if (fileRestoreSubmitting || fileRestoreInspecting) return;
    fileRestoreInspecting = true;
    try {
        const result = await requestRestoreAction("inspect_restore", fileId);
        const inspection = result?.data && typeof result.data === "object" ? result.data : {};
        fileRestoreInspection = inspection;
        fileRestoreMode = "restore";
        fileRestoreList.hidden = true;
        fileRestoreEmpty.hidden = true;
        fileRestoreConfirmation.hidden = false;
        fileRestoreConfirmTitle.textContent = inspection.conflict
            ? "Restaurar con un nombre diferente"
            : "Confirmar restauración";
        fileRestoreConfirmMessage.textContent = inspection.conflict
            ? "Existe un archivo activo con el mismo nombre pero contenido diferente. La restauración utilizará un nombre disponible y no sobrescribirá ningún archivo."
            : "El archivo volverá a estar disponible dentro del Expediente Digital.";
        fileRestoreOriginal.textContent = inspection.original_name || "Archivo";
        fileRestoreConflictRow.hidden = !inspection.conflict;
        fileRestoreConflict.textContent = inspection.conflicting_name || "";
        fileRestoreFinal.closest("[data-file-restore-final-row]").hidden = !inspection.conflict;
        fileRestoreFinal.textContent = inspection.final_name || inspection.original_name || "Archivo";
        fileRestoreBack.hidden = false;
        filePurgeOpen.hidden = true;
        fileRestoreCancel.textContent = "Cancelar";
        fileRestoreConfirm.hidden = false;
        fileRestoreConfirm.textContent = "Restaurar archivo";
        fileRestoreConfirm.focus();
    } catch (error) {
        console.error("Inspección de restauración:", error);
        const message = error instanceof Error ? error.message : "No fue posible validar el archivo.";
        showRecordUploadToast(message, "error", {
            freshAttempt: message === "Este archivo ya se encuentra disponible dentro del material."
        });
        await refreshRestorableFiles().catch(() => {});
    } finally {
        fileRestoreInspecting = false;
    }
}

function confirmPermanentFileDeletion() {
    const selected = restorableFiles.filter((file) => selectedRestorableFileIds.has(String(file.id)));
    if (!selected.length || fileRestoreSubmitting) return;
    fileRestoreMode = "purge";
    fileRestoreListScrollTop = fileRestoreBody?.scrollTop || 0;
    fileRestoreDialog.classList.add("is-purge");
    fileRestoreInspection = null;
    fileRestoreNotice.hidden = true;
    fileRestoreList.hidden = true;
    fileRestoreEmpty.hidden = true;
    fileRestoreConfirmation.hidden = false;
    fileRestoreConfirmation.classList.add("is-purge");
    fileRestoreConfirmTitle.textContent = selected.length === 1
        ? "Eliminar archivo definitivamente"
        : `Eliminar ${selected.length} archivos definitivamente`;
    fileRestoreConfirmMessage.textContent = "Los archivos dejarán de existir físicamente y ya no podrán restaurarse. Únicamente permanecerá la evidencia en el historial y la auditoría.";
    fileRestoreConfirmation.querySelector("dl").hidden = true;
    filePurgeSummary.replaceChildren(...selected.map((file) => {
        const item = document.createElement("li");
        item.textContent = file.name;
        return item;
    }));
    filePurgeSummary.hidden = false;
    fileRestoreBack.hidden = false;
    filePurgeOpen.hidden = true;
    fileRestoreCancel.textContent = "Cancelar";
    fileRestoreConfirm.hidden = false;
    fileRestoreConfirm.textContent = selected.length === 1 ? "Eliminar definitivamente" : "Eliminar seleccionados";
    window.requestAnimationFrame(() => {
        fileRestoreConfirmation.scrollIntoView({ behavior: "smooth", block: "start" });
        fileRestoreConfirm.focus({ preventScroll: true });
    });
}

function insertRestoredFile(file) {
    appendNeutralAddedFiles([file]);
    const button = neutralFileButtons.find((candidate) => String(candidate.dataset.fileId) === String(file.id));
    const item = button?.closest("[data-record-file-item]");
    if (!button || !item) return;
    button.dataset.fileSortOrder = String(file.sort_order ?? Number.MAX_SAFE_INTEGER);
    const list = item.parentElement;
    const next = [...(list?.querySelectorAll("[data-record-file-item]") || [])].find((candidate) => {
        if (candidate === item) return false;
        const candidateButton = candidate.querySelector("[data-record-file]");
        return Number(candidateButton?.dataset.fileSortOrder ?? Number.MAX_SAFE_INTEGER)
            > Number(button.dataset.fileSortOrder);
    });
    if (next) list.insertBefore(item, next);
    const tree = list?.querySelector(`[data-zip-tree][data-zip-file-id="${String(file.id)}"]`);
    if (tree) item.after(tree);
}

async function submitFileRestore() {
    if (fileRestoreSubmitting || (fileRestoreMode === "restore" && !fileRestoreInspection)
        || (fileRestoreMode === "purge" && !selectedRestorableFileIds.size)) return;
    const purging = fileRestoreMode === "purge";
    fileRestoreSubmitting = true;
    fileRestoreConfirm.disabled = true;
    fileRestoreBack.disabled = true;
    fileRestoreCancel.disabled = true;
    fileRestoreClose.disabled = true;
    fileRestoreConfirm.textContent = purging ? "Eliminando…" : "Restaurando…";
    if (fileRestoreError) fileRestoreError.hidden = true;
    try {
        const purgedIds = purging ? [...selectedRestorableFileIds] : [];
        const result = purging
            ? await requestRestoreAction("purge_restorable", "", { file_ids: purgedIds })
            : await requestRestoreAction(
                "restore",
                String(fileRestoreInspection.file_id || ""),
                { final_name: fileRestoreInspection.final_name || "" }
            );
        const payload = result?.data && typeof result.data === "object" ? result.data : {};
        if (!purging) {
            insertRestoredFile(payload.file);
            updateNeutralPackage(payload.package);
        }
        const completedIds = new Set(
            purging ? (payload.purged_file_ids || []).map(String) : [String(fileRestoreInspection.file_id)]
        );
        restorableFiles = restorableFiles.filter((file) => !completedIds.has(String(file.id)));
        if (purging) selectedRestorableFileIds.clear();
        renderRestorableFiles();
        showRestoreList();
        showRecordUploadToast(
            result.message || (purging ? "Archivos eliminados definitivamente." : "Archivo restaurado correctamente."),
            "success"
        );
        if (!restorableFiles.length) {
            fileRestoreSubmitting = false;
            closeFileRestoreDialog();
        }
    } catch (error) {
        const message = error instanceof Error
            ? error.message
            : (purging ? "No fue posible eliminar definitivamente los archivos." : "No fue posible restaurar el archivo.");
        console.error(purging ? "Eliminación definitiva de archivos:" : "Restauración de archivo:", error);
        fileRestoreError.textContent = message;
        fileRestoreError.hidden = false;
        showRecordUploadToast(message, "error");
    } finally {
        fileRestoreSubmitting = false;
        fileRestoreConfirm.disabled = false;
        fileRestoreBack.disabled = false;
        fileRestoreCancel.disabled = false;
        fileRestoreClose.disabled = false;
        fileRestoreConfirm.textContent = purging ? "Eliminar definitivamente" : "Restaurar archivo";
    }
}

function bindNeutralFileMenu(item) {
    if (!(item instanceof HTMLElement) || item.dataset.fileMenuBound === "true") return;
    item.dataset.fileMenuBound = "true";
    const checkbox = item.querySelector("[data-file-select]");
    const toggle = item.querySelector("[data-file-menu-toggle]");
    const menu = item.querySelector("[data-file-menu]");
    const action = item.querySelector("[data-file-remove-action]");
    const replaceAction = item.querySelector("[data-file-replace-action]");
    const presentationAction = item.querySelector("[data-file-presentation-action]");
    checkbox?.addEventListener("click", (event) => event.stopPropagation());
    checkbox?.addEventListener("change", () => updateFileSelectionFromCheckbox(checkbox));
    if (!toggle || !menu || !action) return;
    const openMenu = () => {
        closeGlobalFileMenu();
        if (openNeutralFileMenu !== item) closeNeutralFileMenu();
        const opening = menu.hidden;
        menu.hidden = !opening;
        toggle.setAttribute("aria-expanded", String(opening));
        openNeutralFileMenu = opening ? item : null;
        if (opening) {
            positionFileMenu(menu, toggle);
            menu.querySelector('[role="menuitem"]:not([disabled])')?.focus();
        } else {
            menu.classList.remove("opens-up");
        }
    };
    toggle.addEventListener("click", openMenu);
    toggle.addEventListener("keydown", (event) => {
        if (event.key === "ArrowDown" || event.key === "Enter" || event.key === " ") {
            event.preventDefault();
            openMenu();
        }
    });
    menu.addEventListener("keydown", (event) => {
        const items = [...menu.querySelectorAll('[role="menuitem"]:not([disabled])')];
        const index = items.indexOf(document.activeElement);
        if (event.key === "Escape") {
            event.preventDefault();
            closeNeutralFileMenu(true);
        } else if (["ArrowDown", "ArrowUp", "Home", "End"].includes(event.key) && items.length) {
            event.preventDefault();
            const next = event.key === "Home" ? 0
                : event.key === "End" ? items.length - 1
                    : event.key === "ArrowUp" ? (index <= 0 ? items.length - 1 : index - 1)
                        : (index >= items.length - 1 ? 0 : index + 1);
            items[next]?.focus();
        }
    });
    item.addEventListener("focusout", (event) => {
        if (openNeutralFileMenu === item && !item.contains(event.relatedTarget)) closeNeutralFileMenu();
    });
    bindFilePresentationAction(presentationAction, item);
    replaceAction?.addEventListener("click", () => openFileReplacePicker(item, replaceAction));
    action.addEventListener("click", () => openFileRemoveDialog(item, action));
}

function updateFileSelectionControls() {
    const count = selectedFileIds.size;
    if (fileSelectionCount) fileSelectionCount.textContent = `${count} ${count === 1 ? "archivo seleccionado" : "archivos seleccionados"}`;
    if (fileSelectionRemove) {
        fileSelectionRemove.disabled = count === 0;
        fileSelectionRemove.textContent = count === 0
            ? "Retirar archivos"
            : (count === 1 ? "Retirar archivo" : `Retirar ${count} archivos`);
    }
}

function updateFileSelectionFromCheckbox(checkbox) {
    const item = checkbox.closest("[data-record-file-item]");
    const id = String(checkbox.value || "");
    if (!item || !id || !fileSelectionMode) return;
    if (checkbox.checked && selectedFileIds.size >= 20 && !selectedFileIds.has(id)) {
        checkbox.checked = false;
        showRecordUploadToast("Puedes retirar hasta 20 archivos por operación.", "warning");
        return;
    }
    if (checkbox.checked) selectedFileIds.add(id);
    else selectedFileIds.delete(id);
    item.classList.toggle("is-selected-for-removal", checkbox.checked);
    updateFileSelectionControls();
}

function normalizedSelectedFileItems() {
    const itemsById = new Map();
    neutralFileList?.querySelectorAll("[data-record-file-item]").forEach((item) => {
        const checkbox = item.querySelector("[data-file-select]");
        const button = item.querySelector("[data-record-file]");
        const id = String(checkbox?.value || "");
        const eligible = checkbox?.checked && id !== ""
            && button?.dataset.filePackage !== "true";
        if (eligible && !itemsById.has(id)) itemsById.set(id, item);
    });
    selectedFileIds.clear();
    itemsById.forEach((item, id) => selectedFileIds.add(id));
    neutralFileList?.querySelectorAll("[data-file-select]").forEach((checkbox) => {
        const id = String(checkbox.value || "");
        const selected = itemsById.get(id) === checkbox.closest("[data-record-file-item]");
        checkbox.checked = selected;
        checkbox.closest("[data-record-file-item]")?.classList.toggle("is-selected-for-removal", selected);
    });
    updateFileSelectionControls();
    if (itemsById.size > 20) {
        showRecordUploadToast("Puedes retirar hasta 20 archivos por operación.", "warning");
        return [];
    }
    return [...itemsById.values()];
}

function setFileSelectionMode(active) {
    fileSelectionMode = Boolean(active);
    selectedFileIds.clear();
    closeNeutralFileMenu();
    closeGlobalFileMenu();
    neutralFileList?.classList.toggle("is-file-selection-mode", fileSelectionMode);
    if (globalFileToggle) globalFileToggle.disabled = fileSelectionMode;
    if (fileSelectionBar) fileSelectionBar.hidden = !fileSelectionMode;
    neutralFileList?.querySelectorAll("[data-file-select]").forEach((checkbox) => {
        checkbox.checked = false;
        checkbox.hidden = !fileSelectionMode;
        checkbox.closest("[data-record-file-item]")?.classList.remove("is-selected-for-removal");
    });
    updateFileSelectionControls();
    if (fileSelectionMode) {
        window.requestAnimationFrame(() => neutralFileList?.querySelector("[data-file-select]:not([hidden])")?.focus());
    }
}

function openFileRemoveDialogForTargets(items, origin, bulk = false) {
    const normalizedTargets = new Map();
    [...items].forEach((item) => {
        const button = item?.querySelector("[data-record-file]");
        const id = String(button?.dataset.fileId || "");
        if (id && button.dataset.filePackage !== "true" && !normalizedTargets.has(id)) {
            normalizedTargets.set(id, {
                id,
                item,
                name: button.dataset.fileName || "Archivo",
            });
        }
    });
    const targets = [...normalizedTargets.values()];
    const names = targets.map(({ name }) => name);
    const expectedCount = bulk ? selectedFileIds.size : 1;
    if (!fileRemoveDialog || !targets.length || targets.length !== expectedCount || names.length !== expectedCount) {
        console.error("No se abrió el diálogo de retiro: contador, IDs, elementos y nombres no coinciden.", {
            counter: expectedCount,
            ids: normalizedTargets.size,
            items: targets.length,
            names: names.length,
        });
        return;
    }
    closeNeutralFileMenu();
    closeGlobalFileMenu();
    fileRemoveTargets = normalizedTargets;
    fileRemoveIsBulk = bulk;
    fileRemoveReturnFocus = origin;
    if (fileRemoveError) {
        fileRemoveError.textContent = "";
        fileRemoveError.hidden = true;
    }
    if (fileRemoveConfirm) fileRemoveConfirm.disabled = false;
    const multiple = targets.length > 1;
    if (fileRemoveTitle) fileRemoveTitle.textContent = bulk ? `Retirar ${targets.length} ${multiple ? "archivos" : "archivo"}` : "Retirar archivo";
    if (fileRemoveHistoryNote) {
        fileRemoveHistoryNote.textContent = multiple
            ? "El retiro de los archivos seleccionados quedará registrado en el historial del expediente y podrá reflejarse en los reportes administrativos."
            : "El retiro de este archivo quedará registrado en el historial del expediente y podrá reflejarse en los reportes administrativos.";
    }
    if (fileRemoveDescription) {
        const removesPresentation = targets.some(({ item }) =>
            item.querySelector("[data-record-file]")?.dataset.filePresentation === "true"
        );
        fileRemoveDescription.textContent = multiple
                ? "Los archivos seleccionados dejarán de estar disponibles en el Expediente Digital, pero permanecerán almacenados y podrán restaurarse posteriormente."
                : "El archivo dejará de estar disponible en el Expediente Digital, pero permanecerá almacenado y podrá restaurarse posteriormente.";
        if (fileRemovePresentationWarning) {
            fileRemovePresentationWarning.textContent = "Este archivo está configurado como presentación. Al retirarlo, el expediente quedará sin archivo inicial.";
            fileRemovePresentationWarning.hidden = !removesPresentation;
        }
        if (fileRemoveReplacement && fileRemoveReplacementSelect) {
            fileRemoveReplacementSelect.replaceChildren();
            fileRemoveReplacement.hidden = true;
        }
    }
    if (fileRemoveName) {
        fileRemoveName.textContent = bulk ? "" : names[0];
        fileRemoveName.title = bulk ? "" : names[0];
        fileRemoveName.hidden = bulk;
    }
    if (fileRemoveList) {
        fileRemoveList.replaceChildren(...names.slice(0, 5).map((name) => {
            const entry = document.createElement("li");
            entry.textContent = name;
            entry.title = name;
            return entry;
        }));
        fileRemoveList.hidden = !bulk;
    }
    if (fileRemoveMore) {
        const remaining = Math.max(0, names.length - 5);
        fileRemoveMore.textContent = remaining ? `y ${remaining} ${remaining === 1 ? "archivo más" : "archivos más"}` : "";
        fileRemoveMore.hidden = remaining === 0;
    }
    if (fileRemoveConfirm) fileRemoveConfirm.textContent = bulk ? "Retirar archivos" : "Retirar archivo";
    fileRemoveBackgroundState = [...document.body.children].filter((element) => element !== fileRemoveDialog).map((element) => ({
        element, inert: element.inert, ariaHidden: element.getAttribute("aria-hidden")
    }));
    fileRemoveBackgroundState.forEach(({ element }) => {
        element.inert = true;
        element.setAttribute("aria-hidden", "true");
    });
    document.documentElement.classList.add("file-remove-open");
    document.body.classList.add("file-remove-open");
    fileRemoveDialog.hidden = false;
    window.requestAnimationFrame(() => fileRemoveCancel?.focus());
}

function openFileRemoveDialog(item, origin) {
    openFileRemoveDialogForTargets([item], origin);
}

function closeFileRemoveDialog(removed = false) {
    if (!fileRemoveDialog || fileRemoveDialog.hidden || fileRemoveSubmitting) return;
    fileRemoveDialog.hidden = true;
    fileRemoveBackgroundState.forEach(({ element, inert, ariaHidden }) => {
        element.inert = inert;
        if (ariaHidden === null) element.removeAttribute("aria-hidden");
        else element.setAttribute("aria-hidden", ariaHidden);
    });
    fileRemoveBackgroundState = [];
    document.documentElement.classList.remove("file-remove-open");
    document.body.classList.remove("file-remove-open");
    const focusTarget = !removed && fileRemoveReturnFocus?.isConnected
        ? fileRemoveReturnFocus
        : (globalFileToggle?.isConnected ? globalFileToggle : recordUploadOpen);
    focusTarget?.focus();
    fileRemoveReturnFocus = null;
    fileRemoveTargets.clear();
    fileRemoveIsBulk = false;
    fileRemoveName?.replaceChildren();
    fileRemoveList?.replaceChildren();
    if (fileRemovePresentationWarning) fileRemovePresentationWarning.hidden = true;
    if (fileRemoveReplacement) fileRemoveReplacement.hidden = true;
    fileRemoveReplacementSelect?.replaceChildren();
}

function removeNeutralFileItems(items, packageData) {
    const removedButtons = items.map((item) => item.querySelector("[data-record-file]")).filter(Boolean);
    if (!removedButtons.length) return;
    const removedSet = new Set(removedButtons);
    const removedPresentation = removedButtons.some((button) => button.dataset.filePresentation === "true");
    const selectedButton = removedButtons.find((button) => button.getAttribute("aria-selected") === "true") || null;
    let fallback = null;
    if (selectedButton) {
        const selectedIndex = neutralFileButtons.indexOf(selectedButton);
        fallback = neutralFileButtons.slice(selectedIndex + 1).find((button) => !removedSet.has(button))
            || neutralFileButtons.slice(0, selectedIndex).reverse().find((button) => !removedSet.has(button))
            || null;
    }
    neutralFileButtons = neutralFileButtons.filter((candidate) => !removedSet.has(candidate));
    const groups = new Set();
    items.forEach((item) => {
        const group = item.closest("[data-file-group]");
        if (group) groups.add(group);
        const button = item.querySelector("[data-record-file]");
        const tree = item.nextElementSibling?.matches("[data-zip-tree]") ? item.nextElementSibling : null;
        if (button?.dataset.fileId) neutralZipTreeStates.delete(button.dataset.fileId);
        tree?.remove();
        item.remove();
    });
    groups.forEach((group) => {
        if (!group.querySelector("[data-record-file]")) group.remove();
    });
    syncNeutralFilesEmptyState();
    updateNeutralPackage(packageData);
    if (!selectedButton) return;
    neutralPreviewSequence += 1;
    neutralPreviewRequest?.abort();
    neutralPreviewRequest = null;
    resetNeutralViewer();
    if (!removedPresentation && fallback?.isConnected) {
        selectNeutralFile(fallback);
    } else {
        neutralSelectedFileId = "";
        if (neutralViewerName) neutralViewerName.textContent = "Archivo";
        if (neutralViewerMeta) neutralViewerMeta.textContent = "";
        setNeutralViewerState("empty");
    }
}

async function submitFileRemoval() {
    if (fileRemoveSubmitting || !fileRemoveTargets.size || !fileRemoveConfig) return;
    const targetRecords = [...fileRemoveTargets.values()];
    const targets = targetRecords.map(({ item }) => item);
    const ids = targetRecords.map(({ id }) => id);
    const buttons = targets.map((item) => item.querySelector("[data-record-file]")).filter(Boolean);
    if (buttons.length !== targets.length || ids.length !== targets.length) {
        console.error("No se envió el retiro: IDs, elementos y payload no coinciden.");
        return;
    }
    const multiple = fileRemoveIsBulk;
    fileRemoveSubmitting = true;
    if (fileRemoveConfirm) {
        fileRemoveConfirm.disabled = true;
        fileRemoveConfirm.textContent = "Retirando…";
    }
    if (fileRemoveCancel) fileRemoveCancel.disabled = true;
    if (fileRemoveClose) fileRemoveClose.disabled = true;
    try {
        const data = new FormData();
        data.append("_csrf", fileRemoveConfig.dataset.csrf || "");
        data.append("material_id", fileRemoveConfig.dataset.materialId || "");
        if (multiple) ids.forEach((id) => data.append("file_ids[]", id));
        else data.append("file_id", ids[0]);
        data.append("action", multiple ? "remove_multiple" : "remove");
        const response = await fetch(fileRemoveConfig.dataset.endpoint || "", {
            method: "POST", body: data, credentials: "same-origin",
            headers: { Accept: "application/json", "X-Requested-With": "XMLHttpRequest" }
        });
        const result = await response.json();
        if (!response.ok || !result?.success) throw new Error(result?.message || "No fue posible retirar el archivo.");
        const payload = result?.data && typeof result.data === "object" ? result.data : {};
        const confirmedIds = new Set((payload.removed_file_ids || []).map(String));
        const removedItems = confirmedIds.size
            ? targets.filter((item) => confirmedIds.has(String(item.querySelector("[data-record-file]")?.dataset.fileId || "")))
            : targets;
        const removedPresentation = payload.presentation_removed === true
            || buttons.some((button) => button.dataset.filePresentation === "true");
        fileRemoveSubmitting = false;
        closeFileRemoveDialog(true);
        removeNeutralFileItems(removedItems, payload.package);
        if (payload.presentation_file_id) {
            const presentation = neutralFileButtons.find(
                (button) => String(button.dataset.fileId) === String(payload.presentation_file_id)
            );
            if (presentation) syncPresentationFile(presentation);
        } else if (removedPresentation) {
            syncPresentationFile(null);
        }
        setFileSelectionMode(false);
        refreshRestorableFiles().catch((error) => {
            console.error("No fue posible actualizar los archivos restaurables.", error);
        });
        showRecordUploadToast(
            removedPresentation
                ? "Archivo retirado correctamente. El expediente quedó sin presentación."
                : (result.message || (multiple ? `${removedItems.length} archivos retirados correctamente.` : "Archivo retirado correctamente.")),
            "success"
        );
    } catch (error) {
        if (fileRemoveError) {
            fileRemoveError.textContent = error instanceof Error ? error.message : "No fue posible retirar el archivo.";
            fileRemoveError.hidden = false;
        }
    } finally {
        fileRemoveSubmitting = false;
        if (fileRemoveConfirm) {
            fileRemoveConfirm.disabled = false;
            fileRemoveConfirm.textContent = fileRemoveIsBulk ? "Retirar archivos" : "Retirar archivo";
        }
        if (fileRemoveCancel) fileRemoveCancel.disabled = false;
        if (fileRemoveClose) fileRemoveClose.disabled = false;
    }
}

neutralFileList?.querySelectorAll("[data-record-file-item]").forEach(bindNeutralFileMenu);
fileSelectionToggle?.addEventListener("click", () => setFileSelectionMode(!fileSelectionMode));
fileSelectionCancel?.addEventListener("click", () => {
    setFileSelectionMode(false);
    globalFileToggle?.focus();
});
fileSelectionRemove?.addEventListener("click", () => {
    const targets = normalizedSelectedFileItems();
    if (targets.length) openFileRemoveDialogForTargets(targets, fileSelectionRemove, true);
});
fileRemoveCancel?.addEventListener("click", () => closeFileRemoveDialog());
fileRemoveClose?.addEventListener("click", () => closeFileRemoveDialog());
fileRemoveConfirm?.addEventListener("click", submitFileRemoval);
fileRemoveReplacementSelect?.addEventListener("change", () => {
    if (fileRemoveConfirm && !fileRemoveSubmitting) {
        fileRemoveConfirm.disabled = !fileRemoveReplacementSelect.value;
    }
});
fileRemoveDialog?.addEventListener("click", (event) => {
    if (event.target === fileRemoveDialog) closeFileRemoveDialog();
});
presentationConfirmCancel?.addEventListener("click", () => closePresentationConfirmDialog());
presentationConfirmClose?.addEventListener("click", () => closePresentationConfirmDialog());
presentationConfirmSubmit?.addEventListener("click", submitPresentationChange);
presentationConfirmDialog?.addEventListener("click", (event) => {
    if (event.target === presentationConfirmDialog) closePresentationConfirmDialog();
});
fileReplaceInput?.addEventListener("change", () => {
    try {
        const files = [...(fileReplaceInput.files || [])];
        if (files.length === 1) openFileReplaceConfirmation(files[0]);
        else if (files.length > 1) showRecordUploadToast("Selecciona un único archivo.", "error");
    } catch (error) {
        console.error("Reemplazo de archivo:", error);
        showRecordUploadToast(
            error instanceof Error ? error.message : "No fue posible preparar el reemplazo.",
            "error"
        );
        fileReplaceSelection = null;
        fileReplaceInput.value = "";
        fileReplaceOrigin?.focus();
    }
});
fileReplaceCancel?.addEventListener("click", () => closeFileReplaceDialog());
fileReplaceClose?.addEventListener("click", () => closeFileReplaceDialog());
fileReplaceConfirm?.addEventListener("click", submitFileReplacement);
fileReplaceDialog?.addEventListener("click", (event) => {
    if (event.target === fileReplaceDialog) closeFileReplaceDialog();
});
fileRestoreOpen?.addEventListener("click", openFileRestoreDialog);
fileRestoreList?.addEventListener("click", (event) => {
    const action = event.target.closest("[data-file-restore-inspect]");
    const item = action?.closest("[data-restore-file-id]");
    if (item?.dataset.restoreFileId) inspectFileRestore(item.dataset.restoreFileId);
});
fileRestoreList?.addEventListener("change", (event) => {
    const checkbox = event.target.closest("[data-file-purge-select]");
    if (!checkbox) return;
    if (checkbox.checked) selectedRestorableFileIds.add(String(checkbox.value));
    else selectedRestorableFileIds.delete(String(checkbox.value));
    if (filePurgeOpen) filePurgeOpen.disabled = selectedRestorableFileIds.size === 0;
});
fileRestoreBack?.addEventListener("click", showRestoreList);
filePurgeOpen?.addEventListener("click", confirmPermanentFileDeletion);
fileRestoreCancel?.addEventListener("click", () => {
    if (fileRestoreMode !== "list") showRestoreList();
    else closeFileRestoreDialog();
});
fileRestoreClose?.addEventListener("click", closeFileRestoreDialog);
fileRestoreConfirm?.addEventListener("click", submitFileRestore);
fileRestoreDialog?.addEventListener("click", (event) => {
    if (event.target === fileRestoreDialog) closeFileRestoreDialog();
});
globalFileToggle?.addEventListener("click", () => {
    if (globalFileMenu?.hidden) openGlobalFileMenu();
    else closeGlobalFileMenu(true);
});
globalFileToggle?.addEventListener("keydown", (event) => {
    if (["Enter", " ", "ArrowDown"].includes(event.key)) {
        event.preventDefault();
        openGlobalFileMenu();
    }
});
globalFileMenu?.addEventListener("click", (event) => {
    if (event.target.closest('[role="menuitem"]')) closeGlobalFileMenu();
});
globalFileMenu?.addEventListener("keydown", (event) => {
    const items = [...globalFileMenu.querySelectorAll('[role="menuitem"]:not([disabled])')];
    const index = items.indexOf(document.activeElement);
    if (event.key === "Escape") {
        event.preventDefault();
        closeGlobalFileMenu(true);
    } else if (["ArrowDown", "ArrowUp", "Home", "End"].includes(event.key) && items.length) {
        event.preventDefault();
        const next = event.key === "Home" ? 0
            : event.key === "End" ? items.length - 1
                : event.key === "ArrowUp" ? (index <= 0 ? items.length - 1 : index - 1)
                    : (index >= items.length - 1 ? 0 : index + 1);
        items[next]?.focus();
    }
});
globalFileActions?.addEventListener("focusout", (event) => {
    if (!globalFileActions.contains(event.relatedTarget)) closeGlobalFileMenu();
});
document.addEventListener("click", (event) => {
    if (openNeutralFileMenu && !openNeutralFileMenu.contains(event.target)) closeNeutralFileMenu();
    if (globalFileActions && !globalFileActions.contains(event.target)) closeGlobalFileMenu();
});
document.addEventListener("keydown", (event) => {
    if (fileRestoreDialog && !fileRestoreDialog.hidden) {
        if (event.key === "Escape" && !fileRestoreSubmitting) {
            event.preventDefault();
            if (fileRestoreMode !== "list") showRestoreList();
            else closeFileRestoreDialog();
            return;
        }
        if (event.key === "Tab") {
            const controls = [...fileRestoreDialog.querySelectorAll('button:not([disabled]):not([hidden])')];
            if (!controls.length) return;
            const index = controls.indexOf(document.activeElement);
            const next = event.shiftKey
                ? (index <= 0 ? controls.length - 1 : index - 1)
                : (index === controls.length - 1 ? 0 : index + 1);
            event.preventDefault();
            controls[next]?.focus();
        }
        return;
    }
    if (fileReplaceDialog && !fileReplaceDialog.hidden) {
        if (event.key === "Escape" && !fileReplaceSubmitting) {
            event.preventDefault();
            closeFileReplaceDialog();
            return;
        }
        if (event.key === "Tab") {
            const controls = [fileReplaceClose, fileReplaceCancel, fileReplaceConfirm]
                .filter((control) => control && !control.disabled);
            const index = controls.indexOf(document.activeElement);
            const next = event.shiftKey
                ? (index <= 0 ? controls.length - 1 : index - 1)
                : (index === controls.length - 1 ? 0 : index + 1);
            event.preventDefault();
            controls[next]?.focus();
        }
        return;
    }
    if (presentationConfirmDialog && !presentationConfirmDialog.hidden) {
        if (event.key === "Escape" && !presentationConfirmSubmitting) {
            event.preventDefault();
            closePresentationConfirmDialog();
            return;
        }
        if (event.key === "Tab") {
            const controls = [presentationConfirmClose, presentationConfirmCancel, presentationConfirmSubmit]
                .filter((control) => control && !control.disabled);
            const index = controls.indexOf(document.activeElement);
            const next = event.shiftKey
                ? (index <= 0 ? controls.length - 1 : index - 1)
                : (index === controls.length - 1 ? 0 : index + 1);
            event.preventDefault();
            controls[next]?.focus();
        }
        return;
    }
    if (fileRemoveDialog && !fileRemoveDialog.hidden) {
        if (event.key === "Escape" && !fileRemoveSubmitting) {
            event.preventDefault();
            closeFileRemoveDialog();
            return;
        }
        if (event.key === "Tab") {
            const controls = [fileRemoveClose, fileRemoveCancel, fileRemoveConfirm].filter((control) => control && !control.disabled);
            const index = controls.indexOf(document.activeElement);
            const next = event.shiftKey ? (index <= 0 ? controls.length - 1 : index - 1) : (index === controls.length - 1 ? 0 : index + 1);
            event.preventDefault();
            controls[next]?.focus();
        }
        return;
    }
    if (event.key === "Escape" && openNeutralFileMenu) {
        event.preventDefault();
        closeNeutralFileMenu(true);
    }
});
// Acciones administrativas generales del material de apoyo.
(() => {
    if (window.SupportMaterialAdminActions) {
        const record = document.querySelector('[data-digital-record][data-entity-type="support_material"]');
        const setAdministrativeUnread = (unread) => {
            document.querySelectorAll("[data-record-unread-dot],[data-record-history-unread-dot],[data-record-unread-text]")
                .forEach(element => { element.hidden = !unread; });
        };
        window.markRecordHistoryUnread = () => setAdministrativeUnread(true);
        record?.querySelectorAll("[data-record-admin-action]").forEach(button => button.addEventListener("click", () => {
            const action = button.dataset.recordAdminAction;
            const withdrawing = action === "publication" && record.dataset.recordStatus === "published";
            window.SupportMaterialAdminActions.open({
                trigger: button,
                type: action === "availability" ? (record.dataset.recordAvailable === "1" ? "availability_off" : "availability_on") : (action === "publication" ? (record.dataset.recordStatus === "published" ? "withdraw" : "publish") : "trash"),
                action,
                available: record.dataset.recordAvailable === "1",
                endpoint: record.dataset.adminEndpoint,
                csrf: record.dataset.adminCsrf,
                material: { id: record.dataset.recordId, title: document.querySelector("#digitalRecordTitle")?.textContent || "" },
                onSuccess: result => {
                    setAdministrativeUnread(true);
                    if (action === "trash" || withdrawing) {
                        sessionStorage.setItem("repositoryToast", result.message);
                        window.location.assign(result.data?.redirect || record.dataset.adminRedirect);
                        return;
                    }
                    window.location.reload();
                },
            });
        }));
        return;
    }
    const record = document.querySelector('[data-digital-record][data-entity-type="support_material"]');
    const dialog = document.querySelector("[data-record-admin-dialog]");
    if (!record || !dialog || !record.dataset.adminEndpoint) return;
    if (dialog.parentElement !== document.body) document.body.append(dialog);
    const form = dialog.querySelector("[data-record-admin-form]");
    const title = dialog.querySelector("[data-record-admin-title]");
    const message = dialog.querySelector("[data-record-admin-message]");
    const reasonWrap = dialog.querySelector("[data-record-admin-reason-wrap]");
    const reasons = dialog.querySelector("[data-record-admin-reasons]");
    const reasonOptions = [...dialog.querySelectorAll('input[name="trash_reason"]')];
    const reason = dialog.querySelector("[data-record-admin-reason]");
    const error = dialog.querySelector("[data-record-admin-error]");
    const confirmButton = dialog.querySelector("[data-record-admin-confirm]");
    const cancelButton = dialog.querySelector("[data-record-admin-cancel]");
    const closeButton = dialog.querySelector("[data-record-admin-close]");
    const confirmLabel = dialog.querySelector("[data-record-admin-confirm-label]");
    const unreadDots = [...document.querySelectorAll("[data-record-unread-dot]")];
    const unreadTexts = [...document.querySelectorAll("[data-record-unread-text]")];
    const historyUnreadDot = document.createElement("span");
    historyUnreadDot.className = "ed-unread-dot";
    historyUnreadDot.dataset.recordHistoryUnreadDot = "";
    historyUnreadDot.setAttribute("aria-hidden", "true");
    const historyUnreadText = document.createElement("span");
    historyUnreadText.className = "ed-sr-only";
    historyUnreadText.textContent = "Hay actividad administrativa nueva";
    recordHistoryTrigger?.append(historyUnreadDot, historyUnreadText);
    const setUnread = unread => {
        [...unreadDots, historyUnreadDot].forEach(dot => { dot.hidden = !unread; });
        [...unreadTexts, historyUnreadText].forEach(text => { text.hidden = !unread; });
        recordHistoryLoaded = unread ? false : recordHistoryLoaded;
        if (unread && recordHistoryOverlay && !recordHistoryOverlay.hidden && recordHistoryNotice) {
            recordHistoryNotice.textContent = "Hay actividad administrativa nueva. Cierra y vuelve a abrir el historial para actualizarlo.";
            recordHistoryNotice.hidden = false;
        }
    };
    window.markRecordHistoryUnread = () => setUnread(true);
    setUnread(!unreadDots.every(dot => dot.hidden));
    let pendingAction = "";
    let processing = false;
    let returnFocus = null;

    const descriptions = {
        availability: () => record.dataset.recordAvailable === "1"
            ? ["Marcar como no disponible", "El material permanecerá publicado, pero los usuarios no podrán consultar ni descargar sus archivos hasta que vuelva a estar disponible."]
            : ["Marcar como disponible", "El material volverá a estar disponible para consulta y descarga."],
        publication: () => record.dataset.recordStatus === "published"
            ? ["Retirar publicación", "El material dejará de mostrarse como publicado. No será enviado automáticamente a Papelera: conservará su información y archivos y podrá volver a publicarse posteriormente."]
            : ["Publicar material", "El material volverá a mostrarse en el Repositorio. Se aplicarán las mismas validaciones del flujo de publicación existente."],
        trash: () => ["Enviar a Papelera", "El material dejará de estar disponible en el Repositorio. Sus archivos y metadatos permanecerán temporalmente recuperables según la política de Papelera y la acción quedará registrada."],
    };

    const open = action => {
        returnFocus = document.activeElement;
        pendingAction = action;
        const [heading, copy] = descriptions[action]();
        title.textContent = heading;
        message.textContent = copy;
        reasons.hidden = action !== "trash";
        reasonWrap.hidden = true;
        reasonOptions.forEach(option => { option.checked = false; });
        reason.value = "";
        error.hidden = true;
        confirmLabel.textContent = action === "trash" ? "Enviar a Papelera" : "Confirmar";
        confirmButton.disabled = action === "trash";
        closeDigitalRecordMenu?.();
        dialog.hidden = false;
        document.documentElement.classList.add("file-remove-open");
        document.body.classList.add("file-remove-open");
        closeButton.focus();
    };
    const close = () => {
        if (processing) return;
        dialog.hidden = true;
        document.documentElement.classList.remove("file-remove-open");
        document.body.classList.remove("file-remove-open");
        if (returnFocus instanceof HTMLElement) returnFocus.focus();
    };
    record.querySelectorAll("[data-record-admin-action]").forEach(button => {
        button.addEventListener("click", () => open(button.dataset.recordAdminAction));
    });
    cancelButton.addEventListener("click", close);
    closeButton.addEventListener("click", close);
    reasonOptions.forEach(option => option.addEventListener("change", () => {
        const custom = option.checked && option.value === "other";
        reasonWrap.hidden = !custom;
        if (!custom) reason.value = "";
        confirmButton.disabled = false;
        if (custom) reason.focus();
    }));
    dialog.addEventListener("click", event => { if (event.target === dialog && !processing) close(); });
    document.addEventListener("keydown", event => {
        if (dialog.hidden || event.key !== "Escape") return;
        event.preventDefault();
        close();
    });

    const updateUi = data => {
        record.dataset.recordStatus = data.status_key;
        record.dataset.recordAvailable = data.is_available ? "1" : "0";
        const published = data.status_key === "published";
        const badge = record.querySelector("[data-record-status-label]");
        badge?.classList.toggle("is-success", published);
        badge?.classList.toggle("is-neutral", !published);
        if (badge) {
            badge.querySelector("i").className = `fa-solid ${published ? "fa-circle-check" : "fa-circle-minus"}`;
            badge.querySelector("span").textContent = data.status_label;
        }
        const availability = record.querySelector('[data-record-meta="availability"] dd');
        if (availability) availability.textContent = published ? data.availability_label : "No aplica";
        const availabilityAction = record.querySelector('[data-record-admin-action="availability"]');
        availabilityAction.hidden = !published;
        availabilityAction.querySelector("span").textContent = data.is_available ? "Marcar como no disponible" : "Marcar como disponible";
        const publicationAction = record.querySelector('[data-record-admin-action="publication"]');
        publicationAction.querySelector("span").textContent = published ? "Retirar publicación" : "Publicar material";
        publicationAction.querySelector("i").className = `fa-solid ${published ? "fa-eye-slash" : "fa-eye"}`;
    };

    form.addEventListener("submit", async event => {
        event.preventDefault();
        if (processing) return;
        const selectedReason = reasonOptions.find(option => option.checked);
        if (pendingAction === "trash" && (!selectedReason || (selectedReason.value === "other" && reason.value.trim().length < 5))) {
            error.textContent = "Selecciona un motivo o completa el detalle con al menos cinco caracteres.";
            error.hidden = false;
            if (!selectedReason) reasonOptions[0]?.focus();
            else reason.focus();
            return;
        }
        processing = true;
        confirmButton.disabled = true;
        cancelButton.disabled = true;
        closeButton.disabled = true;
        error.hidden = true;
        confirmLabel.textContent = pendingAction === "publication"
            ? (record.dataset.recordStatus === "published" ? "Retirando publicación…" : "Publicando material…")
            : pendingAction === "availability" ? "Actualizando disponibilidad…" : "Enviando a Papelera…";
        const body = new FormData();
        body.set("_csrf", record.dataset.adminCsrf);
        body.set("id", record.dataset.recordId);
        body.set("action", pendingAction);
        if (pendingAction === "availability") body.set("is_available", record.dataset.recordAvailable === "1" ? "0" : "1");
        if (pendingAction === "publication") body.set("status", record.dataset.recordStatus === "published" ? "withdrawn" : "published");
        if (pendingAction === "trash") {
            body.set("reason_code", selectedReason.value);
            body.set("reason_detail", selectedReason.value === "other" ? reason.value.trim() : "");
        }
        try {
            const response = await fetch(record.dataset.adminEndpoint, { method: "POST", body, credentials: "same-origin" });
            const result = await response.json();
            if (!response.ok || !result.success) throw new Error(result.message || "No fue posible completar la acción.");
            if (pendingAction === "trash") {
                sessionStorage.setItem("repositoryToast", result.message);
                window.location.assign(result.data?.redirect || record.dataset.adminRedirect);
                return;
            }
            updateUi(result.data);
            setUnread(true);
            processing = false;
            close();
            if (typeof showRecordUploadToast === "function") showRecordUploadToast(result.message, "success");
        } catch (requestError) {
            error.textContent = requestError.message;
            error.hidden = false;
        } finally {
            processing = false;
            confirmButton.disabled = false;
            cancelButton.disabled = false;
            closeButton.disabled = false;
            if (!dialog.hidden) confirmLabel.textContent = pendingAction === "trash" ? "Enviar a Papelera" : "Confirmar";
        }
    });
})();
