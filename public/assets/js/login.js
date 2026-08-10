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

// Manejo de contador de bloqueo temporal (Lockout Countdown)
(() => {
    if (!loginAlert) return;
    let seconds = Number(loginAlert.dataset.lockoutSeconds || 0);
    if (seconds <= 0) return;

    const alertText = document.querySelector("#loginAlertText") || loginAlert.querySelector("span");
    const submitBtn = loginForm?.querySelector("button[type='submit']");

    const updateUI = (secs) => {
        const mins = Math.floor(secs / 60);
        const remSecs = secs % 60;
        const formatted = `${String(mins).padStart(2, "0")}:${String(remSecs).padStart(2, "0")}`;
        if (alertText) alertText.textContent = `Demasiados intentos de inicio de sesión. Podrás intentarlo nuevamente en ${formatted}.`;
        if (submitBtn) submitBtn.disabled = true;
        if (userInput) userInput.disabled = true;
        if (passwordInput) passwordInput.disabled = true;
    };

    updateUI(seconds);
    const interval = setInterval(() => {
        seconds--;
        if (seconds <= 0) {
            clearInterval(interval);
            loginAlert.classList.remove("show");
            if (submitBtn) submitBtn.disabled = false;
            if (userInput) userInput.disabled = false;
            if (passwordInput) passwordInput.disabled = false;
            if (alertText) alertText.textContent = "";
        } else {
            updateUI(seconds);
        }
    }, 1000);
})();

// Inicio de envio del formulario
loginForm?.addEventListener("submit", (event) => {
    updateFieldState(userInput);
    updateFieldState(passwordInput);

    const hasEmptyFields = !userInput.value.trim() || !passwordInput.value.trim();
    loginAlert?.classList.remove("show");

    if (hasEmptyFields) event.preventDefault();
});
// Final de envio del formulario

document.querySelector("[data-session-replacement-form]")?.addEventListener("submit", (event) => {
    const form = event.currentTarget;
    const button = form.querySelector("button[type='submit']");
    if (!button || button.disabled) { event.preventDefault(); return; }
    button.disabled = true;
    button.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin" aria-hidden="true"></i><span>Iniciando…</span>';
});
