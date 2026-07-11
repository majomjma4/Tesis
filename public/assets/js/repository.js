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
const repositorySupportCards = [...document.querySelectorAll(".repository-support .repository-card:not(.repository-more-card)")];
const repositorySupportMoreCard = document.querySelector("#repositorySupportMoreCard");
const repositoryCount = document.querySelector("#repositoryCount");
const repositorySupportCount = document.querySelector("#repositorySupportCount");
const repositorySupportPrev = document.querySelector("#repositorySupportPrev");
const repositorySupportNext = document.querySelector("#repositorySupportNext");
const repositorySupportMore = document.querySelector("#repositorySupportMore");
const repositoryEmpty = document.querySelector("#repositoryEmpty");
const repositoryDropdowns = [...document.querySelectorAll("[data-dropdown]")];
const repositorySupportPageSize = 4;
const repositorySupportLimit = 12;
const repositorySupportDocumentLimit = repositorySupportLimit - 1;
let repositorySupportCurrentPage = 0;

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
        const matchesSearch = normalizeRepositoryText(card.textContent ?? "").includes(searchValue);
        const matchesSemester = semesterValue === "all" || card.dataset.semester === semesterValue;
        const matchesTeacher = teacherValue === "all" || card.dataset.teacher === teacherValue;
        const matchesCategory = categoryValue === "all" || card.dataset.category === categoryValue;
        const matchesType = typeValue === "all" || card.dataset.type === typeValue;
        const matchesPao = paoValue === "all" || card.dataset.pao === paoValue;
        const isVisible = matchesSearch && matchesSemester && matchesTeacher && matchesCategory && matchesType && matchesPao;

        card.hidden = !isVisible;
        if (isVisible) {
            visibleProjects += 1;
        }
    });

    if (repositoryCount) {
        repositoryCount.textContent = `${visibleProjects} ${visibleProjects === 1 ? "resultado" : "resultados"}`;
    }
    if (repositoryEmpty) {
        repositoryEmpty.hidden = visibleProjects !== 0;
    }
}

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
