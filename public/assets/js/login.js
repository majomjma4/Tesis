// Inicio de seleccion de elementos del formulario
const loginForm = document.querySelector("#loginForm");
const userInput = document.querySelector("#user");
const passwordInput = document.querySelector("#password");
const loginAlert = document.querySelector("#loginAlert");
const passwordToggle = document.querySelector("#passwordToggle");
const loginUserClear = document.querySelector("#loginUserClear");
// Final de seleccion de elementos del formulario

function hidePassword() {
    if (!passwordInput || !passwordToggle) return;
    passwordInput.type = "password";
    passwordToggle.setAttribute("aria-pressed", "false");
    passwordToggle.setAttribute("aria-label", "Mostrar contraseña");
    passwordToggle.title = "Mostrar contraseña";
    passwordToggle.querySelector("i").className = "fa-regular fa-eye";
}

hidePassword();
window.addEventListener("pageshow", hidePassword);

function syncUserClearButton() {
    if (loginUserClear) loginUserClear.hidden = userInput?.value === "";
}

syncUserClearButton();
window.addEventListener("pageshow", syncUserClearButton);
window.addEventListener("load", syncUserClearButton, { once: true });
window.setTimeout(syncUserClearButton, 250);
window.setTimeout(syncUserClearButton, 1000);

loginUserClear?.addEventListener("click", () => {
    if (!userInput) return;
    userInput.value = "";
    userInput.dispatchEvent(new Event("input", { bubbles: true }));
    userInput.focus({ preventScroll: true });
});

// Inicio de validacion visual de campos
function updateFieldState(input) {
    const group = input.closest(".form-group");
    group.classList.toggle("invalid", input.value.trim() === "");
}
// Final de validacion visual de campos

// Inicio de eventos de entrada
[userInput, passwordInput].forEach((input) => {
    input?.addEventListener("input", () => {
        updateFieldState(input);
        if (input === userInput) syncUserClearButton();
    });
    input?.addEventListener("blur", () => updateFieldState(input));
});

// Enter en correo continúa hacia contraseña; Enter en contraseña envía el formulario.
userInput?.addEventListener("keydown", (event) => {
    if (event.key !== "Enter") return;
    event.preventDefault();
    updateFieldState(userInput);
    if (userInput.value.trim() !== "") passwordInput?.focus();
});

passwordInput?.addEventListener("keydown", (event) => {
    if (event.key !== "Enter") return;
    event.preventDefault();
    loginForm?.requestSubmit();
});

// Alterna la visibilidad sin perder el foco ni modificar el valor ingresado.
passwordToggle?.addEventListener("click", () => {
    const willShow = passwordInput.type === "password";
    passwordInput.type = willShow ? "text" : "password";
    passwordToggle.setAttribute("aria-pressed", String(willShow));
    passwordToggle.setAttribute("aria-label", willShow ? "Ocultar contraseña" : "Mostrar contraseña");
    passwordToggle.title = willShow ? "Ocultar contraseña" : "Mostrar contraseña";
    passwordToggle.querySelector("i").className = willShow ? "fa-regular fa-eye-slash" : "fa-regular fa-eye";
    passwordInput.focus({ preventScroll: true });
});
// Final de eventos de entrada

// Inicio de envio del formulario
loginForm?.addEventListener("submit", (event) => {
    updateFieldState(userInput);
    updateFieldState(passwordInput);

    const hasEmptyFields = !userInput.value.trim() || !passwordInput.value.trim();
    loginAlert?.classList.remove("show");

    if (hasEmptyFields) event.preventDefault();
});
// Final de envio del formulario
