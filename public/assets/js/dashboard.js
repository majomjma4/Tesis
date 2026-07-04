console.log("Dashboard inicializado correctamente.");

// Inicio de seleccion de elementos
const sidebar = document.querySelector("#sidebar");
const sidebarOverlay = document.querySelector("#sidebarOverlay");
const hamburgerBtn = document.querySelector("#hamburgerBtn");
const avatarButton = document.querySelector("#avatarButton");
const avatarDropdown = document.querySelector("#avatarDropdown");
const teamToggle = document.querySelector("#teamToggle");
const teamDropdown = document.querySelector("#teamDropdown");
const themeToggle = document.querySelector("#themeToggle");
const bell = document.querySelector(".notification-icon");
const logoutButtons = Array.from(document.querySelectorAll("button")).filter((button) =>
    button.textContent.toLowerCase().includes("cerrar sesi")
);
const themeStorageKey = "theme";
// Final de seleccion de elementos

// Inicio de preferencia visual
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

applyTheme(localStorage.getItem(themeStorageKey) === "dark" ? "dark" : "light");
// Final de preferencia visual

// Inicio de funciones del menu lateral
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
// Final de funciones del menu lateral

// Inicio de funciones del avatar
function closeAvatarMenu() {
    avatarDropdown?.classList.remove("show");
    avatarButton?.setAttribute("aria-expanded", "false");
}

function closeTeamMenu() {
    teamDropdown?.classList.remove("show");
    teamToggle?.setAttribute("aria-expanded", "false");
}

function toggleAvatarMenu(event) {
    event.stopPropagation();
    const isOpen = avatarDropdown?.classList.toggle("show");
    avatarButton?.setAttribute("aria-expanded", String(Boolean(isOpen)));
    closeTeamMenu();
}

function toggleTeamMenu(event) {
    event.stopPropagation();
    const isOpen = teamDropdown?.classList.toggle("show");
    teamToggle?.setAttribute("aria-expanded", String(Boolean(isOpen)));
    closeAvatarMenu();
}
// Final de funciones del avatar

// Inicio de animacion inicial de tarjetas
window.addEventListener("load", () => {
    const cards = document.querySelectorAll(
        ".status-card, .current-report, .observations-preview, .activity-summary, .process-dates, .notification-card, .reminder-card"
    );

    cards.forEach((card, index) => {
        setTimeout(() => {
            card.classList.add("show");
        }, index * 85);
    });
});
// Final de animacion inicial de tarjetas

// Inicio de eventos del menu lateral
hamburgerBtn?.addEventListener("click", toggleSidebar);
sidebarOverlay?.addEventListener("click", closeSidebar);

document.querySelectorAll(".menu-item").forEach((item) => {
    item.addEventListener("click", () => {
        document.querySelectorAll(".menu-item").forEach((menuItem) => {
            menuItem.classList.remove("active");
        });
        item.classList.add("active");
        closeSidebar();
    });
});
// Final de eventos del menu lateral

// Inicio de eventos del avatar
avatarButton?.addEventListener("click", toggleAvatarMenu);
teamToggle?.addEventListener("click", toggleTeamMenu);

document.addEventListener("click", (event) => {
    if (!event.target.closest(".avatar-menu")) {
        closeAvatarMenu();
    }

    if (!event.target.closest(".report-team")) {
        closeTeamMenu();
    }
});
// Final de eventos del avatar

// Inicio de eventos generales
document.addEventListener("keydown", (event) => {
    if (event.key === "Escape") {
        closeSidebar();
        closeAvatarMenu();
        closeTeamMenu();
    }
});
// Final de eventos generales

// Inicio de cambio de tema
themeToggle?.addEventListener("click", () => {
    const nextTheme = document.documentElement.classList.contains("theme-dark") ? "light" : "dark";

    localStorage.setItem(themeStorageKey, nextTheme);
    applyTheme(nextTheme);
});
// Final de cambio de tema

// Inicio de efecto de notificaciones
bell?.addEventListener("click", () => {
    bell.classList.add("ring");
    setTimeout(() => {
        bell.classList.remove("ring");
    }, 500);
});
// Final de efecto de notificaciones

// Inicio de cierre de sesion
logoutButtons.forEach((button) => {
    button.addEventListener("click", () => {
        window.location.href = "index.php?page=login";
    });
});
// Final de cierre de sesion
