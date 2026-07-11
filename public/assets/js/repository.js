// Inicio de filtros del repositorio
const repositorySkeleton = document.querySelector("#repositorySkeleton");
const repositoryContent = document.querySelector("#repositoryContent");
const repositorySearch = document.querySelector("#repositorySearch");
const repositorySupportSearch = document.querySelector("#repositorySupportSearch");
const repositorySupportCategory = document.querySelector("#repositorySupportCategory");
const repositorySemester = document.querySelector("#repositorySemester");
const repositoryTeacher = document.querySelector("#repositoryTeacher");
const repositoryCategory = document.querySelector("#repositoryCategory");
const repositoryType = document.querySelector("#repositoryType");
const repositoryPao = document.querySelector("#repositoryPao");
const repositoryCards = [...document.querySelectorAll(".repository-results .repository-card")];
const repositoryResults = document.querySelector(".repository-results");
const repositorySupportCards = [...document.querySelectorAll(".repository-support .repository-card:not(.repository-more-card)")];
const repositorySupportMoreCard = document.querySelector("#repositorySupportMoreCard");
const repositoryCount = document.querySelector("#repositoryCount");
const repositoryFavoritesCount = document.querySelector("#repositoryFavoritesCount");
const repositoryFavoritesFilter = document.querySelector("#repositoryFavoritesFilter");
const repositoryClearFilters = document.querySelector("#repositoryClearFilters");
const repositoryToast = document.querySelector("#repositoryToast");
const repositorySupportCount = document.querySelector("#repositorySupportCount");
const repositorySupportPrev = document.querySelector("#repositorySupportPrev");
const repositorySupportNext = document.querySelector("#repositorySupportNext");
const repositorySupportMore = document.querySelector("#repositorySupportMore");
const repositoryEmpty = document.querySelector("#repositoryEmpty");
const repositoryEmptyTitle = document.querySelector("#repositoryEmptyTitle");
const repositoryEmptyText = document.querySelector("#repositoryEmptyText");
const repositoryShowAllProjects = document.querySelector("#repositoryShowAllProjects");
const repositoryDropdowns = [...document.querySelectorAll("[data-dropdown]")];
const repositorySupportPageSize = 4;
const repositorySupportLimit = 12;
const repositorySupportDocumentLimit = repositorySupportLimit - 1;
let repositorySupportCurrentPage = 0;
let repositoryFavoritesOnly = false;
let repositoryToastTimer = null;

// Inicio de precarga estilo skeleton
setTimeout(() => {
    if (repositorySkeleton) {
        repositorySkeleton.hidden = true;
    }
    if (repositoryContent) {
        repositoryContent.style.display = "block";
        requestAnimationFrame(() => repositoryContent.classList.add("is-loaded"));
    }
}, 800);
// Final de precarga estilo skeleton

function normalizeRepositoryText(value) {
    return value.normalize("NFD").replace(/[\u0300-\u036f]/g, "").toLowerCase().trim();
}

function filterRepositoryProjects() {
    const searchValue = normalizeRepositoryText(repositorySearch?.value ?? "");
    const semesterValue = repositorySemester?.value ?? "all";
    const teacherValue = repositoryTeacher?.value ?? "all";
    const categoryValue = repositoryCategory?.value ?? "all";
    const typeValue = repositoryType?.value ?? "all";
    const paoValue = repositoryPao?.value ?? "all";
    let visibleProjects = 0;

    repositoryCards.forEach((card) => {
        const matchesSearch = normalizeRepositoryText(card.dataset.projectSearch ?? card.textContent ?? "").includes(searchValue);
        const matchesSemester = semesterValue === "all" || card.dataset.semester === semesterValue;
        const matchesTeacher = teacherValue === "all" || card.dataset.teacher === teacherValue;
        const matchesCategory = categoryValue === "all" || card.dataset.category === categoryValue;
        const matchesType = typeValue === "all" || card.dataset.type === typeValue;
        const matchesPao = paoValue === "all" || card.dataset.pao === paoValue;
        const matchesFavorite = !repositoryFavoritesOnly || card.dataset.favorite === "true";
        const isVisible = matchesSearch && matchesSemester && matchesTeacher && matchesCategory && matchesType && matchesPao && matchesFavorite;

        card.hidden = !isVisible;
        if (isVisible) {
            visibleProjects += 1;
        }
    });

    if (repositoryCount) {
        repositoryCount.textContent = `Mostrando ${visibleProjects} de ${repositoryCards.length} proyectos`;
    }
    if (repositoryEmpty) {
        repositoryEmpty.hidden = visibleProjects !== 0;
    }
    if (repositoryEmptyTitle && repositoryEmptyText && repositoryShowAllProjects) {
        const favoritesAreEmpty = visibleProjects === 0 && repositoryFavoritesOnly;
        repositoryEmptyTitle.textContent = favoritesAreEmpty ? "Aún no has guardado proyectos favoritos" : "No se encontraron proyectos";
        repositoryEmptyText.textContent = favoritesAreEmpty
            ? "Guarda proyectos con el corazón para encontrarlos rápidamente aquí."
            : "Prueba con otros términos o modifica los filtros seleccionados.";
        repositoryShowAllProjects.hidden = !favoritesAreEmpty;
    }
}

function updateRepositoryFavoritesCount() {
    const favoritesCount = repositoryCards.filter((card) => card.dataset.favorite === "true").length;
    if (repositoryFavoritesCount) {
        repositoryFavoritesCount.textContent = String(favoritesCount);
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

function setRepositoryFavoritesFilter(active) {
    repositoryFavoritesOnly = active;
    if (!repositoryFavoritesFilter) return;

    repositoryFavoritesFilter.classList.toggle("is-active", repositoryFavoritesOnly);
    repositoryFavoritesFilter.setAttribute("aria-pressed", String(repositoryFavoritesOnly));
    const icon = repositoryFavoritesFilter.querySelector("i");
    const label = repositoryFavoritesFilter.querySelector(":scope > span");
    icon?.classList.toggle("fa-regular", !repositoryFavoritesOnly);
    icon?.classList.toggle("fa-solid", repositoryFavoritesOnly);
    if (label) label.textContent = repositoryFavoritesOnly ? "Mostrando favoritos" : "Favoritos";
}

repositoryFavoritesFilter?.addEventListener("click", () => {
    setRepositoryFavoritesFilter(!repositoryFavoritesOnly);
    filterRepositoryProjects();
});

repositoryCards.forEach((card) => {
    const favoriteButton = card.querySelector(".repository-favorite-btn");

    favoriteButton?.addEventListener("click", async (event) => {
        event.stopPropagation();
        const actionUrl = repositoryResults?.dataset.favoriteUrl ?? "";
        const csrfToken = repositoryResults?.dataset.favoriteCsrf ?? "";
        const projectId = card.dataset.projectId ?? "";

        favoriteButton.disabled = true;
        favoriteButton.setAttribute("aria-busy", "true");

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
            const icon = favoriteButton.querySelector("i");
            const projectTitle = card.querySelector("h3")?.textContent?.trim() ?? "proyecto";
            card.dataset.favorite = String(isFavorite);
            favoriteButton.classList.toggle("is-favorite", isFavorite);
            favoriteButton.setAttribute("aria-pressed", String(isFavorite));
            favoriteButton.setAttribute("title", isFavorite ? "Eliminar de favoritos" : "Guardar en favoritos");
            favoriteButton.setAttribute("aria-label", `${isFavorite ? "Eliminar de favoritos" : "Guardar en favoritos"}: ${projectTitle}`);
            icon?.classList.toggle("fa-solid", isFavorite);
            icon?.classList.toggle("fa-regular", !isFavorite);

            if (repositoryFavoritesCount) {
                repositoryFavoritesCount.textContent = String(result.data.favoritesCount);
            } else {
                updateRepositoryFavoritesCount();
            }
            filterRepositoryProjects();
            showRepositoryToast(result.message);
        } catch (error) {
            showRepositoryToast(error instanceof Error ? error.message : "No fue posible completar la acción.");
        } finally {
            favoriteButton.disabled = false;
            favoriteButton.removeAttribute("aria-busy");
        }
    });

    const openProjectDetail = (event) => {
        if (event.type === "keydown" && event.key !== "Enter") return;
        if (event.target.closest("button")) return;
        const projectUrl = card.dataset.projectUrl;
        if (!projectUrl) return;
        event.preventDefault();
        window.location.href = projectUrl;
    };

    card.addEventListener("click", openProjectDetail);
    card.addEventListener("keydown", openProjectDetail);
});

repositoryClearFilters?.addEventListener("click", () => {
    if (repositorySearch) repositorySearch.value = "";
    setRepositoryFavoritesFilter(false);

    [repositorySemester, repositoryTeacher, repositoryCategory, repositoryType, repositoryPao].forEach((input) => {
        const dropdown = input?.closest("[data-dropdown]");
        const allOption = dropdown?.querySelector('[data-dropdown-option][data-value="all"]');
        if (dropdown && allOption) {
            syncRepositoryDropdown(dropdown, "all", allOption.textContent?.trim() ?? "Todos");
        }
    });

    filterRepositoryProjects();
});

repositoryShowAllProjects?.addEventListener("click", () => {
    setRepositoryFavoritesFilter(false);
    filterRepositoryProjects();
});

function filterRepositorySupportDocuments() {
    const searchValue = normalizeRepositoryText(repositorySupportSearch?.value ?? "");
    const categoryValue = repositorySupportCategory?.value ?? "all";
    repositorySupportCards.forEach((card) => {
        const haystack = normalizeRepositoryText(card.dataset.supportText ?? card.textContent ?? "");
        const matchesSearch = haystack.includes(searchValue);
        const matchesCategory = categoryValue === "all" || card.dataset.supportCategory === categoryValue;
        const matchesFilters = matchesSearch && matchesCategory;

        card.dataset.supportMatch = matchesFilters ? "true" : "false";
    });

    repositorySupportCurrentPage = 0;
    renderRepositorySupportCarousel(getRepositorySupportMatches());

    if (repositorySupportCount) {
        const totalMatches = repositorySupportCards.filter((card) => card.dataset.supportMatch === "true").length;
        repositorySupportCount.textContent = `${totalMatches} ${totalMatches === 1 ? "documento" : "documentos"}`;
    }
}

function getRepositorySupportMatches() {
    const matchingDocuments = repositorySupportCards.filter((card) => card.dataset.supportMatch === "true");

    if (matchingDocuments.length > repositorySupportDocumentLimit && repositorySupportMoreCard) {
        return [...matchingDocuments.slice(0, repositorySupportDocumentLimit), repositorySupportMoreCard];
    }

    return matchingDocuments.slice(0, repositorySupportLimit);
}

function renderRepositorySupportCarousel(documents = getRepositorySupportMatches()) {
    const lastStartPosition = Math.max(0, documents.length - repositorySupportPageSize);
    repositorySupportCurrentPage = Math.min(repositorySupportCurrentPage, lastStartPosition);
    const pageStart = repositorySupportCurrentPage;
    const pageDocuments = documents.slice(pageStart, pageStart + repositorySupportPageSize);

    [...repositorySupportCards, repositorySupportMoreCard].filter(Boolean).forEach((card) => {
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
    if (repositorySupportMore) {
        repositorySupportMore.hidden = documents.length <= repositorySupportPageSize;
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
repositorySupportSearch?.addEventListener("input", filterRepositorySupportDocuments);
[repositorySupportCategory].forEach((filter) => {
    filter?.addEventListener("change", filterRepositorySupportDocuments);
});
[repositorySemester, repositoryTeacher, repositoryCategory, repositoryType, repositoryPao].forEach((filter) => {
    filter?.addEventListener("change", filterRepositoryProjects);
});

function closeRepositoryDropdown(dropdown) {
    const trigger = dropdown.querySelector("[data-dropdown-trigger]");
    const menu = dropdown.querySelector("[data-dropdown-menu]");
    if (menu) {
        menu.hidden = true;
    }
    if (trigger) {
        trigger.setAttribute("aria-expanded", "false");
    }
    dropdown.classList.remove("is-open");
}

function openRepositoryDropdown(dropdown) {
    const trigger = dropdown.querySelector("[data-dropdown-trigger]");
    const menu = dropdown.querySelector("[data-dropdown-menu]");
    if (menu) {
        menu.hidden = false;
    }
    if (trigger) {
        trigger.setAttribute("aria-expanded", "true");
    }
    dropdown.classList.add("is-open");
}

function syncRepositoryDropdown(dropdown, value, label) {
    const hiddenInput = dropdown.querySelector("[data-dropdown-input]");
    const labelNode = dropdown.querySelector("[data-dropdown-label]");
    const options = [...dropdown.querySelectorAll("[data-dropdown-option]")];

    if (hiddenInput) {
        hiddenInput.value = value;
        hiddenInput.dispatchEvent(new Event("change", { bubbles: true }));
    }
    if (labelNode) {
        labelNode.textContent = label;
    }
    options.forEach((option) => {
        option.classList.toggle("is-selected", option.dataset.value === value);
    });
}

repositoryDropdowns.forEach((dropdown) => {
    const trigger = dropdown.querySelector("[data-dropdown-trigger]");
    const menu = dropdown.querySelector("[data-dropdown-menu]");
    const options = [...dropdown.querySelectorAll("[data-dropdown-option]")];

    trigger?.addEventListener("click", (event) => {
        event.stopPropagation();
        const isOpen = dropdown.classList.contains("is-open");
        repositoryDropdowns.forEach((otherDropdown) => {
            if (otherDropdown !== dropdown) {
                closeRepositoryDropdown(otherDropdown);
            }
        });
        if (isOpen) {
            closeRepositoryDropdown(dropdown);
            return;
        }
        openRepositoryDropdown(dropdown);
    });

    options.forEach((option) => {
        option.addEventListener("click", () => {
            syncRepositoryDropdown(dropdown, option.dataset.value ?? "all", option.textContent?.trim() ?? "");
            closeRepositoryDropdown(dropdown);
        });
    });

    menu?.addEventListener("keydown", (event) => {
        if (event.key === "Escape") {
            closeRepositoryDropdown(dropdown);
            trigger?.focus();
        }
    });
});

document.addEventListener("click", (event) => {
    repositoryDropdowns.forEach((dropdown) => {
        if (!dropdown.contains(event.target)) {
            closeRepositoryDropdown(dropdown);
        }
    });
});

document.addEventListener("keydown", (event) => {
    if (event.key === "Escape") {
        repositoryDropdowns.forEach(closeRepositoryDropdown);
    }
});

filterRepositoryProjects();
filterRepositorySupportDocuments();
// Final de filtros del repositorio
