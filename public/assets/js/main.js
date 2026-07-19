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

document.addEventListener("click", (event) => {
    if (!event.target.closest(".topbar-notifications")) closeTopbarNotifications();
});

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
