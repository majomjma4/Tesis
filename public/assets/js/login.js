// Inicio de seleccion de elementos del formulario
const loginForm = document.querySelector("#loginForm");
const userInput = document.querySelector("#user");
const passwordInput = document.querySelector("#password");
const loginAlert = document.querySelector("#loginAlert");
// Final de seleccion de elementos del formulario

// Inicio de validacion visual de campos
function updateFieldState(input) {
    const group = input.closest(".form-group");
    group.classList.toggle("invalid", input.value.trim() === "");
}
// Final de validacion visual de campos

// Inicio de eventos de entrada
[userInput, passwordInput].forEach((input) => {
    input?.addEventListener("input", () => updateFieldState(input));
    input?.addEventListener("blur", () => updateFieldState(input));
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
