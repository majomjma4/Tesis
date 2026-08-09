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
        if(reasonField){reasonField.hidden=true;reasonField.setAttribute('hidden','hidden');reasonField.style.display='none';}
        if(reasonInput)reasonInput.value='';
    };
    const openConfirmation=(title,text,options={})=>{
        const isTrash=options.isTrash===true;
        const titleEl=document.querySelector('#confirmTitle')||document.querySelector('#userConfirmTitle');if(titleEl)titleEl.textContent=title;
        const textEl=document.querySelector('#confirmText')||document.querySelector('#userConfirmText');if(textEl)textEl.textContent=text;
        const reasonField=document.querySelector('#trashReasonField'),reasonInput=document.querySelector('#trashReason');
        if(reasonField){
            reasonField.hidden=!isTrash;
            if(!isTrash){reasonField.setAttribute('hidden','hidden');reasonField.style.display='none';}
            else{reasonField.removeAttribute('hidden');reasonField.style.display='block';}
        }
        if(reasonInput){reasonInput.value='';if(isTrash)reasonInput.placeholder='Indica el motivo por el que se envía esta cuenta a la Papelera.';}
        const accept=confirmBox.querySelector('[data-accept-confirm]');
        if(accept){
            accept.disabled=false;
            accept.textContent=options.confirmText||(isTrash?'Enviar a Papelera':(pending?.kind==='save'?'Guardar cambios':'Confirmar'));
            accept.classList.toggle('danger',isTrash);
        }
        confirmBox.hidden=false;syncDialogState();
        requestAnimationFrame(()=>(isTrash&&reasonInput?reasonInput.focus():accept?.focus()));
    };
    const showRole=()=>{const role=field('role').value;form.querySelectorAll('.role-fields').forEach(el=>el.hidden=!el.dataset.for.split(' ').includes(role));const adminAccess=field('is_admin');if(adminAccess){if(role!=='teacher')adminAccess.checked=false;adminAccess.disabled=role!=='teacher';}};
    const open=(user=null)=>{
        form.reset();field('id').value=user?.id||'';field('full_name').value=user?.full_name||'';field('email').value=user?.email||'';field('username').value=user?.username||'';field('role').value=user?.role_code||'student';field('status').value=user?.status||'active';field('institutional_code').value=user?.institutional_code||'';field('semester').value=user?.semester||'';field('academic_title').value=user?.academic_title||'';field('can_tutor').checked=Boolean(Number(user?.can_tutor||0));field('is_admin').checked=Boolean(Number(user?.is_admin||0));
        document.querySelector('#userModalTitle').textContent=user?'Editar usuario':'Nuevo usuario';const note=document.querySelector('#temporaryPasswordNote');if(note)note.hidden=Boolean(user);const importBtn=document.querySelector('#importUsersButton');if(importBtn)importBtn.hidden=Boolean(user);if(message)message.hidden=true;showRole();formBaseline=formSnapshot();syncSaveButton();modal.hidden=false;syncDialogState();requestAnimationFrame(()=>field('full_name').focus());
    };
    const close=()=>{modal.hidden=true;syncDialogState();};
    const request=async(url,data)=>{const response=await fetch(url,{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:data});const result=await response.json().catch(()=>({success:false,message:'La respuesta del servidor no es válida.'}));if(!response.ok||!result.success)throw new Error(result.message||'No fue posible completar la acción.');return result;};
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
            {isTrash:false,confirmText:field('id').value?'Guardar cambios':'Crear usuario'}
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
                const reasonInput=document.querySelector('#trashReason');
                const reason=String(reasonInput?.value||'').trim();
                if(reason.length<5){
                    showRefreshToast('Indica el motivo por el que se envía a Papelera (mínimo 5 caracteres).','error');
                    reasonInput?.focus();
                    return;
                }
                action.data.reason=reason;
            }
            confirmButton.disabled=true;
            data.set('_csrf',config.dataset.csrf);
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

    const filters=document.querySelector('.users-filters'),search=filters?.querySelector('input[name="search"]'),clear=filters?.querySelector('.users-search-clear');let list=document.querySelector('.users-list'),reindexListing=()=>{},applySearch=()=>{},updateListing=async()=>{};
    if(filters&&search&&list){
        let rows=[],empty=null,searchableNodes=new Map(),original=new WeakMap();const fold=value=>String(value??'').normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLocaleLowerCase('es');
        const textNodes=row=>searchableNodes.get(row)||[];
        reindexListing=()=>{
            rows=[...list.querySelectorAll('.user-record')];empty=list.querySelector('.users-search-empty');
            searchableNodes=new Map();original=new WeakMap();
            rows.forEach(row=>{
                const nodes=[],walker=document.createTreeWalker(row,NodeFilter.SHOW_TEXT,{acceptNode:n=>n.parentElement?.closest('.user-actions-wrap, script')?NodeFilter.FILTER_REJECT:NodeFilter.FILTER_ACCEPT});
                let node;while((node=walker.nextNode()))if(node.nodeValue.trim())nodes.push(node);
                searchableNodes.set(row,nodes);
                nodes.forEach(n=>original.set(n,n.nodeValue));
            });
        };
        reindexListing();
        const clearHighlights=()=>{rows.forEach(row=>textNodes(row).forEach(n=>{if(original.has(n))n.nodeValue=original.get(n);const parent=n.parentElement;if(parent?.classList.contains('users-search-highlight'))parent.replaceWith(document.createTextNode(n.nodeValue));}));};
        const highlight=query=>{
            if(!query){clearHighlights();return;}
            const terms=fold(query).split(/\s+/).filter(Boolean);
            rows.forEach(row=>{
                if(row.hidden)return;
                textNodes(row).forEach(node=>{
                    const raw=original.get(node)||node.nodeValue,normalized=fold(raw);
                    let matched=false;
                    for(const term of terms){if(normalized.includes(term)){matched=true;break;}}
                    if(!matched){if(node.parentElement?.classList.contains('users-search-highlight'))node.parentElement.replaceWith(document.createTextNode(raw));else node.nodeValue=raw;return;}
                    const frag=document.createDocumentFragment();let lastIndex=0;
                    const regex=new RegExp('('+terms.map(t=>t.replace(/[.*+?^${}()|[\]\\]/g,'\\$&')).join('|')+')','gi');
                    raw.replace(regex,(match,offset)=>{
                        if(offset>lastIndex)frag.appendChild(document.createTextNode(raw.slice(lastIndex,offset)));
                        const mark=document.createElement('mark');mark.className='users-search-highlight';mark.textContent=match;frag.appendChild(mark);lastIndex=offset+match.length;return match;
                    });
                    if(lastIndex<raw.length)frag.appendChild(document.createTextNode(raw.slice(lastIndex)));
                    node.parentElement?.replaceChild(frag,node);
                });
            });
        };
        applySearch=()=>{
            const query=search.value.trim(),term=fold(query);clear.hidden=!query;
            let visible=0;
            rows.forEach(row=>{
                const text=fold(row.dataset.searchText||''),num=row.dataset.searchNumber||'';
                const match=!term||text.includes(term)||(num&&num.includes(term));
                row.hidden=!match;if(match)visible++;
            });
            if(empty)empty.hidden=visible>0||rows.length===0;
            highlight(query);
        };
        search.addEventListener('input',applySearch);
        clear.addEventListener('click',()=>{search.value='';applySearch();search.focus();});
    }
    updateListing=async url=>{
        const card=document.querySelector('.users-table-card');card?.classList.add('is-refreshing');
        try{
            const res=await fetch(url.href,{headers:{'X-Requested-With':'XMLHttpRequest'}});
            const html=await res.text();
            const doc=new DOMParser().parseFromString(html,'text/html');
            const newList=doc.querySelector('.users-list'),newSummary=doc.querySelector('.users-summary');
            if(newList&&list){list.innerHTML=newList.innerHTML;bindActionMenus(list);reindexListing();applySearch();}
            if(newSummary){const curSummary=document.querySelector('.users-summary');if(curSummary)curSummary.innerHTML=newSummary.innerHTML;}
        }finally{card?.classList.remove('is-refreshing');}
    };
    document.querySelector('.users-refresh')?.addEventListener('click',async event=>{const button=event.currentTarget,icon=button.querySelector('i');button.disabled=true;icon?.classList.add('fa-spin');try{await updateListing(new URL(button.dataset.resetUrl||location.href));showRefreshToast('Lista de usuarios actualizada');}finally{button.disabled=false;icon?.classList.remove('fa-spin');}});
})();
