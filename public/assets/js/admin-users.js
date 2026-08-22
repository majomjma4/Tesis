(()=>{
    const modal=document.querySelector('#userModal'),form=document.querySelector('#userForm'),config=document.querySelector('#adminUsersConfig')||document.querySelector('#userConfig'),message=document.querySelector('#userFormMessage'),confirmBox=document.querySelector('#userConfirm')||document.querySelector('#userConfirmBox');
    if(!modal||!form||!config)return;
    const dialogLayers=[modal,confirmBox,document.querySelector('#importUsersModal'),document.querySelector('#importConfirm')].filter(Boolean);
    dialogLayers.forEach(layer=>document.body.append(layer));
    const refreshToast=document.createElement('div');refreshToast.className='users-refresh-toast';refreshToast.hidden=true;refreshToast.innerHTML='<i class="fa-solid fa-circle-check"></i><span>Usuarios actualizados</span>';document.body.append(refreshToast);
    let refreshToastTimer;
    const showRefreshToast=(text='Usuarios actualizados',type='success')=>{refreshToast.classList.toggle('is-error',type==='error');refreshToast.querySelector('span').textContent=text;clearTimeout(refreshToastTimer);refreshToast.hidden=false;requestAnimationFrame(()=>refreshToast.classList.add('is-visible'));refreshToastTimer=setTimeout(()=>{refreshToast.classList.remove('is-visible');setTimeout(()=>{refreshToast.hidden=true;},220);},2600);};
    window.showAdminUsersToast=showRefreshToast;
    const syncDialogState=()=>document.body.classList.toggle('user-dialog-open',dialogLayers.some(layer=>!layer.hidden));
    window.syncAdminUserDialogs=syncDialogState;
    let pending=null,formBaseline='';
    const field=name=>form.elements.namedItem(name);
    const saveButton=form.querySelector('[type=submit]');
    const formSnapshot=()=>JSON.stringify([...new FormData(form).entries()]);
    const syncSaveButton=()=>{saveButton.disabled=formSnapshot()===formBaseline;};
    const resetConfirmModal=()=>{
        const reasonField=document.querySelector('#trashReasonField'),reasonInput=document.querySelector('#trashReason');
        const select = document.querySelector('#trashReasonSelect');
        const detailField = document.querySelector('#trashReasonDetailField');
        if(reasonField){reasonField.hidden=true;reasonField.setAttribute('hidden','hidden');reasonField.style.display='none';}
        if(select){select.value='';}
        if(detailField){detailField.hidden=true;detailField.setAttribute('hidden','hidden');detailField.style.display='none';}
        if(reasonInput){reasonInput.value='';reasonInput.disabled=true;reasonInput.required=false;}
    };
    const openConfirmation=(title,text,options={})=>{
        const isTrash=options.isTrash===true;
        const titleEl=document.querySelector('#confirmTitle')||document.querySelector('#userConfirmTitle');if(titleEl)titleEl.textContent=title;
        const textEl=document.querySelector('#confirmText')||document.querySelector('#userConfirmText');if(textEl)textEl.textContent=text;
        const reasonField=document.querySelector('#trashReasonField');
        if(reasonField){
            reasonField.hidden=!isTrash;
            if(!isTrash){reasonField.setAttribute('hidden','hidden');reasonField.style.display='none';}
            else{reasonField.removeAttribute('hidden');reasonField.style.display='block';}
        }

        // Enlazar listeners para select reactivo de motivo si es Papelera
        const select = document.querySelector('#trashReasonSelect');
        const detailField = document.querySelector('#trashReasonDetailField');
        const reasonInput = document.querySelector('#trashReason');

        if (select) {
            select.value = '';
            // Forzar que el dropdown custom de main.js (si existe) se sincronice
            select.dispatchEvent(new Event('change', { bubbles: true }));

            // Listener único de control reactivo
            if (!select.dataset.listenerBound) {
                select.dataset.listenerBound = 'true';
                select.addEventListener('change', () => {
                    const isOther = select.value === 'other';
                    if (detailField) {
                        detailField.hidden = !isOther;
                        if (!isOther) {
                            detailField.setAttribute('hidden', 'hidden');
                            detailField.style.display = 'none';
                        } else {
                            detailField.removeAttribute('hidden');
                            detailField.style.display = 'block';
                        }
                    }
                    if (reasonInput) {
                        reasonInput.disabled = !isOther;
                        reasonInput.required = isOther;
                        if (!isOther) {
                            reasonInput.value = '';
                        } else {
                            requestAnimationFrame(() => reasonInput.focus());
                        }
                    }
                });
            }
        }
        if (detailField) {
            detailField.hidden = true;
            detailField.setAttribute('hidden', 'hidden');
            detailField.style.display = 'none';
        }
        if (reasonInput) {
            reasonInput.value = '';
            reasonInput.disabled = true;
            reasonInput.required = false;
        }

        const accept=confirmBox.querySelector('[data-accept-confirm]');
        if(accept){
            accept.disabled=false;
            accept.textContent=options.confirmText||(isTrash?'Enviar a Papelera':(pending?.kind==='save'?'Guardar cambios':'Confirmar'));
            accept.classList.toggle('danger',isTrash);
        }
        confirmBox.hidden=false;syncDialogState();
        requestAnimationFrame(()=>(isTrash && select ? select.focus() : accept?.focus()));
    };
    const showRole=()=>{const role=field('role').value;form.querySelectorAll('.role-fields').forEach(el=>el.hidden=!el.dataset.for.split(' ').includes(role));form.querySelectorAll('[data-role-field]').forEach(el=>el.hidden=el.dataset.roleField!==role);const thesisAccess=field('can_manage_thesis');if(thesisAccess&&role!=='teacher')thesisAccess.checked=false;const adminAccess=field('is_admin');if(adminAccess){if(role!=='teacher')adminAccess.checked=false;adminAccess.disabled=role!=='teacher';}};
    const open=(user=null)=>{
        form.reset();field('id').value=user?.id||'';field('full_name').value=user?.full_name||'';field('email').value=user?.email||'';field('username').value=user?.username||'';field('role').value=user?.role_code||'student';const statusField=field('status');if(statusField)statusField.value=user?.status||'active';field('institutional_code').value=user?.institutional_code||'';field('semester').value=user?.semester||'';field('academic_title').value=user?.academic_title||'';const tutorAccess=field('can_tutor');if(tutorAccess)tutorAccess.checked=Boolean(Number(user?.can_tutor||0));field('can_manage_thesis').checked=Boolean(Number(user?.can_manage_thesis||0));field('is_admin').checked=Boolean(Number(user?.is_admin||0));
        document.querySelector('#userModalTitle').textContent=user?'Editar usuario':'Nuevo usuario';const note=document.querySelector('#temporaryPasswordNote');if(note)note.hidden=Boolean(user);const importBtn=document.querySelector('#importUsersButton');if(importBtn)importBtn.hidden=Boolean(user);if(message)message.hidden=true;showRole();formBaseline=formSnapshot();syncSaveButton();modal.hidden=false;syncDialogState();requestAnimationFrame(()=>field('full_name').focus());
    };
    const close=()=>{modal.hidden=true;syncDialogState();};
    const request=async(url,data)=>{const response=await fetch(url,{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:data});const result=await response.json().catch(()=>({success:false,message:''}));if(!response.ok||!result.success){const message=response.status===419?'La solicitud expiró o ya no es válida. Recarga la página e inténtalo nuevamente.':response.status===403?(result.message||'No tienes permisos para realizar esta acción.'):response.status===401?'Tu sesión no es válida. Inicia sesión nuevamente.':(result.message||'No fue posible completar la acción.');throw new Error(message);}return result;};
    window.adminUserRequest=request;
    document.querySelector('#newUserButton')?.addEventListener('click',()=>open());
    field('role').addEventListener('change',showRole);form.addEventListener('input',syncSaveButton);form.addEventListener('change',syncSaveButton);
    document.querySelectorAll('[data-close-modal]').forEach(button=>button.addEventListener('click',close));
    modal.addEventListener('click',event=>{if(event.target===modal)close();});
    form.addEventListener('submit',event=>{
        event.preventDefault();const button=form.querySelector('[type=submit]');if(button.disabled)return;
        pending={kind:'save',url:config.dataset.save,data:new FormData(form)};
        openConfirmation(
            field('id').value?'Guardar cambios':'Crear usuario',
            field('id').value?'¿Estás seguro de guardar los cambios realizados en este usuario?':'¿Estás seguro de crear esta cuenta institucional?',
        );
    });

    const closeMenus=()=>document.querySelectorAll('.user-actions-wrap[open]').forEach(details=>details.removeAttribute('open'));
    const bindActionMenus=(root=document)=>root.querySelectorAll('.user-actions-wrap').forEach(details=>{if(details.dataset.menuReady)return;details.dataset.menuReady='true';details.addEventListener('toggle',()=>{const record=details.closest('.user-record');record?.classList.toggle('has-open-actions',details.open);if(!details.open)return;document.querySelectorAll('.user-actions-wrap[open]').forEach(other=>{if(other!==details)other.removeAttribute('open');});});});
    bindActionMenus();
    document.addEventListener('click',event=>{if(!event.target.closest('.user-actions-wrap'))closeMenus();});
    document.addEventListener('click',event=>{
        const button=event.target.closest('.user-actions [data-action]');if(!button)return;
        const record=button.closest('.user-record'),user=JSON.parse(record.querySelector('.user-data').textContent);closeMenus();
        if(button.dataset.action==='edit')return open(user);
        const isPassword=button.dataset.action==='password',isTrash=button.dataset.action==='trash',restoring=button.dataset.status==='active';
        const targetUrl=isPassword?config.dataset.password:(isTrash?(config.dataset.trash||'index.php?page=admin-trash-user'):config.dataset.status);
        const targetData=isTrash?{id:user.id}:{id:user.id,status:button.dataset.status||''};
        pending={kind:'action',url:targetUrl,data:targetData};
        openConfirmation(
            isPassword?'Restablecer contraseña':(isTrash?'Enviar usuario a Papelera':(restoring?'Restablecer acceso':'Bloquear usuario')),
            isPassword?`La contraseña de ${user.full_name} volverá a ser Istel2026+ y sus sesiones se cerrarán.`:
            (isTrash?`¿Estás seguro de enviar la cuenta de ${user.full_name} a la Papelera?`:
            (restoring?`¿Estás seguro de restablecer el acceso de ${user.full_name}?`:`¿Estás seguro de bloquear a ${user.full_name}? Sus sesiones activas se cerrarán.`)),
            {isTrash:isTrash,confirmText:isPassword?'Restablecer':(isTrash?'Enviar a Papelera':(restoring?'Restablecer':'Bloquear'))}
        );
    });
    document.querySelector('[data-cancel-confirm]')?.addEventListener('click',()=>{if(confirmBox)confirmBox.hidden=true;pending=null;resetConfirmModal();syncDialogState();});
    confirmBox?.addEventListener('click',event=>{if(event.target===confirmBox){confirmBox.hidden=true;pending=null;resetConfirmModal();syncDialogState();}});
    document.querySelector('[data-accept-confirm]')?.addEventListener('click',async event=>{
        if(!pending)return;
        const confirmButton=event.currentTarget;
        const action=pending;
        const data=action.kind==='save'?action.data:new FormData();
        if(action.kind!=='save'){
            if(action.url===(config.dataset.trash||'index.php?page=admin-trash-user')){
                const select = document.querySelector('#trashReasonSelect');
                const reasonInput = document.querySelector('#trashReason');

                if (!select || select.value === '') {
                    showRefreshToast('Selecciona un motivo para enviar a la Papelera.', 'error');
                    select?.focus();
                    return;
                }

                let reason = select.value;
                if (reason === 'other') {
                    const detail = String(reasonInput?.value || '').trim();
                    if (detail.length < 5) {
                        showRefreshToast('Indica detalladamente el motivo de envío (mínimo 5 caracteres).', 'error');
                        reasonInput?.focus();
                        return;
                    }
                    reason = detail; // Mandar el detalle para el campo de BD
                } else {
                    // Mapear los motivos predefinidos a texto amigable
                    const labels = {
                        'duplicate': 'Cuenta duplicada',
                        'disengaged': 'Usuario retirado o desvinculado',
                        'created_by_error': 'Registro creado por error',
                        'administrative_request': 'Solicitud administrativa'
                    };
                    reason = labels[reason] || reason;
                }

                action.data.reason = reason;
            }
            confirmButton.disabled=true;
            const trashEndpoint=config.dataset.trash||'index.php?page=admin-trash-user';
            data.set('_csrf',action.url===trashEndpoint?(config.dataset.trashCsrf||config.dataset.csrf):config.dataset.csrf);
            Object.entries(action.data).forEach(([key,value])=>data.set(key,value));
        }else{
            confirmButton.disabled=true;
        }
        try{
            const result=await request(action.url,data);
            if(confirmBox)confirmBox.hidden=true;
            pending=null;
            resetConfirmModal();
            if(action.kind==='save')close();
            else syncDialogState();
            await updateListing(new URL(location.href));
            showRefreshToast(result.message||'Operación realizada correctamente.');
        }catch(error){
            if(confirmBox)confirmBox.hidden=true;
            pending=null;
            resetConfirmModal();
            syncDialogState();
            if(action.kind==='save'&&message){
                message.className='users-message error';
                message.textContent=error.message;
                message.hidden=false;
                syncSaveButton();
            }else{
                showRefreshToast(error.message,'error');
            }
        }finally{
            confirmButton.disabled=false;
        }
    });

    const filters=document.querySelector('.users-filters'),search=filters?.querySelector('input[name="search"]'),clear=filters?.querySelector('.users-search-clear');let list=document.querySelector('.users-list'),reindexListing=()=>{},updateListing=async()=>{};
    if(filters&&search&&list){
        reindexListing=()=>{
            // Mantenido como firma vacía sin operaciones de highlight para preservar compatibilidad con modales de edición
        };
        reindexListing();
    }

    let abortController = null;
    const bindPaginationAjax = () => {
        document.querySelectorAll('.data-pagination-pages a[href]').forEach(link => {
            if (link.dataset.paginationReady) return;
            link.dataset.paginationReady = 'true';
            link.addEventListener('click', event => {
                event.preventDefault();
                updateListing(new URL(link.href), true);
            });
        });
        const sizeForm = document.querySelector('.data-pagination-size');
        if (sizeForm && !sizeForm.dataset.paginationReady) {
            sizeForm.dataset.paginationReady = 'true';
            const select = sizeForm.querySelector('select');

            // Forzar que el trigger del custom select se entere de los cambios y dispare el ajax
            select?.addEventListener('change', event => {
                event.preventDefault();
                const currentUrl = new URL(location.href);
                currentUrl.searchParams.set(select.name, select.value);
                currentUrl.searchParams.delete('p'); // Reset a página 1 al cambiar de tamaño
                updateListing(currentUrl, true);
            });
            // Deshabilitar el onchange nativo inline
            select?.removeAttribute('onchange');
        }
    };

    updateListing = async (url, pushState = true) => {
        if (abortController) abortController.abort();
        abortController = new AbortController();

        const card = document.querySelector('.users-table-card');
        const refreshButton = document.querySelector('.users-refresh');
        const refreshIcon = refreshButton?.querySelector('i');

        if (card) {
            card.classList.add('is-refreshing');
            card.setAttribute('aria-busy', 'true');
        }
        if (refreshButton) {
            refreshButton.disabled = true;
            refreshButton.setAttribute('aria-busy', 'true');
            refreshIcon?.classList.add('fa-spin');
        }

        const startTime = performance.now();

        try {
            const res = await fetch(url.href, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                signal: abortController.signal,
                cache: 'no-store'
            });
            if (!res.ok) throw new Error();
            const html = await res.text();

            // Garantizar tiempo mínimo de skeleton de 400ms para feedback visual fluido
            const elapsed = performance.now() - startTime;
            const minDuration = 400;
            if (elapsed < minDuration) {
                await new Promise(resolve => setTimeout(resolve, minDuration - elapsed));
            }

            const doc = new DOMParser().parseFromString(html, 'text/html');
            const newResults = doc.querySelector('#adminUsersResults');
            const currentResults = document.querySelector('#adminUsersResults');
            const newSummary = doc.querySelector('.users-summary');

            if (newResults && currentResults) {
                currentResults.replaceWith(newResults);
                // Re-enlazar elementos internos actualizados
                list = document.querySelector('.users-list');
                if (list) {
                    bindActionMenus(list);
                }
                reindexListing();
            }
            if (newSummary) {
                const curSummary = document.querySelector('.users-summary');
                if (curSummary) curSummary.innerHTML = newSummary.innerHTML;
            }

            // Actualizar paginador global si existe o cambia
            const newPagination = doc.querySelector('.data-pagination');
            const currentPagination = document.querySelector('.data-pagination');
            if (newPagination && currentPagination) {
                currentPagination.replaceWith(newPagination);
            } else if (newPagination) {
                document.querySelector('#appPageContent')?.append(newPagination);
            } else if (currentPagination) {
                currentPagination.remove();
            }

            bindPaginationAjax();

            if (pushState) {
                // Si la actualización es por el buscador, usamos replaceState para evitar contaminar el historial
                const isSearchUpdate = url.searchParams.has('search') && url.searchParams.get('search') !== '';
                if (isSearchUpdate) {
                    window.history.replaceState({ path: url.toString() }, '', url.toString());
                } else {
                    window.history.pushState({ path: url.toString() }, '', url.toString());
                }
            }
        } catch (error) {
            if (error.name !== 'AbortError') {
                // Si falla el refresh manual, no redireccionamos agresivamente; recuperamos el estado visual
                console.error('Error actualizando listado:', error);
            }
        } finally {
            // Asegurar que siempre se desactiva el estado de carga al terminar
            const activeCard = document.querySelector('.users-table-card');
            const activeRefreshButton = document.querySelector('.users-refresh');
            const activeRefreshIcon = activeRefreshButton?.querySelector('i');

            if (activeCard) {
                activeCard.classList.remove('is-refreshing');
                activeCard.removeAttribute('aria-busy');
            }
            if (activeRefreshButton) {
                activeRefreshButton.disabled = false;
                activeRefreshButton.removeAttribute('aria-busy');
                activeRefreshIcon?.classList.remove('fa-spin');
            }
        }
    };
    bindPaginationAjax();

    if (filters) {
        const submitFormFilters = (resetPage = true) => {
            const data = new FormData(filters);
            const params = new URLSearchParams();
            for (const [key, val] of data.entries()) {
                if (val !== '') params.set(key, val);
            }
            if (resetPage) {
                params.delete('p'); // Al filtrar, volvemos a la página 1
            } else {
                const currentParams = new URLSearchParams(window.location.search);
                if (currentParams.has('p')) params.set('p', currentParams.get('p'));
            }
            const nextUrl = new URL(window.location.pathname, window.location.origin);
            params.forEach((value, key) => nextUrl.searchParams.set(key, value));
            updateListing(nextUrl, true);
        };

        filters.addEventListener('submit', event => {
            event.preventDefault();
            submitFormFilters(true);
        });

        filters.querySelectorAll('select').forEach(select => {
            select.addEventListener('change', () => {
                submitFormFilters(true);
            });
        });

        if (search) {
            let debounceTimer = null;
            search.addEventListener('input', () => {
                if (clear) clear.hidden = !search.value;
                clearTimeout(debounceTimer);
                debounceTimer = setTimeout(() => {
                    submitFormFilters(true);
                }, 350);
            });

            // Forzar actualización al limpiar input con el botón X
            clear?.addEventListener('click', () => {
                search.value = '';
                if (clear) clear.hidden = true;
                search.focus();
                submitFormFilters(true);
            });

            // Sincronizar el botón de limpiar inicialmente
            if (clear) clear.hidden = !search.value;
        }
    }

    window.addEventListener('popstate', () => {
        const currentUrl = new URL(window.location.href);
        const urlParams = currentUrl.searchParams;

        if (search) {
            search.value = urlParams.get('search') || '';
            if (clear) clear.hidden = !search.value;
        }
        if (filters) {
            filters.querySelectorAll('select').forEach(select => {
                select.value = urlParams.get(select.name) || '';
            });
        }
        updateListing(currentUrl, false);
    });

    document.addEventListener('click', async event => {
        const button = event.target.closest('.users-refresh');
        if (!button) return;
        event.preventDefault();
        const currentUrl = new URL(window.location.href);
        await updateListing(currentUrl, false);
        showRefreshToast('Lista de usuarios actualizada');
    });
})();
