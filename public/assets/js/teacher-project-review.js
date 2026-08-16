document.addEventListener('DOMContentLoaded', () => {
    const workspace = document.querySelector('[data-student-workspace]');
    const manager = workspace?.querySelector('[data-sw-document-manager][data-sw-review-mode="draft"]');
    const configNode = workspace?.querySelector('[data-sw-review-config-json]');
    if (!workspace || !manager || !configNode) return;

    let config;
    let existingObservations = [];
    try {
        config = JSON.parse(configNode.textContent || '{}');
        existingObservations = JSON.parse(workspace.querySelector('[data-sw-observations-json]')?.textContent || '[]');
    } catch (error) {
        console.error('No fue posible iniciar la revision docente.', error);
        return;
    }

    const allowedStatuses = new Set(['approved', 'corrections_requested']);
    const limits = {
        bodyMin: Number(config.limits?.body_min || 5),
        bodyMax: Number(config.limits?.body_max || 2000),
        categoryMax: Number(config.limits?.category_max || 60),
        locationMax: Number(config.limits?.location_max || 180),
    };
    const categories = Array.isArray(config.categories) && config.categories.length
        ? config.categories.map(String).filter((item) => item.length > 0 && item.length <= limits.categoryMax)
        : ['General', 'Contenido', 'Formato', 'Redaccion', 'Referencias'];
    const files = (Array.isArray(config.files) ? config.files : []).filter((file) =>
        Number(file.file_id) > 0 && /^[a-f0-9]{64}$/.test(String(file.expected_checksum || ''))
    ).map((file) => ({
        file_id: Number(file.file_id),
        expected_checksum: String(file.expected_checksum),
        status: String(file.status || 'development'),
        name: String(file.name || 'Documento'),
    }));
    const filesById = new Map(files.map((file) => [file.file_id, file]));
    const buttonsById = new Map([...manager.querySelectorAll('[data-sw-file]')].map((button) => [Number(button.dataset.fileId || 0), button]));
    const reviewIsAvailable = String(config.expected_project_status || '') === 'under_review';
    const reviewEndpoint = String(config.endpoint || '').trim();
    const reviewCsrf = String(config.csrf || '').trim();
    const reviewContext = String(config.context || 'academic').trim() || 'academic';
    const projectId = Number(config.project_id || 0);
    const hasRemoteConfig = reviewEndpoint !== '' && reviewCsrf !== '' && projectId > 0 && reviewContext === 'academic';

    const observationPanel = manager.querySelector('[data-sw-file-observations]');
    const previewStage = manager.querySelector('[data-sw-preview-stage]');
    const explorerPanel = manager.querySelector('[data-sw-explorer]');
    const mobileBadge = manager.querySelector('[data-sw-mobile-obs-badge]');
    const confirmModal = manager.querySelector('[data-sw-review-confirm-modal]');
    const confirmHeading = confirmModal?.querySelector('[data-sw-review-confirm-heading]');
    const confirmMessage = confirmModal?.querySelector('[data-sw-review-confirm-message]');
    const confirmApprovedCount = confirmModal?.querySelector('[data-sw-review-approved-count]');
    const confirmCorrectionsCount = confirmModal?.querySelector('[data-sw-review-corrections-count]');
    const confirmStatus = confirmModal?.querySelector('[data-sw-review-confirm-status]');
    const confirmError = confirmModal?.querySelector('[data-sw-review-confirm-error]');
    const confirmSubmitButton = confirmModal?.querySelector('[data-sw-review-confirm-submit]');

    const reviewDraft = Object.create(null);
    const observationMeta = new Map();
    const completedFileIds = new Set();

    let activeFileId = 0;
    let editorState = null;
    let selectionState = null;
    let selectionPopover = null;
    let reviewError = '';
    let isSubmitting = false;
    let confirmModalOpen = false;
    let confirmModalError = '';
    let confirmModalStatus = '';

    const announce = (message, kind = 'info') => {
        if (typeof window.showToast === 'function') window.showToast(message, kind);
    };
    const setFlashToast = (message, kind = 'success') => {
        try {
            sessionStorage.setItem('studentWorkspace.flashToast', JSON.stringify({ message, kind }));
        } catch (error) {
            /* ignore storage errors */
        }
    };

    const isReviewableFile = (file) => file.status !== 'approved';
    const hasDraftChanges = () => Object.keys(reviewDraft).length > 0;
    const completedDrafts = () => files.filter((file) => completedFileIds.has(file.file_id));
    const reviewableFiles = () => files.filter((file) => isReviewableFile(file));
    const observationsForFile = (fileId) => existingObservations.filter((item) => Number(item.file_id || 0) === fileId);
    const decisionStatusLabel = (status) => status === 'approved' ? 'Aprobado' : (status === 'corrections_requested' ? 'Requiere correcciones' : 'Sin decision');
    const decisionIconClass = (status) => status === 'approved' ? 'fa-circle-check' : (status === 'corrections_requested' ? 'fa-triangle-exclamation' : 'fa-circle');

    const beforeUnloadHandler = (event) => {
        if (!hasDraftChanges() || isSubmitting) return;
        event.preventDefault();
        event.returnValue = '';
    };

    const draftFor = (fileId, initialStatus = '') => {
        if (!reviewDraft[fileId] && allowedStatuses.has(initialStatus)) {
            const file = filesById.get(fileId);
            if (!file || file.status === 'approved') return null;
            reviewDraft[fileId] = {
                file_id: file.file_id,
                expected_checksum: file.expected_checksum,
                status: initialStatus,
                observations: [],
            };
            observationMeta.set(fileId, []);
        }
        return reviewDraft[fileId] || null;
    };

    const metadataFor = (fileId) => {
        if (!observationMeta.has(fileId)) observationMeta.set(fileId, []);
        return observationMeta.get(fileId);
    };

    const pendingFiles = () => files.filter((file) => file.status !== 'approved' && !completedFileIds.has(file.file_id));
    const nextPendingAfter = (fileId) => {
        const start = files.findIndex((file) => file.file_id === fileId);
        if (start < 0) return pendingFiles()[0] || null;
        for (let offset = 1; offset <= files.length; offset++) {
            const candidate = files[(start + offset) % files.length];
            if (candidate.status !== 'approved' && !completedFileIds.has(candidate.file_id)) return candidate;
        }
        return null;
    };

    const summary = () => {
        const persistedApproved = files.filter((file) => file.status === 'approved').length;
        const completed = completedDrafts();
        const approved = persistedApproved + completed.filter((file) => reviewDraft[file.file_id]?.status === 'approved').length;
        const corrections = completed.filter((file) => reviewDraft[file.file_id]?.status === 'corrections_requested').length;
        return {
            total: files.length,
            reviewed: persistedApproved + completed.length,
            approved,
            corrections,
            pending: Math.max(0, files.length - persistedApproved - completed.length),
            newObservations: completed.reduce((count, file) => count + (reviewDraft[file.file_id]?.observations.length || 0), 0),
        };
    };

    const decisionSummary = () => {
        const decisions = reviewableFiles()
            .filter((file) => completedFileIds.has(file.file_id))
            .map((file) => reviewDraft[file.file_id])
            .filter(Boolean);
        return {
            total: decisions.length,
            approved: decisions.filter((item) => item.status === 'approved').length,
            corrections: decisions.filter((item) => item.status === 'corrections_requested').length,
        };
    };

    const removeSelectionPopover = (clearSelection = false) => {
        selectionPopover?.remove();
        selectionPopover = null;
        selectionState = null;
        if (clearSelection) {
            const selection = window.getSelection();
            if (selection && !selection.isCollapsed) selection.removeAllRanges();
        }
    };

    const clearDraftState = () => {
        Object.keys(reviewDraft).forEach((fileId) => delete reviewDraft[fileId]);
        observationMeta.clear();
        completedFileIds.clear();
        activeFileId = 0;
        editorState = null;
        selectionState = null;
        reviewError = '';
        confirmModalError = '';
        confirmModalStatus = '';
        confirmModalOpen = false;
        removeSelectionPopover(true);
        window.removeEventListener('beforeunload', beforeUnloadHandler);
    };

    const validateObservation = (item) => {
        const body = String(item?.body || '').trim();
        const category = String(item?.category || '').trim();
        const location = item?.location_reference == null ? '' : String(item.location_reference).trim();
        if (body.length < limits.bodyMin || body.length > limits.bodyMax) {
            return `Cada observacion debe contener entre ${limits.bodyMin} y ${limits.bodyMax} caracteres.`;
        }
        if (!category || category.length > limits.categoryMax || location.length > limits.locationMax) {
            return 'La categoria o referencia de la observacion no es valida.';
        }
        return '';
    };

    const validateReady = (fileId) => {
        const draft = draftFor(fileId);
        if (!draft || !allowedStatuses.has(draft.status)) return 'Selecciona una decision para este archivo.';
        if (draft.status === 'approved' && draft.observations.length) return 'Un documento aprobado no puede incluir observaciones pendientes.';
        if (draft.status === 'corrections_requested' && !draft.observations.length) return 'Agrega al menos una observacion para solicitar correcciones.';
        for (const item of draft.observations) {
            const error = validateObservation(item);
            if (error) return error;
        }
        return '';
    };

    const getConfirmState = () => {
        if (!reviewIsAvailable) {
            return { enabled: false, reason: 'La revision documental solo puede confirmarse cuando el proyecto esta en revision.' };
        }
        if (!hasRemoteConfig) {
            return { enabled: false, reason: 'La configuracion segura de confirmacion no esta disponible para este proyecto.' };
        }
        const reviewable = reviewableFiles();
        if (!reviewable.length) {
            return { enabled: false, reason: 'Todos los documentos vigentes ya fueron aprobados para este checksum.' };
        }
        if (!completedFileIds.size) {
            return { enabled: false, reason: 'Finaliza al menos un documento con "Listo" antes de confirmar.' };
        }
        for (const file of reviewable) {
            if (!completedFileIds.has(file.file_id)) {
                return { enabled: false, reason: 'Completa la revision temporal de todos los documentos pendientes antes de confirmar.' };
            }
            const error = validateReady(file.file_id);
            if (error) return { enabled: false, reason: error };
        }
        return { enabled: !isSubmitting, reason: isSubmitting ? 'La revision se esta guardando en este momento.' : '' };
    };

    const serializeDecisions = () => reviewableFiles()
        .filter((file) => completedFileIds.has(file.file_id))
        .map((file) => {
            const draft = reviewDraft[file.file_id];
            return {
                file_id: draft.file_id,
                expected_checksum: draft.expected_checksum,
                status: draft.status,
                observations: draft.observations.map((observation) => ({
                    body: observation.body,
                    category: observation.category,
                    location_reference: observation.location_reference,
                })),
            };
        });

    const updateFileIndicators = () => {
        files.forEach((file) => {
            const button = buttonsById.get(file.file_id);
            const actions = button?.closest('.sw-file-row')?.querySelector('.sw-file-row-actions');
            if (!button || !actions) return;
            let indicator = actions.querySelector('[data-sw-review-indicator]');
            if (!indicator) {
                indicator = document.createElement('span');
                indicator.dataset.swReviewIndicator = '';
                indicator.className = 'sw-review-file-indicator';
                indicator.setAttribute('role', 'img');
                actions.prepend(indicator);
            }
            const draft = draftFor(file.file_id);
            const complete = completedFileIds.has(file.file_id);
            let state = 'pending';
            let label = 'Pendiente de revision';
            let icon = 'fa-regular fa-circle';
            if (file.status === 'approved') {
                state = 'approved';
                label = 'Aprobado';
                icon = 'fa-solid fa-check';
            } else if (complete && draft?.status === 'approved') {
                state = 'approved';
                label = 'Revisado en esta sesion: aprobado';
                icon = 'fa-solid fa-check';
            } else if (complete && draft?.status === 'corrections_requested') {
                state = 'corrections';
                label = 'Revisado en esta sesion: requiere correcciones';
                icon = 'fa-solid fa-exclamation';
            } else if (draft?.status) {
                state = 'draft';
                label = `Borrador: ${decisionStatusLabel(draft.status)}`;
                icon = 'fa-solid fa-pen';
            }
            indicator.className = `sw-review-file-indicator is-${state}`;
            indicator.title = label;
            indicator.setAttribute('aria-label', label);
            indicator.innerHTML = `<i class="${icon}" aria-hidden="true"></i>`;
            button.classList.toggle('is-review-complete', complete || file.status === 'approved');
            button.disabled = isSubmitting;
        });
    };

    const createProgress = () => {
        const value = summary();
        const section = document.createElement('section');
        section.className = 'sw-review-progress';
        section.innerHTML = `
            <div class="sw-review-progress-heading"><strong>Revision</strong><span>${value.reviewed} de ${value.total} documentos</span></div>
            <div class="sw-review-progress-track" aria-hidden="true"><span style="width:${value.total ? Math.round((value.reviewed / value.total) * 100) : 0}%"></span></div>
            <div class="sw-review-progress-counts">
                <span class="is-approved"><i class="fa-solid fa-check" aria-hidden="true"></i>${value.approved} aprobados</span>
                <span class="is-corrections"><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>${value.corrections} con correcciones</span>
                <span class="is-pending"><i class="fa-regular fa-circle" aria-hidden="true"></i>${value.pending} pendientes</span>
            </div>`;
        return section;
    };

    const createExistingSection = (file) => {
        const items = observationsForFile(file.file_id);
        const section = document.createElement('section');
        section.className = 'sw-review-observation-group is-existing';
        const heading = document.createElement('div');
        heading.className = 'sw-review-group-heading';
        heading.innerHTML = `<strong>Observaciones existentes</strong><span>${items.length}</span>`;
        section.append(heading);
        if (!items.length) {
            const empty = document.createElement('p');
            empty.className = 'sw-review-empty-inline';
            empty.textContent = 'Este archivo no tiene observaciones registradas.';
            section.append(empty);
            return section;
        }
        const list = document.createElement('div');
        list.className = 'sw-review-card-list';
        items.forEach((item) => {
            const card = document.createElement('article');
            card.className = 'sw-review-observation-card is-existing';
            const meta = document.createElement('div');
            meta.className = 'sw-review-card-meta';
            const category = document.createElement('strong');
            category.textContent = String(item.category || 'General');
            const state = document.createElement('span');
            const previousVersion = item.file_checksum_sha256 && String(item.file_checksum_sha256) !== file.expected_checksum;
            state.textContent = previousVersion ? 'Version anterior' : String(item.status || 'Registrada');
            meta.append(category, state);
            const body = document.createElement('p');
            body.textContent = String(item.body || '');
            card.append(meta, body);
            if (item.location_reference) {
                const location = document.createElement('small');
                location.innerHTML = '<i class="fa-solid fa-location-dot" aria-hidden="true"></i>';
                location.append(document.createTextNode(` ${item.location_reference}`));
                card.append(location);
            }
            list.append(card);
        });
        section.append(list);
        return section;
    };

    const openEditor = (mode, fileId, options = {}) => {
        if (isSubmitting || !reviewIsAvailable || filesById.get(fileId)?.status === 'approved') return;
        reviewError = '';
        editorState = {
            mode,
            fileId,
            index: Number.isInteger(options.index) ? options.index : null,
            category: String(options.category || categories[0] || 'General'),
            body: String(options.body || ''),
            locationReference: String(options.locationReference || ''),
            selectedText: String(options.selectedText || ''),
        };
        removeSelectionPopover(false);
        renderReviewCenter();
        if (window.innerWidth <= 768) manager.querySelector('[data-sw-mobile-tab="observations"]')?.click();
        observationPanel?.querySelector('[data-sw-review-body]')?.focus();
    };

    const duplicateBodyExists = (body, editingFileId, editingIndex) => Object.values(reviewDraft).some((draft) =>
        draft.observations.some((item, index) => item.body === body && !(draft.file_id === editingFileId && index === editingIndex))
    );

    const saveEditor = () => {
        if (isSubmitting || !editorState) return;
        const form = observationPanel?.querySelector('[data-sw-review-form]');
        const body = String(form?.querySelector('[data-sw-review-body]')?.value || '').trim();
        const category = String(form?.querySelector('[data-sw-review-category]')?.value || '').trim();
        const locationReference = String(editorState.locationReference || '').trim();
        const validationError = validateObservation({
            body,
            category,
            location_reference: locationReference || null,
        });
        if (validationError) {
            reviewError = validationError;
            renderReviewCenter();
            return;
        }
        if (duplicateBodyExists(body, editorState.fileId, editorState.index)) {
            reviewError = 'No incluyas observaciones duplicadas en la misma revision.';
            renderReviewCenter();
            return;
        }
        const draft = draftFor(editorState.fileId, 'corrections_requested');
        if (!draft) return;
        const observation = { body, category, location_reference: locationReference || null };
        const meta = { selected_text: editorState.selectedText || '' };
        const metas = metadataFor(editorState.fileId);
        if (editorState.index === null) {
            draft.observations.push(observation);
            metas.push(meta);
        } else {
            draft.observations[editorState.index] = observation;
            metas[editorState.index] = meta;
        }
        draft.status = 'corrections_requested';
        completedFileIds.delete(editorState.fileId);
        editorState = null;
        reviewError = '';
        renderReviewCenter();
        announce('Observacion agregada al borrador. Todavia no se ha enviado la revision.', 'info');
    };

    const createEditor = () => {
        const state = editorState;
        const form = document.createElement('form');
        form.className = 'sw-review-form';
        form.dataset.swReviewForm = '';
        form.noValidate = true;
        const title = document.createElement('div');
        title.className = 'sw-review-form-heading';
        title.innerHTML = `<strong>${state.index === null ? 'Nueva observacion' : 'Editar observacion'}</strong><button type="button" data-sw-review-cancel aria-label="Cancelar"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>`;
        form.append(title);
        if (state.selectedText) {
            const context = document.createElement('div');
            context.className = 'sw-review-selection-context';
            const label = document.createElement('span');
            label.textContent = 'Texto seleccionado';
            const quote = document.createElement('blockquote');
            quote.textContent = `"${state.selectedText}"`;
            const reference = document.createElement('small');
            reference.textContent = state.locationReference;
            context.append(label, quote, reference);
            form.append(context);
        }
        const categoryLabel = document.createElement('label');
        categoryLabel.textContent = 'Categoria';
        const select = document.createElement('select');
        select.dataset.swReviewCategory = '';
        select.disabled = isSubmitting;
        categories.forEach((category) => {
            const option = document.createElement('option');
            option.value = category;
            option.textContent = category;
            option.selected = category === state.category;
            select.append(option);
        });
        categoryLabel.append(select);
        const bodyLabel = document.createElement('label');
        bodyLabel.textContent = 'Comentario';
        const textarea = document.createElement('textarea');
        textarea.dataset.swReviewBody = '';
        textarea.rows = 5;
        textarea.minLength = limits.bodyMin;
        textarea.maxLength = limits.bodyMax;
        textarea.placeholder = 'Describe con claridad el cambio solicitado.';
        textarea.value = state.body;
        textarea.disabled = isSubmitting;
        const counter = document.createElement('small');
        counter.className = 'sw-review-character-count';
        counter.textContent = `${textarea.value.length}/${limits.bodyMax}`;
        textarea.addEventListener('input', () => {
            counter.textContent = `${textarea.value.length}/${limits.bodyMax}`;
        });
        bodyLabel.append(textarea, counter);
        form.append(categoryLabel, bodyLabel);
        if (reviewError) {
            const error = document.createElement('p');
            error.className = 'sw-review-error';
            error.setAttribute('role', 'alert');
            error.textContent = reviewError;
            form.append(error);
        }
        const actions = document.createElement('div');
        actions.className = 'sw-review-form-actions';
        actions.innerHTML = '<button type="button" class="is-secondary" data-sw-review-cancel>Cancelar</button><button type="submit" class="is-primary">Agregar observacion</button>';
        actions.querySelectorAll('button').forEach((button) => {
            button.disabled = isSubmitting;
        });
        form.append(actions);
        form.querySelectorAll('[data-sw-review-cancel]').forEach((button) => button.addEventListener('click', () => {
            if (isSubmitting) return;
            editorState = null;
            reviewError = '';
            renderReviewCenter();
        }));
        form.addEventListener('submit', (event) => {
            event.preventDefault();
            saveEditor();
        });
        return form;
    };

    const createDraftSection = (file) => {
        const draft = draftFor(file.file_id);
        const items = draft?.observations || [];
        const metas = metadataFor(file.file_id);
        const section = document.createElement('section');
        section.className = 'sw-review-observation-group is-draft';
        const heading = document.createElement('div');
        heading.className = 'sw-review-group-heading';
        heading.innerHTML = `<strong>Observaciones de esta revision</strong><span>${items.length}</span>`;
        section.append(heading);
        if (!items.length) {
            const empty = document.createElement('p');
            empty.className = 'sw-review-empty-inline';
            empty.textContent = 'Todavia no agregas observaciones al borrador.';
            section.append(empty);
            return section;
        }
        const list = document.createElement('div');
        list.className = 'sw-review-card-list';
        items.forEach((item, index) => {
            const card = document.createElement('article');
            card.className = 'sw-review-observation-card is-draft';
            const meta = document.createElement('div');
            meta.className = 'sw-review-card-meta';
            const category = document.createElement('strong');
            category.textContent = item.category;
            const pending = document.createElement('span');
            pending.textContent = 'Pendiente de guardar';
            meta.append(category, pending);
            const body = document.createElement('p');
            body.textContent = item.body;
            card.append(meta, body);
            if (item.location_reference) {
                const location = document.createElement('small');
                location.textContent = item.location_reference;
                card.append(location);
            }
            if (metas[index]?.selected_text) {
                const fragment = document.createElement('blockquote');
                fragment.textContent = `"${metas[index].selected_text}"`;
                card.append(fragment);
            }
            const actions = document.createElement('div');
            actions.className = 'sw-review-card-actions';
            actions.innerHTML = '<button type="button" data-action="edit"><i class="fa-solid fa-pen" aria-hidden="true"></i> Editar</button><button type="button" data-action="delete"><i class="fa-regular fa-trash-can" aria-hidden="true"></i> Eliminar</button>';
            actions.querySelectorAll('button').forEach((button) => {
                button.disabled = isSubmitting;
            });
            actions.querySelector('[data-action="edit"]').addEventListener('click', () => openEditor('edit', file.file_id, {
                index,
                category: item.category,
                body: item.body,
                locationReference: item.location_reference || '',
                selectedText: metas[index]?.selected_text || '',
            }));
            actions.querySelector('[data-action="delete"]').addEventListener('click', () => {
                if (isSubmitting) return;
                draft.observations.splice(index, 1);
                metas.splice(index, 1);
                completedFileIds.delete(file.file_id);
                reviewError = '';
                renderReviewCenter();
            });
            card.append(actions);
            list.append(card);
        });
        section.append(list);
        return section;
    };

    const selectDecision = (fileId, status) => {
        if (isSubmitting || !reviewIsAvailable || !allowedStatuses.has(status)) return;
        const draft = draftFor(fileId, status);
        if (!draft) return;
        if (status === 'approved' && draft.observations.length) {
            reviewError = 'Elimina las observaciones temporales antes de aprobar este documento.';
            renderReviewCenter();
            return;
        }
        draft.status = status;
        completedFileIds.delete(fileId);
        reviewError = '';
        renderReviewCenter();
    };

    const keepVisibleInExplorer = (button) => {
        if (!explorerPanel || !button) return;
        const panelRect = explorerPanel.getBoundingClientRect();
        const buttonRect = button.getBoundingClientRect();
        const headerAllowance = 56;
        if (buttonRect.top < panelRect.top + headerAllowance) {
            explorerPanel.scrollTop -= panelRect.top + headerAllowance - buttonRect.top;
        } else if (buttonRect.bottom > panelRect.bottom - 8) {
            explorerPanel.scrollTop += buttonRect.bottom - panelRect.bottom + 8;
        }
    };

    const finishFile = (fileId) => {
        if (isSubmitting) return;
        const error = validateReady(fileId);
        if (error) {
            reviewError = error;
            renderReviewCenter();
            return;
        }
        completedFileIds.add(fileId);
        reviewError = '';
        editorState = null;
        updateFileIndicators();
        const next = nextPendingAfter(fileId);
        if (next) {
            const button = buttonsById.get(next.file_id);
            button?.click();
            keepVisibleInExplorer(button);
            announce('Revision del archivo preparada. Continuamos con el siguiente documento.', 'info');
            return;
        }
        renderReviewCenter();
        if (observationPanel) observationPanel.scrollTop = 0;
        manager.querySelector('[data-sw-mobile-tab="observations"]')?.click();
        announce('Revision preparada. Ya puedes confirmar el lote completo.', 'success');
    };

    const createReviewActions = (file) => {
        const draft = draftFor(file.file_id);
        const section = document.createElement('section');
        section.className = 'sw-review-actions';
        const title = document.createElement('div');
        title.className = 'sw-review-current-file';
        const selectedStatus = draft?.status || '';
        title.innerHTML = `<span>Decision del archivo</span><strong class="is-${selectedStatus || 'pending'}"><i class="fa-solid ${decisionIconClass(selectedStatus)}" aria-hidden="true"></i>${decisionStatusLabel(selectedStatus)}</strong>`;
        const decisions = document.createElement('div');
        decisions.className = 'sw-review-decision-grid';
        decisions.innerHTML = `
            <button type="button" data-decision="approved" aria-pressed="${selectedStatus === 'approved'}"><i class="fa-solid fa-check" aria-hidden="true"></i>Aprobar documento</button>
            <button type="button" data-decision="corrections_requested" aria-pressed="${selectedStatus === 'corrections_requested'}"><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>Requiere correcciones</button>`;
        decisions.querySelectorAll('[data-decision]').forEach((button) => {
            button.disabled = isSubmitting;
            button.addEventListener('click', () => selectDecision(file.file_id, button.dataset.decision));
        });
        const general = document.createElement('button');
        general.type = 'button';
        general.className = 'sw-review-add-general';
        general.disabled = isSubmitting;
        general.innerHTML = '<i class="fa-solid fa-plus" aria-hidden="true"></i> Observacion general';
        general.addEventListener('click', () => openEditor('general', file.file_id));
        section.append(title, decisions, general);
        return section;
    };

    const createReadySummary = () => {
        const value = summary();
        const confirmState = getConfirmState();
        const buttonText = isSubmitting ? 'Guardando revision...' : 'Confirmar revision';
        const helperText = confirmState.enabled
            ? 'La confirmacion enviara el lote completo al backend y aplicara las transiciones reales del proyecto.'
            : confirmState.reason;
        const section = document.createElement('section');
        section.className = 'sw-review-ready-summary';
        section.innerHTML = `
            <i class="fa-solid fa-clipboard-check" aria-hidden="true"></i>
            <div><strong>Revision preparada</strong><span>${value.reviewed} documentos revisados</span></div>
            <ul><li>${value.approved} aprobados</li><li>${value.corrections} requieren correcciones</li><li>${value.newObservations} observaciones nuevas</li></ul>
            <button type="button" ${confirmState.enabled ? '' : 'disabled'}>${buttonText}</button>
            <small>${helperText}</small>`;
        const button = section.querySelector('button');
        button.title = confirmState.enabled ? 'Confirmar revision documental' : helperText;
        button.addEventListener('click', () => {
            if (!confirmState.enabled || isSubmitting) return;
            openConfirmModal();
        });
        return section;
    };

    const renderReviewCenter = () => {
        if (!observationPanel) return;
        updateFileIndicators();
        observationPanel.replaceChildren(createProgress());
        const value = summary();
        if (value.total > 0 && value.pending === 0) observationPanel.append(createReadySummary());
        if (!reviewIsAvailable) {
            const unavailable = document.createElement('div');
            unavailable.className = 'sw-review-unavailable';
            unavailable.innerHTML = '<i class="fa-solid fa-lock" aria-hidden="true"></i><strong>Revision no disponible</strong><span>El proyecto debe encontrarse en revision para confirmar decisiones documentales.</span>';
            observationPanel.append(unavailable);
            return;
        }
        const file = filesById.get(activeFileId);
        if (!file) {
            const empty = document.createElement('div');
            empty.className = 'sw-review-unavailable';
            empty.innerHTML = '<i class="fa-regular fa-file-lines" aria-hidden="true"></i><strong>Selecciona un archivo</strong><span>Abre un documento para iniciar o continuar su revision.</span>';
            observationPanel.append(empty);
            return;
        }
        const fileHeading = document.createElement('div');
        fileHeading.className = 'sw-review-file-heading';
        const fileName = document.createElement('strong');
        fileName.textContent = file.name;
        const persistedState = document.createElement('span');
        persistedState.textContent = file.status === 'approved' ? 'Aprobado para esta version' : 'En revision';
        fileHeading.append(fileName, persistedState);
        observationPanel.append(fileHeading);
        if (file.status === 'approved') {
            const approved = document.createElement('div');
            approved.className = 'sw-review-persisted-approved';
            approved.innerHTML = '<i class="fa-solid fa-circle-check" aria-hidden="true"></i><div><strong>Documento aprobado</strong><span>Este checksum ya fue aprobado y no requiere una nueva decision.</span></div>';
            observationPanel.append(approved, createExistingSection(file));
            return;
        }
        observationPanel.append(createReviewActions(file), createExistingSection(file), createDraftSection(file));
        if (editorState?.fileId === file.file_id) observationPanel.append(createEditor());
        if (reviewError && !editorState) {
            const error = document.createElement('p');
            error.className = 'sw-review-error';
            error.setAttribute('role', 'alert');
            error.textContent = reviewError;
            observationPanel.append(error);
        }
        const footer = document.createElement('div');
        footer.className = 'sw-review-ready-action';
        const complete = completedFileIds.has(file.file_id);
        footer.innerHTML = `<span>${isSubmitting ? 'Guardando revision...' : (complete ? 'Revisado en esta sesion' : 'La decision sigue en borrador')}</span><button type="button"><i class="fa-solid fa-check" aria-hidden="true"></i> ${isSubmitting ? 'Guardando revision...' : 'Listo'}</button>`;
        const footerButton = footer.querySelector('button');
        footerButton.disabled = isSubmitting;
        footerButton.addEventListener('click', () => finishFile(file.file_id));
        observationPanel.append(footer);
        if (mobileBadge) {
            const total = observationsForFile(file.file_id).length + (draftFor(file.file_id)?.observations.length || 0);
            mobileBadge.textContent = String(total);
            mobileBadge.hidden = total === 0;
        }
    };

    const positionPopover = (rangeRect) => {
        if (!selectionPopover || !previewStage) return;
        const viewportPadding = 8;
        const stageRect = previewStage.getBoundingClientRect();
        const popoverRect = selectionPopover.getBoundingClientRect();
        const minLeft = Math.max(viewportPadding, stageRect.left + viewportPadding);
        const maxLeft = Math.max(minLeft, Math.min(window.innerWidth - popoverRect.width - viewportPadding, stageRect.right - popoverRect.width - viewportPadding));
        let left = rangeRect.left + (rangeRect.width - popoverRect.width) / 2;
        left = Math.min(maxLeft, Math.max(minLeft, left));
        let top = rangeRect.top - popoverRect.height - 8;
        const minTop = Math.max(viewportPadding, stageRect.top + viewportPadding);
        if (top < minTop) top = rangeRect.bottom + 8;
        top = Math.min(window.innerHeight - popoverRect.height - viewportPadding, Math.max(minTop, top));
        selectionPopover.style.left = `${Math.round(left)}px`;
        selectionPopover.style.top = `${Math.round(top)}px`;
    };

    const inspectTextSelection = () => {
        if (isSubmitting || !reviewIsAvailable || filesById.get(activeFileId)?.status === 'approved') {
            removeSelectionPopover(false);
            return;
        }
        const selection = window.getSelection();
        if (!selection || selection.isCollapsed || selection.rangeCount !== 1) {
            removeSelectionPopover(false);
            return;
        }
        const range = selection.getRangeAt(0);
        const startElement = range.startContainer.nodeType === Node.ELEMENT_NODE ? range.startContainer : range.startContainer.parentElement;
        const endElement = range.endContainer.nodeType === Node.ELEMENT_NODE ? range.endContainer : range.endContainer.parentElement;
        const startLayer = startElement?.closest?.('.sw-poc-text-layer');
        const endLayer = endElement?.closest?.('.sw-poc-text-layer');
        const page = startLayer?.closest('.sw-poc-page');
        if (!startLayer || startLayer !== endLayer || !page || !previewStage?.contains(page)) {
            removeSelectionPopover(false);
            return;
        }
        const text = selection.toString().replace(/\s+/g, ' ').trim();
        const rects = [...range.getClientRects()].filter((rect) => rect.width > 0 && rect.height > 0);
        if (!text || !rects.length) {
            removeSelectionPopover(false);
            return;
        }
        const anchorRect = rects[rects.length - 1];
        const pageNumber = Number(page.dataset.pocPage || 0);
        if (pageNumber < 1) {
            removeSelectionPopover(false);
            return;
        }
        removeSelectionPopover(false);
        selectionState = {
            fileId: activeFileId,
            selectedText: text.length > 500 ? `${text.slice(0, 497)}...` : text,
            locationReference: `Pagina ${pageNumber}`,
            rangeRect: anchorRect,
        };
        selectionPopover = document.createElement('button');
        selectionPopover.type = 'button';
        selectionPopover.className = 'sw-review-selection-popover';
        selectionPopover.innerHTML = '<i class="fa-solid fa-comment" aria-hidden="true"></i> Agregar observacion';
        selectionPopover.addEventListener('mousedown', (event) => event.preventDefault());
        selectionPopover.addEventListener('click', () => {
            const captured = selectionState;
            if (!captured || captured.fileId !== activeFileId) return;
            openEditor('contextual', captured.fileId, {
                selectedText: captured.selectedText,
                locationReference: captured.locationReference,
            });
        });
        document.body.append(selectionPopover);
        positionPopover(anchorRect);
    };

    const readJsonResponse = async (response) => {
        const redirectedToLogin = response.redirected && /([?&]page=login)(?:&|$)/i.test(response.url || '');
        if (response.status === 401 || redirectedToLogin) {
            const error = new Error('Tu sesion ha expirado. Vuelve a iniciar sesion.');
            error.status = 401;
            error.code = 'session_expired';
            throw error;
        }
        const contentType = String(response.headers.get('content-type') || '').toLowerCase();
        if (!contentType.includes('application/json')) {
            const error = new Error('Respuesta inesperada del servidor.');
            error.status = response.status;
            error.code = 'unexpected_response';
            throw error;
        }
        const payload = await response.json();
        if (!response.ok) {
            const error = new Error(payload.message || 'No fue posible completar la operacion.');
            error.status = response.status;
            error.data = payload.data || {};
            throw error;
        }
        return payload;
    };

    const explainSubmitError = (error) => {
        if (error?.status === 401) return 'Tu sesion ha expirado. Vuelve a iniciar sesion antes de confirmar la revision.';
        if (error?.status === 403) return error.message || 'No tienes autorizacion para confirmar esta revision documental.';
        if (error?.status === 409) return error.message || 'Algun documento cambio desde que iniciaste la revision. Recarga el expediente antes de continuar.';
        if (error?.status === 419) return 'La sesion del formulario expiro. Recarga la pagina antes de volver a intentarlo.';
        if (error?.status === 422) return error.message || 'La revision contiene datos invalidos. Corrige el borrador y vuelve a intentarlo.';
        if (error?.status === 500) return error.message || 'No fue posible confirmar la revision documental. No se realizaron cambios.';
        if (error?.code === 'unexpected_response') return 'El servidor devolvio una respuesta inesperada. No se guardo la revision.';
        return error?.message || 'No fue posible confirmar la revision documental.';
    };

    const refreshConfirmModal = () => {
        if (!confirmModal) return;
        const confirmState = getConfirmState();
        const currentSummary = decisionSummary();
        const hasCorrections = currentSummary.corrections > 0;
        if (confirmHeading) {
            confirmHeading.textContent = currentSummary.total > 0
                ? 'Has terminado la revision de los documentos.'
                : 'Todavia no hay una revision lista para confirmar.';
        }
        if (confirmMessage) {
            confirmMessage.textContent = hasCorrections
                ? 'Al confirmar, los estudiantes recibiran el resultado de la revision y se solicitaran correcciones en los documentos observados.'
                : 'Al confirmar, los documentos revisados quedaran registrados como aprobados por esta revision.';
        }
        if (confirmApprovedCount) confirmApprovedCount.textContent = `${currentSummary.approved} aprobados`;
        if (confirmCorrectionsCount) confirmCorrectionsCount.textContent = `${currentSummary.corrections} con correcciones`;
        if (confirmStatus) {
            confirmStatus.hidden = confirmModalStatus === '';
            confirmStatus.textContent = confirmModalStatus;
        }
        if (confirmError) {
            confirmError.hidden = confirmModalError === '';
            confirmError.textContent = confirmModalError;
        }
        if (confirmSubmitButton) {
            confirmSubmitButton.disabled = !confirmState.enabled || isSubmitting;
            confirmSubmitButton.querySelector('span').textContent = isSubmitting ? 'Guardando revision...' : 'Confirmar revision';
        }
    };

    const closeConfirmModal = (force = false) => {
        if (!confirmModal) return;
        if (isSubmitting && !force) return;
        confirmModal.hidden = true;
        confirmModalOpen = false;
        confirmModalError = '';
        confirmModalStatus = '';
        refreshConfirmModal();
    };

    const openConfirmModal = () => {
        if (!confirmModal || isSubmitting) return;
        const confirmState = getConfirmState();
        if (!confirmState.enabled) {
            announce(confirmState.reason, 'info');
            return;
        }
        confirmModalError = '';
        confirmModalStatus = '';
        confirmModal.hidden = false;
        confirmModalOpen = true;
        refreshConfirmModal();
    };

    const handleSubmitSuccess = (payload) => {
        const result = payload?.data || {};
        closeConfirmModal(true);
        clearDraftState();
        isSubmitting = false;
        setFlashToast(payload.message || result.message || 'Revision confirmada correctamente.', 'success');
        window.location.reload();
    };

    const submitReview = async () => {
        if (isSubmitting) return;
        const confirmState = getConfirmState();
        if (!confirmState.enabled) {
            confirmModalError = confirmState.reason;
            refreshConfirmModal();
            renderReviewCenter();
            return;
        }
        const decisions = serializeDecisions();
        isSubmitting = true;
        reviewError = '';
        confirmModalError = '';
        confirmModalStatus = 'Guardando revision...';
        removeSelectionPopover(true);
        renderReviewCenter();
        refreshConfirmModal();

        const body = new FormData();
        body.set('_csrf', reviewCsrf);
        body.set('project_id', String(projectId));
        body.set('expected_project_status', String(config.expected_project_status || ''));
        body.set('context', reviewContext);
        body.set('decisions', JSON.stringify(decisions));

        try {
            const payload = await readJsonResponse(await fetch(reviewEndpoint, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { Accept: 'application/json' },
                body,
            }));
            if (!payload.success) throw new Error(payload.message || 'No fue posible confirmar la revision documental.');
            handleSubmitSuccess(payload);
        } catch (error) {
            isSubmitting = false;
            confirmModalStatus = '';
            confirmModalError = explainSubmitError(error);
            refreshConfirmModal();
            renderReviewCenter();
        }
    };

    manager.querySelectorAll('[data-sw-file]').forEach((button) => button.addEventListener('click', () => {
        if (isSubmitting) return;
        const fileId = Number(button.dataset.fileId || 0);
        if (!filesById.has(fileId)) return;
        activeFileId = fileId;
        editorState = null;
        reviewError = '';
        removeSelectionPopover(true);
        renderReviewCenter();
        if (observationPanel) observationPanel.scrollTop = 0;
    }));

    previewStage?.addEventListener('mouseup', () => setTimeout(inspectTextSelection, 0));
    previewStage?.addEventListener('keyup', () => setTimeout(inspectTextSelection, 0));
    previewStage?.addEventListener('scroll', () => removeSelectionPopover(false), { passive: true });
    manager.querySelector('[data-sw-viewer-zoom]')?.addEventListener('click', () => removeSelectionPopover(true), true);

    document.addEventListener('selectionchange', () => {
        if (!selectionPopover) return;
        const selection = window.getSelection();
        if (!selection || selection.isCollapsed) removeSelectionPopover(false);
    });

    document.addEventListener('pointerdown', (event) => {
        if (selectionPopover && !selectionPopover.contains(event.target) && !event.target.closest('.sw-poc-text-layer')) {
            removeSelectionPopover(false);
        }
        if (confirmModalOpen && confirmModal && event.target === confirmModal) {
            closeConfirmModal(false);
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') return;
        if (confirmModalOpen) {
            closeConfirmModal(false);
            return;
        }
        if (selectionPopover) {
            removeSelectionPopover(true);
            return;
        }
        if (editorState && !isSubmitting) {
            editorState = null;
            reviewError = '';
            renderReviewCenter();
        }
    });

    window.addEventListener('resize', () => {
        if (selectionPopover && selectionState?.rangeRect) positionPopover(selectionState.rangeRect);
    });
    window.addEventListener('beforeunload', beforeUnloadHandler);

    if (previewStage && typeof MutationObserver === 'function') {
        new MutationObserver(() => {
            if (selectionPopover) removeSelectionPopover(true);
        }).observe(previewStage, { childList: true, subtree: true });
    }

    confirmModal?.querySelectorAll('[data-sw-review-confirm-close],[data-sw-review-confirm-cancel]').forEach((button) => {
        button.addEventListener('click', () => closeConfirmModal(false));
    });
    confirmSubmitButton?.addEventListener('click', () => submitReview());

    updateFileIndicators();
    renderReviewCenter();
    refreshConfirmModal();
});
