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
  const decisionDialog = document.querySelector("[data-adjustment-decision-dialog]");
  // El diálogo de creación debe escapar de cualquier contenedor con transform u overflow.
  if (createDialog && createDialog.parentElement !== document.body) document.body.appendChild(createDialog);
  if (decisionDialog && decisionDialog.parentElement !== document.body) document.body.appendChild(decisionDialog);
  if (createDialog) createDialog.hidden = true;
  document.body.classList.remove("project-adjustment-dialog-open");
  const createForm = createDialog?.querySelector("[data-adjustment-create-form]");
  const isPublishedModification = createForm?.querySelector('input[name="request_type"][value="published_modification"]') !== null;
  const createTitle = createDialog?.querySelector("#projectModificationTitle");
  const createFormBody = createForm?.querySelector("[data-adjustment-form-body]");
  const createConfirmation = createForm?.querySelector("[data-adjustment-confirmation]");
  const createFormFooter = createForm?.querySelector("[data-adjustment-form-footer]");
  const createConfirmationFooter = createForm?.querySelector("[data-adjustment-confirmation-footer]");
  const createConfirmationSubmit = createForm?.querySelector("[data-adjustment-confirmation-submit]");
  const createConfirmationMessage = createForm?.querySelector("[data-adjustment-confirmation-message]");
  const originalCreateTitle = createTitle?.textContent || "Solicitar modificación";
  let pendingCreateData = null;
  const setPublishedConfirmation = visible => {
    if (!isPublishedModification) return;
    if (createFormBody) createFormBody.hidden = visible;
    if (createConfirmation) createConfirmation.hidden = !visible;
    if (createFormFooter) createFormFooter.hidden = visible;
    if (createConfirmationFooter) createConfirmationFooter.hidden = !visible;
    if (createTitle) createTitle.textContent = visible ? "¿Enviar solicitud de modificación?" : originalCreateTitle;
    if (visible && createConfirmationMessage) {
      createConfirmationMessage.textContent = "";
      createConfirmationMessage.hidden = true;
      createConfirmationMessage.classList.remove("is-error");
    }
  };
  setPublishedConfirmation(false);
  const resetCreateState = () => {
    if (!isPublishedModification) return;
    pendingCreateData = null;
    setPublishedConfirmation(false);
    const formMessage = createForm?.querySelector("[data-adjustment-form-body] [data-adjustment-message]");
    if (formMessage) {
      formMessage.textContent = "";
      formMessage.hidden = true;
      formMessage.classList.remove("is-error");
    }
  };
  let returnFocus = null;
  let responseRequest = null;
  let lockedScrollX = 0;
  let lockedScrollY = 0;
  let bodyScrollState = null;
  let decisionConfirmationOpen = false;
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
  const close = dialog => { if (!dialog) return; if (dialog === createDialog) resetCreateState(); dialog.hidden = true; if (![createDialog,responseDialog,decisionDialog].some(item => item && !item.hidden)) restoreDocumentScroll(); returnFocus?.focus(); };
  const trap = (event, dialog) => { if (event.key === "Escape") { close(dialog); return; } if (event.key !== "Tab") return; const items=focusable(dialog); if (!items.length) return; const first=items[0],last=items.at(-1); if(event.shiftKey&&document.activeElement===first){event.preventDefault();last.focus();}else if(!event.shiftKey&&document.activeElement===last){event.preventDefault();first.focus();} };
  [createDialog,responseDialog].filter(Boolean).forEach(dialog => { dialog.addEventListener("keydown", event => trap(event,dialog)); dialog.addEventListener("click", event => { if(event.target===dialog) close(dialog); }); });
  createDialog?.addEventListener("click", event => {
    if (event.target !== createDialog && !event.target.closest("[data-adjustment-cancel]")) return;
    event.preventDefault();
    event.stopPropagation();
    close(createDialog);
  });

  const requestDecisionConfirmation = (operation, trigger) => new Promise(resolve => {
    if (!decisionDialog || decisionConfirmationOpen) { resolve(false); return; }
    const title = decisionDialog.querySelector("[data-adjustment-decision-title]");
    const message = decisionDialog.querySelector("[data-adjustment-decision-message]");
    const confirm = decisionDialog.querySelector("[data-adjustment-decision-confirm]");
    const reasonWrap = decisionDialog.querySelector("[data-adjustment-decision-reason-wrap]");
    const reason = decisionDialog.querySelector("[data-adjustment-decision-reason]");
    const decisionError = decisionDialog.querySelector("[data-adjustment-decision-error]");
    const cancelButtons = [...decisionDialog.querySelectorAll("[data-adjustment-decision-cancel]")];
    if (!title || !message || !confirm || !cancelButtons.length || !reasonWrap || !reason || !decisionError) { resolve({confirmed:false,rejectionReason:""}); return; }
    const approving = operation === "approve";
    title.textContent = approving ? "¿Aprobar solicitud de modificación?" : "¿Rechazar solicitud de modificación?";
    message.textContent = approving
      ? "Al aprobarla, el proyecto volverá a estar disponible para modificación por el estudiante."
      : "La solicitud será rechazada y el proyecto conservará su estado actual.";
    confirm.textContent = approving ? "Aprobar" : "Rechazar";
    confirm.classList.toggle("is-success", approving);
    confirm.classList.toggle("is-danger", !approving);
    reasonWrap.hidden = approving;
    reason.disabled = approving;
    reason.required = !approving;
    reason.value = "";
    decisionError.textContent = "";
    decisionError.hidden = true;
    decisionConfirmationOpen = true;
    let settled = false;
    const finish = (confirmed, rejectionReason = "") => {
      if (settled) return;
      settled = true;
      decisionConfirmationOpen = false;
      decisionDialog.removeEventListener("keydown", onKeydown);
      decisionDialog.removeEventListener("click", onBackdropClick);
      cancelButtons.forEach(button => button.removeEventListener("click", onCancel));
      confirm.removeEventListener("click", onConfirm);
      close(decisionDialog);
      resolve({confirmed,rejectionReason});
    };
    const onCancel = event => { event.preventDefault(); finish(false); };
    const onConfirm = event => {
      event.preventDefault();
      if (!approving) {
        const value = reason.value.trim();
        if (value.length < 5 || value.length > 500) {
          decisionError.textContent = "El motivo del rechazo debe contener entre 5 y 500 caracteres.";
          decisionError.hidden = false;
          reason.focus();
          return;
        }
        finish(true, value);
        return;
      }
      finish(true);
    };
    const onBackdropClick = event => { if (event.target === decisionDialog) finish(false); };
    const onKeydown = event => {
      if (event.key === "Escape") { event.preventDefault(); finish(false); return; }
      trap(event, decisionDialog);
    };
    cancelButtons.forEach(button => button.addEventListener("click", onCancel));
    confirm.addEventListener("click", onConfirm);
    decisionDialog.addEventListener("click", onBackdropClick);
    decisionDialog.addEventListener("keydown", onKeydown);
    open(decisionDialog, trigger);
    (approving ? confirm : reason).focus();
  });

  const common = () => ({ _csrf:config.dataset.csrf, project_id:config.dataset.projectId, expected_project_status:config.dataset.status, context:config.dataset.context });
  const request = async (endpoint, body) => {
    const response=await fetch(endpoint,{method:"POST",headers:{Accept:"application/json","Content-Type":"application/json"},body:JSON.stringify(body)});
    const result=await response.json().catch(()=>({success:false,message:"La respuesta del servidor no es válida."}));
    if(!response.ok||!result.success) throw new Error(result.message||"No fue posible completar la operación.");
    return result;
  };
  const show = (form, message, error=false, target=null) => { const node=target || form.querySelector("[data-adjustment-message]"); if(!node)return; node.textContent=message;node.hidden=false;node.classList.toggle("is-error",error); };
  const markModificationPending = () => {
    if (!isPublishedModification) return;
    const trigger = returnFocus?.matches?.('a[href="#projectAdjustmentDialog"]') ? returnFocus : document.querySelector('a[href="#projectAdjustmentDialog"]');
    if (!trigger?.parentElement) return;
    const pending = document.createElement("button");
    pending.type = "button";
    pending.disabled = true;
    pending.className = trigger.className;
    pending.setAttribute("aria-label", "Solicitud pendiente");
    pending.title = "Ya existe una solicitud de modificación pendiente.";
    pending.innerHTML = '<i class="fa-regular fa-clock" aria-hidden="true"></i><span>Solicitud pendiente</span>';
    trigger.replaceWith(pending);
    returnFocus = null;
  };
  const submit = async (form, endpoint, data, submitButton=null) => {
    const button=submitButton || form.querySelector('button[type="submit"]');
    if (button) button.disabled=true;
    try {
      const result=await request(endpoint,data);
      if (isPublishedModification) {
        markModificationPending();
        close(createDialog);
        window.AppToast?.success("Solicitud enviada correctamente.");
        return;
      }
      show(form,result.message);
      window.location.reload();
    } catch(error) {
      show(form,error.message,true,isPublishedModification ? createConfirmationMessage : null);
      if (isPublishedModification) window.AppToast?.error(error.message);
      if (button) button.disabled=false;
    }
  };

  document.addEventListener("click", async event => {
    const createTrigger=event.target.closest('a[href="#projectAdjustmentDialog"]');
    if(createTrigger){event.preventDefault();open(createDialog,createTrigger);return;}
    const confirmationBack=event.target.closest("[data-adjustment-confirmation-back]");
    if(confirmationBack){setPublishedConfirmation(false);createForm?.querySelector('textarea[name="message"]')?.focus();return;}
    const confirmationSubmit=event.target.closest("[data-adjustment-confirmation-submit]");
    if(confirmationSubmit && pendingCreateData){submit(createForm,config.dataset.create,pendingCreateData,confirmationSubmit);return;}
    if(event.target.closest("[data-adjustment-cancel]")){close(createDialog);return;}
    if(event.target.closest("[data-adjustment-response-cancel]")){close(responseDialog);return;}
    if(event.target.closest("[data-adjustment-decision-cancel],[data-adjustment-decision-confirm]")){return;}
    const respond=event.target.closest("[data-adjustment-respond]");
    if(respond){responseRequest={request_id:respond.dataset.requestId,lock_version:respond.dataset.lockVersion};open(responseDialog,respond);return;}
    const action=event.target.closest("[data-adjustment-address],[data-adjustment-close],[data-adjustment-approve],[data-adjustment-reject]");
    if(!action)return;
    const operation=action.hasAttribute("data-adjustment-close")?"close":(action.hasAttribute("data-adjustment-approve")?"approve":(action.hasAttribute("data-adjustment-reject")?"reject":"address"));
    let rejectionReason = "";
    if (operation === "approve" || operation === "reject") {
      const decision = await requestDecisionConfirmation(operation, action);
      if (!decision?.confirmed) return;
      rejectionReason = decision.rejectionReason || "";
    }
    action.disabled=true;
    try{
      const body={...common(),request_id:action.dataset.requestId,lock_version:action.dataset.lockVersion};
      if (operation === "reject") body.rejection_reason=rejectionReason;
      const result=await request(config.dataset[operation],body);
      const successMessage=result.message || "Solicitud rechazada correctamente.";
      if (operation === "reject") {
        let persisted=false;
        try { sessionStorage.setItem("digitalRecordToast", successMessage); persisted=true; } catch {}
        if (!persisted) window.AppToast?.success(successMessage);
      } else window.AppToast?.success(successMessage);
      window.location.reload();
    }catch(error){action.disabled=false;window.AppToast?.error(error.message);}
  });
  const createData = form => { const values=Object.fromEntries(new FormData(form)); return {...values,project_id:Number(values.project_id),file_id:values.file_id?Number(values.file_id):null}; };
  const validatePublishedModification = form => {
    const field=form.querySelector('textarea[name="message"]');
    if (!form.checkValidity()) { form.reportValidity(); return false; }
    if (!field || field.value.trim().length < 10) {
      show(form,"El motivo de la solicitud debe contener entre 10 y 2000 caracteres.",true);
      field?.focus();
      return false;
    }
    return true;
  };
  createForm?.addEventListener("submit", event => {
    event.preventDefault();
    const form=event.currentTarget;
    if (isPublishedModification) {
      if (!validatePublishedModification(form)) return;
      pendingCreateData=createData(form);
      setPublishedConfirmation(true);
      createConfirmationSubmit?.focus();
      return;
    }
    submit(form,config.dataset.create,createData(form));
  });
  responseDialog?.querySelector("form")?.addEventListener("submit", event => { event.preventDefault();const form=event.currentTarget;const message=String(new FormData(form).get("message")||"").trim();submit(form,config.dataset.respond,{...common(),...responseRequest,message}); });
})();
