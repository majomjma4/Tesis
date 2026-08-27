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
        status: String(file.status || file.document_status || 'development'),
        document_status: String(file.document_status || file.status || 'development'),
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
    const obsFooter = manager.querySelector('.sw-obs-footer') || observationPanel?.parentElement?.querySelector('.sw-obs-footer');
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
    const confirmLock = confirmModal?.querySelector('[data-sw-review-confirm-lock]');
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
    let decisionModalAction = null;

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

    const clamp01 = (value) => Math.max(0, Math.min(1, Number(value) || 0));

    const normalizeRect01 = (rect) => {
        const rawLeft = Number(rect?.left) || 0;
        const rawTop = Number(rect?.top) || 0;
        const rawWidth = Number(rect?.width) || 0;
        const rawHeight = Number(rect?.height) || 0;

        const left = clamp01(rawLeft);
        const top = clamp01(rawTop);
        const width = Math.max(0.0001, Math.min(clamp01(rawWidth), 1 - left));
        const height = Math.max(0.0001, Math.min(clamp01(rawHeight), 1 - top));
        return { left, top, width, height };
    };

    const compactRelativeRects = (rects) => {
        if (!Array.isArray(rects) || !rects.length) return [];

        const valid = rects
            .map((r) => normalizeRect01(r))
            .filter((r) => r.width > 0 && r.height > 0);

        if (!valid.length) return [];

        valid.sort((a, b) => (a.top !== b.top ? a.top - b.top : a.left - b.left));

        const lines = [];
        valid.forEach((rect) => {
            if (lines.length === 0) {
                lines.push([rect]);
                return;
            }

            const currentLine = lines[lines.length - 1];
            const lineTop = currentLine[0].top;
            const lineHeight = currentLine.reduce((max, r) => Math.max(max, r.height), currentLine[0].height);

            const topDiff = Math.abs(rect.top - lineTop);
            const verticalOverlap = Math.min(rect.top + rect.height, lineTop + lineHeight) - Math.max(rect.top, lineTop);
            const isSameLine = topDiff < 0.015 || (verticalOverlap > 0.3 * Math.min(rect.height, lineHeight));

            if (isSameLine) {
                currentLine.push(rect);
            } else {
                lines.push([rect]);
            }
        });

        let compacted = lines.map((lineRects) => {
            let minLeft = lineRects[0].left;
            let minTop = lineRects[0].top;
            let maxRight = lineRects[0].left + lineRects[0].width;
            let maxBottom = lineRects[0].top + lineRects[0].height;

            for (let i = 1; i < lineRects.length; i++) {
                const r = lineRects[i];
                if (r.left < minLeft) minLeft = r.left;
                if (r.top < minTop) minTop = r.top;
                if (r.left + r.width > maxRight) maxRight = r.left + r.width;
                if (r.top + r.height > maxBottom) maxBottom = r.top + r.height;
            }

            return normalizeRect01({
                left: minLeft,
                top: minTop,
                width: maxRight - minLeft,
                height: maxBottom - minTop,
            });
        }).filter((r) => r.width > 0 && r.height > 0);

        while (compacted.length > 50) {
            const nextPass = [];
            for (let i = 0; i < compacted.length; i += 2) {
                if (i + 1 < compacted.length) {
                    const r1 = compacted[i];
                    const r2 = compacted[i + 1];
                    const minLeft = Math.min(r1.left, r2.left);
                    const minTop = Math.min(r1.top, r2.top);
                    const maxRight = Math.max(r1.left + r1.width, r2.left + r2.width);
                    const maxBottom = Math.max(r1.top + r1.height, r2.top + r2.height);
                    nextPass.push(normalizeRect01({
                        left: minLeft,
                        top: minTop,
                        width: maxRight - minLeft,
                        height: maxBottom - minTop,
                    }));
                } else {
                    nextPass.push(compacted[i]);
                }
            }
            compacted = nextPass;
        }

        return compacted;
    };

    const normalizeInternalEntry = (value) => {
        if (typeof value !== 'string') return null;
        const clean = value.replace(/^[/\\]+/, '').replace(/\\/g, '/').trim();
        return clean !== '' ? clean : null;
    };

    const resolveLegacyPageNumber = (meta, observation) => {
        const current = Number(meta?.page_number || 0);
        if (Number.isInteger(current) && current >= 1) {
            return current;
        }

        const selectedText = String(meta?.selected_text || '').trim();
        const hasRects = Array.isArray(meta?.relative_rects) && meta.relative_rects.length > 0;
        const isContextual = selectedText !== '' || hasRects;
        if (!isContextual) {
            return null;
        }

        const reference = String(observation?.location_reference || '');
        const match = reference.match(/pagina\s*(\d+)/i);
        if (!match) {
            return null;
        }

        const derived = Number.parseInt(match[1], 10);
        return Number.isInteger(derived) && derived >= 1 ? derived : null;
    };

    const announce = (message, kind = 'info') => {
        window.AppToast?.show(message, kind);
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
    const observationsForFile = (fileId) => {
        const file = filesById.get(fileId);
        const expectedChecksum = file ? String(file.expected_checksum || '').toLowerCase().trim() : '';
        return existingObservations.filter((item) => {
            if (Number(item.file_id || 0) !== fileId) return false;
            if (!expectedChecksum) return true;
            const obsChecksum = String(item.file_checksum_sha256 || '').toLowerCase().trim();
            return obsChecksum === expectedChecksum;
        });
    };
    const resolveObservationDisplayType = (item, file = null, meta = null) => {
        let anchor = item?.selection_anchor || meta;
        if (typeof anchor === 'string') {
            try { anchor = JSON.parse(anchor); } catch (e) { anchor = null; }
        }

        const pageNum = Number(anchor?.page_number || meta?.page_number || 0);
        const selectedText = String(anchor?.selected_text || meta?.selected_text || '').trim();
        const hasRects = (Array.isArray(anchor?.relative_rects) && anchor.relative_rects.length > 0) ||
                         (Array.isArray(meta?.relative_rects) && meta.relative_rects.length > 0);

        const isContextual = pageNum >= 1 && (selectedText !== '' || hasRects);
        if (isContextual) {
            return {
                typeKey: 'contextual',
                typeLabel: 'Sobre el texto',
                badgeClass: 'is-contextual',
                iconClass: 'fa-solid fa-highlighter',
            };
        }

        const locRef = String(item?.location_reference || meta?.entry_name || meta?.internal_entry || '');
        const isZipEntry = locRef.includes('→') || (file && (file.extension === 'zip' || (file.name && file.name.endsWith('.zip'))) && locRef !== '');

        if (isZipEntry) {
            return {
                typeKey: 'zip',
                typeLabel: 'General del ZIP',
                badgeClass: 'is-zip',
                iconClass: 'fa-solid fa-file-zipper',
            };
        }

        const fileId = Number(item?.file_id || file?.file_id || file?.id || 0);
        if (fileId > 0) {
            return {
                typeKey: 'file',
                typeLabel: 'General del archivo',
                badgeClass: 'is-general',
                iconClass: 'fa-solid fa-comment-dots',
            };
        }

        return {
            typeKey: 'project',
            typeLabel: 'General del proyecto',
            badgeClass: 'is-project',
            iconClass: 'fa-solid fa-circle-info',
        };
    };
    const decisionStatusLabel = (status) => status === 'approved' ? 'Aprobado' : (status === 'corrections_requested' ? 'Requiere correcciones' : 'Sin decision');
    const decisionIconClass = (status) => status === 'approved' ? 'fa-circle-check' : (status === 'corrections_requested' ? 'fa-triangle-exclamation' : 'fa-circle');

    const ensureCardNavStyles = () => {
        const styleId = 'sw-review-card-nav-styles';
        if (!document.getElementById(styleId)) {
            const styleNode = document.createElement('style');
            styleNode.id = styleId;
            styleNode.textContent = `
                @keyframes swHighlightPulse {
                    0% { transform: scale(1); box-shadow: 0 0 0 0 rgba(37, 99, 235, 0.8); outline: 2px solid #2563eb; }
                    50% { transform: scale(1.05); box-shadow: 0 0 0 8px rgba(37, 99, 235, 0); outline: 3px solid #1d4ed8; }
                    100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(37, 99, 235, 0); outline: 2px solid #2563eb; }
                }
                .sw-highlight-pulse {
                    animation: swHighlightPulse 0.8s ease-in-out 2 !important;
                    z-index: 100 !important;
                }
                .sw-review-observation-card.is-active-card {
                    border-left: 3px solid #2563eb !important;
                    background-color: #f8fafc !important;
                    box-shadow: 0 1px 3px rgba(0,0,0,0.08) !important;
                }
                .sw-obs-status-badge {
                    font-size: 0.72rem;
                    padding: 2px 7px;
                    border-radius: 4px;
                    font-weight: 600;
                    background: #f1f5f9;
                    color: #475569;
                }
                .sw-obs-status-badge.is-sent {
                    background: #e0f2fe;
                    color: #0369a1;
                }
                .sw-obs-status-badge.is-previous {
                    background: #fef3c7;
                    color: #b45309;
                }
                .sw-review-highlight-overlay {
                    background: var(--sw-color-bg, rgba(250, 204, 21, 0.16));
                    border: 1px solid var(--sw-color-border, rgba(202, 138, 4, 0.75));
                    border-radius: 3px;
                    box-sizing: border-box;
                    transition: border-color 0.2s ease, box-shadow 0.2s ease, background-color 0.2s ease;
                }
                .sw-review-highlight-overlay.sw-highlight-pulse {
                    border-width: 2px !important;
                    box-shadow: 0 0 12px rgba(37, 99, 235, 0.5) !important;
                }
            `;
            document.head.append(styleNode);
        }
    };
    ensureCardNavStyles();

    const navigateToContextualObservation = (fileId, pageNumber, obsIndex, cardElement) => {
        const allCards = observationPanel?.querySelectorAll('.sw-review-observation-card');
        allCards?.forEach((c) => c.classList.remove('is-active-card'));
        if (cardElement) {
            cardElement.classList.add('is-active-card');
        }

        const isViewerHidden = () => {
            if (!previewStage) return true;
            const rect = previewStage.getBoundingClientRect();
            return rect.width === 0 || rect.height === 0;
        };

        const executeScrollAndHighlight = () => {
            renderHighlights();
            const targetOverlays = previewStage?.querySelectorAll(`.sw-review-highlight-overlay[data-observation-index="${obsIndex}"]`);
            const guide = previewStage?.querySelector(`.sw-review-highlight-rail-guide[data-observation-index="${obsIndex}"]`);
            const targetPage = previewStage?.querySelector(`[data-poc-page="${pageNumber}"]`)
                || previewStage?.querySelector(`[data-page-number="${pageNumber}"]`);

            if (targetOverlays && targetOverlays.length > 0) {
                targetOverlays[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
                targetOverlays.forEach((el) => el.classList.add('sw-highlight-pulse'));
                guide?.classList.add('sw-highlight-pulse');
                setTimeout(() => {
                    targetOverlays.forEach((el) => el.classList.remove('sw-highlight-pulse'));
                    guide?.classList.remove('sw-highlight-pulse');
                }, 2000);
            } else if (targetPage) {
                targetPage.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        };

        const needsTabSwitch = isViewerHidden() || (activeFileId !== fileId && fileId > 0);

        if (needsTabSwitch) {
            const viewerTabBtn = manager?.querySelector('[data-sw-mobile-tab="viewer"]');
            viewerTabBtn?.click();

            if (fileId > 0 && activeFileId !== fileId) {
                selectFile(fileId);
            }

            let attempts = 0;
            const checkAndScroll = () => {
                attempts++;
                const pageEl = previewStage?.querySelector(`[data-poc-page="${pageNumber}"]`)
                    || previewStage?.querySelector(`[data-page-number="${pageNumber}"]`);
                if (pageEl || attempts > 25) {
                    executeScrollAndHighlight();
                } else {
                    requestAnimationFrame(checkAndScroll);
                }
            };
            requestAnimationFrame(checkAndScroll);
        } else {
            executeScrollAndHighlight();
        }
    };

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
                        const obsList = reviewDraft[fileId].observations || [];
                        observationMeta.set(fileId, metas.map((m, idx) => {
                            const obs = obsList[idx];
                            const pageNum = resolveLegacyPageNumber(m, obs);
                            return {
                                selected_text: String(m?.selected_text || ''),
                                page_number: pageNum,
                                entry_name: normalizeInternalEntry(m?.entry_name),
                                internal_entry: normalizeInternalEntry(m?.internal_entry),
                                relative_rects: Array.isArray(m?.relative_rects) ? compactRelativeRects(m.relative_rects) : [],
                            };
                        }));
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

    const populateObservationMetaFromDB = () => {
        existingObservations.forEach((item) => {
            const fileId = Number(item.file_id || 0);
            if (fileId <= 0) return;
            if (!observationMeta.has(fileId)) {
                observationMeta.set(fileId, []);
            }
            const metas = observationMeta.get(fileId);
            const obsForFile = observationsForFile(fileId);
            const index = obsForFile.indexOf(item);
            if (index < 0) return;

            let anchorObj = null;
            if (item.selection_anchor) {
                try {
                    anchorObj = typeof item.selection_anchor === 'string' ? JSON.parse(item.selection_anchor) : item.selection_anchor;
                } catch (e) {}
            }

            const pageNum = anchorObj?.page_number || resolveLegacyPageNumber(anchorObj, item);
            const selectedText = String(anchorObj?.selected_text || '').trim();
            let internalEntry = normalizeInternalEntry(anchorObj?.internal_entry || anchorObj?.entry_name);
            if (!internalEntry && item.location_reference && item.location_reference.includes('→')) {
                const parts = item.location_reference.split('→');
                if (parts.length > 1) {
                    internalEntry = normalizeInternalEntry(parts.slice(1).join('→').trim());
                }
            }
            const validRects = Array.isArray(anchorObj?.relative_rects) ? compactRelativeRects(anchorObj.relative_rects) : [];

            metas[index] = {
                selected_text: selectedText,
                page_number: pageNum,
                entry_name: internalEntry,
                internal_entry: internalEntry,
                relative_rects: validRects,
            };
        });
    };
    populateObservationMetaFromDB();

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
        const filesList = files;
        const completed = completedDrafts();
        const persistedApproved = filesList.filter((file) => !completedFileIds.has(file.file_id) && (file.status === 'approved' || file.document_status === 'approved')).length;
        const persistedCorrections = filesList.filter((file) => !completedFileIds.has(file.file_id) && (file.status === 'corrections_requested' || file.document_status === 'corrections_requested')).length;
        const draftApproved = completed.filter((file) => reviewDraft[file.file_id]?.status === 'approved').length;
        const draftCorrections = completed.filter((file) => reviewDraft[file.file_id]?.status === 'corrections_requested').length;

        const approved = persistedApproved + draftApproved;
        const corrections = persistedCorrections + draftCorrections;
        const reviewed = approved + corrections;

        return {
            total: filesList.length,
            reviewed,
            approved,
            corrections,
            pending: Math.max(0, filesList.length - reviewed),
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
        const file = filesById.get(fileId);
        const fileName = file ? file.name : `Archivo #${fileId}`;
        const metas = metadataFor(fileId);

        for (let i = 0; i < draft.observations.length; i++) {
            const item = draft.observations[i];
            const error = validateObservation(item);
            if (error) return error;

            const meta = metas[i];
            const selectedText = String(meta?.selected_text || '').trim();
            const hasRects = Array.isArray(meta?.relative_rects) && meta.relative_rects.length > 0;
            const hasContextualMeta = selectedText !== '' || hasRects;

            if (hasContextualMeta) {
                const pageNum = resolveLegacyPageNumber(meta, item);
                if (!pageNum || pageNum < 1) {
                    return `Una observación contextual perdió su página de ubicación. Revisa las observaciones del archivo "${fileName}" antes de terminar la revisión.`;
                }
                const validRects = Array.isArray(meta?.relative_rects)
                    ? compactRelativeRects(meta.relative_rects)
                    : [];
                if (!validRects.length) {
                    return `Una observación contextual perdió su posición de recuadro. Revisa las observaciones del archivo "${fileName}" antes de terminar la revisión.`;
                }
            }
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
                        const text = String(meta?.selected_text || '').trim();
                        const pageNum = resolveLegacyPageNumber(meta, observation);
                        if (!text || !pageNum || !Array.isArray(meta?.relative_rects) || !meta.relative_rects.length) return null;
                        const validRects = compactRelativeRects(meta.relative_rects);
                        if (!validRects.length) return null;
                        const entryValue = normalizeInternalEntry(meta.internal_entry || meta.entry_name);
                        return {
                            selected_text: text.slice(0, 500),
                            page_number: pageNum,
                            relative_rects: validRects,
                            internal_entry: entryValue,
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
            const fileStatus = file.status || file.document_status;
            let state = 'pending';
            let label = 'Pendiente de revision';
            let icon = 'fa-regular fa-circle';
            if (fileStatus === 'approved') {
                state = 'approved';
                label = 'Aprobado';
                icon = 'fa-solid fa-check';
            } else if (fileStatus === 'corrections_requested') {
                state = 'corrections';
                label = 'Requiere correcciones';
                icon = 'fa-solid fa-triangle-exclamation';
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
            button.classList.toggle('is-review-complete', complete || fileStatus === 'approved' || fileStatus === 'corrections_requested');
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

        if (!reviewIsAvailable) {
            section.innerHTML = `
                <div class="sw-review-progress-heading"><strong>Revisión finalizada</strong><span>${value.reviewed} de ${value.total} documentos</span></div>
                <div class="sw-review-progress-track" aria-hidden="true"><span style="width:${value.total ? Math.round((value.reviewed / value.total) * 100) : 0}%"></span></div>
                <div class="sw-review-progress-counts">
                    <span class="is-approved"><i class="fa-solid fa-check" aria-hidden="true"></i>${value.approved} aprobados</span>
                    <span class="is-corrections"><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>${value.corrections} con correcciones</span>
                    <span class="is-pending"><i class="fa-regular fa-circle" aria-hidden="true"></i>${value.pending} pendientes</span>
                </div>
                <div class="sw-review-read-only-banner" style="margin-top:10px;padding:8px 10px;background:rgba(234,179,8,0.1);border-radius:6px;font-size:0.8rem;color:var(--text-color, #334155);display:flex;align-items:center;gap:6px;">
                    <i class="fa-solid fa-lock" style="color:#d97706;" aria-hidden="true"></i>
                    <span>La revisión fue confirmada y se enviaron los resultados al estudiante.</span>
                </div>`;
            return section;
        }

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

    const createObservationCard = (file, item, index, anchorObj, isContextual, isZip, pageNum = null, selectedText = '') => {
        const card = document.createElement('article');
        card.className = 'sw-review-observation-card is-existing';
        card.dataset.obsIndex = String(index);

        const meta = document.createElement('div');
        meta.className = 'sw-review-card-meta';
        const category = document.createElement('strong');
        category.textContent = String(item.category || 'General');

        const state = document.createElement('span');
        const previousVersion = item.file_checksum_sha256 && String(item.file_checksum_sha256) !== file.expected_checksum;
        if (previousVersion) {
            state.textContent = 'Versión anterior';
            state.className = 'sw-obs-status-badge is-previous';
        } else if (item.status === 'addressed') {
            state.textContent = 'Atendida';
            state.className = 'sw-obs-status-badge';
        } else if (item.status === 'resolved') {
            state.textContent = 'Resuelta';
            state.className = 'sw-obs-status-badge';
        } else {
            state.textContent = 'Enviada';
            state.className = 'sw-obs-status-badge is-sent';
        }
        meta.append(category, state);

        let internalEntry = normalizeInternalEntry(anchorObj?.internal_entry || anchorObj?.entry_name);
        if (!internalEntry && item.location_reference && item.location_reference.includes('→')) {
            const parts = item.location_reference.split('→');
            if (parts.length > 1) {
                internalEntry = normalizeInternalEntry(parts.slice(1).join('→').trim());
            }
        }

        const locationBox = document.createElement('div');
        locationBox.className = 'sw-review-card-location';
        locationBox.style.fontSize = '0.78rem';
        locationBox.style.margin = '4px 0 8px 0';
        locationBox.style.padding = '4px 8px';
        locationBox.style.borderRadius = '4px';
        locationBox.style.background = 'rgba(241,245,249,0.85)';
        locationBox.style.border = '1px solid #e2e8f0';

        if (internalEntry) {
            locationBox.innerHTML = `<div style="display:flex;align-items:center;gap:6px;font-weight:600;color:#0284c7;margin-bottom:2px;"><i class="fa-solid fa-file-zipper"></i> ${isZip ? 'General del ZIP' : 'Observación en entrada interna'}</div><div style="font-size:0.75rem;color:#334155;"><strong>Archivo interno:</strong> ${escapeHtml(internalEntry)}</div>`;
        } else if (isContextual && pageNum) {
            const textSnippet = truncateText(selectedText, 140).truncated;
            locationBox.innerHTML = `<div style="display:flex;align-items:center;gap:6px;font-weight:600;color:#0f172a;margin-bottom:2px;"><i class="fa-solid fa-bookmark" style="color:#2563eb;"></i> Página ${pageNum}</div><div data-sw-review-card-quote style="font-style:italic;color:#475569;font-size:0.75rem;line-height:1.2;">“${escapeHtml(textSnippet)}”</div>`;
            card.dataset.pageNumber = String(pageNum);
            card.style.cursor = 'pointer';
            card.title = 'Haz clic para ubicar esta observación en el documento';
        } else if (isZip) {
            locationBox.innerHTML = `<i class="fa-solid fa-file-zipper" style="color:#0284c7;margin-right:4px;"></i> <span style="font-weight:600;color:#0369a1;">General del ZIP completo</span>`;
        } else {
            locationBox.innerHTML = `<i class="fa-solid fa-file-lines" style="color:#64748b;margin-right:4px;"></i> <span style="font-weight:500;color:#64748b;">General del archivo</span>`;
        }

        const body = document.createElement('p');
        body.className = 'sw-review-card-body';
        const bodySummary = truncateText(item.body, 140);
        body.textContent = bodySummary.truncated;

        card.append(meta, locationBox, body);

        if (bodySummary.isTruncated || (isContextual && selectedText && selectedText.length > 140)) {
            const toggleBtn = document.createElement('button');
            toggleBtn.type = 'button';
            toggleBtn.className = 'sw-review-toggle-more-btn';
            toggleBtn.innerHTML = '<i class="fa-solid fa-chevron-down" aria-hidden="true"></i> Ver más';
            toggleBtn.addEventListener('click', (event) => {
                event.preventDefault();
                event.stopPropagation();
                const expanded = card.dataset.expanded === 'true';
                card.dataset.expanded = expanded ? 'false' : 'true';
                body.textContent = expanded ? bodySummary.truncated : String(item.body || '');
                if (isContextual && selectedText) {
                    const quote = locationBox.querySelector('[data-sw-review-card-quote]');
                    if (quote) {
                        const quoteSummary = truncateText(selectedText, 140);
                        quote.textContent = `“${expanded ? quoteSummary.truncated : selectedText}”`;
                    }
                }
                toggleBtn.innerHTML = expanded
                    ? '<i class="fa-solid fa-chevron-down" aria-hidden="true"></i> Ver más'
                    : '<i class="fa-solid fa-chevron-up" aria-hidden="true"></i> Ocultar';
            });
            card.append(toggleBtn);
        }

        if (isContextual && pageNum) {
            card.addEventListener('click', () => {
                navigateToContextualObservation(file.file_id, pageNum, index, card);
            });
        }

        return card;
    };

    const createExistingSection = (file) => {
        const items = observationsForFile(file.file_id);

        if (!items.length) {
            if (!reviewIsAvailable) return null;
            const section = document.createElement('section');
            section.className = 'sw-review-observation-group is-existing';
            const empty = document.createElement('p');
            empty.className = 'sw-review-empty-inline';
            empty.textContent = 'Este documento no tiene observaciones registradas.';
            section.append(empty);
            return section;
        }

        const section = document.createElement('section');
        section.className = 'sw-review-observation-group is-existing';

        const generalItems = [];
        const contextualItems = [];

        items.forEach((item, originalIndex) => {
            let anchorObj = null;
            if (item.selection_anchor) {
                try {
                    anchorObj = typeof item.selection_anchor === 'string' ? JSON.parse(item.selection_anchor) : item.selection_anchor;
                } catch (e) {}
            }
            const pageNum = anchorObj?.page_number || resolveLegacyPageNumber(anchorObj, item);
            const selectedText = String(anchorObj?.selected_text || '').trim();
            const hasContextualAnchor = pageNum && (selectedText !== '' || (Array.isArray(anchorObj?.relative_rects) && anchorObj.relative_rects.length > 0));

            if (hasContextualAnchor) {
                contextualItems.push({ item, originalIndex, anchorObj, pageNum, selectedText });
            } else {
                generalItems.push({ item, originalIndex, anchorObj });
            }
        });

        const isZip = /\.zip$/i.test(String(file.name || ''));

        if (generalItems.length > 0) {
            const generalGroup = document.createElement('div');
            generalGroup.className = 'sw-review-subgroup';
            generalGroup.style.marginBottom = '1rem';

            const generalHeading = document.createElement('div');
            generalHeading.className = 'sw-review-group-heading';
            generalHeading.innerHTML = `<strong>Observaciones generales</strong><span>${generalItems.length}</span>`;
            generalGroup.append(generalHeading);

            const genList = document.createElement('div');
            genList.className = 'sw-review-card-list';
            generalItems.forEach(({ item, originalIndex, anchorObj }) => {
                const card = createObservationCard(file, item, originalIndex, anchorObj, false, isZip);
                genList.append(card);
            });
            generalGroup.append(genList);
            section.append(generalGroup);
        }

        if (contextualItems.length > 0) {
            const contextualGroup = document.createElement('div');
            contextualGroup.className = 'sw-review-subgroup';

            const contextualHeading = document.createElement('div');
            contextualHeading.className = 'sw-review-group-heading';
            contextualHeading.innerHTML = `<strong>Observaciones sobre el texto</strong><span>${contextualItems.length}</span>`;
            contextualGroup.append(contextualHeading);

            const ctxList = document.createElement('div');
            ctxList.className = 'sw-review-card-list';
            contextualItems.forEach(({ item, originalIndex, anchorObj, pageNum, selectedText }) => {
                const card = createObservationCard(file, item, originalIndex, anchorObj, true, isZip, pageNum, selectedText);
                ctxList.append(card);
            });
            contextualGroup.append(ctxList);
            section.append(contextualGroup);
        }

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
        manager.querySelectorAll('[data-sw-zip-tree]').forEach((tree) => {
            tree.hidden = true;
            tree.querySelectorAll('.sw-zip-subtree').forEach((sub) => { sub.hidden = true; });
            tree.querySelectorAll('.sw-zip-folder-btn i').forEach((icon) => { icon.className = 'fa-solid fa-folder-closed'; });
        });

        const viewerName = manager.querySelector('[data-sw-viewer-name]');
        const viewerMeta = manager.querySelector('[data-sw-viewer-meta]');
        const viewerDownload = manager.querySelector('[data-sw-viewer-download]');
        const viewerZoom = manager.querySelector('[data-sw-viewer-zoom]');
        if (viewerName) viewerName.textContent = 'Visor de documentos';
        if (viewerMeta) viewerMeta.textContent = 'Exploración y consulta documental';
        if (viewerDownload) { viewerDownload.hidden = false; viewerDownload.disabled = true; delete viewerDownload.dataset.downloadUrl; }
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
        const metas = metadataFor(editorState.fileId);

        if (editorState.index === null) {
            const meta = {
                selected_text: editorState.selectedText || '',
                page_number: editorState.pageNumber || null,
                entry_name: normalizeInternalEntry(editorState.entryName),
                internal_entry: normalizeInternalEntry(editorState.internalEntry),
                relative_rects: Array.isArray(editorState.relativeRects) ? compactRelativeRects(editorState.relativeRects) : [],
            };
            draft.observations.push(observation);
            metas.push(meta);
        } else {
            const previousMeta = metas[editorState.index] || {};
            const meta = {
                ...previousMeta,
                selected_text: editorState.selectedText || previousMeta.selected_text || '',
                page_number: previousMeta.page_number || null,
                entry_name: normalizeInternalEntry(previousMeta.entry_name),
                internal_entry: normalizeInternalEntry(previousMeta.internal_entry),
                relative_rects: Array.isArray(previousMeta.relative_rects) && previousMeta.relative_rects.length
                    ? compactRelativeRects(previousMeta.relative_rects)
                    : [],
            };
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
        form.append(categoryLabel);

        const targetFile = filesById.get(state.fileId);
        const isZipFile = targetFile && (targetFile.extension === 'zip' || (targetFile.name && targetFile.name.endsWith('.zip')));

        if (isZipFile) {
            const zipEntriesList = [];
            manager.querySelectorAll('.sw-zip-entry-name').forEach((span) => {
                const name = span.textContent.trim();
                if (name && !zipEntriesList.includes(name)) zipEntriesList.push(name);
            });

            if (zipEntriesList.length > 0) {
                const internalLabel = document.createElement('label');
                internalLabel.className = 'sw-review-internal-entry-label';
                internalLabel.textContent = 'Archivo interno relacionado (opcional)';

                const select = document.createElement('select');
                select.className = 'sw-review-internal-entry-select';
                select.dataset.swReviewInternalEntry = '';
                select.disabled = isSubmitting;

                const defaultOpt = document.createElement('option');
                defaultOpt.value = '';
                defaultOpt.textContent = '[ Todo el paquete ZIP ]';
                select.append(defaultOpt);

                zipEntriesList.forEach((entryName) => {
                    const opt = document.createElement('option');
                    opt.value = entryName;
                    opt.textContent = entryName;
                    if (state.internalEntry === entryName || (state.locationReference && state.locationReference.includes(entryName))) {
                        opt.selected = true;
                    }
                    select.append(opt);
                });

                select.addEventListener('change', () => {
                    const val = select.value.trim();
                    if (val) {
                        state.internalEntry = val;
                        state.entryName = val;
                        state.locationReference = `${targetFile.name} \u2192 ${val}`;
                    } else {
                        state.internalEntry = null;
                        state.entryName = null;
                        state.locationReference = null;
                    }
                });

                internalLabel.append(select);
                form.append(internalLabel);
            }
        }

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
        if (!reviewIsAvailable && !generalObservations.length) {
            return null;
        }
        const section = document.createElement('section');
        section.className = 'sw-review-observation-group is-general-project';
        const heading = document.createElement('div');
        heading.className = 'sw-review-group-heading';
        heading.innerHTML = `<strong>Observaciones generales del proyecto</strong><span>${generalObservations.length}</span>`;
        section.append(heading);

        if (reviewIsAvailable) {
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
        } else if (!generalObservations.length) {
            const emptyGen = document.createElement('p');
            emptyGen.className = 'sw-review-empty-inline';
            emptyGen.style.fontSize = '0.8rem';
            emptyGen.style.color = '#64748b';
            emptyGen.textContent = 'Sin observaciones generales.';
            section.append(emptyGen);
        }

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

    const createReviewSections = (file) => {
        const draft = draftFor(file.file_id);
        const items = draft?.observations || [];
        const metas = metadataFor(file.file_id);
        const contextualMap = getContextualObservationMap(file.file_id);

        const generalIndices = [];
        const contextualIndices = [];

        items.forEach((item, index) => {
            if (contextualMap.has(index)) {
                contextualIndices.push(index);
            } else {
                generalIndices.push(index);
            }
        });

        // 1. OBSERVACIONES GENERALES
        const generalSection = document.createElement('section');
        generalSection.className = 'sw-review-observation-group is-general';

        const generalHeading = document.createElement('div');
        generalHeading.className = 'sw-review-group-heading';
        generalHeading.innerHTML = `<strong>Observaciones generales</strong><span>${generalIndices.length}</span>`;
        generalSection.append(generalHeading);

        if (!generalIndices.length) {
            const empty = document.createElement('p');
            empty.className = 'sw-review-empty-inline';
            empty.textContent = 'No hay observaciones generales.';
            generalSection.append(empty);
        } else {
            const list = document.createElement('div');
            list.className = 'sw-review-card-list';

            generalIndices.forEach((index) => {
                const item = items[index];
                const selectedText = metas[index]?.selected_text || '';
                const compactLoc = formatCompactLocation(item.location_reference);

                const card = document.createElement('article');
                card.className = 'sw-review-observation-card is-draft';

                const renderCardContent = () => {
                    card.innerHTML = '';
                    const isExpanded = card.dataset.expanded === 'true';

                    const meta = document.createElement('div');
                    meta.className = 'sw-review-card-meta';

                    const displayInfo = resolveObservationDisplayType(item, file, metas[index]);
                    if (item.category && item.category !== 'General' && item.category !== 'Texto seleccionado') {
                        const category = document.createElement('strong');
                        category.className = 'sw-review-category-badge';
                        category.textContent = item.category;
                        meta.append(category);
                    }
                    const typeLabel = document.createElement('span');
                    typeLabel.className = `sw-review-type-label ${displayInfo.badgeClass}`;
                    typeLabel.textContent = displayInfo.typeLabel;
                    meta.append(typeLabel);

                    const pending = document.createElement('span');
                    pending.textContent = 'Borrador';
                    meta.append(pending);
                    card.append(meta);

                    const commentBody = document.createElement('p');
                    commentBody.className = 'sw-review-card-comment';
                    const commentTrunc = truncateText(item.body, 140);
                    commentBody.textContent = (!isExpanded && commentTrunc.isTruncated) ? commentTrunc.truncated : item.body;
                    card.append(commentBody);

                    if (compactLoc) {
                        const metaLine = document.createElement('div');
                        metaLine.className = 'sw-review-card-meta-line';
                        const locSmall = document.createElement('small');
                        locSmall.textContent = isExpanded ? (item.location_reference || compactLoc) : compactLoc;
                        metaLine.append(locSmall);
                        card.append(metaLine);
                    }

                    if (commentTrunc.isTruncated) {
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

                    const actions = document.createElement('div');
                    actions.className = 'sw-review-card-actions';
                    actions.innerHTML = '<button type="button" data-action="edit"><i class="fa-solid fa-pen" aria-hidden="true"></i> Editar</button><button type="button" data-action="delete"><i class="fa-regular fa-trash-can" aria-hidden="true"></i> Eliminar</button>';
                    actions.querySelectorAll('button').forEach((b) => b.disabled = isSubmitting);

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
                list.append(card);
            });
            generalSection.append(list);
        }

        // 2. OBSERVACIONES SOBRE EL TEXTO
        const contextualSection = document.createElement('section');
        contextualSection.className = 'sw-review-observation-group is-contextual';

        const contextualHeading = document.createElement('div');
        contextualHeading.className = 'sw-review-group-heading';
        contextualHeading.innerHTML = `<strong>Observaciones sobre el texto</strong><span>${contextualIndices.length}</span>`;
        contextualSection.append(contextualHeading);

        if (!contextualIndices.length) {
            const empty = document.createElement('p');
            empty.className = 'sw-review-empty-inline';
            empty.textContent = 'No hay observaciones sobre el texto.';
            contextualSection.append(empty);
        } else {
            const list = document.createElement('div');
            list.className = 'sw-review-card-list';

            contextualIndices.forEach((index) => {
                const item = items[index];
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

                    const meta = document.createElement('div');
                    meta.className = 'sw-review-card-meta';

                    if (obsNumber) {
                        const numberBadge = document.createElement('span');
                        numberBadge.className = `sw-review-card-number-badge ${colorClass}`;
                        numberBadge.textContent = String(obsNumber);
                        meta.append(numberBadge);
                    }

                    const displayInfo = resolveObservationDisplayType(item, file, metas[index]);
                    if (item.category && item.category !== 'General' && item.category !== 'Texto seleccionado') {
                        const category = document.createElement('strong');
                        category.className = 'sw-review-category-badge';
                        category.textContent = item.category;
                        meta.append(category);
                    }
                    const typeLabel = document.createElement('span');
                    typeLabel.className = `sw-review-type-label ${displayInfo.badgeClass}`;
                    typeLabel.textContent = displayInfo.typeLabel;
                    meta.append(typeLabel);

                    const pending = document.createElement('span');
                    pending.textContent = 'Borrador';
                    meta.append(pending);
                    card.append(meta);

                    const commentBody = document.createElement('p');
                    commentBody.className = 'sw-review-card-comment';
                    const commentTrunc = truncateText(item.body, 140);
                    commentBody.textContent = (!isExpanded && commentTrunc.isTruncated) ? commentTrunc.truncated : item.body;
                    card.append(commentBody);

                    if (compactLoc) {
                        const metaLine = document.createElement('div');
                        metaLine.className = 'sw-review-card-meta-line';
                        const locSmall = document.createElement('small');
                        locSmall.textContent = isExpanded ? (item.location_reference || compactLoc) : compactLoc;
                        metaLine.append(locSmall);
                        card.append(metaLine);
                    }

                    if (selectedText) {
                        const fragment = document.createElement('blockquote');
                        fragment.className = 'sw-review-card-quote';
                        const quoteTrunc = truncateText(selectedText, 100);
                        fragment.textContent = (!isExpanded && quoteTrunc.isTruncated) ? `"${quoteTrunc.truncated}"` : `"${selectedText}"`;
                        card.append(fragment);
                    }

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

                    const actions = document.createElement('div');
                    actions.className = 'sw-review-card-actions';

                    const isZipDoc = file.extension === 'zip' || (file.name && file.name.endsWith('.zip'));
                    if (isZipDoc) {
                        const convertBtn = document.createElement('button');
                        convertBtn.type = 'button';
                        convertBtn.className = 'sw-review-convert-zip-btn';
                        convertBtn.innerHTML = '<i class="fa-solid fa-arrow-right-arrow-left" aria-hidden="true"></i> Conservar como observación del archivo interno';
                        convertBtn.title = 'Convertir a observación general del paquete ZIP referenciando la entrada interna';
                        convertBtn.addEventListener('click', (e) => {
                            e.stopPropagation();
                            if (isSubmitting) return;
                            const metaObj = metas[index] || {};
                            let entryName = metaObj.internal_entry || metaObj.entry_name;
                            if (!entryName && item.location_reference && item.location_reference.includes('→')) {
                                entryName = item.location_reference.replace(/^.*?→\s*/, '').replace(/\s*·.*$/, '').trim();
                            }
                            const cleanEntry = normalizeInternalEntry(entryName);
                            item.category = item.category && item.category !== 'Texto seleccionado' ? item.category : 'General';
                            item.location_reference = cleanEntry ? `${file.name} \u2192 ${cleanEntry}` : `${file.name}`;
                            metas[index] = {
                                selected_text: '',
                                page_number: null,
                                entry_name: cleanEntry,
                                internal_entry: cleanEntry,
                                relative_rects: [],
                            };
                            saveReviewDraft();
                            renderReviewCenter();
                            announce('Observación convertida a observación general del paquete ZIP.', 'info');
                        });
                        actions.append(convertBtn);
                    }

                    const editBtn = document.createElement('button');
                    editBtn.type = 'button';
                    editBtn.dataset.action = 'edit';
                    editBtn.innerHTML = '<i class="fa-solid fa-pen" aria-hidden="true"></i> Editar';
                    const deleteBtn = document.createElement('button');
                    deleteBtn.type = 'button';
                    deleteBtn.dataset.action = 'delete';
                    deleteBtn.innerHTML = '<i class="fa-regular fa-trash-can" aria-hidden="true"></i> Eliminar';
                    actions.append(editBtn, deleteBtn);

                    actions.querySelectorAll('button').forEach((b) => b.disabled = isSubmitting);

                    editBtn.addEventListener('click', (e) => {
                        e.stopPropagation();
                        openEditor('edit', file.file_id, {
                            index,
                            category: item.category,
                            body: item.body,
                            locationReference: item.location_reference || '',
                            selectedText: selectedText,
                        });
                    });
                    deleteBtn.addEventListener('click', (e) => {
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
                        showHighlightNote(highlights[0], item, index, file.file_id, obsNumber, colorClass);
                    }
                });

                list.append(card);
            });
            contextualSection.append(list);
        }

        return { generalSection, contextualSection };
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
        const showDecisions = reviewIsAvailable && isReviewableFile(file);
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
        const confirmState = getConfirmState();
        const buttonText = isSubmitting ? 'Finalizando revisión...' : 'Terminar revisión';
        const section = document.createElement('section');
        section.className = 'sw-review-finish-action';

        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'sw-review-finish-btn';
        button.disabled = !confirmState.enabled || isSubmitting;
        button.textContent = buttonText;
        button.title = confirmState.enabled ? 'Confirmar revisión documental' : (confirmState.reason || 'Completa los documentos pendientes antes de confirmar.');

        button.addEventListener('click', () => {
            if (!confirmState.enabled || isSubmitting) return;
            openConfirmModal();
        });

        section.append(button);

        if (!confirmState.enabled && !isSubmitting) {
            const helper = document.createElement('small');
            helper.className = 'sw-review-finish-helper';
            helper.textContent = confirmState.reason || 'Completa la revisión de todos los documentos antes de confirmar.';
            section.append(helper);
        }

        return section;
    };

    const renderReviewCenter = () => {
        if (!observationPanel) return;
        updateFileIndicators();
        renderHighlights();

        const progressNode = createProgress();
        const value = summary();

        const scrollBody = document.createElement('div');
        scrollBody.className = 'sw-review-scroll-body';

        observationPanel.replaceChildren(progressNode, scrollBody);

        const mobileFooter = manager.querySelector('[data-sw-mobile-review-footer]');

        if (obsFooter) {
            if (reviewIsAvailable && value.total > 0) {
                obsFooter.replaceChildren(createReadySummary());
                obsFooter.hidden = false;
                obsFooter.style.display = 'block';
            } else {
                obsFooter.replaceChildren();
                obsFooter.hidden = true;
                obsFooter.style.display = 'none';
            }
        }

        if (mobileFooter) {
            if (reviewIsAvailable && value.total > 0) {
                mobileFooter.replaceChildren(createReadySummary());
                mobileFooter.hidden = false;
            } else {
                mobileFooter.replaceChildren();
                mobileFooter.hidden = true;
            }
        }

        if (!reviewIsAvailable) {
            const hasPersistedReview = value.reviewed > 0 || existingObservations.length > 0;
            if (!hasPersistedReview) {
                const unavailable = document.createElement('div');
                unavailable.className = 'sw-review-unavailable';
                unavailable.innerHTML = '<i class="fa-solid fa-lock" aria-hidden="true"></i><strong>Revisión no disponible</strong><span>El proyecto debe encontrarse en revisión para confirmar decisiones documentales.</span>';
                scrollBody.append(unavailable);
                return;
            }
            const file = filesById.get(activeFileId);
            if (!file) {
                const genSec = createProjectGeneralSection();
                if (genSec) scrollBody.append(genSec);
                return;
            }
            if (activeInternalZipEntry) {
                const banner = document.createElement('div');
                banner.className = 'sw-viewer-help-banner sw-zip-internal-banner';
                banner.style.background = '#f8fafc';
                banner.style.borderLeft = '3px solid #64748b';
                banner.style.padding = '0.5rem 0.75rem';
                banner.style.marginBottom = '0.5rem';
                banner.style.fontSize = '0.78rem';
                banner.style.color = '#334155';
                banner.innerHTML = '<i class="fa-solid fa-circle-info" style="color:#64748b;margin-right:0.35rem;"></i> Los archivos dentro de un ZIP se consultan únicamente como referencia.';
                scrollBody.append(banner);
            }
            const fileStatus = file.status || file.document_status;
            const existingSection = createExistingSection(file);
            if (fileStatus === 'approved') {
                const approved = document.createElement('div');
                approved.className = 'sw-review-persisted-approved';
                approved.innerHTML = '<i class="fa-solid fa-circle-check" aria-hidden="true"></i><div><strong style="display:block;margin-bottom:4px;color:#15803d;font-size:0.9rem;">Documento aprobado</strong><span style="display:block;color:#166534;">Este documento fue aprobado en la revisión enviada.</span></div>';
                scrollBody.append(approved);
                if (existingSection) scrollBody.append(existingSection);
            } else if (fileStatus === 'corrections_requested') {
                const corrections = document.createElement('div');
                corrections.className = 'sw-review-persisted-approved';
                corrections.style.borderColor = '#f59e0b';
                corrections.style.background = '#fffbeb';
                corrections.style.borderRadius = '8px';
                corrections.style.border = '1px solid #fde68a';
                corrections.innerHTML = '<i class="fa-solid fa-triangle-exclamation" style="color:#d97706;font-size:1.5rem;" aria-hidden="true"></i><div><strong style="display:block;margin-bottom:6px;color:#92400e;font-size:0.92rem;font-weight:700;">Requiere correcciones</strong><span style="display:block;color:#b45309;font-size:0.78rem;line-height:1.4;">Este documento fue devuelto con observaciones para ajuste del estudiante.</span></div>';
                scrollBody.append(corrections);
                if (existingSection) scrollBody.append(existingSection);
            } else {
                if (existingSection) scrollBody.append(existingSection);
            }
            return;
        }

        const file = filesById.get(activeFileId);
        if (!file) {
            const genSec = createProjectGeneralSection();
            if (genSec) scrollBody.append(genSec);
            if (editorState?.fileId === 0) scrollBody.append(createEditor());
            return;
        }

        if (activeInternalZipEntry) {
            const banner = document.createElement('div');
            banner.className = 'sw-viewer-help-banner sw-zip-internal-banner';
            banner.style.background = '#f8fafc';
            banner.style.borderLeft = '3px solid #64748b';
            banner.style.padding = '0.5rem 0.75rem';
            banner.style.marginBottom = '0.5rem';
            banner.style.fontSize = '0.78rem';
            banner.style.color = '#334155';
            banner.innerHTML = '<i class="fa-solid fa-circle-info" style="color:#64748b;margin-right:0.35rem;"></i> Los archivos dentro de un ZIP se consultan únicamente como referencia. Las observaciones se registran sobre el paquete ZIP.';
            scrollBody.append(banner);
        }

        if (file.status === 'approved') {
            const approved = document.createElement('div');
            approved.className = 'sw-review-persisted-approved';
            approved.innerHTML = '<i class="fa-solid fa-circle-check" aria-hidden="true"></i><div><strong>Documento aprobado</strong><span>Este checksum ya fue aprobado y no requiere una nueva decision.</span></div>';
            const existingSection = createExistingSection(file);
            scrollBody.append(approved);
            if (existingSection) scrollBody.append(existingSection);
            return;
        }

        const isCompletedReadOnly = completedFileIds.has(file.file_id) && editingCompletedFileId !== file.file_id;
        const { generalSection, contextualSection } = createReviewSections(file);

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
            scrollBody.append(banner, generalSection, contextualSection);
            return;
        }

        scrollBody.append(createReviewActions(file), generalSection, contextualSection);
        if (editorState?.fileId === file.file_id) scrollBody.append(createEditor());
        if (reviewError && !editorState) {
            const error = document.createElement('p');
            error.className = 'sw-review-error';
            error.setAttribute('role', 'alert');
            error.textContent = reviewError;
            scrollBody.append(error);
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
        scrollBody.append(footer);

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
        if (!popoverEl || !floatingLayer) return;
        const viewportPadding = 12;
        const layerRect = floatingLayer.getBoundingClientRect();
        const popoverRect = popoverEl.getBoundingClientRect();
        const popoverWidth = popoverRect.width || 320;
        const popoverHeight = popoverRect.height || 260;

        const minLeft = viewportPadding;
        const maxLeft = window.innerWidth - popoverWidth - viewportPadding;
        let left = rangeRect.left + (rangeRect.width - popoverWidth) / 2;
        left = Math.min(maxLeft, Math.max(minLeft, left));

        let top = rangeRect.top - popoverHeight - 10;
        const minTop = viewportPadding;
        if (top < minTop) {
            top = rangeRect.bottom + 10;
        }
        const maxTop = window.innerHeight - popoverHeight - viewportPadding;
        top = Math.min(maxTop, Math.max(minTop, top));

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
        const observations = (reviewIsAvailable && draft?.observations?.length) ? draft.observations : observationsForFile(fileId);
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
                previewStage.querySelectorAll('.sw-review-highlight-overlay, .sw-review-highlight-badge, .sw-review-highlight-rail-guide').forEach((el) => el.remove());

                if (!activeFileId) return;
                const metas = metadataFor(activeFileId);
                const draft = draftFor(activeFileId);
                const observations = (reviewIsAvailable && draft?.observations?.length) ? draft.observations : observationsForFile(activeFileId);
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

                    // Overlays sobre todas las rects seleccionadas (por línea compactada)
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

                    // INDICADOR LATERAL CON GUÍA VERTICAL QUE ABARCA TODO EL RANGO DE SELECCIÓN (minTop -> maxBottom)
                    if (validRects.length > 0) {
                        const tops = validRects.map((r) => r.top);
                        const bottoms = validRects.map((r) => r.top + r.height);
                        const minTop = Math.min(...tops);
                        const maxBottom = Math.max(...bottoms);
                        const rangeHeight = Math.max(0.005, maxBottom - minTop);

                        const guideContainer = document.createElement('div');
                        guideContainer.className = `sw-review-highlight-rail-guide ${colorClass}`;
                        guideContainer.dataset.observationIndex = String(index);
                        guideContainer.title = `Observación [${obsNumber}]: ${obs.body}`;

                        const badge = document.createElement('span');
                        badge.className = `sw-review-highlight-badge ${colorClass}`;
                        badge.textContent = String(obsNumber);

                        const railBar = document.createElement('span');
                        railBar.className = `sw-review-highlight-rail-bar ${colorClass}`;

                        guideContainer.append(badge, railBar);

                        const finalLeftCss = `calc(0% - 26px)`;
                        let badgeTop = minTop * 100;
                        const pageKey = String(meta.page_number);
                        usedTopsByPage[pageKey] = usedTopsByPage[pageKey] || [];
                        while (usedTopsByPage[pageKey].some((t) => Math.abs(t - badgeTop) < 2.5)) {
                            badgeTop += 2.5;
                        }
                        usedTopsByPage[pageKey].push(badgeTop);

                        Object.assign(guideContainer.style, {
                            position: 'absolute',
                            left: finalLeftCss,
                            top: `calc(${badgeTop.toFixed(3)}% - 2px)`,
                            height: `${(rangeHeight * 100).toFixed(3)}%`,
                            minHeight: '20px',
                            display: 'flex',
                            flexDirection: 'column',
                            alignItems: 'center',
                            pointerEvents: 'auto',
                            cursor: 'pointer',
                            zIndex: '15',
                        });

                        Object.assign(railBar.style, {
                            width: '3px',
                            flex: '1 1 auto',
                            borderRadius: '2px',
                            background: 'var(--sw-color-badge-border, #ca8a04)',
                            opacity: '0.85',
                            marginTop: '2px',
                        });

                        guideContainer.addEventListener('click', (e) => {
                            e.stopPropagation();
                            const firstOverlay = pageEl.querySelector(`[data-observation-index="${index}"]`);
                            showHighlightNote(firstOverlay || guideContainer, obs, index, activeFileId, obsNumber, colorClass);
                        });

                        pageEl.append(guideContainer);
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
        if (isSubmitting || !reviewIsAvailable || filesById.get(activeFileId)?.status === 'approved' || activeInternalZipEntry) {
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
        const relativeRects = compactRelativeRects(rects.map((r) => ({
            left: (r.left - pageRect.left) / pageRect.width,
            top: (r.top - pageRect.top) / pageRect.height,
            width: r.width / pageRect.width,
            height: r.height / pageRect.height,
        })));

        if (relativeRects.length > 50) {
            reviewError = 'La selección de texto es demasiado extensa. Selecciona un fragmento más corto para agregar esta observación.';
            renderReviewCenter();
            return;
        }

        const selectedTextSnippet = text.length > 500 ? `${text.slice(0, 497)}...` : text;
        const locationRef = buildLocationReference(pageNumber);

        removeSelectionPopover(false);

        selectionState = {
            fileId: activeFileId,
            selectedText: selectedTextSnippet,
            pageNumber,
            entryName: activeInternalZipEntry ? normalizeInternalEntry(activeInternalZipEntry.entryName) : null,
            internalEntry: activeInternalZipEntry ? normalizeInternalEntry(activeInternalZipEntry.entryName) : null,
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
            if (selectionState?.rangeRect) positionPopover(selectionState.rangeRect);
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
                if (selectionState?.rangeRect) positionPopover(selectionState.rangeRect);
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

    const handleExpiredSession = (notice) => {
        const message = notice || 'Tu sesion ha caducado. Inicia sesion nuevamente.';
        if (typeof announce === 'function') {
            announce(message, 'warning');
        }
        // DO NOT clear localStorage draft (teacher_review_draft_*). Draft remains intact for re-login.
        setTimeout(() => {
            const loginUrl = 'index.php?page=login&notice=' + encodeURIComponent(message);
            window.location.href = loginUrl;
        }, 200);
    };

    const readJsonResponse = async (response) => {
        const redirectedToLogin = response.redirected && /([?&]page=login)(?:&|$)/i.test(response.url || '');
        const is401 = response.status === 401 || response.status === 419;

        let payload = null;
        const contentType = String(response.headers.get('content-type') || '').toLowerCase();
        if (contentType.includes('application/json')) {
            try {
                payload = await response.clone().json();
            } catch (e) {}
        }

        const isSessionExpiredPayload = payload && (payload.authenticated === false || payload.code === 'session_expired');

        if (is401 || redirectedToLogin || isSessionExpiredPayload) {
            const notice = payload?.message || 'Tu sesion ha caducado. Inicia sesion nuevamente.';
            handleExpiredSession(notice);
            const error = new Error(notice);
            error.status = 401;
            error.code = 'session_expired';
            throw error;
        }

        if (!contentType.includes('application/json')) {
            const error = new Error('Respuesta inesperada del servidor.');
            error.status = response.status;
            error.code = 'unexpected_response';
            throw error;
        }

        if (!payload) payload = await response.json();

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
        const val = summary();
        const total = currentSummary.total;
        const approved = currentSummary.approved;
        const corrections = currentSummary.corrections;
        const hasCorrections = corrections > 0;

        if (confirmHeading) {
            confirmHeading.textContent = 'Estás a punto de enviar esta revisión al estudiante.';
        }

        if (confirmMessage) {
            const docWord = total === 1 ? 'documento' : 'documentos';
            const revWord = total === 1 ? 'Se revisó' : 'Se revisaron';

            if (hasCorrections) {
                const approvedText = approved === 1 ? '1 fue aprobado' : `${approved} fueron aprobados`;
                const correctionsText = corrections === 1 ? '1 requiere correcciones' : `${corrections} requieren correcciones`;
                confirmMessage.innerHTML = `${revWord} <strong>${total}</strong> ${docWord}: <strong>${approvedText}</strong> y <strong>${correctionsText}</strong>.<br><br>Las observaciones, comentarios y subrayados realizados durante la revisión serán visibles para el estudiante.<br><br>Una vez confirmada, la revisión quedará bloqueada y el proyecto volverá a estado <strong>En desarrollo</strong> para que el estudiante pueda realizar las correcciones y reenviar los documentos pendientes.`;
            } else {
                const allApprovedText = total === 1 ? 'fue aprobado' : 'todos fueron aprobados';
                confirmMessage.innerHTML = `${revWord} <strong>${total}</strong> ${docWord} y ${allApprovedText}.<br><br>Las observaciones y comentarios realizados durante la revisión serán enviados al estudiante.<br><br>Una vez confirmada, la revisión quedará bloqueada y el proyecto pasará a estado <strong>Aprobado</strong>.`;
            }
        }

        if (confirmApprovedCount) {
            confirmApprovedCount.textContent = `${approved} ${approved === 1 ? 'aprobado' : 'aprobados'}`;
        }
        if (confirmCorrectionsCount) {
            confirmCorrectionsCount.textContent = `${corrections} ${corrections === 1 ? 'requiere correcciones' : 'con correcciones'}`;
        }
        const confirmObsCount = confirmModal.querySelector('[data-sw-review-observations-count]');
        if (confirmObsCount) {
            confirmObsCount.textContent = `${val.newObservations} ${val.newObservations === 1 ? 'observación nueva' : 'observaciones nuevas'}`;
        }

        if (confirmStatus) {
            confirmStatus.hidden = confirmModalStatus === '';
            confirmStatus.textContent = confirmModalStatus;
        }
        if (confirmError) {
            confirmError.hidden = confirmModalError === '';
            confirmError.textContent = confirmModalError;
        }
        if (confirmLock) {
            confirmLock.textContent = 'Después de confirmar no podrás modificar esta revisión.';
        }
        if (confirmSubmitButton) {
            confirmSubmitButton.disabled = !confirmState.enabled || isSubmitting;
            const btnSpan = confirmSubmitButton.querySelector('span');
            if (btnSpan) {
                btnSpan.textContent = isSubmitting ? 'Finalizando revisión...' : 'Terminar revisión';
            }
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
        confirmModalStatus = 'Finalizando revision...';
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

    const syncWorkspaceHeight = () => {
        const workspace = manager.querySelector('.sw-doc-workspace');
        if (!workspace) return;
        const rect = workspace.getBoundingClientRect();
        const available = Math.max(320, Math.floor(window.innerHeight - rect.top - 14));
        workspace.style.setProperty('--sw-workspace-height', `${available}px`);
    };

    window.addEventListener('resize', () => {
        syncWorkspaceHeight();
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

    syncWorkspaceHeight();
    restoreReviewDraft();
    updateFileIndicators();
    renderReviewCenter();
    refreshConfirmModal();
});
