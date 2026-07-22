console.log("Layout global (main.js) inicializado correctamente.");

const appPageContent = document.querySelector("#appPageContent");
function revealGlobalPage() {
    document.body.classList.remove("app-page-loading");
    requestAnimationFrame(() => appPageContent?.classList.add("is-revealed"));
}
requestAnimationFrame(revealGlobalPage);
window.addEventListener("pageshow", revealGlobalPage);
document.addEventListener("click", (event) => {
    const link = event.target.closest("a[href]");
    if (!link || event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || link.target === "_blank" || link.hasAttribute("download")) return;
    const destination = new URL(link.href, window.location.href);
    if (destination.origin !== window.location.origin || !destination.pathname.toLowerCase().endsWith("index.php")) return;
    const sameDocument = destination.pathname === window.location.pathname && destination.search === window.location.search;
    if (destination.href === window.location.href || (sameDocument && destination.hash)) return;
    appPageContent?.classList.remove("is-revealed");
    document.body.classList.add("app-page-loading");
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

logoutAcceptBtn?.addEventListener("click", () => {
    window.location.href = logoutAcceptBtn.dataset.logoutUrl || "index.php?page=logout";
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
        system: "fa-gear",
    }[type] || "fa-bell";
}

function safeRecentNotificationUrl(actionUrl) {
    const notificationsUrl = topbarNotificationsPanel?.querySelector("footer a")?.href || "index.php?page=notifications";
    if (!actionUrl) return notificationsUrl;

    try {
        const destination = new URL(actionUrl, window.location.href);
        const applicationRoot = new URL(topbarNotificationsButton.dataset.listEndpoint, window.location.href).pathname.replace(/index\.php$/, "");
        const isInternal = destination.origin === window.location.origin && destination.pathname.startsWith(applicationRoot);
        return isInternal ? destination.href : notificationsUrl;
    } catch {
        return notificationsUrl;
    }
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
            const createdAt = new Date(String(notification.created_at || "").replace(" ", "T"));
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
    count.textContent = String(value);
    count.hidden = value === 0;
}

async function openRecentNotification(item) {
    const endpoint = topbarNotificationsButton?.dataset.openEndpoint;
    const token = topbarNotificationsButton?.dataset.csrfToken;
    const notificationId = item.dataset.notificationId;
    const fallbackUrl = item.href;
    if (!endpoint || !token || !notificationId) {
        window.location.assign(fallbackUrl);
        return;
    }

    const body = new URLSearchParams({ notification_id: notificationId });
    const response = await fetch(endpoint, {
        method: "POST",
        credentials: "same-origin",
        headers: { "Content-Type": "application/x-www-form-urlencoded;charset=UTF-8", "X-CSRF-Token": token, "X-Requested-With": "XMLHttpRequest" },
        body,
    });
    const payload = await response.json();
    if (!response.ok || !payload.success) throw new Error(payload.message || "No fue posible abrir la notificacion.");
    updateTopbarNotificationCount(payload.data.counters.unread);
    window.location.assign(payload.data.url || fallbackUrl);
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
    item.setAttribute("aria-busy", "true");
    try {
        await openRecentNotification(item);
    } catch (error) {
        item.removeAttribute("aria-busy");
        console.error(error);
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
    const close = (focus = false) => { if (!active) return; const { button, panel } = active; panel.remove(); button.setAttribute("aria-expanded", "false"); button.closest(".custom-select")?.classList.remove("is-open"); active = null; if (focus) button.focus(); };
    const position = (button, panel) => {
        const rect = button.getBoundingClientRect(), margin = 8;
        panel.style.width = `${Math.min(Math.max(rect.width, 220), window.innerWidth - margin * 2)}px`;
        panel.style.left = `${Math.min(Math.max(margin, rect.left), window.innerWidth - panel.offsetWidth - margin)}px`;
        const below = window.innerHeight - rect.bottom - margin, height = Math.min(panel.scrollHeight, 300);
        const above = below < Math.min(height, 190) && rect.top > below;
        panel.style.maxHeight = `${Math.max(140, above ? rect.top - margin * 2 : below)}px`;
        panel.style.top = `${above ? Math.max(margin, rect.top - Math.min(height, rect.top - margin * 2) - 6) : rect.bottom + 6}px`;
    };
    document.querySelectorAll(".app-shell select:not([multiple]):not([data-native-select])").forEach((select, index) => {
        if (select.closest(".calendar-select-wrap")) return;
        const wrapper = document.createElement("span"); wrapper.className = "custom-select"; select.parentNode.insertBefore(wrapper, select); wrapper.append(select); select.classList.add("custom-select-native"); select.dataset.enhanced = "true";
        const button = document.createElement("button"); button.type = "button"; button.className = "custom-select-trigger"; button.setAttribute("aria-haspopup", "listbox"); button.setAttribute("aria-expanded", "false"); button.setAttribute("aria-controls", `customSelectPanel${index}`); button.innerHTML = '<span></span><i class="fa-solid fa-chevron-down" aria-hidden="true"></i>'; wrapper.append(button);
        const sync = () => { const option = select.options[select.selectedIndex]; button.querySelector("span").textContent = option?.textContent.trim() || "Selecciona una opción"; button.disabled = select.disabled; button.classList.toggle("is-placeholder", !select.value); };
        const open = () => {
            if (button.disabled) return; if (active?.select === select) return close(true); close(); sync();
            const panel = document.createElement("div"); panel.id = `customSelectPanel${index}`; panel.className = "custom-select-panel"; panel.setAttribute("role", "listbox"); panel.setAttribute("aria-label", select.getAttribute("aria-label") || "Opciones");
            const searchable = select.dataset.searchable === "true" || select.options.length > 8;
            if (searchable) panel.classList.add("is-searchable");
            let searchInput = null, emptyMessage = null;
            if (searchable) {
                const search = document.createElement("label"); search.className = "custom-select-search"; search.innerHTML = '<i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i><span class="sr-only">Buscar una opción</span>';
                searchInput = document.createElement("input"); searchInput.type = "search"; searchInput.placeholder = select.dataset.searchPlaceholder || "Buscar en la lista..."; searchInput.autocomplete = "off"; search.append(searchInput); panel.append(search);
            }
            [...select.options].forEach(option => { const item = document.createElement("button"); item.type = "button"; item.className = "custom-select-option"; item.disabled = option.disabled; item.dataset.searchText = option.textContent.trim().toLocaleLowerCase("es"); item.setAttribute("role", "option"); item.setAttribute("aria-selected", String(option.selected)); item.innerHTML = '<span></span><i class="fa-solid fa-check" aria-hidden="true"></i>'; item.querySelector("span").textContent = option.textContent.trim(); item.addEventListener("click", () => { select.value = option.value; select.dispatchEvent(new Event("input", { bubbles: true })); select.dispatchEvent(new Event("change", { bubbles: true })); sync(); close(true); }); panel.append(item); });
            if (searchInput) {
                emptyMessage = document.createElement("p"); emptyMessage.className = "custom-select-empty"; emptyMessage.textContent = "No hay coincidencias."; emptyMessage.hidden = true; panel.append(emptyMessage);
                searchInput.addEventListener("input", () => { const query = searchInput.value.trim().toLocaleLowerCase("es"); let visible = 0; panel.querySelectorAll(".custom-select-option").forEach(item => { item.hidden = query !== "" && !item.dataset.searchText.includes(query); if (!item.hidden) visible++; }); emptyMessage.hidden = visible > 0; position(button, panel); });
                searchInput.addEventListener("keydown", event => { if (event.key === "ArrowDown") { event.preventDefault(); panel.querySelector(".custom-select-option:not(:disabled):not([hidden])")?.focus(); } });
            }
            document.body.append(panel); wrapper.classList.add("is-open"); button.setAttribute("aria-expanded", "true"); active = { select, button, panel }; position(button, panel); panel.querySelector('[aria-selected="true"]')?.scrollIntoView({ block: "nearest" });
            searchInput?.focus();
        };
        button.addEventListener("click", open);
        button.addEventListener("keydown", event => { if (["Enter", " "].includes(event.key)) { event.preventDefault(); open(); return; } if (["ArrowDown", "ArrowUp"].includes(event.key)) { event.preventDefault(); if (active?.select !== select) open(); const options = [...(active?.panel.querySelectorAll(".custom-select-option:not(:disabled):not([hidden])") || [])]; options[Math.max(0, options.findIndex(option => option.getAttribute("aria-selected") === "true"))]?.focus(); } });
        select.addEventListener("change", sync); select.form?.addEventListener("reset", () => setTimeout(sync)); sync(); instances.push({ sync });
    });
    document.addEventListener("click", event => { if (active && !event.target.closest(".custom-select-panel") && !event.target.closest(".custom-select-trigger")) close(); });
    document.addEventListener("keydown", event => { if (!active) return; if (event.key === "Escape") { event.preventDefault(); close(true); } if (["ArrowDown", "ArrowUp"].includes(event.key) && event.target.closest(".custom-select-panel") && !event.target.matches("input")) { event.preventDefault(); const options = [...active.panel.querySelectorAll(".custom-select-option:not(:disabled):not([hidden])")], current = options.indexOf(document.activeElement), next = event.key === "ArrowDown" ? Math.min(options.length - 1, current + 1) : Math.max(0, current - 1); options[next]?.focus(); } });
    window.addEventListener("resize", () => close()); window.addEventListener("scroll", event => { if (event.target instanceof Node && active?.panel.contains(event.target)) return; close(); }, true);
    new MutationObserver(() => instances.forEach(({ sync }) => sync())).observe(document.body, { subtree: true, attributes: true, attributeFilter: ["hidden", "disabled", "selected"] });
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
