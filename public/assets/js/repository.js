// Inicio de filtros del repositorio lector
const repositorySkeleton = document.querySelector("#repositorySkeleton");
const repositoryContent = document.querySelector("#repositoryContent");

const tabProjects = document.querySelector("#tabProjects");
const tabSupport = document.querySelector("#tabSupport");
const panelProjects = document.querySelector("#panelProjects");
const panelSupport = document.querySelector("#panelSupport");
const repositoryProjectFilters = document.querySelector("#repositoryProjectFilters");
const toolsSupport = document.querySelector("#toolsSupport");

const repositorySupportSearch = document.querySelector("#repositorySupportSearch");
const repositorySupportCategory = document.querySelector("#repositorySupportCategory");
const repositoryBaseSupportCount = Number(panelSupport?.dataset.baseSupportCount || 0);

const repositoryCount = document.querySelector("#repositoryCount");
const repositoryToast = document.querySelector("#repositoryToast");
const repositorySupportCount = document.querySelector("#repositorySupportCount");
const repositorySupportPagination = document.querySelector("#repositorySupportPagination");
const repositorySupportPaginationSummary = document.querySelector("#repositorySupportPaginationSummary");
const repositorySupportPaginationPages = document.querySelector("#repositorySupportPaginationPages");
const repositorySupportPageSizeSelect = document.querySelector("#repositorySupportPageSize");

const repositorySupportState = { page: 1, size: 10 };
let repositoryToastTimer = null;
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
        if (repositoryProjectFilters) repositoryProjectFilters.hidden = false;
        if (toolsSupport) toolsSupport.hidden = true;
    });
    tabSupport.addEventListener("click", () => {
        tabSupport.classList.add("active");
        tabSupport.setAttribute("aria-selected", "true");
        tabProjects.classList.remove("active");
        tabProjects.setAttribute("aria-selected", "false");
        panelSupport.hidden = false;
        panelProjects.hidden = true;
        if (repositoryProjectFilters) repositoryProjectFilters.hidden = true;
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

function normalizeTypeSlug(value) {
    const slug = normalizeRepositoryText(value).replace(/[\s_]+/g, "-");
    if (["proyecto-pis", "proyecto-integrador-de-saberes", "pis", "proyectos-pis"].includes(slug)) return "proyecto-pis";
    if (["practicas", "practicas-preprofesionales", "practice"].includes(slug)) return "practicas-preprofesionales";
    if (["perfil-tesis", "perfil-de-tesis", "thesis-profile", "thesis_profile"].includes(slug)) return "perfil-tesis";
    if (["tesis", "titulacion", "thesis"].includes(slug)) return "tesis";
    if (["vinculacion", "proyecto-de-vinculacion", "community"].includes(slug)) return "vinculacion";
    return slug;
}

function normalizeCategorySlug(value) {
    const slug = normalizeRepositoryText(value).replace(/[\s_]+/g, "-");
    if (["proyecto-pis", "proyecto-integrador-de-saberes", "pis", "proyectos-pis"].includes(slug)) return "proyecto-pis";
    if (["practicas", "practicas-preprofesionales", "practice"].includes(slug)) return "practicas";
    if (["perfil-tesis", "perfil-de-tesis", "thesis-profile", "thesis_profile"].includes(slug)) return "perfil-tesis";
    if (["tesis", "titulacion", "thesis"].includes(slug)) return "tesis";
    if (["vinculacion", "proyecto-de-vinculacion", "community"].includes(slug)) return "vinculacion";
    return slug;
}

/* Legacy client-side project filtering is intentionally disabled; the catalog is server-side paginated.
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
        const matchesType = typeValue === "all"
            || normalizeTypeSlug(card.dataset.type) === normalizeTypeSlug(typeValue)
            || normalizeTypeSlug(card.dataset.typeCode) === normalizeTypeSlug(typeValue);
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
        if (repositoryBaseProjectCount > 0) {
            repositoryEmptyTitle.textContent = "No se encontraron proyectos";
            repositoryEmptyText.textContent = "Prueba con otros términos o modifica los filtros seleccionados.";
        } else {
            repositoryEmptyTitle.textContent = "Aún no existen proyectos publicados.";
            repositoryEmptyText.textContent = "Los proyectos aprobados aparecerán aquí después de completar su publicación.";
        }
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
*/

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

window.showRepositoryToast = showRepositoryToast;

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
            if (event.target.closest("button, a")) return;
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

function supportPageTokens(page, total) {
    if (total <= 5) return Array.from({ length: total }, (_, index) => index + 1);
    if (page <= 3) return [1, 2, 3, "…", total];
    if (page >= total - 2) return [1, "…", total - 2, total - 1, total];
    return [1, "…", page - 1, page, page + 1, "…", total];
}

function filterRepositorySupportDocuments(resetPage = true) {
    const rawSearchValue = repositorySupportSearch?.value ?? "";
    const searchValue = normalizeRepositoryText(rawSearchValue);
    const searchTerms = searchValue.split(" ").filter(Boolean);
    const categoryValue = repositorySupportCategory?.value ?? "all";
    const supportCards = getSupportCards();

    supportCards.forEach((card) => {
        const haystack = normalizeRepositoryText(card.dataset.supportText ?? card.textContent ?? "");
        const matchesSearch = !searchValue || searchTerms.every(term => haystack.includes(term));
        const matchesCategory = categoryValue === "all"
            || normalizeCategorySlug(card.dataset.supportCategory) === normalizeCategorySlug(categoryValue);
        const matchesFilters = matchesSearch && matchesCategory;

        card.dataset.supportMatch = matchesFilters ? "true" : "false";

        restoreHighlights(card);
        if (matchesFilters && searchTerms.length > 0) {
            getSearchableNodes(card).forEach((node) => highlightNode(node, searchTerms));
        }
    });

    if (resetPage) repositorySupportState.page = 1;
    const matches = getRepositorySupportMatches();
    const totalPages = Math.max(1, Math.ceil(matches.length / repositorySupportState.size));
    repositorySupportState.page = Math.min(repositorySupportState.page, totalPages);
    const start = (repositorySupportState.page - 1) * repositorySupportState.size;
    const visible = new Set(matches.slice(start, start + repositorySupportState.size));
    supportCards.forEach(card => { card.hidden = !visible.has(card); });

    if (repositorySupportCount) {
        repositorySupportCount.textContent = `${matches.length} ${matches.length === 1 ? "resultado visible" : "resultados visibles"}`;
    }

    const repositorySupportEmpty = document.querySelector("#repositorySupportEmpty");
    if (repositorySupportEmpty) {
        repositorySupportEmpty.hidden = matches.length !== 0;
    }
    const repositorySupportEmptyTitle = document.querySelector("#repositorySupportEmptyTitle");
    const repositorySupportEmptyText = document.querySelector("#repositorySupportEmptyText");
    if (repositorySupportEmptyTitle && repositorySupportEmptyText && repositoryBaseSupportCount > 0) {
        repositorySupportEmptyTitle.textContent = "No se encontraron materiales";
        repositorySupportEmptyText.textContent = "Prueba con otros tÃ©rminos o modifica la categorÃ­a seleccionada.";
    }
    const from = matches.length === 0 ? 0 : start + 1;
    const to = Math.min(start + repositorySupportState.size, matches.length);
    if (repositorySupportPaginationSummary) repositorySupportPaginationSummary.textContent = matches.length === 0 ? "Mostrando 0 de 0" : `Mostrando ${from}-${to} de ${matches.length}`;
    if (repositorySupportPagination) repositorySupportPagination.hidden = matches.length <= repositorySupportState.size;
    if (repositorySupportPaginationPages) {
        repositorySupportPaginationPages.replaceChildren();
        if (matches.length > repositorySupportState.size) {
            const add = (label, target, disabled = false, active = false) => { const button = document.createElement("button"); button.type = "button"; button.textContent = label; button.disabled = disabled; button.classList.toggle("active", active); button.addEventListener("click", () => { repositorySupportState.page = target; filterRepositorySupportDocuments(false); }); repositorySupportPaginationPages.append(button); };
            add("Anterior", Math.max(1, repositorySupportState.page - 1), repositorySupportState.page === 1);
            supportPageTokens(repositorySupportState.page, totalPages).forEach(token => { if (typeof token === "number") add(String(token), token, false, token === repositorySupportState.page); else { const ellipsis = document.createElement("span"); ellipsis.textContent = token; repositorySupportPaginationPages.append(ellipsis); } });
            add("Siguiente", Math.min(totalPages, repositorySupportState.page + 1), repositorySupportState.page === totalPages);
        }
    }
}

function getRepositorySupportMatches() {
    return getSupportCards().filter((card) => card.dataset.supportMatch === "true");
}

repositorySupportPageSizeSelect?.addEventListener("change", () => { repositorySupportState.size = Number(repositorySupportPageSizeSelect.value || 10); repositorySupportState.page = 1; filterRepositorySupportDocuments(false); });

repositorySupportSearch?.addEventListener("input", filterRepositorySupportDocuments);
repositorySupportSearch?.addEventListener("keyup", filterRepositorySupportDocuments);
repositorySupportCategory?.addEventListener("change", filterRepositorySupportDocuments);

bindProjectCardEvents();
filterRepositorySupportDocuments();
// Final de filtros del repositorio lector
