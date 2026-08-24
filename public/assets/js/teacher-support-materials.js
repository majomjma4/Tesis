(() => {
  const modal = document.querySelector('[data-teacher-material-modal]');
  const open = document.querySelector('[data-teacher-material-create]');
  const form = document.querySelector('[data-teacher-material-form]');
  if (!modal || !open || !form) return;
  const keywordSelector = form.querySelector('[data-teacher-material-keyword-selector]');
  const keywordTrigger = keywordSelector?.querySelector('[data-teacher-material-keyword-trigger]');
  const keywordPanel = keywordSelector?.querySelector('[data-teacher-material-keyword-panel]');
  const keywordSearch = keywordSelector?.querySelector('[data-teacher-material-keyword-search]');
  const keywordOptionsContainer = keywordSelector?.querySelector('[data-teacher-material-keyword-options]');
  const keywordOptions = [...(keywordSelector?.querySelectorAll('input[name="keywords_selected[]"]') || [])];
  const keywordSummary = keywordSelector?.querySelector('[data-teacher-material-keyword-summary]');
  const keywordChips = keywordSelector?.querySelector('[data-teacher-material-keyword-chips]');
  const keywordLimit = keywordSelector?.querySelector('[data-teacher-material-keyword-limit]');
  const normalizeKeywordSearch = value => String(value || '').normalize('NFD').replace(/\p{Mn}+/gu, '').trim().toLocaleLowerCase('es');
  const closeKeywordSelector = restoreFocus => {
    if (!keywordSelector || !keywordPanel || !keywordTrigger) return;
    keywordPanel.hidden = true;
    keywordSelector.classList.remove('is-open');
    keywordTrigger.setAttribute('aria-expanded', 'false');
    if (restoreFocus) keywordTrigger.focus();
  };
  const renderKeywordSelection = () => {
    const selected = keywordOptions.filter(option => option.checked);
    const atLimit = selected.length >= 4;
    keywordOptions.forEach(option => {
      option.disabled = atLimit && !option.checked;
      option.closest('[role="option"]')?.setAttribute('aria-selected', String(option.checked));
    });
    if (keywordSummary) keywordSummary.textContent = selected.length
      ? `${selected.length} ${selected.length === 1 ? 'etiqueta seleccionada' : 'etiquetas seleccionadas'}`
      : 'Selecciona etiquetas de clasificación';
    if (keywordLimit) keywordLimit.hidden = !atLimit;
    if (!keywordChips) return;
    keywordChips.replaceChildren(...selected.map(option => {
      const chip = document.createElement('button');
      chip.type = 'button';
      chip.className = 'ed-keyword-chip';
      chip.setAttribute('aria-label', `Quitar ${option.value}`);
      const label = document.createElement('span');
      label.textContent = option.value;
      const icon = document.createElement('i');
      icon.className = 'fa-solid fa-xmark';
      icon.setAttribute('aria-hidden', 'true');
      chip.append(label, icon);
      chip.addEventListener('click', () => { option.checked = false; renderKeywordSelection(); keywordTrigger?.focus(); });
      return chip;
    }));
  };
  const openKeywordSelector = () => {
    if (!keywordSelector || !keywordPanel || !keywordTrigger) return;
    keywordPanel.hidden = false;
    keywordSelector.classList.add('is-open');
    keywordTrigger.setAttribute('aria-expanded', 'true');
    keywordSearch?.focus();
  };
  keywordTrigger?.addEventListener('click', () => keywordPanel?.hidden ? openKeywordSelector() : closeKeywordSelector(true));
  keywordSelector?.addEventListener('focusout', event => { if (!keywordSelector.contains(event.relatedTarget)) closeKeywordSelector(false); });
  keywordSearch?.addEventListener('input', () => {
    const query = normalizeKeywordSearch(keywordSearch.value);
    keywordOptions.forEach(option => {
      const row = option.closest('[data-keyword-search]');
      if (row) row.hidden = query !== '' && !normalizeKeywordSearch(row.dataset.keywordSearch).includes(query);
    });
  });
  keywordSearch?.addEventListener('keydown', event => {
    if (event.key === 'Escape') { event.preventDefault(); closeKeywordSelector(true); return; }
    if (!['ArrowDown', 'End'].includes(event.key)) return;
    const visible = keywordOptions.filter(option => !option.disabled && !option.closest('[role="option"]')?.hidden);
    if (!visible.length) return;
    event.preventDefault(); (event.key === 'End' ? visible.at(-1) : visible[0]).focus();
  });
  keywordOptions.forEach(option => {
    option.addEventListener('change', () => {
      if (option.checked && keywordOptions.filter(item => item.checked).length > 4) option.checked = false;
      renderKeywordSelection();
    });
    option.addEventListener('keydown', event => {
      if (event.key === 'Escape') { event.preventDefault(); closeKeywordSelector(true); return; }
      if (!['ArrowDown', 'ArrowUp', 'Home', 'End'].includes(event.key)) return;
      const visible = keywordOptions.filter(item => !item.disabled && !item.closest('[role="option"]')?.hidden);
      const index = visible.indexOf(option);
      if (!visible.length || index < 0) return;
      event.preventDefault();
      const next = event.key === 'Home' ? 0 : event.key === 'End' ? visible.length - 1 : event.key === 'ArrowUp' ? Math.max(0, index - 1) : Math.min(visible.length - 1, index + 1);
      visible[next]?.focus();
    });
  });
  document.addEventListener('click', event => { if (keywordSelector && !keywordSelector.contains(event.target)) closeKeywordSelector(false); });
  document.addEventListener('keydown', event => { if (event.key === 'Escape' && keywordPanel && !keywordPanel.hidden) closeKeywordSelector(true); });
  renderKeywordSelection();
  const setDocumentScrollLocked = locked => {
    document.documentElement.classList.toggle('teacher-material-modal-open', locked);
    document.body.classList.toggle('teacher-material-modal-open', locked);
  };
  const showToast = message => {
    if (typeof window.showRepositoryToast === 'function') window.showRepositoryToast(message);
  };
  const close = () => { modal.hidden = true; setDocumentScrollLocked(false); form.reset(); closeKeywordSelector(false); keywordOptions.forEach(option => { option.disabled = false; option.closest('[role="option"]')?.removeAttribute('aria-selected'); }); keywordOptions.forEach(option => { option.closest('[role="option"]')?.removeAttribute('hidden'); }); renderKeywordSelection(); selectedFiles = []; form.querySelector('[data-file-list]').replaceChildren(); form.querySelector('[data-teacher-material-error]').hidden = true; };
  const openModal = () => { modal.hidden = false; setDocumentScrollLocked(true); modal.querySelector('input[name="title"]')?.focus(); };
  window.openTeacherMaterialModal = openModal;
  open.addEventListener('click', event => { if (open.hasAttribute('data-teacher-content-trigger')) return; openModal(); });
  modal.querySelectorAll('[data-teacher-material-close]').forEach(button => button.addEventListener('click', close));
  modal.addEventListener('click', event => { if (event.target === modal) close(); });
  const fileInput = form.querySelector('[data-files]'); const dropzone = form.querySelector('[data-teacher-dropzone]'); let selectedFiles = [];
  const renderFiles = () => form.querySelector('[data-file-list]').replaceChildren(...selectedFiles.map((file, index) => { const item = document.createElement('li'); const remove = document.createElement('button'); remove.type = 'button'; remove.textContent = 'Quitar'; remove.addEventListener('click', () => { selectedFiles.splice(index, 1); renderFiles(); }); item.append(file.name, remove); return item; }));
  const addFiles = files => { selectedFiles = [...selectedFiles, ...files]; renderFiles(); };
  fileInput?.addEventListener('change', event => addFiles([...event.target.files]));
  ['dragenter','dragover'].forEach(type => dropzone?.addEventListener(type, event => { event.preventDefault(); dropzone.classList.add('is-dragover'); }));
  ['dragleave','drop'].forEach(type => dropzone?.addEventListener(type, event => { event.preventDefault(); dropzone.classList.remove('is-dragover'); }));
  dropzone?.addEventListener('drop', event => addFiles([...event.dataTransfer.files]));
  form.addEventListener('submit', async event => {
    event.preventDefault(); const submit = form.querySelector('[type="submit"]'); const error = form.querySelector('[data-teacher-material-error]');
    submit.disabled = true; error.hidden = true;
    const title = form.querySelector('[data-title]')?.value.trim() || ''; if (title.split(/\s+/).filter(Boolean).length < 3) { error.textContent = 'El título debe contener al menos tres palabras.'; error.hidden = false; showToast(error.textContent); submit.disabled = false; return; }
    const data = new FormData(form); data.set('_csrf', form.dataset.csrf || ''); data.set('full_description', data.get('description') || ''); data.delete('files');
    selectedFiles.forEach(file => data.append('initial_files[]', file));
    try { const response = await fetch(form.dataset.endpoint, {method:'POST', body:data, headers:{Accept:'application/json'}}); const payload = await response.json();
      if (!response.ok || !payload.success) throw new Error(payload.message || 'No fue posible crear el material.');
      close(); showToast(payload.message || 'Material creado correctamente.');
      window.setTimeout(() => window.location.reload(), 2400);
    } catch (exception) { error.textContent = exception.message; error.hidden = false; showToast(error.textContent); submit.disabled = false; }
  });
})();
