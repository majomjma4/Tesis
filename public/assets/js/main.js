const navigationEntry = performance.getEntriesByType("navigation")[0];
const isFullPageReload = navigationEntry?.type === "reload";
const sidebarScrollKey = "app-sidebar-scroll";
let pendingSidebarScroll = null;
try {
    pendingSidebarScroll = JSON.parse(sessionStorage.getItem(sidebarScrollKey) || "null");
} catch {
    pendingSidebarScroll = null;
}
const resetReloadedPage = () => {
    document.querySelectorAll([
        ".ap-modal", ".ap-confirm", ".aa-modal", ".user-modal", ".user-confirm",
        ".notification-modal-overlay", ".calendar-modal-overlay", ".calendar-confirm-overlay",
        ".repository-preview-modal", ".project-file-modal", ".logout-modal-overlay"
    ].join(",")).forEach(layer => { layer.hidden = true; });
    document.querySelectorAll("details[open]").forEach(details => details.removeAttribute("open"));
    document.body.classList.remove(
        "modal-open", "user-dialog-open", "project-dialog-open",
        "repository-preview-modal-open", "project-file-modal-open"
    );
    if (!isFullPageReload) return;
    if ("scrollRestoration" in history) history.scrollRestoration = "manual";
    window.scrollTo(0, 0);
};
resetReloadedPage();

// Evita cerrar un modal cuando una selección/arrastre empieza dentro de él
// y termina sobre el overlay. Un cierre por backdrop sólo debe responder a
// un gesto que comenzó y terminó realmente en el propio overlay.
let modalGestureStart = null;
let modalGesturePointerId = null;
let suppressModalBackdropClick = false;
const modalContainerFor = target => target instanceof Element
    ? target.closest('[class*="modal"],[class*="overlay"],[id*="Modal"],[id*="modal"]')
    : null;
const beginModalGesture = event => {
    modalGestureStart = modalContainerFor(event.target);
    modalGesturePointerId = event.pointerId ?? 'mouse';
    suppressModalBackdropClick = false;
};
const endModalGesture = event => {
    if (modalGesturePointerId !== (event.pointerId ?? 'mouse')) return;
    if (!modalGestureStart) return;
    suppressModalBackdropClick = !(event.target instanceof Node && modalGestureStart.contains(event.target));
};
const cancelModalGesture = () => {
    modalGestureStart = null;
    modalGesturePointerId = null;
    suppressModalBackdropClick = false;
};
document.addEventListener('pointerdown', beginModalGesture, true);
document.addEventListener('pointerup', endModalGesture, true);
document.addEventListener('pointercancel', cancelModalGesture, true);
document.addEventListener('lostpointercapture', cancelModalGesture, true);
document.addEventListener('mousedown', beginModalGesture, true);
document.addEventListener('mouseup', endModalGesture, true);
window.addEventListener('blur', cancelModalGesture, true);
document.addEventListener('click', event => {
    if (!suppressModalBackdropClick) return;
    cancelModalGesture();
    event.preventDefault();
    event.stopImmediatePropagation();
}, true);
if (isFullPageReload) {
    requestAnimationFrame(() => window.scrollTo(0, 0));
    window.addEventListener("load", () => window.scrollTo(0, 0), { once: true });
}

const appPageContent = document.querySelector("#appPageContent");
const appGlobalSkeleton = document.querySelector("#appGlobalSkeleton");
const appSkeletonStartedAt = performance.now();
function revealGlobalPage() {
    if (!appGlobalSkeleton) return;
    document.body.classList.remove("app-page-loading");
    requestAnimationFrame(() => {
        appPageContent?.classList.add("is-revealed");
    });
}
if (appGlobalSkeleton) {
    const minimumSkeletonTime = 520;
    const remainingSkeletonTime = Math.max(0, minimumSkeletonTime - (performance.now() - appSkeletonStartedAt));
    window.setTimeout(revealGlobalPage, remainingSkeletonTime);
}
window.addEventListener("pageshow", (event) => {
    if (event.persisted) revealGlobalPage();
});
document.addEventListener("click", (event) => {
    const menuLink = event.target.closest(".menu-item[href]");
    if (!menuLink || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
    if (!window.matchMedia("(max-width: 900px)").matches) return;

    saveSidebarScroll();
});


// Inicio de selección de elementos globales del layout
const sidebar = document.querySelector("#sidebar");
const sidebarOverlay = document.querySelector("#sidebarOverlay");
const hamburgerBtn = document.querySelector("#hamburgerBtn");
const avatarButton = document.querySelector("#avatarButton");
const avatarDropdown = document.querySelector("#avatarDropdown");
const themeToggle = document.querySelector("#themeToggle");
const bell = document.querySelector(".notification-icon");
const topbarNotifications = document.querySelector(".topbar-notifications");
const topbarNotificationsButton = document.querySelector("#topbarNotificationsButton");
const topbarNotificationsPanel = document.querySelector("#topbarNotificationsPanel");
const topbarNotificationsList = document.querySelector("#topbarNotificationsList");
const logoutModal = document.querySelector("#logoutModal");
const logoutCancelBtn = document.querySelector("#logoutCancelBtn");
const logoutAcceptBtn = document.querySelector("#logoutAcceptBtn");
const logoutButtons = document.querySelectorAll(".js-logout-trigger");
const themeStorageKey = "theme";
// Final de selección de elementos globales del layout

const sidebarScroller = sidebar?.querySelector(":scope > div:first-child");
function saveSidebarScroll() {
    try {
        pendingSidebarScroll = {
            sidebarTop: sidebar?.scrollTop || 0,
            contentTop: sidebarScroller?.scrollTop || 0,
            savedAt: Date.now(),
        };
        sessionStorage.setItem(sidebarScrollKey, JSON.stringify(pendingSidebarScroll));
    } catch {
        // El menú sigue funcionando aunque el almacenamiento esté bloqueado.
    }
}

function restoreSidebarScroll() {
    if (!pendingSidebarScroll || Date.now() - pendingSidebarScroll.savedAt >= 86400000) return;
    if (sidebar) sidebar.scrollTop = Number(pendingSidebarScroll.sidebarTop) || 0;
    if (sidebarScroller) sidebarScroller.scrollTop = Number(pendingSidebarScroll.contentTop) || 0;
}

function keepActiveSidebarItemVisible() {
    const activeItem = sidebar?.querySelector(".menu-item.active");
    if (!activeItem) return;
    const scroller = sidebarScroller && sidebarScroller.scrollHeight > sidebarScroller.clientHeight
        ? sidebarScroller
        : sidebar;
    if (!scroller) return;
    const itemRect = activeItem.getBoundingClientRect();
    const scrollerRect = scroller.getBoundingClientRect();
    if (itemRect.top >= scrollerRect.top && itemRect.bottom <= scrollerRect.bottom) return;
    scroller.scrollTop += itemRect.top - scrollerRect.top - Math.max(0, (scroller.clientHeight - itemRect.height) / 2);
    saveSidebarScroll();
}

function restoreSidebarState() {
    restoreSidebarScroll();
    keepActiveSidebarItemVisible();
}

restoreSidebarState();
requestAnimationFrame(restoreSidebarState);
window.addEventListener("load", restoreSidebarState, { once: true });
sidebar?.addEventListener("scroll", saveSidebarScroll, { passive: true });
sidebarScroller?.addEventListener("scroll", saveSidebarScroll, { passive: true });

// Inicio de preferencia visual (Tema claro/oscuro)
function setThemeIcon(isDarkMode) {
    if (!themeToggle) {
        return;
    }
    themeToggle.innerHTML = isDarkMode
        ? '<i class="fa-solid fa-sun"></i>'
        : '<i class="fa-solid fa-moon"></i>';
}

function applyTheme(theme) {
    const isDarkMode = theme === "dark";
    document.documentElement.classList.toggle("theme-dark", isDarkMode);
    document.body.classList.toggle("dark-mode", isDarkMode);
    setThemeIcon(isDarkMode);
}

// Aplicar tema guardado al cargar
applyTheme(localStorage.getItem(themeStorageKey) === "dark" ? "dark" : "light");

themeToggle?.addEventListener("click", () => {
    const nextTheme = document.documentElement.classList.contains("theme-dark") ? "light" : "dark";
    localStorage.setItem(themeStorageKey, nextTheme);
    applyTheme(nextTheme);
});
// Final de preferencia visual

// Inicio de funciones de control del Sidebar
function closeSidebar() {
    sidebar?.classList.remove("open");
    sidebarOverlay?.classList.remove("show");
    hamburgerBtn?.setAttribute("aria-expanded", "false");
}

function toggleSidebar() {
    const isOpen = sidebar?.classList.toggle("open");
    sidebarOverlay?.classList.toggle("show", Boolean(isOpen));
    hamburgerBtn?.setAttribute("aria-expanded", String(Boolean(isOpen)));
    if (isOpen) requestAnimationFrame(restoreSidebarState);
}

hamburgerBtn?.addEventListener("click", toggleSidebar);
sidebarOverlay?.addEventListener("click", closeSidebar);
// Final de funciones de control del Sidebar

// Inicio de funciones de menú del Avatar
function closeAvatarMenu() {
    avatarDropdown?.classList.remove("show");
    avatarButton?.setAttribute("aria-expanded", "false");
}

function toggleAvatarMenu(event) {
    event.stopPropagation();
    const isOpen = avatarDropdown?.classList.toggle("show");
    avatarButton?.setAttribute("aria-expanded", String(Boolean(isOpen)));
}

avatarButton?.addEventListener("click", toggleAvatarMenu);
// Final de funciones de menú del Avatar

// Inicio de confirmación de Cierre de Sesión (Logout Modal)
function openLogoutModal() {
    closeAvatarMenu();
    closeSidebar();
    logoutModal?.removeAttribute("hidden");
    requestAnimationFrame(() => {
        logoutModal?.classList.add("show");
        document.body.classList.add("modal-open");
        logoutCancelBtn?.focus();
    });
}

function closeLogoutModal() {
    logoutModal?.classList.remove("show");
    document.body.classList.remove("modal-open");

    setTimeout(() => {
        if (!logoutModal?.classList.contains("show")) {
            logoutModal?.setAttribute("hidden", "");
        }
    }, 220);
}

logoutButtons.forEach((button) => {
    button.addEventListener("click", (event) => {
        event.preventDefault();
        openLogoutModal();
    });
});

logoutCancelBtn?.addEventListener("click", closeLogoutModal);

logoutModal?.addEventListener("click", (event) => {
    if (event.target === logoutModal) {
        closeLogoutModal();
    }
});

logoutAcceptBtn?.addEventListener("click", (event) => {
    event.preventDefault();
    logoutAcceptBtn.closest("form")?.requestSubmit();
});
// Final de confirmación de Cierre de Sesión

// Inicio de eventos de teclado (Escape) y clics fuera
document.addEventListener("keydown", (event) => {
    if (event.key === "Escape") {
        closeSidebar();
        closeAvatarMenu();
        closeLogoutModal();
    }
});

document.addEventListener("click", (event) => {
    if (!event.target.closest(".avatar-menu")) {
        closeAvatarMenu();
    }
});
// Final de eventos de teclado y clics fuera

// Inicio de efecto de notificaciones
bell?.addEventListener("click", () => {
    bell.classList.add("ring");
    setTimeout(() => {
        bell.classList.remove("ring");
    }, 500);
});
// Final de efecto de notificaciones

// Inicio de panel de notificaciones recientes
let recentNotificationsLoaded = false;

function closeTopbarNotifications() {
    if (!topbarNotificationsPanel || !topbarNotificationsButton) return;
    topbarNotificationsPanel.hidden = true;
    topbarNotificationsButton.setAttribute("aria-expanded", "false");
}

function notificationIcon(type) {
    return {
        delivery: "fa-cloud-arrow-up",
        observation: "fa-comment-dots",
        status_change: "fa-circle-check",
        review: "fa-triangle-exclamation",
        reminder: "fa-clock",
        tribunal: "fa-user-group",
        repository: "fa-database",
        comment: "fa-message",
        adjustment: "fa-pen-to-square",
        system: "fa-gear",
    }[type] || "fa-bell";
}

function safeRecentNotificationUrl(actionUrl) {
    const fallback = new URL(topbarNotificationsPanel?.querySelector("footer a")?.href || "index.php?page=notifications", window.location.href);
    const applicationRoot = new URL(topbarNotificationsButton?.dataset.listEndpoint || "index.php?page=notifications", window.location.href).pathname.replace(/index\.php$/, "");
    const frontController = `${applicationRoot}index.php`;
    if (!actionUrl) return fallback.href;

    try {
        const destination = new URL(actionUrl, window.location.href);
        if (destination.origin !== window.location.origin) return fallback.href;

        // Solo se permite el front controller real. Las URLs históricas de
        // /scripts/index.php se reconstruyen conservando únicamente query/hash.
        if (destination.pathname === frontController) return destination.href;
        if (destination.pathname === `${applicationRoot}scripts/index.php`) {
            return new URL(`index.php${destination.search}${destination.hash}`, `${window.location.origin}${applicationRoot}`).href;
        }
    } catch {
        // El fallback evita exponer rutas internas o una página 403/404.
    }
    return fallback.href;
}

function renderRecentNotifications(groups) {
    if (!topbarNotificationsList) return;
    const notifications = Object.values(groups || {}).flat().slice(0, 8);
    topbarNotificationsList.replaceChildren();

    if (!notifications.length) {
        const empty = document.createElement("div");
        empty.className = "topbar-notifications-empty";
        empty.textContent = "No tienes notificaciones recientes.";
        topbarNotificationsList.append(empty);
        return;
    }

    function appendSection(sectionTitle, items) {
        if (!items.length) return;
        const heading = document.createElement("div");
        heading.className = "topbar-notifications-section-title";
        heading.textContent = sectionTitle;
        topbarNotificationsList.append(heading);

        items.forEach((notification) => {
            const item = document.createElement("a");
            item.className = `topbar-notification-item ${notification.is_read ? "is-read" : "is-unread"}`;
            item.href = safeRecentNotificationUrl(notification.action_url);
            item.dataset.notificationId = String(notification.id);
            item.setAttribute("aria-label", `${notification.title}. Abrir apartado relacionado`);
            const icon = document.createElement("span"); icon.className = "topbar-notification-icon";
            const glyph = document.createElement("i"); glyph.className = `fa-solid ${notificationIcon(notification.type)}`; icon.append(glyph);
            const copy = document.createElement("div");
            const title = document.createElement("strong"); title.textContent = notification.title;
            const project = document.createElement("p"); project.textContent = notification.project || "Notificacion general";
            const time = document.createElement("small");
            const createdAt = new Date(String(notification.created_at_iso || notification.created_at || "").replace(" ", "T"));
            const minutes = Math.max(0, Math.floor((Date.now() - createdAt.getTime()) / 60000));
            time.textContent = minutes < 60 ? `Hace ${minutes} min` : (minutes < 1440 ? `Hace ${Math.floor(minutes / 60)} h` : `Hace ${Math.floor(minutes / 1440)} d`);
            copy.append(title, project, time);
            if (!notification.is_read) {
                const dot = document.createElement("span"); dot.className = "topbar-notification-dot"; dot.setAttribute("aria-label", "No leída"); item.append(icon, copy, dot);
            } else item.append(icon, copy);
            topbarNotificationsList.append(item);
        });
    }

    appendSection("Nuevas", notifications.filter((item) => !item.is_read));
    appendSection("Anteriores", notifications.filter((item) => item.is_read));
}

function updateTopbarNotificationCount(unread) {
    const count = document.querySelector(".notification-count");
    const value = Math.max(0, Number(unread) || 0);
    if (!count) return;
    if (value === 0) {
        count.textContent = "";
        count.hidden = true;
        count.setAttribute("aria-hidden", "true");
        count.removeAttribute("aria-label");
        return;
    }
    count.textContent = value >= 10 ? "9+" : String(value);
    count.hidden = false;
    count.removeAttribute("aria-hidden");
    count.setAttribute("aria-label", `${value} ${value === 1 ? "notificación no leída" : "notificaciones no leídas"}`);
}

async function openRecentNotification(item) {
    const notificationId = item.dataset.notificationId;
    const fallbackUrl = safeRecentNotificationUrl(item.href);
    if (!notificationId) {
        window.location.assign(fallbackUrl);
        return;
    }

    const endpoint = topbarNotificationsButton?.dataset.openEndpoint;
    const csrfToken = topbarNotificationsButton?.dataset.csrfToken || "";
    if (!endpoint || !csrfToken) {
        window.location.assign(fallbackUrl);
        return;
    }
    const response = await fetch(endpoint, {
        method: "POST",
        credentials: "same-origin",
        headers: { "X-Requested-With": "XMLHttpRequest", "X-CSRF-Token": csrfToken, "Content-Type": "application/x-www-form-urlencoded" },
        body: new URLSearchParams({ notification_id: String(notificationId) })
    });
    const payload = await response.json().catch(() => ({ success: false }));
    if (!response.ok || !payload.success) throw new Error(payload.message || "No fue posible abrir la notificación.");

    updateTopbarNotificationCount(payload.data?.counters?.unread);
    item.classList.remove("is-unread");
    item.classList.add("is-read");
    item.querySelector(".topbar-notification-dot")?.remove();
    item.removeAttribute("aria-busy");
    window.location.assign(safeRecentNotificationUrl(payload.data?.url) || fallbackUrl);
}

async function loadRecentNotifications() {
    if (!topbarNotificationsButton || recentNotificationsLoaded) return;
    try {
        const response = await fetch(topbarNotificationsButton.dataset.listEndpoint, { credentials: "same-origin", headers: { "X-Requested-With": "XMLHttpRequest" } });
        const payload = await response.json();
        if (!response.ok || !payload.success) throw new Error();
        renderRecentNotifications(payload.data.groups);
        updateTopbarNotificationCount(payload.data.counters.unread);
        recentNotificationsLoaded = true;
    } catch {
        if (topbarNotificationsList) {
            topbarNotificationsList.replaceChildren();
            const error = document.createElement("div");
            error.className = "topbar-notifications-empty";
            error.textContent = "No fue posible cargar las notificaciones.";
            topbarNotificationsList.append(error);
        }
    }
}

topbarNotificationsButton?.addEventListener("click", async () => {
    const willOpen = Boolean(topbarNotificationsPanel?.hidden);
    if (topbarNotificationsPanel) topbarNotificationsPanel.hidden = !willOpen;
    topbarNotificationsButton.setAttribute("aria-expanded", String(willOpen));
    if (willOpen) await loadRecentNotifications();
});

topbarNotificationsList?.addEventListener("click", async (event) => {
    const item = event.target.closest(".topbar-notification-item");
    if (!item) return;
    event.preventDefault();
    if (item.getAttribute("aria-busy") === "true") return;
    item.setAttribute("aria-busy", "true");
    try {
        await openRecentNotification(item);
    } catch (error) {
        item.removeAttribute("aria-busy");
        console.error(error);
        window.location.assign(item.href);
    }
});

// Se usa captura para cerrar también cuando otro menú detiene la propagación
// (por ejemplo, el menú del avatar).
document.addEventListener("click", (event) => {
    if (!event.target.closest(".topbar-notifications")) closeTopbarNotifications();
}, true);

document.addEventListener("keydown", (event) => {
    if (event.key === "Escape" && topbarNotificationsPanel && !topbarNotificationsPanel.hidden) {
        closeTopbarNotifications();
        topbarNotificationsButton?.focus();
    }
});

async function syncTopbarNotificationCount() {
    const endpoint = topbarNotificationsButton?.dataset.countersEndpoint;
    if (!endpoint || document.visibilityState === "hidden") return;
    try {
        const response = await fetch(endpoint, { credentials: "same-origin", headers: { "X-Requested-With": "XMLHttpRequest" } });
        const payload = await response.json();
        if (response.ok && payload.success) updateTopbarNotificationCount(payload.data?.counters?.unread);
    } catch {}
}

updateTopbarNotificationCount(document.querySelector(".notification-count")?.textContent || 0);
window.addEventListener("focus", syncTopbarNotificationCount);
document.addEventListener("visibilitychange", () => { if (document.visibilityState === "visible") syncTopbarNotificationCount(); });
// Final de panel de notificaciones recientes

// Inicio de aviso descartable de contraseña temporal
const temporaryPasswordWarning = document.querySelector("[data-password-warning]");
if (temporaryPasswordWarning) {
    const storageKey = `temporary-password-warning:${temporaryPasswordWarning.dataset.warningKey || "current"}`;
    if (sessionStorage.getItem(storageKey) === "dismissed") temporaryPasswordWarning.hidden = true;
    temporaryPasswordWarning.querySelectorAll("[data-password-warning-dismiss]").forEach((button) => {
        button.addEventListener("click", () => {
            sessionStorage.setItem(storageKey, "dismissed");
            temporaryPasswordWarning.hidden = true;
        });
    });
}
// Final de aviso descartable de contraseña temporal

// Inicio de selectores personalizados
(() => {
    const instances = [];
    let active = null;
    let ignoreScrollUntil = 0;
    const close = (focus = false) => { if (!active) return; const { button, panel, customSection, customHome } = active; if (customSection && customHome) { customSection.hidden = true; customHome.append(customSection); } panel.remove(); button.setAttribute("aria-expanded", "false"); button.closest(".custom-select")?.classList.remove("is-open"); active = null; if (focus) button.focus(); };
    const position = (button, panel) => {
        const rect = button.getBoundingClientRect(), margin = 8, gap = 6;
        const viewportHeight = window.visualViewport?.height || window.innerHeight;
        const opensAbove = button.closest(".custom-select")?.querySelector("select")?.dataset.dropdownPlacement === "top";
        const minWidth = button.closest('.data-pagination-size') ? rect.width : 220;
        panel.style.width = `${Math.min(Math.max(rect.width, minWidth), window.innerWidth - margin * 2)}px`;
        panel.style.left = `${Math.min(Math.max(margin, rect.left), window.innerWidth - panel.offsetWidth - margin)}px`;
        const availableBelow = Math.max(40, opensAbove ? rect.top - gap - margin : viewportHeight - rect.bottom - gap - margin);
        const visibleOptions = [...panel.querySelectorAll(".custom-select-option:not([hidden])")];
        const configuredLimit = Number(button.closest(".custom-select")?.querySelector("select")?.dataset.dropdownVisibleOptions || 4);
        const visibleOptionLimit = Number.isFinite(configuredLimit) ? Math.max(1, Math.floor(configuredLimit)) : 4;
        const hasEmbeddedCustom = panel.classList.contains("has-embedded-custom");
        panel.style.overflowY = hasEmbeddedCustom ? "hidden" : (visibleOptions.length > visibleOptionLimit ? "auto" : "hidden");
        const options = visibleOptions.slice(0, visibleOptionLimit);
        const panelStyle = getComputedStyle(panel);
        const bottomInset = parseFloat(panelStyle.paddingBottom) + parseFloat(panelStyle.borderBottomWidth);
        const completeOptionHeights = options.map(option => Math.ceil(option.offsetTop + option.offsetHeight + bottomInset));
        const fittingHeights = completeOptionHeights.filter(height => height <= availableBelow);
        const completeHeight = hasEmbeddedCustom
            ? Math.min(panel.scrollHeight, availableBelow)
            : (fittingHeights.at(-1) || Math.min(completeOptionHeights[0] || panel.scrollHeight, availableBelow));
        panel.style.setProperty("max-height", `${Math.min(completeHeight, availableBelow)}px`, "important");
        if (hasEmbeddedCustom) panel.style.overflowY = panel.scrollHeight > availableBelow ? "auto" : "hidden";
        panel.style.top = `${opensAbove ? Math.max(margin, rect.top - gap - Math.min(completeHeight, availableBelow)) : rect.bottom + gap}px`;
    };
    let nextSelectId = 0;
    const enhanceSelect = (select) => {
        if (!(select instanceof HTMLSelectElement) || select.multiple || select.hasAttribute("data-native-select") || select.dataset.enhanced === "true") return;
        const index = nextSelectId++;
        if (select.closest(".calendar-select-wrap")) return;
        const wrapper = document.createElement("span"); wrapper.className = "custom-select"; select.parentNode.insertBefore(wrapper, select); wrapper.append(select); select.classList.add("custom-select-native"); select.dataset.enhanced = "true";
        const button = document.createElement("button"); button.type = "button"; button.className = "custom-select-trigger"; button.setAttribute("aria-haspopup", "listbox"); button.setAttribute("aria-expanded", "false"); button.setAttribute("aria-controls", `customSelectPanel${index}`); button.innerHTML = '<span></span><i class="fa-solid fa-chevron-down" aria-hidden="true"></i>'; wrapper.append(button);
        const embeddedInput = select.dataset.embeddedCustom ? document.getElementById(select.dataset.embeddedCustom) : null;
        const embeddedSection = embeddedInput?.closest("[data-custom-select-content]") || null;
        const embeddedHome = embeddedSection?.parentElement || null;
        if (embeddedSection) button.setAttribute("aria-label", select.labels?.[0]?.textContent.trim() || "Tipo de material");
        const sync = () => { const option = select.options[select.selectedIndex]; const customLabel = select.value === "Otros" ? embeddedInput?.value.trim() : ""; button.querySelector("span").textContent = customLabel || option?.textContent.trim() || "Selecciona una opción"; button.disabled = select.disabled; button.classList.toggle("is-placeholder", !select.value); };
        embeddedInput?.addEventListener("input", sync);
        const open = () => {
            if (button.disabled) return; if (active?.select === select) return close(true); close(); sync();
            document.dispatchEvent(new CustomEvent("app:dropdown-open", { detail: { trigger: button } }));
            const panel = document.createElement("div"); panel.id = `customSelectPanel${index}`; panel.className = "custom-select-panel"; panel.setAttribute("role", embeddedSection ? "dialog" : "listbox"); panel.setAttribute("aria-label", select.getAttribute("aria-label") || "Opciones");
            const optionHost = embeddedSection ? document.createElement("div") : panel;
            if (embeddedSection) { optionHost.setAttribute("role", "listbox"); optionHost.setAttribute("aria-label", "Tipos de material"); panel.append(optionHost); }
            const searchable = select.dataset.searchable === "false" ? false : (select.dataset.searchable === "true" || select.options.length > 8);
            if (searchable) panel.classList.add("is-searchable");
            let searchInput = null, emptyMessage = null;
            if (searchable) {
                const search = document.createElement("label"); search.className = "custom-select-search"; search.innerHTML = '<i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i><span class="sr-only">Buscar una opción</span>';
                searchInput = document.createElement("input"); searchInput.type = "search"; searchInput.placeholder = select.dataset.searchPlaceholder || "Buscar en la lista..."; searchInput.autocomplete = "off"; search.append(searchInput); panel.append(search);
            }
            [...select.options].filter(option => !option.hidden).forEach(option => { const item = document.createElement("button"); item.type = "button"; item.className = "custom-select-option"; item.disabled = option.disabled; item.dataset.searchText = option.textContent.trim().toLocaleLowerCase("es"); item.setAttribute("role", "option"); item.setAttribute("aria-selected", String(option.selected)); item.innerHTML = '<span></span><i class="fa-solid fa-check" aria-hidden="true"></i>'; item.querySelector("span").textContent = option.textContent.trim(); item.addEventListener("click", () => { select.value = option.value; select.dispatchEvent(new Event("input", { bubbles: true })); select.dispatchEvent(new Event("change", { bubbles: true })); sync(); if (option.value === "Otros" && embeddedSection && embeddedInput) { panel.querySelectorAll(".custom-select-option").forEach(panelOption => panelOption.setAttribute("aria-selected", String(panelOption === item))); embeddedSection.hidden = false; position(button, panel); embeddedInput.focus(); return; } close(true); }); optionHost.append(item); });
            if (embeddedSection) {
                panel.classList.add("has-embedded-custom");
                panel.append(embeddedSection);
                embeddedSection.hidden = select.value !== "Otros";
            }
            if (searchInput) {
                emptyMessage = document.createElement("p"); emptyMessage.className = "custom-select-empty"; emptyMessage.textContent = "No hay coincidencias."; emptyMessage.hidden = true; panel.append(emptyMessage);
                searchInput.addEventListener("input", () => { const query = searchInput.value.trim().toLocaleLowerCase("es"); let visible = 0; panel.querySelectorAll(".custom-select-option").forEach(item => { item.hidden = query !== "" && !item.dataset.searchText.includes(query); if (!item.hidden) visible++; }); emptyMessage.hidden = visible > 0; position(button, panel); });
                searchInput.addEventListener("keydown", event => { if (event.key === "ArrowDown") { event.preventDefault(); panel.querySelector(".custom-select-option:not(:disabled):not([hidden])")?.focus(); } });
            }
            document.body.append(panel);
            const configuredLimit = Number(select.dataset.dropdownVisibleOptions || 4);
            const initialOptionLimit = Number.isFinite(configuredLimit) ? Math.max(1, Math.floor(configuredLimit)) : 4;
            const firstOptions = [...panel.querySelectorAll(".custom-select-option:not([hidden])")].slice(0, initialOptionLimit);
            const lastInitialOption = firstOptions.at(-1);
            const desiredHeight = lastInitialOption ? lastInitialOption.offsetTop + lastInitialOption.offsetHeight + 8 : panel.scrollHeight;
            const viewportHeight = window.visualViewport?.height || window.innerHeight;
            if (select.dataset.dropdownPlacement !== "top" && viewportHeight - button.getBoundingClientRect().bottom - 14 < desiredHeight) {
                ignoreScrollUntil = performance.now() + 300;
                button.scrollIntoView({ block: "center", inline: "nearest", behavior: "instant" });
            }
            wrapper.classList.add("is-open"); button.setAttribute("aria-expanded", "true"); active = { select, button, panel, customSection: embeddedSection, customHome: embeddedHome }; position(button, panel); panel.querySelector('[aria-selected="true"]')?.scrollIntoView({ block: "nearest" });
            if (select.value === "Otros" && embeddedInput) embeddedInput.focus(); else searchInput?.focus();
        };
        button.addEventListener("click", open);
        button.addEventListener("keydown", event => { if (["Enter", " "].includes(event.key)) { event.preventDefault(); open(); return; } if (["ArrowDown", "ArrowUp"].includes(event.key)) { event.preventDefault(); if (active?.select !== select) open(); const options = [...(active?.panel.querySelectorAll(".custom-select-option:not(:disabled):not([hidden])") || [])]; options[Math.max(0, options.findIndex(option => option.getAttribute("aria-selected") === "true"))]?.focus(); } });
        select.addEventListener("change", sync); select.form?.addEventListener("reset", () => setTimeout(sync)); sync(); instances.push({ select, sync });
    };
    document.querySelectorAll(".app-shell select:not([multiple]):not([data-native-select])").forEach(enhanceSelect);
    document.addEventListener("click", event => { if (active && !event.target.closest(".custom-select-panel") && !event.target.closest(".custom-select-trigger")) close(); });
    document.addEventListener("app:dropdown-open", event => { if (active && active.button !== event.detail?.trigger) close(); });
    document.addEventListener("keydown", event => { if (!active) return; if (event.key === "Escape") { event.preventDefault(); close(true); } if (["ArrowDown", "ArrowUp"].includes(event.key) && event.target.closest(".custom-select-panel") && !event.target.matches("input")) { event.preventDefault(); const options = [...active.panel.querySelectorAll(".custom-select-option:not(:disabled):not([hidden])")], current = options.indexOf(document.activeElement), next = event.key === "ArrowDown" ? Math.min(options.length - 1, current + 1) : Math.max(0, current - 1); options[next]?.focus(); } });
    window.addEventListener("resize", () => close()); window.addEventListener("scroll", event => {
        if (event.target instanceof Node && active?.panel.contains(event.target)) return;
        if (active && performance.now() < ignoreScrollUntil) { requestAnimationFrame(() => active && position(active.button, active.panel)); return; }
        close();
    }, true);
    new MutationObserver(records => {
        const changedSelects = new Set();
        records.forEach(record => {
            record.addedNodes.forEach(node => {
                if (!(node instanceof Element)) return;
                if (node.matches("select:not([multiple]):not([data-native-select])")) enhanceSelect(node);
                node.querySelectorAll?.("select:not([multiple]):not([data-native-select])").forEach(enhanceSelect);
            });
            const target = record.target;
            const select = target instanceof HTMLSelectElement
                ? target
                : (target instanceof HTMLOptionElement ? target.closest("select") : null);
            if (select?.dataset.enhanced === "true") changedSelects.add(select);
        });
        if (!changedSelects.size) return;
        instances.forEach(instance => { if (changedSelects.has(instance.select)) instance.sync(); });
    }).observe(document.body, { subtree: true, childList: true, attributes: true, attributeFilter: ["hidden", "disabled", "selected"] });
})();

// Mantiene los diálogos fuera de contenedores animados o desplazables.
// El overlay conserva el viewport completo y el CSS centra la ventana en el área útil.
(() => {
    const selector = [
        '.ap-modal',
        '.ap-confirm',
        '.aa-modal',
        '.user-modal',
        '.user-confirm',
        '.notification-modal-overlay',
        '.calendar-modal-overlay',
        '.repository-preview-modal',
        '.project-file-modal',
        '.forced-password-shell',
        '.logout-modal-overlay'
    ].join(',');
    document.querySelectorAll(selector).forEach(layer => {
        if (layer.parentElement !== document.body) document.body.append(layer);
    });
})();
// Final de selectores personalizados

// Inicio de historial breve para buscadores
(() => {
    const inputs = document.querySelectorAll('.app-shell input[type="search"],.app-shell .ap-filters input[name="search"],.app-shell .users-filters input[name="search"]');
    inputs.forEach((input, index) => {
        if (input.hasAttribute("data-no-search-history")) return;
        input.autocomplete = "off";
        const key = `recentSearch:${location.pathname}:${input.id || input.name || index}`;
        const list = document.createElement("datalist"); list.id = `recentSearchList${index}`; document.body.append(list); input.setAttribute("list", list.id);
        const read = () => { try { return JSON.parse(localStorage.getItem(key) || "[]").slice(0, 3); } catch { return []; } };
        const field = document.createElement("span"); field.className = "search-history-field"; input.parentNode.insertBefore(field, input); field.append(input);
        const clearButton = document.createElement("button"); clearButton.type = "button"; clearButton.className = "search-history-clear"; clearButton.title = "Borrar historial de búsqueda"; clearButton.setAttribute("aria-label", "Borrar historial de búsqueda"); clearButton.innerHTML = '<i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i><i class="fa-solid fa-xmark" aria-hidden="true"></i>'; field.append(clearButton);
        const render = () => { const history = read(); list.replaceChildren(...history.map(value => { const option = document.createElement("option"); option.value = value; return option; })); clearButton.hidden = history.length === 0; };
        const remember = () => { const value = input.value.trim(); if (!value) return; try { localStorage.setItem(key, JSON.stringify([value, ...read().filter(item => item.toLocaleLowerCase("es") !== value.toLocaleLowerCase("es"))].slice(0, 3))); render(); } catch {} };
        clearButton.addEventListener("click", () => { try { localStorage.removeItem(key); } catch {} render(); input.focus(); });
        input.addEventListener("keydown", event => { if (event.key === "Enter") remember(); }); input.form?.addEventListener("submit", event => { if (event.submitter) remember(); }); render();
    });
})();
// Final de historial breve para buscadores
