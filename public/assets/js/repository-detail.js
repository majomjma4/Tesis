// Inicio de interacciones del detalle del repositorio
const repositoryDetailSkeleton = document.querySelector("#repositoryDetailSkeleton");
const repositoryDetailContent = document.querySelector("#repositoryDetailContent");
const repositoryDetailFavorite = document.querySelector("#repositoryDetailFavorite");
const repositoryDetailToast = document.querySelector("#repositoryDetailToast");
const repositoryExplorerBreadcrumb = document.querySelector("#repositoryExplorerBreadcrumb");
const repositoryFileList = document.querySelector("#repositoryFileList");
const repositoryFileRows = document.querySelector("#repositoryFileRows");
const repositoryExplorerState = document.querySelector("#repositoryExplorerState");
let repositoryDetailToastTimer = null;
let repositoryArchiveRequest = null;

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

        const nameCell = document.createElement(item.kind === "folder" ? "button" : "span");
        nameCell.className = item.kind === "folder" ? "repository-file-name repository-file-entry" : "repository-file-name";
        nameCell.setAttribute("role", "cell");
        if (item.kind === "folder") {
            nameCell.type = "button";
            nameCell.dataset.folderPath = item.path;
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

async function loadRepositoryFolder(path) {
    const actionUrl = repositoryDetailContent?.dataset.filesUrl ?? "";
    const projectId = repositoryDetailContent?.dataset.projectId ?? "";
    if (!actionUrl || !projectId) return;

    repositoryArchiveRequest?.abort();
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
});

repositoryExplorerBreadcrumb?.addEventListener("click", (event) => {
    const breadcrumbButton = event.target.closest("[data-archive-path]");
    if (breadcrumbButton) loadRepositoryFolder(breadcrumbButton.dataset.archivePath ?? "");
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
