(() => {
    const overlay = document.querySelector("[data-project-description-reminder]");
    if (!overlay) return;
    const form = overlay.querySelector("[data-project-description-form]");
    const field = form?.elements.namedItem("description");
    const error = overlay.querySelector("[data-project-description-error]");
    const skip = overlay.querySelector("[data-project-description-skip]");
    const submit = form?.querySelector('[type="submit"]');
    const close = () => {
        overlay.hidden = true;
        document.body.classList.remove("project-description-open");
    };
    document.body.classList.add("project-description-open");
    window.requestAnimationFrame(() => field?.focus());
    skip?.addEventListener("click", close);
    form?.addEventListener("submit", async event => {
        event.preventDefault();
        const description = String(field?.value ?? "").trim();
        if (!description) {
            error.textContent = 'Escribe una descripción o selecciona “Omitir por ahora”.';
            error.hidden = false;
            field?.focus();
            return;
        }
        error.hidden = true;
        submit.disabled = true;
        try {
            const response = await fetch(overlay.dataset.endpoint, { method: "POST", body: new FormData(form), headers: { "X-Requested-With": "XMLHttpRequest" } });
            const payload = await response.json();
            if (!response.ok || !payload.success) throw new Error(payload.message || "No fue posible guardar la descripción.");
            close();
        } catch (exception) {
            error.textContent = exception.message;
            error.hidden = false;
        } finally {
            submit.disabled = false;
        }
    });
})();
