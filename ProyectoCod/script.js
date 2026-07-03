console.log("Dashboard inicializado correctamente.");

// Inicio de selección de elementos
const sidebar = document.querySelector("#sidebar");
const sidebarOverlay = document.querySelector("#sidebarOverlay");
const hamburgerBtn = document.querySelector("#hamburgerBtn");
const avatarButton = document.querySelector("#avatarButton");
const avatarDropdown = document.querySelector("#avatarDropdown");
const themeToggle = document.querySelector("#themeToggle");
const bell = document.querySelector(".notification-icon");
const logoutButtons = Array.from(document.querySelectorAll("button")).filter((button) =>
    button.textContent.toLowerCase().includes("cerrar sesi")
);
// Final de selección de elementos

// Inicio de funciones del menú lateral
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
// Final de funciones del menú lateral

// Inicio de funciones del avatar
function closeAvatarMenu() {
    avatarDropdown?.classList.remove("show");
    avatarButton?.setAttribute("aria-expanded", "false");
}

function toggleAvatarMenu(event) {
    event.stopPropagation();
    const isOpen = avatarDropdown?.classList.toggle("show");
    avatarButton?.setAttribute("aria-expanded", String(Boolean(isOpen)));
}
// Final de funciones del avatar

// Inicio de animación inicial de tarjetas
window.addEventListener("load", () => {
    const cards = document.querySelectorAll(
        ".status-card, .project-card, .calendar-summary, .notification-card, .reminder-card"
    );

    cards.forEach((card, index) => {
        setTimeout(() => {
            card.classList.add("show");
        }, index * 85);
    });
});
// Final de animación inicial de tarjetas

// Inicio de eventos del menú lateral
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
// Final de eventos del menú lateral

// Inicio de eventos del avatar
avatarButton?.addEventListener("click", toggleAvatarMenu);

document.addEventListener("click", (event) => {
    if (!event.target.closest(".avatar-menu")) {
        closeAvatarMenu();
    }
});
// Final de eventos del avatar

// Inicio de eventos generales
document.addEventListener("keydown", (event) => {
    if (event.key === "Escape") {
        closeSidebar();
        closeAvatarMenu();
    }
});
// Final de eventos generales

// Inicio de cambio de tema
themeToggle?.addEventListener("click", () => {
    document.body.classList.toggle("dark-mode");
    const isDarkMode = document.body.classList.contains("dark-mode");
    themeToggle.innerHTML = isDarkMode
        ? '<i class="fa-solid fa-sun"></i>'
        : '<i class="fa-solid fa-moon"></i>';
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
        window.location.href = "login.html";
    });
});
// Final de cierre de sesion
