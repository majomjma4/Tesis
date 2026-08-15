document.addEventListener('DOMContentLoaded', () => {
    const workspace = document.querySelector('[data-student-workspace]');
    let toastContainer = document.querySelector('.sw-toast-container');
    if (!toastContainer) {
        toastContainer = document.createElement('div');
        toastContainer.className = 'sw-toast-container';
        document.body.appendChild(toastContainer);
    }

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
    const readJsonResponse=async(response)=>{const redirectedToLogin=response.redirected&&/([?&]page=login)(?:&|$)/i.test(response.url||'');if(response.status===401||redirectedToLogin){const error=new Error('Tu sesión ha expirado. Vuelve a iniciar sesión.');error.status=401;error.code='session_expired';throw error;}const contentType=(response.headers.get('content-type')||'').toLowerCase();if(!contentType.includes('application/json')){const error=new Error('Respuesta inesperada del servidor.');error.status=response.status;error.code='unexpected_response';throw error;}const payload=await response.json();if(!response.ok){const error=new Error(payload.message||'No fue posible completar la operación.');error.status=response.status;error.data=payload.data||{};throw error;}return payload;};
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
    const renderStudentObservations=()=>{if(!observationPanel)return;const items=allStudentObservations.filter((item)=>Number(item.file_id||0)===selectedObservationFileId);const pending=items.filter((item)=>!observationIsAddressed(item)).length;const addressed=items.length-pending;if(mobileObsBadge){mobileObsBadge.textContent=String(items.length);mobileObsBadge.hidden=items.length===0;}observationPanel.replaceChildren();const filters=document.createElement('div');filters.className='sw-obs-filters';[['all','Todas',items.length,'is-all'],['pending','Pendientes',pending,'is-pending'],['addressed','Atendidas',addressed,'is-addressed']].forEach(([key,label,count,toneClass])=>{const button=document.createElement('button');button.type='button';const isActive=observationFilter===key;button.className=`sw-obs-filter ${toneClass}${isActive?' is-active':''}`;button.setAttribute('aria-pressed',isActive?'true':'false');button.textContent=`${label} (${count})`;button.addEventListener('click',()=>{observationFilter=key;renderStudentObservations();});filters.append(button);});observationPanel.append(filters);const visible=items.filter((item)=>observationFilter==='all'||(observationFilter==='addressed'?observationIsAddressed(item):!observationIsAddressed(item)));if(!visible.length){const empty=document.createElement('div');empty.className='sw-obs-empty';const emptySubtitle=selectedObservationFileId===0?'Selecciona un archivo para consultar sus observaciones.':'Cuando el docente registre comentarios sobre este archivo aparecerán aquí.';empty.innerHTML=`<i class="fa-regular fa-comments" aria-hidden="true"></i><strong>Sin observaciones</strong><span>${emptySubtitle}</span>`;observationPanel.append(empty);return;}const list=document.createElement('div');list.className='sw-obs-list';visible.forEach((item,index)=>{const card=document.createElement('article');card.className=`sw-obs-card is-${observationTone(item,index)}`;const marker=document.createElement('span');marker.className='sw-obs-marker';marker.textContent=String(index+1);const content=document.createElement('div');content.className='sw-obs-card-content';const meta=document.createElement('div');meta.className='sw-obs-card-meta';meta.innerHTML=`<span>${item.location_reference||item.category||'Observación general'}</span><b class="sw-obs-status ${observationIsAddressed(item)?'is-addressed':'is-pending'}">${observationIsAddressed(item)?'Atendida':'Pendiente'}</b>`;const body=document.createElement('p');body.textContent=String(item.body||'');const author=document.createElement('small');author.textContent=`${item.author_name||'Docente'} · ${item.created_at||''}`;content.append(meta,body,author);card.append(marker,content);list.append(card);});observationPanel.append(list);};
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
    const closeMenus=()=>manager.querySelectorAll('[data-sw-file-menu]').forEach((menu)=>{menu.hidden=true;menu.previousElementSibling?.setAttribute('aria-expanded','false');});
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
    const replaceFile=async(file,fileId,checksum)=>{
        if(!validateFileClient(file))return;
        try{
            await request('replace',file,{file_id:fileId,expected_checksum:checksum});
            setFlashToast('Archivo reemplazado correctamente.','success');
            reloadDocuments();
        }catch(error){
            const isIdentical=error.status===409||/idéntico/i.test(error.message||'');
            if(isIdentical){
                toast(error.message||'El archivo no presenta cambios respecto a la versión actual.','info');
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
                    confirm('Ya existe este archivo',`Ya existe un archivo llamado “${file.name}”. ¿Deseas reemplazarlo?`,`Archivo actual: ${file.name}\nNuevo archivo: ${file.name}`,false,async()=>{closeModal();await replaceFile(file,replace.dataset.fileId,replace.dataset.fileChecksum);});
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
    manager.querySelectorAll('[data-sw-menu-trigger]').forEach((trigger)=>trigger.addEventListener('click',(event)=>{event.stopPropagation();const menu=trigger.nextElementSibling,open=!menu.hidden;closeMenus();menu.hidden=open;trigger.setAttribute('aria-expanded',open?'false':'true');}));
    document.addEventListener('click',(event)=>{if(!manager.contains(event.target))closeMenus();});
    manager.querySelectorAll('[data-sw-replace]').forEach((button)=>button.addEventListener('click',()=>{closeMenus();const chooser=document.createElement('input');chooser.type='file';chooser.hidden=true;document.body.appendChild(chooser);chooser.addEventListener('change',()=>{const file=chooser.files?.[0];chooser.remove();if(!file)return;confirm('Reemplazar archivo','Se conservará el historial técnico del archivo anterior.',`Archivo actual: ${button.dataset.fileName}\nNuevo archivo: ${file.name}`,false,async()=>{closeModal();await replaceFile(file,button.dataset.fileId,button.dataset.fileChecksum);});});chooser.click();}));
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
        renderPreviewState({
            type: 'unsupported',
            title: 'Vista de revisión pendiente',
            message: 'Este documento está guardado correctamente, pero necesitamos una copia PDF para mostrarlo durante la revisión académica.'
        });
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
    const renderPdfPoc=async(preview,restore=null)=>{
        activePdfPreview=preview;
        const generation=++pdfRenderGeneration;

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

            await page.render({canvasContext:canvas.getContext('2d'),viewport,transform:outputScale===1?null:[outputScale,0,0,outputScale,0,0]}).promise;
            if(generation!==pdfRenderGeneration)return;

            const text=document.createElement('div');
            text.className='sw-poc-text-layer';
            text.style.width=`${viewport.width}px`;
            text.style.height=`${viewport.height}px`;
            host.append(text);

            await new api.TextLayer({textContentSource:await page.getTextContent(),container:text,viewport}).render();
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

        if(previewStage){
            previewStage.hidden=false;
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
            if(error.name==='AbortError'||error.code==='preview_superseded')return;
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
    let currentFileName = '';
    let currentFileExtension = '';
    const updateSelectedFileHeader=(name,extension,size,downloadUrl)=>{
        currentFileName = name || '';
        currentFileExtension = String(extension || '').toLowerCase().trim();
        currentDownloadUrl = downloadUrl || '';
        if (viewerIcon) {
            viewerIcon.className = downloadUrl ? 'fa-regular fa-file' : 'fa-solid fa-folder-open';
        }
        viewerName.textContent=name||'Visor de documentos';
        viewerMeta.textContent=downloadUrl ? `${extension||'Archivo'} · ${size||'Tamaño no disponible'}` : 'Exploración y consulta documental';
        if (viewerEmpty) viewerEmpty.hidden=true;
        if (viewerDownload) {
            viewerDownload.hidden=!downloadUrl;
            viewerDownload.href=downloadUrl||'#';
        }
    };
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
                button.innerHTML=`<i class="fa-regular fa-file" aria-hidden="true"></i><span class="sw-zip-entry-name">${node.name}</span>`;
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
                tooltipStatus.innerHTML = `<i class="fa-regular fa-file" aria-hidden="true"></i> <span class="sw-file-tooltip-label">${extLabel}${sizeText}</span>`;

                tooltip.append(tooltipName, tooltipStatus);
                button.append(tooltip);

                const previewUrl = node.preview_url || zipEntryUrl(rootButton.dataset.fileZipPreviewUrl, node.path);
                button.addEventListener('click',()=>{
                    cancelPreviewRequest();
                    selectItem(button);
                    selectedObservationFileId=Number(rootButton.dataset.fileId||0);
                    observationFilter='all';
                    renderStudentObservations();
                    updateSelectedFileHeader(`${rootButton.dataset.fileName} → ${node.name}`,node.extension||'',sizeLabel,node.download_url||zipEntryUrl(rootButton.dataset.fileZipDownloadUrl, node.path));
                    currentPreviewUrl=previewUrl;
                    if(node.type!=='directory'&&window.innerWidth<=768){ switchMobileTab('viewer'); }
                    void loadPreview(previewUrl);
                });
                entry.append(button);
            }
            tree.append(entry);
        });
        setTimeout(updateAllZipNames, 0);
    };
    const loadZipDirectory=async(rootButton,path,tree)=>{if(!tree)return;if(tree.dataset.loaded==='true'){tree.hidden=!tree.hidden;return;}tree.hidden=false;tree.textContent='Cargando…';try{const target=new URL(rootButton.dataset.fileZipUrl,window.location.origin);if(path)target.searchParams.set('path',path);const payload=await readJsonResponse(await fetch(target,jsonRequestInit()));if(!payload.success)throw new Error(payload.message||'No fue posible abrir el ZIP.');renderZipTree(tree,payload.data?.archive?.items||payload.data?.archive||[],rootButton);tree.dataset.loaded='true';}catch(error){console.error('No fue posible cargar la estructura del ZIP.',error);tree.textContent=error.code==='session_expired'?error.message:'No fue posible abrir esta carpeta.';}};
    const selectFile=(button)=>{
        cancelPreviewRequest();
        selectItem(button);
        selectedObservationFileId=Number(button.dataset.fileId||0);
        observationFilter='all';
        renderStudentObservations();
        updateSelectedFileHeader(button.dataset.fileName,button.dataset.fileExtension,button.dataset.fileSize,button.dataset.fileDownload);
        if(button.dataset.fileZipUrl){
            currentPreviewUrl='';
            renderPreviewState({type:'empty',title:'Archivo ZIP seleccionado',message:'Usa el explorador de archivos para desplegar y consultar el contenido del ZIP.'});
            void loadZipDirectory(button,'',button.closest('.sw-archive-node')?.querySelector('[data-sw-zip-tree]'));
            return;
        }
        if(window.innerWidth<=768){ switchMobileTab('viewer'); }
        currentPreviewUrl=button.dataset.filePreview;
        void loadPreview(button.dataset.filePreview);
    };
    manager.querySelectorAll('[data-sw-file]').forEach((button)=>button.addEventListener('click',(event)=>{event.preventDefault();selectFile(event.currentTarget);}));
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
        trigger.addEventListener('click', () => {
            lastFocus = trigger;
            clearError(); setSubmitting(false);
            submitModal.hidden = false;
            submitModal.querySelector('[data-sw-submit-cancel]')?.focus();
        });
        cancelButtons?.forEach((button) => button.addEventListener('click', close));
        submitModal.addEventListener('click', (event) => { if (event.target === submitModal) close(); });
        document.addEventListener('keydown', (event) => { if (event.key === 'Escape' && !submitModal.hidden && !submitting) { event.preventDefault(); close(); } });
        confirmButton.addEventListener('click', async () => {
            if (submitting) return;
            clearError(); setSubmitting(true);
            const body = new FormData(); body.set('project_id', String(projectId)); body.set('_csrf', submitCsrf);
            try {
                const payload = await readJsonResponse(await fetch(submitEndpoint, jsonRequestInit({ method: 'POST', body })));
                if (!payload.success) throw new Error(payload.message || 'No fue posible enviar los documentos a revisión.');
                const result = payload.data || {};
                setSubmitting(false); submitModal.hidden = true;
                updateStatusUi(result);
                const submittedFileCount = Number(result.submitted_file_count || 0);
                const submissionMessage = submittedFileCount === 1
                    ? '1 documento fue enviado al tutor para su revisión.'
                    : `${submittedFileCount} documentos fueron enviados al tutor para su revisión.`;
                showVisualToast(submissionMessage, 'success', 'Proyecto enviado a revisión');
            } catch (error) {
                setSubmitting(false);
                const status = Number(error.status || 0);
                const data = error.data || {};
                const message = status === 419
                    ? 'La sesión del formulario venció. Actualiza la página e inténtalo nuevamente.'
                    : status === 409 ? 'El proyecto ya fue enviado a revisión. Actualiza la página para consultar su estado actual.'
                    : status === 401 ? 'Tu sesión expiró. Inicia sesión nuevamente para continuar.'
                    : status === 403 ? 'No tienes autorización para enviar este proyecto a revisión.'
                    : (error.message || 'No fue posible enviar los documentos a revisión.');
                setError(message, Array.isArray(data.pending_review_representations) ? data.pending_review_representations : []);
                showVisualToast(message, 'error');
            }
        });
    };
    initStudentProjectSubmission();

    if (historicalPreview) {
        (async()=>{try{const payload=await readJsonResponse(await fetch(historicalPreview,jsonRequestInit()));if(!payload.success)throw new Error(payload.message||'No fue posible cargar la versión.');const preview=payload.data.preview;updateSelectedFileHeader(preview.original_name,`Versión ${preview.version_number}`,'Historial','');await renderPreview({status:'ready',preview_type:'pdf',content_url:preview.content_url,name:preview.original_name});const items=preview.observations||[];observationPanel.innerHTML=items.length?items.map((item)=>`<article class="sw-record"><strong>${String(item.category||'Observación')}</strong><p>${String(item.body||'')}</p></article>`).join(''):'<p class="sw-empty-state">No hay observaciones registradas para esta versión.</p>';}catch(error){console.error(error);previewMessage(error.code==='session_expired'?error.message:'No fue posible cargar la versión histórica.','is-error');}})();
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
