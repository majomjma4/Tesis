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
    const viewerPanel = manager.querySelector('.sw-viewer-panel');
    const floatingLayer = (() => {
        if (!viewerPanel) return null;
        let layer = viewerPanel.querySelector('[data-sw-review-floating-layer]');
        if (!layer) {
            layer = document.createElement('div');
            layer.className = 'sw-review-floating-layer';
            layer.dataset.swReviewFloatingLayer = '';
            viewerPanel.append(layer);
        }
        if (layer) {
            layer.style.pointerEvents = 'none';
        }
        return layer;
    })();
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
    const decisionModal = manager.querySelector('[data-sw-review-decision-modal]');
    const decisionModalTitle = decisionModal?.querySelector('[data-sw-review-decision-title]');
    const decisionModalMessage = decisionModal?.querySelector('[data-sw-review-decision-message]');
    const decisionModalConfirm = decisionModal?.querySelector('[data-sw-review-decision-confirm]');

    const reviewDraft = Object.create(null);
    const observationMeta = new Map();
    const completedFileIds = new Set();
    const completedSnapshots = new Map();

    let activeFileId = 0;
    let activeInternalZipEntry = null;
    let editingCompletedFileId = null;
    let editorState = null;
    let selectionState = null;
    let selectionPopover = null;
    let reviewError = '';
    let isSubmitting = false;
    let confirmModalOpen = false;
    let confirmModalError = '';
    let confirmModalStatus = '';

    const showDecisionDialog = ({ title, message, confirmText, confirmIcon = 'fa-check', confirmClass = 'is-success', onConfirm, onCancel }) => {
        if (!decisionModal) {
            if (onConfirm) onConfirm();
            return;
        }
        if (decisionModalTitle) decisionModalTitle.textContent = title;
        if (decisionModalMessage) decisionModalMessage.textContent = message;
        if (decisionModalConfirm) {
            decisionModalConfirm.className = `sw-review-confirm-submit ${confirmClass}`;
            const iconHtml = confirmIcon ? `<i class="fa-solid ${confirmIcon}" aria-hidden="true"></i> ` : '';
            decisionModalConfirm.innerHTML = `${iconHtml}<span>${confirmText}</span>`;
        }

        const cancelBtns = decisionModal.querySelectorAll('[data-sw-review-decision-cancel]');

        const cleanup = () => {
            decisionModal.hidden = true;
            decisionModalConfirm?.removeEventListener('click', handleConfirm);
            cancelBtns.forEach((btn) => btn.removeEventListener('click', handleCancel));
        };

        const handleConfirm = () => {
            cleanup();
            if (onConfirm) onConfirm();
        };

        const handleCancel = () => {
            cleanup();
            if (onCancel) onCancel();
        };

        decisionModalConfirm?.addEventListener('click', handleConfirm);
        cancelBtns.forEach((btn) => btn.addEventListener('click', handleCancel));

        decisionModal.hidden = false;
    };

    const takeCompletedSnapshot = (fileId) => {
        const draft = reviewDraft[fileId];
        if (!draft) {
            completedSnapshots.delete(fileId);
            return;
        }
        completedSnapshots.set(fileId, JSON.stringify({
            status: draft.status || '',
            observations: (draft.observations || []).map((obs) => ({
                body: String(obs.body || '').trim(),
                category: String(obs.category || '').trim(),
                location_reference: obs.location_reference ? String(obs.location_reference).trim() : null,
            })),
        }));
    };

    const getCompletedSnapshot = (fileId) => {
        const raw = completedSnapshots.get(fileId);
        if (!raw) return null;
        try {
            return JSON.parse(raw);
        } catch (e) {
            return null;
        }
    };

    const hasRealChanges = (fileId) => {
        const snapshot = getCompletedSnapshot(fileId);
        if (!snapshot) return false;
        const currentDraft = reviewDraft[fileId];
        const currentStatus = currentDraft?.status || '';
        const currentObs = (currentDraft?.observations || []).map((obs) => ({
            body: String(obs.body || '').trim(),
            category: String(obs.category || '').trim(),
            location_reference: obs.location_reference ? String(obs.location_reference).trim() : null,
        }));

        if (currentStatus !== snapshot.status) return true;
        if (currentObs.length !== snapshot.observations.length) return true;
        for (let i = 0; i < currentObs.length; i++) {
            if (currentObs[i].body !== snapshot.observations[i].body ||
                currentObs[i].category !== snapshot.observations[i].category ||
                currentObs[i].location_reference !== snapshot.observations[i].location_reference) {
                return true;
            }
        }
        return false;
    };

    const restoreCompletedSnapshot = (fileId) => {
        const snapshot = getCompletedSnapshot(fileId);
        if (!snapshot) return;
        const draft = draftFor(fileId, snapshot.status);
        if (draft) {
            draft.status = snapshot.status;
            draft.observations = snapshot.observations.map((obs) => ({ ...obs }));
        }
    };

    const startEditingCompletedReview = (fileId) => {
        showDecisionDialog({
            title: '¿Editar la revisión de este documento?',
            message: 'La decisión actual se conservará hasta que realices y guardes nuevos cambios.',
            confirmText: 'Editar revisión',
            confirmIcon: 'fa-pen',
            confirmClass: 'is-primary-blue',
            onConfirm: () => {
                takeCompletedSnapshot(fileId);
                editingCompletedFileId = fileId;
                renderReviewCenter();
            },
        });
    };

    const discardCompletedEdits = (fileId, onDiscardSuccess) => {
        showDecisionDialog({
            title: '¿Descartar los cambios?',
            message: 'Si descartas los cambios, la revisión volverá exactamente al estado en que la finalizaste.',
            confirmText: 'Descartar cambios',
            confirmIcon: 'fa-trash-can',
            confirmClass: 'is-danger-red',
            onConfirm: () => {
                restoreCompletedSnapshot(fileId);
                completedFileIds.add(fileId);
                editingCompletedFileId = null;
                reviewError = '';
                saveReviewDraft();
                renderReviewCenter();
                if (onDiscardSuccess) onDiscardSuccess();
            },
        });
    };

    const buildLocationReference = (pageNumber = null) => {
        if (activeInternalZipEntry) {
            const base = `${activeInternalZipEntry.parentFileName} → ${activeInternalZipEntry.entryName}`;
            return pageNumber ? `${base} · Pagina ${pageNumber}` : base;
        }
        return pageNumber ? `Pagina ${pageNumber}` : '';
    };

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

    const teacherId = Number(config.teacher_id || 0);
    const draftStorageKey = (projectId > 0 && teacherId > 0)
        ? `teacher_review_draft_${projectId}_${teacherId}`
        : '';
    let lastSavedTime = null;

    const beforeUnloadHandler = (event) => {
        if (isSubmitting || !isFormDirty()) return;
        event.preventDefault();
        event.returnValue = '';
    };

    const saveReviewDraft = () => {
        if (!draftStorageKey || !reviewIsAvailable) return;
        try {
            const payload = {
                version: 1,
                project_id: projectId,
                teacher_id: teacherId,
                expected_project_status: String(config.expected_project_status || ''),
                saved_at: new Date().toISOString(),
                reviewDraft,
                completedFileIds: Array.from(completedFileIds),
                generalObservations,
                generalMeta,
                observationMeta: Object.fromEntries(
                    Array.from(observationMeta.entries()).map(([k, v]) => [k, v])
                ),
            };
            localStorage.setItem(draftStorageKey, JSON.stringify(payload));
            lastSavedTime = new Date();
        } catch (error) {
            console.warn('No se pudo guardar el borrador en localStorage.', error);
        }
    };

    const clearLocalStorageDraft = () => {
        if (!draftStorageKey) return;
        try {
            localStorage.removeItem(draftStorageKey);
            lastSavedTime = null;
        } catch (error) {
            /* ignore storage errors */
        }
    };

    const discardReviewDraft = () => {
        if (isSubmitting) return;
        if (!window.confirm('¿Deseas descartar el borrador de revisión guardado localmente? Se eliminarán todas las decisiones temporales y observaciones no confirmadas.')) {
            return;
        }
        clearLocalStorageDraft();
        clearDraftState();
        deselectCurrentFile();
        announce('Se descartó el borrador de revisión guardado.', 'info');
    };

    const restoreReviewDraft = () => {
        if (!draftStorageKey || !reviewIsAvailable) return;
        try {
            const raw = localStorage.getItem(draftStorageKey);
            if (!raw) return;
            const payload = JSON.parse(raw);
            if (!payload || payload.version !== 1) return;
            if (Number(payload.project_id) !== projectId || Number(payload.teacher_id) !== teacherId) return;
            if (String(payload.expected_project_status || '') !== String(config.expected_project_status || '')) {
                clearLocalStorageDraft();
                return;
            }

            let restoredCount = 0;
            let outdatedFileNames = [];

            if (payload.reviewDraft && typeof payload.reviewDraft === 'object') {
                Object.entries(payload.reviewDraft).forEach(([fileIdStr, draftItem]) => {
                    const fileId = Number(fileIdStr);
                    const file = filesById.get(fileId);
                    if (!file || file.status === 'approved') return;

                    // VALIDACIÓN DE CHECKSUM
                    if (String(draftItem.expected_checksum || '').toLowerCase() === file.expected_checksum.toLowerCase()) {
                        if (allowedStatuses.has(draftItem.status)) {
                            reviewDraft[fileId] = {
                                file_id: file.file_id,
                                expected_checksum: file.expected_checksum,
                                status: draftItem.status,
                                decisionSource: draftItem.decisionSource === 'manual' ? 'manual' : 'auto',
                                observations: Array.isArray(draftItem.observations) ? draftItem.observations.map((obs) => ({
                                    body: String(obs.body || '').trim(),
                                    category: String(obs.category || 'General').trim(),
                                    location_reference: obs.location_reference ? String(obs.location_reference).trim() : null,
                                })) : [],
                            };
                            restoredCount++;
                        }
                    } else {
                        outdatedFileNames.push(file.name);
                    }
                });
            }

            if (Array.isArray(payload.completedFileIds)) {
                payload.completedFileIds.forEach((id) => {
                    const fileId = Number(id);
                    if (reviewDraft[fileId]) {
                        completedFileIds.add(fileId);
                        takeCompletedSnapshot(fileId);
                    }
                });
            }

            if (Array.isArray(payload.generalObservations)) {
                payload.generalObservations.forEach((item) => {
                    const body = String(item.body || '').trim();
                    if (body) {
                        generalObservations.push({
                            body,
                            category: String(item.category || 'General').trim(),
                            location_reference: null,
                        });
                    }
                });
            }

            if (payload.observationMeta && typeof payload.observationMeta === 'object') {
                Object.entries(payload.observationMeta).forEach(([fileIdStr, metas]) => {
                    const fileId = Number(fileIdStr);
                    if (reviewDraft[fileId] && Array.isArray(metas)) {
                        observationMeta.set(fileId, metas.map((m) => ({
                            selected_text: String(m?.selected_text || ''),
                            page_number: Number(m?.page_number || 0) || null,
                            entry_name: m?.entry_name ? String(m.entry_name) : null,
                            internal_entry: m?.internal_entry ? String(m.internal_entry) : null,
                            relative_rects: Array.isArray(m?.relative_rects) ? m.relative_rects.map((rect) => ({
                                left: Number(rect?.left), top: Number(rect?.top), width: Number(rect?.width), height: Number(rect?.height),
                            })) : [],
                        })));
                    }
                });
            }

            if (payload.saved_at) {
                lastSavedTime = new Date(payload.saved_at);
            }

            if (restoredCount > 0 || generalObservations.length > 0) {
                announce('Se recuperó tu borrador de revisión local.', 'info');
            }

            if (outdatedFileNames.length > 0) {
                announce(`Borrador desactualizado omitido para: ${outdatedFileNames.join(', ')}.`, 'warning');
            }
        } catch (error) {
            console.error('No se pudo restaurar el borrador en localStorage.', error);
        }
    };

    const draftFor = (fileId, initialStatus = '') => {
        if (!reviewDraft[fileId] && allowedStatuses.has(initialStatus)) {
            const file = filesById.get(fileId);
            if (!file || file.status === 'approved') return null;
            reviewDraft[fileId] = {
                file_id: file.file_id,
                expected_checksum: file.expected_checksum,
                status: initialStatus,
                decisionSource: 'auto',
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
            return `Escribe un comentario de entre ${limits.bodyMin} y ${limits.bodyMax} caracteres.`;
        }
        if (!category || category.length > limits.categoryMax || location.length > limits.locationMax) {
            return 'La categoria o referencia de la observacion no es valida.';
        }
        return '';
    };

    const validateReady = (fileId) => {
        const draft = draftFor(fileId);
        if (!draft || !allowedStatuses.has(draft.status)) return 'Selecciona una decision para este archivo.';
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
                observations: draft.observations.map((observation, index) => ({
                    body: observation.body,
                    category: observation.category,
                    location_reference: observation.location_reference,
                    anchor: (() => {
                        const meta = metadataFor(file.file_id)[index];
                        if (!meta?.page_number || !Array.isArray(meta.relative_rects) || !meta.relative_rects.length) return null;
                        return {
                            selected_text: String(meta.selected_text || '').slice(0, 500),
                            page_number: Number(meta.page_number),
                            relative_rects: meta.relative_rects.map(({ left, top, width, height }) => ({ left, top, width, height })),
                            internal_entry: meta.internal_entry || meta.entry_name || null,
                        };
                    })(),
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

        let timeLabel = '';
        if (lastSavedTime) {
            const hours = String(lastSavedTime.getHours()).padStart(2, '0');
            const mins = String(lastSavedTime.getMinutes()).padStart(2, '0');
            timeLabel = `Borrador guardado · ${hours}:${mins}`;
        } else if (hasDraftChanges() || generalObservations.length > 0) {
            timeLabel = 'Borrador en memoria';
        }

        const hasSavedDraft = Boolean(draftStorageKey && localStorage.getItem(draftStorageKey));

        section.innerHTML = `
            <div class="sw-review-progress-heading"><strong>Revisión</strong><span>${value.reviewed} de ${value.total} documentos</span></div>
            <div class="sw-review-progress-track" aria-hidden="true"><span style="width:${value.total ? Math.round((value.reviewed / value.total) * 100) : 0}%"></span></div>
            <div class="sw-review-progress-counts">
                <span class="is-approved"><i class="fa-solid fa-check" aria-hidden="true"></i>${value.approved} aprobados</span>
                <span class="is-corrections"><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>${value.corrections} con correcciones</span>
                <span class="is-pending"><i class="fa-regular fa-circle" aria-hidden="true"></i>${value.pending} pendientes</span>
            </div>
            <div class="sw-review-draft-meta-bar">
                <span class="sw-review-autosave-tag"><i class="fa-solid fa-cloud-arrow-up" aria-hidden="true"></i> ${timeLabel || 'Autoguardado activo'}</span>
                ${hasSavedDraft ? `<button type="button" class="sw-review-discard-draft-btn" data-sw-discard-draft><i class="fa-regular fa-trash-can" aria-hidden="true"></i> Descartar borrador</button>` : ''}
            </div>`;

        const discardBtn = section.querySelector('[data-sw-discard-draft]');
        discardBtn?.addEventListener('click', () => discardReviewDraft());

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

    const generalObservations = [];
    const generalMeta = [];

    const isFormDirty = () => {
        if (!editorState) return false;
        const form = observationPanel?.querySelector('[data-sw-review-form]');
        const body = String(form?.querySelector('[data-sw-review-body]')?.value || '').trim();
        return body.length > 0;
    };

    const deselectCurrentFile = () => {
        activeFileId = 0;
        activeInternalZipEntry = null;
        editorState = null;
        reviewError = '';
        removeSelectionPopover(true);

        manager.querySelectorAll('[data-sw-file], [data-sw-zip-entry]').forEach((btn) => btn.classList.remove('is-selected'));

        const viewerName = manager.querySelector('[data-sw-viewer-name]');
        const viewerMeta = manager.querySelector('[data-sw-viewer-meta]');
        const viewerDownload = manager.querySelector('[data-sw-viewer-download]');
        const viewerZoom = manager.querySelector('[data-sw-viewer-zoom]');
        if (viewerName) viewerName.textContent = 'Visor de documentos';
        if (viewerMeta) viewerMeta.textContent = 'Exploración y consulta documental';
        if (viewerDownload) { viewerDownload.hidden = true; viewerDownload.href = '#'; }
        if (viewerZoom) viewerZoom.hidden = true;

        if (previewStage) {
            previewStage.replaceChildren();
            const card = document.createElement('div');
            card.className = 'sw-preview-state-card is-empty';
            card.innerHTML = `
                <div class="sw-state-icon-badge">
                    <i class="fa-regular fa-file-lines main-icon" aria-hidden="true"></i>
                    <i class="fa-solid fa-eye sub-icon" aria-hidden="true"></i>
                </div>
                <h3 class="sw-state-title">Visualiza tus archivos</h3>
                <p class="sw-state-message">Selecciona un documento del panel Archivos para consultarlo aquí.</p>
            `;
            previewStage.append(card);
            previewStage.hidden = false;
        }

        renderReviewCenter();
        if (observationPanel) observationPanel.scrollTop = 0;
    };

    const openEditor = (mode, fileId, options = {}) => {
        const targetFileId = Number(fileId || 0);
        if (isSubmitting || !reviewIsAvailable) return;
        if (targetFileId > 0 && filesById.get(targetFileId)?.status === 'approved') return;

        if (targetFileId > 0 && completedFileIds.has(targetFileId) && editingCompletedFileId !== targetFileId) {
            startEditingCompletedReview(targetFileId);
            return;
        }

        reviewError = '';
        editorState = {
            mode,
            fileId: targetFileId,
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

    const duplicateBodyExists = (body, editingFileId, editingIndex) => {
        if (editingFileId === 0) {
            return generalObservations.some((item, index) => item.body === body && index !== editingIndex);
        }
        return Object.values(reviewDraft).some((draft) =>
            draft.observations.some((item, index) => item.body === body && !(draft.file_id === editingFileId && index === editingIndex))
        );
    };

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
            reviewError = 'No incluyas observaciones duplicadas en la misma revisión.';
            renderReviewCenter();
            return;
        }
        if (editorState.fileId === 0) {
            const observation = { body, category, location_reference: locationReference || null };
            const meta = { selected_text: editorState.selectedText || '' };
            if (editorState.index === null) {
                generalObservations.push(observation);
                generalMeta.push(meta);
            } else {
                generalObservations[editorState.index] = observation;
                generalMeta[editorState.index] = meta;
            }
            editorState = null;
            reviewError = '';
            saveReviewDraft();
            renderReviewCenter();
            announce('Observación general agregada al borrador del proyecto.', 'info');
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
        if (draft.decisionSource !== 'manual') draft.decisionSource = 'auto';

        if (editingCompletedFileId === editorState.fileId && hasRealChanges(editorState.fileId)) {
            completedFileIds.delete(editorState.fileId);
        }

        editorState = null;
        reviewError = '';
        saveReviewDraft();
        renderReviewCenter();
        announce('Observación agregada al borrador. Todavía no se ha enviado la revisión.', 'info');
    };

    const createCategorySelect = (selectedCategory) => {
        const initialCategory = categories.includes(selectedCategory) ? selectedCategory : (categories[0] || 'General');

        const wrapper = document.createElement('div');
        wrapper.className = 'sw-review-category-wrapper';

        const hiddenInput = document.createElement('input');
        hiddenInput.type = 'hidden';
        hiddenInput.dataset.swReviewCategory = '';
        hiddenInput.value = initialCategory;

        const trigger = document.createElement('button');
        trigger.type = 'button';
        trigger.className = 'sw-review-category-trigger';
        trigger.setAttribute('role', 'combobox');
        trigger.setAttribute('aria-haspopup', 'listbox');
        trigger.setAttribute('aria-expanded', 'false');
        trigger.disabled = isSubmitting;

        const labelSpan = document.createElement('span');
        labelSpan.textContent = initialCategory;

        const icon = document.createElement('i');
        icon.className = 'fa-solid fa-chevron-down';
        icon.setAttribute('aria-hidden', 'true');

        trigger.append(labelSpan, icon);

        const menu = document.createElement('ul');
        menu.className = 'sw-review-category-menu';
        menu.setAttribute('role', 'listbox');
        menu.hidden = true;

        let focusedIndex = -1;

        const updateSelection = (category) => {
            hiddenInput.value = category;
            labelSpan.textContent = category;
            menu.querySelectorAll('[role="option"]').forEach((opt) => {
                const isMatch = opt.dataset.value === category;
                opt.setAttribute('aria-selected', isMatch ? 'true' : 'false');
            });
        };

        const closeMenu = () => {
            menu.hidden = true;
            trigger.setAttribute('aria-expanded', 'false');
            focusedIndex = -1;
            menu.querySelectorAll('.sw-review-category-option').forEach(el => el.classList.remove('is-focused'));
        };

        const openMenu = () => {
            if (isSubmitting) return;
            menu.hidden = false;
            trigger.setAttribute('aria-expanded', 'true');
            focusedIndex = categories.indexOf(hiddenInput.value);
            highlightOption(focusedIndex);
        };

        const highlightOption = (index) => {
            const options = menu.querySelectorAll('.sw-review-category-option');
            options.forEach((opt, i) => {
                opt.classList.toggle('is-focused', i === index);
                if (i === index) opt.scrollIntoView({ block: 'nearest' });
            });
        };

        categories.forEach((category) => {
            const option = document.createElement('li');
            option.className = 'sw-review-category-option';
            option.setAttribute('role', 'option');
            option.dataset.value = category;
            option.setAttribute('aria-selected', category === initialCategory ? 'true' : 'false');

            const text = document.createElement('span');
            text.textContent = category;

            const check = document.createElement('i');
            check.className = 'fa-solid fa-check';
            check.setAttribute('aria-hidden', 'true');

            option.append(text, check);

            option.addEventListener('click', (e) => {
                e.stopPropagation();
                updateSelection(category);
                closeMenu();
                trigger.focus();
            });

            menu.append(option);
        });

        trigger.addEventListener('click', (e) => {
            e.preventDefault();
            if (menu.hidden) openMenu(); else closeMenu();
        });

        trigger.addEventListener('keydown', (e) => {
            if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
                e.preventDefault();
                if (menu.hidden) openMenu();
                const dir = e.key === 'ArrowDown' ? 1 : -1;
                focusedIndex = Math.max(0, Math.min(categories.length - 1, (focusedIndex < 0 ? 0 : focusedIndex) + dir));
                highlightOption(focusedIndex);
            } else if (e.key === 'Enter' || e.key === ' ') {
                if (!menu.hidden && focusedIndex >= 0 && focusedIndex < categories.length) {
                    e.preventDefault();
                    updateSelection(categories[focusedIndex]);
                    closeMenu();
                } else if (menu.hidden) {
                    e.preventDefault();
                    openMenu();
                }
            } else if (e.key === 'Escape') {
                if (!menu.hidden) {
                    e.preventDefault();
                    closeMenu();
                }
            } else if (e.key === 'Tab') {
                closeMenu();
            }
        });

        const handleOutsideClick = (e) => {
            if (!wrapper.contains(e.target)) {
                closeMenu();
            }
        };

        document.addEventListener('pointerdown', handleOutsideClick);

        wrapper.append(hiddenInput, trigger, menu);
        return wrapper;
    };

    const createEditor = () => {
        const state = editorState;
        const form = document.createElement('form');
        form.className = 'sw-review-form';
        form.dataset.swReviewForm = '';
        form.noValidate = true;
        const title = document.createElement('div');
        title.className = 'sw-review-form-heading';
        title.innerHTML = `<strong>${state.index === null ? 'Nueva observación' : 'Editar observación'}</strong><button type="button" data-sw-review-cancel aria-label="Cancelar"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>`;
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
        categoryLabel.textContent = 'Categoría';
        const categoryCustomSelect = createCategorySelect(state.category);
        categoryLabel.append(categoryCustomSelect);
        const bodyLabel = document.createElement('label');
        bodyLabel.textContent = 'Comentario';
        const textarea = document.createElement('textarea');
        textarea.dataset.swReviewBody = '';
        textarea.rows = 5;
        textarea.minLength = limits.bodyMin;
        textarea.maxLength = limits.bodyMax;
        textarea.placeholder = state.fileId === 0 ? 'Describe un comentario o sugerencia general para el proyecto.' : 'Describe con claridad el cambio solicitado.';
        textarea.value = state.body;
        textarea.disabled = isSubmitting;
        const counter = document.createElement('small');
        counter.className = 'sw-review-character-count';
        counter.textContent = `${textarea.value.length}/${limits.bodyMax}`;
        textarea.addEventListener('input', () => {
            counter.textContent = `${textarea.value.length}/${limits.bodyMax}`;
            if (reviewError) {
                const len = textarea.value.trim().length;
                if (len >= limits.bodyMin && len <= limits.bodyMax) {
                    reviewError = '';
                    form.querySelector('.sw-review-error')?.remove();
                }
            }
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
        actions.innerHTML = '<button type="button" class="is-secondary" data-sw-review-cancel>Cancelar</button><button type="submit" class="is-primary">Agregar observación</button>';
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

    const createProjectGeneralSection = () => {
        const section = document.createElement('section');
        section.className = 'sw-review-observation-group is-general-project';
        const heading = document.createElement('div');
        heading.className = 'sw-review-group-heading';
        heading.innerHTML = `<strong>Observaciones generales del proyecto</strong><span>${generalObservations.length}</span>`;
        section.append(heading);

        const info = document.createElement('p');
        info.className = 'sw-review-empty-inline';
        info.style.marginBottom = '0.55rem';
        info.textContent = 'Registra comentarios que correspondan al proyecto en general y no a un documento específico.';
        section.append(info);

        const addBtn = document.createElement('button');
        addBtn.type = 'button';
        addBtn.className = 'sw-review-add-general';
        addBtn.disabled = isSubmitting;
        addBtn.innerHTML = '<i class="fa-solid fa-plus" aria-hidden="true"></i> Agregar observación general';
        addBtn.addEventListener('click', () => openEditor('general_project', 0));
        section.append(addBtn);

        if (generalObservations.length > 0) {
            const list = document.createElement('div');
            list.className = 'sw-review-card-list';
            generalObservations.forEach((item, index) => {
                const card = document.createElement('article');
                card.className = 'sw-review-observation-card is-draft';
                const meta = document.createElement('div');
                meta.className = 'sw-review-card-meta';
                const category = document.createElement('strong');
                category.textContent = item.category || 'General';
                const state = document.createElement('span');
                state.textContent = 'General del proyecto';
                meta.append(category, state);
                const body = document.createElement('p');
                body.textContent = item.body;
                card.append(meta, body);

                const actions = document.createElement('div');
                actions.className = 'sw-review-card-actions';
                actions.innerHTML = '<button type="button" data-action="edit"><i class="fa-solid fa-pen" aria-hidden="true"></i> Editar</button><button type="button" data-action="delete"><i class="fa-regular fa-trash-can" aria-hidden="true"></i> Eliminar</button>';
                actions.querySelectorAll('button').forEach((b) => b.disabled = isSubmitting);
                actions.querySelector('[data-action="edit"]').addEventListener('click', () => openEditor('general_project', 0, {
                    index,
                    category: item.category,
                    body: item.body,
                }));
                actions.querySelector('[data-action="delete"]').addEventListener('click', () => {
                    if (isSubmitting) return;
                    generalObservations.splice(index, 1);
                    generalMeta.splice(index, 1);
                    reviewError = '';
                    saveReviewDraft();
                    renderReviewCenter();
                });
                card.append(actions);
                list.append(card);
            });
            section.append(list);
        }
        return section;
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
        const contextualMap = getContextualObservationMap(file.file_id);

        items.forEach((item, index) => {
            const selectedText = metas[index]?.selected_text || '';
            const compactLoc = formatCompactLocation(item.location_reference);
            const contextualInfo = contextualMap.get(index);
            const obsNumber = contextualInfo?.obsNumber || null;
            const colorClass = contextualInfo?.colorClass || '';

            const card = document.createElement('article');
            card.className = `sw-review-observation-card is-draft ${colorClass}`.trim();

            const renderCardContent = () => {
                card.innerHTML = '';
                const isExpanded = card.dataset.expanded === 'true';

                // Header
                const meta = document.createElement('div');
                meta.className = 'sw-review-card-meta';

                if (obsNumber) {
                    const numberBadge = document.createElement('span');
                    numberBadge.className = `sw-review-card-number-badge ${colorClass}`;
                    numberBadge.textContent = String(obsNumber);
                    meta.append(numberBadge);
                }

                if (item.category && item.category !== 'General') {
                    const category = document.createElement('strong');
                    category.className = 'sw-review-category-badge';
                    category.textContent = item.category;
                    meta.append(category);
                } else {
                    const typeLabel = document.createElement('span');
                    typeLabel.className = 'sw-review-type-label';
                    typeLabel.textContent = selectedText ? 'Texto seleccionado' : 'Observación general';
                    meta.append(typeLabel);
                }

                const pending = document.createElement('span');
                pending.textContent = 'Borrador';
                meta.append(pending);
                card.append(meta);

                // Body Comment (Primary Focus)
                const commentBody = document.createElement('p');
                commentBody.className = 'sw-review-card-comment';
                const commentTrunc = truncateText(item.body, 140);
                if (!isExpanded && commentTrunc.isTruncated) {
                    commentBody.textContent = commentTrunc.truncated;
                } else {
                    commentBody.textContent = item.body;
                }
                card.append(commentBody);

                // Compact Location Line
                if (compactLoc) {
                    const metaLine = document.createElement('div');
                    metaLine.className = 'sw-review-card-meta-line';
                    const locSmall = document.createElement('small');
                    locSmall.textContent = isExpanded ? (item.location_reference || compactLoc) : compactLoc;
                    metaLine.append(locSmall);
                    card.append(metaLine);
                }

                // Selected Text Fragment
                if (selectedText) {
                    const fragment = document.createElement('blockquote');
                    fragment.className = 'sw-review-card-quote';
                    const quoteTrunc = truncateText(selectedText, 100);
                    if (!isExpanded && quoteTrunc.isTruncated) {
                        fragment.textContent = `"${quoteTrunc.truncated}"`;
                    } else {
                        fragment.textContent = `"${selectedText}"`;
                    }
                    card.append(fragment);
                }

                // "Ver más" / "Ver menos" toggle
                const isCommentTruncated = commentTrunc.isTruncated;
                const isQuoteTruncated = selectedText && selectedText.length > 100;
                if (isCommentTruncated || isQuoteTruncated) {
                    const toggleBtn = document.createElement('button');
                    toggleBtn.type = 'button';
                    toggleBtn.className = 'sw-review-toggle-more-btn';
                    toggleBtn.innerHTML = isExpanded
                        ? '<i class="fa-solid fa-chevron-up" aria-hidden="true"></i> Ver menos'
                        : '<i class="fa-solid fa-chevron-down" aria-hidden="true"></i> Ver más';

                    toggleBtn.addEventListener('click', (e) => {
                        e.stopPropagation();
                        card.dataset.expanded = isExpanded ? 'false' : 'true';
                        renderCardContent();
                    });
                    card.append(toggleBtn);
                }

                // Actions (Editar / Eliminar)
                const actions = document.createElement('div');
                actions.className = 'sw-review-card-actions';
                actions.innerHTML = '<button type="button" data-action="edit"><i class="fa-solid fa-pen" aria-hidden="true"></i> Editar</button><button type="button" data-action="delete"><i class="fa-regular fa-trash-can" aria-hidden="true"></i> Eliminar</button>';
                actions.querySelectorAll('button').forEach((button) => {
                    button.disabled = isSubmitting;
                });
                actions.querySelector('[data-action="edit"]').addEventListener('click', (e) => {
                    e.stopPropagation();
                    openEditor('edit', file.file_id, {
                        index,
                        category: item.category,
                        body: item.body,
                        locationReference: item.location_reference || '',
                        selectedText: selectedText,
                    });
                });
                actions.querySelector('[data-action="delete"]').addEventListener('click', (e) => {
                    e.stopPropagation();
                    if (isSubmitting) return;
                    draft.observations.splice(index, 1);
                    metas.splice(index, 1);
                    completedFileIds.delete(file.file_id);
                    if (!draft.observations.length && draft.status === 'corrections_requested' && draft.decisionSource === 'auto') {
                        delete reviewDraft[file.file_id];
                        observationMeta.delete(file.file_id);
                    }
                    removeActiveNotePopover();
                    reviewError = '';
                    saveReviewDraft();
                    renderReviewCenter();
                });
                card.append(actions);
            };

            renderCardContent();

            card.addEventListener('click', () => {
                const highlights = previewStage?.querySelectorAll(`[data-observation-index="${index}"]`);
                if (highlights && highlights.length) {
                    highlights[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
                    showHighlightNote(highlights[0], item, index, file.file_id);
                }
            });

            list.append(card);
        });
        section.append(list);
        return section;
    };

    const closeDecisionModal = () => {
        if (!decisionModal) return;
        decisionModal.hidden = true;
        decisionModalAction = null;
    };

    const openDecisionModal = (fileId, mode) => {
        const draft = draftFor(fileId);
        if (!decisionModal) return;
        const withObservations = mode === 'with_observations';
        decisionModalTitle.textContent = withObservations ? '¿Aprobar con observaciones?' : '¿Aprobar este documento?';
        decisionModalMessage.textContent = withObservations
            ? `Este documento contiene ${draft?.observations.length || 0} observaci${draft?.observations.length === 1 ? 'ón' : 'ones'}. Se conservarán como comentarios, pero no se solicitarán correcciones.`
            : 'No has registrado observaciones. Si continúas, el documento se marcará como aprobado.';
        decisionModalConfirm.querySelector('span').textContent = withObservations ? 'Aprobar de todas formas' : 'Aprobar y continuar';
        decisionModalAction = { fileId, finish: !withObservations };
        decisionModal.hidden = false;
    };

    const approveDecision = (fileId, finish = false) => {
        const draft = draftFor(fileId, 'approved');
        if (!draft) return;
        draft.status = 'approved';
        draft.decisionSource = 'manual';
        completedFileIds.delete(fileId);
        reviewError = '';
        saveReviewDraft();
        closeDecisionModal();
        if (finish) finishFile(fileId);
        else renderReviewCenter();
    };

    const selectDecision = (fileId, status) => {
        if (isSubmitting || !reviewIsAvailable || !allowedStatuses.has(status)) return;
        if (completedFileIds.has(fileId) && editingCompletedFileId !== fileId) {
            startEditingCompletedReview(fileId);
            return;
        }

        const draft = draftFor(fileId, status);
        if (!draft) return;
        if (status === 'approved' && draft.observations.length) {
            showDecisionDialog({
                title: '¿Aprobar con observaciones?',
                message: `Este documento contiene ${draft.observations.length} observaci${draft.observations.length === 1 ? 'ón' : 'ones'}. Se conservarán como comentarios, pero no se solicitarán correcciones.`,
                confirmText: 'Aprobar de todas formas',
                confirmIcon: 'fa-check',
                confirmClass: 'is-success',
                onConfirm: () => {
                    draft.status = 'approved';
                    draft.decisionSource = 'manual';
                    if (editingCompletedFileId === fileId && hasRealChanges(fileId)) {
                        completedFileIds.delete(fileId);
                    }
                    reviewError = '';
                    saveReviewDraft();
                    renderReviewCenter();
                },
            });
            return;
        }

        draft.status = status;
        draft.decisionSource = 'manual';
        if (editingCompletedFileId === fileId && hasRealChanges(fileId)) {
            completedFileIds.delete(fileId);
        }
        reviewError = '';
        saveReviewDraft();
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
        const draft = draftFor(fileId);

        // Si se estaba editando una revisión completada y no hubo cambios reales
        if (completedFileIds.has(fileId) && editingCompletedFileId === fileId && !hasRealChanges(fileId)) {
            editingCompletedFileId = null;
            renderReviewCenter();
            announce('Revisión conservada sin cambios.', 'info');
            return;
        }

        // Regla: approved -> corrections_requested requiere al menos 1 observación
        if (draft?.status === 'corrections_requested' && (!draft.observations || !draft.observations.length)) {
            reviewError = 'Agrega al menos una observación para solicitar correcciones.';
            renderReviewCenter();
            return;
        }

        // Aprobación implícita con Listo si no hay decisión manual ni observaciones
        if (!draft || (!draft.status || draft.status === 'pending') && (!draft.observations || !draft.observations.length)) {
            showDecisionDialog({
                title: '¿Aprobar este documento?',
                message: 'No has registrado observaciones. Si continúas, el documento se marcará como aprobado.',
                confirmText: 'Aprobar y continuar',
                confirmIcon: 'fa-check',
                confirmClass: 'is-success',
                onConfirm: () => {
                    const updatedDraft = draftFor(fileId, 'approved');
                    if (updatedDraft) {
                        updatedDraft.status = 'approved';
                        updatedDraft.decisionSource = 'manual';
                    }
                    takeCompletedSnapshot(fileId);
                    completedFileIds.add(fileId);
                    editingCompletedFileId = null;
                    reviewError = '';
                    editorState = null;
                    saveReviewDraft();
                    updateFileIndicators();

                    const next = nextPendingAfter(fileId);
                    if (next) {
                        const button = buttonsById.get(next.file_id);
                        button?.click();
                        keepVisibleInExplorer(button);
                        announce('Revisión del archivo preparada. Continuamos con el siguiente documento.', 'info');
                        return;
                    }
                    renderReviewCenter();
                    if (observationPanel) observationPanel.scrollTop = 0;
                    announce('Revisión preparada. Ya puedes confirmar el lote completo.', 'success');
                },
            });
            return;
        }

        const error = validateReady(fileId);
        if (error) {
            reviewError = error;
            renderReviewCenter();
            return;
        }

        takeCompletedSnapshot(fileId);
        completedFileIds.add(fileId);
        editingCompletedFileId = null;
        reviewError = '';
        editorState = null;
        saveReviewDraft();
        updateFileIndicators();

        const next = nextPendingAfter(fileId);
        if (next) {
            const button = buttonsById.get(next.file_id);
            button?.click();
            keepVisibleInExplorer(button);
            announce('Revisión del archivo preparada. Continuamos con el siguiente documento.', 'info');
            return;
        }
        renderReviewCenter();
        if (observationPanel) observationPanel.scrollTop = 0;
        manager.querySelector('[data-sw-mobile-tab="observations"]')?.click();
        announce('Revisión preparada. Ya puedes confirmar el lote completo.', 'success');
    };

    const createReviewActions = (file) => {
        const draft = draftFor(file.file_id);
        const section = document.createElement('section');
        section.className = 'sw-review-actions';
        const showDecisions = Boolean(draft?.observations.length || draft?.decisionSource === 'manual');
        if (showDecisions) {
            const selectedStatus = draft?.status || '';
            const decisions = document.createElement('div');
            decisions.className = 'sw-review-decision-grid';
            decisions.innerHTML = `
                <button type="button" data-decision="approved" aria-pressed="${selectedStatus === 'approved'}"><i class="fa-solid fa-check" aria-hidden="true"></i>Aprobar</button>
                <button type="button" data-decision="corrections_requested" aria-pressed="${selectedStatus === 'corrections_requested'}"><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>Requiere correcciones</button>`;
            decisions.querySelectorAll('[data-decision]').forEach((button) => {
                button.disabled = isSubmitting;
                button.addEventListener('click', () => selectDecision(file.file_id, button.dataset.decision));
            });
            section.append(decisions);
        }
        const general = document.createElement('button');
        general.type = 'button';
        general.className = 'sw-review-add-general';
        general.disabled = isSubmitting;
        if (activeInternalZipEntry && activeInternalZipEntry.parentFileId === file.file_id) {
            general.innerHTML = '<i class="fa-solid fa-plus" aria-hidden="true"></i> Observacion en este archivo';
            general.addEventListener('click', () => openEditor('general', file.file_id, {
                locationReference: buildLocationReference()
            }));
        } else {
            general.innerHTML = '<i class="fa-solid fa-plus" aria-hidden="true"></i> Observacion general';
            general.addEventListener('click', () => openEditor('general', file.file_id));
        }
        section.append(general);
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
        renderHighlights();
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
            observationPanel.append(createProjectGeneralSection());
            if (editorState?.fileId === 0) observationPanel.append(createEditor());
            return;
        }
        if (file.status === 'approved') {
            const approved = document.createElement('div');
            approved.className = 'sw-review-persisted-approved';
            approved.innerHTML = '<i class="fa-solid fa-circle-check" aria-hidden="true"></i><div><strong>Documento aprobado</strong><span>Este checksum ya fue aprobado y no requiere una nueva decision.</span></div>';
            observationPanel.append(approved, createExistingSection(file));
            return;
        }

        const isCompletedReadOnly = completedFileIds.has(file.file_id) && editingCompletedFileId !== file.file_id;
        if (isCompletedReadOnly) {
            const draft = draftFor(file.file_id);
            const isApproved = draft?.status === 'approved';
            const isCorrections = draft?.status === 'corrections_requested';

            const banner = document.createElement('div');
            banner.className = `sw-review-completed-banner ${isApproved ? 'is-approved' : (isCorrections ? 'is-corrections' : '')}`;

            const header = document.createElement('div');
            header.className = 'sw-review-completed-header';
            const badge = document.createElement('span');
            badge.className = 'sw-review-completed-badge';
            badge.innerHTML = `<i class="fa-solid ${isApproved ? 'fa-circle-check' : 'fa-triangle-exclamation'}" aria-hidden="true"></i> ${isApproved ? 'Revisión finalizada: Aprobado' : 'Revisión finalizada: Requiere correcciones'}`;
            header.append(badge);

            const message = document.createElement('p');
            message.className = 'sw-review-completed-message';
            message.textContent = isApproved
                ? 'Has finalizado la revisión de este documento como Aprobado. Para realizar cambios, activa el modo de edición.'
                : 'Has finalizado la revisión de este documento solicitando correcciones. Para realizar cambios, activa el modo de edición.';

            const editBtn = document.createElement('button');
            editBtn.type = 'button';
            editBtn.className = 'sw-review-edit-completed-btn';
            editBtn.innerHTML = '<i class="fa-solid fa-pen" aria-hidden="true"></i> <span>Editar revisión</span>';
            editBtn.addEventListener('click', () => startEditingCompletedReview(file.file_id));

            banner.append(header, message, editBtn);
            observationPanel.append(banner, createExistingSection(file), createDraftSection(file));
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
        const isEditingActive = editingCompletedFileId === file.file_id;
        const footerText = isSubmitting
            ? 'Guardando revision...'
            : (isEditingActive && hasRealChanges(file.file_id) ? 'Edición en progreso: guarda los cambios' : (complete ? 'Revisado en esta sesión' : 'Marca este documento como listo'));

        footer.innerHTML = `<span>${footerText}</span><button type="button"><i class="fa-solid fa-check" aria-hidden="true"></i> ${isSubmitting ? 'Guardando revision...' : 'Listo'}</button>`;
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

    let isRenderingHighlights = false;

    const withScrollPreserved = (fn) => {
        const containers = [
            previewStage,
            previewStage?.parentElement,
            explorerPanel,
            manager,
            document.documentElement,
            document.body,
        ].filter((el) => el && el.nodeType === Node.ELEMENT_NODE);

        const savedScrolls = containers.map((el) => ({
            el,
            top: el.scrollTop || 0,
            left: el.scrollLeft || 0,
        }));
        const winX = window.scrollX || 0;
        const winY = window.scrollY || 0;

        try {
            fn();
        } finally {
            savedScrolls.forEach(({ el, top, left }) => {
                if (el.scrollTop !== top) el.scrollTop = top;
                if (el.scrollLeft !== left) el.scrollLeft = left;
            });
            if (window.scrollY !== winY || window.scrollX !== winX) {
                window.scrollTo(winX, winY);
            }
            requestAnimationFrame(() => {
                savedScrolls.forEach(({ el, top, left }) => {
                    if (el.scrollTop !== top) el.scrollTop = top;
                    if (el.scrollLeft !== left) el.scrollLeft = left;
                });
                if (window.scrollY !== winY || window.scrollX !== winX) {
                    window.scrollTo(winX, winY);
                }
            });
        }
    };

    let activeNotePopover = null;

    const removeActiveNotePopover = () => {
        if (activeNotePopover) {
            activeNotePopover.remove();
            activeNotePopover = null;
        }
        previewStage?.querySelectorAll('.sw-review-highlight-overlay.is-active').forEach((el) => el.classList.remove('is-active'));
    };

    const truncateText = (text, maxLength = 100) => {
        const str = String(text || '').trim();
        if (str.length <= maxLength) return { truncated: str, isTruncated: false };
        const cut = str.slice(0, maxLength);
        const lastSpace = cut.lastIndexOf(' ');
        const safeCut = lastSpace > maxLength * 0.6 ? cut.slice(0, lastSpace) : cut;
        return { truncated: `${safeCut}...`, isTruncated: true };
    };

    const formatCompactLocation = (locationRef) => {
        if (!locationRef) return '';
        const str = String(locationRef).trim();
        const zipArrow = ' → ';
        if (str.includes(zipArrow)) {
            const parts = str.split(zipArrow);
            return parts[parts.length - 1];
        }
        return str;
    };

    const positionPopoverElement = (popoverEl, rangeRect) => {
        if (!popoverEl || !previewStage || !floatingLayer) return;
        const viewportPadding = 12;
        const stageRect = previewStage.getBoundingClientRect();
        const layerRect = floatingLayer.getBoundingClientRect();
        const popoverRect = popoverEl.getBoundingClientRect();
        const minLeft = Math.max(viewportPadding, stageRect.left + viewportPadding);
        const maxLeft = Math.max(minLeft, Math.min(window.innerWidth - popoverRect.width - viewportPadding, stageRect.right - popoverRect.width - viewportPadding));
        let left = rangeRect.left + (rangeRect.width - popoverRect.width) / 2;
        left = Math.min(maxLeft, Math.max(minLeft, left));
        let top = rangeRect.top - popoverRect.height - 10;
        const minTop = Math.max(viewportPadding, stageRect.top + viewportPadding);
        if (top < minTop) {
            top = rangeRect.bottom + 10;
        }
        top = Math.min(window.innerHeight - popoverRect.height - viewportPadding, Math.max(minTop, top));
        popoverEl.style.left = `${Math.round(left - layerRect.left)}px`;
        popoverEl.style.top = `${Math.round(top - layerRect.top)}px`;
    };

    const showHighlightNote = (highlightEl, obs, index, fileId, obsNumber = null, colorClass = '') => {
        removeActiveNotePopover();
        removeSelectionPopover(false);

        highlightEl.classList.add('is-active');

        const note = document.createElement('div');
        note.className = `sw-review-note-popover ${colorClass}`;
        note.setAttribute('role', 'dialog');
        note.setAttribute('aria-label', 'Observación sobre el documento');

        const compactLoc = formatCompactLocation(obs.location_reference);
        const headerBadgeHtml = obsNumber
            ? `<span class="sw-review-card-number-badge ${colorClass}">${obsNumber}</span>`
            : '<i class="fa-solid fa-comment" aria-hidden="true"></i>';

        note.innerHTML = `
            <div class="sw-review-note-header">
                <span>${headerBadgeHtml} Observación</span>
                <button type="button" class="sw-review-note-close" aria-label="Cerrar nota"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
            </div>
            <p class="sw-review-note-body">${escapeHtml(obs.body)}</p>
            <div class="sw-review-note-footer">
                <span class="sw-review-note-location">${escapeHtml(compactLoc || 'Página 1')}</span>
                <button type="button" class="sw-review-note-link">Ver en observaciones</button>
            </div>
        `;

        note.addEventListener('mousedown', (e) => e.stopPropagation());

        note.querySelector('.sw-review-note-close').addEventListener('click', () => {
            removeActiveNotePopover();
        });

        note.querySelector('.sw-review-note-link').addEventListener('click', () => {
            removeActiveNotePopover();
            const cards = observationPanel?.querySelectorAll('.sw-review-observation-card');
            if (cards && cards[index]) {
                cards[index].scrollIntoView({ behavior: 'smooth', block: 'center' });
                cards[index].classList.add('is-highlighted-temp');
                setTimeout(() => cards[index]?.classList.remove('is-highlighted-temp'), 1500);
            }
        });

        activeNotePopover = note;
        if (!floatingLayer) return;
        floatingLayer.append(note);

        const highlightRect = highlightEl.getBoundingClientRect();
        positionPopoverElement(note, highlightRect);
    };

    const escapeHtml = (str) => String(str || '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');

    const getValidContextualRects = (meta) => {
        if (!meta || !meta.page_number || !Array.isArray(meta.relative_rects)) return [];
        return meta.relative_rects.filter(
            (r) => r && Number.isFinite(Number(r.left)) && Number.isFinite(Number(r.top)) && Number.isFinite(Number(r.width)) && Number.isFinite(Number(r.height)) && Number(r.width) > 0 && Number(r.height) > 0
        );
    };

    const getContextualObservationMap = (fileId) => {
        const draft = draftFor(fileId);
        const metas = metadataFor(fileId);
        const observations = draft?.observations || [];
        const map = new Map();
        let count = 0;

        observations.forEach((obs, index) => {
            const meta = metas[index];
            const validRects = getValidContextualRects(meta);
            const hasRects = validRects.length > 0;
            const isContextual = Boolean(hasRects || meta?.selected_text);

            if (isContextual) {
                count++;
                const colorIndex = ((count - 1) % 5) + 1;
                map.set(index, {
                    obsNumber: count,
                    colorClass: `sw-review-color-${colorIndex}`,
                    hasRects,
                });
            }
        });

        return map;
    };

    const renderHighlights = () => {
        if (!previewStage || isRenderingHighlights) return;
        isRenderingHighlights = true;
        try {
            withScrollPreserved(() => {
                previewStage.querySelectorAll('.sw-review-highlight-overlay, .sw-review-highlight-badge').forEach((el) => el.remove());

                if (!activeFileId) return;
                const metas = metadataFor(activeFileId);
                const draft = draftFor(activeFileId);
                const observations = draft?.observations || [];
                const contextualMap = getContextualObservationMap(activeFileId);
                const usedTopsByPage = {};

                observations.forEach((obs, index) => {
                    const meta = metas[index];
                    const validRects = getValidContextualRects(meta);
                    if (!validRects.length) return;

                    if (activeInternalZipEntry && meta.entry_name && meta.entry_name !== activeInternalZipEntry.entryName) {
                        return;
                    }

                    const contextualInfo = contextualMap.get(index);
                    const obsNumber = contextualInfo?.obsNumber || (index + 1);
                    const colorClass = contextualInfo?.colorClass || 'sw-review-color-1';

                    const pageEl = previewStage.querySelector(`[data-poc-page="${meta.page_number}"]`);
                    if (!pageEl) return;

                    // Overlays sobre todas las rects seleccionadas
                    validRects.forEach((rect) => {
                        const highlight = document.createElement('span');
                        highlight.className = `sw-review-highlight-overlay ${colorClass}`;
                        highlight.dataset.observationIndex = String(index);
                        highlight.title = `Observación [${obsNumber}]: ${obs.body}`;

                        Object.assign(highlight.style, {
                            position: 'absolute',
                            left: `${(rect.left * 100).toFixed(3)}%`,
                            top: `${(rect.top * 100).toFixed(3)}%`,
                            width: `${(rect.width * 100).toFixed(3)}%`,
                            height: `${(rect.height * 100).toFixed(3)}%`,
                            pointerEvents: 'auto',
                            cursor: 'pointer',
                            zIndex: '10',
                        });

                        highlight.addEventListener('click', (e) => {
                            e.stopPropagation();
                            showHighlightNote(highlight, obs, index, activeFileId, obsNumber, colorClass);
                        });

                        pageEl.append(highlight);
                    });

                    // UN ÚNICO BADGE AL MARGEN REAL DE LA PÁGINA (ALINEADO VERTICALMENTE CON LA PRIMERA LÍNEA)
                    const firstRect = validRects[0];
                    if (firstRect) {
                        const badge = document.createElement('span');
                        badge.className = `sw-review-highlight-badge ${colorClass}`;
                        badge.textContent = String(obsNumber);
                        badge.title = `Observación [${obsNumber}]: ${obs.body}`;

                        // El badge se fija al margen izquierdo de la página para no tapar texto ni celdas de tablas
                        const finalLeftCss = `calc(0% - 24px)`;

                        // Evitar solapamiento vertical entre badges en la misma página
                        let badgeTop = firstRect.top * 100;
                        const pageKey = String(meta.page_number);
                        usedTopsByPage[pageKey] = usedTopsByPage[pageKey] || [];
                        while (usedTopsByPage[pageKey].some((t) => Math.abs(t - badgeTop) < 2.5)) {
                            badgeTop += 2.5;
                        }
                        usedTopsByPage[pageKey].push(badgeTop);

                        Object.assign(badge.style, {
                            left: finalLeftCss,
                            top: `calc(${badgeTop.toFixed(3)}% - 2px)`,
                        });

                        badge.addEventListener('click', (e) => {
                            e.stopPropagation();
                            const firstOverlay = pageEl.querySelector(`[data-observation-index="${index}"]`);
                            showHighlightNote(firstOverlay || badge, obs, index, activeFileId, obsNumber, colorClass);
                        });

                        pageEl.append(badge);
                    }
                });
            });
        } finally {
            isRenderingHighlights = false;
        }
    };

    window.addEventListener('resize', () => {
        if (selectionPopover && selectionState?.rangeRect) positionPopover(selectionState.rangeRect);
        renderHighlights();
    });
    window.addEventListener('beforeunload', beforeUnloadHandler);

    const positionPopover = (rangeRect) => {
        positionPopoverElement(selectionPopover, rangeRect);
    };

    const inspectTextSelection = () => {
        if (isSubmitting || !reviewIsAvailable || filesById.get(activeFileId)?.status === 'approved') {
            removeSelectionPopover(false);
            return;
        }

        if (completedFileIds.has(activeFileId) && editingCompletedFileId !== activeFileId) {
            removeSelectionPopover(false);
            return;
        }

        const selection = window.getSelection();
        if (!selection || selection.isCollapsed || selection.rangeCount !== 1) {
            if (selectionPopover && selectionPopover.contains(document.activeElement)) {
                return;
            }
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
            if (selectionPopover && selectionPopover.contains(document.activeElement)) {
                return;
            }
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

        const pageRect = page.getBoundingClientRect();
        const relativeRects = rects.map((r) => ({
            left: (r.left - pageRect.left) / pageRect.width,
            top: (r.top - pageRect.top) / pageRect.height,
            width: r.width / pageRect.width,
            height: r.height / pageRect.height,
        }));

        const selectedTextSnippet = text.length > 500 ? `${text.slice(0, 497)}...` : text;
        const locationRef = buildLocationReference(pageNumber);

        removeSelectionPopover(false);

        selectionState = {
            fileId: activeFileId,
            selectedText: selectedTextSnippet,
            pageNumber,
            locationReference: locationRef,
            rangeRect: anchorRect,
            relativeRects,
        };

        const editor = document.createElement('div');
        editor.className = 'sw-review-selection-editor';
        editor.setAttribute('role', 'dialog');
        editor.setAttribute('aria-label', 'Agregar observación contextual');
        editor.style.visibility = 'hidden';
        editor.style.left = '-9999px';
        editor.style.top = '-9999px';

        editor.innerHTML = `
            <div class="sw-review-selection-quote">
                <span>Texto seleccionado</span>
                <blockquote>"${escapeHtml(selectedTextSnippet)}"</blockquote>
                <small>${escapeHtml(locationRef)}</small>
            </div>
            <div class="sw-review-selection-body">
                <textarea rows="3" placeholder="Escribe tu comentario sobre este texto..." minlength="${limits.bodyMin}" maxlength="${limits.bodyMax}"></textarea>
                <small class="sw-review-selection-count">0/${limits.bodyMax}</small>
            </div>
            <p class="sw-review-selection-error" role="alert" hidden></p>
            <div class="sw-review-selection-actions">
                <button type="button" class="sw-review-selection-cancel">Cancelar</button>
                <button type="button" class="sw-review-selection-submit">Agregar</button>
            </div>
        `;

        editor.addEventListener('mousedown', (e) => e.stopPropagation());

        const textarea = editor.querySelector('textarea');
        const countEl = editor.querySelector('.sw-review-selection-count');
        const errorEl = editor.querySelector('.sw-review-selection-error');
        const cancelBtn = editor.querySelector('.sw-review-selection-cancel');
        const submitBtn = editor.querySelector('.sw-review-selection-submit');

        textarea.addEventListener('input', () => {
            const val = textarea.value.trim();
            countEl.textContent = `${textarea.value.length}/${limits.bodyMax}`;
            if (val.length >= limits.bodyMin && val.length <= limits.bodyMax) {
                errorEl.hidden = true;
                errorEl.textContent = '';
            }
        });

        textarea.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                removeSelectionPopover(true);
            } else if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) {
                e.preventDefault();
                submitBtn.click();
            }
        });

        cancelBtn.addEventListener('click', () => {
            removeSelectionPopover(true);
        });

        submitBtn.addEventListener('click', () => {
            const body = textarea.value.trim();
            if (body.length < limits.bodyMin || body.length > limits.bodyMax) {
                errorEl.textContent = `Escribe un comentario de entre ${limits.bodyMin} y ${limits.bodyMax} caracteres.`;
                errorEl.hidden = false;
                return;
            }

            const draft = draftFor(activeFileId, 'corrections_requested');
            if (!draft) return;

            const observation = {
                body,
                category: 'General',
                location_reference: locationRef,
            };

            const meta = {
                selected_text: selectedTextSnippet,
                page_number: pageNumber,
                entry_name: activeInternalZipEntry ? activeInternalZipEntry.entryName : null,
                relative_rects: relativeRects,
            };

            draft.observations.push(observation);
            const metas = metadataFor(activeFileId);
            metas.push(meta);

            draft.status = 'corrections_requested';
            if (draft.decisionSource !== 'manual') draft.decisionSource = 'auto';

            if (editingCompletedFileId === activeFileId && hasRealChanges(activeFileId)) {
                completedFileIds.delete(activeFileId);
            }

            removeSelectionPopover(true);
            saveReviewDraft();
            renderReviewCenter();
            announce('Observación agregada al borrador.', 'info');
        });

        selectionPopover = editor;
        if (!floatingLayer) return;
        floatingLayer.append(editor);
        positionPopover(anchorRect);
        editor.style.visibility = 'visible';
        if (typeof textarea.focus === 'function') textarea.focus({ preventScroll: true });
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
        clearLocalStorageDraft();
        clearDraftState();
        isSubmitting = false;
        setFlashToast(payload.message || result.message || 'Revisión confirmada correctamente.', 'success');
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

        const proceedToNewFile = () => {
            activeFileId = fileId;
            editingCompletedFileId = null;
            activeInternalZipEntry = null;
            editorState = null;
            reviewError = '';
            removeSelectionPopover(true);
            renderReviewCenter();
            if (observationPanel) observationPanel.scrollTop = 0;
        };

        if (activeFileId === fileId) {
            if (completedFileIds.has(activeFileId) && editingCompletedFileId === activeFileId && hasRealChanges(activeFileId)) {
                discardCompletedEdits(activeFileId, () => deselectCurrentFile());
                return;
            }
            if (isFormDirty()) {
                if (!window.confirm('Tienes cambios no guardados en la observación actual. ¿Deseas descartarlos y cerrar el archivo?')) {
                    return;
                }
            }
            deselectCurrentFile();
            return;
        }

        if (activeFileId > 0 && completedFileIds.has(activeFileId) && editingCompletedFileId === activeFileId && hasRealChanges(activeFileId)) {
            discardCompletedEdits(activeFileId, proceedToNewFile);
            return;
        }

        if (activeFileId > 0 && isFormDirty()) {
            if (!window.confirm('Tienes cambios no guardados en la observación actual. ¿Deseas descartarlos para cambiar de archivo?')) {
                return;
            }
        }

        proceedToNewFile();
    }));

    document.addEventListener('workspace:zip-entry-opened', (event) => {
        const detail = event.detail;
        if (!detail || !detail.parentFileId) return;

        if (activeFileId === detail.parentFileId && activeInternalZipEntry?.entryPath === detail.entryPath) {
            activeInternalZipEntry = null;
            renderReviewCenter();
            return;
        }

        activeFileId = Number(detail.parentFileId);
        activeInternalZipEntry = {
            parentFileId: Number(detail.parentFileId),
            parentFileName: String(detail.parentFileName || ''),
            parentChecksum: String(detail.parentChecksum || ''),
            entryPath: String(detail.entryPath || ''),
            entryName: String(detail.entryName || ''),
            extension: String(detail.extension || ''),
        };
        editorState = null;
        reviewError = '';
        removeSelectionPopover(true);
        renderReviewCenter();
        if (observationPanel) observationPanel.scrollTop = 0;
    });

    document.addEventListener('workspace:zip-entry-closed', () => {
        activeInternalZipEntry = null;
        renderReviewCenter();
    });

    previewStage?.addEventListener('mouseup', () => setTimeout(inspectTextSelection, 0));
    previewStage?.addEventListener('keyup', () => setTimeout(inspectTextSelection, 0));
    previewStage?.addEventListener('scroll', () => removeSelectionPopover(false), { passive: true });
    workspace.addEventListener('workspace:document-preview-rendered', () => renderHighlights());
    manager.querySelector('[data-sw-viewer-zoom]')?.addEventListener('click', () => removeSelectionPopover(true), true);

    document.addEventListener('selectionchange', () => {
        // No remover selectionPopover automáticamente durante selectionchange
        // ya que el foco en el textarea colapsa la selección del documento de forma nativa.
    });

    document.addEventListener('pointerdown', (event) => {
        if (activeNotePopover && !activeNotePopover.contains(event.target) && !event.target.closest('.sw-review-highlight-overlay')) {
            removeActiveNotePopover();
        }
        if (selectionPopover && !selectionPopover.contains(event.target) && !event.target.closest('.sw-poc-text-layer')) {
            removeSelectionPopover(false);
        }
        if (confirmModalOpen && confirmModal && event.target === confirmModal) {
            closeConfirmModal(false);
        }
        if (decisionModal && event.target === decisionModal) closeDecisionModal();
    });

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') return;
        if (activeNotePopover) {
            removeActiveNotePopover();
            return;
        }
        if (confirmModalOpen) {
            closeConfirmModal(false);
            return;
        }
        if (decisionModal && !decisionModal.hidden) {
            closeDecisionModal();
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
        renderHighlights();
    });
    window.addEventListener('beforeunload', beforeUnloadHandler);

    confirmModal?.querySelectorAll('[data-sw-review-confirm-close],[data-sw-review-confirm-cancel]').forEach((button) => {
        button.addEventListener('click', () => closeConfirmModal(false));
    });
    confirmSubmitButton?.addEventListener('click', () => submitReview());
    decisionModal?.querySelectorAll('[data-sw-review-decision-cancel]').forEach((button) => {
        button.addEventListener('click', closeDecisionModal);
    });
    decisionModalConfirm?.addEventListener('click', () => {
        if (!decisionModalAction) return;
        approveDecision(decisionModalAction.fileId, decisionModalAction.finish);
    });

    restoreReviewDraft();
    updateFileIndicators();
    renderReviewCenter();
    refreshConfirmModal();
});
