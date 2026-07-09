console.log("Layout global (main.js) inicializado correctamente.");

// Inicio de selección de elementos globales del layout
const sidebar = document.querySelector("#sidebar");
const sidebarOverlay = document.querySelector("#sidebarOverlay");
const hamburgerBtn = document.querySelector("#hamburgerBtn");
const avatarButton = document.querySelector("#avatarButton");
const avatarDropdown = document.querySelector("#avatarDropdown");
const themeToggle = document.querySelector("#themeToggle");
const bell = document.querySelector(".notification-icon");
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
