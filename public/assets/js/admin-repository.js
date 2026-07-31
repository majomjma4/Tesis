(() => {
    const page = document.querySelector("#arPage");
    if (!page) return;

    const config = document.querySelector("#arConfig");
    const search = document.querySelector("#arSearch");
    const clearSearch = document.querySelector("#arClearSearch");
    const typeFilter = document.querySelector("#arTypeFilter");
    const periodFilter = document.querySelector("#arPeriodFilter");
    const categoryFilter = document.querySelector("#arCategoryFilter");
    const materialStatusFilter = document.querySelector("#arMaterialStatusFilter");
    const clearMaterialFilters = document.querySelector("#arClearMaterialFilters");
    const paginationControls = Object.fromEntries(
        [...document.querySelectorAll("[data-pagination-for]")].map((pagination) => [
            pagination.dataset.paginationFor,
            {
                pagination,
                summary: pagination.querySelector("[data-pagination-summary]"),
                pages: pagination.querySelector("[data-pagination-pages]"),
                size: pagination.querySelector("[data-page-size]"),
            },
        ])
    );
    const paginationState = {
        projects: { page: 1, size: 10 },
        materials: { page: 1, size: 10 },
    };
    const materialEditModal = document.querySelector("#arMaterialEditModal");
    const materialFilesModal = document.querySelector("#arMaterialFilesModal");
    const presentationModal = document.querySelector("#arPresentationModal");
    const presentationForm = document.querySelector("#arPresentationForm");
    const presentationOptions = presentationModal?.querySelector("[data-presentation-options]");
    const presentationError = presentationModal?.querySelector("[data-presentation-error]");
    const materialEditForm = document.querySelector("#arMaterialEditForm");
    const materialFileForm = document.querySelector("#arMaterialFileForm");
    const materialFilesList = document.querySelector("#arMaterialFilesList");
    const confirmation = document.querySelector("#arConfirm");
    const confirmationTitle = document.querySelector("#arConfirmTitle");
    const confirmationText = document.querySelector("#arConfirmText");
    const confirmationAccept = confirmation?.querySelector("[data-confirm-accept]");
    const confirmationCancel = confirmation?.querySelector("[data-confirm-cancel]");
    const toastStack = document.querySelector("#arToastStack");
    const tooltip = document.querySelector("#arTooltip");
    if (materialEditModal) document.body.append(materialEditModal);
    if (materialFilesModal) document.body.append(materialFilesModal);
    if (presentationModal) document.body.append(presentationModal);
    if (confirmation) document.body.append(confirmation);
    if (toastStack) document.body.append(toastStack);
    if (tooltip) document.body.append(tooltip);
    let activeTab = "projects";
    const toastRegistry = new Map();
    const toastDurations = { success: 3800, info: 4000, warning: 5000, error: 5600 };
    const originalText = new WeakMap();

    const reflowToasts = (mutator) => {
        const before = new Map(
            [...toastStack.children].map((toast) => [toast, toast.getBoundingClientRect().top])
        );
        mutator();
        [...toastStack.children].forEach((toast) => {
            const previousTop = before.get(toast);
            if (previousTop === undefined) return;
            const delta = previousTop - toast.getBoundingClientRect().top;
            if (!delta) return;
            toast.style.transition = "none";
            toast.style.transform = `translateY(${delta}px)`;
            window.requestAnimationFrame(() => {
                toast.style.transition = "transform 220ms ease";
                toast.style.transform = "";
                window.setTimeout(() => { toast.style.transition = ""; }, 230);
            });
        });
    };

    const normalize = (value) => String(value || "")
        .normalize("NFD")
        .replace(/[\u0300-\u036f]/g, "")
        .replace(/\s+/g, " ")
        .toLowerCase()
        .trim();

    const dismissToast = (key) => {
        const entry = toastRegistry.get(key);
        if (!entry) return;
        window.clearTimeout(entry.timer);
        entry.element.classList.add("is-leaving");
        window.setTimeout(() => reflowToasts(() => entry.element.remove()), 210);
        toastRegistry.delete(key);
    };

    const showToast = (message, type = "success") => {
        if (!toastStack || !message) return;
        const normalizedType = ["success", "info", "warning", "error"].includes(type) ? type : "info";
        const key = `${normalizedType}:${message}`;
        const existing = toastRegistry.get(key);
        if (existing?.element?.isConnected) {
            existing.count += 1;
            existing.copy.textContent = `${message} ×${existing.count}`;
            window.clearTimeout(existing.timer);
            existing.timer = window.setTimeout(() => dismissToast(key), toastDurations[normalizedType]);
            return;
        }
        const element = document.createElement("div");
        const icon = document.createElement("i");
        const copy = document.createElement("span");
        const close = document.createElement("button");
        const icons = { success: "fa-circle-check", info: "fa-circle-info", warning: "fa-triangle-exclamation", error: "fa-circle-xmark" };
        element.className = `ar-toast ${normalizedType}`;
        element.setAttribute("role", normalizedType === "error" ? "alert" : "status");
        icon.className = `fa-solid ${icons[normalizedType]}`;
        copy.textContent = message;
        close.type = "button";
        close.setAttribute("aria-label", "Cerrar mensaje");
        close.innerHTML = '<i class="fa-solid fa-xmark" aria-hidden="true"></i>';
        element.append(icon, copy, close);
        toastStack.prepend(element);
        const entry = { element, copy, count: 1, timer: null };
        entry.timer = window.setTimeout(() => dismissToast(key), toastDurations[normalizedType]);
        toastRegistry.set(key, entry);
        close.addEventListener("click", () => dismissToast(key));
    };

    const requestConfirmation = (message, options = {}) => new Promise((resolve) => {
        if (!confirmation) {
            resolve(false);
            return;
        }
        confirmationTitle.textContent = options.title || "Confirmar acción";
        confirmationText.textContent = message;
        confirmationAccept.textContent = options.acceptLabel || "Confirmar";
        confirmationAccept.classList.toggle("danger", options.danger !== false);
        confirmationAccept.classList.toggle("restore-material-confirm-primary", options.variant === "restore-material");
        confirmation.hidden = false;
        document.body.classList.add("modal-open");
        confirmationAccept.focus();
        const close = (accepted) => {
            confirmation.hidden = true;
            const secondaryModalOpen = [materialEditModal, materialFilesModal, presentationModal]
                .some((modal) => modal && !modal.hidden);
            if (!secondaryModalOpen) document.body.classList.remove("modal-open");
            confirmationAccept.removeEventListener("click", accept);
            confirmationCancel.removeEventListener("click", cancel);
            confirmation.removeEventListener("click", backdrop);
            document.removeEventListener("keydown", escape);
            resolve(accepted);
        };
        const accept = () => close(true);
        const cancel = () => close(false);
        const backdrop = (event) => {
            if (event.target === confirmation) close(false);
        };
        const escape = (event) => {
            if (event.key === "Escape") close(false);
        };
        confirmationAccept.addEventListener("click", accept);
        confirmationCancel.addEventListener("click", cancel);
        confirmation.addEventListener("click", backdrop);
        document.addEventListener("keydown", escape);
    });

    const showTooltip = (trigger) => {
        if (!tooltip) return;
        const text = trigger.dataset.tooltip;
        if (!text) return;
        tooltip.textContent = text;
        tooltip.hidden = false;
        const rect = trigger.getBoundingClientRect();
        const top = rect.top > 48 ? rect.top - 36 : rect.bottom + 8;
        tooltip.style.left = `${Math.min(window.innerWidth - 112, Math.max(112, rect.left + rect.width / 2))}px`;
        tooltip.style.top = `${top}px`;
        requestAnimationFrame(() => tooltip.classList.add("is-visible"));
    };

    const hideTooltip = () => {
        if (!tooltip) return;
        tooltip.classList.remove("is-visible");
        window.setTimeout(() => {
            if (!tooltip.classList.contains("is-visible")) tooltip.hidden = true;
        }, 100);
    };

    document.querySelectorAll("[data-tooltip]").forEach((trigger) => {
        trigger.addEventListener("pointerenter", () => showTooltip(trigger));
        trigger.addEventListener("pointerleave", hideTooltip);
        trigger.addEventListener("focus", () => showTooltip(trigger));
        trigger.addEventListener("blur", hideTooltip);
        trigger.addEventListener("click", hideTooltip);
    });

    document.addEventListener("keydown", (event) => {
        if (event.key === "Escape" && materialEditModal && !materialEditModal.hidden && confirmation?.hidden) {
            closeModal(materialEditModal);
        } else if (event.key === "Escape" && materialFilesModal && !materialFilesModal.hidden && confirmation?.hidden) {
            closeModal(materialFilesModal);
        }
    });

    const readMaterial = (trigger) => {
        const node = trigger.closest("[data-repository-item]")?.querySelector("[data-material-json]");
        return node ? JSON.parse(node.textContent || "{}") : null;
    };

    const openModal = (modal) => {
        if (!modal) return;
        modal.hidden = false;
        document.body.classList.add("modal-open");
        modal.querySelector("input:not([type=hidden]),select,textarea,button")?.focus();
    };

    const closeModal = (modal) => {
        if (!modal) return;
        modal.hidden = true;
        if (![materialEditModal, materialFilesModal, presentationModal].some((item) => item && !item.hidden)) {
            document.body.classList.remove("modal-open");
        }
    };

    document.querySelectorAll("[data-close-material-edit]").forEach((button) => {
        button.addEventListener("click", () => closeModal(materialEditModal));
    });
    document.querySelectorAll("[data-close-material-files]").forEach((button) => {
        button.addEventListener("click", () => closeModal(materialFilesModal));
    });
    [materialEditModal, materialFilesModal].forEach((modal) => {
        modal?.addEventListener("click", (event) => {
            if (event.target === modal) closeModal(modal);
        });
    });

    let presentationMaterial = null;
    const previewExtensions = new Set(["pdf", "docx", "txt", "png", "jpg", "jpeg", "webp"]);
    const presentationIcon = (extension) => {
        if (extension === "pdf") return "fa-file-pdf";
        if (["doc", "docx"].includes(extension)) return "fa-file-word";
        if (["png", "jpg", "jpeg", "webp"].includes(extension)) return "fa-file-image";
        if (extension === "txt") return "fa-file-lines";
        return "fa-file";
    };
    const publishPresentationChoice = async (fileId = "") => {
        if (!presentationMaterial) return;
        const material = presentationMaterial;
        const submit = presentationForm?.querySelector("[type=submit]");
        if (submit) submit.disabled = true;
        const data = new FormData();
        data.set("_csrf", config.dataset.csrf);
        data.set("id", material.id);
        data.set("status", "published");
        if (fileId) data.set("presentation_file_id", fileId);
        try {
            const response = await fetch(config.dataset.materialStatus, { method: "POST", body: data });
            const result = await response.json();
            if (!response.ok || !result.success) throw new Error(result.message);
            sessionStorage.setItem("repositoryToast", result.message || "Material publicado correctamente.");
            window.location.reload();
        } catch (error) {
            if (presentationError) {
                presentationError.textContent = error.message || "No fue posible publicar el material.";
                presentationError.hidden = false;
            }
            showToast(error.message || "No fue posible publicar el material.", "error");
            if (submit) submit.disabled = false;
        }
    };
    const closePresentationModal = () => publishPresentationChoice("");
    document.querySelectorAll("[data-close-presentation]").forEach((button) => {
        button.addEventListener("click", closePresentationModal);
    });
    presentationModal?.addEventListener("click", (event) => {
        if (event.target === presentationModal) closePresentationModal();
    });

    const openPresentationModal = (material) => {
        const eligible = (material.files || []).filter((file) =>
            previewExtensions.has(String(file.extension || "").toLowerCase())
        );
        presentationMaterial = material;
        presentationOptions?.replaceChildren();
        if (presentationError) {
            presentationError.hidden = true;
            presentationError.textContent = "";
        }
        eligible.forEach((file) => {
            const label = document.createElement("label");
            const input = document.createElement("input");
            const icon = document.createElement("i");
            const copy = document.createElement("span");
            const name = document.createElement("strong");
            const meta = document.createElement("small");
            label.className = "ar-presentation-option";
            input.type = "radio";
            input.name = "presentation_file_id";
            input.value = file.id;
            input.checked = eligible.length === 1 || Number(material.presentation_file_id) === Number(file.id);
            icon.className = `fa-regular ${presentationIcon(String(file.extension || "").toLowerCase())}`;
            name.textContent = file.name;
            meta.textContent = `${file.format} · ${file.size}`;
            copy.append(name, meta);
            label.append(input, icon, copy);
            presentationOptions?.append(label);
        });
        const submit = presentationForm?.querySelector("[type=submit]");
        if (submit) submit.disabled = false;
        presentationModal.querySelector("h2").textContent = "Seleccionar archivo de presentación (opcional)";
        presentationModal.querySelector("header p").textContent = "Puedes elegir el archivo que se mostrará automáticamente cuando una persona ingrese al Expediente Digital. Esta elección es opcional y no afecta la importancia de los demás documentos.";
        presentationModal.querySelector("footer [data-close-presentation]").textContent = "Omitir";
        if (submit) submit.textContent = "Continuar";
        openModal(presentationModal);
    };
    presentationOptions?.addEventListener("change", () => {
        const submit = presentationForm?.querySelector("[type=submit]");
        if (submit) submit.disabled = false;
    });
    document.querySelectorAll("[data-publish-material]").forEach((button) => {
        button.addEventListener("click", () => {
            const material = readMaterial(button);
            if (material) openPresentationModal(material);
        });
    });
    presentationForm?.addEventListener("submit", async (event) => {
        event.preventDefault();
        const selected = presentationForm.querySelector("input[name=presentation_file_id]:checked");
        const submit = presentationForm.querySelector("[type=submit]");
        if (!presentationMaterial || submit.disabled) return;
        publishPresentationChoice(selected?.value || "");
    });

    document.querySelectorAll("[data-edit-material]").forEach((button) => {
        button.addEventListener("click", () => {
            const material = readMaterial(button);
            if (!material || !materialEditForm) return;
            Object.entries(material).forEach(([name, value]) => {
                const field = materialEditForm.elements.namedItem(name);
                if (field && !Array.isArray(value)) field.value = String(value ?? "");
            });
            const publicationDisplay = materialEditForm.querySelector("[data-material-publication-date]");
            if (publicationDisplay) publicationDisplay.textContent = material.publication_date || "Sin publicar";
            openModal(materialEditModal);
        });
    });

    materialEditForm?.addEventListener("submit", async (event) => {
        event.preventDefault();
        const confirmed = await requestConfirmation(
            "¿Estás seguro de guardar los cambios realizados en este material de apoyo?",
            { title: "Guardar cambios", acceptLabel: "Guardar cambios", danger: false }
        );
        if (!confirmed) return;
        const submit = materialEditForm.querySelector("[type=submit]");
        submit.disabled = true;
        try {
            const response = await fetch(config.dataset.materialSave, {
                method: "POST",
                body: new FormData(materialEditForm),
            });
            const result = await response.json();
            if (!response.ok || !result.success) throw new Error(result.message);
            sessionStorage.setItem("repositoryToast", result.message);
            window.location.reload();
        } catch (error) {
            showToast(error.message || "No fue posible guardar el material.", "error");
            submit.disabled = false;
        }
    });

    const renderMaterialFiles = (material) => {
        materialFilesList.replaceChildren();
        document.querySelector("#arMaterialFilesSubtitle").textContent = material.title;
        materialFileForm.elements.material_id.value = material.id;
        if (!material.files.length) {
            const empty = document.createElement("div");
            empty.className = "empty";
            empty.textContent = "Este material todavía no tiene archivos.";
            materialFilesList.append(empty);
            return;
        }
        material.files.forEach((file) => {
            const row = document.createElement("article");
            const copy = document.createElement("div");
            const name = document.createElement("strong");
            const meta = document.createElement("small");
            const type = document.createElement("span");
            const remove = document.createElement("button");
            name.textContent = file.name;
            meta.textContent = `${file.format} · ${file.size}`;
            type.textContent = file.presentation ? "Presentación" : "Archivo";
            remove.type = "button";
            remove.innerHTML = '<i class="fa-regular fa-trash-can"></i>';
            remove.setAttribute("aria-label", `Retirar ${file.name}`);
            remove.addEventListener("click", async () => {
                const confirmed = await requestConfirmation(
                    `¿Estás seguro de retirar el archivo “${file.name}”?`,
                    { title: "Retirar archivo", acceptLabel: "Retirar archivo", danger: true }
                );
                if (!confirmed) return;
                const data = new FormData();
                data.set("_csrf", config.dataset.csrf);
                data.set("material_id", material.id);
                data.set("file_id", file.id);
                data.set("action", "remove");
                try {
                    const response = await fetch(config.dataset.materialFile, { method: "POST", body: data });
                    const result = await response.json();
                    if (!response.ok || !result.success) throw new Error(result.message);
                    sessionStorage.setItem("repositoryToast", result.message);
                    window.location.reload();
                } catch (error) {
                    showToast(error.message || "No fue posible retirar el archivo.", "error");
                }
            });
            copy.append(name, meta);
            row.append(copy, type, remove);
            materialFilesList.append(row);
        });
    };

    document.querySelectorAll("[data-manage-material-files]").forEach((button) => {
        button.addEventListener("click", () => {
            const material = readMaterial(button);
            if (!material) return;
            renderMaterialFiles(material);
            openModal(materialFilesModal);
        });
    });

    materialFileForm?.addEventListener("submit", async (event) => {
        event.preventDefault();
        const submit = materialFileForm.querySelector("[type=submit]");
        submit.disabled = true;
        try {
            const response = await fetch(config.dataset.materialFile, {
                method: "POST",
                body: new FormData(materialFileForm),
            });
            const result = await response.json();
            if (!response.ok || !result.success) throw new Error(result.message);
            sessionStorage.setItem("repositoryToast", result.message);
            window.location.reload();
        } catch (error) {
            showToast(error.message || "No fue posible agregar el archivo.", "error");
            submit.disabled = false;
        }
    });

    document.querySelectorAll("[data-withdraw-material]").forEach((button) => {
        button.addEventListener("click", () => {
            const material = readMaterial(button);
            if (!material) return;
            window.SupportMaterialAdminActions.open({
                trigger: button, type: "withdraw", action: "publication",
                endpoint: config.dataset.materialStatus, csrf: config.dataset.csrf, material,
                onSuccess: result => {
                    sessionStorage.setItem("repositoryToast", result.message);
                    window.location.reload();
                },
            });
        });
    });

    document.querySelectorAll("[data-material-availability]").forEach((button) => {
        button.addEventListener("click", () => {
            const material = readMaterial(button);
            if (!material) return;
            const available = button.dataset.available === "1";
            window.SupportMaterialAdminActions.open({
                trigger: button,
                type: available ? "availability_off" : "availability_on",
                action: "availability",
                endpoint: config.dataset.materialStatus,
                csrf: config.dataset.csrf,
                material: { ...material, is_available: available },
                onSuccess: result => {
                    sessionStorage.setItem("repositoryToast", result.message);
                    window.location.reload();
                },
            });
        });
    });

    document.querySelectorAll("[data-project-availability]").forEach((button) => {
        button.addEventListener("click", async () => {
            const available = button.dataset.available === "1";
            const message = available
                ? "El proyecto permanecerá publicado, pero no estará disponible temporalmente para consulta."
                : "El proyecto volverá a estar disponible para consulta.";
            if (!await requestConfirmation(message, {
                title: available ? "Marcar como no disponible" : "Marcar como disponible",
                acceptLabel: available ? "Marcar como no disponible" : "Marcar como disponible",
                danger: false,
            })) return;
            button.disabled = true;
            const data = new FormData();
            data.set("_csrf", config.dataset.csrf);
            data.set("id", button.dataset.id);
            data.set("action", "availability");
            data.set("is_available", available ? "0" : "1");
            try {
                const response = await fetch(config.dataset.endpoint, { method: "POST", body: data });
                const result = await response.json();
                if (!response.ok || !result.success) throw new Error(result.message);
                sessionStorage.setItem("repositoryToast", result.message);
                window.location.reload();
            } catch (error) {
                showToast(error.message || "No fue posible actualizar la disponibilidad.", "error");
                button.disabled = false;
            }
        });
    });

    document.querySelectorAll("[data-restore-material]").forEach((button) => {
        button.addEventListener("click", async () => {
            const confirmed = await requestConfirmation(
                "¿Deseas restaurar este material de apoyo en el repositorio?",
                { title: "Restaurar material", acceptLabel: "Restaurar material", danger: false, variant: "restore-material" }
            );
            if (!confirmed) return;
            button.disabled = true;
            const data = new FormData();
            data.set("_csrf", config.dataset.csrf);
            data.set("id", button.dataset.id);
            data.set("status", "published");
            try {
                const response = await fetch(config.dataset.materialStatus, { method: "POST", body: data });
                const result = await response.json();
                if (!response.ok || !result.success) throw new Error(result.message);
                sessionStorage.setItem("repositoryToast", result.message);
                window.location.reload();
            } catch (error) {
                showToast(error.message || "No fue posible restaurar el material.", "error");
                button.disabled = false;
            }
        });
    });

    const searchableNodes = () => [...document.querySelectorAll(
        "[data-repository-item] h3,[data-repository-item] dd,[data-repository-item] .ar-code,[data-repository-item] .ar-project-type,[data-repository-item] .ar-card-copy p,[data-repository-item] header strong"
    )];

    const restoreHighlights = () => {
        searchableNodes().forEach((node) => {
            if (!originalText.has(node)) originalText.set(node, node.textContent);
            node.textContent = originalText.get(node);
        });
    };

    const highlightNode = (node, terms) => {
        const value = node.textContent;
        const characters = [...value];
        let folded = "";
        const positions = [];
        characters.forEach((character, index) => {
            const normalizedCharacter = normalize(character);
            folded += normalizedCharacter;
            [...normalizedCharacter].forEach(() => positions.push(index));
        });
        const ranges = [];
        terms.forEach((term) => {
            let from = 0;
            while (term && (from = folded.indexOf(term, from)) !== -1) {
                ranges.push([positions[from], positions[from + term.length - 1] + 1]);
                from += term.length;
            }
        });
        if (!ranges.length) return;
        ranges.sort((first, second) => first[0] - second[0]);
        const merged = ranges.reduce((result, range) => {
            const previous = result.at(-1);
            if (previous && range[0] <= previous[1]) previous[1] = Math.max(previous[1], range[1]);
            else result.push(range);
            return result;
        }, []);
        const fragment = document.createDocumentFragment();
        let cursor = 0;
        merged.forEach(([start, end]) => {
            if (start > cursor) fragment.append(document.createTextNode(characters.slice(cursor, start).join("")));
            const mark = document.createElement("mark");
            mark.className = "ar-search-highlight";
            mark.textContent = characters.slice(start, end).join("");
            fragment.append(mark);
            cursor = end;
        });
        if (cursor < characters.length) fragment.append(document.createTextNode(characters.slice(cursor).join("")));
        node.replaceChildren(fragment);
    };

    const pageTokens = (pageNumber, totalPages) => {
        if (totalPages <= 5) return Array.from({ length: totalPages }, (_, index) => index + 1);
        if (pageNumber <= 3) return [1, 2, 3, "ellipsis", totalPages];
        if (pageNumber >= totalPages - 2) return [1, "ellipsis", totalPages - 2, totalPages - 1, totalPages];
        return [1, "ellipsis-start", pageNumber - 1, pageNumber, pageNumber + 1, "ellipsis-end", totalPages];
    };

    const renderPagination = (total, size, state, controls) => {
        const totalPages = Math.max(1, Math.ceil(total / size));
        state.page = Math.min(state.page, totalPages);
        const from = total === 0 ? 0 : ((state.page - 1) * size) + 1;
        const to = Math.min(state.page * size, total);

        if (controls?.summary) {
            controls.summary.textContent = total === 0
                ? "Mostrando 0 de 0"
                : `Mostrando ${from}-${to} de ${total}`;
        }
        if (!controls?.pagination || !controls.pages) return;
        controls.pagination.hidden = total <= 10;
        controls.pages.replaceChildren();
        if (total <= 10) return;

        const createButton = (label, target, options = {}) => {
            const button = document.createElement("button");
            button.type = "button";
            button.innerHTML = options.icon ? `<i class="${options.icon}"></i>` : String(label);
            button.disabled = Boolean(options.disabled);
            button.classList.toggle("active", Boolean(options.active));
            button.setAttribute("aria-label", options.ariaLabel || `Página ${label}`);
            if (options.active) button.setAttribute("aria-current", "page");
            button.addEventListener("click", () => {
                state.page = target;
                updateResults(false);
            });
            return button;
        };

        controls.pages.append(createButton("Anterior", Math.max(1, state.page - 1), {
            icon: "fa-solid fa-chevron-left",
            disabled: state.page === 1,
            ariaLabel: "Página anterior",
        }));
        pageTokens(state.page, totalPages).forEach((token) => {
            if (typeof token === "string") {
                const ellipsis = document.createElement("span");
                ellipsis.textContent = "…";
                controls.pages.append(ellipsis);
                return;
            }
            controls.pages.append(createButton(token, token, { active: token === state.page }));
        });
        controls.pages.append(createButton("Siguiente", Math.min(totalPages, state.page + 1), {
            icon: "fa-solid fa-chevron-right",
            disabled: state.page === totalPages,
            ariaLabel: "Página siguiente",
        }));
    };

    function updateResults(resetPage = false) {
        const state = paginationState[activeTab];
        const controls = paginationControls[activeTab];
        if (resetPage) state.page = 1;
        restoreHighlights();
        const query = normalize(search?.value);
        const terms = query.split(/\s+/).filter(Boolean);
        const type = normalize(typeFilter?.value);
        const period = normalize(periodFilter?.value);
        const category = normalize(categoryFilter?.value);
        const materialStatus = materialStatusFilter?.value || "all";
        const size = Number(controls?.size?.value || state.size || 10);
        state.size = size;
        const items = [...document.querySelectorAll(`[data-repository-item="${activeTab}"]`)];

        const matches = items.filter((item) => {
            const searchable = normalize(item.dataset.search);
            const matchesSearch = !terms.length || terms.every((term) => searchable.includes(term));
            const matchesType = activeTab !== "projects" || !type || normalize(item.dataset.type) === type;
            const matchesPeriod = activeTab !== "projects" || !period || normalize(item.dataset.period) === period;
            const matchesCategory = activeTab !== "materials" || !category || normalize(item.dataset.category) === category;
            const matchesMaterialStatus = activeTab !== "materials"
                || materialStatus === "all"
                || item.dataset.materialState === materialStatus;
            return matchesSearch && matchesType && matchesPeriod && matchesCategory && matchesMaterialStatus;
        });

        const totalPages = Math.max(1, Math.ceil(matches.length / size));
        state.page = Math.min(state.page, totalPages);
        const start = (state.page - 1) * size;
        const visibleItems = new Set(matches.slice(start, start + size));
        items.forEach((item) => {
            item.hidden = !visibleItems.has(item);
            if (visibleItems.has(item) && terms.length) {
                item.querySelectorAll("h3,dd,.ar-code,.ar-project-type,.ar-card-copy p,header strong")
                    .forEach((node) => highlightNode(node, terms));
            }
        });

        const count = document.querySelector(activeTab === "projects" ? "#arProjectCount" : "#arMaterialCount");
        const materialCountText = document.querySelector("#arMaterialCountText");
        const empty = document.querySelector(activeTab === "projects" ? "#arProjectsEmpty" : "#arMaterialsEmpty");
        const hasCriteria = Boolean(
            query || type || category || (activeTab === "materials" && materialStatus !== "all")
            || (period && !periodFilter?.matches("[data-fixed-filter]"))
        );
        if (count) count.textContent = String(matches.length);
        if (activeTab === "materials" && materialCountText) {
            materialCountText.textContent = `${matches.length} ${matches.length === 1 ? "resultado visible" : "resultados visibles"}`;
        }
        if (empty) {
            empty.hidden = matches.length > 0;
            const title = empty.querySelector("h2");
            const description = empty.querySelector("p");
            if (title) {
                title.textContent = activeTab === "materials" && query
                    ? "No se encontraron materiales que coincidan con la búsqueda."
                    : activeTab === "materials" && materialStatus === "withdrawn"
                        ? "No existen publicaciones retiradas con los filtros seleccionados."
                    : activeTab === "materials" && category
                        ? "No se encontraron materiales en esta categoría."
                    : hasCriteria
                    ? activeTab === "materials"
                        ? "No se encontraron materiales con los filtros seleccionados."
                        : "No se encontraron resultados con los criterios seleccionados."
                    : activeTab === "projects"
                        ? "No hay proyectos publicados disponibles."
                        : "No hay materiales de apoyo disponibles.";
            }
            if (description) {
                description.textContent = hasCriteria
                    ? activeTab === "materials"
                        ? "Prueba con otros términos o ajusta el estado y la categoría."
                        : "Prueba con otros términos o restablece los filtros."
                    : activeTab === "projects"
                        ? "Los proyectos aparecerán después de completar su publicación oficial."
                        : "Los recursos institucionales publicados aparecerán en esta sección.";
            }
        }

        if (clearSearch) clearSearch.hidden = !search?.value;
        if (clearMaterialFilters) {
            const hasActiveMaterialFilters = activeTab === "materials"
                && Boolean(query || category || materialStatus !== "all");
            clearMaterialFilters.hidden = !hasActiveMaterialFilters;
        }
        renderPagination(matches.length, size, state, controls);
    }

    const selectTab = (tab) => {
        activeTab = tab;
        document.querySelectorAll("[data-repository-tab]").forEach((button) => {
            const selected = button.dataset.repositoryTab === tab;
            button.classList.toggle("active", selected);
            button.setAttribute("aria-selected", String(selected));
        });
        document.querySelectorAll("[data-repository-panel]").forEach((panel) => {
            panel.hidden = panel.dataset.repositoryPanel !== tab;
        });
        document.querySelectorAll("[data-filter-for]").forEach((filter) => {
            filter.hidden = filter.dataset.filterFor !== tab;
        });
        updateResults(false);
    };

    document.querySelectorAll("[data-repository-tab]").forEach((button) => {
        button.addEventListener("click", () => selectTab(button.dataset.repositoryTab));
    });
    search?.addEventListener("input", () => updateResults(true));
    [typeFilter, periodFilter, categoryFilter].forEach((control) => {
        control?.addEventListener("change", () => updateResults(true));
    });
    materialStatusFilter?.addEventListener("change", () => {
        const url = new URL(window.location.href);
        url.searchParams.set("status", materialStatusFilter.value);
        window.history.replaceState({}, "", url);
        updateResults(true);
    });
    Object.entries(paginationControls).forEach(([tab, controls]) => {
        controls.size?.addEventListener("change", () => {
            paginationState[tab].size = Number(controls.size.value || 10);
            paginationState[tab].page = 1;
            if (activeTab === tab) updateResults(false);
        });
    });

    clearSearch?.addEventListener("click", () => {
        search.value = "";
        search.focus();
        updateResults(true);
    });
    clearMaterialFilters?.addEventListener("click", () => {
        if (search) search.value = "";
        if (categoryFilter) {
            categoryFilter.value = "";
            categoryFilter.dispatchEvent(new Event("change", { bubbles: true }));
        }
        if (materialStatusFilter) {
            materialStatusFilter.value = "all";
            materialStatusFilter.dispatchEvent(new Event("change", { bubbles: true }));
        }
        updateResults(true);
        search?.focus();
    });
    document.querySelectorAll("[data-publish]").forEach((button) => {
        button.addEventListener("click", async () => {
            const publish = button.dataset.publish === "publish";
            const message = publish
                ? "¿Publicar este proyecto en el repositorio institucional?"
                : "¿Estás seguro de retirar este proyecto del repositorio institucional? Permanecerá disponible en Proyectos, pero dejará de estar visible para estudiantes y docentes.";
            if (!await requestConfirmation(message, {
                title: "Retirar del repositorio",
                acceptLabel: "Retirar proyecto",
                danger: true,
            })) return;

            button.disabled = true;
            const data = new FormData();
            data.set("_csrf", config.dataset.csrf);
            data.set("id", button.dataset.id);
            data.set("action", publish ? "publish" : "unpublish");
            try {
                const response = await fetch(config.dataset.endpoint, { method: "POST", body: data });
                const result = await response.json();
                if (!response.ok || !result.success) throw new Error(result.message);
                sessionStorage.setItem("repositoryToast", result.message || "El repositorio se actualizó correctamente.");
                window.location.reload();
            } catch (error) {
                showToast(error.message || "No fue posible actualizar la publicación.", "error");
                button.disabled = false;
            }
        });
    });

    const storedToast = sessionStorage.getItem("repositoryToast");
    if (storedToast) {
        sessionStorage.removeItem("repositoryToast");
        showToast(storedToast);
    }
    const requestedParams = new URLSearchParams(location.search);
    const requestedTab = requestedParams.get("tab");
    const requestedMaterialStatus = requestedParams.get("status");
    const requestedPeriod = requestedParams.get("period");
    const allowedMaterialStatuses = new Set(["all", "available", "unavailable", "withdrawn"]);
    if (materialStatusFilter) {
        materialStatusFilter.value = allowedMaterialStatuses.has(requestedMaterialStatus)
            ? requestedMaterialStatus
            : "all";
    }
    if (periodFilter && requestedPeriod) {
        if (periodFilter instanceof HTMLSelectElement) {
            let requestedOption = [...periodFilter.options].find(
                (option) => normalize(option.value) === normalize(requestedPeriod)
            );
            if (!requestedOption) {
                requestedOption = new Option(requestedPeriod, requestedPeriod);
                periodFilter.add(requestedOption);
            }
            periodFilter.value = requestedOption.value;
        } else {
            periodFilter.value = requestedPeriod;
        }
    }
    selectTab(requestedTab === "materials" ? "materials" : "projects");
    const requestedMaterialEdit = requestedParams.get("edit_material");
    if (requestedMaterialEdit) {
        const requestedMaterialCard = [...document.querySelectorAll('[data-repository-item="materials"]')].find((card) => {
            const data = card.querySelector("[data-material-json]");
            if (!data) return false;
            try { return String(JSON.parse(data.textContent || "{}").id) === requestedMaterialEdit; }
            catch { return false; }
        });
        const data = requestedMaterialCard?.querySelector("[data-material-json]");
        const material = data ? JSON.parse(data.textContent || "{}") : null;
        if (material && materialEditForm) {
            Object.entries(material).forEach(([name, value]) => {
                const field = materialEditForm.elements.namedItem(name);
                if (field && !Array.isArray(value)) field.value = String(value ?? "");
            });
            const publicationDisplay = materialEditForm.querySelector("[data-material-publication-date]");
            if (publicationDisplay) publicationDisplay.textContent = material.publication_date || "Sin publicar";
            openModal(materialEditModal);
        }
    }
})();
