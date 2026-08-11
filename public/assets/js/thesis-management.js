(() => {
  const root=document.querySelector('#thesisManagementPage'); if(!root)return;
  const items=[...root.querySelectorAll('[data-tm-item]')],search=root.querySelector('[data-tm-search]'),period=root.querySelector('[data-tm-period]'),situation=root.querySelector('[data-tm-situation]'),count=root.querySelector('[data-tm-count]'),empty=root.querySelector('[data-tm-filter-empty]'),pagination=root.querySelector('[data-tm-pagination]'),summary=root.querySelector('[data-tm-summary]'),size=root.querySelector('[data-tm-size]'),pages=root.querySelector('[data-tm-pages]');const state={page:1,size:10};const normalize=v=>String(v||'').normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLocaleLowerCase('es');
  const highlight=(node,term)=>{if(!node)return;const source=node.dataset.tmOriginal??node.textContent;node.dataset.tmOriginal=source;const q=normalize(term),i=normalize(source).indexOf(q);if(!q||i<0){node.textContent=source;return;}const mark=document.createElement('mark');mark.textContent=source.slice(i,i+term.length);node.replaceChildren(source.slice(0,i),mark,source.slice(i+term.length));}; const tokens=(page,total)=>{const out=[];for(let n=1;n<=total;n++)if(n===1||n===total||Math.abs(n-page)<=1)out.push(n);else if(out.at(-1)!=='…')out.push('…');return out;};
  const render=()=>{const term=search?.value||'',filtered=items.filter(item=>{const yes=(!term||normalize(item.dataset.search).includes(normalize(term)))&&(!period?.value||item.dataset.period===period.value)&&(!situation?.value||item.dataset.situation===situation.value);item.querySelectorAll('[data-tm-highlight]').forEach(n=>highlight(n,yes?term:''));return yes;});const total=filtered.length,max=Math.max(1,Math.ceil(total/state.size));state.page=Math.min(state.page,max);const first=(state.page-1)*state.size,last=Math.min(first+state.size,total);items.forEach(i=>i.hidden=true);filtered.slice(first,last).forEach(i=>i.hidden=false);if(count)count.textContent=`${total} resultados visibles`;if(empty)empty.hidden=total!==0;if(summary)summary.textContent=total?`Mostrando ${first+1}-${last} de ${total}`:'Mostrando 0 de 0';if(!pagination)return;pagination.hidden=total<=state.size;pages?.replaceChildren();if(!pages)return;const nav=(label,target,disabled)=>{const b=document.createElement('button');b.type='button';b.textContent=label;b.disabled=disabled;b.onclick=()=>{state.page=target;render();};return b;};pages.append(nav('Anterior',state.page-1,state.page===1));tokens(state.page,max).forEach(t=>{if(t==='…'){const s=document.createElement('span');s.textContent=t;pages.append(s);}else{const b=nav(t,t,false);b.className=t===state.page?'is-current':'';pages.append(b);}});pages.append(nav('Siguiente',state.page+1,state.page===max));};[search,period,situation].filter(Boolean).forEach(c=>c.addEventListener('input',()=>{state.page=1;render();}));size?.addEventListener('change',()=>{state.size=+size.value||10;state.page=1;render();});root.querySelectorAll('[data-tm-quick-filter]').forEach(b=>b.onclick=()=>{if(situation){situation.value=b.dataset.tmQuickFilter||'';state.page=1;render();}});render();
  const config=document.querySelector('#thesisTribunalConfig'),overlay=document.querySelector('[data-tribunal-modal]'),dialog=overlay?.querySelector('[data-tribunal-dialog]'),candidateList=overlay?.querySelector('[data-tribunal-candidates]'),selectedList=overlay?.querySelector('[data-tribunal-selected]'),modalSearch=overlay?.querySelector('[data-tribunal-search]'),modalCount=overlay?.querySelector('[data-tribunal-count]'),modalHelp=overlay?.querySelector('[data-tribunal-help]'),modalError=overlay?.querySelector('[data-tribunal-error]'),save=overlay?.querySelector('[data-tribunal-save]'),reason=overlay?.querySelector('[data-tribunal-reason]'),reasonWrap=overlay?.querySelector('[data-tribunal-reason-wrap]'),warning=overlay?.querySelector('[data-tribunal-defense]'),context=overlay?.querySelector('[data-tribunal-context]');let trigger=null,active=null,candidates=[],selected=new Set();
  const message=text=>{if(!modalError)return;modalError.hidden=!text;modalError.textContent=text||'';};const close=()=>{if(!overlay)return;overlay.hidden=true;document.body.classList.remove('tm-modal-open');trigger?.focus();};const draw=()=>{if(!active)return;const term=normalize(modalSearch?.value),limit=selected.size>=5;candidateList.replaceChildren();candidates.filter(p=>!term||normalize(`${p.full_name} ${p.email} ${p.institutional_code}`).includes(term)).forEach(p=>{const label=document.createElement('label');label.className='tm-candidate';const check=document.createElement('input');check.type='checkbox';check.checked=selected.has(+p.id);check.disabled=!check.checked&&limit;check.onchange=()=>{check.checked?selected.add(+p.id):selected.delete(+p.id);draw();};const text=document.createElement('span');text.innerHTML=`<strong></strong><small></small>`;text.querySelector('strong').textContent=p.full_name;text.querySelector('small').textContent=[p.academic_title,p.email].filter(Boolean).join(' · ');label.append(check,text);candidateList.append(label);});selectedList.replaceChildren(...[...selected].map(id=>{const p=candidates.find(x=>+x.id===id);const li=document.createElement('li');li.textContent=p?.full_name||`Docente #${id}`;return li;}));modalCount.textContent=`${selected.size} de 5 miembros seleccionados`;modalHelp.textContent=selected.size<3?'Selecciona al menos 3 docentes para conformar el Tribunal.':selected.size===5?'Se alcanzó el máximo de 5 miembros.':'Composición válida: puedes confirmar el Tribunal.';const defense=active.status==='defense';save.disabled=selected.size<3||selected.size>5||(defense&&reason.value.trim().length<5);};
  const open=async button=>{trigger=button;active={id:+button.dataset.projectId,status:button.dataset.projectStatus,code:button.dataset.projectCode,title:button.dataset.projectTitle};selected=new Set();
    message('');context.textContent=`${active.code} · ${active.title}`;warning.hidden=active.status!=='defense';reasonWrap.hidden=active.status!=='defense';reason.value='';overlay.hidden=false;document.body.classList.add('tm-modal-open');dialog.focus();candidateList.textContent='Cargando docentes disponibles…';try{const r=await fetch(`${config.dataset.candidates}&project_id=${active.id}`,{headers:{Accept:'application/json'}}),json=await r.json();if(!json.success)throw new Error(json.message);candidates=json.data.items||[];const existing=JSON.parse(button.dataset.currentMembers||'[]').map(Number);selected=new Set(candidates.filter(p=>existing.includes(+p.id)).map(p=>+p.id));draw();}catch(e){message(e.message||'No fue posible cargar docentes candidatos.');candidateList.replaceChildren();}};
  root.querySelectorAll('[data-tribunal-manage]').forEach(b=>b.onclick=()=>open(b));overlay?.querySelectorAll('[data-tribunal-close],[data-tribunal-cancel]').forEach(b=>b.onclick=close);overlay?.addEventListener('click',e=>{if(e.target===overlay)close();});document.addEventListener('keydown',e=>{if(e.key==='Escape'&&!overlay?.hidden)close();});modalSearch?.addEventListener('input',draw);reason?.addEventListener('input',draw);save?.addEventListener('click',async()=>{if(save.disabled||!active)return;save.disabled=true;message('');const body=new FormData();body.append('_csrf',config.dataset.csrf);body.append('project_id',active.id);body.append('expected_status',active.status);body.append('reason',reason.value);[...selected].forEach(id=>body.append('member_ids[]',id));try{const r=await fetch(config.dataset.save,{method:'POST',body,headers:{Accept:'application/json'}}),json=await r.json();if(!json.success)throw new Error(json.message);window.location.reload();}catch(e){message(e.message||'No fue posible actualizar el Tribunal.');draw();}});
})();

(() => {
  const config = document.querySelector('#thesisDefenseConfig');
  const overlay = document.querySelector('[data-defense-modal]');
  if (!config || !overlay) return;

  const dialog = overlay.querySelector('[data-defense-dialog]');
  const context = overlay.querySelector('[data-defense-context]');
  const project = overlay.querySelector('[data-defense-project]');
  const members = overlay.querySelector('[data-defense-members]');
  const error = overlay.querySelector('[data-defense-error]');
  const confirm = overlay.querySelector('[data-defense-confirm]');
  let trigger = null;

  const close = () => {
    overlay.hidden = true;
    document.body.classList.remove('tm-modal-open');
    trigger?.focus();
  };
  const showError = (message = '') => { error.textContent = message; error.hidden = !message; };
  document.querySelectorAll('[data-defense-send]').forEach((button) => button.addEventListener('click', () => {
    trigger = button;
    context.textContent = `${button.dataset.projectCode} · ${button.dataset.projectTitle}`;
    project.textContent = `${button.dataset.projectCode} — ${button.dataset.projectTitle}`;
    const selected = JSON.parse(button.dataset.members || '[]');
    members.replaceChildren(...selected.map((name) => { const item = document.createElement('li'); item.textContent = name; return item; }));
    showError(); overlay.hidden = false; document.body.classList.add('tm-modal-open'); dialog.focus();
  }));
  overlay.querySelectorAll('[data-defense-close],[data-defense-cancel]').forEach((button) => button.addEventListener('click', close));
  overlay.addEventListener('click', (event) => { if (event.target === overlay) close(); });
  document.addEventListener('keydown', (event) => { if (!overlay.hidden && event.key === 'Escape') close(); });
  confirm.addEventListener('click', async () => {
    if (confirm.disabled || !trigger) return;
    confirm.disabled = true; showError();
    const payload = new FormData();
    payload.set('_csrf', config.dataset.csrf || '');
    payload.set('project_id', trigger.dataset.projectId || '');
    payload.set('expected_status', 'approved');
    try {
      const response = await fetch(config.dataset.defense, { method: 'POST', body: payload, headers: { Accept: 'application/json' } });
      const result = await response.json();
      if (!response.ok || !result.success) throw new Error(result.message || 'No fue posible enviar el proyecto a Tribunal.');
      window.location.reload();
    } catch (exception) { showError(exception.message); confirm.disabled = false; }
  });
})();
