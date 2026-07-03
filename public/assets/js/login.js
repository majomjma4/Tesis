const loginForm = document.querySelector("#loginForm");
const userInput = document.querySelector("#user");
const passwordInput = document.querySelector("#password");
const loginAlert = document.querySelector("#loginAlert");

function updateFieldState(input) {
    const group = input.closest(".form-group");
    group.classList.toggle("invalid", input.value.trim() === "");
}

[userInput, passwordInput].forEach((input) => {
    input?.addEventListener("input", () => updateFieldState(input));
    input?.addEventListener("blur", () => updateFieldState(input));
});

loginForm?.addEventListener("submit", (event) => {
    event.preventDefault();

    updateFieldState(userInput);
    updateFieldState(passwordInput);

    const hasEmptyFields = !userInput.value.trim() || !passwordInput.value.trim();
    loginAlert?.classList.remove("show");

    if (!hasEmptyFields) {
        window.location.href = loginForm.dataset.dashboardUrl;
    }
});
