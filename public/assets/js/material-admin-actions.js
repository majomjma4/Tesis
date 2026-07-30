(() => {
    const overlay = document.querySelector("[data-sma-overlay]");
    if (!overlay) return;
    if (overlay.parentElement !== document.body) document.body.append(overlay);
    const title = overlay.querySelector("[data-sma-title]");
    const description = overlay.querySelector("[data-sma-description]");
    const selection = overlay.querySelector("[data-sma-selection]");
    const confirmation = overlay.querySelector("[data-sma-confirmation]");
    const reason = overlay.querySelector("[data-sma-reason]");
    const reasonValue = overlay.querySelector("[data-sma-reason-value]");
    const reasonText = overlay.querySelector("[data-sma-reason-text]");
    const reasonList = document.createElement("div");
    reasonList.id = "smaReasonListbox";
    reasonList.className = "sma-combobox-list";
    reasonList.setAttribute("role", "listbox");
    reasonList.hidden = true;
    document.body.append(reasonList);
    const detailWrap = overlay.querySelector("[data-sma-detail-wrap]");
    const detail = overlay.querySelector("[data-sma-detail]");
    const warning = overlay.querySelector("[data-sma-warning]");
    const materialRow = overlay.querySelector("[data-sma-material-row]");
    const materialLabel = overlay.querySelector("[data-sma-material]");
    const reasonLabel = overlay.querySelector("[data-sma-reason-label]");
    const detailRow = overlay.querySelector("[data-sma-detail-row]");
    const detailLabel = overlay.querySelector("[data-sma-detail-label]");
    const error = overlay.querySelector("[data-sma-error]");
    const next = overlay.querySelector("[data-sma-next]");
    const back = overlay.querySelector("[data-sma-back]");
    const cancel = overlay.querySelector("[data-sma-cancel]");
    const closeButton = overlay.querySelector("[data-sma-close]");
    const submit = overlay.querySelector("[data-sma-submit]");
    const submitLabel = overlay.querySelector("[data-sma-submit-label]");
    let config = null;
    let processing = false;
    let returnFocus = null;
    let reasonMenuActive = false;
    let outsideClosedDropdown = false;
    let backgroundState = [];
    let previousBodyOverflow = "";
    let previousHtmlOverflow = "";

    const lockBackground = () => {
        previousBodyOverflow = document.body.style.overflow;
        previousHtmlOverflow = document.documentElement.style.overflow;
        backgroundState = [...document.body.children]
            .filter(element => ![overlay, reasonList].includes(element) && !["SCRIPT", "STYLE"].includes(element.tagName))
            .map(element => ({ element, inert: element.inert, ariaHidden: element.getAttribute("aria-hidden") }));
        backgroundState.forEach(({ element }) => {
            element.inert = true;
            element.setAttribute("aria-hidden", "true");
        });
        document.body.style.overflow = "hidden";
        document.documentElement.style.overflow = "hidden";
    };
    const unlockBackground = () => {
        backgroundState.forEach(({ element, inert, ariaHidden }) => {
            element.inert = inert;
            if (ariaHidden === null) element.removeAttribute("aria-hidden");
            else element.setAttribute("aria-hidden", ariaHidden);
        });
        backgroundState = [];
        document.body.style.overflow = previousBodyOverflow;
        document.documentElement.style.overflow = previousHtmlOverflow;
    };

    const definitions = {
        availability_off: {
            title: "Marcar como no disponible",
            confirmTitle: "Confirmar cambio de disponibilidad",
            warning: "El material permanecerá publicado, pero los usuarios no podrán consultar ni descargar sus archivos hasta que vuelva a estar disponible.",
            submit: "Marcar como no disponible", processing: "Actualizando disponibilidad…",
            reasons: [["temporary_update","Actualización temporal del contenido"],["administrative_review","Revisión administrativa"],["files_pending","Archivos pendientes de corrección"],["outdated","Información desactualizada"],["temporary_suspension","Acceso suspendido temporalmente"],["other","Otro motivo"]],
        },
        availability_on: {
            title: "Marcar como disponible",
            confirmTitle: "Confirmar cambio de disponibilidad",
            warning: "El material volverá a estar disponible para consulta y descarga.",
            submit: "Marcar como disponible", processing: "Actualizando disponibilidad…",
            reasons: [["corrections_completed","Correcciones completadas"],["content_updated","Contenido actualizado"],["files_verified","Archivos verificados"],["review_completed","Revisión administrativa finalizada"],["other","Otro motivo"]],
        },
        withdraw: {
            title: "Retirar publicación", confirmTitle: "Confirmar retiro de publicación",
            warning: "El material dejará de mostrarse como publicado. No será enviado automáticamente a Papelera; conservará su información y archivos y podrá volver a publicarse posteriormente.",
            submit: "Retirar publicación", processing: "Retirando publicación…",
            reasons: [["incorrect","Publicación incorrecta"],["outdated","Contenido desactualizado"],["pending_review","Material pendiente de revisión"],["incomplete_files","Archivos incompletos"],["replaced","Material reemplazado"],["other","Otro motivo"]],
        },
        publish: {
            title: "Publicar material", confirmTitle: "Confirmar publicación",
            warning: "El material volverá a mostrarse como publicado y estará disponible según su configuración actual.",
            submit: "Publicar material", processing: "Publicando material…",
            reasons: [["review_completed","Revisión administrativa finalizada"],["content_updated","Contenido actualizado"],["corrections_completed","Correcciones completadas"],["other","Otro motivo"]],
        },
        trash: {
            title: "Enviar a Papelera", confirmTitle: "Confirmar envío a Papelera",
            warning: "El material dejará de estar disponible en el Repositorio. Sus archivos y metadatos permanecerán temporalmente recuperables según la política de Papelera.",
            submit: "Enviar a Papelera", processing: "Enviando a Papelera…",
            reasons: [["duplicate","Contenido duplicado"],["outdated","Información desactualizada"],["replaced","Material reemplazado"],["incorrect","Publicación incorrecta"],["not_required","Ya no es requerido"],["other","Otro motivo"]],
        },
    };
    const currentDefinition = () => definitions[config?.type];
    const selectedLabel = () => reasonText.textContent.trim();
    const selectedValue = () => reasonValue.value;
    const valid = () => selectedValue() && (selectedValue() !== "other" || detail.value.trim().length >= 5);
    const positionReasonList = () => {
        if (reasonList.hidden) return;
        const rect = reason.getBoundingClientRect();
        const viewportGap = 10;
        reasonList.style.height = "auto";
        reasonList.style.maxHeight = "none";
        reasonList.style.overflowY = "hidden";
        const options = [...reasonList.querySelectorAll('[role="option"]')];
        const style = getComputedStyle(reasonList);
        const verticalChrome = parseFloat(style.paddingTop) + parseFloat(style.paddingBottom)
            + parseFloat(style.borderTopWidth) + parseFloat(style.borderBottomWidth);
        const rowHeights = options.map(option => option.getBoundingClientRect().height);
        const totalHeight = verticalChrome + rowHeights.reduce((sum, height) => sum + height, 0);
        const sixRowHeight = verticalChrome + rowHeights.slice(0, 6).reduce((sum, height) => sum + height, 0);
        const contentLimit = options.length > 6 ? sixRowHeight : totalHeight;
        const below = window.innerHeight - rect.bottom - viewportGap;
        const above = rect.top - viewportGap;
        const openAbove = below < contentLimit && above > below;
        const available = Math.max(0, openAbove ? above : below);
        const targetLimit = Math.min(totalHeight, sixRowHeight, available);
        let completeRowsHeight = verticalChrome;
        for (const rowHeight of rowHeights.slice(0, 6)) {
            if (completeRowsHeight + rowHeight > targetLimit + .5) break;
            completeRowsHeight += rowHeight;
        }
        if (completeRowsHeight <= verticalChrome && rowHeights.length) {
            completeRowsHeight = Math.min(available, verticalChrome + rowHeights[0]);
        }
        const finalHeight = Math.min(contentLimit, available, completeRowsHeight);
        reasonList.style.left = `${Math.max(viewportGap, Math.min(rect.left, window.innerWidth - rect.width - viewportGap))}px`;
        reasonList.style.width = `${Math.min(rect.width, window.innerWidth - viewportGap * 2)}px`;
        reasonList.style.height = `${Math.max(0, finalHeight)}px`;
        reasonList.style.top = openAbove
            ? `${Math.max(viewportGap, rect.top - finalHeight - 5)}px`
            : `${Math.min(window.innerHeight - viewportGap, rect.bottom + 5)}px`;
        reasonList.style.overflowY = reasonList.scrollHeight > reasonList.clientHeight + 1 ? "auto" : "hidden";
    };
    const closeReasonList = (restoreFocus = false) => {
        if (reasonList.hidden) return;
        reasonList.hidden = true;
        reason.setAttribute("aria-expanded", "false");
        reason.removeAttribute("aria-activedescendant");
        reasonMenuActive = false;
        if (restoreFocus) reason.focus();
    };
    const openReasonList = () => {
        if (processing || !reasonList.children.length) return;
        reasonList.hidden = false;
        reason.setAttribute("aria-expanded", "true");
        reasonMenuActive = true;
        const selected = reasonList.querySelector('[aria-selected="true"]');
        reasonList.querySelectorAll(".is-active").forEach(item => item.classList.remove("is-active"));
        const active = selected || reasonList.firstElementChild;
        active?.classList.add("is-active");
        if (active) reason.setAttribute("aria-activedescendant", active.id);
        positionReasonList();
        (selected || reasonList.firstElementChild)?.scrollIntoView({ block: "nearest" });
    };
    const chooseReason = option => {
        reasonValue.value = option.dataset.value;
        reasonText.textContent = option.textContent;
        reasonList.querySelectorAll('[role="option"]').forEach(item => item.setAttribute("aria-selected", String(item === option)));
        detailWrap.hidden = selectedValue() !== "other";
        if (selectedValue() !== "other") detail.value = "";
        next.disabled = !valid();
        error.hidden = true;
        closeReasonList(true);
        if (selectedValue() === "other") detail.focus();
    };
    const setStep = step => {
        const confirming = step === "confirm";
        selection.hidden = confirming;
        confirmation.hidden = !confirming;
        back.hidden = !confirming;
        next.hidden = confirming;
        submit.hidden = !confirming;
        title.textContent = confirming ? currentDefinition().confirmTitle : currentDefinition().title;
        if (confirming) {
            warning.textContent = currentDefinition().warning;
            reasonLabel.textContent = selectedLabel();
            detailRow.hidden = selectedValue() !== "other";
            detailLabel.textContent = detail.value.trim();
            materialRow.hidden = config.type !== "trash";
            materialLabel.textContent = config.material.title || "";
            submitLabel.textContent = currentDefinition().submit;
            back.focus();
        } else reason.focus();
    };
    const close = () => {
        if (processing) return;
        closeReasonList();
        overlay.hidden = true;
        document.documentElement.classList.remove("sma-open");
        document.body.classList.remove("sma-open");
        unlockBackground();
        returnFocus?.focus?.();
    };
    const open = options => {
        config = options;
        returnFocus = options.trigger || document.activeElement;
        const definition = currentDefinition();
        reasonList.replaceChildren();
        definition.reasons.forEach(([value,label], index) => {
            const option = document.createElement("button");
            option.type = "button";
            option.className = "sma-combobox-option";
            option.id = `smaReasonOption-${index}`;
            option.setAttribute("role", "option");
            option.setAttribute("aria-selected", "false");
            option.tabIndex = -1;
            option.dataset.value = value;
            option.textContent = label;
            option.addEventListener("click", () => chooseReason(option));
            reasonList.append(option);
        });
        reasonValue.value = ""; reasonText.textContent = "Selecciona un motivo";
        detail.value = ""; detailWrap.hidden = true; error.hidden = true;
        description.textContent = "Selecciona el motivo y revisa la advertencia antes de confirmar.";
        next.disabled = true; submit.disabled = false; cancel.disabled = false; closeButton.disabled = false;
        setStep("select");
        lockBackground();
        overlay.hidden = false;
        document.documentElement.classList.add("sma-open");
        document.body.classList.add("sma-open");
        reason.focus();
    };
    reason.addEventListener("click", () => reasonList.hidden ? openReasonList() : closeReasonList(true));
    reason.addEventListener("keydown", event => {
        if (event.repeat && ["Enter"," "].includes(event.key)) {
            event.preventDefault();
            return;
        }
        if (["Enter"," "].includes(event.key)) {
            event.preventDefault();
            if (reasonList.hidden) openReasonList();
            else {
                const active = reasonList.querySelector(".is-active");
                if (active) chooseReason(active);
                else closeReasonList(true);
            }
            return;
        }
        if (event.key === "Escape" && !reasonList.hidden) {
            event.preventDefault(); event.stopPropagation(); closeReasonList(true); return;
        }
        if (!["ArrowDown","ArrowUp","Home","End"].includes(event.key)) return;
        event.preventDefault();
        if (reasonList.hidden) openReasonList();
        const options = [...reasonList.querySelectorAll('[role="option"]')];
        let index = options.findIndex(item => item.classList.contains("is-active"));
        if (event.key === "Home") index = 0;
        else if (event.key === "End") index = options.length - 1;
        else index = event.key === "ArrowDown" ? Math.min(options.length - 1,index + 1) : Math.max(0,index - 1);
        options.forEach(item => item.classList.remove("is-active"));
        options[index]?.classList.add("is-active");
        if (options[index]) reason.setAttribute("aria-activedescendant", options[index].id);
        options[index]?.scrollIntoView({ block: "nearest" });
    });
    detail.addEventListener("input", () => { next.disabled = !valid(); error.hidden = true; });
    next.addEventListener("click", () => {
        detail.value = detail.value.trim();
        if (!valid()) { error.textContent = "Completa el motivo antes de continuar."; error.hidden = false; return; }
        setStep("confirm");
    });
    back.addEventListener("click", () => setStep("select"));
    cancel.addEventListener("click", close);
    closeButton.addEventListener("click", close);
    document.addEventListener("pointerdown", event => {
        if (reasonList.hidden || reason.contains(event.target) || reasonList.contains(event.target)) return;
        outsideClosedDropdown = event.target === overlay;
        closeReasonList(true);
    }, true);
    overlay.addEventListener("click", event => {
        if (event.target !== overlay) return;
        if (outsideClosedDropdown) { outsideClosedDropdown = false; return; }
        close();
    });
    window.addEventListener("resize", () => closeReasonList());
    document.addEventListener("scroll", event => {
        if (!reasonList.hidden && event.target !== reasonList && !reasonList.contains(event.target)) closeReasonList();
    }, true);
    document.addEventListener("keydown", event => {
        if (overlay.hidden) return;
        if (event.key === "Escape") {
            if (document.activeElement === reason && reasonMenuActive) {
                event.preventDefault();
                reasonMenuActive = false;
                return;
            }
            event.preventDefault();
            close();
            return;
        }
        if (!event.defaultPrevented && ["ArrowLeft","ArrowRight","ArrowUp","ArrowDown"].includes(event.key)) {
            const active = document.activeElement;
            if (active?.matches("button:not([role='combobox'])") && !active.closest('[role="listbox"]')) {
                const controls = [...overlay.querySelectorAll(
                    'button:not(:disabled):not([hidden]),[tabindex]:not([tabindex="-1"])'
                )].filter(control => control.getClientRects().length > 0);
                const index = controls.indexOf(active);
                if (index >= 0 && controls.length > 1) {
                    event.preventDefault();
                    const backwards = event.key === "ArrowLeft" || event.key === "ArrowUp";
                    const next = backwards
                        ? (index === 0 ? controls.length - 1 : index - 1)
                        : (index === controls.length - 1 ? 0 : index + 1);
                    controls[next]?.focus();
                }
            }
            return;
        }
        if (event.key !== "Tab") return;
        if (reasonMenuActive) closeReasonList(false);
        const controls = [...overlay.querySelectorAll(
            'button:not(:disabled):not([hidden]),input:not(:disabled):not([hidden]),textarea:not(:disabled):not([hidden]),[tabindex]:not([tabindex="-1"])'
        )].filter(control => control.getClientRects().length > 0);
        if (!controls.length) return;
        const index = controls.indexOf(document.activeElement);
        const nextIndex = event.shiftKey
            ? (index <= 0 ? controls.length - 1 : index - 1)
            : (index === controls.length - 1 ? 0 : index + 1);
        event.preventDefault();
        controls[nextIndex]?.focus();
    });
    submit.addEventListener("click", async () => {
        if (processing) return;
        processing = true; submit.disabled = true; cancel.disabled = true; back.disabled = true; closeButton.disabled = true;
        submitLabel.textContent = currentDefinition().processing; error.hidden = true;
        const body = new FormData();
        body.set("_csrf", config.csrf); body.set("id", config.material.id); body.set("action", config.action);
        body.set("reason_code", selectedValue()); body.set("reason_detail", selectedValue() === "other" ? detail.value.trim() : "");
        if (config.action === "availability") body.set("is_available", config.available ? "0" : "1");
        if (config.action === "publication") body.set("status", config.type === "publish" ? "published" : "withdrawn");
        try {
            const response = await fetch(config.endpoint, { method: "POST", body, credentials: "same-origin" });
            const result = await response.json();
            if (!response.ok || !result.success) throw new Error(result.message || "No fue posible completar la acción.");
            processing = false; close();
            config.onSuccess?.(result);
        } catch (requestError) {
            error.textContent = requestError.message; error.hidden = false;
        } finally {
            processing = false; submit.disabled = false; cancel.disabled = false; back.disabled = false; closeButton.disabled = false;
            submitLabel.textContent = currentDefinition().submit;
        }
    });
    window.SupportMaterialAdminActions = { open };
})();
