document.addEventListener('DOMContentLoaded', () => {
    const workspace = document.querySelector('[data-student-workspace]');
    let toastContainer = document.querySelector('.sw-toast-container');
    if (!toastContainer) {
        toastContainer = document.createElement('div');
        toastContainer.className = 'sw-toast-container';
        document.body.appendChild(toastContainer);
    }

    const escapeHtml = (str = '') => String(str || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');

    const showVisualToast = (message, kind = false, title = '') => {
        if (!message) return;

        const isError = kind === true || kind === 'error';
        const isInfo = kind === 'info' || kind === 'warning';
        const toastClass = isError ? 'is-error' : (isInfo ? 'is-info' : 'is-success');
        const iconClass = isError ? 'fa-circle-xmark' : (isInfo ? 'fa-circle-info' : 'fa-circle-check');

        const toastEl = document.createElement('div');
        toastEl.className = `sw-toast ${toastClass}`;
        toastEl.setAttribute('role', isError ? 'alert' : 'status');

        const icon = document.createElement('i');
        icon.className = `fa-solid ${iconClass}`;
        icon.setAttribute('aria-hidden', 'true');

        const text = document.createElement('span');
        text.className = 'sw-toast-text';
        if (title) {
            const heading = document.createElement('strong');
            heading.textContent = title;
            text.append(heading, document.createElement('br'));
        }
        text.append(document.createTextNode(message));

        const closeBtn = document.createElement('button');
        closeBtn.type = 'button';
        closeBtn.className = 'sw-toast-close';
        closeBtn.setAttribute('aria-label', 'Cerrar mensaje');
        closeBtn.innerHTML = '<i class="fa-solid fa-xmark" aria-hidden="true"></i>';

        const dismiss = () => {
            toastEl.classList.add('is-leaving');
            setTimeout(() => toastEl.remove(), 200);
        };

        closeBtn.addEventListener('click', dismiss);
        toastEl.append(icon, text, closeBtn);

        toastContainer.appendChild(toastEl);

        setTimeout(dismiss, 4200);
    };

    const setFlashToast = (message, kind = 'success') => {
        try {
            sessionStorage.setItem('studentWorkspace.flashToast', JSON.stringify({ message, kind }));
        } catch (e) {
            /* ignore storage errors */
        }
    };

    const consumeFlashToast = () => {
        try {
            const raw = sessionStorage.getItem('studentWorkspace.flashToast');
            if (!raw) return;
            sessionStorage.removeItem('studentWorkspace.flashToast');
            const data = JSON.parse(raw);
            if (data && data.message) {
                showVisualToast(data.message, data.kind || false);
            }
        } catch (e) {
            sessionStorage.removeItem('studentWorkspace.flashToast');
        }
    };

    window.showToast = showVisualToast;
    const toast = showVisualToast;
    consumeFlashToast();
    const tabs = [...workspace.querySelectorAll('[data-sw-tab]')];
    const panes = [...workspace.querySelectorAll('.sw-tab-pane')];
    tabs.forEach((tab) => tab.addEventListener('click', (event) => { event.preventDefault(); const pane = workspace.querySelector(`#swTab-${CSS.escape(tab.dataset.swTab)}`); if (!pane) return; tabs.forEach((item) => { const active=item===tab; item.classList.toggle('is-active',active); item.setAttribute('aria-selected',active?'true':'false'); }); panes.forEach((item) => item.classList.toggle('is-active',item===pane)); const url=new URL(workspace.dataset.projectUrl,window.location.origin); url.searchParams.set('tab',tab.dataset.swTab); window.history.pushState({},'',url); }));
    const fullTimeline=workspace.querySelector('[data-sw-full-timeline]'), timelineToggle=workspace.querySelector('[data-sw-toggle-timeline]');
    if (fullTimeline && timelineToggle) timelineToggle.addEventListener('click', () => { const open=fullTimeline.hidden; fullTimeline.hidden=!open; timelineToggle.setAttribute('aria-expanded',open?'true':'false'); timelineToggle.textContent=open?'Ocultar recorrido':'Ver recorrido completo'; });
    // FASE — PANELES LATERALES REDIMENSIONABLES (Manual Resizing)
    const STORAGE_EXPLORER_KEY = 'sw_explorer_width';
    const STORAGE_OBS_KEY = 'sw_obs_width';
    const DEFAULT_EXPLORER_WIDTH = 250;
    const DEFAULT_OBS_WIDTH = 280;
    const MIN_EXPLORER_WIDTH = 190;
    const MIN_OBS_WIDTH = 210;
    const MIN_VIEWER_WIDTH = 380;

    let preferredExplorerWidth = DEFAULT_EXPLORER_WIDTH;
    let preferredObsWidth = DEFAULT_OBS_WIDTH;

    const clamp = (val, min, max) => Math.round(Math.max(min, Math.min(max, val)));

    const updateWorkspacePanelWidths = () => {
        const docWorkspace = workspace.querySelector('.sw-doc-workspace');
        if (!docWorkspace) return;
        if (window.innerWidth > 768) {
            const containerW = docWorkspace.getBoundingClientRect().width;
            if (containerW > 0) {
                const maxExplorerW = Math.min(containerW * 0.35, Math.max(MIN_EXPLORER_WIDTH, containerW - MIN_VIEWER_WIDTH - MIN_OBS_WIDTH));
                const maxObsW = Math.min(containerW * 0.35, Math.max(MIN_OBS_WIDTH, containerW - MIN_VIEWER_WIDTH - MIN_EXPLORER_WIDTH));

                const effectiveExplorerW = clamp(preferredExplorerWidth, MIN_EXPLORER_WIDTH, maxExplorerW);
                const effectiveObsW = clamp(preferredObsWidth, MIN_OBS_WIDTH, maxObsW);

                workspace.style.setProperty('--sw-explorer-w', `${effectiveExplorerW}px`);
                workspace.style.setProperty('--sw-obs-w', `${effectiveObsW}px`);
            }
        } else {
            workspace.style.removeProperty('--sw-explorer-w');
            workspace.style.removeProperty('--sw-obs-w');
        }
    };

    const togglePanel=(panelSelector,triggerSelector,reopenSelector) => {
        const panel=workspace.querySelector(panelSelector),
            trigger=workspace.querySelector(triggerSelector),
            reopen=workspace.querySelector(reopenSelector),
            stateClass=panelSelector.includes('explorer')?'sw-explorer-collapsed':'sw-observations-collapsed';
        const set=(collapsed)=>{
            panel.classList.toggle('is-collapsed',collapsed);
            workspace.classList.toggle(stateClass,collapsed);
            trigger.setAttribute('aria-expanded',collapsed?'false':'true');
            if(reopen)reopen.hidden=!collapsed;
            if (!collapsed) {
                updateWorkspacePanelWidths();
            }
            window.dispatchEvent(new Event('resize'));
        };
        if(panel&&trigger){
            trigger.addEventListener('click',()=>set(!panel.classList.contains('is-collapsed')));
            reopen?.addEventListener('click',()=>set(false));
        }
    };
    togglePanel('[data-sw-explorer]','[data-sw-toggle-explorer]','[data-sw-open-explorer]');
    togglePanel('[data-sw-observations]','[data-sw-toggle-observations]','[data-sw-open-observations]');

    const initResizablePanels = () => {
        const docWorkspace = workspace.querySelector('.sw-doc-workspace');
        if (!docWorkspace) return;

        const explorerPanel = docWorkspace.querySelector('[data-sw-explorer]');
        const obsPanel = docWorkspace.querySelector('[data-sw-observations]');
        const resizerExplorer = docWorkspace.querySelector('[data-sw-resizer="explorer"]');
        const resizerObs = docWorkspace.querySelector('[data-sw-resizer="observations"]');

        const storedExplorer = parseInt(localStorage.getItem(STORAGE_EXPLORER_KEY) || '', 10);
        const storedObs = parseInt(localStorage.getItem(STORAGE_OBS_KEY) || '', 10);

        if (!isNaN(storedExplorer) && storedExplorer >= MIN_EXPLORER_WIDTH) {
            preferredExplorerWidth = storedExplorer;
        }
        if (!isNaN(storedObs) && storedObs >= MIN_OBS_WIDTH) {
            preferredObsWidth = storedObs;
        }

        updateWorkspacePanelWidths();
        window.addEventListener('resize', updateWorkspacePanelWidths);

        const attachResizer = (resizerEl, type) => {
            if (!resizerEl) return;

            let startX = 0;
            let startWidth = 0;
            let isDragging = false;

            const onPointerDown = (event) => {
                if (window.innerWidth <= 760 || event.button !== 0) return;
                event.preventDefault();

                isDragging = true;
                resizerEl.setPointerCapture(event.pointerId);
                resizerEl.classList.add('is-dragging');
                document.body.style.cursor = 'col-resize';
                document.body.style.userSelect = 'none';

                startX = event.clientX;
                const panel = type === 'explorer' ? explorerPanel : obsPanel;
                startWidth = panel ? panel.getBoundingClientRect().width : (type === 'explorer' ? preferredExplorerWidth : preferredObsWidth);
            };

            const onPointerMove = (event) => {
                if (!isDragging) return;

                const delta = event.clientX - startX;
                const containerW = docWorkspace.getBoundingClientRect().width;

                if (type === 'explorer') {
                    const newW = startWidth + delta;
                    const obsW = obsPanel && !obsPanel.classList.contains('is-collapsed') ? obsPanel.getBoundingClientRect().width : 40;
                    const maxW = Math.min(containerW * 0.35, Math.max(MIN_EXPLORER_WIDTH, containerW - obsW - MIN_VIEWER_WIDTH));
                    const clampedW = clamp(newW, MIN_EXPLORER_WIDTH, maxW);
                    preferredExplorerWidth = clampedW;
                    workspace.style.setProperty('--sw-explorer-w', `${clampedW}px`);
                } else {
                    const newW = startWidth - delta;
                    const explorerW = explorerPanel && !explorerPanel.classList.contains('is-collapsed') ? explorerPanel.getBoundingClientRect().width : 40;
                    const maxW = Math.min(containerW * 0.35, Math.max(MIN_OBS_WIDTH, containerW - explorerW - MIN_VIEWER_WIDTH));
                    const clampedW = clamp(newW, MIN_OBS_WIDTH, maxW);
                    preferredObsWidth = clampedW;
                    workspace.style.setProperty('--sw-obs-w', `${clampedW}px`);
                }
            };

            const onPointerUp = (event) => {
                if (!isDragging) return;
                isDragging = false;

                try {
                    resizerEl.releasePointerCapture(event.pointerId);
                } catch (e) {}

                resizerEl.classList.remove('is-dragging');
                document.body.style.cursor = '';
                document.body.style.userSelect = '';

                if (type === 'explorer') {
                    localStorage.setItem(STORAGE_EXPLORER_KEY, String(preferredExplorerWidth));
                } else {
                    localStorage.setItem(STORAGE_OBS_KEY, String(preferredObsWidth));
                }

                window.dispatchEvent(new Event('resize'));
            };

            resizerEl.addEventListener('pointerdown', onPointerDown);
            resizerEl.addEventListener('pointermove', onPointerMove);
            resizerEl.addEventListener('pointerup', onPointerUp);
            resizerEl.addEventListener('pointercancel', onPointerUp);

            resizerEl.addEventListener('dblclick', () => {
                if (type === 'explorer') {
                    preferredExplorerWidth = DEFAULT_EXPLORER_WIDTH;
                    localStorage.removeItem(STORAGE_EXPLORER_KEY);
                    workspace.style.setProperty('--sw-explorer-w', `${DEFAULT_EXPLORER_WIDTH}px`);
                } else {
                    preferredObsWidth = DEFAULT_OBS_WIDTH;
                    localStorage.removeItem(STORAGE_OBS_KEY);
                    workspace.style.setProperty('--sw-obs-w', `${DEFAULT_OBS_WIDTH}px`);
                }
                window.dispatchEvent(new Event('resize'));
            });

            resizerEl.addEventListener('keydown', (event) => {
                if (event.key === 'ArrowLeft' || event.key === 'ArrowRight') {
                    event.preventDefault();
                    const step = (event.key === 'ArrowRight' ? 15 : -15) * (type === 'explorer' ? 1 : -1);
                    const containerW = docWorkspace.getBoundingClientRect().width;
                    if (type === 'explorer') {
                        preferredExplorerWidth = clamp(preferredExplorerWidth + step, MIN_EXPLORER_WIDTH, Math.min(containerW * 0.35, 400));
                        workspace.style.setProperty('--sw-explorer-w', `${preferredExplorerWidth}px`);
                        localStorage.setItem(STORAGE_EXPLORER_KEY, String(preferredExplorerWidth));
                    } else {
                        preferredObsWidth = clamp(preferredObsWidth + step, MIN_OBS_WIDTH, Math.min(containerW * 0.35, 400));
                        workspace.style.setProperty('--sw-obs-w', `${preferredObsWidth}px`);
                        localStorage.setItem(STORAGE_OBS_KEY, String(preferredObsWidth));
                    }
                    window.dispatchEvent(new Event('resize'));
                }
            });
        };

        attachResizer(resizerExplorer, 'explorer');
        attachResizer(resizerObs, 'observations');
    };

    initResizablePanels();

    const initFileTooltips = () => {
        let hoverTimer = null;
        let activeItem = null;

        const cancelTimer = () => {
            if (hoverTimer) {
                clearTimeout(hoverTimer);
                hoverTimer = null;
            }
        };

        const attachTooltipToItem = (item) => {
            if (item.dataset.swTooltipInit === 'true') return;
            item.dataset.swTooltipInit = 'true';

            const tooltip = item.querySelector('.sw-file-tooltip');
            if (!tooltip) return;

            const hideTooltip = () => {
                cancelTimer();
                if (activeItem === item) {
                    activeItem = null;
                }
                tooltip.classList.remove('is-visible');
                tooltip.hidden = true;
            };

            const showTooltipNow = () => {
                if (window.innerWidth <= 760) return;
                tooltip.hidden = false;
                tooltip.classList.add('is-visible');

                const rect = item.getBoundingClientRect();
                const tooltipRect = tooltip.getBoundingClientRect();

                let top = rect.top - tooltipRect.height - 8;
                if (top < 10) {
                    top = rect.bottom + 8;
                }

                let left = rect.left;
                const maxLeft = window.innerWidth - tooltipRect.width - 12;
                left = Math.max(12, Math.min(maxLeft, left));

                tooltip.style.top = `${top}px`;
                tooltip.style.left = `${left}px`;
            };

            const startHoverTimer = () => {
                if (activeItem === item) return;

                cancelTimer();
                workspace.querySelectorAll('.sw-file-tooltip.is-visible').forEach((el) => {
                    el.classList.remove('is-visible');
                    el.hidden = true;
                });

                activeItem = item;

                hoverTimer = setTimeout(() => {
                    hoverTimer = null;
                    if (activeItem === item) {
                        showTooltipNow();
                    }
                }, 2000);
            };

            item.addEventListener('mouseenter', startHoverTimer);
            item.addEventListener('mouseleave', hideTooltip);
            item.addEventListener('click', hideTooltip);
            item.addEventListener('pointerdown', hideTooltip);
            item.addEventListener('blur', hideTooltip);

            const trigger = item.closest('.sw-file-row')?.querySelector('[data-sw-menu-trigger]');
            trigger?.addEventListener('mouseenter', hideTooltip);
            trigger?.addEventListener('click', hideTooltip);
        };

        workspace.querySelectorAll('.sw-tree-item, .sw-zip-entry, .sw-zip-folder-btn').forEach(attachTooltipToItem);

        const observer = new MutationObserver(() => {
            workspace.querySelectorAll('.sw-tree-item, .sw-zip-entry, .sw-zip-folder-btn').forEach(attachTooltipToItem);
        });
        observer.observe(workspace, { childList: true, subtree: true });
    };

    const initFileLockTooltips = () => {
        let activeBadge = null;

        const hideLockTooltip = (badge) => {
            if (!badge) return;
            badge.classList.remove('is-active');
            const tooltip = badge.querySelector('.sw-file-lock-tooltip');
            if (tooltip) {
                tooltip.classList.remove('is-visible');
                tooltip.hidden = true;
            }
            if (activeBadge === badge) activeBadge = null;
        };

        const showLockTooltip = (badge) => {
            if (!badge) return;
            if (activeBadge && activeBadge !== badge) hideLockTooltip(activeBadge);

            const tooltip = badge.querySelector('.sw-file-lock-tooltip');
            if (!tooltip) return;

            activeBadge = badge;
            badge.classList.add('is-active');
            tooltip.hidden = false;
            tooltip.classList.add('is-visible');

            const rect = badge.getBoundingClientRect();
            const tooltipRect = tooltip.getBoundingClientRect();

            let top = rect.top + (rect.height / 2) - (tooltipRect.height / 2);
            top = Math.max(10, Math.min(window.innerHeight - tooltipRect.height - 10, top));

            let left = rect.right + 8;
            if (left + tooltipRect.width > window.innerWidth - 12) {
                left = rect.left - tooltipRect.width - 8;
            }
            left = Math.max(12, left);

            tooltip.style.top = `${top}px`;
            tooltip.style.left = `${left}px`;
        };

        workspace.addEventListener('mouseenter', (e) => {
            const badge = e.target.closest('.sw-file-lock-badge');
            if (badge) showLockTooltip(badge);
        }, true);

        workspace.addEventListener('mouseleave', (e) => {
            const badge = e.target.closest('.sw-file-lock-badge');
            if (badge) hideLockTooltip(badge);
        }, true);

        workspace.addEventListener('focusin', (e) => {
            const badge = e.target.closest('.sw-file-lock-badge');
            if (badge) showLockTooltip(badge);
        });

        workspace.addEventListener('focusout', (e) => {
            const badge = e.target.closest('.sw-file-lock-badge');
            if (badge) hideLockTooltip(badge);
        });

        workspace.addEventListener('click', (e) => {
            const badge = e.target.closest('.sw-file-lock-badge');
            if (badge) {
                e.stopPropagation();
                if (badge.classList.contains('is-active')) {
                    hideLockTooltip(badge);
                } else {
                    showLockTooltip(badge);
                }
            } else if (activeBadge) {
                hideLockTooltip(activeBadge);
            }
        });

        window.addEventListener('scroll', () => { if (activeBadge) hideLockTooltip(activeBadge); }, { passive: true });
    };

    initFileTooltips();
    initFileLockTooltips();

    const manager=workspace.querySelector('[data-sw-document-manager]'); if (!manager) return;
    const endpoint=manager.dataset.endpoint, csrf=manager.dataset.csrf, reviewRepresentationEndpoint=manager.dataset.reviewRepresentationEndpoint||'', reviewRepresentationCsrf=manager.dataset.reviewRepresentationCsrf||'', projectId=manager.dataset.projectId, historicalPreview=manager.dataset.historicalPreview||'';
    let pdfjsPromise=null, pdfDocument=null, pdfDocumentKey='', pdfDocumentLoading=null, pdfDocumentGeneration=0, previewGeneration=0, previewController=null, currentPreviewUrl='', pdfScale=1, pdfFitScale=1, activePdfPreview=null, pocAnnotations=[], annotationsVisible=true;
    const pdfjs=async()=>{if(!pdfjsPromise)pdfjsPromise=import(manager.dataset.pdfjsUrl).then((module)=>{module.GlobalWorkerOptions.workerSrc=manager.dataset.pdfjsWorker;return module;});return pdfjsPromise;};
    const jsonRequestInit=(options={})=>({...options,credentials:'same-origin',headers:{Accept:'application/json',...(options.headers||{})}});
    let isRedirectingToLogin = false;
    const handleExpiredSession = (notice) => {
        if (isRedirectingToLogin) return;
        isRedirectingToLogin = true;
        const message = notice || 'Tu sesión expiró. Inicia sesión nuevamente para continuar.';
        showVisualToast(message, 'error', 'Sesión expirada');
        if (manager) {
            manager.style.pointerEvents = 'none';
            manager.style.opacity = '0.6';
        }
        setTimeout(() => {
            const loginUrl = 'index.php?page=login&notice=' + encodeURIComponent(message);
            window.location.assign(loginUrl);
        }, 300);
    };

    const readJsonResponse = async (response) => {
        const redirectedToLogin = response.redirected && /([?&]page=login)(?:&|$)/i.test(response.url || '');
        const is401 = response.status === 401;

        let payload = null;
        const contentType = (response.headers.get('content-type') || '').toLowerCase();
        if (contentType.includes('application/json')) {
            try {
                payload = await response.clone().json();
            } catch (e) {}
        }

        const isSessionExpiredPayload = payload && (payload.authenticated === false || payload.code === 'session_expired');

        if (is401 || redirectedToLogin || isSessionExpiredPayload) {
            const notice = payload?.message || 'Tu sesión expiró. Inicia sesión nuevamente para continuar.';
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
            const error = new Error(payload.message || 'No fue posible completar la operación.');
            error.status = response.status;
            error.data = payload.data || {};
            throw error;
        }
        return payload;
    };
    const fileInput=manager.querySelector('[data-sw-file-input]'), addButton=manager.querySelector('[data-sw-add-files]');
    const viewerName=manager.querySelector('[data-sw-viewer-name]'), viewerMeta=manager.querySelector('[data-sw-viewer-meta]'), viewerDownload=manager.querySelector('[data-sw-viewer-download]'), viewerEmpty=manager.querySelector('[data-sw-viewer-empty]'), previewStage=manager.querySelector('[data-sw-preview-stage]'), observationPanel=manager.querySelector('[data-sw-file-observations]');
    const mobileObsBadge=manager.querySelector('[data-sw-mobile-obs-badge]');
    const mobileTabButtons=manager.querySelectorAll('[data-sw-mobile-tab]');
    const switchMobileTab=(tabName)=>{
        if(!tabName)return;
        manager.dataset.swActiveTab=tabName;
        mobileTabButtons.forEach((btn)=>{
            const isActive=btn.dataset.swMobileTab===tabName;
            btn.classList.toggle('is-active',isActive);
            btn.setAttribute('aria-selected',isActive?'true':'false');
        });
        if(tabName==='viewer'&&activePdfPreview){
            setTimeout(()=>{ try{ void renderPdfPoc(activePdfPreview); }catch(e){} },50);
        }
    };
    mobileTabButtons.forEach((btn)=>btn.addEventListener('click',()=>switchMobileTab(btn.dataset.swMobileTab)));
    let allStudentObservations=[]; try { allStudentObservations=JSON.parse(manager.parentElement?.querySelector('[data-sw-observations-json]')?.textContent||'[]'); } catch (error) { console.error('No fue posible leer las observaciones del proyecto.',error); }
    let observationFilter='all', selectedObservationFileId=0;
    const observationIsAddressed=(item)=>['addressed','resolved'].includes(String(item.status||'').toLowerCase());
    const observationTone=(item,index)=>['amber','blue','green','violet','rose'][(Number(item.id||index)||index)%5];
    let correctionReadiness={}; try { correctionReadiness=JSON.parse(manager.dataset.correctionReadiness||'{}'); } catch (error) { correctionReadiness={}; }

    const canResubmitCorrections = () => {
        const files = [...manager.querySelectorAll('[data-sw-file]')];
        const hasProjectDeliveries = correctionReadiness.has_deliveries === true;
        const required = Array.isArray(correctionReadiness.required) ? correctionReadiness.required : [];
        const completed = required.filter((item) => {
            const file = files.find((candidate) => String(candidate.dataset.fileId || '') === String(item.file_id || ''));
            if (!file) return false;
            return file.dataset.documentStatus !== 'corrections_requested'
                && String(file.dataset.fileChecksum || '').toLowerCase() !== String(item.checksum || '').toLowerCase();
        }).length;
        const totalNeeded = required.length;
        const unreplaced = totalNeeded - completed;
        const eligible = !hasProjectDeliveries || unreplaced === 0;
        return { eligible, totalNeeded, completed, unreplaced, hasDeliveries: hasProjectDeliveries };
    };

    const checkStudentResubmissionEligibility = () => {
        const sendBtn = manager.querySelector('[data-sw-send-for-review]') || manager.parentElement?.querySelector('[data-sw-send-for-review]');
        if (!sendBtn) return;

        const res = canResubmitCorrections();

        let helperEl = manager.querySelector('[data-sw-resubmission-helper]');
        if (!helperEl) {
            helperEl = document.createElement('p');
            helperEl.className = 'sw-resubmission-helper-text';
            helperEl.dataset.swResubmissionHelper = '';
            Object.assign(helperEl.style, {
                fontSize: '0.78rem',
                color: '#b45309',
                margin: '6px 0 0 0',
                lineHeight: '1.3',
                fontWeight: '600',
            });
            sendBtn.parentElement?.append(helperEl);
        }

        if (res.hasDeliveries && !res.eligible) {
            sendBtn.disabled = true;
            const progressText = `Correcciones realizadas: ${res.completed} de ${res.totalNeeded}`;
            sendBtn.title = `Debes corregir todos los documentos observados antes de reenviar el proyecto. (${progressText})`;
            helperEl.textContent = `Debes corregir todos los documentos observados antes de reenviar el proyecto. (${progressText})`;
            helperEl.style.color = '#b45309';
            helperEl.hidden = false;
        } else {
            sendBtn.disabled = false;
            sendBtn.title = '';
            if (res.hasDeliveries && res.totalNeeded > 0) {
                helperEl.textContent = `Correcciones realizadas: ${res.completed} de ${res.totalNeeded}`;
                helperEl.style.color = '#15803d';
                helperEl.hidden = false;
            } else {
                helperEl.hidden = true;
            }
        }
    };

    const toggleStudentObservationStatusInBackend = async (item, newStatus, buttonEl) => {
        if (!item.id || isRedirectingToLogin) return;
        buttonEl.disabled = true;
        try {
            const body = new FormData();
            body.set('_csrf', csrf);
            body.set('project_id', String(projectId));
            body.set('observation_id', String(item.id));
            body.set('status', newStatus);

            const response = await fetch('index.php?page=student-project-observation-toggle-status', jsonRequestInit({ method: 'POST', body }));
            const payload = await readJsonResponse(response);

            if (!payload.success) {
                throw new Error(payload.message || 'No se pudo actualizar el estado de la observación.');
            }

            item.status = newStatus;
            toast(newStatus === 'addressed' ? 'Observación marcada como atendida.' : 'Observación marcada como pendiente.', 'info');
            renderStudentObservations();
        } catch (error) {
            if (error.code === 'session_expired' || error.status === 401 || error.status === 419) {
                return;
            }
            console.error('Error al cambiar estado de observación:', error);
            toast(error.message || 'No fue posible cambiar el estado de la observación.', true);
        } finally {
            if (!isRedirectingToLogin) buttonEl.disabled = false;
        }
    };

    const sortStudentObservations = (itemList) => {
        return [...itemList].map((item, originalIndex) => {
            let anchorObj = null;
            if (item.selection_anchor) {
                try {
                    anchorObj = typeof item.selection_anchor === 'string' ? JSON.parse(item.selection_anchor) : item.selection_anchor;
                } catch (e) {}
            }
            const pageNum = anchorObj?.page_number || (item.location_reference ? Number((item.location_reference.match(/Página\s+(\d+)/i) || [])[1] || 0) : 0);
            const selectedText = String(anchorObj?.selected_text || '').trim();
            const hasRects = Array.isArray(anchorObj?.relative_rects) && anchorObj.relative_rects.length > 0;
            const pageNumber = Number(anchorObj?.page_number || pageNum || 0);

            const isContextual = selectedText !== '' && Number.isInteger(pageNumber) && pageNumber >= 1 && (hasRects || Boolean(item.selection_anchor));
            const priority = isContextual ? 1 : 0;
            const fileId = Number(item.file_id || 0);

            return { item, fileId, priority, originalIndex };
        }).sort((a, b) => {
            if (a.fileId !== b.fileId) {
                return a.fileId - b.fileId;
            }
            if (a.priority !== b.priority) {
                return a.priority - b.priority;
            }
            return a.originalIndex - b.originalIndex;
        }).map((wrapper) => wrapper.item);
    };

    const getActiveChecksumsMap = () => {
        const map = new Map();
        if (manager) {
            manager.querySelectorAll('[data-sw-file]').forEach((btn) => {
                const fId = Number(btn.dataset.fileId || 0);
                const fChecksum = String(btn.dataset.fileChecksum || btn.dataset.checksum || '').toLowerCase().trim();
                if (fId > 0 && fChecksum) {
                    map.set(fId, fChecksum);
                }
            });
        }
        return map;
    };

    const isObservationForActiveVersion = (item, activeChecksumsMap) => {
        const fileId = Number(item.file_id || 0);
        if (fileId <= 0) return true; // General del proyecto

        const requiredItems = Array.isArray(correctionReadiness?.required) ? correctionReadiness.required : [];
        const isRequiredForCorrections = requiredItems.some((r) => Number(r.file_id || 0) === fileId);

        if (isRequiredForCorrections) {
            const reqItem = requiredItems.find((r) => Number(r.file_id || 0) === fileId);
            if (reqItem && reqItem.checksum) {
                const obsChecksum = String(item.file_checksum_sha256 || '').toLowerCase().trim();
                const targetChecksum = String(reqItem.checksum || '').toLowerCase().trim();
                return obsChecksum === targetChecksum;
            }
            return true;
        }

        const activeChecksum = activeChecksumsMap.get(fileId);
        if (!activeChecksum) return true;
        const obsChecksum = String(item.file_checksum_sha256 || '').toLowerCase().trim();
        return obsChecksum === activeChecksum;
    };

    const renderStudentObservations = () => {
        if (!observationPanel) return;

        checkStudentResubmissionEligibility();

        const activeChecksumsMap = getActiveChecksumsMap();
        const fileObject = [...manager.querySelectorAll('[data-sw-file]')].find((f) => Number(f.dataset.fileId) === selectedObservationFileId);
        const documentStatus = fileObject?.dataset.documentStatus || '';
        const fileName = fileObject?.dataset.fileName || '';

        const projectStatus = String(manager?.dataset.projectStatus || '').toLowerCase().trim();
        const isStudentCorrectionStage = projectStatus === 'development' || projectStatus === 'corrections_requested';

        // Filtrar observaciones: sólo las pertenecientes a las versiones activas si el estudiante está en etapa activa de corrección
        const activeObservations = isStudentCorrectionStage
            ? allStudentObservations.filter((item) => isObservationForActiveVersion(item, activeChecksumsMap))
            : [];

        const items = selectedObservationFileId === 0
            ? activeObservations
            : activeObservations.filter((item) => Number(item.file_id || 0) === selectedObservationFileId);

        const pending = items.filter((item) => !observationIsAddressed(item)).length;
        const addressed = items.length - pending;

        if (mobileObsBadge) {
            mobileObsBadge.textContent = String(activeObservations.length);
            mobileObsBadge.hidden = activeObservations.length === 0;
        }

        observationPanel.replaceChildren();

        const filters = document.createElement('div');
        filters.className = 'sw-obs-filters';
        [
            ['all', 'Todas', items.length, 'is-all'],
            ['pending', 'Pendientes', pending, 'is-pending'],
            ['addressed', 'Atendidas', addressed, 'is-addressed'],
        ].forEach(([key, label, count, toneClass]) => {
            const button = document.createElement('button');
            button.type = 'button';
            const isActive = observationFilter === key;
            button.className = `sw-obs-filter ${toneClass}${isActive ? ' is-active' : ''}`;
            button.setAttribute('aria-pressed', isActive ? 'true' : 'false');
            button.textContent = `${label} (${count})`;
            button.addEventListener('click', () => {
                observationFilter = key;
                renderStudentObservations();
            });
            filters.append(button);
        });
        observationPanel.append(filters);

        // BANNERS INFORMATIVOS DE ESTADO ACADÉMICO DEL DOCUMENTO (ABAJO DE LOS FILTROS)
        if (selectedObservationFileId > 0 && documentStatus === 'approved') {
            const banner = document.createElement('div');
            banner.className = 'sw-obs-approved-banner';
            banner.style.background = '#f0fdf4';
            banner.style.border = '1px solid #bbf7d0';
            banner.style.borderRadius = '8px';
            banner.style.padding = '10px 12px';
            banner.style.marginTop = '8px';
            banner.style.marginBottom = '12px';
            banner.style.display = 'flex';
            banner.style.alignItems = 'center';
            banner.style.gap = '10px';
            banner.innerHTML = `<i class="fa-solid fa-circle-check" style="color:#16a34a;font-size:1.25rem;"></i><div><strong style="display:block;color:#15803d;font-size:0.88rem;">Documento aprobado</strong><span style="display:block;color:#166534;font-size:0.78rem;line-height:1.3;">Este documento fue aprobado en la última revisión y no requiere cambios.</span></div>`;
            observationPanel.append(banner);
        } else if (selectedObservationFileId > 0 && documentStatus === 'corrections_requested') {
            const banner = document.createElement('div');
            banner.className = 'sw-obs-corrections-banner';
            banner.style.background = '#fffbeb';
            banner.style.border = '1px solid #fde68a';
            banner.style.borderRadius = '8px';
            banner.style.padding = '10px 12px';
            banner.style.marginTop = '8px';
            banner.style.marginBottom = '12px';
            banner.style.display = 'flex';
            banner.style.alignItems = 'center';
            banner.style.gap = '10px';
            banner.innerHTML = `<i class="fa-solid fa-triangle-exclamation" style="color:#d97706;font-size:1.25rem;"></i><div><strong style="display:block;color:#92400e;font-size:0.88rem;">Requiere correcciones</strong><span style="display:block;color:#b45309;font-size:0.78rem;line-height:1.3;">Revisa las observaciones enviadas por el docente y reemplaza el archivo ajustado.</span></div>`;
            observationPanel.append(banner);
        }

        const visibleUnsorted = items.filter((item) => observationFilter === 'all' || (observationFilter === 'addressed' ? observationIsAddressed(item) : !observationIsAddressed(item)));
        const visible = sortStudentObservations(visibleUnsorted);

        if (!visible.length) {
            if (selectedObservationFileId > 0 && documentStatus === 'approved') {
                return; // Si fue aprobado y no tiene observaciones, el banner superior ya explica el estado.
            }
            const empty = document.createElement('div');
            empty.className = 'sw-obs-empty';
            const emptySubtitle = selectedObservationFileId === 0
                ? 'Selecciona un archivo para consultar sus observaciones específicas.'
                : 'Este documento no presenta observaciones registradas en la revisión.';
            empty.innerHTML = `<i class="fa-regular fa-comments" aria-hidden="true"></i><strong>Sin observaciones</strong><span>${emptySubtitle}</span>`;
            observationPanel.append(empty);
            return;
        }

        const list = document.createElement('div');
        list.className = 'sw-obs-list';
        let contextualCount = 0;
        visible.forEach((item, index) => {
            let anchorObj = null;
            if (item.selection_anchor) {
                try {
                    anchorObj = typeof item.selection_anchor === 'string' ? JSON.parse(item.selection_anchor) : item.selection_anchor;
                } catch (e) {}
            }
            const pageNum = anchorObj?.page_number || (item.location_reference ? Number((item.location_reference.match(/Página\s+(\d+)/i) || [])[1] || 0) : 0);
            const selectedText = String(anchorObj?.selected_text || '').trim();
            const hasRects = Array.isArray(anchorObj?.relative_rects) && anchorObj.relative_rects.length > 0;
            const pageNumber = Number(anchorObj?.page_number || pageNum || 0);

            const hasContextualAnchor = selectedText !== '' && Number.isInteger(pageNumber) && pageNumber >= 1 && (hasRects || Boolean(item.selection_anchor));

            let obsNumber = null;
            let colorClass = 'sw-review-color-gray';
            if (hasContextualAnchor) {
                contextualCount++;
                obsNumber = contextualCount;
                colorClass = `sw-review-color-${((obsNumber - 1) % 5) + 1}`;
            }

            const card = document.createElement('article');
            card.className = `sw-obs-card ${colorClass}${activeStudentObservationId === item.id ? ' is-active' : ''}`;
            card.dataset.observationId = String(item.id || '');
            card.style.display = 'flex';
            card.style.flexDirection = 'column';
            card.style.gap = '8px';
            card.style.padding = '12px';

            let internalEntry = anchorObj?.internal_entry || anchorObj?.entry_name;
            if (!internalEntry && item.location_reference && item.location_reference.includes('→')) {
                const parts = item.location_reference.split('→');
                if (parts.length > 1) internalEntry = parts.slice(1).join('→').trim();
            }

            const itemFileId = Number(item.file_id || 0);
            const isZip = Boolean(internalEntry) || (fileObject && (fileObject.dataset.fileExtension || '').toUpperCase() === 'ZIP');
            const isProjectLevel = itemFileId === 0;

            let typeLabel = 'General del archivo';
            if (hasContextualAnchor) {
                typeLabel = 'Sobre el texto';
            } else if (isZip) {
                typeLabel = 'General del ZIP';
            } else if (isProjectLevel) {
                typeLabel = 'General del proyecto';
            }

            // 1. Meta superior: Tipo de observación ("Sobre el texto", "General del archivo", etc.) + Badge de Estado
            const meta = document.createElement('div');
            meta.className = 'sw-obs-card-meta';
            meta.style.display = 'flex';
            meta.style.justifyContent = 'space-between';
            meta.style.alignItems = 'center';

            const typeSpan = document.createElement('span');
            typeSpan.style.fontWeight = '700';
            typeSpan.style.color = '#334155';
            typeSpan.style.fontSize = '0.85rem';
            typeSpan.style.display = 'flex';
            typeSpan.style.alignItems = 'center';

            if (hasContextualAnchor && obsNumber) {
                typeSpan.innerHTML = `<span class="sw-review-card-number-badge ${colorClass}" style="display:inline-flex;align-items:center;justify-content:center;width:20px;height:20px;border-radius:50%;background:var(--sw-color-badge-bg);color:var(--sw-color-badge-text);border:1px solid var(--sw-color-badge-border);font-size:0.75rem;font-weight:700;margin-right:6px;">${obsNumber}</span> Sobre el texto`;
            } else {
                typeSpan.textContent = typeLabel;
            }

            const statusBadge = document.createElement('b');
            statusBadge.className = `sw-obs-status ${observationIsAddressed(item) ? 'is-addressed' : 'is-pending'}`;
            statusBadge.textContent = observationIsAddressed(item) ? 'Atendida' : 'Pendiente';
            meta.append(typeSpan, statusBadge);

            // 2. Ubicación y Contexto
            const locationBox = document.createElement('div');
            locationBox.className = 'sw-obs-card-location';
            locationBox.style.fontSize = '0.78rem';
            locationBox.style.padding = '6px 9px';
            locationBox.style.borderRadius = '5px';

            if (hasContextualAnchor) {
                const textSnippet = selectedText.length > 80 ? selectedText.slice(0, 80) + '…' : selectedText;
                let catBadge = item.category && item.category !== 'General'
                    ? `<span style="font-size:0.7rem;color:var(--sw-color-badge-text, #475569);font-weight:500;background:var(--sw-color-badge-bg, #e2e8f0);border:1px solid var(--sw-color-badge-border, #cbd5e1);padding:1px 5px;border-radius:3px;white-space:nowrap;margin-left:auto;">${escapeHtml(item.category)}</span>`
                    : '';
                locationBox.innerHTML = `<div style="display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:4px;font-weight:600;color:#0f172a;margin-bottom:2px;"><span><i class="fa-solid fa-bookmark" style="color:var(--sw-color-badge-border, #2563eb);"></i> Página ${pageNum}</span>${catBadge}</div><div style="font-style:italic;color:#475569;font-size:0.75rem;line-height:1.2;word-break:break-word;">“${escapeHtml(textSnippet)}”</div>`;
                card.style.cursor = 'pointer';
                card.title = 'Haz clic para ubicar esta observación en el documento';
            } else if (internalEntry) {
                let catBadge = item.category && item.category !== 'General'
                    ? `<span style="font-size:0.7rem;color:#0369a1;font-weight:500;background:#e0f2fe;padding:1px 5px;border-radius:3px;white-space:nowrap;margin-left:auto;">${escapeHtml(item.category)}</span>`
                    : '';
                locationBox.innerHTML = `<div style="display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:4px;font-weight:600;color:#0284c7;margin-bottom:2px;"><span><i class="fa-solid fa-file-zipper"></i> Archivo interno</span>${catBadge}</div><div style="font-size:0.75rem;color:#334155;word-break:break-word;">${escapeHtml(internalEntry)}</div>`;
            } else if (isProjectLevel) {
                locationBox.innerHTML = `<div style="display:flex;align-items:center;gap:4px;"><i class="fa-solid fa-folder-tree" style="color:#64748b;"></i> <span style="font-weight:500;color:#64748b;">Observación general del expediente</span></div>`;
            } else {
                let catBadge = item.category && item.category !== 'General'
                    ? `<span style="font-size:0.7rem;color:#475569;font-weight:500;background:#e2e8f0;padding:1px 5px;border-radius:3px;white-space:nowrap;margin-left:auto;">${escapeHtml(item.category)}</span>`
                    : '';
                locationBox.innerHTML = `<div style="display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:4px;"><span style="font-weight:500;color:#64748b;"><i class="fa-solid fa-file-lines" style="color:#64748b;margin-right:4px;"></i> Observación general</span>${catBadge}</div>`;
            }

            // 3. Cuerpo del comentario
            const fullBodyText = String(item.body || '');
            const truncatedBodyText = truncateText(fullBodyText, 140);
            const body = document.createElement('p');
            body.className = 'sw-obs-card-body';
            body.style.margin = '2px 0';
            body.style.color = '#1e293b';
            body.style.fontSize = '0.86rem';
            body.style.lineHeight = '1.4';

            if (truncatedBodyText) {
                const bodyText = document.createElement('span');
                bodyText.className = 'sw-obs-body-text';
                bodyText.textContent = truncatedBodyText;

                const toggleMoreBtn = document.createElement('button');
                toggleMoreBtn.type = 'button';
                toggleMoreBtn.className = 'sw-obs-toggle-more-btn';
                toggleMoreBtn.style.cssText = 'background:none;border:none;padding:2px 0;margin-top:2px;color:#2563eb;font-size:0.75rem;font-weight:700;cursor:pointer;display:inline-flex;align-items:center;gap:4px;';
                toggleMoreBtn.innerHTML = '<i class="fa-solid fa-chevron-down" aria-hidden="true"></i> Ver más';

                let isExpanded = false;
                toggleMoreBtn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    isExpanded = !isExpanded;
                    bodyText.textContent = isExpanded ? fullBodyText : truncatedBodyText;
                    toggleMoreBtn.innerHTML = isExpanded
                        ? '<i class="fa-solid fa-chevron-up" aria-hidden="true"></i> Ocultar'
                        : '<i class="fa-solid fa-chevron-down" aria-hidden="true"></i> Ver más';
                });

                body.append(bodyText, document.createElement('br'), toggleMoreBtn);
            } else {
                body.textContent = fullBodyText;
            }

            // 4. Footer con Autor, Fecha y Botón Discreto de Alternar Estado
            const footer = document.createElement('div');
            footer.style.display = 'flex';
            footer.style.justifyContent = 'space-between';
            footer.style.alignItems = 'center';
            footer.style.gap = '8px';
            footer.style.marginTop = '4px';
            footer.style.paddingTop = '6px';
            footer.style.borderTop = '1px solid #f1f5f9';
            footer.style.flexWrap = 'wrap';

            const author = document.createElement('small');
            author.style.color = '#64748b';
            author.style.fontSize = '0.72rem';
            author.textContent = `${item.author_name || 'Docente'} · ${item.created_at || ''}`;

            const toggleBtn = document.createElement('button');
            toggleBtn.type = 'button';
            toggleBtn.className = 'sw-obs-toggle-status-btn';
            const isAddressed = observationIsAddressed(item);
            toggleBtn.style.fontSize = '0.73rem';
            toggleBtn.style.padding = '3px 7px';
            toggleBtn.style.borderRadius = '4px';
            toggleBtn.style.border = '1px solid transparent';
            toggleBtn.style.background = 'transparent';
            toggleBtn.style.color = isAddressed ? '#64748b' : '#16a34a';
            toggleBtn.style.cursor = 'pointer';
            toggleBtn.style.fontWeight = '500';
            toggleBtn.style.marginLeft = 'auto';
            toggleBtn.style.transition = 'all 0.15s ease';
            toggleBtn.innerHTML = isAddressed
                ? '<i class="fa-solid fa-rotate-left" style="margin-right:4px;font-size:0.7rem;"></i> Marcar como pendiente'
                : '<i class="fa-solid fa-check" style="margin-right:4px;font-size:0.75rem;"></i> Marcar como atendida';

            toggleBtn.addEventListener('mouseenter', () => {
                toggleBtn.style.background = isAddressed ? '#f1f5f9' : '#f0fdf4';
                toggleBtn.style.borderColor = isAddressed ? '#cbd5e1' : '#bbf7d0';
            });
            toggleBtn.addEventListener('mouseleave', () => {
                toggleBtn.style.background = 'transparent';
                toggleBtn.style.borderColor = 'transparent';
            });

            toggleBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                const nextStatus = isAddressed ? 'pending' : 'addressed';
                toggleStudentObservationStatusInBackend(item, nextStatus, toggleBtn);
            });

            footer.append(author, toggleBtn);

            card.dataset.observationId = String(item.id || '');
            if (activeStudentObservationId !== null && Number(item.id) === activeStudentObservationId) {
                card.classList.add('is-active');
            }

            card.append(meta, locationBox, body, footer);

            // CLIC EN TARJETA DE OBSERVACIÓN
            card.addEventListener('click', () => {
                setActiveStudentObservation(item.id);

                const targetFileId = Number(item.file_id || selectedObservationFileId || 0);
                const isViewerHidden = () => {
                    if (!previewStage) return true;
                    const rect = previewStage.getBoundingClientRect();
                    return rect.width === 0 || rect.height === 0;
                };

                const executeNav = () => {
                    setActiveStudentObservation(item.id, 2500);
                    if (!hasContextualAnchor || pageNum < 1) return;
                    const targetPage = previewStage?.querySelector(`[data-poc-page="${pageNum}"]`)
                        || previewStage?.querySelector(`[data-page-number="${pageNum}"]`);
                    if (targetPage) {
                        targetPage.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                    const targetOverlays = previewStage?.querySelectorAll(`[data-observation-id="${item.id}"], [data-sw-observation-highlight="${item.id}"], [data-sw-observation-badge="${item.id}"]`);
                    if (targetOverlays && targetOverlays.length > 0) {
                        targetOverlays[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
                        targetOverlays.forEach((el) => {
                            el.classList.add('sw-highlight-pulse');
                            setTimeout(() => el.classList.remove('sw-highlight-pulse'), 1600);
                        });
                    }
                };

                if (targetFileId > 0 && selectedObservationFileId !== targetFileId) {
                    const targetButton = [...manager.querySelectorAll('[data-sw-file]')].find((f) => Number(f.dataset.fileId) === targetFileId);
                    if (targetButton && !targetButton.classList.contains('is-selected')) {
                        targetButton.click();
                    }
                }

                if (hasContextualAnchor && pageNum > 0) {
                    if (isViewerHidden()) {
                        switchMobileTab('viewer');
                        let attempts = 0;
                        const checkAndScroll = () => {
                            attempts++;
                            const pageEl = previewStage?.querySelector(`[data-poc-page="${pageNum}"]`)
                                || previewStage?.querySelector(`[data-page-number="${pageNum}"]`);
                            if (pageEl || attempts > 25) {
                                executeNav();
                            } else {
                                requestAnimationFrame(checkAndScroll);
                            }
                        };
                        requestAnimationFrame(checkAndScroll);
                    } else {
                        executeNav();
                    }
                }
            });

            list.append(card);
        });
        observationPanel.append(list);
    };
    const formatObservationFilters=()=>observationPanel?.querySelectorAll('.sw-obs-filter').forEach((button)=>{if(button.dataset.formatted==='true')return;const match=button.textContent.trim().match(/^(.+?)\s*\((\d+)\)$/);if(!match)return;button.replaceChildren();const label=document.createElement('span');label.className='sw-obs-filter-label';label.textContent=match[1];const count=document.createElement('span');count.className='sw-obs-filter-count';count.textContent=`(${match[2]})`;button.append(label,count);button.dataset.formatted='true';});
    const observationFilterObserver=observationPanel&&typeof MutationObserver==='function'?new MutationObserver(formatObservationFilters):null; observationFilterObserver?.observe(observationPanel,{childList:true,subtree:true}); formatObservationFilters();
    const viewerToolbar=manager.querySelector('.sw-viewer-toolbar');
    const printButton=manager.querySelector('[data-sw-print]');
    let printFrame=null, printCleanupTimer=null, printObjectUrl='', printRequest=0;
    const cleanupPrintFrame=()=>{if(printCleanupTimer){clearTimeout(printCleanupTimer);printCleanupTimer=null;}if(printFrame){printFrame.remove();printFrame=null;}if(printObjectUrl){URL.revokeObjectURL(printObjectUrl);printObjectUrl='';}};
    const printPreview=async()=>{const url=activePdfPreview?.content_url||currentDownloadUrl||'';if(!url){toast('Selecciona un archivo visualizable para imprimir.','info');return;}const request=++printRequest;cleanupPrintFrame();toast('Preparando impresión…','info');try{const response=await fetch(url,{credentials:'same-origin'});if(request!==printRequest)return;if(!response.ok)throw new Error('El documento no está disponible para impresión.');printObjectUrl=URL.createObjectURL(await response.blob());const frame=document.createElement('iframe');frame.title='Preparando documento para impresión';Object.assign(frame.style,{position:'fixed',width:'1px',height:'1px',border:'0',opacity:'0',pointerEvents:'none',left:'-9999px',top:'-9999px'});printFrame=frame;frame.onload=()=>{try{const target=frame.contentWindow;if(!target)throw new Error('No se pudo acceder al documento de impresión.');target.focus();target.print();frame.addEventListener('afterprint',cleanupPrintFrame,{once:true});printCleanupTimer=setTimeout(cleanupPrintFrame,60000);}catch(error){console.error('No fue posible imprimir el documento.',error);cleanupPrintFrame();toast('No fue posible preparar el archivo para impresión.','error');}};frame.onerror=()=>{cleanupPrintFrame();toast('No fue posible preparar el archivo para impresión.','error');};document.body.appendChild(frame);frame.src=printObjectUrl;}catch(error){console.error('No fue posible obtener el documento para imprimir.',error);cleanupPrintFrame();toast('No fue posible preparar el archivo para impresión.','error');}};
    printButton?.addEventListener('click',printPreview);
    let projectActions=manager.querySelector('.sw-project-actions');
    if (!projectActions) {
        projectActions=document.createElement('div'); projectActions.className='sw-project-actions'; const packageUrl=manager.dataset.packageUrl||`index.php?page=project-package-download&id=${encodeURIComponent(manager.dataset.projectId||'')}`; projectActions.innerHTML=`<div class="sw-project-actions-group"><a class="sw-viewer-action" href="${packageUrl}"><i class="fa-solid fa-file-zipper" aria-hidden="true"></i> Descargar todo (.zip)</a></div><div class="sw-project-actions-file"><a class="sw-viewer-action is-file-download" data-sw-viewer-download hidden><i class="fa-solid fa-download" aria-hidden="true"></i> Descargar</a><button type="button" class="sw-viewer-action" data-sw-print disabled><i class="fa-solid fa-print" aria-hidden="true"></i> Imprimir</button></div>`; manager.querySelector('.sw-viewer-panel')?.prepend(projectActions);
    }
    const modal=manager.querySelector('[data-sw-operation-modal]'), modalTitle=manager.querySelector('[data-sw-modal-title]'), modalMessage=manager.querySelector('[data-sw-modal-message]'), modalSummary=manager.querySelector('[data-sw-modal-summary]'), modalConfirm=manager.querySelector('[data-sw-modal-confirm]'); let modalAction=null;
    const closeMenus=()=>{
        document.querySelectorAll('[data-sw-file-menu]').forEach((menu)=>{
            menu.hidden=true;
            if(menu._origin&&menu.parentElement===document.body){
                menu._origin.appendChild(menu);
            }
            menu._origin=null;
            Object.assign(menu.style,{position:'',top:'',left:'',right:'',bottom:'',zIndex:'',margin:''});
        });
        document.querySelectorAll('[data-sw-menu-trigger]').forEach((trigger)=>trigger.setAttribute('aria-expanded','false'));
    };
    const closeModal=()=>{ modal.hidden=true; modalAction=null; };
    manager.querySelectorAll('[data-sw-modal-cancel]').forEach((button)=>button.addEventListener('click',closeModal));
    modal?.addEventListener('click',(event)=>{if(event.target===modal)closeModal();});
    document.addEventListener('keydown',(event)=>{if(event.key==='Escape'){closeModal();closeMenus();}});
    const confirm=(title,message,summary,destructive,callback)=>{modalTitle.textContent=title;modalMessage.textContent=message;modalSummary.hidden=!summary;modalSummary.textContent=summary||'';modalConfirm.textContent=destructive?'Quitar':'Reemplazar';modalConfirm.classList.toggle('is-danger',destructive);modalAction=callback;modal.hidden=false;modalConfirm.focus();};
    modalConfirm?.addEventListener('click',async()=>{if(!modalAction)return;const action=modalAction;modalConfirm.disabled=true;try{await action();}finally{modalConfirm.disabled=false;}});
    const request=async(action,file,extra={})=>{const body=new FormData();body.set('_csrf',csrf);body.set('project_id',projectId);body.set('action',action);Object.entries(extra).forEach(([key,value])=>body.set(key,value));if(file)body.set('file',file);const response=await fetch(endpoint,jsonRequestInit({method:'POST',body}));const payload=await readJsonResponse(response);if(!payload.success){const error=new Error(payload.message||'No fue posible completar la operación.');error.status=response.status;throw error;}return payload;};
    const reloadDocuments=()=>{const url=new URL(workspace.dataset.projectUrl,window.location.origin);url.searchParams.set('tab','documents');window.location.assign(url);};
    const existingByName=(name)=>[...manager.querySelectorAll('[data-sw-file]')].find((item)=>item.dataset.fileName===name);
    const maxFileBytes=Number(manager.dataset.maxFileBytes||20971520), maxFileMb=Number(manager.dataset.maxFileMb||20);
    const validateFileClient=(file)=>{
        if(!file||file.size===0){ toast('El archivo seleccionado está vacío (0 bytes).',true); return false; }
        if(file.size>maxFileBytes){ toast(`El archivo supera el límite máximo permitido de ${maxFileMb} MB.`,true); return false; }
        return true;
    };
    const replaceModal = manager.querySelector('[data-sw-replace-modal]');
    const replaceCurrentName = replaceModal?.querySelector('[data-sw-replace-current-name]');
    const replaceNewName = replaceModal?.querySelector('[data-sw-replace-new-name]');
    const replaceNewLabel = replaceModal?.querySelector('[data-sw-replace-new-label]');
    const replaceNotice = replaceModal?.querySelector('[data-sw-replace-notice]');
    const replaceReasonGroup = replaceModal?.querySelector('[data-sw-replace-reason-group]');
    const replaceReasonSelect = replaceModal?.querySelector('[data-sw-replace-reason-select]');
    const replaceReasonTrigger = replaceModal?.querySelector('[data-sw-reason-trigger]');
    const replaceReasonTriggerText = replaceReasonTrigger?.querySelector('[data-sw-reason-trigger-text]');
    const replaceOtherGroup = replaceModal?.querySelector('[data-sw-replace-other-group]');
    const replaceOtherDetail = replaceModal?.querySelector('[data-sw-replace-other-detail]');
    const replaceErrorAlert = replaceModal?.querySelector('[data-sw-replace-error]');
    const replaceConfirmBtn = replaceModal?.querySelector('[data-sw-replace-confirm]');

    let activeReasonPortal = null;

    const closeReasonPortal = () => {
        if (activeReasonPortal) {
            activeReasonPortal.remove();
            activeReasonPortal = null;
        }
        if (replaceReasonTrigger) {
            replaceReasonTrigger.setAttribute('aria-expanded', 'false');
            const icon = replaceReasonTrigger.querySelector('i');
            if (icon) icon.style.transform = 'rotate(0deg)';
        }
    };

    const openReasonPortal = () => {
        closeReasonPortal();
        if (!replaceReasonTrigger || !replaceReasonSelect) return;

        const options = [...replaceReasonSelect.options];
        const rect = replaceReasonTrigger.getBoundingClientRect();

        const portal = document.createElement('div');
        portal.className = 'sw-reason-portal-listbox';
        portal.setAttribute('role', 'listbox');

        const itemHeight = 38;
        const visibleLimit = 5;
        const totalHeight = (visibleLimit * itemHeight) + 12; // 202px: exactly 5 complete options
        const spaceBelow = window.innerHeight - rect.bottom;
        const openUpwards = spaceBelow < totalHeight && rect.top > totalHeight;

        const top = openUpwards ? (rect.top - totalHeight - 4) : (rect.bottom + 4);

        Object.assign(portal.style, {
            position: 'fixed',
            top: `${Math.max(8, top)}px`,
            left: `${rect.left}px`,
            width: `${rect.width}px`,
            maxHeight: `${totalHeight}px`,
            height: `${totalHeight}px`,
            overflowY: 'auto',
            background: '#ffffff',
            border: '1px solid #cbd5e1',
            borderRadius: '12px',
            boxShadow: '0 16px 36px rgba(15, 23, 42, 0.26)',
            zIndex: '100000',
            padding: '6px 0',
            boxSizing: 'border-box',
        });

        portal.addEventListener('wheel', (e) => e.stopPropagation());
        portal.addEventListener('touchmove', (e) => e.stopPropagation());

        options.forEach((opt) => {
            const item = document.createElement('div');
            item.setAttribute('role', 'option');
            item.textContent = opt.textContent;
            item.dataset.value = opt.value;

            const isSelected = replaceReasonSelect.value === opt.value;
            Object.assign(item.style, {
                padding: '0 14px',
                height: '38px',
                minHeight: '38px',
                display: 'flex',
                alignItems: 'center',
                boxSizing: 'border-box',
                fontSize: '0.83rem',
                lineHeight: '1.2',
                color: isSelected ? '#2563eb' : '#0f172a',
                background: isSelected ? '#eff6ff' : '#ffffff',
                fontWeight: isSelected ? '600' : '400',
                cursor: 'pointer',
                transition: 'background 0.12s ease',
            });

            item.addEventListener('mouseenter', () => {
                if (replaceReasonSelect.value !== opt.value) item.style.background = '#f8fafc';
            });
            item.addEventListener('mouseleave', () => {
                if (replaceReasonSelect.value !== opt.value) item.style.background = '#ffffff';
            });

            item.addEventListener('click', (e) => {
                e.stopPropagation();
                replaceReasonSelect.value = opt.value;
                replaceReasonSelect.dispatchEvent(new Event('change'));

                if (replaceReasonTriggerText) {
                    replaceReasonTriggerText.textContent = opt.textContent;
                    replaceReasonTriggerText.style.color = opt.value === '' ? '#64748b' : '#0f172a';
                }
                closeReasonPortal();
            });

            portal.append(item);
        });

        document.body.appendChild(portal);
        activeReasonPortal = portal;

        replaceReasonTrigger.setAttribute('aria-expanded', 'true');
        const icon = replaceReasonTrigger.querySelector('i');
        if (icon) icon.style.transform = 'rotate(180deg)';
    };

    replaceReasonTrigger?.addEventListener('click', (e) => {
        e.stopPropagation();
        if (activeReasonPortal) {
            closeReasonPortal();
        } else {
            openReasonPortal();
        }
    });

    document.addEventListener('click', (e) => {
        if (!e.target.closest('[data-sw-reason-trigger]') && !e.target.closest('.sw-reason-portal-listbox')) {
            closeReasonPortal();
        }
    });

    window.addEventListener('resize', closeReasonPortal);
    window.addEventListener('scroll', (e) => {
        if (activeReasonPortal && !e.target.closest?.('.sw-reason-portal-listbox')) {
            closeReasonPortal();
        }
    }, true);

    const closeReplaceModal = () => {
        closeReasonPortal();
        document.body.classList.remove('sw-modal-open');
        if (replaceModal) replaceModal.hidden = true;
    };

    replaceModal?.querySelectorAll('[data-sw-replace-cancel]').forEach((btn) => {
        btn.addEventListener('click', closeReplaceModal);
    });

    const openReplaceModal = (file, fileId, checksum, currentFileName) => {
        if (!validateFileClient(file)) return;
        if (!replaceModal) {
            void replaceFile(file, fileId, checksum);
            return;
        }

        document.body.classList.add('sw-modal-open');

        const newFileName = file.name;
        const currentExt = currentFileName.split('.').pop().toLowerCase();
        const newExt = newFileName.split('.').pop().toLowerCase();
        const isNameOrExtChanged = (currentFileName !== newFileName) || (currentExt !== newExt);

        if (replaceCurrentName) replaceCurrentName.textContent = currentFileName;
        if (replaceNewName) replaceNewName.textContent = newFileName;

        if (replaceErrorAlert) {
            replaceErrorAlert.hidden = true;
            replaceErrorAlert.textContent = '';
        }

        if (replaceReasonGroup) {
            replaceReasonGroup.querySelectorAll('.custom-select, .custom-select-trigger').forEach((el) => el.style.setProperty('display', 'none', 'important'));
        }
        if (replaceReasonSelect) replaceReasonSelect.value = '';
        if (replaceReasonTriggerText) {
            replaceReasonTriggerText.textContent = '-- Selecciona un motivo --';
            replaceReasonTriggerText.style.color = '#64748b';
        }
        closeReasonPortal();
        if (replaceOtherDetail) replaceOtherDetail.value = '';

        if (!isNameOrExtChanged) {
            if (replaceNewLabel) replaceNewLabel.textContent = 'Nueva versión:';
            if (replaceNotice) replaceNotice.textContent = 'Se cargará una nueva versión de este documento. La versión anterior permanecerá registrada en el historial.';
            if (replaceReasonGroup) replaceReasonGroup.hidden = true;
            if (replaceOtherGroup) replaceOtherGroup.hidden = true;
            if (replaceConfirmBtn) replaceConfirmBtn.disabled = false;
        } else {
            if (replaceNewLabel) replaceNewLabel.textContent = 'Nuevo archivo:';
            if (replaceNotice) replaceNotice.textContent = 'El nombre o formato del archivo seleccionado es diferente. Indica el motivo del cambio para continuar.';
            if (replaceReasonGroup) replaceReasonGroup.hidden = false;
            if (replaceOtherGroup) replaceOtherGroup.hidden = true;
            if (replaceConfirmBtn) replaceConfirmBtn.disabled = true;
        }

        const updateValidation = () => {
            if (!isNameOrExtChanged) {
                if (replaceConfirmBtn) replaceConfirmBtn.disabled = false;
                return;
            }
            const selectedReason = replaceReasonSelect?.value || '';
            if (selectedReason === 'other') {
                if (replaceOtherGroup) replaceOtherGroup.hidden = false;
                const detailText = replaceOtherDetail?.value.trim() || '';
                if (replaceConfirmBtn) replaceConfirmBtn.disabled = detailText.length < 5;
            } else {
                if (replaceOtherGroup) replaceOtherGroup.hidden = true;
                if (replaceConfirmBtn) replaceConfirmBtn.disabled = selectedReason === '';
            }
        };

        const onSelectChange = () => updateValidation();
        const onDetailInput = () => updateValidation();

        replaceReasonSelect?.removeEventListener('change', onSelectChange);
        replaceReasonSelect?.addEventListener('change', onSelectChange);

        replaceOtherDetail?.removeEventListener('input', onDetailInput);
        replaceOtherDetail?.addEventListener('input', onDetailInput);

        const onConfirmClick = async () => {
            if (replaceConfirmBtn?.disabled) return;
            if (replaceConfirmBtn) replaceConfirmBtn.disabled = true;
            if (replaceErrorAlert) { replaceErrorAlert.hidden = true; replaceErrorAlert.textContent = ''; }

            const payload = {
                file_id: fileId,
                expected_checksum: checksum,
            };

            if (isNameOrExtChanged) {
                payload.reason_type = replaceReasonSelect?.value || '';
                if (payload.reason_type === 'other') {
                    payload.reason_detail = replaceOtherDetail?.value.trim() || '';
                }
            }

            try {
                await request('replace', file, payload);
                closeReplaceModal();
                setFlashToast('Archivo reemplazado correctamente.', 'success');
                reloadDocuments();
            } catch (error) {
                const message = error.message || 'No fue posible reemplazar el archivo.';
                if (replaceErrorAlert) {
                    replaceErrorAlert.textContent = message;
                    replaceErrorAlert.hidden = false;
                } else {
                    toast(message, true);
                }
            } finally {
                updateValidation();
            }
        };

        replaceConfirmBtn.onclick = onConfirmClick;
        replaceModal.hidden = false;
    };

    const replaceFile=async(file,fileId,checksum,reasonType='',reasonDetail='')=>{
        if(!validateFileClient(file))return;
        try{
            const extra = {file_id:fileId,expected_checksum:checksum};
            if(reasonType){
                extra.reason_type = reasonType;
                if(reasonDetail) extra.reason_detail = reasonDetail;
            }
            await request('replace',file,extra);
            setFlashToast('Archivo reemplazado correctamente.','success');
            reloadDocuments();
        }catch(error){
            const isIdentical=error.status===422||error.status===409||/mismo contenido|idéntico/i.test(error.message||'');
            if(isIdentical){
                toast(error.message||'El archivo seleccionado tiene el mismo contenido que la versión actual. Realiza las correcciones necesarias antes de reemplazarlo.',true);
                return;
            }
            toast(error.message||'No se pudo subir el archivo. Inténtalo nuevamente.',true);
        }
    };
    const upload=async(file)=>{
        if(!validateFileClient(file))return false;
        try{
            if(addButton)addButton.disabled=true;
            await request('add',file);
            setFlashToast('Archivo agregado correctamente.','success');
            return true;
        }catch(error){
            const existing=existingByName(file.name);
            if(error.status===409&&existing&&/existe/i.test(error.message)&&!/idéntico/i.test(error.message)){
                const replace=existing.closest('.sw-file-row')?.querySelector('[data-sw-replace]');
                if(replace){
                    openReplaceModal(file, replace.dataset.fileId, replace.dataset.fileChecksum, replace.dataset.fileName);
                    return false;
                }
            }
            const isIdentical=error.status===409&&/idéntico/i.test(error.message||'');
            if(isIdentical){
                toast(error.message||'El archivo no presenta cambios respecto a la versión actual.','info');
                return false;
            }
            toast(error.message||'No se pudo subir el archivo. Inténtalo nuevamente.',true);
            return false;
        }finally{
            if(addButton)addButton.disabled=false;
        }
    };
    addButton?.addEventListener('click',()=>fileInput?.click());
    fileInput?.addEventListener('change',async()=>{let changed=false;for(const file of [...fileInput.files])changed=(await upload(file))||changed;fileInput.value='';if(changed)reloadDocuments();});
    const externalFiles=(event)=>{const transfer=event.dataTransfer;if(!transfer||!transfer.files||transfer.files.length===0)return [];return [...transfer.files].filter((file)=>file instanceof File&&file.size>=0);};
    manager.addEventListener('dragover',(event)=>{if(!fileInput||externalFiles(event).length===0)return;event.preventDefault();manager.classList.add('is-dragging');}); manager.addEventListener('dragleave',()=>manager.classList.remove('is-dragging'));
    manager.addEventListener('drop',async(event)=>{const files=externalFiles(event);if(files.length===0)return;event.preventDefault();manager.classList.remove('is-dragging');let changed=false;for(const file of files)changed=(await upload(file))||changed;if(changed)reloadDocuments();});
    const positionFileMenu=(trigger,menu)=>{
        menu._origin=trigger.parentElement;
        document.body.appendChild(menu);
        menu.hidden=false;

        const rect=trigger.getBoundingClientRect();
        const menuWidth=menu.offsetWidth||160;
        const menuHeight=menu.offsetHeight||135;
        const spaceBelow=window.innerHeight-rect.bottom;
        const spaceAbove=rect.top;

        let left=rect.right-menuWidth;
        if(left<10)left=10;
        if(left+menuWidth>window.innerWidth-10){
            left=window.innerWidth-menuWidth-10;
        }

        let top;
        if(spaceBelow<menuHeight+10&&spaceAbove>spaceBelow){
            top=rect.top-menuHeight-6;
        }else{
            top=rect.bottom+6;
        }

        Object.assign(menu.style,{
            position:'fixed',
            top:`${Math.max(6,top)}px`,
            left:`${Math.max(6,left)}px`,
            right:'auto',
            bottom:'auto',
            zIndex:'100000',
            margin:'0'
        });
    };

    manager.querySelectorAll('[data-sw-menu-trigger]').forEach((trigger)=>trigger.addEventListener('click',(event)=>{
        event.stopPropagation();
        const menu=trigger.nextElementSibling||(trigger._linkedMenu&&trigger._linkedMenu.parentElement===document.body?trigger._linkedMenu:null);
        if(!menu)return;
        const wasOpen=!menu.hidden&&menu.parentElement===document.body;
        closeMenus();
        if(!wasOpen){
            trigger._linkedMenu=menu;
            positionFileMenu(trigger,menu);
            trigger.setAttribute('aria-expanded','true');
        }
    }));
    document.addEventListener('click',(event)=>{if(!event.target.closest('[data-sw-menu-trigger]')&&!event.target.closest('[data-sw-file-menu]'))closeMenus();});
    window.addEventListener('scroll',closeMenus,{passive:true,capture:true});
    window.addEventListener('resize',closeMenus,{passive:true});
    manager.querySelectorAll('[data-sw-replace]').forEach((button)=>button.addEventListener('click',()=>{closeMenus();const chooser=document.createElement('input');chooser.type='file';chooser.hidden=true;document.body.appendChild(chooser);chooser.addEventListener('change',()=>{const file=chooser.files?.[0];chooser.remove();if(!file)return;openReplaceModal(file,button.dataset.fileId,button.dataset.fileChecksum,button.dataset.fileName);});chooser.click();}));
    manager.querySelectorAll('[data-sw-remove]').forEach((button)=>button.addEventListener('click',()=>{closeMenus();confirm('Quitar archivo','Este archivo dejará de formar parte del espacio de trabajo actual.',button.dataset.fileName,true,async()=>{try{await request('remove',null,{file_id:button.dataset.fileId});closeModal();setFlashToast('Archivo quitado.','success');reloadDocuments();}catch(error){toast(error.message||'No fue posible completar la operación.',true);}});}));

    const objectUrls=new Set();
    const releasePdfDocument=()=>{pdfDocumentGeneration++;const previous=pdfDocument;pdfDocument=null;pdfDocumentKey='';pdfDocumentLoading=null;if(previous&&typeof previous.destroy==='function'){try{const destruction=previous.destroy();if(destruction&&typeof destruction.catch==='function')destruction.catch(()=>{});}catch(error){/* La selección nueva no debe quedar bloqueada por una liberación tardía. */}}};
    const cancelPreviewRequest=()=>{previewGeneration++;printRequest++;if(previewController){previewController.abort();previewController=null;}activePdfPreview=null;if(printButton)printButton.disabled=true;cleanupPrintFrame();try{releasePdfDocument();}catch(error){/* La selección nueva continúa aunque el documento anterior no se libere de inmediato. */}};
    const loadPdfDocument=async(preview)=>{const key=String(preview.content_url||'');if(!key)throw new Error('PDF privado no disponible.');if(pdfDocument&&pdfDocumentKey===key)return pdfDocument;if(pdfDocumentLoading&&pdfDocumentLoading.key===key)return pdfDocumentLoading.promise;releasePdfDocument();const generation=pdfDocumentGeneration;const promise=(async()=>{const response=await fetch(preview.content_url,{credentials:'same-origin'});if(!response.ok)throw new Error('PDF privado no disponible.');const api=await pdfjs(),bytes=new Uint8Array(await response.arrayBuffer()),document=await api.getDocument({data:bytes,standardFontDataUrl:manager.dataset.pdfjsFonts}).promise;if(generation!==pdfDocumentGeneration){document.destroy().catch(()=>{});throw new Error('Carga de PDF cancelada.');}pdfDocument=document;pdfDocumentKey=key;return document;})();pdfDocumentLoading={key,promise};try{return await promise;}finally{if(pdfDocumentLoading?.promise===promise)pdfDocumentLoading=null;}};
    const viewerZoom = manager.querySelector('[data-sw-viewer-zoom]');
    const zoomMinusBtn = manager.querySelector('[data-sw-zoom-minus]');
    const zoomFitBtn = manager.querySelector('[data-sw-zoom-fit]');
    const zoomPlusBtn = manager.querySelector('[data-sw-zoom-plus]');
    const zoomPercentageLabel = manager.querySelector('[data-sw-zoom-percentage]');

    const handleZoomChange = (newMultiplier) => {
        if (!activePdfPreview) return;
        const position = {
            top: previewStage.scrollTop / Math.max(1, previewStage.scrollHeight - previewStage.clientHeight),
            left: previewStage.scrollLeft / Math.max(1, previewStage.scrollWidth - previewStage.clientWidth)
        };
        pdfZoomMultiplier = newMultiplier;
        renderPdfPoc(activePdfPreview, position);
    };

    zoomMinusBtn?.addEventListener('click', () => handleZoomChange(Math.max(0.5, Math.round((pdfZoomMultiplier - 0.10) * 100) / 100)));
    zoomFitBtn?.addEventListener('click', () => handleZoomChange(1.0));
    zoomPlusBtn?.addEventListener('click', () => handleZoomChange(Math.min(3.0, Math.round((pdfZoomMultiplier + 0.10) * 100) / 100)));

    const clearPreview = () => {
        objectUrls.forEach((url) => URL.revokeObjectURL(url));
        objectUrls.clear();
        previewStage?.replaceChildren();
        if (previewStage) previewStage.hidden = false;
        if (viewerZoom) viewerZoom.hidden = true;
    };
    const renderPreviewState = ({
        type = 'empty',
        title = '',
        message = '',
        actionText = '',
        actionCallback = null,
        actionHref = ''
    }) => {
        clearPreview();
        if (!previewStage) return;

        const card = document.createElement('div');
        card.className = `sw-preview-state-card is-${type}`;

        const badge = document.createElement('div');
        badge.className = 'sw-state-icon-badge';

        if (type === 'empty') {
            badge.innerHTML = '<i class="fa-regular fa-file-lines main-icon" aria-hidden="true"></i><i class="fa-solid fa-eye sub-icon" aria-hidden="true"></i>';
        } else if (type === 'loading' || type === 'processing') {
            badge.innerHTML = '<i class="fa-solid fa-spinner fa-spin main-icon" aria-hidden="true"></i>';
        } else if (type === 'unsupported') {
            badge.innerHTML = '<i class="fa-solid fa-file-arrow-down main-icon" aria-hidden="true"></i><i class="fa-solid fa-eye-slash sub-icon" aria-hidden="true"></i>';
        } else if (type === 'forbidden') {
            badge.innerHTML = '<i class="fa-solid fa-file-circle-xmark main-icon" aria-hidden="true"></i>';
        } else if (type === 'error') {
            badge.innerHTML = '<i class="fa-solid fa-circle-exclamation main-icon" aria-hidden="true"></i>';
        }

        const titleEl = document.createElement('h3');
        titleEl.className = 'sw-state-title';
        titleEl.textContent = title || (type === 'empty' ? 'Visualiza tus archivos' : (type === 'loading' ? 'Preparando documento' : 'Vista del documento'));

        const msgEl = document.createElement('p');
        msgEl.className = 'sw-state-message';
        msgEl.textContent = message || '';

        card.append(badge, titleEl, msgEl);

        if (actionHref) {
            const actionLink = document.createElement('a');
            actionLink.className = 'sw-state-action-btn';
            actionLink.href = actionHref;
            actionLink.innerHTML = `<i class="fa-solid fa-download" aria-hidden="true"></i> ${actionText || 'Descargar'}`;
            card.append(actionLink);
        } else if (typeof actionCallback === 'function') {
            const actionBtn = document.createElement('button');
            actionBtn.type = 'button';
            actionBtn.className = 'sw-state-action-btn';
            actionBtn.innerHTML = `<i class="fa-solid fa-rotate-right" aria-hidden="true"></i> ${actionText || 'Reintentar'}`;
            actionBtn.addEventListener('click', (e) => {
                e.preventDefault();
                actionBtn.disabled = true;
                actionCallback();
            });
            card.append(actionBtn);
        }

        previewStage.append(card);
        previewStage.hidden = false;
    };

    let currentDownloadUrl = '';
    const previewMessage = (message, kind = '', retryCallback = null) => {
        const isError = kind === 'is-error';
        renderPreviewState({
            type: isError ? 'error' : 'unsupported',
            title: isError ? 'No pudimos abrir el archivo' : 'Vista previa no disponible',
            message: message || (isError ? 'Ocurrió un problema al preparar la vista del documento.' : 'Este formato no puede visualizarse en el visor. Puedes descargar el archivo para consultarlo.'),
            actionText: isError ? 'Reintentar vista previa' : (currentDownloadUrl ? 'Descargar archivo' : ''),
            actionCallback: retryCallback,
            actionHref: !isError ? currentDownloadUrl : ''
        });
    };
    const reviewPendingMessage = (preview) => {
        const isTeacherReviewMode = manager.classList.contains('is-teacher-review') || Boolean(manager.dataset.swReviewMode);

        renderPreviewState({
            type: 'unsupported',
            title: 'Vista de revisión pendiente',
            message: 'Este documento está guardado correctamente, pero necesitamos una copia PDF para mostrarlo durante la revisión académica.',
            actionText: isTeacherReviewMode && currentDownloadUrl ? 'Descargar archivo' : '',
            actionHref: isTeacherReviewMode ? currentDownloadUrl : ''
        });

        if (isTeacherReviewMode) {
            return;
        }

        if (!previewStage) return;
        const card = previewStage.querySelector('.sw-preview-state-card');
        if (!card) return;
        const uploadBtn = document.createElement('button');
        uploadBtn.type = 'button';
        uploadBtn.className = 'sw-state-action-btn';
        uploadBtn.innerHTML = '<i class="fa-solid fa-file-arrow-up" aria-hidden="true"></i> Subir PDF para revisión';
        const input = document.createElement('input');
        input.type = 'file';
        input.accept = '.pdf,application/pdf';
        input.hidden = true;
        uploadBtn.addEventListener('click', () => input.click());
        input.addEventListener('change', async () => {
            const file = input.files?.[0];
            input.value = '';
            if (!file) return;
            const body = new FormData();
            body.set('_csrf', reviewRepresentationCsrf);
            body.set('project_id', projectId);
            body.set('action', 'upload');
            body.set('file_id', String(preview.file_id || ''));
            body.set('file', file);
            uploadBtn.disabled = true;
            uploadBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin" aria-hidden="true"></i> Validando PDF…';
            try {
                const response = await fetch(reviewRepresentationEndpoint, { method: 'POST', credentials: 'same-origin', headers: { Accept: 'application/json' }, body });
                const payload = await readJsonResponse(response);
                if (!payload.success) throw new Error(payload.message || 'No fue posible asociar el PDF.');
                await loadPreview(currentPreviewUrl);
            } catch (error) {
                uploadBtn.disabled = false;
                uploadBtn.innerHTML = '<i class="fa-solid fa-file-arrow-up" aria-hidden="true"></i> Subir PDF para revisión';
                renderPreviewState({
                    type: 'error',
                    title: 'No fue posible asociar el PDF',
                    message: error.message || 'Ocurrió un problema al subir la copia en formato PDF.'
                });
            }
        });
        card.append(uploadBtn, input);
    };
    const renderBlocks = (blocks) => {
        const content = document.createElement('div');
        content.className = 'sw-preview-docx-content';
        (blocks || []).forEach((block) => {
            if (block.type === 'table') {
                const table = document.createElement('table');
                (block.rows || []).forEach((row) => {
                    const tr = document.createElement('tr');
                    row.forEach((cell) => {
                        const td = document.createElement('td');
                        td.textContent = cell;
                        tr.append(td);
                    });
                    table.append(tr);
                });
                content.append(table);
            } else {
                const node = document.createElement(block.type === 'heading' ? `h${Math.min(6, Math.max(1, block.level || 2))}` : 'p');
                node.textContent = block.text || '';
                content.append(node);
            }
        });
        previewStage.append(content);
    };
    const previewErrorMessage = (type) =>
        type === 'image'
            ? 'No fue posible mostrar esta imagen.'
            : (type === 'pdf'
                ? 'No fue posible abrir este PDF.'
                : 'No fue posible generar la vista previa de este documento.');
    const renderImage = async (preview) => {
        const response = await fetch(preview.content_url, { credentials: 'same-origin' });
        const contentType = response.headers.get('content-type') || '';
        if (!response.ok || !contentType.startsWith('image/')) throw new Error('Respuesta de imagen no válida.');
        const url = URL.createObjectURL(await response.blob());
        const image = document.createElement('img');
        objectUrls.add(url);
        image.src = url;
        image.alt = preview.name;
        image.draggable = false;
        image.className = 'sw-preview-image';
        await new Promise((resolve, reject) => {
            image.addEventListener('load', resolve, { once: true });
            image.addEventListener('error', reject, { once: true });
            previewStage.append(image);
        });
    };
    let pdfZoomMultiplier=1.0;
    let pdfRenderGeneration=0;
    let currentPdfRenderTask=null;
    const cancelActivePdfRenderTask=()=>{
        if(currentPdfRenderTask){
            try{ currentPdfRenderTask.cancel(); }catch(e){}
            currentPdfRenderTask=null;
        }
    };
    const renderPdfPoc=async(preview,restore=null)=>{
        activePdfPreview=preview;
        const generation=++pdfRenderGeneration;
        cancelActivePdfRenderTask();

        try {
            const pdfDoc=await loadPdfDocument(preview);
            if(generation!==pdfRenderGeneration)return;

            const api=await pdfjs();
            if(generation!==pdfRenderGeneration)return;

            const first=await pdfDoc.getPage(1);
            if(generation!==pdfRenderGeneration)return;

            const natural=first.getViewport({scale:1.0});
            const availableWidth=Math.max(280,(previewStage.clientWidth||600)-24);
            const usableWidth=availableWidth*0.92;
            pdfFitScale=Math.min(3.0,Math.max(0.2,usableWidth/natural.width));
            const effectiveScale=pdfFitScale*pdfZoomMultiplier;
            const relativePercentage=Math.round(pdfZoomMultiplier*100);

            if (viewerZoom) viewerZoom.hidden = false;
            if (zoomPercentageLabel) zoomPercentageLabel.textContent = `${relativePercentage}%`;

            const nextPages=document.createElement('div');
            nextPages.className='sw-poc-pages';

            for(let number=1;number<=pdfDoc.numPages;number++){
                if(generation!==pdfRenderGeneration)return;

                const page=await pdfDoc.getPage(number);
                if(generation!==pdfRenderGeneration)return;

                const viewport=page.getViewport({scale:effectiveScale}),host=document.createElement('div');
                host.className='sw-poc-page';
                host.dataset.pocPage=String(number);
                host.style.width=`${viewport.width}px`;
                host.style.height=`${viewport.height}px`;
                host.style.setProperty('--scale-factor',String(effectiveScale));
                const outputScale=window.devicePixelRatio||1,canvas=document.createElement('canvas');
                canvas.width=Math.floor(viewport.width*outputScale);
                canvas.height=Math.floor(viewport.height*outputScale);
                canvas.style.width=`${viewport.width}px`;
                canvas.style.height=`${viewport.height}px`;
                host.append(canvas);

                const renderTask=page.render({canvasContext:canvas.getContext('2d'),viewport,transform:outputScale===1?null:[outputScale,0,0,outputScale,0,0]});
                currentPdfRenderTask=renderTask;
                try {
                    await renderTask.promise;
                } catch (renderError) {
                    if (
                        renderError?.name === 'RenderingCancelledException' ||
                        renderError?.name === 'AbortException' ||
                        (typeof renderError?.message === 'string' && (renderError.message.includes('Rendering cancelled') || renderError.message.includes('RenderingCancelledException')))
                    ) {
                        return;
                    }
                    throw renderError;
                } finally {
                    if (currentPdfRenderTask === renderTask) {
                        currentPdfRenderTask = null;
                    }
                }
                if(generation!==pdfRenderGeneration)return;

                const text=document.createElement('div');
                text.className='sw-poc-text-layer';
                text.style.width=`${viewport.width}px`;
                text.style.height=`${viewport.height}px`;
                host.append(text);

                try {
                    await new api.TextLayer({textContentSource:await page.getTextContent(),container:text,viewport}).render();
                } catch (textLayerError) {
                    if (
                        textLayerError?.name === 'RenderingCancelledException' ||
                        textLayerError?.name === 'AbortException' ||
                        (typeof textLayerError?.message === 'string' && (textLayerError.message.includes('Rendering cancelled') || textLayerError.message.includes('RenderingCancelledException')))
                    ) {
                        return;
                    }
                    console.warn('TextLayer non-fatal warning:', textLayerError);
                }
                if(generation!==pdfRenderGeneration)return;

                nextPages.append(host);
            }

            if(generation!==pdfRenderGeneration)return;

            const sel = window.getSelection();
            if (sel && !sel.isCollapsed) {
                sel.removeAllRanges();
            }

            const existingPages=previewStage.querySelector('.sw-poc-pages');
            if(existingPages){
                existingPages.replaceWith(nextPages);
            }else{
                previewStage.append(nextPages);
            }

            if(restore){
                previewStage.scrollTop=(previewStage.scrollHeight-previewStage.clientHeight)*restore.top;
                previewStage.scrollLeft=(previewStage.scrollWidth-previewStage.clientWidth)*restore.left;
            }

            drawPocAnnotations();
            drawStudentObservationHighlights();

            if(previewStage){
                previewStage.hidden=false;
            }
            workspace.dispatchEvent(new CustomEvent('workspace:document-preview-rendered', { bubbles: true }));
        } catch (error) {
            if (
                error?.name === 'RenderingCancelledException' ||
                error?.name === 'AbortException' ||
                (typeof error?.message === 'string' && (error.message.includes('Rendering cancelled') || error.message.includes('RenderingCancelledException')))
            ) {
                return;
            }
            throw error;
        }
    };
    const renderPreview = async (preview, originalUrl = '') => {
        clearPreview();
        if (!previewStage) return;
        pdfZoomMultiplier = 1.0;
        if (preview.status !== 'ready') {
            activePdfPreview = null; if (printButton) printButton.disabled=true;
            releasePdfDocument();
            const ext = String(preview.extension || '').toLowerCase();
            const type = String(preview.preview_type || '').toLowerCase();
            const isDocx = ext === 'docx' || type === 'docx' || (originalUrl && /docx/i.test(originalUrl));

            if (preview.manual_pdf_required && !historicalPreview && reviewRepresentationEndpoint) {
                reviewPendingMessage(preview);
                return;
            }

            const extUpper = (preview.extension || ext || 'FILE').toUpperCase();
            if (preview.status === 'unsupported' || type === 'unsupported' || preview.status === 'too_large' || preview.status === 'empty') {
                renderPreviewState({
                    type: 'unsupported',
                    title: 'Formato no disponible para visualización',
                    message: preview.message || `Los archivos ${extUpper} no pueden visualizarse directamente en este visor. Puedes descargar el archivo para consultarlo.`,
                    actionText: currentDownloadUrl ? 'Descargar archivo' : '',
                    actionHref: currentDownloadUrl
                });
                return;
            }

            const retryCb = isDocx && originalUrl ? () => loadPreview(originalUrl, true) : null;
            previewMessage(preview.message || 'No fue posible abrir este documento.', 'is-error', retryCb);
            return;
        }

        const type = preview.preview_type;
        if (type === 'pdf') {
            await renderPdfPoc(preview); if (printButton) printButton.disabled=false;
        } else {
            activePdfPreview = null; if (printButton) printButton.disabled=true;
            releasePdfDocument();
            if (type === 'image') {
                await renderImage(preview);
            } else if (type === 'text' || type === 'code') {
                const pre = document.createElement('pre');
                pre.className = `sw-preview-text ${type === 'code' ? 'is-code' : ''}`;
                pre.textContent = preview.content || '';
                pre.draggable = false;
                previewStage.append(pre);
            } else if (type === 'docx') {
                activePdfPreview = preview; if (printButton) printButton.disabled = !preview.content_url;
                const note = document.createElement('p');
                note.className = 'sw-docx-notice';
                note.textContent = 'Vista previa del contenido. Descarga el archivo para consultar el formato completo.';
                previewStage.append(note);
                if (!window.JSZip || typeof window.docx?.renderAsync !== 'function' || !preview.content_url) {
                    renderBlocks(preview.blocks);
                    previewStage.hidden = false;
                    return;
                }
                const response = await fetch(preview.content_url, { credentials: 'same-origin' });
                const contentType = response.headers.get('content-type') || '';
                if (!response.ok || !/application\/(vnd\.openxmlformats-officedocument\.wordprocessingml\.document|zip|octet-stream)/i.test(contentType)) throw new Error('Respuesta DOCX no válida.');
                const data = await response.arrayBuffer();
                const host = document.createElement('div');
                host.className = 'sw-preview-docx';
                host.draggable = false;
                previewStage.append(host);
                await window.docx.renderAsync(data, host, null, { inWrapper: true, ignoreLastRenderedPageBreak: false, renderHeaders: true, renderFooters: true });
            } else {
                const extUpper = (preview.extension || 'FILE').toUpperCase();
                renderPreviewState({
                    type: 'unsupported',
                    title: 'Formato no disponible para visualización',
                    message: preview.message || `Los archivos ${extUpper} no pueden visualizarse directamente en este visor. Puedes descargar el archivo para consultarlo.`,
                    actionText: currentDownloadUrl ? 'Descargar archivo' : '',
                    actionHref: currentDownloadUrl
                });
            }
        }
        previewStage.hidden = false;
    };
    const loadPreview=async(url,retry=false)=>{
        const extClean = (currentFileExtension || '').toLowerCase().trim();
        const isImageFormat = ['jpg', 'jpeg', 'png', 'webp', 'gif'].includes(extClean);
        if (isImageFormat) {
            const extUpper = (extClean || 'JPG').toUpperCase();
            return renderPreviewState({
                type: 'unsupported',
                title: 'Formato no disponible para visualización',
                message: `Los archivos ${extUpper} no pueden visualizarse directamente en este visor. Puedes descargar el archivo para consultarlo.`,
                actionText: currentDownloadUrl ? 'Descargar archivo' : '',
                actionHref: currentDownloadUrl
            });
        }
        if(!url){
            return renderPreviewState({
                type:'unsupported',
                title:'Formato no disponible para visualización',
                message:'Este formato no puede visualizarse en el visor. Puedes descargar el archivo para consultarlo.',
                actionText:currentDownloadUrl?'Descargar archivo':'',
                actionHref:currentDownloadUrl
            });
        }
        const generation=++previewGeneration;
        previewController?.abort();
        const controller=new AbortController();
        previewController=controller;

        const isDocxUrl = Boolean(url && /docx/i.test(url));
        renderPreviewState({
            type: 'loading',
            title: isDocxUrl ? 'Preparando vista previa' : 'Preparando documento',
            message: isDocxUrl ? 'Estamos convirtiendo el documento para poder visualizarlo.' : 'Estamos preparando la vista del documento. Esto puede tomar un momento.'
        });

        let type='';
        let targetUrl=url;
        if(retry){
            try{
                const target=new URL(url,window.location.origin);
                target.searchParams.set('retry_preview','1');
                targetUrl=target.toString();
            }catch(e){
                targetUrl=url+(url.includes('?')?'&':'?')+'retry_preview=1';
            }
        }
        try{
            const payload=await readJsonResponse(await fetch(targetUrl,jsonRequestInit({signal:controller.signal})));
            if(generation!==previewGeneration)throw Object.assign(new Error('Preview superseded.'),{code:'preview_superseded'});
            const preview=payload?.data?.preview||{};
            type=preview.preview_type||'';
            if(!payload.success)throw new Error(payload.message||'No fue posible cargar la vista previa.');
            await renderPreview(preview,url);
            if(generation!==previewGeneration)throw Object.assign(new Error('Preview superseded.'),{code:'preview_superseded'});
        }catch(error){
            const isCancelled = error?.name === 'AbortError' ||
                error?.code === 'preview_superseded' ||
                error?.name === 'RenderingCancelledException' ||
                error?.name === 'AbortException' ||
                (typeof error?.message === 'string' && (error.message.includes('Rendering cancelled') || error.message.includes('RenderingCancelledException')));
            if (isCancelled) return;
            console.error('No fue posible cargar la vista previa.',error);
            if(error.code==='session_expired'){
                previewMessage(error.message,'is-error');
                return;
            }
            const extUpper = (extClean || 'FILE').toUpperCase();
            const isNonDocument = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'zip', 'rar'].includes(extClean) || type === 'image' || type === 'unsupported';
            if (isNonDocument) {
                renderPreviewState({
                    type: 'unsupported',
                    title: 'Formato no disponible para visualización',
                    message: `Los archivos ${extUpper} no pueden visualizarse directamente en este visor. Puedes descargar el archivo para consultarlo.`,
                    actionText: currentDownloadUrl ? 'Descargar archivo' : '',
                    actionHref: currentDownloadUrl
                });
                return;
            }
            const isDocx=(type==='docx')||(url&&/docx/i.test(url));
            const retryCb=isDocx?()=>loadPreview(url,true):null;
            previewMessage(previewErrorMessage(type),'is-error',retryCb);
        }finally{
            if(previewController===controller)previewController=null;
        }
    };
    const selectItem=(item)=>manager.querySelectorAll('[data-sw-file], [data-sw-zip-entry]').forEach((entry)=>entry.classList.toggle('is-selected',entry===item));
    const viewerIcon = workspace.querySelector('[data-sw-viewer-icon]');
    const getFileIconClass = (extension = '', mimeType = '', filename = '') => {
        let ext = String(extension || '').toLowerCase().trim();
        const mime = String(mimeType || '').toLowerCase().trim();
        const name = String(filename || '').toLowerCase().trim();

        if (!ext && name) {
            const parts = name.split('.');
            if (parts.length > 1) {
                ext = parts.pop().toLowerCase();
            }
        }

        if (['zip', 'rar', '7z', 'tar', 'gz', 'bz2', 'xz'].includes(ext) || mime.includes('zip') || mime.includes('compressed') || mime.includes('tar') || mime.includes('archive')) {
            return 'fa-solid fa-file-zipper';
        }

        if (['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp', 'tiff', 'ico'].includes(ext) || mime.startsWith('image/')) {
            return 'fa-solid fa-file-image';
        }

        if (ext === 'pdf' || mime === 'application/pdf') {
            return 'fa-solid fa-file-lines';
        }

        if (['doc', 'docx', 'odt', 'rtf'].includes(ext) || mime.includes('word') || mime.includes('officedocument.wordprocessingml')) {
            return 'fa-solid fa-file-word';
        }

        if (['xls', 'xlsx', 'csv', 'ods'].includes(ext) || mime.includes('excel') || mime.includes('spreadsheet')) {
            return 'fa-solid fa-file-excel';
        }

        if (['ppt', 'pptx', 'odp'].includes(ext) || mime.includes('powerpoint') || mime.includes('presentation')) {
            return 'fa-solid fa-file-powerpoint';
        }

        if (['txt', 'md', 'json', 'xml', 'log'].includes(ext) || mime.startsWith('text/')) {
            return 'fa-solid fa-file-lines';
        }

        return 'fa-regular fa-file';
    };

    let currentFileName = '';
    let currentFileExtension = '';
    const updateSelectedFileHeader=(name,extension,size,downloadUrl)=>{
        currentFileName = name || '';
        currentFileExtension = String(extension || '').toLowerCase().trim();
        currentDownloadUrl = downloadUrl || '';
        if (viewerIcon) {
            viewerIcon.className = downloadUrl ? getFileIconClass(currentFileExtension, '', currentFileName) : 'fa-solid fa-folder-open';
        }
        viewerName.textContent=name||'Visor de documentos';
        viewerMeta.textContent=downloadUrl ? `${extension||'Archivo'} · ${size||'Tamaño no disponible'}` : 'Exploración y consulta documental';
        if (viewerEmpty) viewerEmpty.hidden=true;
        if (viewerDownload) {
            viewerDownload.hidden = false;
            viewerDownload.disabled = !downloadUrl;
            if (downloadUrl) {
                viewerDownload.dataset.downloadUrl = downloadUrl;
            } else {
                delete viewerDownload.dataset.downloadUrl;
            }
        }
    };
    viewerDownload?.addEventListener('click', (event) => {
        event.preventDefault();
        const url = viewerDownload.dataset.downloadUrl;
        if (!url || viewerDownload.disabled) return;
        window.location.href = url;
    });
    let zipCanvasContext = null;
    const measureZipTextWidth = (text, font) => {
        if (!zipCanvasContext) {
            const canvas = document.createElement('canvas');
            zipCanvasContext = canvas.getContext('2d');
        }
        if (zipCanvasContext) {
            zipCanvasContext.font = font || '13.6px system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif';
            return zipCanvasContext.measureText(text).width;
        }
        return text.length * 7.5;
    };

    const fitZipEntryName = (nameElement) => {
        const fullName = nameElement.dataset.fullName;
        if (!fullName) return;

        const button = nameElement.closest('.sw-zip-entry, .sw-zip-folder-btn');
        if (!button) return;

        const buttonWidth = button.clientWidth;
        if (buttonWidth <= 0) return;

        const availableWidth = Math.max(30, buttonWidth - 36);
        const font = window.getComputedStyle(nameElement).font;

        if (measureZipTextWidth(fullName, font) <= availableWidth) {
            nameElement.textContent = fullName;
            return;
        }

        let low = 1;
        let high = fullName.length;
        let best = 1;

        while (low <= high) {
            const mid = Math.floor((low + high) / 2);
            const candidate = fullName.slice(0, mid) + '…';
            if (measureZipTextWidth(candidate, font) <= availableWidth) {
                best = mid;
                low = mid + 1;
            } else {
                high = mid - 1;
            }
        }

        nameElement.textContent = fullName.slice(0, best) + '…';
    };

    const updateAllZipNames = () => {
        workspace.querySelectorAll('.sw-zip-entry-name').forEach((el) => {
            if (!el.dataset.fullName) {
                el.dataset.fullName = el.textContent.trim();
            }
            fitZipEntryName(el);
        });
    };

    const explorerPanel = workspace.querySelector('[data-sw-explorer]');
    if (explorerPanel && typeof window.ResizeObserver === 'function') {
        const explorerObserver = new ResizeObserver(() => {
            updateAllZipNames();
        });
        explorerObserver.observe(explorerPanel);
    }

    const zipEntryUrl=(baseUrl,path)=>{if(!baseUrl)return '';try{const target=new URL(baseUrl,window.location.origin);if(path)target.searchParams.set('path',path);return target.toString();}catch(e){return baseUrl;}};
    const renderZipTree=(tree,nodes,rootButton)=>{
        tree.replaceChildren();
        const items = Array.isArray(nodes) ? nodes : (Array.isArray(nodes?.items) ? nodes.items : []);
        items.forEach((node)=>{
            const isDirectory = node.kind === 'folder' || node.kind === 'directory' || node.type === 'directory' || node.type === 'folder' || Boolean(node.is_dir);
            const entry=document.createElement('div');
            entry.className=`sw-zip-node ${isDirectory?'is-dir':''}`;
            if(isDirectory){
                const button=document.createElement('button');
                button.type='button';
                button.className='sw-zip-folder-btn';
                button.innerHTML=`<i class="fa-solid fa-folder-closed" aria-hidden="true"></i><span class="sw-zip-entry-name">${node.name}</span>`;
                const nameSpan = button.querySelector('.sw-zip-entry-name');
                if (nameSpan) {
                    nameSpan.dataset.fullName = node.name;
                }

                const tooltip = document.createElement('span');
                tooltip.className = 'sw-file-tooltip';
                tooltip.setAttribute('role', 'tooltip');
                tooltip.setAttribute('aria-hidden', 'true');
                tooltip.hidden = true;

                const tooltipName = document.createElement('span');
                tooltipName.className = 'sw-file-tooltip-name';
                tooltipName.textContent = node.name;

                const tooltipStatus = document.createElement('span');
                tooltipStatus.className = 'sw-file-tooltip-status';
                tooltipStatus.innerHTML = `<i class="fa-solid fa-folder" aria-hidden="true"></i> <span class="sw-file-tooltip-label">Carpeta</span>`;

                tooltip.append(tooltipName, tooltipStatus);
                button.append(tooltip);

                const subtree=document.createElement('div');
                subtree.className='sw-zip-subtree';
                subtree.hidden=true;
                button.addEventListener('click',async()=>{
                    const open=!subtree.hidden;
                    subtree.hidden=open;
                    button.querySelector('i').className=`fa-solid ${open?'fa-folder-closed':'fa-folder-open'}`;
                    if(!open&&subtree.dataset.loaded!=='true'){
                        await loadZipDirectory(rootButton,node.path,subtree);
                    }
                });
                entry.append(button,subtree);
            }else{
                const button=document.createElement('button');
                button.type='button';
                button.className='sw-zip-entry';
                button.dataset.swZipEntry='';
                const sizeLabel = node.size_label || node.size || '';
                button.dataset.fileSize = sizeLabel;
                const entryIconClass = getFileIconClass(node.extension, '', node.name);
                button.innerHTML=`<i class="${entryIconClass}" aria-hidden="true"></i><span class="sw-zip-entry-name">${node.name}</span>`;
                const nameSpan = button.querySelector('.sw-zip-entry-name');
                if (nameSpan) {
                    nameSpan.dataset.fullName = node.name;
                }

                const tooltip = document.createElement('span');
                tooltip.className = 'sw-file-tooltip';
                tooltip.setAttribute('role', 'tooltip');
                tooltip.setAttribute('aria-hidden', 'true');
                tooltip.hidden = true;

                const tooltipName = document.createElement('span');
                tooltipName.className = 'sw-file-tooltip-name';
                tooltipName.textContent = node.name;

                const tooltipStatus = document.createElement('span');
                tooltipStatus.className = 'sw-file-tooltip-status';
                const extLabel = (node.extension || 'Archivo').toUpperCase();
                const sizeText = sizeLabel && sizeLabel !== '—' ? ` · ${sizeLabel}` : '';
                tooltipStatus.innerHTML = `<i class="${entryIconClass}" aria-hidden="true"></i> <span class="sw-file-tooltip-label">${extLabel}${sizeText}</span>`;

                tooltip.append(tooltipName, tooltipStatus);
                button.append(tooltip);

                const previewUrl = node.preview_url || zipEntryUrl(rootButton.dataset.fileZipPreviewUrl, node.path);
                button.addEventListener('click',()=>{
                    if (button.classList.contains('is-selected')) {
                        deselectCurrentWorkspaceFile();
                        return;
                    }
                    cancelPreviewRequest();
                    selectItem(button);
                    selectedObservationFileId=Number(rootButton.dataset.fileId||0);
                    observationFilter='all';
                    renderStudentObservations();
                    updateSelectedFileHeader(`${rootButton.dataset.fileName} → ${node.name}`,node.extension||'',sizeLabel,node.download_url||zipEntryUrl(rootButton.dataset.fileZipDownloadUrl, node.path));
                    currentPreviewUrl=previewUrl;
                    if(node.type!=='directory'&&window.innerWidth<=768){ switchMobileTab('viewer'); }
                    workspace.dispatchEvent(new CustomEvent('workspace:zip-entry-opened', {
                        bubbles: true,
                        detail: {
                            parentFileId: Number(rootButton.dataset.fileId || 0),
                            parentFileName: String(rootButton.dataset.fileName || '').trim(),
                            parentChecksum: String(rootButton.dataset.fileChecksum || '').trim(),
                            entryPath: String(node.path || '').trim(),
                            entryName: String(node.name || '').trim(),
                            extension: String(node.extension || '').trim()
                        }
                    }));
                    void loadPreview(previewUrl);
                });
                entry.append(button);
            }
            tree.append(entry);
        });
        setTimeout(updateAllZipNames, 0);
    };
    const deselectCurrentWorkspaceFile = () => {
        cancelPreviewRequest();
        workspace.dispatchEvent(new CustomEvent('workspace:zip-entry-closed', { bubbles: true, detail: {} }));
        selectItem(null);
        selectedObservationFileId = 0;
        currentPreviewUrl = '';
        renderStudentObservations();
        updateSelectedFileHeader('Visor de documentos', 'Exploración y consulta documental', '', '');
        renderPreviewState({
            type: 'empty',
            title: 'Visualiza tus archivos',
            message: 'Selecciona un documento del explorador para consultar su contenido y observaciones.'
        });
        workspace.querySelectorAll('[data-sw-zip-tree]').forEach((tree) => {
            tree.hidden = true;
            tree.querySelectorAll('.sw-zip-subtree').forEach((sub) => { sub.hidden = true; });
            tree.querySelectorAll('.sw-zip-folder-btn i').forEach((icon) => { icon.className = 'fa-solid fa-folder-closed'; });
        });
    };

    const loadZipDirectory=async(rootButton,path,tree)=>{if(!tree)return;if(tree.dataset.loaded==='true'){tree.hidden=false;return;}tree.hidden=false;tree.textContent='Cargando…';try{const target=new URL(rootButton.dataset.fileZipUrl,window.location.origin);if(path)target.searchParams.set('path',path);const payload=await readJsonResponse(await fetch(target,jsonRequestInit()));if(!payload.success)throw new Error(payload.message||'No fue posible abrir el ZIP.');renderZipTree(tree,payload.data?.archive?.items||payload.data?.archive||[],rootButton);tree.dataset.loaded='true';}catch(error){console.error('No fue posible cargar la estructura del ZIP.',error);tree.textContent=error.code==='session_expired'?error.message:'No fue posible abrir esta carpeta.';}};
    const selectFile=(button)=>{
        const fileId = Number(button.dataset.fileId || 0);
        const archiveNode = button.closest('.sw-archive-node');
        const zipTree = archiveNode?.querySelector('[data-sw-zip-tree]');

        // TOGGLE UNIFICADO: Si el ZIP/archivo ya está seleccionado o expandido, deseleccionar y colapsar todo en un solo clic
        if (button.classList.contains('is-selected') || (button.dataset.fileZipUrl && zipTree && !zipTree.hidden)) {
            deselectCurrentWorkspaceFile();
            if (zipTree) {
                zipTree.hidden = true;
                zipTree.querySelectorAll('.sw-zip-subtree').forEach((sub) => { sub.hidden = true; });
                zipTree.querySelectorAll('.sw-zip-folder-btn i').forEach((icon) => { icon.className = 'fa-solid fa-folder-closed'; });
            }
            return;
        }

        cancelPreviewRequest();
        workspace.dispatchEvent(new CustomEvent('workspace:zip-entry-closed', { bubbles: true, detail: {} }));
        selectItem(button);
        selectedObservationFileId=fileId;
        observationFilter='all';
        renderStudentObservations();
        updateSelectedFileHeader(button.dataset.fileName,button.dataset.fileExtension,button.dataset.fileSize,button.dataset.fileDownload);
        if(button.dataset.fileZipUrl){
            currentPreviewUrl='';
            renderPreviewState({type:'empty',title:'Archivo ZIP seleccionado',message:'Usa el explorador de archivos para desplegar y consultar el contenido del ZIP.'});
            if (zipTree) zipTree.hidden = false;
            void loadZipDirectory(button,'',zipTree);
            return;
        }
        if(window.innerWidth<=768){ switchMobileTab('viewer'); }
        currentPreviewUrl=button.dataset.filePreview;
        void loadPreview(button.dataset.filePreview);
    };
    manager.querySelectorAll('[data-sw-file]').forEach((button)=>button.addEventListener('click',(event)=>{event.preventDefault();selectFile(event.currentTarget);}));
    let studentActiveNotePopover = null;
    let activeStudentObservationId = null;
    let activeStudentObservationTimer = null;

    const clearActiveStudentObservation = () => {
        if (activeStudentObservationTimer) {
            clearTimeout(activeStudentObservationTimer);
            activeStudentObservationTimer = null;
        }
        activeStudentObservationId = null;

        const cards = observationPanel?.querySelectorAll('.sw-obs-card') || [];
        cards.forEach((card) => card.classList.remove('is-active'));

        if (!previewStage) return;
        previewStage.querySelectorAll('.sw-review-highlight-overlay.is-active, .sw-review-highlight-badge.is-active').forEach((el) => {
            el.classList.remove('is-active');
        });
    };

    const setActiveStudentObservation = (observationId, autoClearMs = 2500) => {
        if (activeStudentObservationTimer) {
            clearTimeout(activeStudentObservationTimer);
            activeStudentObservationTimer = null;
        }

        activeStudentObservationId = observationId ? Number(observationId) : null;

        // 1. Marcar tarjeta activa en el panel
        const cards = observationPanel?.querySelectorAll('.sw-obs-card') || [];
        cards.forEach((card) => {
            const cardId = Number(card.dataset.observationId || 0);
            const isActive = activeStudentObservationId !== null && cardId === activeStudentObservationId;
            card.classList.toggle('is-active', isActive);
        });

        // 2. Marcar highlights y badges en el visor
        if (previewStage) {
            const overlays = previewStage.querySelectorAll('.sw-review-highlight-overlay');
            const badges = previewStage.querySelectorAll('.sw-review-highlight-badge');

            overlays.forEach((el) => {
                const elId = Number(el.dataset.observationId || el.dataset.swObservationHighlight || 0);
                const isActive = activeStudentObservationId !== null && elId === activeStudentObservationId;
                el.classList.toggle('is-active', isActive);
            });

            badges.forEach((el) => {
                const elId = Number(el.dataset.observationId || el.dataset.swObservationBadge || 0);
                const isActive = activeStudentObservationId !== null && elId === activeStudentObservationId;
                el.classList.toggle('is-active', isActive);
            });
        }

        // 3. Auto-retirar estado activo tras autoClearMs
        if (activeStudentObservationId !== null && autoClearMs > 0) {
            activeStudentObservationTimer = setTimeout(() => {
                clearActiveStudentObservation();
            }, autoClearMs);
        }
    };

    const removeStudentActiveNotePopover = () => {
        if (studentActiveNotePopover) {
            studentActiveNotePopover.remove();
            studentActiveNotePopover = null;
        }
    };

    const viewerPanel = workspace?.querySelector('.sw-viewer-panel');
    const floatingLayer = (() => {
        if (!viewerPanel) return null;
        let layer = viewerPanel.querySelector('[data-sw-review-floating-layer]');
        if (!layer) {
            layer = document.createElement('div');
            layer.dataset.swReviewFloatingLayer = '';
            layer.className = 'sw-review-floating-layer';
            viewerPanel.append(layer);
        }
        if (layer) {
            layer.style.pointerEvents = 'none';
        }
        return layer;
    })();

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

    const showStudentHighlightNote = (highlightEl, item, obsNumber = null, colorClass = '') => {
        removeStudentActiveNotePopover();
        if (!floatingLayer) return;

        if (item && item.id) setActiveStudentObservation(item.id);
        highlightEl.classList.add('is-active');

        const note = document.createElement('div');
        note.className = `sw-review-note-popover ${colorClass}`;
        note.setAttribute('role', 'dialog');
        note.setAttribute('aria-label', 'Observación del docente');

        let anchor = item.selection_anchor;
        try { if (typeof anchor === 'string') anchor = JSON.parse(anchor); } catch (e) { anchor = null; }

        const rawLoc = item.location_reference || (anchor?.page_number ? `Página ${anchor.page_number}` : '');
        const zipArrow = ' → ';
        const compactLoc = rawLoc.includes(zipArrow) ? rawLoc.split(zipArrow).pop() : rawLoc;

        const escapeHtml = (str) => String(str || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');

        const headerBadgeHtml = obsNumber
            ? `<span class="sw-review-card-number-badge ${colorClass}">${obsNumber}</span>`
            : '<i class="fa-solid fa-comment" aria-hidden="true"></i>';

        note.innerHTML = `
            <div class="sw-review-note-header">
                <span>${headerBadgeHtml} Observación docente</span>
                <button type="button" class="sw-review-note-close" aria-label="Cerrar nota"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
            </div>
            <p class="sw-review-note-body">${escapeHtml(item.comment || item.observation_text || item.body || '')}</p>
            <div class="sw-review-note-footer">
                <span class="sw-review-note-location">${escapeHtml(compactLoc || 'Página 1')}</span>
                <button type="button" class="sw-review-note-link">Ver en observaciones</button>
            </div>
        `;

        note.addEventListener('mousedown', (e) => e.stopPropagation());

        note.querySelector('.sw-review-note-close').addEventListener('click', () => {
            removeStudentActiveNotePopover();
        });

        note.querySelector('.sw-review-note-link').addEventListener('click', () => {
            removeStudentActiveNotePopover();
            const card = observationPanel?.querySelector(`[data-observation-id="${item.id}"]`);
            if (card) {
                card.scrollIntoView({ behavior: 'smooth', block: 'center' });
                card.classList.add('is-highlighted-temp');
                setTimeout(() => card?.classList.remove('is-highlighted-temp'), 1500);
            }
        });

        studentActiveNotePopover = note;
        floatingLayer.append(note);

        const rangeRect = highlightEl.getBoundingClientRect();
        positionPopoverElement(note, rangeRect);
    };

    document.addEventListener('pointerdown', (e) => {
        if (studentActiveNotePopover && !studentActiveNotePopover.contains(e.target) && !e.target.closest('.sw-review-highlight-overlay')) {
            removeStudentActiveNotePopover();
        }
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && studentActiveNotePopover) {
            removeStudentActiveNotePopover();
        }
    });

    const drawStudentObservationHighlights=()=>{
        if (workspace?.classList.contains('is-teacher-review')) return;
        previewStage?.querySelectorAll('[data-sw-observation-highlight], .sw-review-highlight-badge').forEach((node)=>node.remove());
        removeStudentActiveNotePopover();
        const fileId=Number(selectedObservationFileId||0);
        const selected=manager.querySelector('[data-sw-file].is-selected');
        const activeChecksum = String(selected?.dataset?.fileChecksum || selected?.dataset?.checksum || '').toLowerCase().trim();
        const version=new URL(selected?.dataset.filePreview||window.location.href,window.location.href).searchParams.get('v')||'';
        const entry=manager.querySelector('[data-sw-zip-entry].is-selected')?.dataset?.zipEntryName||'';
        let contextualCount = 0;
        const usedTopsByPage = {};

        allStudentObservations.filter((item) => {
            if (Number(item.file_id || 0) !== fileId) return false;
            const obsChecksum = String(item.file_checksum_sha256 || '').toLowerCase().trim();
            if (activeChecksum) {
                return obsChecksum === activeChecksum;
            }
            if (version) {
                return obsChecksum.startsWith(version.toLowerCase().trim());
            }
            return true;
        }).forEach((item)=>{
            let anchor=item.selection_anchor;
            try{if(typeof anchor==='string')anchor=JSON.parse(anchor);}catch(e){anchor=null;}
            if(!anchor?.page_number||!Array.isArray(anchor.relative_rects)||((anchor.internal_entry||anchor.entry_name)&&String(anchor.internal_entry||anchor.entry_name)!==entry))return;
            const page=previewStage?.querySelector(`[data-poc-page="${anchor.page_number}"]`);
            if(!page)return;

            contextualCount++;
            const obsNumber = contextualCount;
            const colorClass = `sw-review-color-${((obsNumber - 1) % 5) + 1}`;

            anchor.relative_rects.forEach((rect)=>{
                const mark=document.createElement('button');
                mark.type='button';
                mark.dataset.swObservationHighlight=String(item.id||'');
                mark.dataset.observationId=String(item.id||'');
                mark.className=`sw-review-highlight-overlay ${colorClass}`;
                mark.setAttribute('aria-label',`Ver observación docente ${obsNumber}`);
                Object.assign(mark.style,{left:`${Number(rect.left)*100}%`,top:`${Number(rect.top)*100}%`,width:`${Number(rect.width)*100}%`,height:`${Number(rect.height)*100}%`});

                mark.addEventListener('click',(e)=>{
                    e.stopPropagation();
                    showStudentHighlightNote(mark, item, obsNumber, colorClass);
                });
                page.append(mark);
            });

            // Badge al margen real de la página
            const firstRect = anchor.relative_rects[0];
            if (firstRect) {
                const badge = document.createElement('span');
                badge.className = `sw-review-highlight-badge ${colorClass}`;
                badge.dataset.swObservationBadge=String(item.id||'');
                badge.dataset.observationId=String(item.id||'');
                badge.textContent = String(obsNumber);
                badge.setAttribute('aria-label', `Ver observación docente ${obsNumber}`);

                const finalLeftCss = `calc(0% - 24px)`;

                let badgeTop = Number(firstRect.top) * 100;
                const pageKey = String(anchor.page_number);
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
                    const firstMark = page.querySelector(`[data-sw-observation-highlight="${item.id}"]`);
                    showStudentHighlightNote(firstMark || badge, item, obsNumber, colorClass);
                });

                page.append(badge);
            }
        });
        if (activeStudentObservationId !== null) {
            setActiveStudentObservation(activeStudentObservationId);
        }
    };
    const drawPocAnnotations=()=>{previewStage?.querySelectorAll('[data-poc-overlay]').forEach((node)=>node.remove());if(!annotationsVisible)return;pocAnnotations.forEach((annotation,index)=>{const page=previewStage?.querySelector(`[data-poc-page="${annotation.page}"]`);if(!page)return;annotation.rects.forEach((rect)=>{const overlay=document.createElement('span');overlay.dataset.pocOverlay='';overlay.className=`sw-poc-annotation is-${annotation.style}`;Object.assign(overlay.style,{left:`${rect.x*100}%`,top:`${rect.y*100}%`,width:`${rect.width*100}%`,height:`${rect.height*100}%`});page.append(overlay);});const marker=document.createElement('button');marker.type='button';marker.dataset.pocOverlay='';marker.className='sw-poc-marker';marker.textContent=String(index+1);marker.setAttribute('aria-label',`Abrir observación ${index+1}`);Object.assign(marker.style,{left:`${annotation.rects[0].x*100}%`,top:`${annotation.rects[0].y*100}%`});marker.addEventListener('click',()=>page.scrollIntoView({behavior:'smooth',block:'center'}));page.append(marker);});};
    const renderPocPanel=()=>{};

    // Timeline adaptativo
    const workspaceRoot = manager.closest('.student-workspace') || document.querySelector('.student-workspace');
    const timelineContainer = workspaceRoot?.querySelector('[data-sw-timeline]');
    const timelineTrack = timelineContainer?.querySelector('[data-sw-timeline-track]');
    const timelineSteps = Array.from(timelineTrack?.querySelectorAll('[data-sw-timeline-step]') || []);
    const timelineToggleBtn = timelineContainer?.querySelector('[data-sw-timeline-toggle]');

    const updateAdaptiveTimeline = () => {
        if (!timelineContainer || !timelineTrack || !timelineSteps.length) return;
        if (timelineContainer.classList.contains('is-expanded')) return;

        timelineTrack.classList.remove('is-vertical');
        timelineSteps.forEach(step => { step.style.display = ''; });

        const containerWidth = timelineContainer.clientWidth;
        if (containerWidth <= 0) return;

        let totalStepsWidth = 0;
        timelineSteps.forEach(step => {
            totalStepsWidth += (step.offsetWidth || 100);
        });

        if (totalStepsWidth <= containerWidth) {
            timelineSteps.forEach(step => { step.style.display = ''; });
            if (timelineToggleBtn) {
                timelineToggleBtn.hidden = true;
                timelineToggleBtn.setAttribute('aria-expanded', 'false');
                timelineToggleBtn.innerHTML = '<i class="fa-solid fa-chevron-right" aria-hidden="true"></i>';
            }
            return;
        }

        const btnWidth = 44;
        const availableWidth = Math.max(80, containerWidth - btnWidth);
        let currentWidth = 0;
        let visibleCount = 0;

        for (let i = 0; i < timelineSteps.length; i++) {
            const stepWidth = timelineSteps[i].offsetWidth || 100;
            if (currentWidth + stepWidth <= availableWidth) {
                currentWidth += stepWidth;
                visibleCount++;
            } else {
                break;
            }
        }

        if (visibleCount === 0) visibleCount = 1;

        timelineSteps.forEach((step, idx) => {
            step.style.display = idx < visibleCount ? '' : 'none';
        });

        if (timelineToggleBtn) {
            timelineToggleBtn.hidden = false;
            timelineToggleBtn.setAttribute('aria-expanded', 'false');
            timelineToggleBtn.setAttribute('aria-label', 'Ver todos los estados');
            timelineToggleBtn.innerHTML = '<i class="fa-solid fa-chevron-right" aria-hidden="true"></i>';
            timelineToggleBtn.title = 'Ver recorrido completo de estados';
        }
    };

    timelineToggleBtn?.addEventListener('click', () => {
        if (!timelineContainer) return;
        const isExpanded = timelineContainer.classList.toggle('is-expanded');
        timelineTrack?.classList.toggle('is-vertical', isExpanded);

        if (isExpanded) {
            timelineSteps.forEach((step) => { step.style.display = ''; });
            timelineToggleBtn.setAttribute('aria-expanded', 'true');
            timelineToggleBtn.setAttribute('aria-label', 'Ocultar recorrido completo');
            timelineToggleBtn.innerHTML = '<i class="fa-solid fa-chevron-up" aria-hidden="true"></i>';
            timelineToggleBtn.title = 'Ocultar recorrido completo de estados';
        } else {
            timelineToggleBtn.setAttribute('aria-expanded', 'false');
            timelineToggleBtn.setAttribute('aria-label', 'Ver todos los estados');
            timelineToggleBtn.innerHTML = '<i class="fa-solid fa-chevron-right" aria-hidden="true"></i>';
            timelineToggleBtn.title = 'Ver recorrido completo de estados';
            timelineTrack?.classList.remove('is-vertical');
            updateAdaptiveTimeline();
        }
    });

    if (timelineContainer && typeof ResizeObserver !== 'undefined') {
        const ro = new ResizeObserver(() => {
            if (!timelineContainer.classList.contains('is-expanded')) {
                updateAdaptiveTimeline();
            }
        });
        ro.observe(timelineContainer);
    }

    // Reset Handler de Breakpoints (Desktop <-> Mobile sin reload)
    let currentMode = window.innerWidth > 768 ? 'desktop' : 'mobile';

    const handleBreakpointTransition = () => {
        const newWidth = window.innerWidth;
        const newMode = newWidth > 768 ? 'desktop' : 'mobile';

        if (newMode !== currentMode) {
            currentMode = newMode;
            if (newMode === 'desktop') {
                delete manager.dataset.swActiveTab;
                const explorerPanel = manager.querySelector('#swExplorerPanel');
                const obsPanel = manager.querySelector('#swObservationsPanel');
                if (explorerPanel) explorerPanel.style.width = '';
                if (obsPanel) obsPanel.style.width = '';
                updateWorkspacePanelWidths();
            } else if (newMode === 'mobile') {
                const explorerPanel = workspace.querySelector('#swExplorerPanel');
                const obsPanel = workspace.querySelector('#swObservationsPanel');
                explorerPanel?.classList.remove('is-collapsed');
                obsPanel?.classList.remove('is-collapsed');
                workspace.classList.remove('sw-explorer-collapsed', 'sw-observations-collapsed');

                const openExplorerBtn = workspace.querySelector('[data-sw-open-explorer]');
                const openObsBtn = workspace.querySelector('[data-sw-open-observations]');
                if (openExplorerBtn) openExplorerBtn.hidden = true;
                if (openObsBtn) openObsBtn.hidden = true;

                const targetTab = manager.dataset.swActiveTab || 'explorer';
                switchMobileTab(targetTab);
            }
        }

        updateAdaptiveTimeline();

        if (activePdfPreview) {
            const restore = {
                top: previewStage.scrollTop / Math.max(1, previewStage.scrollHeight - previewStage.clientHeight),
                left: previewStage.scrollLeft / Math.max(1, previewStage.scrollWidth - previewStage.clientWidth)
            };
            try { void renderPdfPoc(activePdfPreview, restore); } catch (e) {}
        }
    };

    let pdfResizeTimer = null;
    window.addEventListener('resize', () => {
        clearTimeout(pdfResizeTimer);
        pdfResizeTimer = setTimeout(handleBreakpointTransition, 80);
    });

    if (window.ResizeObserver) {
        const workspaceObserver = new ResizeObserver(() => {
            requestAnimationFrame(handleBreakpointTransition);
        });
        workspaceObserver.observe(manager);
        if (timelineContainer) workspaceObserver.observe(timelineContainer);
    }

    setTimeout(updateAdaptiveTimeline, 100);

    // Envío formal a revisión: usa únicamente el endpoint y las capacidades emitidas por el servidor.
    const initStudentProjectSubmission = () => {
        const trigger = manager.querySelector('[data-sw-submit-review]');
        const submitModal = manager.querySelector('[data-sw-submit-modal]');
        const confirmButton = submitModal?.querySelector('[data-sw-submit-confirm]');
        const cancelButtons = submitModal?.querySelectorAll('[data-sw-submit-cancel]');
        const errorBox = submitModal?.querySelector('[data-sw-submit-error]');
        const errorTitle = submitModal?.querySelector('[data-sw-submit-error-title]');
        const errorList = submitModal?.querySelector('[data-sw-submit-error-list]');
        const submitEndpoint = manager.dataset.submitEndpoint || '';
        const submitCsrf = manager.dataset.submitCsrf || '';
        if (!trigger || !submitModal || !confirmButton || !submitEndpoint || !submitCsrf) return;

        let submitting = false;
        let lastFocus = null;
        const setError = (message, pending = []) => {
            if (!errorBox || !errorTitle || !errorList) return;
            errorBox.hidden = false;
            errorTitle.textContent = message;
            errorList.replaceChildren();
            pending.forEach((item) => {
                const row = document.createElement('li');
                const name = String(item?.name || 'Documento');
                const reason = String(item?.reason || '').replace(/_/g, ' ');
                row.textContent = reason ? `${name} — ${reason}` : name;
                errorList.append(row);
            });
            errorList.hidden = pending.length === 0;
        };
        const clearError = () => {
            if (errorBox) errorBox.hidden = true;
            if (errorList) { errorList.replaceChildren(); errorList.hidden = true; }
        };
        const setSubmitting = (active) => {
            submitting = active;
            confirmButton.disabled = active;
            cancelButtons?.forEach((button) => { button.disabled = active; });
            const label = confirmButton.querySelector('span');
            if (label) label.textContent = active ? 'Enviando a revisión…' : 'Enviar a revisión';
            confirmButton.querySelector('i')?.classList.toggle('fa-spin', active);
        };
        const close = () => {
            if (submitting) return;
            submitModal.hidden = true;
            clearError();
            lastFocus?.focus();
        };
        const updateStatusUi = (result) => {
            const status = String(result.project_status || 'under_review');
            const capabilities = result.capabilities || {};
            workspace.dataset.swProjectStatus = status;
            const labels = { development: 'En desarrollo', under_review: 'En revisión', approved: 'Aprobado', defense: 'En tribunal', tribunal_approved: 'Aprobado por el tribunal', published: 'Publicado' };
            const badge = workspace.querySelector('[data-sw-project-status-badge]');
            if (badge) {
                badge.className = `sw-badge-status is-${status}`;
                badge.replaceChildren();
                const icon = document.createElement('i');
                icon.className = 'fa-solid fa-circle-dot'; icon.setAttribute('aria-hidden', 'true');
                badge.append(icon, document.createTextNode(labels[status] || 'En revisión'));
            }
            const sequence = ['development', 'under_review', 'approved', 'defense', 'tribunal_approved', 'published'];
            const currentIndex = sequence.indexOf(status);
            timelineSteps.forEach((step) => {
                const index = sequence.indexOf(step.dataset.swStatusStep || '');
                const current = index === currentIndex;
                const completed = index >= 0 && currentIndex >= 0 && index < currentIndex;
                step.classList.toggle('is-current', current);
                step.classList.toggle('is-completed', completed);
                step.classList.toggle('is-pending', !current && !completed);
                const icon = step.querySelector('.sw-ct-node i');
                if (icon) icon.className = `fa-solid ${current ? 'fa-circle-dot' : (completed ? 'fa-circle-check' : 'fa-circle')}`;
            });
            updateAdaptiveTimeline();
            if (capabilities.edit_information === false || status === 'under_review') workspace.querySelector('[data-sw-edit-info-open]')?.remove();
            if (capabilities.manage_workspace_files === false || status === 'under_review') {
                manager.querySelector('[data-sw-add-files]')?.remove();
                manager.querySelector('[data-sw-file-input]')?.remove();
                manager.querySelectorAll('[data-sw-menu-trigger]').forEach((button) => button.remove());
                manager.querySelectorAll('[data-sw-file-menu]').forEach((menu) => { menu.hidden = true; });
            }
            if (capabilities.send_for_review === false || status === 'under_review') trigger.remove();
            const historyPane = workspace.querySelector('#swTab-history');
            if (!historyPane) return;
            let list = historyPane.querySelector('.sw-history-list');
            if (!list) {
                const empty = historyPane.querySelector('.sw-empty-state');
                list = document.createElement('div'); list.className = 'sw-history-list';
                empty?.replaceWith(list);
            }
            const event = document.createElement('article'); event.className = 'sw-history-event is-delivery';
            const main = document.createElement('div'); main.className = 'sw-history-main';
            const title = document.createElement('strong'); title.className = 'sw-history-title';
            title.innerHTML = '<i class="fa-solid fa-paper-plane" aria-hidden="true"></i> Entrega documental registrada';
            const description = document.createElement('p'); description.className = 'sw-history-desc';
            description.textContent = `Entrega ${result.delivery_number}: ${result.submitted_file_count} documento(s) enviado(s) a revisión.`;
            main.append(title, description);
            event.append(main); list.prepend(event);
        };
        const openSubmitModal = (e) => {
            if (e && typeof e.preventDefault === 'function') e.preventDefault();
            const res = canResubmitCorrections();
            if (res.hasDeliveries && !res.eligible) {
                const message = `Debes corregir todos los documentos observados antes de reenviar el proyecto. (Correcciones realizadas: ${res.completed} de ${res.totalNeeded})`;
                showVisualToast(message, 'warning', 'Correcciones pendientes');
                return false;
            }
            lastFocus = trigger;
            clearError(); setSubmitting(false);
            submitModal.hidden = false;
            submitModal.querySelector('[data-sw-submit-cancel]')?.focus();
            return true;
        };

        trigger.addEventListener('click', openSubmitModal);
        cancelButtons?.forEach((button) => button.addEventListener('click', close));
        submitModal.addEventListener('click', (event) => { if (event.target === submitModal) close(); });
        document.addEventListener('keydown', (event) => { if (event.key === 'Escape' && !submitModal.hidden && !submitting) { event.preventDefault(); close(); } });
        confirmButton.addEventListener('click', async () => {
            if (submitting) return;
            const res = canResubmitCorrections();
            if (res.hasDeliveries && !res.eligible) {
                submitModal.hidden = true;
                const message = `Debes corregir todos los documentos observados antes de reenviar el proyecto. (Correcciones realizadas: ${res.completed} de ${res.totalNeeded})`;
                showVisualToast(message, 'warning', 'Correcciones pendientes');
                return;
            }
            clearError(); setSubmitting(true);
            const body = new FormData(); body.set('project_id', String(projectId)); body.set('_csrf', submitCsrf);
            try {
                const response = await fetch(submitEndpoint, jsonRequestInit({ method: 'POST', body }));
                const payload = await readJsonResponse(response);
                if (!payload.success) throw new Error(payload.message || 'No fue posible enviar los documentos a revisión.');
                const result = payload.data || {};
                submitModal.hidden = true;
                updateStatusUi(result);
                const submittedFileCount = Number(result.submitted_file_count || 0);
                const submissionMessage = submittedFileCount === 1
                    ? '1 documento fue enviado al tutor para su revisión.'
                    : `${submittedFileCount} documentos fueron enviados al tutor para su revisión.`;
                showVisualToast(submissionMessage, 'success', 'Proyecto enviado a revisión');
            } catch (error) {
                const isNetworkError = error instanceof TypeError || error.name === 'TypeError' || (error.message || '').includes('fetch') || (error.message || '').includes('NetworkError');
                const status = Number(error.status || 0);
                const data = error.data || {};
                const message = isNetworkError
                    ? 'No fue posible completar el envío. Verifica tu conexión e inténtalo nuevamente.'
                    : status === 419 ? 'La sesión del formulario venció. Actualiza la página e inténtalo nuevamente.'
                    : status === 409 ? 'El proyecto ya fue enviado a revisión. Actualiza la página para consultar su estado actual.'
                    : status === 401 ? 'Tu sesión expiró. Inicia sesión nuevamente para continuar.'
                    : status === 403 ? 'No tienes autorización para enviar este proyecto a revisión.'
                    : (error.message || 'No fue posible enviar los documentos a revisión.');
                setError(message, Array.isArray(data.pending_review_representations) ? data.pending_review_representations : []);
                showVisualToast(message, 'error');
            } finally {
                setSubmitting(false);
            }
        });
    };
    initStudentProjectSubmission();

    const truncateText = (text, maxLength = 140) => {
        const str = String(text || '').trim();
        if (str.length <= maxLength) return null;
        const truncated = str.slice(0, maxLength).replace(/\s+\S*$/, '');
        return truncated.length > 0 ? truncated + '…' : str.slice(0, maxLength) + '…';
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

    const renderHistoricalObservations = (items, preview) => {
        if (!observationPanel) return;
        observationPanel.replaceChildren();

        if (!items || items.length === 0) {
            const empty = document.createElement('p');
            empty.className = 'sw-empty-state';
            empty.textContent = 'No hay observaciones registradas para esta versión.';
            observationPanel.append(empty);
            return;
        }

        const container = document.createElement('div');
        container.className = 'sw-obs-list-container sw-historical-obs-list';
        let contextualCount = 0;

        const sortedItems = [...items].sort((a, b) => {
            let anchorA = a.selection_anchor;
            try { if (typeof anchorA === 'string') anchorA = JSON.parse(anchorA); } catch (e) { anchorA = null; }
            const isContextualA = Boolean(anchorA?.page_number && (anchorA.selected_text || (Array.isArray(anchorA.relative_rects) && anchorA.relative_rects.length > 0)));

            let anchorB = b.selection_anchor;
            try { if (typeof anchorB === 'string') anchorB = JSON.parse(anchorB); } catch (e) { anchorB = null; }
            const isContextualB = Boolean(anchorB?.page_number && (anchorB.selected_text || (Array.isArray(anchorB.relative_rects) && anchorB.relative_rects.length > 0)));

            if (isContextualA !== isContextualB) {
                return isContextualA ? 1 : -1;
            }
            return 0;
        });

        sortedItems.forEach((item) => {
            let anchor = item.selection_anchor;
            try { if (typeof anchor === 'string') anchor = JSON.parse(anchor); } catch (e) { anchor = null; }

            const displayInfo = resolveObservationDisplayType(item, preview, anchor);
            const isContextual = displayInfo.typeKey === 'contextual';
            const isZipEntry = displayInfo.typeKey === 'zip';
            const categoryLabel = String(item.category || 'Observación');

            let colorClass = '';
            let obsNumber = 0;
            if (isContextual) {
                contextualCount++;
                obsNumber = contextualCount;
                colorClass = `sw-review-color-${((obsNumber - 1) % 5) + 1}`;
            }

            const card = document.createElement('article');
            card.className = `sw-obs-card sw-historical-obs-card ${colorClass}`;
            card.dataset.observationId = String(item.id || '');

            let typeBadgeClass = displayInfo.badgeClass;
            let typeBadgeText = isContextual ? `#${obsNumber} · ${displayInfo.typeLabel}` : displayInfo.typeLabel;
            let iconClass = displayInfo.iconClass;

            const header = document.createElement('header');
            header.className = 'sw-obs-card-header';
            header.innerHTML = `<div class="sw-obs-type-badge ${typeBadgeClass}"><i class="${iconClass}" aria-hidden="true"></i> <span>${typeBadgeText}</span></div><span class="sw-obs-cat-tag">${escapeHtml(categoryLabel)}</span>`;
            card.append(header);

            if (isZipEntry) {
                const zipRef = document.createElement('div');
                zipRef.className = 'sw-obs-zip-ref';
                zipRef.innerHTML = `<i class="fa-solid fa-file-lines" aria-hidden="true"></i> <strong>Archivo interno:</strong> ${escapeHtml(String(item.location_reference))}`;
                card.append(zipRef);
            } else if (item.location_reference && !isContextual) {
                const locRef = document.createElement('div');
                locRef.className = 'sw-obs-loc-ref';
                locRef.textContent = String(item.location_reference);
                card.append(locRef);
            }

            if (isContextual && anchor?.page_number) {
                const selectedQuote = String(anchor.selected_text || '');
                const truncatedQuote = truncateText(selectedQuote, 90);
                const contextRef = document.createElement('div');
                contextRef.className = 'sw-obs-context-ref';

                if (truncatedQuote) {
                    const quoteText = document.createElement('span');
                    quoteText.className = 'sw-obs-quote-text';
                    quoteText.textContent = `Página ${anchor.page_number}: "${truncatedQuote}"`;
                    contextRef.append(quoteText);

                    const quoteToggle = document.createElement('button');
                    quoteToggle.type = 'button';
                    quoteToggle.className = 'sw-obs-quote-toggle';
                    quoteToggle.innerHTML = '<i class="fa-solid fa-chevron-down" aria-hidden="true"></i> Ver más';
                    let quoteExpanded = false;

                    quoteToggle.addEventListener('click', (e) => {
                        e.stopPropagation();
                        quoteExpanded = !quoteExpanded;
                        quoteText.textContent = quoteExpanded
                            ? `Página ${anchor.page_number}: "${selectedQuote}"`
                            : `Página ${anchor.page_number}: "${truncatedQuote}"`;
                        quoteToggle.innerHTML = quoteExpanded
                            ? '<i class="fa-solid fa-chevron-up" aria-hidden="true"></i> Ver menos'
                            : '<i class="fa-solid fa-chevron-down" aria-hidden="true"></i> Ver más';
                    });
                    contextRef.append(quoteToggle);
                } else {
                    contextRef.innerHTML = `<i class="fa-solid fa-bookmark" aria-hidden="true"></i> Página ${anchor.page_number}${selectedQuote ? ': "' + escapeHtml(selectedQuote) + '"' : ''}`;
                }
                card.append(contextRef);
            }

            const fullBodyText = String(item.body || '');
            const truncatedBodyText = truncateText(fullBodyText, 140);
            const body = document.createElement('div');
            body.className = 'sw-obs-card-body';

            if (truncatedBodyText) {
                const bodyContent = document.createElement('span');
                bodyContent.className = 'sw-obs-body-text';
                bodyContent.textContent = truncatedBodyText;
                body.append(bodyContent);

                const toggleBtn = document.createElement('button');
                toggleBtn.type = 'button';
                toggleBtn.className = 'sw-obs-toggle-btn';
                toggleBtn.innerHTML = '<i class="fa-solid fa-chevron-down" aria-hidden="true"></i> Ver más';

                let expanded = false;
                toggleBtn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    expanded = !expanded;
                    bodyContent.textContent = expanded ? fullBodyText : truncatedBodyText;
                    toggleBtn.innerHTML = expanded
                        ? '<i class="fa-solid fa-chevron-up" aria-hidden="true"></i> Ver menos'
                        : '<i class="fa-solid fa-chevron-down" aria-hidden="true"></i> Ver más';
                    card.classList.toggle('is-expanded', expanded);
                });
                body.append(toggleBtn);
            } else {
                body.textContent = fullBodyText;
            }
            card.append(body);

            const footer = document.createElement('footer');
            footer.className = 'sw-obs-card-footer';
            const author = String(item.author_name || 'Docente Evaluador');
            const dateStr = item.created_at ? new Date(item.created_at).toLocaleDateString('es-EC', { day: '2-digit', month: 'short', year: 'numeric' }) : '';
            footer.innerHTML = `<span><i class="fa-solid fa-user-pen" aria-hidden="true"></i> ${escapeHtml(author)}</span>${dateStr ? `<span><i class="fa-regular fa-clock" aria-hidden="true"></i> ${dateStr}</span>` : ''}`;
            card.append(footer);

            if (isContextual && anchor?.page_number) {
                card.style.cursor = 'pointer';
                card.addEventListener('click', () => {
                    const pageEl = previewStage?.querySelector(`[data-poc-page="${anchor.page_number}"]`);
                    if (pageEl) {
                        pageEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        const highlights = pageEl.querySelectorAll(`[data-observation-id="${item.id}"]`);
                        highlights.forEach((hl) => {
                            hl.classList.add('sw-highlight-flash');
                            setTimeout(() => hl.classList.remove('sw-highlight-flash'), 2000);
                        });
                    }
                });
            }

            container.append(card);
        });

        observationPanel.append(container);
    };

    if (historicalPreview) {
        (async () => {
            try {
                const payload = await readJsonResponse(await fetch(historicalPreview, jsonRequestInit()));
                if (!payload.success) throw new Error(payload.message || 'No fue posible cargar la versión.');

                const preview = payload.data.preview;
                const items = preview.observations || [];
                const ext = String(preview.extension || '').toLowerCase();
                const isZip = ext === 'zip' || preview.preview_type === 'zip';

                updateSelectedFileHeader(preview.original_name, `Versión ${preview.version_number}`, 'Historial', '');

                allStudentObservations = items;
                selectedObservationFileId = Number(preview.file_id || 0);

                if (viewerDownload) {
                    viewerDownload.disabled = false;
                    viewerDownload.onclick = () => {
                        window.location.href = preview.content_url || route('project-file-version-download') + `&project_id=${projectId}&version_id=${preview.version_id}`;
                    };
                }

                if (isZip) {
                    releasePdfDocument();
                    activePdfPreview = null;
                    if (printButton) printButton.disabled = true;

                    renderPreviewState({
                        type: 'info',
                        title: 'Archivo ZIP de versión histórica',
                        message: 'El contenido interno de paquetes ZIP no se explora en versiones anteriores.',
                        actionText: 'Descargar paquete ZIP',
                        actionHref: preview.content_url
                    });
                } else {
                    await renderPreview({
                        status: 'ready',
                        preview_type: 'pdf',
                        content_url: preview.content_url,
                        name: preview.original_name,
                        file_id: preview.file_id
                    });
                }

                renderHistoricalObservations(items, preview);
            } catch (error) {
                console.error(error);
                previewMessage(error.code === 'session_expired' ? error.message : 'No fue posible cargar la versión histórica.', 'is-error');
            }
        })();
    } else {
        renderStudentObservations();
        renderPreviewState({
            type: 'empty',
            title: 'Visualiza tus archivos',
            message: 'Selecciona un documento del panel Archivos para consultarlo aquí.'
        });
    }

    // ==========================================================================
    // FASE 3: EDITOR DE INFORMACIÓN DEL PROYECTO DEL ESTUDIANTE
    // ==========================================================================
    const initStudentProjectEditor = () => {
        const modal = document.querySelector('#swEditProjectModal');
        const openBtn = document.querySelector('[data-sw-edit-info-open]');
        const confirmModal = document.querySelector('#swUnsavedChangesConfirm');
        const form = document.querySelector('#swEditProjectForm');
        const titleInput = document.querySelector('#swEditProjectTitleInput');
        const summaryInput = document.querySelector('#swEditProjectSummaryInput');
        const alertEl = document.querySelector('#swEditProjectAlert');
        const dirtyIndicator = document.querySelector('[data-sw-dirty-indicator]');
        const submitBtn = document.querySelector('[data-sw-edit-submit]');
        const config = document.querySelector('#swStudentProjectEditorConfig');

        if (!modal || !openBtn || !form || !config) return;

        const teachersCatalogNode = document.querySelector('#swStudentTeachersCatalog');
        const authorsCatalogNode = document.querySelector('#swStudentAuthorsCatalog');
        const initialPayloadNode = document.querySelector('#swStudentProjectInitialPayload');

        let teachersCatalog = [];
        let studentsCatalog = [];
        let initialPayload = null;

        try { teachersCatalog = JSON.parse(teachersCatalogNode?.textContent || '[]'); } catch { teachersCatalog = []; }
        try { studentsCatalog = JSON.parse(authorsCatalogNode?.textContent || '[]'); } catch { studentsCatalog = []; }
        try { initialPayload = JSON.parse(initialPayloadNode?.textContent || '{}'); } catch { initialPayload = {}; }

        const currentUserId = parseInt(config.dataset.currentUserId || '0', 10);
        const saveEndpoint = config.dataset.save || '';
        const projectId = parseInt(config.dataset.projectId || '0', 10);

        let activeState = {
            title: '',
            summary: '',
            tutoring_user_ids: [],
            tutoring_primary_id: 0,
            author_user_ids: [],
            author_leader_id: 0,
        };

        let initialStateString = '';
        let isSaving = false;
        let selectedTutors = [];
        let selectedAuthors = [];

        // Elements for Tutoring
        const tutorAddBtn = form.querySelector('[data-sw-tutor-add-trigger]');
        const tutorPickerPanel = form.querySelector('[data-sw-tutor-picker]');
        const tutorSearchInput = form.querySelector('[data-sw-tutor-search]');
        const tutorPickerOptions = form.querySelector('[data-sw-tutor-picker-options]');
        const tutorsList = form.querySelector('[data-sw-tutors-list]');

        // Elements for Authors
        const authorAddBtn = form.querySelector('[data-sw-author-add-trigger]');
        const authorPickerPanel = form.querySelector('[data-sw-author-picker]');
        const authorSearchInput = form.querySelector('[data-sw-author-search]');
        const authorPickerOptions = form.querySelector('[data-sw-author-picker-options]');
        const authorsList = form.querySelector('[data-sw-authors-list]');

        const showAlert = (message, isError = true) => {
            if (!alertEl) return;
            alertEl.textContent = message;
            alertEl.className = `sw-edit-project-alert ${isError ? 'error' : 'success'}`;
            alertEl.hidden = !message;
        };

        const hideAlert = () => {
            if (!alertEl) return;
            alertEl.hidden = true;
            alertEl.textContent = '';
        };

        const getComparableState = () => {
            const tutorIds = [...selectedTutors.map(t => parseInt(t.user_id, 10))].sort((a, b) => a - b);
            const authorIds = [...selectedAuthors.map(a => parseInt(a.user_id, 10))].sort((a, b) => a - b);
            const primaryTutor = parseInt(activeState.tutoring_primary_id || '0', 10);
            const leaderAuthor = parseInt(activeState.author_leader_id || '0', 10);

            return JSON.stringify([
                (titleInput.value || '').trim().normalize('NFC'),
                (summaryInput.value || '').trim().normalize('NFC'),
                tutorIds,
                primaryTutor,
                authorIds,
                leaderAuthor
            ]);
        };

        const syncChangeState = () => {
            const currentState = getComparableState();
            const isDirty = Boolean(initialStateString) && currentState !== initialStateString;

            if (dirtyIndicator) dirtyIndicator.hidden = !isDirty;
            if (submitBtn) submitBtn.disabled = !isDirty || isSaving;
            return isDirty;
        };

        // Render Tutors List
        const renderTutorsList = () => {
            if (!tutorsList) return;
            tutorsList.replaceChildren();

            const primaryId = parseInt(activeState.tutoring_primary_id || '0', 10);

            selectedTutors.forEach((tutor) => {
                const uId = parseInt(tutor.user_id, 10);
                const isPrimary = uId === primaryId;

                const card = document.createElement('div');
                card.className = 'sw-edit-project-card';

                const main = document.createElement('div');
                main.className = 'sw-edit-project-card-main';

                const avatar = document.createElement('span');
                avatar.className = 'sw-edit-project-avatar';
                const initials = (tutor.full_name || '?').trim().split(/\s+/).slice(0, 2).map(p => p[0]).join('').toUpperCase();
                avatar.textContent = initials;

                const details = document.createElement('div');
                details.className = 'sw-edit-project-card-details';
                const name = document.createElement('strong');
                name.textContent = tutor.full_name || 'Docente';
                const meta = document.createElement('small');
                meta.textContent = tutor.email || 'Tutor del proyecto';
                details.append(name, meta);
                main.append(avatar, details);

                const actions = document.createElement('div');
                actions.className = 'sw-edit-project-card-actions';

                if (isPrimary) {
                    const badge = document.createElement('span');
                    badge.className = 'sw-edit-project-badge-leader';
                    badge.innerHTML = '<i class="fa-solid fa-star" aria-hidden="true"></i> Principal';
                    actions.append(badge);
                } else {
                    const makePrimaryBtn = document.createElement('button');
                    makePrimaryBtn.type = 'button';
                    makePrimaryBtn.className = 'sw-edit-project-add-btn';
                    makePrimaryBtn.textContent = 'Hacer principal';
                    makePrimaryBtn.addEventListener('click', () => {
                        activeState.tutoring_primary_id = uId;
                        renderTutorsList();
                        syncChangeState();
                    });
                    actions.append(makePrimaryBtn);
                }

                if (selectedTutors.length > 1) {
                    const removeBtn = document.createElement('button');
                    removeBtn.type = 'button';
                    removeBtn.className = 'sw-edit-project-btn-icon';
                    removeBtn.setAttribute('aria-label', `Quitar tutor ${tutor.full_name}`);
                    removeBtn.innerHTML = '<i class="fa-solid fa-trash-can" aria-hidden="true"></i>';
                    removeBtn.addEventListener('click', () => {
                        selectedTutors = selectedTutors.filter(t => parseInt(t.user_id, 10) !== uId);
                        if (isPrimary && selectedTutors.length > 0) {
                            activeState.tutoring_primary_id = parseInt(selectedTutors[0].user_id, 10);
                        }
                        renderTutorsList();
                        syncChangeState();
                    });
                    actions.append(removeBtn);
                }

                card.append(main, actions);
                tutorsList.append(card);
            });
        };

        // Render Authors List
        const renderAuthorsList = () => {
            if (!authorsList) return;
            authorsList.replaceChildren();

            const leaderId = parseInt(activeState.author_leader_id || '0', 10);

            selectedAuthors.forEach((author) => {
                const uId = parseInt(author.user_id, 10);
                const isLeader = uId === leaderId;
                const isSelf = uId === currentUserId;

                const card = document.createElement('div');
                card.className = 'sw-edit-project-card';

                const main = document.createElement('div');
                main.className = 'sw-edit-project-card-main';

                const avatar = document.createElement('span');
                avatar.className = 'sw-edit-project-avatar';
                const initials = (author.full_name || '?').trim().split(/\s+/).slice(0, 2).map(p => p[0]).join('').toUpperCase();
                avatar.textContent = initials;

                const details = document.createElement('div');
                details.className = 'sw-edit-project-card-details';
                const name = document.createElement('strong');
                name.textContent = author.full_name || 'Estudiante';
                const meta = document.createElement('small');
                meta.textContent = [author.institutional_code, author.username && `@${author.username}`].filter(Boolean).join(' · ') || 'Estudiante integrante';
                details.append(name, meta);
                main.append(avatar, details);

                const actions = document.createElement('div');
                actions.className = 'sw-edit-project-card-actions';

                if (isSelf) {
                    const selfBadge = document.createElement('span');
                    selfBadge.className = 'sw-edit-project-badge-self';
                    selfBadge.textContent = 'Tú';
                    actions.append(selfBadge);
                }

                if (isLeader) {
                    const badge = document.createElement('span');
                    badge.className = 'sw-edit-project-badge-leader';
                    badge.innerHTML = '<i class="fa-solid fa-crown" aria-hidden="true"></i> Líder';
                    actions.append(badge);
                } else {
                    const makeLeaderBtn = document.createElement('button');
                    makeLeaderBtn.type = 'button';
                    makeLeaderBtn.className = 'sw-edit-project-add-btn';
                    makeLeaderBtn.textContent = 'Hacer líder';
                    makeLeaderBtn.addEventListener('click', () => {
                        activeState.author_leader_id = uId;
                        renderAuthorsList();
                        syncChangeState();
                    });
                    actions.append(makeLeaderBtn);
                }

                // REGLA 0 y 6: El estudiante propio NO se puede eliminar
                const removeBtn = document.createElement('button');
                removeBtn.type = 'button';
                removeBtn.className = 'sw-edit-project-btn-icon';
                removeBtn.setAttribute('aria-label', `Quitar integrante ${author.full_name}`);
                removeBtn.innerHTML = '<i class="fa-solid fa-trash-can" aria-hidden="true"></i>';

                if (isSelf) {
                    removeBtn.disabled = true;
                    removeBtn.title = 'No puedes retirarte a ti mismo del proyecto';
                } else {
                    removeBtn.addEventListener('click', () => {
                        selectedAuthors = selectedAuthors.filter(a => parseInt(a.user_id, 10) !== uId);
                        if (isLeader && selectedAuthors.length > 0) {
                            activeState.author_leader_id = parseInt(selectedAuthors[0].user_id, 10);
                        }
                        renderAuthorsList();
                        syncChangeState();
                    });
                }
                actions.append(removeBtn);

                card.append(main, actions);
                authorsList.append(card);
            });
        };

        // Render Tutor Picker Options
        const renderTutorPickerOptions = () => {
            if (!tutorPickerOptions) return;
            tutorPickerOptions.replaceChildren();

            const query = (tutorSearchInput?.value || '').trim().toLowerCase();
            const selectedIds = new Set(selectedTutors.map(t => parseInt(t.user_id, 10)));

            const available = teachersCatalog.filter(t => {
                const uId = parseInt(t.id || t.user_id, 10);
                if (selectedIds.has(uId)) return false;
                if (!query) return true;
                const searchable = `${t.full_name} ${t.email} ${t.username}`.toLowerCase();
                return searchable.includes(query);
            });

            if (available.length === 0) {
                const empty = document.createElement('div');
                empty.className = 'sw-edit-project-picker-option';
                empty.style.color = '#94a3b8';
                empty.style.cursor = 'default';
                empty.textContent = query ? 'No se encontraron docentes.' : 'Todos los docentes disponibles han sido añadidos.';
                tutorPickerOptions.append(empty);
                return;
            }

            available.forEach((teacher) => {
                const uId = parseInt(teacher.id || teacher.user_id, 10);
                const option = document.createElement('button');
                option.type = 'button';
                option.className = 'sw-edit-project-picker-option';
                option.setAttribute('role', 'option');

                const info = document.createElement('div');
                info.className = 'info';
                const name = document.createElement('strong');
                name.textContent = teacher.full_name || 'Docente';
                const meta = document.createElement('small');
                meta.textContent = teacher.email || (teacher.username ? `@${teacher.username}` : '');
                info.append(name, meta);
                option.append(info);

                option.addEventListener('click', () => {
                    selectedTutors.push({
                        user_id: uId,
                        full_name: teacher.full_name,
                        email: teacher.email,
                        username: teacher.username,
                    });
                    if (selectedTutors.length === 1) {
                        activeState.tutoring_primary_id = uId;
                    }
                    if (tutorPickerPanel) tutorPickerPanel.hidden = true;
                    renderTutorsList();
                    syncChangeState();
                });

                tutorPickerOptions.append(option);
            });
        };

        // Render Author Picker Options
        const renderAuthorPickerOptions = () => {
            if (!authorPickerOptions) return;
            authorPickerOptions.replaceChildren();

            const query = (authorSearchInput?.value || '').trim().toLowerCase();
            const selectedIds = new Set(selectedAuthors.map(a => parseInt(a.user_id, 10)));

            const available = studentsCatalog.filter(s => {
                const uId = parseInt(s.id || s.user_id, 10);
                if (selectedIds.has(uId)) return false;
                if (!query) return true;
                const searchable = `${s.full_name} ${s.email} ${s.username} ${s.institutional_code}`.toLowerCase();
                return searchable.includes(query);
            });

            if (available.length === 0) {
                const empty = document.createElement('div');
                empty.className = 'sw-edit-project-picker-option';
                empty.style.color = '#94a3b8';
                empty.style.cursor = 'default';
                empty.textContent = query ? 'No se encontraron estudiantes.' : 'Todos los estudiantes disponibles han sido añadidos.';
                authorPickerOptions.append(empty);
                return;
            }

            available.forEach((student) => {
                const uId = parseInt(student.id || student.user_id, 10);
                const option = document.createElement('button');
                option.type = 'button';
                option.className = 'sw-edit-project-picker-option';
                option.setAttribute('role', 'option');

                const info = document.createElement('div');
                info.className = 'info';
                const name = document.createElement('strong');
                name.textContent = student.full_name || 'Estudiante';
                const meta = document.createElement('small');
                meta.textContent = [student.institutional_code, student.username && `@${student.username}`].filter(Boolean).join(' · ');
                info.append(name, meta);
                option.append(info);

                option.addEventListener('click', () => {
                    selectedAuthors.push({
                        user_id: uId,
                        full_name: student.full_name,
                        email: student.email,
                        username: student.username,
                        institutional_code: student.institutional_code,
                        is_leader: selectedAuthors.length === 0,
                    });
                    if (selectedAuthors.length === 1) {
                        activeState.author_leader_id = uId;
                    }
                    if (authorPickerPanel) authorPickerPanel.hidden = true;
                    renderAuthorsList();
                    syncChangeState();
                });

                authorPickerOptions.append(option);
            });
        };

        // Open Modal
        const openModal = () => {
            hideAlert();

            // Populate form values from initialPayload
            const data = initialPayload || {};
            titleInput.value = data.title || '';
            summaryInput.value = data.summary || '';

            selectedTutors = (data.tutors || []).map(t => ({ ...t }));
            selectedAuthors = (data.authors || []).map(a => ({ ...a }));

            activeState.tutoring_primary_id = parseInt(data.tutoring_primary_id || '0', 10);
            activeState.author_leader_id = parseInt(data.author_leader_id || '0', 10);

            if (selectedTutors.length > 0 && !activeState.tutoring_primary_id) {
                activeState.tutoring_primary_id = parseInt(selectedTutors[0].user_id, 10);
            }
            if (selectedAuthors.length > 0 && !activeState.author_leader_id) {
                activeState.author_leader_id = parseInt(selectedAuthors[0].user_id, 10);
            }

            renderTutorsList();
            renderAuthorsList();

            if (tutorPickerPanel) tutorPickerPanel.hidden = true;
            if (authorPickerPanel) authorPickerPanel.hidden = true;

            initialStateString = getComparableState();
            syncChangeState();

            modal.hidden = false;
            titleInput.focus();
        };

        // Close Modal Handling with Unsaved Changes Check
        const closeModal = (force = false) => {
            const isDirty = syncChangeState();
            if (!force && isDirty) {
                if (confirmModal) confirmModal.hidden = false;
                return;
            }
            modal.hidden = true;
            if (confirmModal) confirmModal.hidden = true;
            hideAlert();
            openBtn.focus();
        };

        // Elements for Tutoring Hide
        const tutorHideBtn = form.querySelector('[data-sw-tutor-picker-hide]');
        // Elements for Authors Hide
        const authorHideBtn = form.querySelector('[data-sw-author-picker-hide]');

        // Event Listeners
        openBtn.addEventListener('click', openModal);

        modal.querySelectorAll('[data-sw-edit-close]').forEach(btn => {
            btn.addEventListener('click', () => closeModal(false));
        });

        modal.addEventListener('click', (event) => {
            if (event.target === modal) closeModal(false);
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && !modal.hidden && confirmModal?.hidden) {
                event.preventDefault();
                closeModal(false);
            }
        });

        confirmModal?.querySelector('[data-sw-confirm-keep]')?.addEventListener('click', () => {
            confirmModal.hidden = true;
        });

        confirmModal?.querySelector('[data-sw-confirm-discard]')?.addEventListener('click', () => {
            confirmModal.hidden = true;
            closeModal(true);
        });

        titleInput.addEventListener('input', syncChangeState);
        summaryInput.addEventListener('input', syncChangeState);

        tutorAddBtn?.addEventListener('click', () => {
            if (!tutorPickerPanel) return;
            const open = tutorPickerPanel.hidden;
            tutorPickerPanel.hidden = !open;
            if (open) {
                if (tutorSearchInput) tutorSearchInput.value = '';
                renderTutorPickerOptions();
                tutorSearchInput?.focus();
            }
        });

        tutorHideBtn?.addEventListener('click', () => {
            if (tutorPickerPanel) tutorPickerPanel.hidden = true;
        });

        tutorSearchInput?.addEventListener('input', renderTutorPickerOptions);

        authorAddBtn?.addEventListener('click', () => {
            if (!authorPickerPanel) return;
            const open = authorPickerPanel.hidden;
            authorPickerPanel.hidden = !open;
            if (open) {
                if (authorSearchInput) authorSearchInput.value = '';
                renderAuthorPickerOptions();
                authorSearchInput?.focus();
            }
        });

        authorHideBtn?.addEventListener('click', () => {
            if (authorPickerPanel) authorPickerPanel.hidden = true;
        });

        authorSearchInput?.addEventListener('input', renderAuthorPickerOptions);

        // Click outside handler to hide pickers
        document.addEventListener('click', (event) => {
            if (modal.hidden) return;
            if (tutorPickerPanel && !tutorPickerPanel.hidden) {
                if (!tutorPickerPanel.contains(event.target) && !tutorAddBtn?.contains(event.target)) {
                    tutorPickerPanel.hidden = true;
                }
            }
            if (authorPickerPanel && !authorPickerPanel.hidden) {
                if (!authorPickerPanel.contains(event.target) && !authorAddBtn?.contains(event.target)) {
                    authorPickerPanel.hidden = true;
                }
            }
        });

        // Submit Form via Fetch
        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            hideAlert();

            const titleVal = (titleInput.value || '').trim();
            if (titleVal.length < 5) {
                showAlert('El título debe tener al menos 5 caracteres.');
                titleInput.focus();
                return;
            }
            if (titleVal.length > 240) {
                showAlert('El título no puede superar 240 caracteres.');
                titleInput.focus();
                return;
            }

            const summaryVal = (summaryInput.value || '').trim();
            if (summaryVal.length < 30) {
                showAlert('La descripción debe tener al menos 30 caracteres.');
                summaryInput.focus();
                return;
            }

            if (selectedTutors.length === 0) {
                showAlert('Debes conservar al menos un tutor de referencia.');
                return;
            }

            if (selectedAuthors.length === 0) {
                showAlert('El proyecto debe conservar al menos un integrante.');
                return;
            }

            const primaryTutorId = parseInt(activeState.tutoring_primary_id || '0', 10);
            if (!selectedTutors.some(t => parseInt(t.user_id, 10) === primaryTutorId)) {
                showAlert('Selecciona un tutor principal válido.');
                return;
            }

            const leaderAuthorId = parseInt(activeState.author_leader_id || '0', 10);
            if (!selectedAuthors.some(a => parseInt(a.user_id, 10) === leaderAuthorId)) {
                showAlert('Selecciona un integrante líder válido.');
                return;
            }

            // REGLA 0 y 6: Verificar que el propio estudiante esté en la lista de autores
            if (!selectedAuthors.some(a => parseInt(a.user_id, 10) === currentUserId)) {
                showAlert('No puedes retirarte a ti mismo del proyecto desde esta opción.');
                return;
            }

            isSaving = true;
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.querySelector('span').textContent = 'Guardando cambios...';
            }

            const data = new FormData();
            data.set('_csrf', config.dataset.csrf || '');
            data.set('project_id', String(projectId));
            data.set('title', titleVal);
            data.set('summary', summaryVal);
            data.set('tutoring_primary_id', String(primaryTutorId));
            data.set('author_leader_id', String(leaderAuthorId));

            selectedTutors.forEach(t => data.append('tutoring_user_ids[]', String(t.user_id)));
            selectedAuthors.forEach(a => data.append('author_user_ids[]', String(a.user_id)));

            try {
                const response = await fetch(saveEndpoint, {
                    method: 'POST',
                    body: data,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });

                const result = await response.json();
                if (!response.ok || !result.success) {
                    throw new Error(result.message || 'No fue posible actualizar la información del proyecto.');
                }

                // Exito!
                showVisualToast(result.message || 'Información del proyecto actualizada correctamente.', 'success');

                // Dynamic Workspace UI Update (Requirement 11)
                const updated = result.data?.updated_data;
                if (updated) {
                    // Update Title
                    const swTitle = workspace.querySelector('.sw-title');
                    if (swTitle) {
                        swTitle.textContent = updated.title;
                        swTitle.title = updated.title;
                    }
                    document.title = `${updated.title} | Gestión Académica`;

                    // Update Tutors List in Header
                    const tutorListEl = workspace.querySelector('.sw-tutor-group .sw-person-list');
                    if (tutorListEl && Array.isArray(updated.tutors) && updated.tutors.length > 0) {
                        tutorListEl.replaceChildren(...updated.tutors.map(t => {
                            const span = document.createElement('span');
                            span.className = 'sw-person-name';
                            span.textContent = t.full_name;
                            return span;
                        }));
                        const tutorLabel = workspace.querySelector('.sw-tutor-group .sw-person-label strong');
                        if (tutorLabel) tutorLabel.textContent = updated.tutors.length > 1 ? 'TUTORES' : 'TUTOR';
                    }

                    // Update Authors List in Header
                    const authorListEl = workspace.querySelector('.sw-students-group .sw-person-list');
                    if (authorListEl && Array.isArray(updated.authors) && updated.authors.length > 0) {
                        authorListEl.replaceChildren(...updated.authors.map(a => {
                            const span = document.createElement('span');
                            span.className = 'sw-person-name';
                            span.textContent = a.full_name;
                            return span;
                        }));
                    }

                    // Update Initial Payload Cache
                    initialPayload.title = updated.title;
                    initialPayload.summary = updated.summary;
                    initialPayload.tutoring_primary_id = primaryTutorId;
                    initialPayload.author_leader_id = leaderAuthorId;
                    initialPayload.tutors = selectedTutors.map(t => ({ ...t }));
                    initialPayload.authors = selectedAuthors.map(a => ({ ...a, is_leader: parseInt(a.user_id, 10) === leaderAuthorId }));
                }

                closeModal(true);
            } catch (error) {
                showAlert(error.message || 'No fue posible actualizar la información del proyecto.', true);
            } finally {
                isSaving = false;
                if (submitBtn) {
                    submitBtn.querySelector('span').textContent = 'Guardando cambios';
                    syncChangeState();
                }
            }
        });
    };

    initStudentProjectEditor();
});
