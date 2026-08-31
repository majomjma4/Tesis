window.RepositoryContentDrafts = window.RepositoryContentDrafts || (() => {
  const prefix = 'tesis:repository-content-draft:v1';
  const storage = () => {
    try { return window.localStorage; } catch (_) { return null; }
  };
  const key = (userId, type) => {
    const id = Number(userId);
    return id > 0 && (type === 'project' || type === 'material') ? `${prefix}:${id}:${type}` : '';
  };
  const read = (userId, type) => {
    const storageArea = storage(); const storageKey = key(userId, type);
    if (!storageArea || !storageKey) return null;
    try {
      const parsed = JSON.parse(storageArea.getItem(storageKey) || 'null');
      return parsed && typeof parsed === 'object' && !Array.isArray(parsed) ? parsed : null;
    } catch (_) { return null; }
  };
  const write = (userId, type, value) => {
    const storageArea = storage(); const storageKey = key(userId, type);
    if (!storageArea || !storageKey) return;
    try { storageArea.setItem(storageKey, JSON.stringify(value)); } catch (_) {}
  };
  const remove = (userId, type) => {
    const storageArea = storage(); const storageKey = key(userId, type);
    if (!storageArea || !storageKey) return;
    try { storageArea.removeItem(storageKey); } catch (_) {}
  };
  return Object.freeze({ read, write, remove });
})();

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
  const draftUserId = Number(form?.dataset.draftUserId || 0);
  const draftNotice = form?.querySelector('[data-direct-draft-notice]');
  const directDraftResume = direct?.querySelector('[data-direct-draft-resume]');
  const directDraftContinue = direct?.querySelector('[data-direct-draft-continue]');
  const directDraftStartNew = direct?.querySelector('[data-direct-draft-start-new]');
  let directDraftRecoveryPending = false;
  let userTouchedTitle = false;
  let userTouchedDescription = false;
  const lock = on => { document.documentElement.classList.toggle('teacher-material-modal-open', on); document.body.classList.toggle('teacher-material-modal-open', on); };
  const openModal = (modal, focusTarget = null) => { activeModal = modal; modal.hidden = false; lock(true); (focusTarget || (modal === direct ? form?.querySelector('[name="title"]') : modal.querySelector('[data-repository-content-project], [data-repository-content-material]')))?.focus(); };
  const closeModal = (modal, restore = true) => { if (!modal) return; if (modal === direct && !submitting && !directDraftRecoveryPending) saveDirectDraft(); modal.hidden = true; if (modal === direct && !submitting) { resetDirectForm(); directDraftRecoveryPending = false; directDraftResume?.setAttribute('hidden', ''); if (form) form.hidden = false; } if (activeModal === modal) activeModal = null; lock(false); if (restore) returnFocus?.focus(); };
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
  const setDraftNotice = fileCount => {
    if (!draftNotice) return;
    const count = Number(fileCount) || 0;
    draftNotice.hidden = count < 1;
    draftNotice.textContent = count === 1
      ? 'El borrador tenía 1 archivo seleccionado. Por seguridad, vuelve a seleccionarlo antes de publicar.'
      : `El borrador tenía ${count} archivos seleccionados. Por seguridad, vuelve a seleccionarlos antes de publicar.`;
  };
  const normalizedDraftPeople = value => (Array.isArray(value) ? value : [])
    .map(person => ({
      id: Number(person?.id || 0),
      name: String(person?.name || '').slice(0, 180),
      identification: String(person?.identification || '').slice(0, 80),
      code: String(person?.code || '').slice(0, 80),
    }))
    .filter(person => person.id > 0 && person.name !== '');
  const directDraftState = () => {
    const keywordIds = [...(keywordOptions?.querySelectorAll('input[type="checkbox"]:checked') || [])].map(input => String(input.value));
    return {
      title: String(form?.elements.title?.value || ''),
      project_type_id: String(form?.elements.project_type_id?.value || ''),
      description: String(form?.elements.description?.value || ''),
      authors: normalizedDraftPeople(authors),
      tutors: normalizedDraftPeople(tutors),
      primary_tutor_id: Number(primaryTutorId) || 0,
      keyword_ids: keywordIds,
      file_count: files.length,
    };
  };
  const hasSubstantialDirectDraft = state => {
    const title = state.title.trim(); const description = state.description.trim();
    const meaningfulTitle = title !== '' && (userTouchedTitle || title !== currentAutoTitle);
    const meaningfulDescription = description !== '' && (userTouchedDescription || description !== currentAutoDescription);
    return meaningfulTitle || meaningfulDescription || state.authors.length > 0 || state.tutors.length > 0 || state.keyword_ids.length > 0 || state.file_count > 0;
  };
  const hasSubstantialDirectDraftRecord = draft => {
    if (!draft || typeof draft !== 'object') return false;
    const option = [...(typeSelect?.options || [])].find(item => String(item.value) === String(draft.project_type_id || ''));
    const defaultTitle = String(option?.getAttribute('data-default-title') || '').trim();
    const defaultDescription = String(option?.getAttribute('data-registration-description') || '').trim();
    const title = String(draft.title || '').trim();
    const description = String(draft.description || '').trim();
    const meaningfulTitle = title !== '' && (Boolean(draft.user_touched_title) || title !== defaultTitle);
    const meaningfulDescription = description !== '' && (Boolean(draft.user_touched_description) || description !== defaultDescription);
    const hasPeople = [draft.authors, draft.tutors].some(value => Array.isArray(value) && value.some(person => Number(person?.id || 0) > 0));
    const hasKeywords = Array.isArray(draft.keyword_ids) && draft.keyword_ids.length > 0;
    return meaningfulTitle || meaningfulDescription || hasPeople || hasKeywords || Number(draft.file_count) > 0;
  };
  const saveDirectDraft = () => {
    const state = directDraftState();
    if (!window.RepositoryContentDrafts || !draftUserId) return;
    if (!hasSubstantialDirectDraft(state)) {
      window.RepositoryContentDrafts.remove(draftUserId, 'project');
      return;
    }
    window.RepositoryContentDrafts.write(draftUserId, 'project', {
      version: 1,
      updated_at: Date.now(),
      current_step: currentStep,
      title: state.title,
      project_type_id: state.project_type_id,
      description: state.description,
      authors: state.authors,
      tutors: state.tutors,
      primary_tutor_id: state.primary_tutor_id,
      keyword_ids: state.keyword_ids,
      file_count: state.file_count,
      user_touched_title: userTouchedTitle,
      user_touched_description: userTouchedDescription,
    });
  };
  const restoreDirectDraft = () => {
    if (!window.RepositoryContentDrafts || !draftUserId || !form) return;
    const draft = window.RepositoryContentDrafts.read(draftUserId, 'project');
    if (!draft) return;
    form.elements.title.value = String(draft.title || '');
    form.elements.project_type_id.value = String(draft.project_type_id || '');
    form.elements.description.value = String(draft.description || '');
    authors = normalizedDraftPeople(draft.authors);
    tutors = normalizedDraftPeople(draft.tutors);
    primaryTutorId = Number(draft.primary_tutor_id || 0);
    if (!tutors.some(person => person.id === primaryTutorId)) primaryTutorId = tutors[0]?.id || 0;
    tutor = tutors.find(person => person.id === primaryTutorId) || null;
    const option = typeSelect?.selectedOptions?.[0];
    const defaultTitle = String(option?.getAttribute('data-default-title') || '').trim();
    const defaultDescription = String(option?.getAttribute('data-registration-description') || '').trim();
    currentAutoTitle = form.elements.title.value.trim() === defaultTitle ? defaultTitle : '';
    currentAutoDescription = form.elements.description.value.trim() === defaultDescription ? defaultDescription : '';
    userTouchedTitle = Boolean(draft.user_touched_title) || (form.elements.title.value.trim() !== '' && form.elements.title.value.trim() !== currentAutoTitle);
    userTouchedDescription = Boolean(draft.user_touched_description) || (form.elements.description.value.trim() !== '' && form.elements.description.value.trim() !== currentAutoDescription);
    const selectedKeywordIds = new Set((Array.isArray(draft.keyword_ids) ? draft.keyword_ids : []).map(value => String(value)));
    keywordOptions?.querySelectorAll('input[type="checkbox"]').forEach(input => { input.checked = selectedKeywordIds.has(String(input.value)); });
    files = [];
    setDraftNotice(Number(draft.file_count) || 0);
    renderAuthors(); renderTutor(); renderFiles(); updateKeywordSelection();
    const requestedStep = Math.max(1, Math.min(3, Number(draft.current_step) || 1));
    const stepOneValid = form.elements.title.value.trim().length >= 5
      && form.elements.title.value.trim().length <= 240
      && form.elements.project_type_id.value !== ''
      && form.elements.description.value.trim().length >= 30;
    const stepTwoValid = authors.length > 0 && tutors.length > 0 && tutors.some(person => person.id === primaryTutorId);
    currentStep = requestedStep >= 3 && stepOneValid && stepTwoValid ? 3 : (requestedStep >= 2 && stepOneValid ? 2 : 1);
  };
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
  const resetDirectForm = () => { form?.reset(); authors = []; tutors = []; primaryTutorId = 0; tutor = null; files = []; userTouchedTitle = false; userTouchedDescription = false; currentAutoTitle = ''; currentAutoDescription = ''; idempotencyToken = token(); currentStep = 1; setDraftNotice(0); renderAuthors(); renderTutor(); renderFiles(); updateKeywordSelection(); filterKeywordOptions(); closeKeywordSelector(); clearError(); };
  const updateSummary = () => { if (!summary) return; const type = form.elements.project_type_id?.selectedOptions?.[0]?.textContent || 'Sin seleccionar'; const period = direct.querySelector('input[readonly]')?.value || 'No disponible'; summary.replaceChildren(); const heading = document.createElement('h4'); heading.textContent = 'Resumen de publicación'; const list = document.createElement('dl'); [['Proyecto', form.elements.title.value.trim() || 'Sin título'], ['Tipo', type], ['Autores', `${authors.length} ${authors.length === 1 ? 'autor' : 'autores'}`], ['Tutores', `${tutors.length} ${tutors.length === 1 ? 'tutor' : 'tutores'}`], ['Tutor principal', tutors.find(person => person.id === primaryTutorId)?.name || 'Sin tutor'], ['Archivos', `${files.length} ${files.length === 1 ? 'archivo' : 'archivos'}`], ['PAO', period]].forEach(([label, value]) => { const row = document.createElement('div'); const term = document.createElement('dt'); term.textContent = label; const detail = document.createElement('dd'); detail.textContent = value; row.append(term, detail); list.append(row); }); summary.append(heading, list); };
  const showStep = step => { currentStep = Math.max(1, Math.min(3, step)); stepPanels.forEach(panel => { const active = Number(panel.dataset.directStepPanel) === currentStep; panel.hidden = !active; panel.classList.toggle('is-active', active); }); stepIndicators.forEach(indicator => { const value = Number(indicator.dataset.directStepIndicator); indicator.toggleAttribute('aria-current', value === currentStep); indicator.classList.toggle('is-complete', value < currentStep); indicator.disabled = value > currentStep + 1; indicator.setAttribute('aria-disabled', String(value > currentStep + 1)); }); if (stepPrevious) stepPrevious.hidden = currentStep === 1; if (stepNext) stepNext.hidden = currentStep === 3; if (stepSubmit) stepSubmit.hidden = currentStep !== 3; updateSummary(); saveDirectDraft(); const heading = form?.querySelector(`[data-direct-step-panel="${currentStep}"] h3`); heading?.focus(); };
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
  const addFiles = incoming => { incoming.forEach(file => { if (!files.some(existing => existing.name === file.name && existing.size === file.size && existing.lastModified === file.lastModified)) files.push(file); }); renderFiles(); saveDirectDraft(); };
  const openDirectFlow = () => {
    resetDirectForm();
    const draft = window.RepositoryContentDrafts?.read(draftUserId, 'project');
    if (hasSubstantialDirectDraftRecord(draft)) {
      directDraftRecoveryPending = true;
      directDraftResume?.removeAttribute('hidden');
      if (form) form.hidden = true;
      openModal(direct, directDraftContinue || directDraftStartNew);
      return;
    }
    if (draft) window.RepositoryContentDrafts?.remove(draftUserId, 'project');
    directDraftRecoveryPending = false;
    directDraftResume?.setAttribute('hidden', '');
    if (form) form.hidden = false;
    openModal(direct);
    showStep(currentStep);
  };
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
  selector.querySelector('[data-repository-content-project]')?.addEventListener('click', () => { closeModal(selector, false); returnFocus = trigger; openDirectFlow(); });
  selector.querySelector('[data-repository-content-material]')?.addEventListener('click', () => { closeModal(selector, false); returnFocus = trigger; if (typeof window.openTeacherMaterialModal === 'function') window.openTeacherMaterialModal(); });
  selector.querySelectorAll('[data-repository-content-close]').forEach(button => button.addEventListener('click', () => closeModal(selector)));
  direct?.querySelectorAll('[data-direct-project-close]').forEach(button => button.addEventListener('click', () => closeModal(direct)));
  directDraftContinue?.addEventListener('click', () => { directDraftRecoveryPending = false; directDraftResume?.setAttribute('hidden', ''); if (form) form.hidden = false; restoreDirectDraft(); showStep(currentStep); });
  directDraftStartNew?.addEventListener('click', () => { window.RepositoryContentDrafts?.remove(draftUserId, 'project'); directDraftRecoveryPending = false; directDraftResume?.setAttribute('hidden', ''); resetDirectForm(); if (form) form.hidden = false; showStep(currentStep); });
  [selector, direct].forEach(modal => modal?.addEventListener('click', event => { if (event.target === modal) closeModal(modal); }));
  document.addEventListener('keydown', event => { if (event.key !== 'Escape' || !activeModal) return; if (activeModal === direct && keywordPanel && !keywordPanel.hidden) { closeKeywordSelector(true); return; } closeModal(activeModal); });
  stepNext?.addEventListener('click', () => goToStep(currentStep + 1));
  stepPrevious?.addEventListener('click', () => goToStep(currentStep - 1));
  form?.addEventListener('input', event => { if (event.target?.name === 'title') userTouchedTitle = true; if (event.target?.name === 'description') userTouchedDescription = true; updateSummary(); saveDirectDraft(); });
  typeSelect?.addEventListener('change', () => { applyTypeDefaults(); saveDirectDraft(); });
  form?.addEventListener('change', updateSummary);
  form?.addEventListener('submit', async event => {
    event.preventDefault(); if (submitting) return; if (currentStep !== 3) { if (validateStep(currentStep)) showStep(currentStep + 1); return; } clearError();
    if (authors.length === 0) { const field = form.querySelector('[data-direct-error-for="author_ids"]'); field.textContent = 'Selecciona al menos un autor.'; form.querySelector('[data-direct-people-search="students"]')?.focus(); return; }
    if (tutors.length === 0 || !tutors.some(person => person.id === primaryTutorId)) { fieldError('tutoring_user_ids', 'Selecciona los tutores y un tutor principal.'); showStep(2); return; }
    submitting = true; const submit = form.querySelector('[type="submit"]'); submit.disabled = true; form.setAttribute('aria-busy', 'true');
    const data = new FormData(); data.set('_csrf', form.dataset.csrf || ''); data.set('idempotency_token', idempotencyToken); ['title', 'project_type_id', 'description'].forEach(name => data.set(name, form.elements[name].value.trim())); authors.forEach(person => data.append('author_ids[]', person.id)); tutors.forEach(person => data.append('tutoring_user_ids[]', person.id)); data.set('tutoring_primary_id', String(primaryTutorId)); form.querySelectorAll('input[name="keyword_ids[]"]:checked').forEach(input => data.append('keyword_ids[]', input.value)); files.forEach(file => data.append('files[]', file));
     try { const response = await fetch(form.dataset.endpoint, { method: 'POST', body: data, headers: { Accept: 'application/json' }, credentials: 'same-origin' }); const payload = await response.json(); if (!response.ok || !payload.success) { if (response.status === 422 && payload.data?.errors) { const errorKeys = Object.keys(payload.data.errors); errorKeys.forEach(key => { const field = form.querySelector(`[data-direct-error-for="${key}"]`); if (field) field.textContent = Array.isArray(payload.data.errors[key]) ? payload.data.errors[key].join(' ') : payload.data.errors[key]; }); const targetStep = errorKeys.some(key => ['title', 'project_type_id', 'description'].includes(key)) ? 1 : (errorKeys.some(key => ['author_ids', 'tutoring_user_ids', 'tutoring_primary_id', 'keyword_ids'].includes(key)) ? 2 : 3); showStep(targetStep); } throw new Error(payload.message || (response.status === 409 ? 'Esta solicitud ya fue procesada con otros datos.' : 'No fue posible publicar el proyecto.')); }
      window.RepositoryContentDrafts?.remove(draftUserId, 'project'); closeModal(direct); resetDirectForm(); window.AppToast?.success(payload.message || 'Proyecto incorporado al repositorio correctamente.'); const url = new URL(window.location.href); url.searchParams.set('page', 'repository'); url.searchParams.set('repository_page', '1'); document.querySelector('#tabProjects')?.click(); if (typeof window.loadProjectsAjax === 'function') window.loadProjectsAjax(url.href, 'replace', true); else window.location.reload();
    } catch (error) { message(error.message || 'No fue posible publicar el proyecto.'); } finally { submitting = false; submit.disabled = false; form.removeAttribute('aria-busy'); }
  });
  window.addEventListener('beforeunload', () => { if (!directDraftRecoveryPending) saveDirectDraft(); });
})();
