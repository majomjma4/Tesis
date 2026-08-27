(() => {
  const trigger = document.querySelector('[data-teacher-content-trigger]');
  const selector = document.querySelector('[data-repository-content-selector]');
  const direct = document.querySelector('[data-direct-project-modal]');
  if (!trigger || !selector || trigger.dataset.repositoryContentInitialized) return;
  trigger.dataset.repositoryContentInitialized = '1';
  const form = direct?.querySelector('[data-direct-project-form]');
  let activeModal = null;
  let returnFocus = trigger;
  let authors = [];
  let tutor = null;
  let tutors = [];
  let primaryTutorId = 0;
  let files = [];
  let submitting = false;
  let currentStep = 1;
  const lock = on => { document.documentElement.classList.toggle('teacher-material-modal-open', on); document.body.classList.toggle('teacher-material-modal-open', on); };
  const openModal = modal => { activeModal = modal; modal.hidden = false; lock(true); (modal === direct ? form?.querySelector('[name="title"]') : modal.querySelector('[data-repository-content-project], [data-repository-content-material]'))?.focus(); };
  const closeModal = (modal, restore = true) => { if (!modal) return; modal.hidden = true; if (modal === direct && !submitting) resetDirectForm(); if (activeModal === modal) activeModal = null; lock(false); if (restore) returnFocus?.focus(); };
  const clearError = () => { if (!form) return; form.querySelector('[data-direct-project-error]').hidden = true; form.querySelectorAll('[data-direct-error-for]').forEach(node => { node.textContent = ''; }); };
  const message = text => { const node = form?.querySelector('[data-direct-project-error]'); if (node) { node.textContent = text; node.hidden = !text; } };
  const token = () => window.crypto?.randomUUID?.() || `${Date.now()}-${Math.random().toString(36).slice(2)}-${Math.random().toString(36).slice(2)}`;
  let idempotencyToken = token();
  const stepPanels = [...(form?.querySelectorAll('[data-direct-step-panel]') || [])];
  const stepIndicators = [...(form?.querySelectorAll('[data-direct-step-indicator]') || [])];
  const stepPrevious = form?.querySelector('[data-direct-project-previous]');
  const stepNext = form?.querySelector('[data-direct-project-next]');
  const stepSubmit = form?.querySelector('[data-direct-project-submit]');
  const summary = form?.querySelector('[data-direct-project-summary]');
  const typeSelect = form?.elements.project_type_id;
  const keywordSelector = form?.querySelector('[data-direct-keyword-selector]');
  const keywordTrigger = keywordSelector?.querySelector('[data-direct-keyword-trigger]');
  const keywordPanel = keywordSelector?.querySelector('[data-direct-keyword-panel]');
  const keywordSearch = keywordSelector?.querySelector('[data-direct-keyword-search]');
  const keywordOptions = keywordSelector?.querySelector('[data-direct-keyword-options]');
  const keywordSummary = keywordSelector?.querySelector('[data-direct-keyword-summary]');
  let currentAutoTitle = '';
  let currentAutoDescription = '';
  const applyTypeDefaults = () => {
    const option = typeSelect?.selectedOptions?.[0];
    const nextTitle = String(option?.getAttribute('data-default-title') || '').trim();
    const nextDescription = String(option?.getAttribute('data-registration-description') || '').trim();
    const title = form?.elements.title;
    const description = form?.elements.description;
    const currentTitle = String(title?.value || '').trim();
    const currentDescription = String(description?.value || '').trim();
    if (!title || !description) return;
    if (currentTitle === '' || currentTitle === currentAutoTitle) {
      title.value = nextTitle;
      currentAutoTitle = nextTitle;
    } else {
      currentAutoTitle = '';
    }
    if (currentDescription === '' || currentDescription === currentAutoDescription) {
      description.value = nextDescription;
      currentAutoDescription = nextDescription;
    } else {
      currentAutoDescription = '';
    }
  };
  const normalizeKeyword = value => String(value ?? '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLocaleLowerCase('es');
  const closeKeywordSelector = (restoreFocus = false) => { if (!keywordPanel || !keywordTrigger) return; keywordPanel.hidden = true; keywordSelector?.classList.remove('is-open'); keywordTrigger.setAttribute('aria-expanded', 'false'); if (restoreFocus) keywordTrigger.focus(); };
  const updateKeywordSelection = () => { const inputs = [...(keywordOptions?.querySelectorAll('input[type="checkbox"]') || [])]; const selected = inputs.filter(input => input.checked); inputs.forEach(input => input.closest('[role="option"]')?.setAttribute('aria-selected', String(input.checked))); if (keywordSummary) keywordSummary.textContent = selected.length ? `${selected.length} ${selected.length === 1 ? 'etiqueta seleccionada' : 'etiquetas seleccionadas'}` : 'Selecciona etiquetas de clasificación'; };
  const filterKeywordOptions = () => { const query = normalizeKeyword(keywordSearch?.value || ''); keywordOptions?.querySelectorAll('[role="option"]').forEach(option => { option.hidden = query !== '' && !normalizeKeyword(option.dataset.keywordSearch || option.textContent).includes(query); }); };
  const resetDirectForm = () => { form?.reset(); authors = []; tutors = []; primaryTutorId = 0; tutor = null; files = []; currentAutoTitle = ''; currentAutoDescription = ''; idempotencyToken = token(); currentStep = 1; renderAuthors(); renderTutor(); renderFiles(); updateKeywordSelection(); filterKeywordOptions(); closeKeywordSelector(); clearError(); };
  const updateSummary = () => { if (!summary) return; const type = form.elements.project_type_id?.selectedOptions?.[0]?.textContent || 'Sin seleccionar'; const period = direct.querySelector('input[readonly]')?.value || 'No disponible'; summary.replaceChildren(); const heading = document.createElement('h4'); heading.textContent = 'Resumen de publicación'; const list = document.createElement('dl'); [['Proyecto', form.elements.title.value.trim() || 'Sin título'], ['Tipo', type], ['Autores', `${authors.length} ${authors.length === 1 ? 'autor' : 'autores'}`], ['Tutores', `${tutors.length} ${tutors.length === 1 ? 'tutor' : 'tutores'}`], ['Tutor principal', tutors.find(person => person.id === primaryTutorId)?.name || 'Sin tutor'], ['Archivos', `${files.length} ${files.length === 1 ? 'archivo' : 'archivos'}`], ['PAO', period]].forEach(([label, value]) => { const row = document.createElement('div'); const term = document.createElement('dt'); term.textContent = label; const detail = document.createElement('dd'); detail.textContent = value; row.append(term, detail); list.append(row); }); summary.append(heading, list); };
  const showStep = step => { currentStep = Math.max(1, Math.min(3, step)); stepPanels.forEach(panel => { const active = Number(panel.dataset.directStepPanel) === currentStep; panel.hidden = !active; panel.classList.toggle('is-active', active); }); stepIndicators.forEach(indicator => { const value = Number(indicator.dataset.directStepIndicator); indicator.toggleAttribute('aria-current', value === currentStep); indicator.classList.toggle('is-complete', value < currentStep); indicator.disabled = value > currentStep + 1; indicator.setAttribute('aria-disabled', String(value > currentStep + 1)); }); if (stepPrevious) stepPrevious.hidden = currentStep === 1; if (stepNext) stepNext.hidden = currentStep === 3; if (stepSubmit) stepSubmit.hidden = currentStep !== 3; updateSummary(); const heading = form?.querySelector(`[data-direct-step-panel="${currentStep}"] h3`); heading?.focus(); };
  const goToStep = target => { if (target < currentStep) { showStep(target); return; } if (target === currentStep) return; if (target !== currentStep + 1 || !validateStep(currentStep)) return; showStep(target); };
  const fieldError = (name, text) => { const node = form?.querySelector(`[data-direct-error-for="${name}"]`); if (node) node.textContent = text; };
  const validateStep = step => { clearError(); if (step === 1) { const title = form.elements.title.value.trim(); const type = form.elements.project_type_id.value; const description = form.elements.description.value.trim(); if (title.length < 5 || title.length > 240) { fieldError('title', 'El título debe tener entre 5 y 240 caracteres.'); form.elements.title.focus(); return false; } if (!type) { fieldError('project_type_id', 'Selecciona un tipo de proyecto.'); form.elements.project_type_id.focus(); return false; } if (description.length < 30) { fieldError('description', 'La descripción debe tener al menos 30 caracteres.'); form.elements.description.focus(); return false; } } if (step === 2 && authors.length === 0) { fieldError('author_ids', 'Selecciona al menos un autor.'); form.querySelector('[data-direct-people-search="students"]')?.focus(); return false; } if (step === 2 && tutors.length === 0) { fieldError('tutoring_user_ids', 'Selecciona al menos un tutor.'); form.querySelector('[data-direct-people-search="tutors"]')?.focus(); return false; } if (step === 2 && !tutors.some(person => person.id === primaryTutorId)) { fieldError('tutoring_primary_id', 'Selecciona un tutor principal.'); return false; } if (step === 3 && files.length === 0) { fieldError('files', 'Agrega al menos un archivo.'); return false; } return true; };
  const escapeText = value => String(value ?? '');
  const renderAuthors = () => {
    const box = form.querySelector('[data-direct-selected-authors]'); box.replaceChildren();
    authors.forEach((person, index) => { const chip = document.createElement('span'); chip.className = 'teacher-direct-project-chip'; chip.append(`${index === 0 ? 'Autor principal: ' : ''}${escapeText(person.name)}`); const remove = document.createElement('button'); remove.type = 'button'; remove.setAttribute('aria-label', `Quitar ${person.name}`); remove.textContent = '×'; remove.addEventListener('click', () => { authors = authors.filter(item => item.id !== person.id); renderAuthors(); }); chip.append(remove); box.append(chip); });
  };
  const renderTutor = () => { const box = form.querySelector('[data-direct-selected-tutors]'); if (!box) return; box.replaceChildren(); tutors.forEach(person => { const chip = document.createElement('span'); chip.className = 'teacher-direct-project-chip'; chip.append(`${person.id === primaryTutorId ? 'Tutor principal: ' : ''}${escapeText(person.name)}`); if (person.id !== primaryTutorId) { const primary = document.createElement('button'); primary.type = 'button'; primary.textContent = 'Hacer principal'; primary.addEventListener('click', () => { primaryTutorId = person.id; tutor = person; renderTutor(); }); chip.append(primary); } const remove = document.createElement('button'); remove.type = 'button'; remove.setAttribute('aria-label', `Quitar ${person.name}`); remove.textContent = '×'; remove.addEventListener('click', () => { tutors = tutors.filter(item => item.id !== person.id); if (primaryTutorId === person.id) primaryTutorId = tutors[0]?.id || 0; tutor = tutors.find(item => item.id === primaryTutorId) || null; renderTutor(); }); chip.append(remove); box.append(chip); }); };
  const renderFiles = () => { const list = form.querySelector('[data-direct-file-list]'); list.replaceChildren(...files.map((file, index) => { const item = document.createElement('li'); item.append(`${file.name} (${Math.ceil(file.size / 1024)} KB)`); const remove = document.createElement('button'); remove.type = 'button'; remove.textContent = 'Quitar'; remove.addEventListener('click', () => { files.splice(index, 1); renderFiles(); }); item.append(remove); return item; })); };
  const addFiles = incoming => { incoming.forEach(file => { if (!files.some(existing => existing.name === file.name && existing.size === file.size && existing.lastModified === file.lastModified)) files.push(file); }); renderFiles(); };
  const renderResults = (kind, items) => { const box = form.querySelector(`[data-direct-results="${kind}"]`); const input = form.querySelector(`[data-direct-people-search="${kind}"]`); box.replaceChildren(); items.filter(person => kind === 'students' ? !authors.some(item => item.id === person.id) : !tutors.some(item => item.id === person.id)).forEach(person => { const button = document.createElement('button'); button.type = 'button'; button.textContent = `${person.name}${person.identification || person.code ? ` · ${person.identification || person.code}` : ''}`; button.addEventListener('click', () => { if (kind === 'students') { if (!authors.some(item => item.id === person.id)) authors.push(person); renderAuthors(); } else if (!tutors.some(item => item.id === person.id)) { tutors.push(person); if (!primaryTutorId) primaryTutorId = person.id; tutor = person; renderTutor(); } input.value = ''; box.replaceChildren(); box.hidden = true; updateSummary(); }); box.append(button); }); box.hidden = box.children.length === 0; };
  const searchPeople = (input, kind) => { let timer; input.addEventListener('input', () => { clearTimeout(timer); const query = input.value.trim(); if (query.length < 2) { renderResults(kind, []); return; } timer = setTimeout(async () => { try { const url = new URL(form.dataset.searchEndpoint, window.location.href); url.searchParams.set('kind', kind); url.searchParams.set('q', query); const response = await fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' }); const payload = await response.json(); renderResults(kind, response.ok && payload.success ? (payload.data?.items || []) : []); } catch (_) { renderResults(kind, []); } }, 300); }); };
  form?.querySelectorAll('[data-direct-people-search]').forEach(input => searchPeople(input, input.dataset.directPeopleSearch));
  keywordTrigger?.addEventListener('click', () => { const open = keywordPanel?.hidden; if (open) { keywordPanel.hidden = false; keywordSelector?.classList.add('is-open'); keywordTrigger.setAttribute('aria-expanded', 'true'); keywordSearch?.focus(); } else closeKeywordSelector(); });
  keywordSearch?.addEventListener('input', filterKeywordOptions);
  keywordOptions?.addEventListener('change', updateKeywordSelection);
  keywordOptions?.addEventListener('keydown', event => { if (event.key === 'Escape') { event.preventDefault(); closeKeywordSelector(true); return; } if (!['ArrowDown', 'ArrowUp', 'Home', 'End'].includes(event.key)) return; const visible = [...keywordOptions.querySelectorAll('input[type="checkbox"]')].filter(input => !input.closest('[role="option"]')?.hidden); if (!visible.length) return; event.preventDefault(); const index = visible.indexOf(event.target); const next = event.key === 'Home' ? 0 : event.key === 'End' ? visible.length - 1 : (index + (event.key === 'ArrowDown' ? 1 : -1) + visible.length) % visible.length; visible[next].focus(); });
  updateKeywordSelection();
  form?.querySelector('[data-direct-files]')?.addEventListener('change', event => { addFiles([...event.target.files]); event.target.value = ''; });
  const dropzone = form?.querySelector('[data-direct-dropzone]');
  ['dragenter', 'dragover'].forEach(type => dropzone?.addEventListener(type, event => { event.preventDefault(); dropzone.classList.add('is-dragover'); }));
  ['dragleave', 'drop'].forEach(type => dropzone?.addEventListener(type, event => { event.preventDefault(); dropzone.classList.remove('is-dragover'); }));
  dropzone?.addEventListener('drop', event => addFiles([...event.dataTransfer.files]));
  trigger.addEventListener('click', () => { returnFocus = trigger; openModal(selector); });
  stepIndicators.forEach(indicator => indicator.addEventListener('click', () => goToStep(Number(indicator.dataset.directStepIndicator))));
  selector.querySelector('[data-repository-content-project]')?.addEventListener('click', () => { closeModal(selector, false); returnFocus = trigger; resetDirectForm(); openModal(direct); showStep(1); });
  selector.querySelector('[data-repository-content-material]')?.addEventListener('click', () => { closeModal(selector, false); returnFocus = trigger; if (typeof window.openTeacherMaterialModal === 'function') window.openTeacherMaterialModal(); });
  selector.querySelectorAll('[data-repository-content-close]').forEach(button => button.addEventListener('click', () => closeModal(selector)));
  direct?.querySelectorAll('[data-direct-project-close]').forEach(button => button.addEventListener('click', () => closeModal(direct)));
  [selector, direct].forEach(modal => modal?.addEventListener('click', event => { if (event.target === modal) closeModal(modal); }));
  document.addEventListener('keydown', event => { if (event.key !== 'Escape' || !activeModal) return; if (activeModal === direct && keywordPanel && !keywordPanel.hidden) { closeKeywordSelector(true); return; } closeModal(activeModal); });
  stepNext?.addEventListener('click', () => goToStep(currentStep + 1));
  stepPrevious?.addEventListener('click', () => goToStep(currentStep - 1));
  form?.addEventListener('input', updateSummary);
  typeSelect?.addEventListener('change', applyTypeDefaults);
  form?.addEventListener('change', updateSummary);
  form?.addEventListener('submit', async event => {
    event.preventDefault(); if (submitting) return; if (currentStep !== 3) { if (validateStep(currentStep)) showStep(currentStep + 1); return; } clearError();
    if (authors.length === 0) { const field = form.querySelector('[data-direct-error-for="author_ids"]'); field.textContent = 'Selecciona al menos un autor.'; form.querySelector('[data-direct-people-search="students"]')?.focus(); return; }
    if (tutors.length === 0 || !tutors.some(person => person.id === primaryTutorId)) { fieldError('tutoring_user_ids', 'Selecciona los tutores y un tutor principal.'); showStep(2); return; }
    submitting = true; const submit = form.querySelector('[type="submit"]'); submit.disabled = true; form.setAttribute('aria-busy', 'true');
    const data = new FormData(); data.set('_csrf', form.dataset.csrf || ''); data.set('idempotency_token', idempotencyToken); ['title', 'project_type_id', 'description'].forEach(name => data.set(name, form.elements[name].value.trim())); authors.forEach(person => data.append('author_ids[]', person.id)); tutors.forEach(person => data.append('tutoring_user_ids[]', person.id)); data.set('tutoring_primary_id', String(primaryTutorId)); form.querySelectorAll('input[name="keyword_ids[]"]:checked').forEach(input => data.append('keyword_ids[]', input.value)); files.forEach(file => data.append('files[]', file));
    try { const response = await fetch(form.dataset.endpoint, { method: 'POST', body: data, headers: { Accept: 'application/json' }, credentials: 'same-origin' }); const payload = await response.json(); if (!response.ok || !payload.success) { if (response.status === 422 && payload.data?.errors) { const errorKeys = Object.keys(payload.data.errors); errorKeys.forEach(key => { const field = form.querySelector(`[data-direct-error-for="${key}"]`); if (field) field.textContent = Array.isArray(payload.data.errors[key]) ? payload.data.errors[key].join(' ') : payload.data.errors[key]; }); const targetStep = errorKeys.some(key => ['title', 'project_type_id', 'description'].includes(key)) ? 1 : (errorKeys.some(key => ['author_ids', 'tutoring_user_ids', 'tutoring_primary_id', 'keyword_ids'].includes(key)) ? 2 : 3); showStep(targetStep); } throw new Error(payload.message || (response.status === 409 ? 'Esta solicitud ya fue procesada con otros datos.' : 'No fue posible publicar el proyecto.')); }
      closeModal(direct); window.AppToast?.success(payload.message || 'Proyecto incorporado al repositorio correctamente.'); const url = new URL(window.location.href); url.searchParams.set('page', 'repository'); url.searchParams.set('repository_page', '1'); document.querySelector('#tabProjects')?.click(); if (typeof window.loadProjectsAjax === 'function') window.loadProjectsAjax(url.href, 'replace', true); else window.location.reload();
    } catch (error) { message(error.message || 'No fue posible publicar el proyecto.'); } finally { submitting = false; submit.disabled = false; form.removeAttribute('aria-busy'); }
  });
})();
