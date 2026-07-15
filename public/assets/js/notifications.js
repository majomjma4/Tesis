const shell = document.querySelector("#notificationsShell");
const preloader = document.querySelector("#notificationsPreloader");
const searchInput = document.querySelector("#notificationSearch");
const typeFilter = document.querySelector("#notificationFilter");
const filterTrigger = document.querySelector("#notificationFilterTrigger");
const filterMenu = document.querySelector("#notificationFilterMenu");
const filterLabel = document.querySelector("#notificationFilterLabel");
const groupsContainer = document.querySelector("#notificationGroups");
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
let returnFocus = null;

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

function showToast(message, error = false) {
    if (!toast) return;
    toast.textContent = message;
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
    const dot = document.createElement("span"); dot.className = "unread-dot"; dot.setAttribute("aria-label", notification.is_read ? "Leida" : "No leida");
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
    const view = createButton("view-notification", "Ver", "", "open");
    const toggleLabel = notification.is_read ? "Marcar como no leida" : "Marcar como leida";
    const toggle = createButton("mark-notification", toggleLabel, notification.is_read ? "fa-eye" : "fa-eye-slash", "toggle-read"); toggle.setAttribute("aria-pressed", String(notification.is_read));
    const more = createButton("more-notification", "Mas opciones", "fa-ellipsis-vertical", "menu"); more.setAttribute("aria-haspopup", "menu"); more.setAttribute("aria-expanded", "false");
    const menu = document.createElement("div"); menu.className = "notification-context-menu"; menu.role = "menu"; menu.hidden = true;
    [["toggle-read", toggleLabel], ["detail", "Ver detalle"], ["delete", "Ocultar notificacion"]].forEach(([action, label]) => { const b = document.createElement("button"); b.type = "button"; b.role = "menuitem"; b.dataset.menuAction = action; b.textContent = label; if (action === "delete") b.className = "danger"; menu.append(b); });
    if (notification.deleted_at) {
        const restore = createButton("view-notification", "Restaurar", "", "restore");
        actions.append(restore);
        row.classList.add("is-hidden-notification");
    } else {
        actions.append(view, toggle, more, menu);
    }
    meta.append(time, actions); row.append(dot, icon, copy, meta);
    [title, message, projectName].forEach((el) => highlight(el, searchInput?.value || ""));
    return row;
}

function renderGroups(groups) {
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
        groupsContainer?.insertBefore(section, emptyState);
    });
    if (emptyState) { emptyState.hidden = total > 0; emptyState.querySelector("h2").textContent = searchInput?.value || typeFilter?.value !== "all" ? "No se encontraron notificaciones con los filtros seleccionados." : "No tienes notificaciones por el momento."; }
}

async function loadNotifications(showMessage = false) {
    requestController?.abort(); requestController = new AbortController();
    refreshButton?.setAttribute("disabled", ""); refreshButton?.querySelector("i")?.classList.add("is-spinning");
    const showingHidden = typeFilter?.value === "hidden";
    const params = new URLSearchParams({ search: searchInput?.value || "", type: showingHidden || typeFilter?.value === "all" ? "" : typeFilter?.value || "", hidden: showingHidden ? "1" : "0" });
    try {
        const payload = await request(`${endpoints.list}&${params}`, { signal: requestController.signal });
        renderGroups(payload.data.groups); updateCounters(payload.data.counters); if (errorState) errorState.hidden = true;
        if (showMessage) showToast(payload.message);
    } catch (error) {
        if (error.name !== "AbortError") { if (errorState) errorState.hidden = false; showToast(error.message, true); }
    } finally {
        refreshButton?.removeAttribute("disabled"); refreshButton?.querySelector("i")?.classList.remove("is-spinning");
    }
}

function debounce(callback, delay) { let timer; return (...args) => { clearTimeout(timer); timer = setTimeout(() => callback(...args), delay); }; }
const debouncedLoad = debounce(() => loadNotifications(), 300);
searchInput?.addEventListener("input", debouncedLoad);
typeFilter?.addEventListener("change", () => loadNotifications());

function closeFilterMenu(restoreFocus = false) {
    if (!filterMenu || !filterTrigger) return;
    filterMenu.hidden = true;
    filterTrigger.setAttribute("aria-expanded", "false");
    if (restoreFocus) filterTrigger.focus();
}

function selectFilter(value, requestUpdate = true) {
    if (!typeFilter || !filterMenu) return;
    typeFilter.value = value;
    const selected = filterMenu.querySelector(`[data-filter-value="${CSS.escape(value)}"]`);
    filterMenu.querySelectorAll("[data-filter-value]").forEach((option) => option.setAttribute("aria-selected", String(option === selected)));
    if (filterLabel && selected) filterLabel.textContent = selected.querySelector("span:nth-child(2)")?.textContent || "Todas";
    closeFilterMenu();
    if (requestUpdate) typeFilter.dispatchEvent(new Event("change"));
}

filterTrigger?.addEventListener("click", () => {
    const willOpen = filterMenu.hidden;
    filterMenu.hidden = !willOpen;
    filterTrigger.setAttribute("aria-expanded", String(willOpen));
    if (willOpen) filterMenu.querySelector('[aria-selected="true"]')?.focus();
});

filterMenu?.addEventListener("click", (event) => {
    const option = event.target.closest("[data-filter-value]");
    if (option) selectFilter(option.dataset.filterValue);
});

filterMenu?.addEventListener("keydown", (event) => {
    const options = [...filterMenu.querySelectorAll("[data-filter-value]")];
    const current = options.indexOf(document.activeElement);
    if (event.key === "ArrowDown" || event.key === "ArrowUp") {
        event.preventDefault();
        const direction = event.key === "ArrowDown" ? 1 : -1;
        options[(current + direction + options.length) % options.length]?.focus();
    }
    if (event.key === "Home") { event.preventDefault(); options[0]?.focus(); }
    if (event.key === "End") { event.preventDefault(); options.at(-1)?.focus(); }
    if (event.key === "Enter" || event.key === " ") { event.preventDefault(); document.activeElement?.click(); }
});

document.querySelector("#clearNotificationFilters")?.addEventListener("click", () => { searchInput.value = ""; selectFilter("all"); searchInput.focus(); });
refreshButton?.addEventListener("click", () => loadNotifications(true));
document.querySelector("#retryNotifications")?.addEventListener("click", () => loadNotifications());

async function postAction(endpoint, id, button) {
    button?.setAttribute("disabled", "");
    try {
        const body = new URLSearchParams(); if (id) body.set("notification_id", id);
        const payload = await request(endpoint, { method: "POST", body });
        updateCounters(payload.data.counters || {}); showToast(payload.message); return payload;
    } catch (error) { showToast(error.message, true); throw error; }
    finally { button?.removeAttribute("disabled"); }
}

readAllButton?.addEventListener("click", async () => { try { await postAction(endpoints["read-all"], null, readAllButton); await loadNotifications(); } catch {} });

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
    if (action === "delete") { pendingDeleteId = row.dataset.notificationId; openModal(deleteModal, menuButton); }
    if (action === "detail") { fillDetailFromRow(row); openModal(detailModal, menuButton); }
    if (action === "open") { try { const payload = await postAction(endpoints.open, row.dataset.notificationId, actionButton); if (payload.data.url) window.location.assign(payload.data.url); else { fillDetail(payload.data.detail); openModal(detailModal, actionButton); await loadNotifications(); } } catch {} }
});

function fillDetailFromRow(row) { fillDetail({ type: row.querySelector(".notification-category")?.textContent, title: row.querySelector("h3")?.textContent, message: row.querySelector(".notification-copy p")?.textContent, project_name: row.querySelector(".notification-project-name")?.textContent, created_at: row.querySelector("time")?.textContent, is_read: row.dataset.read === "true" }); }
function fillDetail(item) { document.querySelector("#notificationModalType").textContent = item.type || "Notificacion"; document.querySelector("#notificationModalTitle").textContent = item.title || ""; document.querySelector("#notificationModalMessage").textContent = item.message || ""; document.querySelector("#notificationModalProject").textContent = item.project_name || item.project || "Notificacion general"; document.querySelector("#notificationModalDate").textContent = item.created_at || ""; document.querySelector("#notificationModalStatus").textContent = item.is_read ? "Leida" : "No leida"; }

document.querySelector("#confirmDeleteNotification")?.addEventListener("click", async (event) => { try { await postAction(endpoints.delete, pendingDeleteId, event.currentTarget); closeModal(deleteModal); await loadNotifications(); } catch {} });
document.querySelectorAll("[data-modal-close]").forEach((button) => button.addEventListener("click", () => closeModal(button.closest(".notification-modal-overlay"))));
document.addEventListener("click", (event) => { if (!event.target.closest(".notification-row-actions")) closeMenus(); if (!event.target.closest(".notification-filter-custom")) closeFilterMenu(); });
document.addEventListener("keydown", (event) => { if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === "k") { event.preventDefault(); searchInput?.focus(); } if (event.key === "Escape") { closeMenus(); if (filterMenu && !filterMenu.hidden) closeFilterMenu(true); else if (detailModal && !detailModal.hidden) closeModal(detailModal); else if (deleteModal && !deleteModal.hidden) closeModal(deleteModal); else if (document.activeElement === searchInput) { searchInput.value = ""; searchInput.blur(); loadNotifications(); } } });

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
