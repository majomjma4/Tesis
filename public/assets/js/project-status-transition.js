// Transiciones académicas administradas por la política entregada por el backend.
(() => {
    const overlay = document.querySelector("[data-project-status-dialog]");
    if (!overlay) return;
    const dialog = overlay.querySelector("[data-project-status-dialog-card]");
    const title = overlay.querySelector("[data-project-status-title]");
    const warning = overlay.querySelector("[data-project-status-warning]");
    const current = overlay.querySelector("[data-project-status-current]");
    const target = overlay.querySelector("[data-project-status-target]");
    const effect = overlay.querySelector("[data-project-status-effect]");
    const requirements = overlay.querySelector("[data-project-status-requirements]");
    const reasonWrap = overlay.querySelector("[data-project-status-reason-wrap]");
    const reason = overlay.querySelector("[data-project-status-reason]");
    const reasonLabel = overlay.querySelector("[data-project-status-reason-label]");
    const reasonHelp = overlay.querySelector("[data-project-status-reason-help]");
    const observationsWrap = overlay.querySelector("[data-project-observations-wrap]");
    const observationList = overlay.querySelector("[data-project-observation-list]");
    const observationAdd = overlay.querySelector("[data-project-observation-add]");
    const error = overlay.querySelector("[data-project-status-error]");
    const closeButton = overlay.querySelector("[data-project-status-close]");
    const cancelButton = overlay.querySelector("[data-project-status-cancel]");
    const submitButton = overlay.querySelector("[data-project-status-submit]");
    const submitLabel = overlay.querySelector("[data-project-status-submit-label]");
    let selected = null;
    let returnFocus = null;
    let busy = false;
    const syncObservationRemoveButtons = () => {
        const buttons = [...observationList.querySelectorAll(".pst-remove-observation")];
        buttons.forEach(button => { button.disabled = buttons.length === 1; button.title = buttons.length === 1 ? "Debe existir al menos una observación." : ""; });
    };
    const clearFieldError = field => {
        field?.removeAttribute("aria-invalid");
        if (!error.hidden) { error.hidden = true; error.textContent = ""; }
    };
    const addObservation = () => {
        const row = document.createElement("div"); row.className = "pst-observation-row";
        const category = document.createElement("select"); category.setAttribute("aria-label", "Categoría de la observación");
        [["", "Sin categoría"], ["content", "Contenido"], ["methodology", "Metodología"], ["format", "Formato"], ["documentation", "Documentación"]].forEach(([value, label]) => category.add(new Option(label, value)));
        const reference = document.createElement("input"); reference.type = "text"; reference.maxLength = 180; reference.placeholder = "Referencia (opcional)"; reference.setAttribute("aria-label", "Referencia de la observación");
        const file = document.createElement("select"); file.setAttribute("aria-label", "Archivo relacionado"); file.add(new Option("Sin archivo relacionado", ""));
        (selected?.files || []).forEach(item => file.add(new Option(item.name, String(item.id))));
        const body = document.createElement("textarea"); body.minLength = 5; body.maxLength = 2000; body.placeholder = "Describe la corrección solicitada"; body.setAttribute("aria-label", "Contenido de la observación"); body.setAttribute("aria-describedby", "pstObservationHelp pstError");
        const remove = document.createElement("button"); remove.type = "button"; remove.className = "pst-remove-observation"; remove.setAttribute("aria-label", "Eliminar observación"); remove.innerHTML = '<i class="fa-solid fa-trash-can" aria-hidden="true"></i>';
        remove.addEventListener("click", () => { if (observationList.children.length > 1) { row.remove(); syncObservationRemoveButtons(); } });
        [category,reference,file,body].forEach(field => field.addEventListener("input", () => clearFieldError(field)));
        row.append(category, reference, remove, file, body); observationList.append(row); syncObservationRemoveButtons();
    };

    const setBusy = value => {
        busy = value;
        closeButton.disabled = value;
        cancelButton.disabled = value;
        submitButton.disabled = value || !selected?.requirements_met;
        submitLabel.textContent = value ? "Actualizando…" : "Confirmar";
        dialog.setAttribute("aria-busy", String(value));
    };
    const close = () => {
        if (busy || overlay.hidden) return;
        overlay.hidden = true;
        document.documentElement.classList.remove("pst-open");
        document.body.classList.remove("pst-open");
        returnFocus?.focus();
    };
    const open = trigger => {
        try { selected = JSON.parse(trigger.dataset.projectStatusTransition || "{}"); }
        catch { selected = null; }
        if (!selected?.target) return;
        returnFocus = trigger;
        title.textContent = selected.dialog_title || selected.label || "Cambiar estado académico";
        warning.textContent = selected.warning || "";
        current.textContent = selected.current_label || "";
        target.textContent = selected.target_label || "";
        effect.textContent = selected.effect || "";
        reason.value = "";
        reason.removeAttribute("aria-invalid");
        reasonWrap.hidden = !selected.reason_required;
        observationsWrap.hidden = !selected.structured_observations;
        observationList.replaceChildren();
        if (selected.structured_observations) addObservation();
        reasonLabel.textContent = selected.reason_label || "Motivo obligatorio";
        reasonHelp.textContent = selected.reason_help || "Entre 5 y 500 caracteres.";
        error.hidden = true;
        error.textContent = "";
        requirements.replaceChildren();
        (selected.requirements || []).forEach(item => {
            const row = document.createElement("li");
            row.classList.toggle("is-met", Boolean(item.met));
            const icon = document.createElement("i");
            icon.className = `fa-solid ${item.met ? "fa-circle-check" : "fa-circle-exclamation"}`;
            icon.setAttribute("aria-hidden", "true");
            const text = document.createElement("span");
            text.textContent = item.met ? item.label : item.message;
            row.append(icon, text);
            requirements.append(row);
        });
        requirements.hidden = !(selected.requirements || []).length;
        setBusy(false);
        overlay.hidden = false;
        document.documentElement.classList.add("pst-open");
        document.body.classList.add("pst-open");
        const menuPanel = document.querySelector("[data-record-menu-panel]");
        const menuTrigger = document.querySelector("[data-record-menu-trigger]");
        if (menuPanel) menuPanel.hidden = true;
        menuTrigger?.setAttribute("aria-expanded", "false");
        window.requestAnimationFrame(() => (selected.reason_required ? reason : (selected.structured_observations ? observationList.querySelector("textarea") : submitButton)).focus());
    };
    const submit = async () => {
        if (!selected || busy || !selected.requirements_met) return;
        const reasonValue = reason.value.trim();
        if (selected.reason_required && (reasonValue.length < 5 || reasonValue.length > 500)) {
            error.textContent = "Indica un motivo de entre 5 y 500 caracteres.";
            error.hidden = false;
            reason.setAttribute("aria-invalid", "true");
            reason.focus();
            return;
        }
        setBusy(true);
        error.hidden = true;
        const body = new FormData();
        body.set("_csrf", overlay.dataset.csrf || "");
        body.set("action", selected.action || "change_status");
        body.set("id", overlay.dataset.projectId || "");
        body.set("expected_status", overlay.dataset.currentStatus || "");
        if (!selected.structured_observations) body.set("target_status", selected.target);
        if (selected.expected_published_at) body.set("expected_published_at", selected.expected_published_at);
        if (selected.reason_required) body.set("reason", reasonValue);
        if (selected.structured_observations) {
            const observations = [...observationList.querySelectorAll(".pst-observation-row")].map(row => ({category: row.querySelector("select")?.value || "General", location_reference: row.querySelector("input")?.value.trim() || null, file_id: Number(row.querySelectorAll("select")[1]?.value || 0) || null, body: row.querySelector("textarea")?.value.trim() || ""}));
            const textareas = [...observationList.querySelectorAll("textarea")];
            textareas.forEach(field => field.removeAttribute("aria-invalid"));
            const invalidIndex = observations.findIndex(item => item.body.length < 5 || item.body.length > 2000);
            const normalizedBodies = observations.map(item => item.body.toLocaleLowerCase("es"));
            const duplicateIndex = normalizedBodies.findIndex((body, index) => normalizedBodies.indexOf(body) !== index);
            if (!observations.length || invalidIndex >= 0 || duplicateIndex >= 0) {
                const fieldIndex = invalidIndex >= 0 ? invalidIndex : duplicateIndex;
                const invalidField = textareas[Math.max(0, fieldIndex)];
                error.textContent = duplicateIndex >= 0 ? "No incluyas observaciones duplicadas en la misma revisión." : "Registra observaciones válidas de entre 5 y 2000 caracteres.";
                error.hidden = false; invalidField?.setAttribute("aria-invalid", "true"); setBusy(false); invalidField?.focus(); return;
            }
            body.set("observations", JSON.stringify(observations));
            if (selected.delivery_id) body.set("delivery_id", String(selected.delivery_id));
        }
        try {
            const response = await fetch(overlay.dataset.endpoint, { method: "POST", body, credentials: "same-origin" });
            const result = await response.json();
            if (!response.ok || !result.success) throw new Error(result.message || "No fue posible cambiar el estado académico.");
            sessionStorage.setItem("digitalRecordToast", result.message || "Estado académico actualizado correctamente.");
            if (overlay.dataset.closeEditorOnSuccess === "1") {
                const destination = new URL(window.location.href);
                destination.searchParams.delete("edit");
                window.location.replace(destination.toString());
            } else {
                window.location.reload();
            }
        } catch (requestError) {
            error.textContent = requestError instanceof Error ? requestError.message : "No fue posible cambiar el estado académico.";
            error.hidden = false;
            setBusy(false);
        }
    };

    document.addEventListener("click", event => {
        const trigger = event.target.closest?.("[data-project-status-transition]");
        if (!trigger || trigger.disabled) return;
        open(trigger);
    });
    closeButton.addEventListener("click", close);
    cancelButton.addEventListener("click", close);
    submitButton.addEventListener("click", submit);
    observationAdd?.addEventListener("click", addObservation);
    reason?.addEventListener("input", () => clearFieldError(reason));
    overlay.addEventListener("click", event => { if (event.target === overlay) close(); });
    overlay.addEventListener("keydown", event => {
        if (event.key === "Escape") { event.preventDefault(); close(); return; }
        if (event.key !== "Tab") return;
        const controls = [...dialog.querySelectorAll("button:not(:disabled),textarea:not(:disabled),input:not(:disabled),select:not(:disabled)")].filter(control => !control.hidden && control.getClientRects().length > 0);
        if (!controls.length) return;
        const index = controls.indexOf(document.activeElement);
        if ((!event.shiftKey && index === controls.length - 1) || (event.shiftKey && index <= 0)) {
            event.preventDefault();
            controls[event.shiftKey ? controls.length - 1 : 0].focus();
        }
    });
})();
