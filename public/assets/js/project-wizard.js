(() => {
    const root = document.querySelector("#projectWizard");
    const configNode = document.querySelector("#wizardConfig");
    if (!root || !configNode) return;
    const config = JSON.parse(configNode.textContent || "{}");
    const form = document.querySelector("#projectWizardForm");
    const storageKey = "academic_project_draft_v1";

    function serializableFields() {
        if (!form) return {};
        const result = {};
        new FormData(form).forEach((value, key) => {
            if (key === "_csrf" || key === "files[]" || value instanceof File) return;
            const cleanKey = key.endsWith("[]") ? key.slice(0, -2) : key;
            if (key.endsWith("[]")) (result[cleanKey] ||= []).push(String(value)); else result[cleanKey] = String(value);
        });
        return result;
    }
    function saveDraft(showMessage = true) {
        if (!form) return;
        sessionStorage.setItem(storageKey, JSON.stringify(serializableFields()));
        if (showMessage) announce("Borrador conservado en esta pestaña. Los archivos no se guardaron y deberán seleccionarse otra vez.");
    }
    function restoreDraft() {
        if (!form || Object.keys(config.serverErrors || {}).length) return;
        let data; try { data = JSON.parse(sessionStorage.getItem(storageKey) || "null"); } catch { data = null; }
        if (!data || typeof data !== "object") return;
        Object.entries(data).forEach(([name, value]) => {
            const controls = [...form.elements].filter((field) => field.name === name || field.name === `${name}[]`);
            controls.forEach((field) => {
                if (field.type === "checkbox" || field.type === "radio") field.checked = (Array.isArray(value) ? value : [value]).includes(field.value);
                else if (!Array.isArray(value)) field.value = value;
            });
        });
        renderTags((data.tags || []).filter(Boolean));
        announce("Restauramos el borrador de esta pestaña. Vuelve a seleccionar los archivos si los necesitas.");
    }
    function announce(message, error = false) {
        const summary = document.querySelector(".wizard-error-summary");
        if (!summary) return;
        summary.hidden = false; summary.innerHTML = `<strong>${error ? "Revisa este paso" : "Listo"}</strong><p></p>`;
        summary.querySelector("p").textContent = message;
        if (!error) setTimeout(() => { summary.hidden = true; }, 4500);
    }
    document.querySelectorAll("[data-save-draft]").forEach((button) => button.addEventListener("click", () => {
        if (form) saveDraft(); else { button.textContent = "Borrador conservado"; button.disabled = true; }
    }));
    document.querySelectorAll("[data-discard-draft]").forEach((button) => button.addEventListener("click", () => { sessionStorage.removeItem(storageKey); form?.reset(); location.reload(); }));
    document.querySelector("[data-edit-draft]")?.addEventListener("click", () => window.location.assign(`${window.location.pathname}${window.location.search}`));
    if (!form) return;

    const allSteps = ["type", "details", "team", "files", "confirm"];
    let visibleSteps = [...allSteps]; let current = 0;
    const stepSections = Object.fromEntries([...document.querySelectorAll("[data-step]")].map((node) => [node.dataset.step, node]));
    const indicatorNodes = [...document.querySelectorAll("[data-step-indicator]")];
    const previous = document.querySelector("[data-previous]"); const next = document.querySelector("[data-next]"); const submit = document.querySelector("[data-submit]");

    function selectedType() { return form.elements.type?.value || ""; }
    function updateConditionalFields() {
        const type = selectedType(); const typeContract = config.contract?.[type] || {}; const additional = typeContract.additional || [];
        document.querySelectorAll("[data-conditional]").forEach((label) => { const active = additional.includes(label.dataset.conditional); label.hidden = !active; label.querySelector("input,select").required = active; });
        document.querySelectorAll("[data-thesis-only]").forEach((node) => { node.hidden = type !== "thesis"; node.querySelector("select").required = type === "thesis"; });
        const descriptionField = document.querySelector("[data-description-field]");
        if (descriptionField) { descriptionField.hidden = type !== "" && typeContract.uses_description === false; descriptionField.querySelector("textarea").required = typeContract.uses_description === true; }
        visibleSteps = [...allSteps];
        indicatorNodes.forEach((node) => { node.hidden = false; node.classList.remove("is-skipped"); });
        updateTeamPolicy();
    }
    function updateTeamPolicy() {
        const individual = selectedType() === "thesis" && form.elements.modality?.value === "individual";
        const memberInputs = [...form.querySelectorAll('[name="members[]"][type="checkbox"]')];
        memberInputs.forEach((input) => {
            const isActor = config.policy.auto_leader && input.value === "student-1";
            input.disabled = isActor || (individual && !isActor);
            if (individual && !isActor) input.checked = false;
            input.closest("label").hidden = individual && !isActor;
        });
        if (form.elements.leader_id) form.elements.leader_id.required = Boolean(config.policy.must_select_leader);
    }
    function showStep(index, focus = true) {
        updateConditionalFields(); current = Math.max(0, Math.min(index, visibleSteps.length - 1)); const active = visibleSteps[current];
        Object.entries(stepSections).forEach(([key, section]) => { const isActive = key === active; section.hidden = !isActive; section.classList.toggle("is-active", isActive); });
        indicatorNodes.forEach((node) => { const position = visibleSteps.indexOf(node.dataset.stepIndicator); node.removeAttribute("aria-current"); node.classList.toggle("is-complete", position >= 0 && position < current); if (position === current) node.setAttribute("aria-current", "step"); });
        document.querySelector("#wizardProgressText").textContent = `Paso ${allSteps.indexOf(active) + 1} de 5`;
        previous.hidden = current === 0; next.hidden = current === visibleSteps.length - 1; submit.hidden = current !== visibleSteps.length - 1;
        if (active === "confirm") renderConfirmation();
        updatePreview(); if (focus) stepSections[active].querySelector("h2")?.focus({ preventScroll: true });
    }
    function fieldError(field, message) {
        field.setAttribute("aria-invalid", "true");
        let error = field.closest("label,fieldset")?.querySelector(".wizard-field-error.js-error");
        if (!error) { error = document.createElement("span"); error.className = "wizard-field-error js-error"; field.closest("label,fieldset")?.append(error); }
        error.textContent = message;
    }
    function clearClientErrors(section) { section.querySelectorAll("[aria-invalid]").forEach((f) => f.removeAttribute("aria-invalid")); section.querySelectorAll(".js-error").forEach((e) => e.remove()); }
    function validateCurrent() {
        const section = stepSections[visibleSteps[current]]; clearClientErrors(section); const invalid = [];
        section.querySelectorAll("input,select,textarea").forEach((field) => {
            if (field.disabled || field.closest("[hidden]") || field.type === "file") return;
            let message = "";
            if (field.required && field.type === "radio") { if (!section.querySelector(`[name="${field.name}"]:checked`)) message = "Selecciona una opción."; }
            else if (field.required && !field.value.trim()) message = "Este campo es obligatorio.";
            else if (field.name === "title" && field.value.trim().length < 8) message = "Escribe al menos 8 caracteres.";
            else if (field.name === "description" && field.value.trim().length < 30) message = "Escribe al menos 30 caracteres.";
            if (message && !invalid.some((item) => item.name === field.name)) { invalid.push(field); fieldError(field, message); }
        });
        const leader = form.elements.leader_id; if (leader?.value) { const members = [...form.querySelectorAll('[name="members[]"]:checked,[name="members[]"][type="hidden"]')].map((f) => f.value); if (!members.includes(leader.value)) { invalid.push(leader); fieldError(leader, "El líder debe estar seleccionado como integrante."); } }
        if (invalid.length) { announce(`${invalid.length} campo(s) necesitan atención.`, true); const customButton=invalid[0].closest(".wizard-custom-select")?.querySelector("button"); (customButton||invalid[0]).focus(); return false; }
        document.querySelector(".wizard-error-summary").hidden = true; return true;
    }
    next.addEventListener("click", () => { if (validateCurrent()) showStep(current + 1); });
    previous.addEventListener("click", () => showStep(current - 1));
    form.addEventListener("submit", (event) => { if (!validateCurrent()) event.preventDefault(); else saveDraft(false); });
    form.addEventListener("keydown", (event) => { if (event.key === "Enter" && event.target.matches("textarea")) return; });
    let autosaveTimer;
    function scheduleAutosave(){clearTimeout(autosaveTimer);autosaveTimer=setTimeout(()=>saveDraft(false),350);}
    form.addEventListener("input", () => { updateConditionalFields(); updatePreview(); scheduleAutosave(); });
    form.addEventListener("change", () => { updateConditionalFields(); updatePreview(); scheduleAutosave(); });

    const memberSemester=document.querySelector("#memberSemester");const memberSearch=document.querySelector("#memberSearch");
    function filterStudents(){const semester=memberSemester?.value||"";const query=(memberSearch?.value||"").trim().toLocaleLowerCase("es");let visible=0;document.querySelectorAll("[data-student-name]").forEach((row)=>{const show=row.dataset.semester===semester&&row.dataset.studentName.includes(query);row.hidden=!show;if(show)visible++;});const empty=document.querySelector("[data-no-students]");if(empty)empty.hidden=visible>0;}
    memberSemester?.addEventListener("change",filterStudents);memberSearch?.addEventListener("input",filterStudents);

    function enhanceSelect(select){
        if(select.dataset.enhanced||select.multiple)return;select.dataset.enhanced="true";select.classList.add("wizard-native-select");
        const wrapper=document.createElement("div");wrapper.className="wizard-custom-select";select.parentNode.insertBefore(wrapper,select);wrapper.append(select);
        const trigger=document.createElement("button");trigger.type="button";trigger.setAttribute("aria-haspopup","listbox");trigger.setAttribute("aria-expanded","false");
        const list=document.createElement("ul");list.className="wizard-custom-options";list.setAttribute("role","listbox");list.hidden=true;
        function refresh(){trigger.textContent=select.selectedOptions[0]?.textContent||"Selecciona una opción";[...list.children].forEach((item)=>item.setAttribute("aria-selected",String(item.dataset.value===select.value)));}
        [...select.options].forEach((option)=>{const item=document.createElement("li");item.setAttribute("role","option");item.tabIndex=-1;item.dataset.value=option.value;item.textContent=option.textContent;item.addEventListener("click",()=>{select.value=option.value;select.dispatchEvent(new Event("change",{bubbles:true}));close();refresh();trigger.focus();});list.append(item);});
        function open(){document.querySelectorAll(".wizard-custom-select.is-open").forEach((node)=>{if(node!==wrapper)node.querySelector("button")?.click();});wrapper.classList.add("is-open");list.hidden=false;trigger.setAttribute("aria-expanded","true");(list.querySelector('[aria-selected="true"]')||list.firstElementChild)?.focus();}
        function close(){wrapper.classList.remove("is-open");list.hidden=true;trigger.setAttribute("aria-expanded","false");}
        trigger.addEventListener("click",()=>wrapper.classList.contains("is-open")?close():open());
        list.addEventListener("keydown",(event)=>{const items=[...list.children],index=items.indexOf(document.activeElement);if(event.key==="ArrowDown"){event.preventDefault();items[Math.min(index+1,items.length-1)]?.focus();}else if(event.key==="ArrowUp"){event.preventDefault();items[Math.max(index-1,0)]?.focus();}else if(event.key==="Enter"||event.key===" "){event.preventDefault();document.activeElement.click();}else if(event.key==="Escape"){close();trigger.focus();}});
        wrapper.append(trigger,list);refresh();select.addEventListener("change",refresh);
    }
    document.addEventListener("click",(event)=>{document.querySelectorAll(".wizard-custom-select.is-open").forEach((node)=>{if(!node.contains(event.target))node.querySelector("button")?.click();});});

    const tagInput = document.querySelector("#tagInput"); const tagList = document.querySelector("[data-tag-list]");
    function renderTags(tags) { tagList.replaceChildren(...tags.slice(0, 8).map((tag) => { const pill=document.createElement("span");pill.append(document.createTextNode(tag));const hidden=document.createElement("input");hidden.type="hidden";hidden.name="tags[]";hidden.value=tag;const remove=document.createElement("button");remove.type="button";remove.textContent="×";remove.setAttribute("aria-label",`Eliminar etiqueta ${tag}`);remove.addEventListener("click",()=>{pill.remove();updatePreview();});pill.append(hidden,remove);return pill; })); }
    function addTag() { const tag=tagInput.value.trim();const tags=[...tagList.querySelectorAll("input")].map((f)=>f.value);if(tag&&!tags.includes(tag)&&tags.length<8)renderTags([...tags,tag]);tagInput.value="";updatePreview(); }
    document.querySelector("[data-add-tag]")?.addEventListener("click",addTag); tagInput?.addEventListener("keydown",(e)=>{if(e.key==="Enter"){e.preventDefault();addTag();}});

    const fileInput=document.querySelector("#projectFiles");const fileList=document.querySelector("[data-file-list]");let selectedFiles=[];
    function syncFiles(){const transfer=new DataTransfer();selectedFiles.forEach((file)=>transfer.items.add(file));fileInput.files=transfer.files;renderFiles();updatePreview();}
    function renderFiles(){fileList.replaceChildren();if(!selectedFiles.length){const p=document.createElement("p");p.textContent="No hay archivos seleccionados.";fileList.append(p);return;}selectedFiles.forEach((file,index)=>{const row=document.createElement("div");const text=document.createElement("span");text.textContent=`${file.name} · ${(file.size/1048576).toFixed(1)} MB`;const remove=document.createElement("button");remove.type="button";remove.textContent="Eliminar";remove.setAttribute("aria-label",`Eliminar ${file.name}`);remove.addEventListener("click",()=>{selectedFiles.splice(index,1);syncFiles();});row.append(text,remove);fileList.append(row);});}
    fileInput?.addEventListener("change",()=>{selectedFiles=[...fileInput.files];const total=selectedFiles.reduce((sum,f)=>sum+f.size,0);const duplicate=new Set(selectedFiles.map(f=>f.name.toLowerCase())).size!==selectedFiles.length;if(duplicate||selectedFiles.some(f=>f.size>config.fileLimits.max_bytes)||total>config.fileLimits.max_total_bytes)announce("Revisa nombres duplicados y los límites de tamaño de los archivos.",true);renderFiles();updatePreview();});

    function lookup(selectName){const field=form.elements[selectName];return field?.selectedOptions?.[0]?.textContent?.trim()||"Pendiente";}
    function previewData(){const type=config.types?.[selectedType()];const participants=new Set([...form.querySelectorAll('[name="members[]"]:checked,[name="members[]"][type="hidden"]')].map((field)=>field.value)).size;return [["Tipo",type?.label||"Sin seleccionar"],["Título",form.elements.title?.value||"Sin título"],[config.policy.actor_type==="student"?"Tutor propuesto":"Responsable",lookup("tutor_id")],["Periodo",form.elements.period?.value||"Pendiente"],["Modalidad",selectedType()==="thesis"?lookup("modality"):"Según tipo"],["Participantes",String(participants)],["Etiquetas",String(tagList.querySelectorAll("input").length)],["Archivos",String(selectedFiles.length)]];}
    function updatePreview(){const dl=document.querySelector("[data-preview]");if(!dl)return;dl.replaceChildren(...previewData().map(([term,value])=>{const div=document.createElement("div"),dt=document.createElement("dt"),dd=document.createElement("dd");dt.textContent=term;dd.textContent=value;div.append(dt,dd);return div;}));}
    function renderConfirmation(){const box=document.querySelector("[data-confirmation]");box.replaceChildren(...previewData().map(([term,value])=>{const div=document.createElement("div"),span=document.createElement("span"),strong=document.createElement("strong");span.textContent=term;strong.textContent=value;div.append(span,strong);return div;}));const prefix=config.types?.[selectedType()]?.prefix||"PRY";document.querySelector("[data-code-preview]").textContent=`${prefix}-${new Date().getFullYear()}-XXXX`;}

    restoreDraft(); updateConditionalFields(); filterStudents(); document.querySelectorAll(".project-wizard select").forEach(enhanceSelect);
    const firstServerError=Object.keys(config.serverErrors||{}).find((key)=>!key.startsWith("_")&&!key.startsWith("files"));
    if(firstServerError){const field=form.elements[firstServerError];const owner=field?.closest("[data-step]");const index=visibleSteps.indexOf(owner?.dataset.step);showStep(index>=0?index:0,false);document.querySelector(".wizard-error-summary")?.focus();}else showStep(0,false);
    updatePreview();
})();
