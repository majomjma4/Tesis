const shell = document.querySelector("#notificationsShell");
const preloader = document.querySelector("#notificationsPreloader");
const searchInput = document.querySelector("#notificationSearch");

const statusFilter = document.querySelector("#notificationStatusFilter");

const typeFilter = document.querySelector("#notificationTypeFilter");
const filterControls = [...document.querySelectorAll("[data-filter-control]")];
const activeFilter = document.querySelector("#notificationActiveFilter");
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
if (toast?.parentElement !== document.body) document.body.append(toast);
const dateTrigger = document.querySelector("#notificationDateTrigger");
const datePopover = document.querySelector("#notificationDatePopover");
const dateFromInput = document.querySelector("#notificationDateFrom");
const dateToInput = document.querySelector("#notificationDateTo");
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

function hideToast() {
    window.clearTimeout(showToast.timer);
    if (!toast) return;
    toast.hidden = true;
    toast.classList.remove("is-error");
    toast.replaceChildren();
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
    showToast.timer = window.setTimeout(hideToast, 3400);
}

async function request(url, options = {}) {
    const response = await fetch(url, { credentials: "same-origin", headers: { "X-Requested-With": "XMLHttpRequest", ...(options.method === "POST" ? { "X-CSRF-Token": csrfToken, "Content-Type": "application/x-www-form-urlencoded" } : {}) }, ...options });
    const payload = await response.json().catch(() => ({ success: false, message: "Respuesta no valida." }));
    if (!response.ok || !payload.success) throw new Error(payload.message || "No fue posible procesar la solicitud.");
    return payload;
}

function updateCounters(counters) {
    Object.entries(counters).forEach(([key, value]) => {
        document.querySelector(`[data-counter-card="${key}"] .notification-stat-value`)?.replaceChildren(document.createTextNode(String(value)));
    });
    if (typeof window.updateTopbarNotificationCount === "function") window.updateTopbarNotificationCount(counters.unread);
    const unread = Number(counters.unread || 0);
    if (readAllButton) { readAllButton.disabled = unread === 0; readAllButton.hidden = unread === 0; }
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
    return { delivery: "fa-cloud-arrow-up", observation: "fa-comment-dots", status_change: "fa-circle-check", review: "fa-triangle-exclamation", reminder: "fa-clock", system: "fa-gear", tribunal: "fa-user-group", repository: "fa-database", comment: "fa-message", adjustment: "fa-pen-to-square" }[type] || "fa-bell";
}

function notificationTypeLabel(type) {
    return { delivery: "Entrega", observation: "Observación", status_change: "Cambio de estado", review: "Revisión", reminder: "Recordatorio", system: "Sistema", tribunal: "Tribunal", repository: "Repositorio", comment: "Comentario", adjustment: "Solicitud de cambios" }[type] || "Notificación";
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
    [["delete", "Archivar", "Ocultar de la bandeja sin eliminar"], ["destroy", "Mover a la papelera", "Se eliminara automaticamente en 60 dias"]].forEach(([action, label, description]) => { const b = document.createElement("button"); b.type = "button"; b.role = "menuitem"; b.dataset.menuAction = action; if (action === "destroy") b.className = "danger"; const text = document.createElement("span"); const strong = document.createElement("strong"); strong.textContent = label; const small = document.createElement("small"); small.textContent = description; text.append(strong, small); b.append(text); menu.append(b); });
    
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
    if (emptyState) {
        emptyState.hidden = total > 0;
        const emptyH2 = emptyState.querySelector("h2");
        if (emptyH2) {
            let msg = "No tienes notificaciones por el momento.";
            if (statusFilter.value === "trash") msg = "La papelera está vacía.";
            else if (statusFilter.value === "hidden") msg = "No tienes notificaciones archivadas.";
            else if (statusFilter.value === "sent") msg = "No has enviado notificaciones.";
            else if (searchInput?.value || statusFilter.value !== "all" || typeFilter?.value !== "all") msg = "No se encontraron notificaciones con los filtros seleccionados.";
            emptyH2.textContent = msg;
        }
    }
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
}

function updateContextualCards() {}

async function loadNotifications(showMessage = false) {
    requestController?.abort(); requestController = new AbortController();
    refreshButton?.setAttribute("disabled", ""); refreshButton?.querySelector("i")?.classList.add("is-spinning");
    const showingHidden = statusFilter.value === "hidden";
    const showingTrash = statusFilter.value === "trash";
    
    const params = new URLSearchParams({
        search: searchInput?.value || "",
        type: typeFilter?.value === "all" ? "" : typeFilter?.value || "",
        status: ["read", "unread", "sent"].includes(statusFilter.value) ? statusFilter.value : "",
        hidden: showingHidden ? "1" : "0",
        trash: showingTrash ? "1" : "0",
        date_from: dateFromInput?.value || "",
        date_to: dateToInput?.value || "",
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

        const selectedOption = control.querySelector(`[data-filter-value="${CSS.escape(select.value)}"]`);

        const prefix = type === "project" ? "Proyecto" : "Tipo";

        const label =
            selectedOption?.querySelector("span:nth-child(2)")?.textContent || select.options[select.selectedIndex]?.textContent || "Todos";

        if (triggerLabel) {
            triggerLabel.textContent = `${prefix}: ${label}`;
        }

        if (select.value !== "all" && select.value !== "0") {
            active.push(`${prefix}: ${label}`);
        }
    });

    if (dateFromInput?.value || dateToInput?.value) active.push(`Fecha: ${dateFromInput?.value || "…"} — ${dateToInput?.value || "…"}`);

    if (activeFilter && activeFilterLabel) {
        activeFilter.hidden = active.length === 0;
        activeFilterLabel.textContent = active.join(" · ");
    }

    if (trashToolbar && statusFilter) {
        trashToolbar.hidden = statusFilter.value !== "trash";
    }

}

function updateFilterState() {
    const active = [];
    if (statusFilter?.value !== "all") active.push({ label: `Mostrar: ${statusFilter.options[statusFilter.selectedIndex]?.textContent || "Todas"}`, clear: () => { statusFilter.value = "all"; } });
    if (typeFilter?.value && typeFilter.value !== "all") active.push({ label: `Tipo: ${typeFilter.options[typeFilter.selectedIndex]?.textContent}`, clear: () => { typeFilter.value = "all"; } });
    if (dateFromInput?.value || dateToInput?.value) active.push({ label: `Fecha: ${dateFromInput?.value || "…"} — ${dateToInput?.value || "…"}`, clear: () => { if (dateFromInput) dateFromInput.value = ""; if (dateToInput) dateToInput.value = ""; } });
    if (activeFilter) { activeFilter.replaceChildren(); activeFilter.hidden = active.length === 0; active.forEach((item) => { const chip = document.createElement("button"); chip.type = "button"; chip.className = "notification-filter-chip"; chip.append(document.createTextNode(item.label)); const close = document.createElement("i"); close.className = "fa-solid fa-xmark"; close.setAttribute("aria-hidden", "true"); chip.append(close); chip.addEventListener("click", () => { item.clear(); currentPage = 1; updateFilterState(); loadNotifications(); }); activeFilter.append(chip); }); }
    if (trashToolbar) trashToolbar.hidden = statusFilter?.value !== "trash";
}

function selectFilter(control, value, requestUpdate = true) {
    const select = control.querySelector("select"); const menu = control.querySelector(".notification-filter-menu");
    select.value = value;
    menu?.querySelectorAll("[data-filter-value]").forEach((option) => option.setAttribute("aria-selected", String(option.dataset.filterValue === value)));
    closeFilterMenus(); updateFilterState();
    if (requestUpdate) { currentPage = 1; loadNotifications(); }
}

filterControls.forEach((control) => {
    const type = control.dataset.filterControl;

    // El rango de fechas se gestiona desde su popover independiente.
    if (type === "date") {
        return;
    }

    const trigger = control.querySelector(
        ".notification-filter-trigger"
    );

    const menu = control.querySelector(
        ".notification-filter-menu"
    );

    if (!trigger || !menu) {
        control.querySelector("select")?.addEventListener("change", () => { hideToast(); currentPage = 1; updateFilterState(); loadNotifications(); });
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
        const defaultValue = "all";
        if (control.dataset.filterControl !== "date") selectFilter(control, defaultValue, false);
    });
    if (dateFromInput) dateFromInput.value = "";
    if (dateToInput) dateToInput.value = "";
    updateFilterState();
    loadNotifications();
    searchInput.focus();
}
document.querySelector("#clearActiveNotificationFilter")?.addEventListener("click", clearAllFilters);
updateFilterState();
renderPagination(paginationState);
selectAllTrash?.addEventListener("change", () => { document.querySelectorAll(".trash-notification-checkbox").forEach((checkbox) => { checkbox.checked = selectAllTrash.checked; }); updateTrashSelection(); });
groupsContainer?.addEventListener("change", (event) => { if (event.target.matches(".trash-notification-checkbox")) updateTrashSelection(); });
refreshButton?.addEventListener("click", () => loadNotifications(true));
document.querySelector("#retryNotifications")?.addEventListener("click", () => loadNotifications());
window.addEventListener("resize", hideToast, { passive: true });

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
    const titleEl = document.querySelector("#notificationDeleteTitle");
    if (titleEl) titleEl.textContent = "¿Eliminar definitivamente las notificaciones seleccionadas?";
    const textEl = document.querySelector("#notificationDeleteText");
    if (textEl) textEl.textContent = `Se eliminaran ${pendingBulkIds.length} notificaciones. Esta accion no se puede deshacer.`;
    const confirmBtn = document.querySelector("#confirmDeleteNotification");
    if (confirmBtn) confirmBtn.textContent = "Eliminar seleccionadas";
    openModal(deleteModal, deleteSelectedButton);
});

emptyTrashButton?.addEventListener("click", () => {
    pendingDeleteMode = "empty-trash";
    const titleEl = document.querySelector("#notificationDeleteTitle");
    if (titleEl) titleEl.textContent = "¿Vaciar toda la papelera?";
    const textEl = document.querySelector("#notificationDeleteText");
    if (textEl) textEl.textContent = "Todas las notificaciones de la papelera se eliminaran definitivamente. Esta accion no se puede deshacer.";
    const confirmBtn = document.querySelector("#confirmDeleteNotification");
    if (confirmBtn) confirmBtn.textContent = "Vaciar papelera";
    openModal(deleteModal, emptyTrashButton);
});

function closeMenus() { document.querySelectorAll(".notification-context-menu:not([hidden])").forEach((menu) => { menu.hidden = true; menu.previousElementSibling?.setAttribute("aria-expanded", "false"); }); }
function openModal(modal, opener) { if (!modal) return; returnFocus = opener; modal.hidden = false; document.body.classList.add("modal-open"); modal.querySelector("button")?.focus(); }
function closeModal(modal) { if (!modal) return; modal.hidden = true; document.body.classList.remove("modal-open"); returnFocus?.focus(); }

groupsContainer?.addEventListener("click", async (event) => {
    const row = event.target.closest(".notification-row"); if (!row) return;
    const actionButton = event.target.closest("[data-notification-action]"); const menuButton = event.target.closest("[data-menu-action]");
    if (actionButton?.dataset.notificationAction === "menu") { const menu = row.querySelector(".notification-context-menu"); const opening = menu ? menu.hidden : false; closeMenus(); if (menu) menu.hidden = !opening; actionButton.setAttribute("aria-expanded", String(opening)); return; }
    const action = menuButton?.dataset.menuAction || actionButton?.dataset.notificationAction; if (!action) return; closeMenus();
    if (action === "toggle-read") { const read = row.dataset.read === "true"; try { await postAction(read ? endpoints.unread : endpoints.read, row.dataset.notificationId, actionButton || menuButton); await loadNotifications(); } catch {} }
    if (action === "restore") { try { await postAction(endpoints.restore, row.dataset.notificationId, actionButton); await loadNotifications(); } catch {} }
    if (action === "delete" || action === "destroy") {
        pendingDeleteId = row.dataset.notificationId;
        pendingDeleteMode = action === "destroy" ? "destroy" : "archive";
        const moveToTrash = pendingDeleteMode === "destroy";
        const titleEl = document.querySelector("#notificationDeleteTitle");
        if (titleEl) titleEl.textContent = moveToTrash ? "¿Mover esta notificacion a la papelera?" : "¿Deseas archivar esta notificacion?";
        const textEl = document.querySelector("#notificationDeleteText");
        if (textEl) textEl.textContent = moveToTrash ? "Podras restaurarla desde Papelera durante 60 dias. Despues se eliminara automaticamente." : "La notificacion saldra del listado principal, pero podras recuperarla desde el filtro Archivadas.";
        const confirmBtn = document.querySelector("#confirmDeleteNotification");
        if (confirmBtn) confirmBtn.textContent = moveToTrash ? "Mover a la papelera" : "Archivar";
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
    if (!item) return;
    currentDetailId = item.id || null;
    const typeEl = document.querySelector("#notificationModalType");
    if (typeEl) typeEl.textContent = notificationTypeLabel(item.type);
    const titleEl = document.querySelector("#notificationModalTitle");
    if (titleEl) titleEl.textContent = item.title || "";
    const messageEl = document.querySelector("#notificationModalMessage");
    if (messageEl) messageEl.textContent = item.message || "";
    const projectEl = document.querySelector("#notificationModalProject");
    if (projectEl) projectEl.textContent = item.project_name || item.project || "Notificacion general";
    const dateEl = document.querySelector("#notificationModalDate");
    if (dateEl) dateEl.textContent = item.created_at || "";
    const statusEl = document.querySelector("#notificationModalStatus");
    if (statusEl) {
        statusEl.textContent = item.is_read ? "Leida" : "No leida";
        statusEl.hidden = statusFilter.value === "sent";
    }
    
    const markUnreadBtn = document.querySelector("#notificationModalMarkUnread");
    if (markUnreadBtn) markUnreadBtn.hidden = statusFilter.value === "sent";

    const destination = document.querySelector("#notificationModalDestination");
    if (destination) {
        const isContextualDestination = (() => {
            if (!destinationUrl) return false;
            try {
                const url = new URL(destinationUrl, window.location.href);
                return url.origin === window.location.origin && /\/index\.php$/i.test(url.pathname) && url.searchParams.get("page") !== "notifications";
            } catch {
                return false;
            }
        })();
        destination.hidden = !isContextualDestination;
        destination.href = isContextualDestination ? destinationUrl : "#";
        const spanEl = destination.querySelector("span");
        if (spanEl) spanEl.textContent = item.action_label || "Ir a la seccion relacionada";
    }
}

document.querySelector("#notificationModalDestination")?.addEventListener("click", () => closeModal(detailModal));

document.querySelector("#notificationModalMarkUnread")?.addEventListener("click", async (event) => {
    if (!currentDetailId) return;
    try {
        await postAction(endpoints.unread, currentDetailId, event.currentTarget);
        const statusEl = document.querySelector("#notificationModalStatus");
        if (statusEl) statusEl.textContent = "No leida";
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
document.addEventListener("keydown", (event) => { if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === "k") { event.preventDefault(); searchInput?.focus(); } if (event.key === "Escape") { closeMenus(); const hasOpenFilter = filterControls.some((control) => { const menu = control.querySelector(".notification-filter-menu"); return menu && !menu.hidden; }); if (hasOpenFilter) closeFilterMenus(true); else if (detailModal && !detailModal.hidden) closeModal(detailModal); else if (deleteModal && !deleteModal.hidden) closeModal(deleteModal); else if (createModal && !createModal.hidden) closeModal(createModal); else if (document.activeElement === searchInput) { searchInput.value = ""; searchInput.blur(); updateFilterState(); loadNotifications(); } } });

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

dateTrigger?.addEventListener("click", () => {
    const open = Boolean(datePopover?.hidden);
    if (datePopover) datePopover.hidden = !open;
    dateTrigger.setAttribute("aria-expanded", String(open));
    if (open) dateFromInput?.focus();
});
document.querySelector("#applyNotificationDate")?.addEventListener("click", () => { hideToast(); currentPage = 1; updateFilterState(); loadNotifications(); if (datePopover) datePopover.hidden = true; dateTrigger?.setAttribute("aria-expanded", "false"); });
document.querySelector("#clearNotificationDate")?.addEventListener("click", () => { hideToast(); if (dateFromInput) dateFromInput.value = ""; if (dateToInput) dateToInput.value = ""; currentPage = 1; updateFilterState(); loadNotifications(); if (datePopover) datePopover.hidden = true; dateTrigger?.setAttribute("aria-expanded", "false"); });
document.addEventListener("click", (event) => { if (!event.target.closest(".notification-date-filter") && datePopover && !datePopover.hidden) { datePopover.hidden = true; dateTrigger?.setAttribute("aria-expanded", "false"); } });
document.addEventListener("keydown", (event) => { if (event.key === "Escape" && datePopover && !datePopover.hidden) { datePopover.hidden = true; dateTrigger?.setAttribute("aria-expanded", "false"); dateTrigger?.focus(); } });

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
        const userGroup = document.querySelector("#groupScopeUser");
        const roleGroup = document.querySelector("#groupScopeRole");
        const projectGroup = document.querySelector("#groupScopeProject");
        const allGroup = document.querySelector("#groupScopeAll");

        if (userGroup) userGroup.hidden = scope !== "user";
        if (roleGroup) roleGroup.hidden = scope !== "role";
        if (projectGroup) projectGroup.hidden = scope !== "project";
        if (allGroup) allGroup.hidden = scope !== "all";

        const userInput = document.querySelector("#newNotificationUser");
        const roleInput = document.querySelector("#newNotificationRole");
        const projectInput = document.querySelector("#newNotificationProject");
        const confirmAllInput = document.querySelector("#confirmAllCheckbox");

        if (userInput) { userInput.required = scope === "user"; if (scope !== "user") userInput.value = ""; }
        if (roleInput) { roleInput.required = scope === "role"; }
        if (projectInput) { projectInput.required = scope === "project"; if (scope !== "project") projectInput.value = ""; }
        if (confirmAllInput) { confirmAllInput.required = scope === "all"; if (scope !== "all") confirmAllInput.checked = false; }
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

// Flujo administrativo escalable: no precarga personas ni proyectos en el HTML.
const recipientSearch = document.querySelector("#newNotificationRecipientSearch");
const recipientResults = document.querySelector("#newNotificationRecipientResults");
const recipientChips = document.querySelector("#newNotificationRecipientChips");
const semesterSelect = document.querySelector("#newNotificationSemester");
const massGroup = document.querySelector("#groupScopeMass");
const recipientsGroup = document.querySelector("#groupScopeRecipients");
const semesterGroup = document.querySelector("#groupScopeSemester");
const massSummary = document.querySelector("#newNotificationMassSummary");
const sendSummary = document.querySelector("#newNotificationSendSummary");
const templateSelect = document.querySelector("#newNotificationTemplate");
const chosenRecipients = new Map();
let recipientTimer = null;
let recipientSearchVersion = 0;
const templates = { institutional:["Comunicado institucional",""], maintenance:["Mantenimiento programado del sistema","El sistema estará temporalmente fuera de servicio el día [fecha], desde [hora inicial] hasta [hora final]."], period:["Cambio de periodo académico","Se informa el cambio de periodo académico. Revisa las fechas y actividades correspondientes."], deadline:["Recordatorio de fecha límite","Te recordamos que la fecha límite es [fecha]."], call:["Convocatoria","Se comunica la apertura de una nueva convocatoria."], platform:["Actualización de plataforma","La plataforma ha sido actualizada. Revisa las novedades disponibles."], academic:["Asignación académica","Se ha registrado una nueva asignación académica para tu revisión."], important:["Aviso importante","Por favor revisa esta información importante."] };
function audienceKind(){return scopeSelect?.value.startsWith("teacher")?"teacher":"student";}
function renderChosenRecipients(){ if(!recipientChips)return; recipientChips.replaceChildren(); chosenRecipients.forEach((person)=>{const chip=document.createElement("button");chip.type="button";chip.className="notification-recipient-chip";chip.textContent=person.full_name;chip.setAttribute("aria-label",`Quitar a ${person.full_name}`);chip.addEventListener("click",()=>{chosenRecipients.delete(person.id);renderChosenRecipients();updateSendSummary();});recipientChips.append(chip);}); }
function updateSendSummary(){if(!sendSummary||!scopeSelect)return;const scope=scopeSelect.value;const n=chosenRecipients.size;if(scope.endsWith("_one"))sendSummary.textContent=n?`Se enviará esta notificación a ${[...chosenRecipients.values()][0].full_name}.`:"Selecciona un destinatario.";else if(scope.endsWith("_many"))sendSummary.textContent=n?`Se enviará esta notificación a ${n} destinatario${n===1?"":"s"} seleccionado${n===1?"":"s"}.`:"Selecciona destinatarios.";else if(scope==="semester_students")sendSummary.textContent=semesterSelect?.selectedOptions[0]?.dataset.total?`Se enviará esta notificación a ${semesterSelect.selectedOptions[0].dataset.total} estudiantes activos de ${semesterSelect.selectedOptions[0].textContent}.`:"Selecciona un semestre.";else if(scope==="all_students")sendSummary.textContent="Se enviará esta notificación a todos los estudiantes activos.";else if(scope==="all_teachers")sendSummary.textContent="Se enviará esta notificación a todos los docentes activos.";else if(scope==="all")sendSummary.textContent="Se enviará esta notificación a todos los usuarios activos.";}
function updateAudienceScope(){if(!scopeSelect)return;const scope=scopeSelect.value;const individual=scope.endsWith("_one")||scope.endsWith("_many");const semester=scope==="semester_students";const mass=scope==="all_students"||scope==="all_teachers";if(recipientsGroup)recipientsGroup.hidden=!individual;if(semesterGroup)semesterGroup.hidden=!semester;if(massGroup)massGroup.hidden=!mass;const massText=scope==="all_students"?"Se enviará esta notificación a todos los estudiantes activos.":scope==="all_teachers"?"Se enviará esta notificación a todos los docentes activos.":"";if(massSummary)massSummary.textContent=massText;document.querySelector("#groupScopeAll").hidden=scope!=="all";chosenRecipients.clear();renderChosenRecipients();if(recipientResults)recipientResults.hidden=true;updateSendSummary();}
async function loadSemesters(){if(!semesterSelect||semesterSelect.dataset.loaded)return;const url=new URL(endpoints["admin-recipients"]||"",window.location.href);url.searchParams.set("kind","semester");const payload=await request(url.toString());payload.data.semesters.forEach((row)=>{const option=document.createElement("option");option.value=row.semester;option.dataset.total=row.total;option.textContent=row.semester===1?"1.er semestre":`${row.semester}.º semestre`;semesterSelect.append(option);});semesterSelect.dataset.loaded="1";}
function showRecipientResults(rows){if(!recipientResults)return;recipientResults.replaceChildren();if(!rows.length){const empty=document.createElement("p");empty.className="notification-recipient-empty";empty.textContent="No se encontraron coincidencias.";recipientResults.append(empty);recipientResults.hidden=false;return;}rows.forEach((person)=>{const button=document.createElement("button");button.type="button";button.role="option";const projects=(person.projects||[]).map(p=>`${p.code} · ${p.title} [${p.status}]`).join(" · ")||"Sin proyecto activo";button.innerHTML=`<strong></strong><span></span><small></small>`;button.querySelector("strong").textContent=person.full_name;button.querySelector("span").textContent=`${person.email}${person.semester?` · ${person.semester}.er semestre`:""}`;button.querySelector("small").textContent=projects;button.addEventListener("click",()=>{if(scopeSelect.value.endsWith("_one"))chosenRecipients.clear();chosenRecipients.set(person.id,person);renderChosenRecipients();updateSendSummary();recipientSearch.value="";recipientResults.hidden=true;});recipientResults.append(button);});recipientResults.hidden=false;}
recipientSearch?.addEventListener("input",()=>{window.clearTimeout(recipientTimer);const q=recipientSearch.value.trim();const version=++recipientSearchVersion;if(q.length<2){if(recipientResults)recipientResults.hidden=true;return;}recipientTimer=window.setTimeout(async()=>{try{const url=new URL(endpoints["admin-recipients"]||"",window.location.href);url.searchParams.set("kind",audienceKind());url.searchParams.set("q",q);const payload=await request(url.toString());if(version===recipientSearchVersion)showRecipientResults(payload.data.recipients||[]);}catch(error){if(version===recipientSearchVersion)showToast(error.message,true);}},250);});
scopeSelect?.addEventListener("change",async()=>{updateAudienceScope();if(scopeSelect.value==="semester_students")try{await loadSemesters();}catch(error){showToast(error.message,true);}});
semesterSelect?.addEventListener("change",updateSendSummary);
templateSelect?.addEventListener("change",()=>{const template=templates[templateSelect.value];if(template){document.querySelector("#newNotificationTitle").value=template[0];document.querySelector("#newNotificationMessage").value=template[1];}});
const notificationTypeSelect=document.querySelector("#newNotificationType");const customTypeGroup=document.querySelector("#groupScopeCustomType");const customTypeInput=document.querySelector("#newNotificationCustomType");
notificationTypeSelect?.addEventListener("change",()=>{const other=notificationTypeSelect.selectedOptions[0]?.dataset.customType==="1";if(customTypeGroup)customTypeGroup.hidden=!other;if(customTypeInput){customTypeInput.required=other;if(!other)customTypeInput.value="";}});
btnNewNotification?.addEventListener("click",()=>notificationTypeSelect?.dispatchEvent(new Event("change")));
btnNewNotification?.addEventListener("click",()=>{chosenRecipients.clear();renderChosenRecipients();updateAudienceScope();});
document.addEventListener("click",(event)=>{if(!event.target.closest("#groupScopeRecipients")&&recipientResults)recipientResults.hidden=true;});
const sendConfirmModal=document.querySelector("#notificationSendConfirmModal");const sendConfirmText=document.querySelector("#notificationSendConfirmText");const sendConfirmMeta=document.querySelector("#notificationSendConfirmMeta");const confirmSendButton=document.querySelector("#confirmNotificationSend");let pendingNotificationSend=null;
function closeSendConfirmation(){if(!sendConfirmModal)return;sendConfirmModal.hidden=true;pendingNotificationSend=null;}
function validateNotificationSend(){const scope=scopeSelect?.value||"";if(!createForm?.reportValidity())throw new Error("Completa los campos obligatorios.");if(scope.endsWith("_one")&&chosenRecipients.size!==1)throw new Error("Selecciona un destinatario.");if(scope.endsWith("_many")&&chosenRecipients.size<1)throw new Error("Selecciona al menos un destinatario.");if(scope==="semester_students"&&!semesterSelect?.value)throw new Error("Selecciona un semestre.");if((scope==="all_students"||scope==="all_teachers")&&!document.querySelector("#confirmMassCheckbox")?.checked)throw new Error("Confirma el envío masivo.");if(scope==="all"&&!document.querySelector("#confirmAllCheckbox")?.checked)throw new Error("Confirma el envío a todos los usuarios.");return scope;}
function confirmationCopy(scope){const title=document.querySelector("#newNotificationTitle")?.value.trim()||"Sin título";const total=semesterSelect?.selectedOptions[0]?.dataset.total||"";if(scope.endsWith("_one")){const person=[...chosenRecipients.values()][0];return [`¿Deseas enviar esta notificación a ${person.full_name}?`,title];}if(scope.endsWith("_many")){const kind=scope.startsWith("student")?"estudiantes":"docentes";return [`¿Deseas enviar esta notificación a ${chosenRecipients.size} ${kind} seleccionados?`,title];}if(scope==="semester_students")return [`¿Deseas enviar esta notificación a los ${total||""} estudiantes activos de ${semesterSelect?.selectedOptions[0]?.textContent||"este semestre"}?`,title];if(scope==="all_students")return ["Esta notificación será enviada a todos los estudiantes activos. ¿Deseas continuar?",title];if(scope==="all_teachers")return ["Esta notificación será enviada a todos los docentes activos. ¿Deseas continuar?",title];return ["Este comunicado será enviado a todos los usuarios activos del sistema. Esta acción afectará a toda la comunidad de la plataforma. ¿Deseas continuar?",title];}
async function persistNotificationSend(){if(!pendingNotificationSend||!createForm)return;const {scope,data}=pendingNotificationSend;if(data.get("type")==="other")data.set("type","system");const submit=document.querySelector("#btnSubmitNewNotification");try{confirmSendButton.disabled=true;confirmSendButton.textContent="Enviando…";submit.disabled=true;submit.textContent="Enviando…";const endpoint=scope==="all"?endpoints["admin-send"]:endpoints["admin-audience-send"];const response=await fetch(endpoint,{method:"POST",credentials:"same-origin",headers:{"Content-Type":"application/x-www-form-urlencoded","X-Requested-With":"XMLHttpRequest"},body:new URLSearchParams(data)});const payload=await response.json().catch(()=>({success:false}));if(!response.ok||!payload.success)throw new Error(payload.message||"❌ No se pudo enviar la notificación. Inténtalo nuevamente.");closeSendConfirmation();createForm.reset();chosenRecipients.clear();renderChosenRecipients();updateAudienceScope();closeModal(createModal);showToast("✅ Notificación enviada correctamente.");if(statusFilter?.value==="sent")await loadNotifications();else loadNotifications();}catch(problem){console.error("Notification send failed",problem);const message="No se pudo enviar la notificación. Inténtalo nuevamente.";showToast(message,true);if(errorMsg){errorMsg.textContent=message;errorMsg.hidden=false;}closeSendConfirmation();}finally{confirmSendButton.disabled=false;confirmSendButton.textContent="Enviar notificación";submit.disabled=false;submit.textContent="Enviar notificación";}}
createForm?.addEventListener("submit",(event)=>{event.preventDefault();event.stopImmediatePropagation();try{const scope=validateNotificationSend();if(errorMsg)errorMsg.hidden=true;const data=new FormData(createForm);chosenRecipients.forEach(person=>data.append("recipient_ids[]",person.id));const [text,title]=confirmationCopy(scope);pendingNotificationSend={scope,data};if(sendConfirmText)sendConfirmText.textContent=text;if(sendConfirmMeta)sendConfirmMeta.textContent=`Título: ${title}`;if(sendConfirmModal){sendConfirmModal.hidden=false;confirmSendButton?.focus();}}catch(problem){if(errorMsg){errorMsg.textContent=problem.message;errorMsg.hidden=false;}}},true);
confirmSendButton?.addEventListener("click",persistNotificationSend);document.querySelector("#cancelNotificationSendConfirm")?.addEventListener("click",closeSendConfirmation);document.querySelector("#closeNotificationSendConfirm")?.addEventListener("click",closeSendConfirmation);sendConfirmModal?.addEventListener("click",(event)=>{if(event.target===sendConfirmModal&&pendingNotificationSend?.scope!=="all")closeSendConfirmation();});document.addEventListener("keydown",(event)=>{if(event.key==="Escape"&&sendConfirmModal&&!sendConfirmModal.hidden){event.stopImmediatePropagation();closeSendConfirmation();createForm?.querySelector("#btnSubmitNewNotification")?.focus();}},true);
document.addEventListener("click",(event)=>{if(event.target===detailModal)closeModal(detailModal);if(event.target===createModal)closeModal(createModal);if(!event.target.closest("[data-filter-control]"))closeFilterMenus();if(!event.target.closest(".notification-date-filter")&&datePopover&&!datePopover.hidden){datePopover.hidden=true;dateTrigger?.setAttribute("aria-expanded","false");}if(!event.target.closest("#groupScopeRecipients")&&recipientResults)recipientResults.hidden=true;});

async function openNotificationFromQuery() {
    const url = new URL(window.location.href);
    const rawId = url.searchParams.get("notification_id");
    if (!/^[1-9]\d*$/.test(rawId || "")) return;
    try {
        const payload = await request(endpoints.open, { method: "POST", body: new URLSearchParams({ notification_id: rawId }) });
        updateCounters(payload.data.counters || {});
        fillDetail(payload.data.detail, payload.data.url);
        openModal(detailModal, document.querySelector("#topbarNotificationsButton"));
    } catch (error) {
        console.warn("Notification detail was not available", error);
    } finally {
        url.searchParams.delete("notification_id");
        window.history.replaceState({}, "", `${url.pathname}${url.search}${url.hash}`);
    }
}

openNotificationFromQuery();
