(() => {
  "use strict";
  const config = document.querySelector("[data-adjustment-config]");
  if (!(config instanceof HTMLElement)) return;
  const record = document.querySelector('[data-digital-record][data-entity-type="project"]');
  const academicIcons={adjustment:"fa-clipboard-list","document-review":"fa-clipboard-check",file:"fa-file-pen",response:"fa-reply"};
  const enhanceTimeline = root => root.querySelectorAll?.(".ed-academic-event").forEach(item => {
    const archived=String(item.dataset.eventId||"").startsWith("document-archive:");
    const icon=archived?"fa-box-archive":academicIcons[item.dataset.eventType];const node=item.querySelector(".ed-academic-marker i");if(icon&&node)node.className=`fa-solid ${icon}`;
    if(archived){item.dataset.eventType="file";let meta=item.querySelector(".ed-academic-meta");if(!meta){meta=document.createElement("ul");meta.className="ed-academic-meta";item.querySelector(".ed-academic-content")?.append(meta);}if(meta&&![...meta.children].some(child=>child.textContent==="Archivada")){const badge=document.createElement("li");badge.textContent="Archivada";meta.append(badge);}}
  });
  enhanceTimeline(document);
  const academicTimeline=document.querySelector(".ed-academic-timeline");
  if(academicTimeline)new MutationObserver(()=>enhanceTimeline(academicTimeline)).observe(academicTimeline,{childList:true});

  const createDialog = document.querySelector("[data-adjustment-dialog]");
  const responseDialog = document.querySelector("[data-adjustment-response-dialog]");
  // El diálogo de creación debe escapar de cualquier contenedor con transform u overflow.
  if (createDialog && createDialog.parentElement !== document.body) document.body.appendChild(createDialog);
  if (createDialog) createDialog.hidden = true;
  document.body.classList.remove("project-adjustment-dialog-open");
  const createForm = createDialog?.querySelector("[data-adjustment-create-form]");
  let returnFocus = null;
  let responseRequest = null;
  let lockedScrollX = 0;
  let lockedScrollY = 0;
  let bodyScrollState = null;
  const focusable = dialog => [...dialog.querySelectorAll('button:not([disabled]),a[href],input:not([disabled]),select:not([disabled]),textarea:not([disabled])')];
  const lockDocumentScroll = () => {
    if (bodyScrollState) return;
    lockedScrollX = window.scrollX;
    lockedScrollY = window.scrollY;
    bodyScrollState = { position:document.body.style.position, top:document.body.style.top, left:document.body.style.left, right:document.body.style.right, width:document.body.style.width, overflow:document.body.style.overflow, htmlOverflow:document.documentElement.style.overflow };
    Object.assign(document.body.style,{position:"fixed",top:`-${lockedScrollY}px`,left:"0",right:"0",width:"100%",overflow:"hidden"});
    document.documentElement.style.overflow="hidden";
    document.body.classList.add("project-adjustment-dialog-open");
  };
  const restoreDocumentScroll = () => {
    if (!bodyScrollState) return;
    const previous=bodyScrollState;bodyScrollState=null;
    document.body.classList.remove("project-adjustment-dialog-open");
    const {htmlOverflow,...bodyState}=previous;
    Object.assign(document.body.style,bodyState);
    document.documentElement.style.overflow=htmlOverflow;
    window.scrollTo(lockedScrollX,lockedScrollY);
  };
  const open = (dialog, trigger) => { if (!dialog) return; returnFocus = trigger; lockDocumentScroll(); dialog.hidden = false; focusable(dialog)[0]?.focus(); };
  const close = dialog => { if (!dialog) return; dialog.hidden = true; if (![createDialog,responseDialog].some(item => item && !item.hidden)) restoreDocumentScroll(); returnFocus?.focus(); };
  const trap = (event, dialog) => { if (event.key === "Escape") { close(dialog); return; } if (event.key !== "Tab") return; const items=focusable(dialog); if (!items.length) return; const first=items[0],last=items.at(-1); if(event.shiftKey&&document.activeElement===first){event.preventDefault();last.focus();}else if(!event.shiftKey&&document.activeElement===last){event.preventDefault();first.focus();} };
  [createDialog,responseDialog].filter(Boolean).forEach(dialog => { dialog.addEventListener("keydown", event => trap(event,dialog)); dialog.addEventListener("click", event => { if(event.target===dialog) close(dialog); }); });
  createDialog?.addEventListener("click", event => {
    if (event.target !== createDialog && !event.target.closest("[data-adjustment-cancel]")) return;
    event.preventDefault();
    event.stopPropagation();
    close(createDialog);
  });

  const common = () => ({ _csrf:config.dataset.csrf, project_id:config.dataset.projectId, expected_project_status:config.dataset.status, context:config.dataset.context });
  const request = async (endpoint, body) => {
    const response=await fetch(endpoint,{method:"POST",headers:{Accept:"application/json","Content-Type":"application/json"},body:JSON.stringify(body)});
    const result=await response.json().catch(()=>({success:false,message:"La respuesta del servidor no es válida."}));
    if(!response.ok||!result.success) throw new Error(result.message||"No fue posible completar la operación.");
    return result;
  };
  const show = (form, message, error=false) => { const node=form.querySelector("[data-adjustment-message]"); if(!node)return; node.textContent=message;node.hidden=false;node.classList.toggle("is-error",error); };
  const submit = async (form, endpoint, data) => { const button=form.querySelector('button[type="submit"]');button.disabled=true;try{const result=await request(endpoint,data);show(form,result.message);window.location.reload();}catch(error){show(form,error.message,true);button.disabled=false;} };

  document.addEventListener("click", async event => {
    const createTrigger=event.target.closest('a[href="#projectAdjustmentDialog"]');
    if(createTrigger){event.preventDefault();open(createDialog,createTrigger);return;}
    if(event.target.closest("[data-adjustment-cancel]")){close(createDialog);return;}
    if(event.target.closest("[data-adjustment-response-cancel]")){close(responseDialog);return;}
    const respond=event.target.closest("[data-adjustment-respond]");
    if(respond){responseRequest={request_id:respond.dataset.requestId,lock_version:respond.dataset.lockVersion};open(responseDialog,respond);return;}
    const action=event.target.closest("[data-adjustment-address],[data-adjustment-close]");
    if(!action)return;
    const operation=action.hasAttribute("data-adjustment-close")?"close":"address";
    action.disabled=true;
    try{await request(config.dataset[operation],{...common(),request_id:action.dataset.requestId,lock_version:action.dataset.lockVersion});window.location.reload();}catch(error){action.disabled=false;window.alert(error.message);}
  });
  createForm?.addEventListener("submit", event => { event.preventDefault();const form=event.currentTarget;const values=Object.fromEntries(new FormData(form));submit(form,config.dataset.create,{...values,project_id:Number(values.project_id),file_id:values.file_id?Number(values.file_id):null}); });
  responseDialog?.querySelector("form")?.addEventListener("submit", event => { event.preventDefault();const form=event.currentTarget;const message=String(new FormData(form).get("message")||"").trim();submit(form,config.dataset.respond,{...common(),...responseRequest,message}); });
})();
