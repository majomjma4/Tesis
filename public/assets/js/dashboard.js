console.log("Dashboard inicializado correctamente.");

// Inicio de selección de elementos específicos del dashboard
const teamToggle = document.querySelector("#teamToggle");
const teamDropdown = document.querySelector("#teamDropdown");
// Final de selección de elementos específicos del dashboard

// Inicio de funciones de menú del equipo
function closeTeamMenu() {
    teamDropdown?.classList.remove("show");
    teamToggle?.setAttribute("aria-expanded", "false");
}

function toggleTeamMenu(event) {
    event.stopPropagation();
    const isOpen = teamDropdown?.classList.toggle("show");
    teamToggle?.setAttribute("aria-expanded", String(Boolean(isOpen)));
}

teamToggle?.addEventListener("click", toggleTeamMenu);

document.addEventListener("click", (event) => {
    if (!event.target.closest(".report-team")) {
        closeTeamMenu();
    }
});

document.addEventListener("keydown", (event) => {
    if (event.key === "Escape") {
        closeTeamMenu();
    }
});
// Final de funciones de menú del equipo

// Inicio de animación inicial de tarjetas
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
// Final de animación inicial de tarjetas
