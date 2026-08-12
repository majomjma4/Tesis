(() => {
    const config = JSON.parse(document.querySelector('#wizardConfig')?.textContent || '{}');
    const form = document.querySelector('#projectWizardForm');
    if (!form) return;
    const steps = ['type', 'details', 'team', 'files', 'confirm'];
    const sections = Object.fromEntries(steps.map((key) => [key, document.querySelector(`[data-step="${key}"]`)]));
    const endpoints = config.draftEndpoints || {};
    const summary = document.querySelector('.wizard-error-summary');
    const status = document.querySelector('.wizard-autosave-status');
    const fileInput = document.querySelector('#projectFiles');
    const fileList = document.querySelector('[data-file-list]');
    const tagList = document.querySelector('[data-tag-list]');
    const tagInput = document.querySelector('#tagInput');
    let current = 0, uploading = 0, pendingReplace = null, dirty = false, dialogOpener = null;
    let draftFiles = [...(config.storedDraft?.files || [])];

    const announce = (message, error = false) => {
        if (!summary) return;
        summary.hidden = false;
        summary.replaceChildren();
        const title = document.createElement('strong'); title.textContent = error ? 'Revisa este paso' : 'Listo';
        const text = document.createElement('p'); text.textContent = message;
        summary.append(title, text);
        if (!error) window.setTimeout(() => { summary.hidden = true; }, 4200);
    };
    const setSaveStatus = (state) => {
        if (!status) return;
        status.textContent = state === 'saving' ? 'Guardando cambios…' : state === 'saved' ? 'Cambios guardados temporalmente.' : state === 'error' ? 'No fue posible guardar los cambios.' : '';
        status.dataset.state = state;
    };
    const typeData = () => config.types?.[form.elements.type?.value] || {};
    const selectedType = () => form.elements.type?.value || '';
    const actorId = () => String(config.student?.user_id || '');
    const tags = () => [...form.querySelectorAll('[name="tags[]"]')].map((item) => item.value);
    const bytes = (value) => `${(Number(value || 0) / 1048576).toFixed(1)} MB`;
    const request = async (url, body) => {
        const response = await fetch(url, { method: 'POST', body, credentials: 'same-origin', headers: { Accept: 'application/json' } });
        const result = await response.json().catch(() => null);
        if (response.status === 419) throw new Error(result?.message || 'Tu sesión expiró. Inicia sesión nuevamente para continuar.');
        if (!response.ok && !result?.data?.failed) throw new Error(result?.message || 'No se pudo completar la operación.');
        return result;
    };
    const formPayload = () => {
        const data = new FormData(form);
        data.set('_csrf', config.draftCsrf || '');
        data.set('current_step', steps[current]);
        return data;
    };
    async function saveDraft(showError = true) {
        if (!dirty) return true;
        setSaveStatus('saving');
        try {
            const result = await request(endpoints.save, formPayload());
            if (!result.success) throw new Error(result.message);
            config.storedDraft = result.data?.draft || config.storedDraft;
            dirty = false; setSaveStatus('saved'); return true;
        } catch (error) { setSaveStatus('error'); if (showError) announce(error.message || 'No fue posible guardar el borrador.', true); return false; }
    }
    const scheduleSave = () => { dirty = true; window.clearTimeout(form._draftTimer); form._draftTimer = window.setTimeout(() => void saveDraft(), 500); };

    function applyDefaults() {
        const type = typeData();
        if (type.default_title && !form.elements.title.value.trim()) form.elements.title.value = type.default_title;
        if (type.default_description && !form.elements.description.value.trim()) form.elements.description.value = type.default_description;
    }
    function updateConditionalFields() {
        const type = selectedType(); const contract = config.contract?.[type] || {};
        document.querySelectorAll('[data-conditional]').forEach((node) => { const active = (contract.additional || []).includes(node.dataset.conditional); node.hidden = !active; node.querySelector('select')?.toggleAttribute('required', active); });
        document.querySelectorAll('[data-thesis-only]').forEach((node) => { const active = type === 'thesis'; node.hidden = !active; node.querySelector('select')?.toggleAttribute('required', active); });
        applyDefaults(); updateTeamPolicy(); filterStudents(); renderMembers();
    }
    function updateTeamPolicy() {
        const individual = selectedType() === 'thesis' && form.elements.modality?.value === 'individual';
        form.querySelectorAll('[name="members[]"][type="checkbox"]').forEach((input) => {
            const creator = input.value === actorId(); input.disabled = creator || (individual && !creator);
            if (individual && !creator) input.checked = false;
            input.closest('label').hidden = individual && !creator;
        });
    }
    function renderMembers() {
        const box = document.querySelector('[data-selected-members]'); if (!box) return;
        const members = [...form.querySelectorAll('[name="members[]"][type="checkbox"]')].filter((input) => input.checked);
        box.replaceChildren(...members.map((input) => {
            const source = input.closest('label'); const card = document.createElement('div'); card.className = 'wizard-member-card';
            const name = source.querySelector('b')?.textContent || 'Estudiante'; const semester = source.querySelector('small')?.textContent || '';
            const avatar = document.createElement('span'); avatar.className = 'wizard-member-avatar'; avatar.textContent = name.split(/\s+/).slice(0, 2).map((part) => part[0]).join('').toUpperCase();
            const data = document.createElement('span'); const strong = document.createElement('strong'); strong.textContent = name; const detail = document.createElement('small'); detail.textContent = semester; data.append(strong, detail); card.append(avatar, data);
            if (input.value === actorId()) { const leader = document.createElement('em'); leader.textContent = 'Líder'; card.append(leader); }
            else { const remove = document.createElement('button'); remove.type = 'button'; remove.title = 'Quitar integrante'; remove.setAttribute('aria-label', `Quitar integrante ${name}`); remove.innerHTML = '<i class="fa-solid fa-xmark" aria-hidden="true"></i>'; remove.addEventListener('click', () => { input.checked = false; renderMembers(); scheduleSave(); updatePreview(); }); card.append(remove); }
            return card;
        }));
    }
    const resetTrigger = document.querySelector('[data-discard-draft]');
    function syncResetVisibility() {
        if (!resetTrigger) return;
        const hasFields = [...form.elements].some((field) => {
            if (field.type === 'hidden' || field.type === 'submit' || field.type === 'button' || field.name === '_csrf' || field.name === 'period') return false;
            if (field.type === 'checkbox' || field.type === 'radio') return field.checked;
            return String(field.value || '').trim() !== '';
        });
        resetTrigger.hidden = !(Boolean(config.storedDraft?.id) || draftFiles.length > 0 || dirty || current > 0 || hasFields);
    }
    function showStep(index, focus = true) {
        current = Math.max(0, Math.min(index, steps.length - 1)); updateConditionalFields();
        const active = steps[current]; Object.entries(sections).forEach(([key, section]) => { section.hidden = key !== active; section.classList.toggle('is-active', key === active); });
        document.querySelectorAll('[data-step-indicator]').forEach((node, position) => { node.classList.toggle('is-complete', position < current); node.toggleAttribute('aria-current', position === current); });
        document.querySelector('#wizardProgressText').textContent = `Paso ${current + 1} de 5`;
        document.querySelector('[data-previous]').hidden = current === 0; document.querySelector('[data-next]').hidden = current === steps.length - 1; document.querySelector('[data-submit]').hidden = current !== steps.length - 1;
        if (active === 'confirm') renderConfirmation(); updatePreview(); syncResetVisibility(); if (focus) sections[active].querySelector('h2')?.focus({ preventScroll: true });
    }
    function validateClient(target) {
        if (config.availabilityMessage) return announce(config.availabilityMessage, true), false;
        const type = selectedType(); if (!type) return announce('Selecciona un tipo de proyecto disponible.', true), false;
        if (target !== 'type') {
            if (form.elements.title.value.trim().length < 8) return announce('El título debe tener al menos 8 caracteres.', true), false;
            if (form.elements.description.value.trim().length < 30) return announce('La descripción debe tener al menos 30 caracteres.', true), false;
            if (type === 'thesis' && !form.elements.modality.value) return announce('Selecciona una modalidad.', true), false;
            if (['thesis', 'thesis_profile'].includes(type) && !form.elements.research_line.value) return announce('Selecciona una línea de investigación.', true), false;
        }
        if (['team', 'files', 'confirm'].includes(target)) {
            if (!form.elements.tutor_id.value) return announce('Selecciona un tutor disponible.', true), false;
            const members = [...form.querySelectorAll('[name="members[]"]:checked')].map((input) => input.value);
            if (!members.includes(actorId())) return announce('La persona creadora debe permanecer como líder del equipo.', true), false;
            if (type === 'thesis' && form.elements.modality.value === 'individual' && members.length > 1) return announce('La modalidad individual solo admite un estudiante.', true), false;
        }
        if (['files', 'confirm'].includes(target) && uploading > 0) return announce('Espera a que finalicen las cargas de archivos.', true), false;
        if (target === 'confirm' && draftFiles.some((file) => file.available === false)) return announce('Quita o vuelve a subir los archivos no disponibles.', true), false;
        return true;
    }
    document.querySelector('[data-next]')?.addEventListener('click', async () => { if (validateClient(steps[current]) && await saveDraft()) showStep(current + 1); });
    document.querySelector('[data-previous]')?.addEventListener('click', () => showStep(current - 1));
    form.addEventListener('submit', (event) => event.preventDefault());
    form.addEventListener('change', (event) => { if (event.target.name === 'type') applyDefaults(); updateConditionalFields(); updatePreview(); scheduleSave(); syncResetVisibility(); });
    form.addEventListener('input', () => { updatePreview(); scheduleSave(); syncResetVisibility(); });

    const memberPicker = document.querySelector('[data-member-picker]'); const memberSearch = document.querySelector('#memberSearch'); const memberSemester = document.querySelector('#memberSemester');
    document.querySelector('[data-members-open]')?.addEventListener('click', () => { memberPicker.hidden = false; memberSearch?.focus(); filterStudents(); });
    function filterStudents() {
        const semester = memberSemester?.value || ''; const query = memberSearch?.value.trim().toLocaleLowerCase() || ''; const cross = Boolean(config.contract?.[selectedType()]?.allows_cross_semester_members); let visible = 0;
        document.querySelectorAll('[data-student-results] > label').forEach((row) => { const okay = (!semester || row.dataset.semester === semester) && (cross || !config.student || row.dataset.semester === String(config.student.semester)) && (!query || row.dataset.studentName.includes(query)) && !row.hidden; row.classList.toggle('is-filtered', !okay); if (okay) visible++; });
        const empty = document.querySelector('[data-no-students]'); if (empty) empty.hidden = visible !== 0;
    }
    memberSearch?.addEventListener('input', filterStudents); memberSemester?.addEventListener('change', filterStudents);

    function renderTags(values) { tagList.replaceChildren(...values.slice(0, 8).map((tag) => { const pill = document.createElement('span'); pill.append(document.createTextNode(tag)); const hidden = document.createElement('input'); hidden.type = 'hidden'; hidden.name = 'tags[]'; hidden.value = tag; const remove = document.createElement('button'); remove.type = 'button'; remove.setAttribute('aria-label', `Eliminar etiqueta ${tag}`); remove.innerHTML = '<i class="fa-solid fa-xmark" aria-hidden="true"></i>'; remove.addEventListener('click', () => { pill.remove(); updatePreview(); scheduleSave(); }); pill.append(hidden, remove); return pill; })); }
    function addTag(value = tagInput?.value || '') { const tag = value.trim().replace(/\s+/g, ' '); const known = (config.knownTags || []).some((item) => item.toLocaleLowerCase() === tag.toLocaleLowerCase()); if (!tag) return; if (!known && (tag.length < 2 || tag.length > 120 || !/^[\p{L}\p{N}][\p{L}\p{N} ._-]*$/u.test(tag))) return announce('La etiqueta debe tener entre 2 y 120 caracteres válidos.', true); if (tags().some((item) => item.toLocaleLowerCase() === tag.toLocaleLowerCase())) return announce('Esa etiqueta ya fue agregada.', true); if (tags().length >= 8) return announce('Puedes agregar hasta 8 etiquetas.', true); renderTags([...tags(), tag]); tagInput.value = ''; updatePreview(); scheduleSave(); }
    document.querySelector('[data-add-tag]')?.addEventListener('click', () => addTag()); tagInput?.addEventListener('keydown', (event) => { if (event.key === 'Enter') { event.preventDefault(); addTag(); } }); document.querySelectorAll('[data-tag-value]').forEach((button) => button.addEventListener('click', () => addTag(button.dataset.tagValue)));

    function renderFiles() { fileList.replaceChildren(); if (!draftFiles.length) { const empty = document.createElement('p'); empty.textContent = uploading ? 'Subiendo archivos…' : 'No hay archivos seleccionados.'; fileList.append(empty); syncResetVisibility(); return; } draftFiles.forEach((file) => { const row = document.createElement('div'); const text = document.createElement('span'); const zip = file.zip_meta; text.textContent = zip?.valid ? `${file.original_name} · ${bytes(file.size_bytes)} · ${zip.files_count} archivos · ${zip.folders_count} carpetas · ZIP válido` : `${file.original_name} · ${bytes(file.size_bytes)}${file.available === false ? ' · No disponible' : ''}`; const remove = document.createElement('button'); remove.type = 'button'; remove.title = 'Quitar archivo'; remove.setAttribute('aria-label', `Quitar archivo ${file.original_name}`); remove.innerHTML = '<i class="fa-solid fa-xmark" aria-hidden="true"></i>'; remove.addEventListener('click', () => void removeFile(file)); row.append(text, remove); if (zip?.valid && Array.isArray(zip.entries)) { const details = document.createElement('details'); const title = document.createElement('summary'); title.textContent = 'Ver contenido'; const list = document.createElement('ul'); zip.entries.forEach((entry) => { const item = document.createElement('li'); item.textContent = `${entry.is_dir ? 'Carpeta: ' : ''}${entry.name}`; list.append(item); }); details.append(title, list); row.append(details); } fileList.append(row); }); syncResetVisibility(); }
    async function removeFile(file) { const body = new FormData(); body.set('_csrf', config.draftCsrf || ''); body.set('file_id', String(file.id)); try { const result = await request(endpoints.remove, body); draftFiles = result.data?.draft?.files || []; renderFiles(); updatePreview(); announce('Archivo eliminado.'); } catch (error) { announce(error.message || 'No se pudo eliminar el archivo.', true); } }
    async function uploadFiles(files, replace = false) { if (!files.length) return; uploading++; renderFiles(); updatePreview(); const body = new FormData(); body.set('_csrf', config.draftCsrf || ''); if (replace) body.set('replace', '1'); files.forEach((file) => body.append('files[]', file, file.name)); try { const result = await request(endpoints.upload, body); draftFiles = result.data?.draft?.files || draftFiles; if (result.data?.failed?.length) result.data.failed.forEach((failure) => { const original = files.find((file) => file.name === failure.name); if (failure.replace_file_id && original) { pendingReplace = { file: original }; openDialog(document.querySelector('[data-draft-replace-dialog]')); } else announce(`${failure.name}: ${failure.message}`, true); }); if (result.data?.added?.length) announce(result.data.added.length === 1 ? 'Archivo temporal agregado.' : 'Archivos temporales agregados.'); } catch (error) { announce(error.message || 'No se pudo subir el archivo.', true); } finally { uploading--; renderFiles(); updatePreview(); } }
    fileInput?.addEventListener('change', () => { const files = [...fileInput.files]; fileInput.value = ''; void uploadFiles(files); });
    const dropzone = document.querySelector('[data-file-dropzone]'); ['dragenter', 'dragover'].forEach((type) => dropzone?.addEventListener(type, (event) => { event.preventDefault(); dropzone.classList.add('is-dragging'); })); ['dragleave', 'drop'].forEach((type) => dropzone?.addEventListener(type, (event) => { event.preventDefault(); dropzone.classList.remove('is-dragging'); })); dropzone?.addEventListener('drop', (event) => void uploadFiles([...event.dataTransfer.files]));

    function tutorLabel() {
        const raw = form.elements.tutor_id?.selectedOptions?.[0]?.textContent?.split(' · ')[0]?.trim() || '';
        if (!raw || raw === 'Selecciona un tutor') return 'Sin seleccionar';
        const title = raw.match(/^(Msc\.?|Mgtr\.?|Ing\.?|Dr\.?|Dra\.?|Ph\.?D\.?|PhD)\s+/i)?.[1] || '';
        const name = raw.replace(/^(Msc\.?|Mgtr\.?|Ing\.?|Dr\.?|Dra\.?|Ph\.?D\.?|PhD)\s+/i, '').trim().split(/\s+/).filter(Boolean).slice(0, 2).join(' ');
        return `${title ? `${title} ` : ''}${name}`.trim() || 'Sin seleccionar';
    }
    const lookup = (name) => name === 'tutor_id' ? tutorLabel() : form.elements[name]?.selectedOptions?.[0]?.textContent?.trim() || 'Sin seleccionar';
    function previewData() { const members = new Set([...form.querySelectorAll('[name="members[]"]:checked,[name="members[]"][type="hidden"]')].map((field) => field.value)).size; const total = draftFiles.reduce((sum, file) => sum + Number(file.size_bytes || 0), 0); return [['Tipo', typeData().label || 'Sin seleccionar'], ['Título', form.elements.title?.value || 'Sin título'], ['Periodo', config.activePeriod?.code || 'Pendiente'], ['Carrera', config.student?.career_name || 'Pendiente'], ['Semestre', config.student ? `${config.student.semester}.º semestre` : 'Pendiente'], ['Tutor', lookup('tutor_id')], ['Integrantes', String(members)], ['Etiquetas', String(tags().length)], ['Archivos', `${draftFiles.length} · ${bytes(total)}`]]; }
    function fillSummary(target, data) { target.replaceChildren(...data.map(([label, value]) => { const row = document.createElement('div'); const key = document.createElement('dt'); const content = document.createElement('dd'); key.textContent = label; content.textContent = value; row.append(key, content); return row; })); }
    function updatePreview() { const preview = document.querySelector('[data-preview]'); if (preview) fillSummary(preview, previewData()); }
    function renderConfirmation() { const box = document.querySelector('[data-confirmation]'); if (box) fillSummary(box, previewData()); }

    function openDialog(dialog, opener = document.activeElement) { if (!dialog) return; document.querySelectorAll('.wizard-dialog:not([hidden])').forEach((item) => { if (item !== dialog) closeDialog(item, false); }); dialogOpener = opener instanceof HTMLElement ? opener : null; dialog.hidden = false; dialog.setAttribute('aria-hidden', 'false'); document.body.classList.add('wizard-modal-open'); dialog.querySelector('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])')?.focus(); }
    function closeDialog(dialog, restoreFocus = true) { if (!dialog) return; dialog.hidden = true; dialog.setAttribute('aria-hidden', 'true'); const open = document.querySelector('.wizard-dialog:not([hidden])'); if (!open) { document.body.classList.remove('wizard-modal-open'); const target = restoreFocus ? dialogOpener : null; dialogOpener = null; if (target && document.contains(target) && !target.hidden) target.focus(); } }
    const resumeDialog = document.querySelector('[data-draft-resume-dialog]'); const resetDialog = document.querySelector('[data-draft-reset-dialog]'); const replaceDialog = document.querySelector('[data-draft-replace-dialog]'); const registerDialog = document.querySelector('[data-draft-register-dialog]');
    document.addEventListener('keydown', (event) => { const dialog = document.querySelector('.wizard-dialog:not([hidden])'); if (!dialog) return; if (event.key === 'Escape') { event.preventDefault(); closeDialog(dialog); return; } if (event.key !== 'Tab') return; const focusable = [...dialog.querySelectorAll('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])')].filter((item) => !item.disabled && !item.hidden); if (!focusable.length) return; const first = focusable[0], last = focusable[focusable.length - 1]; if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); } else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); } });
    function restoreServerDraft() { const payload = config.storedDraft?.payload; if (!payload) return; Object.entries(payload).forEach(([name, value]) => [...form.elements].filter((field) => field.name === name || field.name === `${name}[]`).forEach((field) => { if (field.disabled) return; if (field.type === 'checkbox' || field.type === 'radio') field.checked = (Array.isArray(value) ? value : [value]).map(String).includes(field.value); else if (!Array.isArray(value)) field.value = value == null ? '' : String(value); })); renderTags((payload.tags || []).filter(Boolean)); dirty = false; updateConditionalFields(); showStep(Math.max(0, steps.indexOf(payload.current_step)), false); renderFiles(); announce('Borrador recuperado.'); }
    document.querySelector('[data-draft-continue]')?.addEventListener('click', () => { closeDialog(resumeDialog); restoreServerDraft(); }); document.querySelector('[data-draft-start-new]')?.addEventListener('click', (event) => openDialog(resetDialog, event.currentTarget)); document.querySelectorAll('[data-discard-draft]').forEach((button) => button.addEventListener('click', (event) => openDialog(resetDialog, event.currentTarget))); document.querySelector('[data-draft-reset-cancel]')?.addEventListener('click', () => closeDialog(resetDialog));
    document.querySelector('[data-draft-reset-confirm]')?.addEventListener('click', async () => { const body = new FormData(); body.set('_csrf', config.draftCsrf || ''); try { const result = await request(endpoints.reset, body); config.storedDraft = null; draftFiles = []; dirty = false; sessionStorage.removeItem(config.storageKey || ''); form.reset(); renderTags([]); closeDialog(resumeDialog, false); closeDialog(resetDialog); renderFiles(); showStep(0, false); syncResetVisibility(); announce(result.message || 'Borrador eliminado correctamente.'); } catch (error) { announce(error.message || 'No fue posible eliminar el borrador.', true); } });
    document.querySelector('[data-draft-replace-cancel]')?.addEventListener('click', () => { pendingReplace = null; closeDialog(replaceDialog); }); document.querySelector('[data-draft-replace-confirm]')?.addEventListener('click', () => { const pending = pendingReplace; pendingReplace = null; closeDialog(replaceDialog); if (pending) void uploadFiles([pending.file], true); });
    document.querySelector('[data-submit]')?.addEventListener('click', async (event) => { if (!validateClient('confirm') || !await saveDraft()) return; event.currentTarget.disabled = true; event.currentTarget.textContent = 'Validando…'; const body = new FormData(); body.set('_csrf', config.draftCsrf || ''); try { const result = await request(endpoints.preflight, body); if (!result.success) throw new Error(result.message); config.storedDraft = result.data?.draft || config.storedDraft; fillSummary(document.querySelector('[data-register-summary]'), [['Título', result.data?.summary?.title || form.elements.title.value], ['Tipo', result.data?.summary?.type_label || typeData().label], ['Tutor', lookup('tutor_id')], ['Integrantes', String(new Set([...form.querySelectorAll('[name="members[]"]:checked')].map((item) => item.value)).size)], ['Archivos', String(draftFiles.length)]]); openDialog(registerDialog); } catch (error) { announce(error.message || 'No fue posible validar el borrador.', true); } finally { event.currentTarget.disabled = false; event.currentTarget.innerHTML = 'Registrar proyecto <i class="fa-solid fa-check" aria-hidden="true"></i>'; } });
    document.querySelector('[data-draft-register-cancel]')?.addEventListener('click', () => closeDialog(registerDialog)); document.querySelector('[data-draft-register-confirm]')?.addEventListener('click', () => { closeDialog(registerDialog); announce('El registro definitivo se conectará en la siguiente fase. Tu borrador continúa guardado.'); });

    form.querySelectorAll('[name="type"]:disabled:checked').forEach((input) => { input.checked = false; }); renderTags((config.storedDraft?.payload?.tags || []).filter(Boolean)); updateConditionalFields(); showStep(0, false); renderFiles(); updatePreview(); syncResetVisibility(); if (config.storedDraft?.id) { const navigation = performance.getEntriesByType?.('navigation')?.[0]; if (navigation?.type === 'reload') restoreServerDraft(); else openDialog(resumeDialog); }
})();
