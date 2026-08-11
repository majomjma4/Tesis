(() => {
  const modal = document.querySelector('[data-teacher-material-modal]');
  const open = document.querySelector('[data-teacher-material-create]');
  const form = document.querySelector('[data-teacher-material-form]');
  if (!modal || !open || !form) return;
  const setDocumentScrollLocked = locked => {
    document.documentElement.classList.toggle('teacher-material-modal-open', locked);
    document.body.classList.toggle('teacher-material-modal-open', locked);
  };
  const showToast = message => {
    if (typeof window.showRepositoryToast === 'function') window.showRepositoryToast(message);
  };
  const close = () => { modal.hidden = true; setDocumentScrollLocked(false); form.reset(); selectedFiles = []; form.querySelector('[data-file-list]').replaceChildren(); form.querySelector('[data-teacher-material-error]').hidden = true; };
  open.addEventListener('click', () => { modal.hidden = false; setDocumentScrollLocked(true); modal.querySelector('input[name="title"]')?.focus(); });
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
