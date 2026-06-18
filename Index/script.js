/*=========================================================
    MENSAJE DE INICIO
=========================================================*/
console.log("Interfaz cargada correctamente");

/*=========================================================
    ANIMACIÓN DE APARICIÓN DE TARJETAS
=========================================================*/
window.addEventListener("load", () => {
    const cards = document.querySelectorAll(
        ".status-card, .project-card, .notification-card, .announcement-card, .activity-card"
    );
    cards.forEach((card, index) => {
        setTimeout(() => {
            card.classList.add("show");
        }, index * 100);
    });
});

/*=========================================================
    EFECTO DE LA CAMPANA DE NOTIFICACIONES
=========================================================*/
const bell = document.querySelector(".notification-icon");
if (bell) {
    bell.addEventListener("click", () => {
        bell.classList.add("ring");
        setTimeout(() => {
            bell.classList.remove("ring");
        }, 500);
    });
}

/*=========================================================
    EFECTO DEL AVATAR DEL USUARIO
=========================================================*/
const avatar = document.querySelector(".user-avatar");
if (avatar) {
    avatar.addEventListener("mouseenter", () => {
        avatar.style.transform = "scale(1.1)";
    });
    avatar.addEventListener("mouseleave", () => {
        avatar.style.transform = "scale(1)";
    });
}

/*=========================================================
    HOVER EN BOTONES
=========================================================*/
const buttons = document.querySelectorAll(".open-btn, .upload-btn");
buttons.forEach(button => {
    button.addEventListener("mouseenter", () => {
        button.style.transform = "translateY(-3px)";
    });
    button.addEventListener("mouseleave", () => {
        button.style.transform = "translateY(0)";
    });
});

/*=========================================================
    SALUDO EN CONSOLA
=========================================================*/
console.log("Dashboard inicializado correctamente.");
