const shell = document.querySelector("#notificationsShell");
const preloader = document.querySelector("#notificationsPreloader");
const searchInput = document.querySelector("#notificationSearch");

const statusFilter = {
    get value() {
        return document.querySelector(".ed-tab[aria-current='page']")?.dataset.tabStatus || "all";
    },
    set value(val) {
        document.querySelectorAll(".ed-tab").forEach(tab => {
            const active = tab.dataset.tabStatus === val;
            tab.setAttribute("aria-selected", String(active));
            if (active) {
                tab.setAttribute("aria-current", "page");
            } else {
                tab.removeAttribute("aria-current");
            }
        });
    }
};

const typeFilter = document.querySelector("#notificationTypeFilter");
const filterControls = [...document.querySelectorAll("[data-filter-control]")];
const clearFiltersButton = document.querySelector("#clearNotificationFilters");
const activeFilter = document.querySelector("#notificationActiveFilter");
const activeFilterLabel = document.querySelector("#notificationActiveFilterLabel");
const trashToolbar = document.querySelector("#notificationTrashToolbar");
const selectAllTrash = document.querySelector("#selectAllTrashNotifications");
const trashSelectionCount = document.querySelector("#trashSelectionCount");
const restoreSelectedButton = document.querySelector("#restoreSelectedNotifications");
const deleteSelectedButton = document.querySelector("#deleteSelectedNotifications");
const emptyTrashButton = document.querySelector("#emptyNotificationTrash");
const groupsContainer = document.querySelector("#notificationGroups");
const paginationContainer = document.querySelector("#notificationPagination");
const emptyState = document.querySelector("#notificationsEmpty");
const errorState = document.querySelector("#notificationsLoadError");
const refreshButton = document.querySelector("#refreshNotifications");
const readAllButton = document.querySelector("#markAllNotificationsRead");
const deleteModal = document.querySelector("#notificationDeleteModal");
const detailModal = document.querySelector("#notificationDetailModal");
const toast = document.querySelector("#notificationToast");
const csrfToken = shell?.dataset.csrfToken || "";
const endpoints = JSON.parse(shell?.dataset.endpoints || "{}");
let requestController = null;
let pendingDeleteId = null;
let pendingDeleteMode = "archive";
let pendingBulkIds = [];
let returnFocus = null;
let currentDetailId = null;
let paginationState = (() => {
    try { return JSON.parse(paginationContainer?.dataset.pagination || "{}"); }
    catch { return {}; }
})();
let currentPage = Number(paginationState.page || 1);
let notificationsPerPage = Number(paginationState.per_page || 10);

function normalize(value) {
    return value.normalize("NFD").replace(/[\u0300-\u036f]/g, "").toLowerCase().trim();
}

function highlight(element, query) {
    const original = element.dataset.originalText ?? element.textContent ?? "";
    element.dataset.originalText = original;
    element.replaceChildren(document.createTextNode(original));
    const needle = normalize(query);
    if (!needle) return;
    const normalized = normalize(original);
    let cursor = 0;
    let index = normalized.indexOf(needle);
    if (index < 0) return;
    const fragment = document.createDocumentFragment();
    while (index >= 0) {
        if (index > cursor) fragment.append(document.createTextNode(original.slice(cursor, index)));
        const span = document.createElement("span");
        span.className = "search-highlight";
        span.textContent = original.slice(index, index + needle.length);
        fragment.append(span);
        cursor = index + needle.length;
        index = normalized.indexOf(needle, cursor);
    }
    fragment.append(document.createTextNode(original.slice(cursor)));
    element.replaceChildren(fragment);
}

function showToast(message, error = false, action = null) {
    if (!toast) return;
    toast.replaceChildren(document.createTextNode(message));
    if (action) {
        const button = document.createElement("button");
        button.type = "button";
        button.textContent = action.label;
        button.addEventListener("click", action.callback, { once: true });
        toast.append(button);
    }
    toast.classList.toggle("is-error", error);
    toast.hidden = false;
    window.clearTimeout(showToast.timer);
    showToast.timer = window.setTimeout(() => { toast.hidden = true; }, 3200);
}

async function request(url, options = {}) {
    const response = await fetch(url, { credentials: "same-origin", headers: { "X-Requested-With": "XMLHttpRequest", ...(options.method === "POST" ? { "X-CSRF-Token": csrfToken, "Content-Type": "application/x-www-form-urlencoded" } : {}) }, ...options });
    const payload = await response.json().catch(() => ({ success: false, message: "Respuesta no valida." }));
    if (!response.ok || !payload.success) throw new Error(payload.message || "No fue posible procesar la solicitud.");
    return payload;
}

function updateCounters(counters) {
    Object.entries(counters).forEach(([key, value]) => {
        document.querySelector(`[data-counter-card="${key}"] strong`)?.replaceChildren(document.createTextNode(String(value)));
    });
    const bell = document.querySelector(".notification-count");
    if (bell) { bell.textContent = String(counters.unread); bell.hidden = counters.unread === 0; }
    if (readAllButton) readAllButton.disabled = counters.unread === 0;
    const unread = Number(counters.unread || 0);
    const total = Number(counters.total || 0);
    const read = Math.max(0, total - unread);
    const progress = total > 0 ? Math.round((read / total) * 100) : 0;
    document.querySelector('[data-side-counter="unread"] strong')?.replaceChildren(document.createTextNode(String(unread)));
    document.querySelector('[data-side-counter="read"] strong')?.replaceChildren(document.createTextNode(String(read)));
    const progressBar = document.querySelector("#notificationReadProgress");
    progressBar?.style.setProperty("--progress", `${progress}%`);
    progressBar?.setAttribute("aria-valuenow", String(progress));
    const progressLabel = document.querySelector("#notificationReadProgressLabel");
    if (progressLabel) progressLabel.textContent = `${progress}%`;
    const lastUpdate = document.querySelector("#notificationLastUpdate");
    if (lastUpdate) lastUpdate.textContent = new Intl.DateTimeFormat("es-EC", { dateStyle: "short", timeStyle: "short" }).format(new Date());
}

function iconFor(type) {
    return { delivery: "fa-cloud-arrow-up", observation: "fa-comment-dots", status_change: "fa-circle-check", review: "fa-triangle-exclamation", reminder: "fa-clock", system: "fa-gear", tribunal: "fa-user-group", repository: "fa-database", comment: "fa-message" }[type] || "fa-bell";
}

function typeClass(type) {
    return { status_change: "status-approved", review: "correction", repository: "system", comment: "observation" }[type] || type;
}

function createButton(className, label, icon, action) {
    const button = document.createElement("button");
    button.type = "button"; button.className = className; button.dataset.notificationAction = action; button.setAttribute("aria-label", label); button.title = label;
    if (icon) { const i = document.createElement("i"); i.className = `${icon === "fa-ellipsis-vertical" ? "fa-solid" : "fa-regular"} ${icon}`; button.append(i); } else button.textContent = label;
    return button;
}

function createRow(notification) {
    const row = document.createElement("article");
    row.className = `notification-row type-${typeClass(notification.type)} ${notification.is_read ? "is-read" : "is-unread"}`;
    row.dataset.notificationId = notification.id; row.dataset.read = String(notification.is_read); row.dataset.type = notification.type;
    const dot = document.createElement((notification.deleted_at && statusFilter.value === "trash") ? "input" : "span");
    if (notification.deleted_at && statusFilter.value === "trash") { dot.type = "checkbox"; dot.className = "trash-notification-checkbox"; dot.value = notification.id; dot.setAttribute("aria-label", `Seleccionar ${notification.title}`); }
    else { dot.className = "unread-dot"; dot.setAttribute("aria-label", notification.is_read ? "Leida" : "No leida"); }
    const icon = document.createElement("span"); icon.className = "notification-type-icon";
    const iconGlyph = document.createElement("i"); iconGlyph.className = `fa-solid ${iconFor(notification.type)}`; icon.append(iconGlyph);
    const copy = document.createElement("div"); copy.className = "notification-copy";
    const head = document.createElement("div"); head.className = "notification-copy-heading";
    const category = document.createElement("span"); category.className = "notification-category"; category.textContent = notification.filter;
    const mobileTime = document.createElement("span"); mobileTime.className = "notification-date-mobile"; mobileTime.textContent = notification.time; head.append(category, mobileTime);
    const title = document.createElement("h3"); title.textContent = notification.title;
    const message = document.createElement("p"); message.textContent = notification.description;
    const project = document.createElement("span"); project.className = "notification-project";
    const folder = document.createElement("i"); folder.className = "fa-regular fa-folder-open";
    const projectName = document.createElement("span"); projectName.className = "notification-project-name"; projectName.textContent = notification.project; project.append(folder, projectName); copy.append(head, title, message, project);
    const meta = document.createElement("div"); meta.className = "notification-meta";
    const time = document.createElement("time"); time.append(document.createTextNode(notification.date)); const strong = document.createElement("strong"); strong.textContent = notification.time; time.append(strong);
    const actions = document.createElement("div"); actions.className = "notification-row-actions";
    const view = createButton("view-notification", "Detalle", "", "open-detail");
    const toggleLabel = notification.is_read ? "Marcar como no leida" : "Marcar como leida";
    const toggle = createButton("mark-notification", toggleLabel, notification.is_read ? "fa-eye" : "fa-eye-slash", "toggle-read"); toggle.setAttribute("aria-pressed", String(notification.is_read));
    const more = createButton("more-notification", "Mas opciones", "fa-ellipsis-vertical", "menu"); more.setAttribute("aria-haspopup", "menu"); more.setAttribute("aria-expanded", "false");
    const menu = document.createElement("div"); menu.className = "notification-context-menu"; menu.role = "menu"; menu.hidden = true;
    [["delete", "Archivar", "Ocultar de la bandeja sin eliminar"], ["destroy", "Mover a la papelera", "Se eliminara automaticamente en 30 dias"]].forEach(([action, label, description]) => { const b = document.createElement("button"); b.type = "button"; b.role = "menuitem"; b.dataset.menuAction = action; if (action === "destroy") b.className = "danger"; const text = document.createElement("span"); const strong = document.createElement("strong"); strong.textContent = label; const small = document.createElement("small"); small.textContent = description; text.append(strong, small); b.append(text); menu.append(b); });
    
    const isSentTab = statusFilter.value === "sent";
    if (notification.deleted_at && statusFilter.value === "trash") {
        const restore = createButton("view-notification", "Restaurar", "", "restore");
        actions.append(restore);
        row.classList.add("is-hidden-notification");
    } else if (isSentTab) {
        actions.append(view);
    } else {
        actions.append(view, toggle, more, menu);
    }
    meta.append(time, actions); row.append(dot, icon, copy, meta);
    [title, message, projectName].forEach((el) => highlight(el, searchInput?.value || ""));
    return row;
}

function renderGroups(groups, sectionCounters = {}) {
    groupsContainer?.querySelectorAll(".notification-group").forEach((group) => group.remove());
    let total = 0;
    Object.entries(groups).forEach(([name, notifications]) => {
        if (!notifications.length) return;
        total += notifications.length;
        const section = document.createElement("section"); section.className = "notification-group";
        const heading = document.createElement("div"); heading.className = "notification-group-heading";
        const h2 = document.createElement("h2"); h2.textContent = name;
        const count = document.createElement("span"); count.textContent = `${notifications.length} ${notifications.length === 1 ? "novedad" : "novedades"}`; heading.append(h2, count);
        const list = document.createElement("div"); list.className = "notification-list"; notifications.forEach((item) => list.append(createRow(item))); section.append(heading, list);
        groupsContainer?.insertBefore(section, paginationContainer || emptyState);
    });
    if (emptyState) { emptyState.hidden = total > 0; emptyState.querySelector("h2").textContent = searchInput?.value || statusFilter.value !== "all" || typeFilter?.value !== "all" ? "No se encontraron notificaciones con los filtros seleccionados." : "No tienes notificaciones por el momento."; }
    updateContextualCards(sectionCounters);
    updateTrashSelection();
}

function renderPagination(pagination = {}) {
    if (!paginationContainer) return;
    paginationState = pagination;
    currentPage = Number(pagination.page || 1);
    notificationsPerPage = Number(pagination.per_page || 10);
    paginationContainer.replaceChildren();
    const total = Number(pagination.total || 0);
    paginationContainer.hidden = total <= 10;
    if (total <= 10) return;

    const summary = document.createElement("p");
    summary.innerHTML = `Mostrando <strong>${pagination.to}</strong> de <strong>${total}</strong>`;
    const sizeLabel = document.createElement("label");
    sizeLabel.append(document.createTextNode("Mostrar "));
    const size = document.createElement("select");
    size.setAttribute("aria-label", "Cantidad de notificaciones visibles");
    [10, 25, 50, 75, 100].filter((value) => value <= total).forEach((value) => {
        const option = document.createElement("option"); option.value = value; option.textContent = value; option.selected = value === notificationsPerPage; size.append(option);
    });
    size.addEventListener("change", () => { notificationsPerPage = Number(size.value); currentPage = 1; loadNotifications(); });
    sizeLabel.append(size);

    const pages = document.createElement("div"); pages.className = "notification-pagination-pages";
    const addButton = (label, page, disabled = false, current = false) => {
        const button = document.createElement("button"); button.type = "button"; button.textContent = label; button.disabled = disabled; button.classList.toggle("is-current", current); button.setAttribute("aria-label", current ? `Pagina ${page}, actual` : `Ir a la pagina ${page}`); if (current) button.setAttribute("aria-current", "page");
        button.addEventListener("click", () => { currentPage = page; loadNotifications(); groupsContainer?.scrollIntoView({ behavior: "smooth", block: "start" }); }); pages.append(button);
    };
    addButton("‹", currentPage - 1, currentPage <= 1);
    const pageCount = Number(pagination.pages || 1);
    const pageItems = pageCount <= 5
        ? Array.from({ length: pageCount }, (_, index) => index + 1)
        : currentPage <= 3
            ? [1, 2, 3, "ellipsis", pageCount]
            : currentPage >= pageCount - 2
                ? [1, "ellipsis", pageCount - 2, pageCount - 1, pageCount]
                : [1, "ellipsis", currentPage - 1, currentPage, currentPage + 1, "ellipsis", pageCount];
    pageItems.forEach((page) => {
        if (page === "ellipsis") {
            const ellipsis = document.createElement("span"); ellipsis.className = "pagination-ellipsis"; ellipsis.textContent = "…"; ellipsis.setAttribute("aria-hidden", "true"); pages.append(ellipsis);
        } else addButton(String(page), page, false, page === currentPage);
    });
    addButton("›", currentPage + 1, currentPage >= pageCount);
    paginationContainer.append(summary, sizeLabel, pages);
}

function selectedTrashIds() {
    return [...document.querySelectorAll(".trash-notification-checkbox:checked")].map((checkbox) => checkbox.value);
}

function updateTrashSelection() {
    const checkboxes = [...document.querySelectorAll(".trash-notification-checkbox")];
    const selected = selectedTrashIds();
    if (trashSelectionCount) trashSelectionCount.textContent = `${selected.length} ${selected.length === 1 ? "seleccionada" : "seleccionadas"}`;
    if (restoreSelectedButton) restoreSelectedButton.disabled = selected.length === 0;
    if (deleteSelectedButton) deleteSelectedButton.disabled = selected.length === 0;
    if (emptyTrashButton) emptyTrashButton.disabled = checkboxes.length === 0;
    if (selectAllTrash) { selectAllTrash.checked = checkboxes.length > 0 && selected.length === checkboxes.length; selectAllTrash.indeterminate = selected.length > 0 && selected.length < checkboxes.length; }
    if (statusFilter.value === "trash") setSummaryCard("week", "Seleccionadas", selected.length);
}

function setSummaryCard(key, label, value) {
    const card = document.querySelector(`[data-counter-card="${key}"]`);
    if (!card) return;
    card.querySelector("strong").textContent = String(value);
    card.querySelector("div span").textContent = label;
}

function updateContextualCards(sectionCounters) {
    const mode = statusFilter.value;
    if (mode === "hidden") {
        setSummaryCard("unread", "Archivadas", sectionCounters.total || 0);
        setSummaryCard("today", "No leídas", sectionCounters.unread || 0);
        setSummaryCard("week", "Esta semana", sectionCounters.week || 0);
        setSummaryCard("total", "Total archivadas", sectionCounters.total || 0);
    } else if (mode === "trash") {
        setSummaryCard("unread", "En papelera", sectionCounters.total || 0);
        setSummaryCard("today", "Próximas a eliminarse", sectionCounters.expiring || 0);
        setSummaryCard("week", "Seleccionadas", selectedTrashIds().length);
        setSummaryCard("total", "Total", sectionCounters.total || 0);
    } else {
        setSummaryCard("unread", "No leídas", document.querySelector('[data-counter-card="unread"] strong')?.textContent || 0);
        document.querySelector('[data-counter-card="today"] div span').textContent = "Hoy";
        document.querySelector('[data-counter-card="week"] div span').textContent = "Esta semana";
        document.querySelector('[data-counter-card="total"] div span').textContent = "Total activas";
    }
}

async function loadNotifications(showMessage = false) {
    requestController?.abort(); requestController = new AbortController();
    refreshButton?.setAttribute("disabled", ""); refreshButton?.querySelector("i")?.classList.add("is-spinning");
    const showingHidden = statusFilter.value === "hidden";
    const showingTrash = statusFilter.value === "trash";
    
    const projectFilter = document.querySelector("#notificationProjectFilter");
    const dateFilter = document.querySelector("#notificationDateFilter");
    
    const params = new URLSearchParams({
        search: searchInput?.value || "",
        type: typeFilter?.value === "all" ? "" : typeFilter?.value || "",
        status: ["read", "unread", "sent"].includes(statusFilter.value) ? statusFilter.value : "",
        hidden: showingHidden ? "1" : "0",
        trash: showingTrash ? "1" : "0",
        project_id: projectFilter?.value || "0",
        date: dateFilter?.value || "",
        notification_page: String(currentPage),
        notifications_per_page: String(notificationsPerPage)
    });
    try {
        const payload = await request(`${endpoints.list}&${params}`, { signal: requestController.signal });
        updateCounters(payload.data.counters); renderGroups(payload.data.groups, payload.data.sectionCounters); renderPagination(payload.data.pagination); if (errorState) errorState.hidden = true;
        if (showMessage) showToast(payload.message);
    } catch (error) {
        if (error.name !== "AbortError") { if (errorState) errorState.hidden = false; showToast(error.message, true); }
    } finally {
        refreshButton?.removeAttribute("disabled"); refreshButton?.querySelector("i")?.classList.remove("is-spinning");
    }
}

function debounce(callback, delay) { let timer; return (...args) => { clearTimeout(timer); timer = setTimeout(() => callback(...args), delay); }; }
const debouncedLoad = debounce(() => loadNotifications(), 300);
searchInput?.addEventListener("input", () => { currentPage = 1; updateFilterState(); debouncedLoad(); });

function closeFilterMenus(restoreFocus = false) {
    filterControls.forEach((control) => {
        const type = control.dataset.filterControl;
        
        if (type === "date") {
            return;
        }
        const menu = control.querySelector(".notification-filter-menu");
        const trigger = control.querySelector(".notification-filter-trigger");
        if (!menu || !trigger) {
            console.warn("Filter control missing expected elements:", control);
            return;
        }
        menu.hidden = true;
        trigger.setAttribute("aria-expanded", "false");
        if (restoreFocus && control.contains(document.activeElement)) trigger.focus();
    });
}

function updateFilterState() {
    const active = [];

    filterControls.forEach((control) => {
        const type = control.dataset.filterControl;

        // El filtro de fecha no usa select ni menú desplegable
        if (type === "date") {
            return;
        }

        const select = control.querySelector("select");
        const triggerLabel = control.querySelector(
            ".notification-filter-trigger > span"
        );

        if (!select) {
            console.warn(
                "Filter control missing select element:",
                control
            );
            return;
        }

        const selectedOption = control.querySelector(
            `[data-filter-value="${CSS.escape(select.value)}"]`
        );

        const prefix = type === "project" ? "Proyecto" : "Tipo";

        const label =
            selectedOption?.querySelector("span:nth-child(2)")?.textContent ||
            "Todos";

        if (triggerLabel) {
            triggerLabel.textContent = `${prefix}: ${label}`;
        }

        if (select.value !== "all" && select.value !== "0") {
            active.push(`${prefix}: ${label}`);
        }
    });

    const dateFilter = document.querySelector("#notificationDateFilter");

    if (dateFilter?.value) {
        active.push(`Fecha: ${dateFilter.value}`);
    }

    if (activeFilter && activeFilterLabel) {
        activeFilter.hidden = active.length === 0;
        activeFilterLabel.textContent = active.join(" · ");
    }

    if (trashToolbar && statusFilter) {
        trashToolbar.hidden = statusFilter.value !== "trash";
    }

    if (clearFiltersButton) {
        clearFiltersButton.hidden =
            !searchInput?.value.trim() &&
            active.length === 0;
    }
}

function selectFilter(control, value, requestUpdate = true) {
    const select = control.querySelector("select"); const menu = control.querySelector(".notification-filter-menu");
    select.value = value;
    menu.querySelectorAll("[data-filter-value]").forEach((option) => option.setAttribute("aria-selected", String(option.dataset.filterValue === value)));
    closeFilterMenus(); updateFilterState();
    if (requestUpdate) { currentPage = 1; loadNotifications(); }
}

filterControls.forEach((control) => {
    const type = control.dataset.filterControl;

    // El filtro de fecha usa un input, no un menú desplegable.
    if (type === "date") {
        const dateInput = control.querySelector(
            "#notificationDateFilter, input[type='date']"
        );

        if (dateInput) {
            dateInput.addEventListener("change", () => {
                updateFilterState();

                // Si tu archivo ya tiene una función para recargar
                // la lista, déjala aquí.
                // Ejemplo:
                // loadNotifications();
            });
        }

        return;
    }

    const trigger = control.querySelector(
        ".notification-filter-trigger"
    );

    const menu = control.querySelector(
        ".notification-filter-menu"
    );

    if (!trigger || !menu) {
        console.warn(
            "Dropdown filter missing trigger or menu element:",
            control
        );
        return;
    }

    trigger.addEventListener("click", () => {
        const open = menu.hidden;

        closeFilterMenus();

        menu.hidden = !open;
        trigger.setAttribute(
            "aria-expanded",
            String(open)
        );

        if (open) {
            menu.querySelector(
                '[aria-selected="true"]'
            )?.focus();
        }
    });

    menu.addEventListener("click", (event) => {
        const option = event.target.closest(
            "[data-filter-value]"
        );

        if (option) {
            selectFilter(
                control,
                option.dataset.filterValue
            );
        }
    });

    menu.addEventListener("keydown", (event) => {
        const options = [
            ...menu.querySelectorAll(
                "[data-filter-value]"
            )
        ];

        const current = options.indexOf(
            document.activeElement
        );

        if (
            event.key === "ArrowDown" ||
            event.key === "ArrowUp"
        ) {
            event.preventDefault();

            const direction =
                event.key === "ArrowDown" ? 1 : -1;

            const nextIndex =
                (
                    current +
                    direction +
                    options.length
                ) % options.length;

            options[nextIndex]?.focus();
        }

        if (event.key === "Home") {
            event.preventDefault();
            options[0]?.focus();
        }

        if (event.key === "End") {
            event.preventDefault();
            options.at(-1)?.focus();
        }

        if (
            event.key === "Enter" ||
            event.key === " "
        ) {
            event.preventDefault();
            document.activeElement?.click();
        }
    });
});

function clearAllFilters() {
    searchInput.value = "";
    currentPage = 1;
    filterControls.forEach((control) => {
        const defaultValue = control.dataset.filterControl === "project" ? "0" : "all";
        selectFilter(control, defaultValue, false);
    });
    const dateFilter = document.querySelector("#notificationDateFilter");
    if (dateFilter) dateFilter.value = "";
    updateFilterState();
    loadNotifications();
    searchInput.focus();
}
clearFiltersButton?.addEventListener("click", clearAllFilters);
document.querySelector("#clearActiveNotificationFilter")?.addEventListener("click", clearAllFilters);
updateFilterState();
renderPagination(paginationState);
selectAllTrash?.addEventListener("change", () => { document.querySelectorAll(".trash-notification-checkbox").forEach((checkbox) => { checkbox.checked = selectAllTrash.checked; }); updateTrashSelection(); });
groupsContainer?.addEventListener("change", (event) => { if (event.target.matches(".trash-notification-checkbox")) updateTrashSelection(); });
refreshButton?.addEventListener("click", () => loadNotifications(true));
document.querySelector("#retryNotifications")?.addEventListener("click", () => loadNotifications());

async function postAction(endpoint, id, button, showSuccess = true) {
    button?.setAttribute("disabled", "");
    try {
        const body = new URLSearchParams(); if (id) body.set("notification_id", id);
        const payload = await request(endpoint, { method: "POST", body });
        updateCounters(payload.data.counters || {}); if (showSuccess) showToast(payload.message); return payload;
    } catch (error) { showToast(error.message, true); throw error; }
    finally { button?.removeAttribute("disabled"); }
}

async function postTrashBulk(action, ids, button, showSuccess = true) {
    button?.setAttribute("disabled", "");
    try {
        const body = new URLSearchParams({ bulk_action: action });
        ids.forEach((id) => body.append("notification_ids[]", id));
        const payload = await request(endpoints["trash-bulk"], { method: "POST", body });
        updateCounters(payload.data.counters || {});
        if (showSuccess) showToast(payload.message);
        return payload;
    } catch (error) { showToast(error.message, true); throw error; }
    finally { button?.removeAttribute("disabled"); updateTrashSelection(); }
}

readAllButton?.addEventListener("click", async () => { try { await postAction(endpoints["read-all"], null, readAllButton); await loadNotifications(); } catch {} });

restoreSelectedButton?.addEventListener("click", async () => {
    const ids = selectedTrashIds();
    if (!ids.length) return;
    try { await postTrashBulk("restore", ids, restoreSelectedButton); await loadNotifications(); } catch {}
});

deleteSelectedButton?.addEventListener("click", () => {
    pendingBulkIds = selectedTrashIds();
    if (!pendingBulkIds.length) return;
    pendingDeleteMode = "bulk-delete";
    document.querySelector("#notificationDeleteTitle").textContent = "¿Eliminar definitivamente las notificaciones seleccionadas?";
    document.querySelector("#notificationDeleteText").textContent = `Se eliminaran ${pendingBulkIds.length} notificaciones. Esta accion no se puede deshacer.`;
    document.querySelector("#confirmDeleteNotification").textContent = "Eliminar seleccionadas";
    openModal(deleteModal, deleteSelectedButton);
});

emptyTrashButton?.addEventListener("click", () => {
    pendingDeleteMode = "empty-trash";
    document.querySelector("#notificationDeleteTitle").textContent = "¿Vaciar toda la papelera?";
    document.querySelector("#notificationDeleteText").textContent = "Todas las notificaciones de la papelera se eliminaran definitivamente. Esta accion no se puede deshacer.";
    document.querySelector("#confirmDeleteNotification").textContent = "Vaciar papelera";
    openModal(deleteModal, emptyTrashButton);
});

function closeMenus() { document.querySelectorAll(".notification-context-menu:not([hidden])").forEach((menu) => { menu.hidden = true; menu.previousElementSibling?.setAttribute("aria-expanded", "false"); }); }
function openModal(modal, opener) { returnFocus = opener; modal.hidden = false; document.body.classList.add("modal-open"); modal.querySelector("button")?.focus(); }
function closeModal(modal) { modal.hidden = true; document.body.classList.remove("modal-open"); returnFocus?.focus(); }

groupsContainer?.addEventListener("click", async (event) => {
    const row = event.target.closest(".notification-row"); if (!row) return;
    const actionButton = event.target.closest("[data-notification-action]"); const menuButton = event.target.closest("[data-menu-action]");
    if (actionButton?.dataset.notificationAction === "menu") { const menu = row.querySelector(".notification-context-menu"); const opening = menu.hidden; closeMenus(); menu.hidden = !opening; actionButton.setAttribute("aria-expanded", String(opening)); return; }
    const action = menuButton?.dataset.menuAction || actionButton?.dataset.notificationAction; if (!action) return; closeMenus();
    if (action === "toggle-read") { const read = row.dataset.read === "true"; try { await postAction(read ? endpoints.unread : endpoints.read, row.dataset.notificationId, actionButton || menuButton); await loadNotifications(); } catch {} }
    if (action === "restore") { try { await postAction(endpoints.restore, row.dataset.notificationId, actionButton); await loadNotifications(); } catch {} }
    if (action === "delete" || action === "destroy") {
        pendingDeleteId = row.dataset.notificationId;
        pendingDeleteMode = action === "destroy" ? "destroy" : "archive";
        const moveToTrash = pendingDeleteMode === "destroy";
        document.querySelector("#notificationDeleteTitle").textContent = moveToTrash ? "¿Mover esta notificacion a la papelera?" : "¿Deseas archivar esta notificacion?";
        document.querySelector("#notificationDeleteText").textContent = moveToTrash ? "Podras restaurarla desde Papelera durante 30 dias. Despues se eliminara automaticamente." : "La notificacion saldra del listado principal, pero podras recuperarla desde el filtro Archivadas.";
        document.querySelector("#confirmDeleteNotification").textContent = moveToTrash ? "Mover a la papelera" : "Archivar";
        openModal(deleteModal, menuButton);
    }
    if (action === "detail" || action === "open-detail") {
        const opener = actionButton || menuButton;
        try {
            const payload = await postAction(endpoints.open, row.dataset.notificationId, opener, false);
            fillDetail(payload.data.detail, payload.data.url);
            openModal(detailModal, opener);
            await loadNotifications();
        } catch {}
    }
});

function fillDetail(item, destinationUrl = null) {
    currentDetailId = item.id || null;
    document.querySelector("#notificationModalType").textContent = item.type || "Notificacion";
    document.querySelector("#notificationModalTitle").textContent = item.title || "";
    document.querySelector("#notificationModalMessage").textContent = item.message || "";
    document.querySelector("#notificationModalProject").textContent = item.project_name || item.project || "Notificacion general";
    document.querySelector("#notificationModalDate").textContent = item.created_at || "";
    document.querySelector("#notificationModalStatus").textContent = item.is_read ? "Leida" : "No leida";
    
    const isSentTab = statusFilter.value === "sent";
    document.querySelector("#notificationModalStatus").hidden = isSentTab;
    const markUnreadBtn = document.querySelector("#notificationModalMarkUnread");
    if (markUnreadBtn) markUnreadBtn.hidden = isSentTab;

    const destination = document.querySelector("#notificationModalDestination");
    if (destination) {
        destination.hidden = !destinationUrl;
        destination.href = destinationUrl || "#";
        destination.querySelector("span").textContent = item.action_label || "Ir a la seccion relacionada";
    }
}

document.querySelector("#notificationModalMarkUnread")?.addEventListener("click", async (event) => {
    if (!currentDetailId) return;
    try {
        await postAction(endpoints.unread, currentDetailId, event.currentTarget);
        document.querySelector("#notificationModalStatus").textContent = "No leida";
        await loadNotifications();
    } catch {}
});

document.querySelector("#confirmDeleteNotification")?.addEventListener("click", async (event) => {
    const archivedId = pendingDeleteId;
    try {
        if (pendingDeleteMode === "bulk-delete") {
            await postTrashBulk("delete", pendingBulkIds, event.currentTarget, false);
            closeModal(deleteModal); await loadNotifications(); showToast("Notificaciones eliminadas definitivamente."); return;
        }
        if (pendingDeleteMode === "empty-trash") {
            await postAction(endpoints["trash-empty"], null, event.currentTarget, false);
            closeModal(deleteModal); await loadNotifications(); showToast("Papelera vaciada."); return;
        }
        const moveToTrash = pendingDeleteMode === "destroy";
        await postAction(moveToTrash ? endpoints.destroy : endpoints.delete, archivedId, event.currentTarget, false);
        closeModal(deleteModal);
        await loadNotifications();
        if (moveToTrash) {
            showToast("Notificacion movida a la papelera.", false, {
                label: "Deshacer",
                callback: async () => {
                    try { await postAction(endpoints.restore, archivedId, null); await loadNotifications(); } catch {}
                },
            });
        } else {
            showToast("Notificacion archivada.", false, {
                label: "Deshacer",
                callback: async () => {
                    try { await postAction(endpoints.restore, archivedId, null); await loadNotifications(); } catch {}
                },
            });
        }
    } catch {}
});

document.querySelectorAll("[data-modal-close]").forEach((button) => button.addEventListener("click", () => closeModal(button.closest(".notification-modal-overlay"))));
document.addEventListener("click", (event) => { if (!event.target.closest(".notification-row-actions")) closeMenus(); if (!event.target.closest(".notification-filter-custom")) closeFilterMenus(); });
document.addEventListener("keydown", (event) => { if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === "k") { event.preventDefault(); searchInput?.focus(); } if (event.key === "Escape") { closeMenus(); const hasOpenFilter = filterControls.some((control) => !control.querySelector(".notification-filter-menu").hidden); if (hasOpenFilter) closeFilterMenus(true); else if (detailModal && !detailModal.hidden) closeModal(detailModal); else if (deleteModal && !deleteModal.hidden) closeModal(deleteModal); else if (document.activeElement === searchInput) { searchInput.value = ""; searchInput.blur(); updateFilterState(); loadNotifications(); } } });

let notificationsRevealed = false;

function revealNotifications() {
    if (notificationsRevealed) return;
    notificationsRevealed = true;
    preloader?.classList.add("is-leaving");
    shell?.classList.remove("is-loading");
    shell?.classList.add("is-ready");
    shell?.setAttribute("aria-busy", "false");
    window.setTimeout(() => preloader?.setAttribute("hidden", ""), 280);
}

window.setTimeout(revealNotifications, 650);
window.setTimeout(revealNotifications, 2500);

// Bindings for tabs navigation
document.querySelectorAll(".ed-tab").forEach(tab => {
    tab.addEventListener("click", (e) => {
        e.preventDefault();
        statusFilter.value = tab.dataset.tabStatus;
        currentPage = 1;
        
        // Hide/show trash toolbar depending on tab
        if (trashToolbar) {
            trashToolbar.hidden = tab.dataset.tabStatus !== "trash";
        }
        
        // Manage active styling classes manually if necessary
        document.querySelectorAll(".ed-tab").forEach(t => t.classList.remove("active"));
        tab.classList.add("active");
        
        loadNotifications();
    });
});

// Date filter binding
document.querySelector("#notificationDateFilter")?.addEventListener("change", () => {
    currentPage = 1;
    updateFilterState();
    loadNotifications();
});

// Admin New Notification button and modal logic
const btnNewNotification = document.querySelector("#btnNewNotification");
const createModal = document.querySelector("#notificationCreateModal");
const createForm = document.querySelector("#notificationCreateForm");
const scopeSelect = document.querySelector("#newNotificationScope");
const errorMsg = document.querySelector("#newNotificationError");

if (btnNewNotification && createModal) {
    btnNewNotification.addEventListener("click", () => {
        createForm?.reset();
        if (scopeSelect) {
            scopeSelect.dispatchEvent(new Event("change"));
        }
        if (errorMsg) errorMsg.hidden = true;
        openModal(createModal, btnNewNotification);
    });
}

if (scopeSelect) {
    scopeSelect.addEventListener("change", () => {
        const scope = scopeSelect.value;
        document.querySelector("#groupScopeUser").hidden = scope !== "user";
        document.querySelector("#groupScopeRole").hidden = scope !== "role";
        document.querySelector("#groupScopeProject").hidden = scope !== "project";
        document.querySelector("#groupScopeAll").hidden = scope !== "all";

        document.querySelector("#newNotificationUser").required = scope === "user";
        document.querySelector("#newNotificationRole").required = scope === "role";
        document.querySelector("#newNotificationProject").required = scope === "project";
        document.querySelector("#confirmAllCheckbox").required = scope === "all";
    });
}

if (createForm) {
    createForm.addEventListener("submit", async (e) => {
        e.preventDefault();
        const btnSubmit = document.querySelector("#btnSubmitNewNotification");
        if (btnSubmit) btnSubmit.disabled = true;
        if (errorMsg) errorMsg.hidden = true;

        try {
            const formData = new FormData(createForm);
            const body = new URLSearchParams(formData);
            const endpoint = endpoints["admin-send"] || "";
            if (!endpoint) throw new Error("Endpoint de envío no configurado.");
            
            const adminCsrf = createForm.querySelector("[name='_csrf']")?.value || csrfToken;
            const response = await fetch(endpoint, {
                method: "POST",
                credentials: "same-origin",
                headers: {
                    "Content-Type": "application/x-www-form-urlencoded",
                    "X-CSRF-Token": adminCsrf,
                    "X-Requested-With": "XMLHttpRequest"
                },
                body
            });

            const payload = await response.json().catch(() => ({ success: false, message: "Respuesta no valida." }));
            if (!response.ok || !payload.success) throw new Error(payload.message || "No fue posible enviar la notificacion.");

            showToast(payload.message);
            closeModal(createModal);
            loadNotifications();
        } catch (error) {
            if (errorMsg) {
                errorMsg.textContent = error.message;
                errorMsg.hidden = false;
            }
        } finally {
            if (btnSubmit) btnSubmit.disabled = false;
        }
    });
}
