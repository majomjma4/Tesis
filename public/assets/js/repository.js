// Inicio de filtros del repositorio lector
const repositorySkeleton = document.querySelector("#repositorySkeleton");
const repositoryContent = document.querySelector("#repositoryContent");

const tabProjects = document.querySelector("#tabProjects");
const tabSupport = document.querySelector("#tabSupport");
const panelProjects = document.querySelector("#panelProjects");
const panelSupport = document.querySelector("#panelSupport");
const toolsProjects = document.querySelector("#toolsProjects");
const toolsSupport = document.querySelector("#toolsSupport");

const repositorySearch = document.querySelector("#repositorySearch");
const repositoryClearSearch = document.querySelector("#repositoryClearSearch");
const repositoryType = document.querySelector("#repositoryType");
const repositoryPao = document.querySelector("#repositoryPao");

const repositorySupportSearch = document.querySelector("#repositorySupportSearch");
const repositorySupportCategory = document.querySelector("#repositorySupportCategory");

const repositoryCount = document.querySelector("#repositoryCount");
const repositoryToast = document.querySelector("#repositoryToast");
const repositorySupportCount = document.querySelector("#repositorySupportCount");
const repositorySupportPrev = document.querySelector("#repositorySupportPrev");
const repositorySupportNext = document.querySelector("#repositorySupportNext");

const repositoryEmpty = document.querySelector("#repositoryEmpty");
const repositoryEmptyTitle = document.querySelector("#repositoryEmptyTitle");
const repositoryEmptyText = document.querySelector("#repositoryEmptyText");

const repositoryPagination = document.querySelector("#repositoryPagination");
const repositoryPaginationSummary = document.querySelector("#repositoryPaginationSummary");
const repositoryPagePrevious = document.querySelector("#repositoryPagePrevious");
const repositoryPageNext = document.querySelector("#repositoryPageNext");
const repositoryPageInfo = document.querySelector("#repositoryPageInfo");

const repositorySupportPageSize = 4;
let repositorySupportCurrentPage = 0;
let repositoryToastTimer = null;
const repositoryProjectsPerPage = 10;
let repositoryCurrentPage = 1;
const originalTextMap = new WeakMap();

// Manejo de pestañas unificadas (Proyectos / Material de apoyo)
if (tabProjects && tabSupport && panelProjects && panelSupport) {
    tabProjects.addEventListener("click", () => {
        tabProjects.classList.add("active");
        tabProjects.setAttribute("aria-selected", "true");
        tabSupport.classList.remove("active");
        tabSupport.setAttribute("aria-selected", "false");
        panelProjects.hidden = false;
        panelSupport.hidden = true;
        if (toolsProjects) toolsProjects.hidden = false;
        if (toolsSupport) toolsSupport.hidden = true;
    });
    tabSupport.addEventListener("click", () => {
        tabSupport.classList.add("active");
        tabSupport.setAttribute("aria-selected", "true");
        tabProjects.classList.remove("active");
        tabProjects.setAttribute("aria-selected", "false");
        panelSupport.hidden = false;
        panelProjects.hidden = true;
        if (toolsProjects) toolsProjects.hidden = true;
        if (toolsSupport) toolsSupport.hidden = false;
    });
}

// Precarga estilo skeleton
setTimeout(() => {
    if (repositorySkeleton) {
        repositorySkeleton.hidden = true;
    }
    if (repositoryContent) {
        repositoryContent.style.display = "block";
        requestAnimationFrame(() => repositoryContent.classList.add("is-loaded"));
    }
}, 800);

function normalizeRepositoryText(value) {
    return String(value || "")
        .normalize("NFD")
        .replace(/[\u0300-\u036f]/g, "")
        .replace(/\s+/g, " ")
        .toLowerCase()
        .trim();
}

function getProjectCards() {
    return [...document.querySelectorAll("#panelProjects .ar-project-card, #panelProjects .repository-card, #readerProjectGrid .ar-project-card")];
}

function getSupportCards() {
    return [...document.querySelectorAll("#panelSupport .ar-material-card:not(.repository-more-card)")];
}

function getSearchableNodes(container) {
    return [...container.querySelectorAll("h3, dd, .ar-code, .ar-project-type, .ar-card-copy p, header strong")];
}

function restoreHighlights(container) {
    getSearchableNodes(container).forEach((node) => {
        if (originalTextMap.has(node)) {
            node.textContent = originalTextMap.get(node);
        }
    });
}

function highlightNode(node, terms) {
    if (!originalTextMap.has(node)) {
        originalTextMap.set(node, node.textContent);
    }
    const value = originalTextMap.get(node);
    if (!value) return;

    const characters = [...value];
    let folded = "";
    const positions = [];
    characters.forEach((character, index) => {
        const normalizedCharacter = normalizeRepositoryText(character);
        folded += normalizedCharacter;
        [...normalizedCharacter].forEach(() => positions.push(index));
    });

    const ranges = [];
    terms.forEach((term) => {
        let from = 0;
        while (term && (from = folded.indexOf(term, from)) !== -1) {
            ranges.push([positions[from], positions[from + term.length - 1] + 1]);
            from += term.length;
        }
    });

    if (!ranges.length) {
        node.textContent = value;
        return;
    }

    ranges.sort((first, second) => first[0] - second[0]);
    const merged = ranges.reduce((result, range) => {
        const previous = result.at(-1);
        if (previous && range[0] <= previous[1]) previous[1] = Math.max(previous[1], range[1]);
        else result.push(range);
        return result;
    }, []);

    const fragment = document.createDocumentFragment();
    let cursor = 0;
    merged.forEach(([start, end]) => {
        if (start > cursor) fragment.append(document.createTextNode(characters.slice(cursor, start).join("")));
        const mark = document.createElement("mark");
        mark.className = "ar-search-highlight";
        mark.textContent = characters.slice(start, end).join("");
        fragment.append(mark);
        cursor = end;
    });
    if (cursor < characters.length) fragment.append(document.createTextNode(characters.slice(cursor).join("")));
    node.replaceChildren(fragment);
}

function filterRepositoryProjects(resetPage = true) {
    const rawSearchValue = repositorySearch?.value ?? "";
    const searchValue = normalizeRepositoryText(rawSearchValue);
    const searchTerms = searchValue.split(" ").filter(Boolean);

    if (repositoryClearSearch) {
        repositoryClearSearch.hidden = searchValue === "";
    }

    const typeValue = repositoryType?.value ?? "all";
    const paoValue = repositoryPao?.value ?? "all";

    const cards = getProjectCards();
    const matchingProjects = [];

    cards.forEach((card) => {
        const searchDataset = card.dataset.projectSearch || "";
        const titleText = card.querySelector("h3")?.textContent || "";
        const codeText = card.querySelector(".ar-code")?.textContent || "";
        const authorsText = card.querySelector("dl div:nth-child(1) dd")?.textContent || "";
        const tutorText = card.querySelector("dl div:nth-child(2) dd")?.textContent || "";
        const periodText = card.querySelector("dl div:nth-child(3) dd")?.textContent || "";
        const typeText = card.querySelector(".ar-project-type")?.textContent || "";

        const haystack = normalizeRepositoryText(
            `${searchDataset} ${codeText} ${titleText} ${authorsText} ${tutorText} ${periodText} ${typeText} ${card.textContent || ""}`
        );

        const matchesSearch = !searchValue || searchTerms.every(term => haystack.includes(term));
        const matchesType = typeValue === "all" || card.dataset.type === typeValue || card.dataset.typeCode === typeValue;
        const matchesPao = paoValue === "all" || card.dataset.pao === paoValue || normalizeRepositoryText(card.dataset.pao || "").includes(paoValue);

        const isVisible = matchesSearch && matchesType && matchesPao;
        if (isVisible) matchingProjects.push(card);
    });

    if (resetPage) repositoryCurrentPage = 1;
    const totalPages = Math.max(1, Math.ceil(matchingProjects.length / repositoryProjectsPerPage));
    repositoryCurrentPage = Math.min(repositoryCurrentPage, totalPages);
    const pageStart = (repositoryCurrentPage - 1) * repositoryProjectsPerPage;
    const pageProjects = matchingProjects.slice(pageStart, pageStart + repositoryProjectsPerPage);

    cards.forEach((card) => {
        const isVisible = pageProjects.includes(card);
        card.hidden = !isVisible;
        if (isVisible) {
            restoreHighlights(card);
            if (searchTerms.length > 0) {
                getSearchableNodes(card).forEach((node) => highlightNode(node, searchTerms));
            }
        } else {
            restoreHighlights(card);
        }
    });

    const firstVisible = matchingProjects.length === 0 ? 0 : pageStart + 1;
    const lastVisible = Math.min(pageStart + repositoryProjectsPerPage, matchingProjects.length);

    if (repositoryCount) {
        repositoryCount.textContent = String(matchingProjects.length);
    }

    if (repositoryEmpty) {
        repositoryEmpty.hidden = matchingProjects.length !== 0;
    }
    if (repositoryEmptyTitle && repositoryEmptyText) {
        repositoryEmptyTitle.textContent = "No se encontraron proyectos";
        repositoryEmptyText.textContent = "Prueba con otros términos o modifica los filtros seleccionados.";
    }

    if (repositoryPagination) {
        repositoryPagination.hidden = totalPages <= 1 || matchingProjects.length <= repositoryProjectsPerPage;
    }
    if (repositoryPaginationSummary) {
        repositoryPaginationSummary.textContent = `Mostrando ${firstVisible} - ${lastVisible} de ${matchingProjects.length}`;
    }
    if (repositoryPageInfo) {
        repositoryPageInfo.textContent = `Página ${repositoryCurrentPage} de ${totalPages}`;
    }
    if (repositoryPagePrevious) {
        repositoryPagePrevious.disabled = repositoryCurrentPage <= 1;
    }
    if (repositoryPageNext) {
        repositoryPageNext.disabled = repositoryCurrentPage >= totalPages;
    }
}

function showRepositoryToast(message) {
    if (!repositoryToast) return;

    window.clearTimeout(repositoryToastTimer);
    repositoryToast.textContent = message;
    repositoryToast.hidden = false;
    requestAnimationFrame(() => repositoryToast.classList.add("show"));

    repositoryToastTimer = window.setTimeout(() => {
        repositoryToast.classList.remove("show");
        window.setTimeout(() => {
            repositoryToast.hidden = true;
        }, 220);
    }, 2200);
}

// Escuchar eventos en las tarjetas de proyecto (Favoritos y Navegación)
function bindProjectCardEvents() {
    const cards = getProjectCards();
    const repositoryResults = document.querySelector("#panelProjects, #readerProjectGrid");

    cards.forEach((card) => {
        const favoriteButton = card.querySelector(".repository-favorite-btn");

        favoriteButton?.replaceWith(favoriteButton.cloneNode(true));
        const newFavoriteButton = card.querySelector(".repository-favorite-btn");

        newFavoriteButton?.addEventListener("click", async (event) => {
            event.stopPropagation();
            const actionUrl = repositoryResults?.dataset.favoriteUrl ?? "";
            const csrfToken = repositoryResults?.dataset.favoriteCsrf ?? "";
            const projectId = card.dataset.projectId ?? "";

            newFavoriteButton.disabled = true;
            newFavoriteButton.setAttribute("aria-busy", "true");

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
                const icon = newFavoriteButton.querySelector("i");
                const projectTitle = card.querySelector("h3")?.textContent?.trim() ?? "proyecto";
                card.dataset.favorite = String(isFavorite);
                newFavoriteButton.classList.toggle("is-favorite", isFavorite);
                newFavoriteButton.setAttribute("aria-pressed", String(isFavorite));
                newFavoriteButton.setAttribute("title", isFavorite ? "Eliminar de favoritos" : "Guardar en favoritos");
                newFavoriteButton.setAttribute("aria-label", `${isFavorite ? "Eliminar de favoritos" : "Guardar en favoritos"}: ${projectTitle}`);
                icon?.classList.toggle("fa-solid", isFavorite);
                icon?.classList.toggle("fa-regular", !isFavorite);

                showRepositoryToast(result.message);
            } catch (error) {
                showRepositoryToast(error instanceof Error ? error.message : "No fue posible completar la acción.");
            } finally {
                newFavoriteButton.disabled = false;
                newFavoriteButton.removeAttribute("aria-busy");
            }
        });

        const openProjectDetail = (event) => {
            if (event.type === "keydown" && !["Enter", " "].includes(event.key)) return;
            if (event.type === "keydown" && event.repeat) return;
            if (event.target.closest("button")) return;
            const projectUrl = card.dataset.projectUrl;
            if (!projectUrl) return;
            event.preventDefault();
            window.location.href = projectUrl;
        };

        card.removeEventListener("click", openProjectDetail);
        card.removeEventListener("keydown", openProjectDetail);
        card.addEventListener("click", openProjectDetail);
        card.addEventListener("keydown", openProjectDetail);
    });
}

repositoryClearSearch?.addEventListener("click", () => {
    if (repositorySearch) {
        repositorySearch.value = "";
        repositorySearch.focus();
    }
    filterRepositoryProjects();
});

repositoryPagePrevious?.addEventListener("click", () => {
    repositoryCurrentPage = Math.max(1, repositoryCurrentPage - 1);
    filterRepositoryProjects(false);
    document.querySelector("#readerProjectGrid")?.scrollIntoView({ behavior: "smooth", block: "start" });
});

repositoryPageNext?.addEventListener("click", () => {
    repositoryCurrentPage += 1;
    filterRepositoryProjects(false);
    document.querySelector("#readerProjectGrid")?.scrollIntoView({ behavior: "smooth", block: "start" });
});

function filterRepositorySupportDocuments() {
    const rawSearchValue = repositorySupportSearch?.value ?? "";
    const searchValue = normalizeRepositoryText(rawSearchValue);
    const searchTerms = searchValue.split(" ").filter(Boolean);
    const categoryValue = repositorySupportCategory?.value ?? "all";
    const supportCards = getSupportCards();

    supportCards.forEach((card) => {
        const haystack = normalizeRepositoryText(card.dataset.supportText ?? card.textContent ?? "");
        const matchesSearch = !searchValue || searchTerms.every(term => haystack.includes(term));
        const matchesCategory = categoryValue === "all" || card.dataset.supportCategory === categoryValue;
        const matchesFilters = matchesSearch && matchesCategory;

        card.dataset.supportMatch = matchesFilters ? "true" : "false";

        restoreHighlights(card);
        if (matchesFilters && searchTerms.length > 0) {
            getSearchableNodes(card).forEach((node) => highlightNode(node, searchTerms));
        }
    });

    repositorySupportCurrentPage = 0;
    renderRepositorySupportCarousel(getRepositorySupportMatches());

    if (repositorySupportCount) {
        const totalMatches = supportCards.filter((card) => card.dataset.supportMatch === "true").length;
        repositorySupportCount.textContent = `${totalMatches} ${totalMatches === 1 ? "resultado visible" : "resultados visibles"}`;
    }

    const repositorySupportEmpty = document.querySelector("#repositorySupportEmpty");
    if (repositorySupportEmpty) {
        const totalMatches = supportCards.filter((card) => card.dataset.supportMatch === "true").length;
        repositorySupportEmpty.hidden = totalMatches !== 0;
    }
}

function getRepositorySupportMatches() {
    return getSupportCards().filter((card) => card.dataset.supportMatch === "true");
}

function renderRepositorySupportCarousel(documents = getRepositorySupportMatches()) {
    const supportCards = getSupportCards();
    const lastStartPosition = Math.max(0, documents.length - repositorySupportPageSize);
    repositorySupportCurrentPage = Math.min(repositorySupportCurrentPage, lastStartPosition);
    const pageStart = repositorySupportCurrentPage;
    const pageDocuments = documents.slice(pageStart, pageStart + repositorySupportPageSize);

    supportCards.forEach((card) => {
        card.hidden = !pageDocuments.includes(card);
    });

    if (repositorySupportPrev) {
        const hasPreviousPage = repositorySupportCurrentPage > 0 && documents.length > 0;
        repositorySupportPrev.hidden = !hasPreviousPage;
        repositorySupportPrev.disabled = !hasPreviousPage;
    }
    if (repositorySupportNext) {
        const hasNextPage = repositorySupportCurrentPage < lastStartPosition && documents.length > 0;
        repositorySupportNext.hidden = !hasNextPage;
        repositorySupportNext.disabled = !hasNextPage;
    }
}

repositorySupportPrev?.addEventListener("click", () => {
    repositorySupportCurrentPage = Math.max(0, repositorySupportCurrentPage - 1);
    renderRepositorySupportCarousel();
});

repositorySupportNext?.addEventListener("click", () => {
    const lastStartPosition = Math.max(0, getRepositorySupportMatches().length - repositorySupportPageSize);
    repositorySupportCurrentPage = Math.min(lastStartPosition, repositorySupportCurrentPage + 1);
    renderRepositorySupportCarousel();
});

repositorySearch?.addEventListener("input", filterRepositoryProjects);
repositorySearch?.addEventListener("keyup", filterRepositoryProjects);
repositorySupportSearch?.addEventListener("input", filterRepositorySupportDocuments);
repositorySupportSearch?.addEventListener("keyup", filterRepositorySupportDocuments);
repositorySupportCategory?.addEventListener("change", filterRepositorySupportDocuments);
repositoryType?.addEventListener("change", filterRepositoryProjects);
repositoryPao?.addEventListener("change", filterRepositoryProjects);

bindProjectCardEvents();
filterRepositoryProjects();
filterRepositorySupportDocuments();
// Final de filtros del repositorio lector
