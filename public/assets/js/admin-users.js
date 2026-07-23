(()=>{
    const modal=document.querySelector('#userModal'),form=document.querySelector('#userForm'),config=document.querySelector('#adminUsersConfig'),message=document.querySelector('#userFormMessage'),confirmBox=document.querySelector('#userConfirm');
    if(!modal||!form||!config)return;
    const dialogLayers=[modal,confirmBox,document.querySelector('#importUsersModal'),document.querySelector('#importConfirm')].filter(Boolean);
    dialogLayers.forEach(layer=>document.body.append(layer));
    const refreshToast=document.createElement('div');refreshToast.className='users-refresh-toast';refreshToast.hidden=true;refreshToast.innerHTML='<i class="fa-solid fa-circle-check"></i><span>Usuarios actualizados</span>';document.body.append(refreshToast);
    let refreshToastTimer;
    const showRefreshToast=()=>{clearTimeout(refreshToastTimer);refreshToast.hidden=false;requestAnimationFrame(()=>refreshToast.classList.add('is-visible'));refreshToastTimer=setTimeout(()=>{refreshToast.classList.remove('is-visible');setTimeout(()=>{refreshToast.hidden=true;},220);},2600);};
    const syncDialogState=()=>document.body.classList.toggle('user-dialog-open',dialogLayers.some(layer=>!layer.hidden));
    window.syncAdminUserDialogs=syncDialogState;
    let pending=null;
    const field=name=>form.elements.namedItem(name);
    const showRole=()=>{const role=field('role').value;form.querySelectorAll('.role-fields').forEach(el=>el.hidden=!el.dataset.for.split(' ').includes(role));};
    const open=(user=null)=>{
        form.reset();field('id').value=user?.id||'';field('full_name').value=user?.full_name||'';field('email').value=user?.email||'';field('username').value=user?.username||'';field('role').value=user?.role_code||'student';field('status').value=user?.status||'active';field('institutional_code').value=user?.institutional_code||'';field('semester').value=user?.semester||'';field('academic_title').value=user?.academic_title||'';field('can_tutor').checked=Boolean(Number(user?.can_tutor||0));
        document.querySelector('#userModalTitle').textContent=user?'Editar usuario':'Nuevo usuario';document.querySelector('#temporaryPasswordNote').hidden=Boolean(user);document.querySelector('#importUsersButton').hidden=Boolean(user);message.hidden=true;showRole();modal.hidden=false;syncDialogState();requestAnimationFrame(()=>field('full_name').focus());
    };
    const close=()=>{modal.hidden=true;syncDialogState();};
    const request=async(url,data)=>{const response=await fetch(url,{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:data});const result=await response.json().catch(()=>({success:false,message:'La respuesta del servidor no es válida.'}));if(!response.ok||!result.success)throw new Error(result.message||'No fue posible completar la acción.');return result;};
    window.adminUserRequest=request;
    document.querySelector('#newUserButton')?.addEventListener('click',()=>open());
    field('role').addEventListener('change',showRole);
    document.querySelectorAll('[data-close-modal]').forEach(button=>button.addEventListener('click',close));
    modal.addEventListener('click',event=>{if(event.target===modal)close();});
    form.addEventListener('submit',async event=>{event.preventDefault();const button=form.querySelector('[type=submit]');button.disabled=true;try{const result=await request(config.dataset.save,new FormData(form));message.className='users-message success';message.textContent=result.message;message.hidden=false;setTimeout(()=>location.reload(),600);}catch(error){message.className='users-message error';message.textContent=error.message;message.hidden=false;}finally{button.disabled=false;}});

    const closeMenus=()=>document.querySelectorAll('.user-actions-wrap[open]').forEach(details=>details.removeAttribute('open'));
    const bindActionMenus=(root=document)=>root.querySelectorAll('.user-actions-wrap').forEach(details=>{if(details.dataset.menuReady)return;details.dataset.menuReady='true';details.addEventListener('toggle',()=>{const record=details.closest('.user-record');record?.classList.toggle('has-open-actions',details.open);if(!details.open)return;document.querySelectorAll('.user-actions-wrap[open]').forEach(other=>{if(other!==details)other.removeAttribute('open');});});});
    bindActionMenus();
    document.addEventListener('click',event=>{if(!event.target.closest('.user-actions-wrap'))closeMenus();});
    document.addEventListener('click',event=>{
        const button=event.target.closest('.user-actions [data-action]');if(!button)return;
        const record=button.closest('.user-record'),user=JSON.parse(record.querySelector('.user-data').textContent);closeMenus();
        if(button.dataset.action==='edit')return open(user);
        const isPassword=button.dataset.action==='password';pending={url:isPassword?config.dataset.password:config.dataset.status,data:{id:user.id,status:button.dataset.status||''}};
        document.querySelector('#confirmTitle').textContent=isPassword?'Restablecer contraseña':'Cambiar estado de acceso';
        document.querySelector('#confirmText').textContent=isPassword?`La contraseña de ${user.full_name} volverá a ser Istel2026+ y sus sesiones se cerrarán.`:`Se cambiará el acceso de ${user.full_name}. Sus sesiones activas se cerrarán.`;
        confirmBox.hidden=false;syncDialogState();
    });
    document.querySelector('[data-cancel-confirm]')?.addEventListener('click',()=>{confirmBox.hidden=true;pending=null;syncDialogState();});
    document.querySelector('[data-accept-confirm]')?.addEventListener('click',async event=>{if(!pending)return;event.currentTarget.disabled=true;const data=new FormData();data.set('_csrf',config.dataset.csrf);Object.entries(pending.data).forEach(([key,value])=>data.set(key,value));try{await request(pending.url,data);location.reload();}catch(error){alert(error.message);}finally{event.currentTarget.disabled=false;confirmBox.hidden=true;pending=null;syncDialogState();}});

    const filters=document.querySelector('.users-filters'),search=filters?.querySelector('input[name="search"]'),clear=filters?.querySelector('.users-search-clear');let list=document.querySelector('.users-list'),reindexListing=()=>{},applySearch=()=>{},updateListing=async()=>{};
    if(filters&&search&&list){
        let rows=[],empty=null,searchableNodes=new Map(),original=new WeakMap();const fold=value=>String(value??'').normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLocaleLowerCase('es');
        const textNodes=row=>searchableNodes.get(row)||[];
        reindexListing=()=>{list=document.querySelector('.users-list');rows=list?[...list.querySelectorAll('.user-record')]:[];empty=list?.querySelector('.users-search-empty')||null;searchableNodes=new Map(rows.map(row=>[row,[...row.querySelectorAll('.user-identity strong,.user-identity small,dd')].filter(node=>!node.closest('.user-actions')&&node.children.length===0)]));original=new WeakMap();rows.forEach(row=>textNodes(row).forEach(node=>original.set(node,node.textContent)));};
        reindexListing();
        const restore=()=>rows.forEach(row=>textNodes(row).forEach(node=>{node.textContent=original.get(node)??node.textContent;}));
        const highlight=(node,terms)=>{const value=node.textContent,chars=[...value];let normalized='';const positions=[];chars.forEach((char,index)=>{const folded=fold(char);normalized+=folded;[...folded].forEach(()=>positions.push(index));});const ranges=[];terms.forEach(term=>{let from=0;while(term&&(from=normalized.indexOf(term,from))!==-1){ranges.push([positions[from],positions[from+term.length-1]+1]);from+=term.length;}});if(!ranges.length)return;ranges.sort((a,b)=>a[0]-b[0]);const merged=ranges.reduce((result,range)=>{const last=result.at(-1);if(last&&range[0]<=last[1])last[1]=Math.max(last[1],range[1]);else result.push(range);return result;},[]),fragment=document.createDocumentFragment();let cursor=0;merged.forEach(([start,end])=>{if(start>cursor)fragment.append(document.createTextNode(chars.slice(cursor,start).join('')));const mark=document.createElement('mark');mark.className='users-search-highlight';mark.textContent=chars.slice(start,end).join('');fragment.append(mark);cursor=end;});if(cursor<chars.length)fragment.append(document.createTextNode(chars.slice(cursor).join('')));node.replaceChildren(fragment);};
        let serverQuery=new URLSearchParams(location.search).get('search')||'',refreshTimer,listingRequest=0;
        const listingUrl=()=>{const url=new URL(location.href);const data=new FormData(filters);for(const [key,value]of data.entries()){if(String(value).trim())url.searchParams.set(key,String(value));else url.searchParams.delete(key);}url.searchParams.delete('p');return url;};
        const syncPagination=(page,nextCard)=>{
            const current=document.querySelector('.data-pagination'),next=page.querySelector('.data-pagination');
            if(!current&&next){next.querySelector('.data-pagination-size select')?.removeAttribute('onchange');document.querySelector('.users-table-card')?.insertAdjacentElement('afterend',next);return;}
            if(!current)return;
            if(next){
                current.querySelector('p').innerHTML=next.querySelector('p').innerHTML;
                current.querySelector('.data-pagination-pages').innerHTML=next.querySelector('.data-pagination-pages').innerHTML;
                const currentSelect=current.querySelector('select'),nextSelect=next.querySelector('select');
                if(currentSelect&&nextSelect){currentSelect.innerHTML=nextSelect.innerHTML;currentSelect.value=nextSelect.value;currentSelect.dispatchEvent(new Event('input',{bubbles:true}));}
                current.querySelector('.data-pagination-size')?.removeAttribute('hidden');
            }else{
                const count=Number(nextCard.querySelector('header span')?.textContent.match(/\d+/)?.[0]||0);
                current.querySelector('p').innerHTML=`Mostrando <strong>${count}</strong> de <strong>${count}</strong>`;
                current.querySelector('.data-pagination-pages').innerHTML='<a class="is-active is-disabled" href="#" aria-current="page" aria-disabled="true" tabindex="-1">1</a>';
                current.querySelector('.data-pagination-size')?.setAttribute('hidden','');
            }
            delete current.dataset.originalSummary;delete current.dataset.originalPages;
        };
        updateListing=async(url=listingUrl(),showToast=false)=>{
            const requestId=++listingRequest,currentCard=document.querySelector('.users-table-card');currentCard?.classList.add('is-refreshing');
            try{
                const [response]=await Promise.all([fetch(url,{headers:{'X-Requested-With':'XMLHttpRequest'},cache:'no-store'}),new Promise(resolve=>setTimeout(resolve,280))]);
                if(!response.ok)throw new Error();
                const page=new DOMParser().parseFromString(await response.text(),'text/html'),nextCard=page.querySelector('.users-table-card');
                if(!nextCard||requestId!==listingRequest)return;
                currentCard.replaceWith(nextCard);syncPagination(page,nextCard);bindActionMenus(nextCard);serverQuery=url.searchParams.get('search')||'';history.replaceState(null,'',url);reindexListing();applySearch();if(showToast)showRefreshToast();
            }catch{if(requestId!==listingRequest)return;currentCard?.classList.remove('is-refreshing');alert('No fue posible consultar los usuarios.');}
        };
        const syncSearchCounters=(visible,query)=>{
            const resultCounter=document.querySelector('.users-table-card>header span');
            const pagination=document.querySelector('.data-pagination');
            if(resultCounter)resultCounter.textContent=query?`${visible} resultados en esta página`:`${rows.length} resultados en esta página`;
            if(!pagination)return;
            const summary=pagination.querySelector('p');
            const pages=pagination.querySelector('.data-pagination-pages');
            if(!pagination.dataset.originalSummary&&summary)pagination.dataset.originalSummary=summary.innerHTML;
            if(!pagination.dataset.originalPages&&pages)pagination.dataset.originalPages=pages.innerHTML;
            if(query&&visible===0){
                if(summary)summary.innerHTML='Mostrando <strong>0</strong> de <strong>0</strong>';
                if(pages)pages.innerHTML='<a class="is-active is-disabled" href="#" aria-current="page" aria-disabled="true" tabindex="-1">1</a>';
                return;
            }
            if(summary&&pagination.dataset.originalSummary)summary.innerHTML=pagination.dataset.originalSummary;
            if(pages&&pagination.dataset.originalPages)pages.innerHTML=pagination.dataset.originalPages;
        };
        applySearch=()=>{restore();const query=fold(search.value).trim(),terms=query.split(/\s+/).filter(Boolean),numeric=/^\d+$/.test(query);let visible=0;rows.forEach(row=>{const searchable=numeric?row.dataset.searchNumber:row.dataset.searchText;const matches=!query||terms.every(term=>fold(searchable).includes(term));row.hidden=!matches;if(matches){visible++;if(query){const nodes=numeric?[...row.querySelectorAll('dl>div:nth-child(2) dd')]:[...row.querySelectorAll('.user-identity strong,.user-identity small,dl>div:first-child dd')];nodes.forEach(node=>highlight(node,terms));}}});clear.hidden=!search.value;if(empty)empty.hidden=visible!==0||!query;syncSearchCounters(visible,query);if(query!==fold(serverQuery).trim()){clearTimeout(refreshTimer);refreshTimer=setTimeout(()=>updateListing(listingUrl()),450);}};
        filters.addEventListener('submit',event=>{event.preventDefault();clearTimeout(refreshTimer);updateListing(listingUrl());});search.addEventListener('input',applySearch);clear.addEventListener('click',()=>{search.value='';applySearch();search.focus();clearTimeout(refreshTimer);updateListing(listingUrl());});filters.querySelectorAll('select').forEach(select=>select.addEventListener('change',()=>{clearTimeout(refreshTimer);updateListing(listingUrl());}));document.querySelector('.data-pagination-size select')?.removeAttribute('onchange');document.addEventListener('change',event=>{const size=event.target.closest('.data-pagination-size select');if(!size)return;const url=new URL(location.href);url.searchParams.set(size.name,size.value);url.searchParams.delete('p');updateListing(url);});document.addEventListener('click',event=>{const link=event.target.closest('.data-pagination-pages a[href]');if(!link||link.classList.contains('is-disabled'))return;event.preventDefault();updateListing(new URL(link.href,location.href));});applySearch();
    }else filters?.querySelectorAll('select').forEach(select=>select.addEventListener('change',()=>filters.requestSubmit()));
    document.addEventListener('click',async event=>{
        const button=event.target.closest('.users-refresh');if(!button)return;
        button.disabled=true;button.querySelector('i')?.classList.add('fa-spin');await updateListing(new URL(location.href),true);if(button.isConnected){button.disabled=false;button.querySelector('i')?.classList.remove('fa-spin');}
    });
    document.addEventListener('keydown',event=>{if(event.key==='Escape'){if(!confirmBox.hidden){confirmBox.hidden=true;pending=null;syncDialogState();}else if(!modal.hidden)close();else closeMenus();}});
})();
