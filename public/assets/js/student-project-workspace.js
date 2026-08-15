document.addEventListener('DOMContentLoaded', () => {
    const workspace = document.querySelector('[data-student-workspace]');
    let toastContainer = document.querySelector('.sw-toast-container');
    if (!toastContainer) {
        toastContainer = document.createElement('div');
        toastContainer.className = 'sw-toast-container';
        document.body.appendChild(toastContainer);
    }

    const showVisualToast = (message, kind = false) => {
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
        text.textContent = message;

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

    let lastExplorerWidth = DEFAULT_EXPLORER_WIDTH;
    let lastObsWidth = DEFAULT_OBS_WIDTH;

    const clamp = (val, min, max) => Math.round(Math.max(min, Math.min(max, val)));

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
                if (panelSelector.includes('explorer')) {
                    workspace.style.setProperty('--sw-explorer-w', `${lastExplorerWidth}px`);
                } else {
                    workspace.style.setProperty('--sw-obs-w', `${lastObsWidth}px`);
                }
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
            lastExplorerWidth = storedExplorer;
        }
        if (!isNaN(storedObs) && storedObs >= MIN_OBS_WIDTH) {
            lastObsWidth = storedObs;
        }

        const applyWidths = () => {
            if (window.innerWidth > 760) {
                const containerW = docWorkspace.getBoundingClientRect().width;
                if (containerW > 0) {
                    const maxExplorerW = Math.min(containerW * 0.35, Math.max(MIN_EXPLORER_WIDTH, containerW - MIN_VIEWER_WIDTH - MIN_OBS_WIDTH));
                    const maxObsW = Math.min(containerW * 0.35, Math.max(MIN_OBS_WIDTH, containerW - MIN_VIEWER_WIDTH - MIN_EXPLORER_WIDTH));

                    lastExplorerWidth = clamp(lastExplorerWidth, MIN_EXPLORER_WIDTH, maxExplorerW);
                    lastObsWidth = clamp(lastObsWidth, MIN_OBS_WIDTH, maxObsW);

                    workspace.style.setProperty('--sw-explorer-w', `${lastExplorerWidth}px`);
                    workspace.style.setProperty('--sw-obs-w', `${lastObsWidth}px`);
                }
            } else {
                workspace.style.removeProperty('--sw-explorer-w');
                workspace.style.removeProperty('--sw-obs-w');
            }
        };

        applyWidths();
        window.addEventListener('resize', applyWidths);

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
                startWidth = panel ? panel.getBoundingClientRect().width : (type === 'explorer' ? lastExplorerWidth : lastObsWidth);
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
                    lastExplorerWidth = clampedW;
                    workspace.style.setProperty('--sw-explorer-w', `${clampedW}px`);
                } else {
                    const newW = startWidth - delta;
                    const explorerW = explorerPanel && !explorerPanel.classList.contains('is-collapsed') ? explorerPanel.getBoundingClientRect().width : 40;
                    const maxW = Math.min(containerW * 0.35, Math.max(MIN_OBS_WIDTH, containerW - explorerW - MIN_VIEWER_WIDTH));
                    const clampedW = clamp(newW, MIN_OBS_WIDTH, maxW);
                    lastObsWidth = clampedW;
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
                    localStorage.setItem(STORAGE_EXPLORER_KEY, String(lastExplorerWidth));
                } else {
                    localStorage.setItem(STORAGE_OBS_KEY, String(lastObsWidth));
                }

                window.dispatchEvent(new Event('resize'));
            };

            resizerEl.addEventListener('pointerdown', onPointerDown);
            resizerEl.addEventListener('pointermove', onPointerMove);
            resizerEl.addEventListener('pointerup', onPointerUp);
            resizerEl.addEventListener('pointercancel', onPointerUp);

            resizerEl.addEventListener('dblclick', () => {
                if (type === 'explorer') {
                    lastExplorerWidth = DEFAULT_EXPLORER_WIDTH;
                    localStorage.removeItem(STORAGE_EXPLORER_KEY);
                    workspace.style.setProperty('--sw-explorer-w', `${DEFAULT_EXPLORER_WIDTH}px`);
                } else {
                    lastObsWidth = DEFAULT_OBS_WIDTH;
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
                        lastExplorerWidth = clamp(lastExplorerWidth + step, MIN_EXPLORER_WIDTH, Math.min(containerW * 0.35, 400));
                        workspace.style.setProperty('--sw-explorer-w', `${lastExplorerWidth}px`);
                        localStorage.setItem(STORAGE_EXPLORER_KEY, String(lastExplorerWidth));
                    } else {
                        lastObsWidth = clamp(lastObsWidth + step, MIN_OBS_WIDTH, Math.min(containerW * 0.35, 400));
                        workspace.style.setProperty('--sw-obs-w', `${lastObsWidth}px`);
                        localStorage.setItem(STORAGE_OBS_KEY, String(lastObsWidth));
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

        workspace.querySelectorAll('.sw-tree-item, .sw-zip-entry').forEach(attachTooltipToItem);

        const observer = new MutationObserver(() => {
            workspace.querySelectorAll('.sw-tree-item, .sw-zip-entry').forEach(attachTooltipToItem);
        });
        observer.observe(workspace, { childList: true, subtree: true });
    };

    initFileTooltips();

    const manager=workspace.querySelector('[data-sw-document-manager]'); if (!manager) return;
    const endpoint=manager.dataset.endpoint, csrf=manager.dataset.csrf, reviewRepresentationEndpoint=manager.dataset.reviewRepresentationEndpoint||'', reviewRepresentationCsrf=manager.dataset.reviewRepresentationCsrf||'', projectId=manager.dataset.projectId, historicalPreview=manager.dataset.historicalPreview||'';
    let pdfjsPromise=null, pdfDocument=null, pdfDocumentKey='', pdfDocumentLoading=null, pdfDocumentGeneration=0, previewGeneration=0, previewController=null, currentPreviewUrl='', pdfScale=1, pdfFitScale=1, activePdfPreview=null, pocAnnotations=[], annotationsVisible=true;
    const pdfjs=async()=>{if(!pdfjsPromise)pdfjsPromise=import(manager.dataset.pdfjsUrl).then((module)=>{module.GlobalWorkerOptions.workerSrc=manager.dataset.pdfjsWorker;return module;});return pdfjsPromise;};
    const jsonRequestInit=(options={})=>({...options,credentials:'same-origin',headers:{Accept:'application/json',...(options.headers||{})}});
    const readJsonResponse=async(response)=>{const redirectedToLogin=response.redirected&&/([?&]page=login)(?:&|$)/i.test(response.url||'');if(response.status===401||redirectedToLogin){const error=new Error('Tu sesión ha expirado. Vuelve a iniciar sesión.');error.status=401;error.code='session_expired';throw error;}const contentType=(response.headers.get('content-type')||'').toLowerCase();if(!contentType.includes('application/json')){const error=new Error('Respuesta inesperada del servidor.');error.status=response.status;error.code='unexpected_response';throw error;}const payload=await response.json();if(!response.ok){const error=new Error(payload.message||'No fue posible completar la operación.');error.status=response.status;throw error;}return payload;};
    const fileInput=manager.querySelector('[data-sw-file-input]'), addButton=manager.querySelector('[data-sw-add-files]');
    const viewerName=manager.querySelector('[data-sw-viewer-name]'), viewerMeta=manager.querySelector('[data-sw-viewer-meta]'), viewerDownload=manager.querySelector('[data-sw-viewer-download]'), viewerEmpty=manager.querySelector('[data-sw-viewer-empty]'), previewStage=manager.querySelector('[data-sw-preview-stage]'), observationPanel=manager.querySelector('[data-sw-file-observations]');
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
    const cancelPreviewRequest=()=>{previewGeneration++;if(previewController){previewController.abort();previewController=null;}activePdfPreview=null;try{releasePdfDocument();}catch(error){/* La selección nueva continúa aunque el documento anterior no se libere de inmediato. */}};
    const loadPdfDocument=async(preview)=>{const key=String(preview.content_url||'');if(!key)throw new Error('PDF privado no disponible.');if(pdfDocument&&pdfDocumentKey===key)return pdfDocument;if(pdfDocumentLoading&&pdfDocumentLoading.key===key)return pdfDocumentLoading.promise;releasePdfDocument();const generation=pdfDocumentGeneration;const promise=(async()=>{const response=await fetch(preview.content_url,{credentials:'same-origin'});if(!response.ok)throw new Error('PDF privado no disponible.');const api=await pdfjs(),bytes=new Uint8Array(await response.arrayBuffer()),document=await api.getDocument({data:bytes,standardFontDataUrl:manager.dataset.pdfjsFonts}).promise;if(generation!==pdfDocumentGeneration){document.destroy().catch(()=>{});throw new Error('Carga de PDF cancelada.');}pdfDocument=document;pdfDocumentKey=key;return document;})();pdfDocumentLoading={key,promise};try{return await promise;}finally{if(pdfDocumentLoading?.promise===promise)pdfDocumentLoading=null;}};
    const clearPreview=()=>{objectUrls.forEach((url)=>URL.revokeObjectURL(url));objectUrls.clear();previewStage?.replaceChildren();if(previewStage)previewStage.hidden=true;};
    const previewMessage=(message,kind='',retryCallback=null)=>{clearPreview();if(!previewStage)return;const wrapper=document.createElement('div');wrapper.className='sw-preview-error-wrapper';const state=document.createElement('p');state.className=`sw-preview-message ${kind}`;state.textContent=message;wrapper.append(state);if(typeof retryCallback==='function'){const retryBtn=document.createElement('button');retryBtn.type='button';retryBtn.className='sw-retry-preview-btn';retryBtn.textContent='Reintentar vista previa';retryBtn.addEventListener('click',(e)=>{e.preventDefault();retryBtn.disabled=true;retryCallback();});wrapper.append(retryBtn);}previewStage.append(wrapper);previewStage.hidden=false;};
    const reviewPendingMessage=(preview)=>{clearPreview();if(!previewStage)return;const wrapper=document.createElement('div');wrapper.className='sw-review-representation-pending';const title=document.createElement('strong');title.textContent='Vista de revisión pendiente';const text=document.createElement('p');text.textContent='Este documento está guardado correctamente, pero necesitamos una copia PDF para mostrarlo durante la revisión académica.';const upload=document.createElement('button');upload.type='button';upload.className='sw-review-representation-upload';upload.textContent='Subir PDF para revisión';const input=document.createElement('input');input.type='file';input.accept='.pdf,application/pdf';input.hidden=true;upload.addEventListener('click',()=>input.click());input.addEventListener('change',async()=>{const file=input.files?.[0];input.value='';if(!file)return;const body=new FormData();body.set('_csrf',reviewRepresentationCsrf);body.set('project_id',projectId);body.set('action','upload');body.set('file_id',String(preview.file_id||''));body.set('file',file);upload.disabled=true;upload.textContent='Validando PDF…';try{const response=await fetch(reviewRepresentationEndpoint,{method:'POST',credentials:'same-origin',headers:{Accept:'application/json'},body});const payload=await readJsonResponse(response);if(!payload.success)throw new Error(payload.message||'No fue posible asociar el PDF.');await loadPreview(currentPreviewUrl);}catch(error){upload.disabled=false;upload.textContent='Subir PDF para revisión';text.textContent=error.message||'No fue posible asociar el PDF.';text.className='sw-preview-message is-error';}});wrapper.append(title,text,upload,input);previewStage.append(wrapper);previewStage.hidden=false;};
    const renderBlocks=(blocks)=>{const content=document.createElement('div');content.className='sw-preview-docx-content';(blocks||[]).forEach((block)=>{if(block.type==='table'){const table=document.createElement('table');(block.rows||[]).forEach((row)=>{const tr=document.createElement('tr');row.forEach((cell)=>{const td=document.createElement('td');td.textContent=cell;tr.append(td);});table.append(tr);});content.append(table);}else{const node=document.createElement(block.type==='heading'?`h${Math.min(6,Math.max(1,block.level||2))}`:'p');node.textContent=block.text||'';content.append(node);}});previewStage.append(content);};
    const previewErrorMessage=(type)=>type==='image'?'No fue posible mostrar esta imagen.':(type==='pdf'?'No fue posible abrir este PDF.':'No fue posible generar la vista previa de este documento.');
    const renderImage=async(preview)=>{const response=await fetch(preview.content_url,{credentials:'same-origin'}),contentType=response.headers.get('content-type')||'';if(!response.ok||!contentType.startsWith('image/'))throw new Error('Respuesta de imagen no válida.');const url=URL.createObjectURL(await response.blob()),image=document.createElement('img');objectUrls.add(url);image.src=url;image.alt=preview.name;image.draggable=false;image.className='sw-preview-image';await new Promise((resolve,reject)=>{image.addEventListener('load',resolve,{once:true});image.addEventListener('error',reject,{once:true});previewStage.append(image);});};
    const renderPdfPoc=async(preview,restore=null)=>{activePdfPreview=preview;const pdfDoc=await loadPdfDocument(preview),api=await pdfjs(),first=await pdfDoc.getPage(1),natural=first.getViewport({scale:1});const availableWidth=Math.max(280,(previewStage.clientWidth||600)-24);pdfFitScale=Math.min(2.5,Math.max(.35,availableWidth/natural.width));const effectiveScale=pdfFitScale*pdfScale,controls=document.createElement('div');controls.className='sw-poc-pdf-controls';for(const [label,factor] of [['−',.8],['Ajustar',0],['+',1.25]]){const button=document.createElement('button');button.type='button';button.textContent=label;button.addEventListener('click',async()=>{const position={top:previewStage.scrollTop/Math.max(1,previewStage.scrollHeight-previewStage.clientHeight),left:previewStage.scrollLeft/Math.max(1,previewStage.scrollWidth-previewStage.clientWidth)};pdfScale=factor===0?1:Math.min(2.5,Math.max(.5,pdfScale*factor));clearPreview();await renderPdfPoc(preview,position);});controls.append(button);}const label=document.createElement('span');label.textContent=pdfScale===1?'Ajustar':`${Math.round(pdfScale*100)}%`;controls.append(label);previewStage.append(controls);const pages=document.createElement('div');pages.className='sw-poc-pages';previewStage.append(pages);for(let number=1;number<=pdfDoc.numPages;number++){const page=await pdfDoc.getPage(number),viewport=page.getViewport({scale:effectiveScale}),host=document.createElement('div');host.className='sw-poc-page';host.dataset.pocPage=String(number);host.style.width=`${viewport.width}px`;host.style.height=`${viewport.height}px`;host.style.setProperty('--scale-factor',String(effectiveScale));const outputScale=window.devicePixelRatio||1,canvas=document.createElement('canvas');canvas.width=Math.floor(viewport.width*outputScale);canvas.height=Math.floor(viewport.height*outputScale);canvas.style.width=`${viewport.width}px`;canvas.style.height=`${viewport.height}px`;host.append(canvas);await page.render({canvasContext:canvas.getContext('2d'),viewport,transform:outputScale===1?null:[outputScale,0,0,outputScale,0,0]}).promise;const text=document.createElement('div');text.className='sw-poc-text-layer';text.style.width=`${viewport.width}px`;text.style.height=`${viewport.height}px`;host.append(text);await new api.TextLayer({textContentSource:await page.getTextContent(),container:text,viewport}).render();pages.append(host);}if(restore){previewStage.scrollTop=(previewStage.scrollHeight-previewStage.clientHeight)*restore.top;previewStage.scrollLeft=(previewStage.scrollWidth-previewStage.clientWidth)*restore.left;}drawPocAnnotations();renderPocPanel();};
    const renderPreview=async(preview,originalUrl='')=>{clearPreview();if(!previewStage)return;if(preview.status!=='ready'){activePdfPreview=null;releasePdfDocument();const ext=String(preview.extension||'').toLowerCase(),type=String(preview.preview_type||'').toLowerCase();const isDocx=ext==='docx'||type==='docx'||(originalUrl&&/docx/i.test(originalUrl));if(preview.manual_pdf_required&&!historicalPreview&&reviewRepresentationEndpoint){reviewPendingMessage(preview);return;}const retryCb=isDocx&&originalUrl?()=>loadPreview(originalUrl,true):null;previewMessage(preview.message||'Vista previa no disponible para este archivo.','is-error',retryCb);return;}const type=preview.preview_type;if(type==='pdf'){await renderPdfPoc(preview);}else{activePdfPreview=null;releasePdfDocument();if(type==='image'){await renderImage(preview);}else if(type==='text'||type==='code'){const pre=document.createElement('pre');pre.className=`sw-preview-text ${type==='code'?'is-code':''}`;pre.textContent=preview.content||'';pre.draggable=false;previewStage.append(pre);}else if(type==='docx'){const note=document.createElement('p');note.className='sw-docx-notice';note.textContent='Vista previa del contenido. Descarga el archivo para consultar el formato completo.';previewStage.append(note);if(!window.JSZip||typeof window.docx?.renderAsync!=='function'||!preview.content_url){renderBlocks(preview.blocks);previewStage.hidden=false;return;}const response=await fetch(preview.content_url,{credentials:'same-origin'}),contentType=response.headers.get('content-type')||'';if(!response.ok||!/application\/(vnd\.openxmlformats-officedocument\.wordprocessingml\.document|zip|octet-stream)/i.test(contentType))throw new Error('Respuesta DOCX no válida.');const data=await response.arrayBuffer(),host=document.createElement('div');host.className='sw-preview-docx';host.draggable=false;previewStage.append(host);await window.docx.renderAsync(data,host,null,{inWrapper:true,ignoreLastRenderedPageBreak:false,renderHeaders:true,renderFooters:true});}else previewMessage(preview.message||'Vista previa no disponible para este archivo.');}previewStage.hidden=false;};
    const loadPreview=async(url,retry=false)=>{if(!url)return previewMessage('Vista previa no disponible para este archivo.');const generation=++previewGeneration;previewController?.abort();const controller=new AbortController();previewController=controller;previewMessage('Preparando vista del documento…');let type='';let targetUrl=url;if(retry){try{const target=new URL(url,window.location.origin);target.searchParams.set('retry_preview','1');targetUrl=target.toString();}catch(e){targetUrl=url+(url.includes('?')?'&':'?')+'retry_preview=1';}}try{const payload=await readJsonResponse(await fetch(targetUrl,jsonRequestInit({signal:controller.signal})));if(generation!==previewGeneration)throw Object.assign(new Error('Preview superseded.'),{code:'preview_superseded'});const preview=payload?.data?.preview||{};type=preview.preview_type||'';if(!payload.success)throw new Error(payload.message||'No fue posible cargar la vista previa.');await renderPreview(preview,url);if(generation!==previewGeneration)throw Object.assign(new Error('Preview superseded.'),{code:'preview_superseded'});}catch(error){if(error.name==='AbortError'||error.code==='preview_superseded')return;console.error('No fue posible cargar la vista previa.',error);if(error.code==='session_expired'){previewMessage(error.message,'is-error');return;}const isDocx=(type==='docx')||(url&&/docx/i.test(url));const retryCb=isDocx?()=>loadPreview(url,true):null;previewMessage(previewErrorMessage(type),'is-error',retryCb);}finally{if(previewController===controller)previewController=null;}};
    const selectItem=(item)=>manager.querySelectorAll('[data-sw-file], [data-sw-zip-entry]').forEach((entry)=>entry.classList.toggle('is-selected',entry===item));
    const setViewer=(name,extension,size,downloadUrl)=>{viewerName.textContent=name||'Archivo';viewerMeta.textContent=`${extension||'Archivo'} · ${size||'Tamaño no disponible'}`;viewerEmpty.hidden=true;viewerDownload.hidden=!downloadUrl;viewerDownload.href=downloadUrl||'#';};
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

        const button = nameElement.closest('.sw-zip-entry');
        if (!button) return;

        const buttonWidth = button.clientWidth;
        if (buttonWidth <= 0) return;

        const icon = button.querySelector('i');
        const iconWidth = icon ? (icon.offsetWidth || 14) : 14;
        const padding = 16;
        const gap = 7;
        const availableWidth = Math.max(20, buttonWidth - (iconWidth + gap + padding + 4));

        const computed = window.getComputedStyle(nameElement);
        const font = computed.font && computed.font !== '' ? computed.font : `${computed.fontSize || '13.6px'} ${computed.fontFamily || 'sans-serif'}`;
        const fullTextWidth = measureZipTextWidth(fullName, font);

        if (fullTextWidth <= availableWidth) {
            nameElement.textContent = fullName;
            return;
        }

        const ellipsis = '…';
        const ellipsisWidth = measureZipTextWidth(ellipsis, font);
        const targetWidth = availableWidth - ellipsisWidth;

        if (targetWidth <= 5) {
            nameElement.textContent = ellipsis;
            return;
        }

        let low = 1;
        let high = fullName.length - 1;
        let bestLength = 1;

        while (low <= high) {
            const mid = Math.floor((low + high) / 2);
            const sub = fullName.substring(0, mid);
            const subWidth = measureZipTextWidth(sub, font);

            if (subWidth <= targetWidth) {
                bestLength = mid;
                low = mid + 1;
            } else {
                high = mid - 1;
            }
        }

        nameElement.textContent = fullName.substring(0, bestLength) + ellipsis;
    };

    const updateAllZipNames = () => {
        workspace.querySelectorAll('.sw-zip-entry-name').forEach(fitZipEntryName);
    };

    if (typeof ResizeObserver === 'function') {
        const explorerPanel = workspace.querySelector('[data-sw-explorer]');
        if (explorerPanel) {
            const zipObserver = new ResizeObserver(() => {
                requestAnimationFrame(updateAllZipNames);
            });
            zipObserver.observe(explorerPanel);
        }
    }

    window.addEventListener('resize', updateAllZipNames);

    const zipEntryUrl=(baseUrl,path)=>{const target=new URL(baseUrl,window.location.origin);target.searchParams.set('path',path);return target.toString();};
    const renderZipTree=(tree,archive,rootButton)=>{
        tree.replaceChildren();
        (archive.items||[]).forEach((item)=>{
            const entry=document.createElement('li'),
                  button=document.createElement('button'),
                  icon=document.createElement('i'),
                  name=document.createElement('span'),
                  isDirectory=item.kind==='folder',
                  fullName=item.name||item.path||'Archivo interno';
            entry.className='sw-zip-node';
            button.type='button';
            button.className='sw-zip-entry';
            button.dataset.swZipEntry='';
            button.setAttribute('aria-label', `${fullName} (${isDirectory ? 'Carpeta' : 'Archivo'})`);

            const tooltip = document.createElement('span');
            tooltip.className = 'sw-file-tooltip';
            tooltip.setAttribute('role', 'tooltip');
            tooltip.setAttribute('aria-hidden', 'true');
            tooltip.hidden = true;

            const tooltipName = document.createElement('span');
            tooltipName.className = 'sw-file-tooltip-name';
            tooltipName.textContent = fullName;

            const tooltipStatus = document.createElement('span');
            tooltipStatus.className = 'sw-file-tooltip-status';
            tooltipStatus.innerHTML = `<i class="${isDirectory ? 'fa-solid fa-folder' : 'fa-regular fa-file'}" aria-hidden="true"></i> <span class="sw-file-tooltip-label">${isDirectory ? 'Carpeta interna' : (item.extension || item.type || 'Archivo').toUpperCase()}</span>`;

            tooltip.append(tooltipName, tooltipStatus);

            icon.className=isDirectory?'fa-solid fa-folder':'fa-regular fa-file';
            icon.setAttribute('aria-hidden','true');
            name.className='sw-zip-entry-name';
            name.dataset.fullName=fullName;
            name.textContent=fullName;
            button.append(icon,name,tooltip);
            entry.append(button);
            if(isDirectory){
                const child=document.createElement('ul');
                child.className='sw-zip-tree';
                child.hidden=true;
                button.addEventListener('click',()=>loadZipDirectory(rootButton,item.path,child));
                entry.append(child);
            } else {
                button.addEventListener('click',()=>{
                    selectItem(button);
                    setViewer(fullName,(item.extension||item.type||'Archivo').toUpperCase(),item.size||'Tamaño no disponible',zipEntryUrl(rootButton.dataset.fileZipDownloadUrl,item.path));
                    observationPanel.innerHTML='<p class="sw-empty-state">Las observaciones de archivos internos se consultarán cuando estén vinculadas al archivo principal.</p>';
                    loadPreview(zipEntryUrl(rootButton.dataset.fileZipPreviewUrl,item.path));
                });
            }
            tree.append(entry);
        });
        setTimeout(updateAllZipNames, 0);
    };
    const loadZipDirectory=async(rootButton,path,tree)=>{if(!tree)return;if(tree.dataset.loaded==='true'){tree.hidden=!tree.hidden;return;}tree.hidden=false;tree.textContent='Cargando…';try{const target=new URL(rootButton.dataset.fileZipUrl,window.location.origin);if(path)target.searchParams.set('path',path);const payload=await readJsonResponse(await fetch(target,jsonRequestInit()));if(!payload.success)throw new Error(payload.message||'No fue posible abrir el ZIP.');renderZipTree(tree,payload.data.archive,rootButton);tree.dataset.loaded='true';}catch(error){console.error('No fue posible cargar la estructura del ZIP.',error);tree.textContent=error.code==='session_expired'?error.message:'No fue posible abrir esta carpeta.';}};
    const selectFile=(button)=>{cancelPreviewRequest();selectItem(button);setViewer(button.dataset.fileName,button.dataset.fileExtension,button.dataset.fileSize,button.dataset.fileDownload);const count=Number(button.dataset.fileObservations||0);observationPanel.innerHTML=`<p class="sw-empty-state">${count?`${count} observación${count===1?'':'es'} registrada${count===1?'':'s'} para este archivo.`:'No hay observaciones registradas para este archivo.'}</p>`;if(button.dataset.fileZipUrl){currentPreviewUrl='';previewMessage('Archivo ZIP seleccionado. Usa el explorador para navegar por su contenido.');void loadZipDirectory(button,'',button.closest('.sw-archive-node')?.querySelector('[data-sw-zip-tree]'));return;}currentPreviewUrl=button.dataset.filePreview;void loadPreview(button.dataset.filePreview);};
    manager.querySelectorAll('[data-sw-file]').forEach((button)=>button.addEventListener('click',(event)=>{event.preventDefault();selectFile(event.currentTarget);}));
    const drawPocAnnotations=()=>{previewStage?.querySelectorAll('[data-poc-overlay]').forEach((node)=>node.remove());if(!annotationsVisible)return;pocAnnotations.forEach((annotation,index)=>{const page=previewStage?.querySelector(`[data-poc-page="${annotation.page}"]`);if(!page)return;annotation.rects.forEach((rect)=>{const overlay=document.createElement('span');overlay.dataset.pocOverlay='';overlay.className=`sw-poc-annotation is-${annotation.style}`;Object.assign(overlay.style,{left:`${rect.x*100}%`,top:`${rect.y*100}%`,width:`${rect.width*100}%`,height:`${rect.height*100}%`});page.append(overlay);});const marker=document.createElement('button');marker.type='button';marker.dataset.pocOverlay='';marker.className='sw-poc-marker';marker.textContent=String(index+1);marker.setAttribute('aria-label',`Abrir observación ${index+1}`);Object.assign(marker.style,{left:`${annotation.rects[0].x*100}%`,top:`${annotation.rects[0].y*100}%`});marker.addEventListener('click',()=>page.scrollIntoView({behavior:'smooth',block:'center'}));page.append(marker);});};
    const renderPocPanel=()=>{if(!observationPanel)return;if(!pocAnnotations.length){observationPanel.innerHTML='<p class="sw-empty-state">No hay observaciones registradas para este archivo.</p>';return;}observationPanel.replaceChildren();const toggle=document.createElement('button');toggle.type='button';toggle.className='sw-poc-control';toggle.textContent=annotationsVisible?'Ocultar observaciones':'Mostrar observaciones';toggle.addEventListener('click',()=>{annotationsVisible=!annotationsVisible;drawPocAnnotations();renderPocPanel();});observationPanel.append(toggle);pocAnnotations.forEach((annotation,index)=>{const item=document.createElement('button');item.type='button';item.className='sw-poc-observation';item.textContent=`${index+1}. ${annotation.comment}: “${annotation.selected_text}”`;item.addEventListener('click',()=>previewStage?.querySelector(`[data-poc-page="${annotation.page}"]`)?.scrollIntoView({behavior:'smooth',block:'center'}));observationPanel.append(item);});};
    let pdfResizeTimer=null;window.addEventListener('resize',()=>{if(!activePdfPreview)return;clearTimeout(pdfResizeTimer);pdfResizeTimer=setTimeout(async()=>{const restore={top:previewStage.scrollTop/Math.max(1,previewStage.scrollHeight-previewStage.clientHeight),left:previewStage.scrollLeft/Math.max(1,previewStage.scrollWidth-previewStage.clientWidth)};clearPreview();try{await renderPdfPoc(activePdfPreview,restore);previewStage.hidden=false;}catch(error){previewMessage('No fue posible cargar la vista previa del documento.','is-error');}},120);});
    if (historicalPreview) {
        (async()=>{try{const payload=await readJsonResponse(await fetch(historicalPreview,jsonRequestInit()));if(!payload.success)throw new Error(payload.message||'No fue posible cargar la versión.');const preview=payload.data.preview;setViewer(preview.original_name,`Versión ${preview.version_number}`,'Historial','');await renderPreview({status:'ready',preview_type:'pdf',content_url:preview.content_url,name:preview.original_name});const items=preview.observations||[];observationPanel.innerHTML=items.length?items.map((item)=>`<article class="sw-record"><strong>${String(item.category||'Observación')}</strong><p>${String(item.body||'')}</p></article>`).join(''):'<p class="sw-empty-state">No hay observaciones registradas para esta versión.</p>';}catch(error){console.error(error);previewMessage(error.code==='session_expired'?error.message:'No fue posible cargar la versión histórica.','is-error');}})();
    }
});
