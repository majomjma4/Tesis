(() => {
  const root = document.querySelector('#thesisManagementPage');
  if (!root) return;
  const storageKey = 'thesis-management-context';
  const normalize = value => String(value || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLocaleLowerCase('es');
  const tabs = [...root.querySelectorAll('[data-tm-tab]')].map(button => button.dataset.tmTab);
  const search = root.querySelector('[data-tm-search]');
  const period = root.querySelector('[data-tm-period]');
  const clear = root.querySelector('[data-tm-clear-search]');
  let activeTab = 'approved';
  const state = Object.fromEntries(tabs.map(tab => [tab, { page: 1, size: 10 }]));
  const saved = (() => { try { return JSON.parse(sessionStorage.getItem(storageKey) || '{}'); } catch { return {}; } })();
  if (tabs.includes(saved.tab)) activeTab = saved.tab;
  tabs.forEach(tab => { if (saved[tab]) state[tab] = { ...state[tab], ...saved[tab] }; });
  if (search && typeof saved.search === 'string') search.value = saved.search;
  if (period && typeof saved.period === 'string') period.value = saved.period;

  const persist = (message = '') => sessionStorage.setItem(storageKey, JSON.stringify({ tab: activeTab, approved: state.approved, defense: state.defense, search: search?.value || '', period: period?.value || '', scrollY: window.scrollY, message }));
  const highlight = (node, needle) => {
    const original = node.dataset.tmOriginal ?? node.textContent;
    node.dataset.tmOriginal = original;
    const start = normalize(original).indexOf(normalize(needle));
    if (!needle || start < 0) { node.textContent = original; return; }
    const mark = document.createElement('mark');
    mark.textContent = original.slice(start, start + needle.length);
    node.replaceChildren(original.slice(0, start), mark, original.slice(start + needle.length));
  };
  const pageTokens = (page, total) => total <= 5 ? Array.from({ length: total }, (_, index) => index + 1) : page <= 3 ? [1, 2, 3, '…', total] : page >= total - 2 ? [1, '…', total - 2, total - 1, total] : [1, '…', page - 1, page, page + 1, '…', total];
  const cards = tab => [...root.querySelectorAll(`[data-tm-list="${tab}"] [data-tm-item]`)];
  const render = tab => {
    const needle = search?.value || '';
    const filtered = cards(tab).filter(card => (!needle || normalize(card.dataset.search).includes(normalize(needle))) && (!period?.value || card.dataset.period === period.value));
    const current = state[tab];
    const totalPages = Math.max(1, Math.ceil(filtered.length / current.size));
    current.page = Math.min(current.page, totalPages);
    const start = (current.page - 1) * current.size;
    const visible = new Set(filtered.slice(start, start + current.size));
    cards(tab).forEach(card => { card.hidden = !visible.has(card); card.querySelectorAll('[data-tm-highlight]').forEach(node => highlight(node, visible.has(card) ? needle : '')); });
    const count = root.querySelector(`[data-tm-count="${tab}"]`);
    const empty = root.querySelector(`[data-tm-empty="${tab}"]`);
    const summary = root.querySelector(`[data-tm-summary="${tab}"]`);
    const pagination = root.querySelector(`[data-tm-pagination="${tab}"]`);
    const nav = root.querySelector(`[data-tm-pages="${tab}"]`);
    if (count) count.textContent = String(filtered.length);
    if (empty) { empty.hidden = filtered.length !== 0; empty.querySelector('h2').textContent = cards(tab).length && filtered.length === 0 ? 'No se encontraron proyectos con los filtros seleccionados.' : empty.dataset.emptyDefault || ''; }
    if (summary) summary.textContent = filtered.length ? `Mostrando ${start + 1}-${Math.min(start + current.size, filtered.length)} de ${filtered.length}` : 'Mostrando 0 de 0';
    if (pagination) pagination.hidden = filtered.length <= current.size;
    if (nav) {
      nav.replaceChildren();
      if (filtered.length > current.size) {
        const add = (label, target, disabled = false, selected = false) => { const button = document.createElement('button'); button.type = 'button'; button.textContent = label; button.disabled = disabled; button.classList.toggle('active', selected); button.addEventListener('click', () => { current.page = target; persist(); render(tab); }); nav.append(button); };
        add('Anterior', Math.max(1, current.page - 1), current.page === 1);
        pageTokens(current.page, totalPages).forEach(token => typeof token === 'number' ? add(String(token), token, false, token === current.page) : nav.append(Object.assign(document.createElement('span'), { textContent: token })));
        add('Siguiente', Math.min(totalPages, current.page + 1), current.page === totalPages);
      }
    }
  };
  const activate = tab => {
    activeTab = tab;
    tabs.forEach(key => { const active = key === tab; root.querySelector(`[data-tm-tab="${key}"]`)?.classList.toggle('active', active); root.querySelector(`[data-tm-tab="${key}"]`)?.setAttribute('aria-selected', String(active)); root.querySelector(`[data-tm-panel="${key}"]`).hidden = !active; });
    persist(); render(tab);
  };
  root.querySelectorAll('[data-tm-tab]').forEach(button => button.addEventListener('click', () => activate(button.dataset.tmTab)));
  root.querySelectorAll('[data-tm-summary-tab]').forEach(button => button.addEventListener('click', () => activate(button.dataset.tmSummaryTab)));
  [search, period].filter(Boolean).forEach(control => control.addEventListener(control === search ? 'input' : 'change', () => { state[activeTab].page = 1; persist(); render(activeTab); }));
  clear?.addEventListener('click', () => { search.value = ''; state[activeTab].page = 1; search.focus(); persist(); render(activeTab); });
  tabs.forEach(tab => root.querySelector(`[data-tm-size="${tab}"]`)?.addEventListener('change', event => { state[tab].size = Number(event.currentTarget.value) || 10; state[tab].page = 1; persist(); render(tab); }));
  tabs.forEach(tab => { const select = root.querySelector(`[data-tm-size="${tab}"]`); if (select) select.value = String(state[tab].size); render(tab); });
  activate(activeTab);
  if (Number.isFinite(Number(saved.scrollY))) requestAnimationFrame(() => window.scrollTo(0, Number(saved.scrollY)));

  let modalScrollY = 0;
  const beforeModal = () => { modalScrollY = window.scrollY; persist(); };
  const reload = message => { persist(message); window.location.reload(); };
  if (saved.message) { const feedback = document.createElement('div'); feedback.className = 'tm-feedback'; feedback.textContent = saved.message; root.prepend(feedback); saved.message = ''; persist(); }

  const tribunalConfig = document.querySelector('#thesisTribunalConfig');
  const tribunalModal = document.querySelector('[data-tribunal-modal]');
  if (tribunalConfig && tribunalModal) {
    const dialog = tribunalModal.querySelector('[data-tribunal-dialog]'), list = tribunalModal.querySelector('[data-tribunal-candidates]'), selectedList = tribunalModal.querySelector('[data-tribunal-selected]'), modalSearch = tribunalModal.querySelector('[data-tribunal-search]'), count = tribunalModal.querySelector('[data-tribunal-count]'), help = tribunalModal.querySelector('[data-tribunal-help]'), error = tribunalModal.querySelector('[data-tribunal-error]'), save = tribunalModal.querySelector('[data-tribunal-save]'), reason = tribunalModal.querySelector('[data-tribunal-reason]'), reasonWrap = tribunalModal.querySelector('[data-tribunal-reason-wrap]'), defenseWarning = tribunalModal.querySelector('[data-tribunal-defense]'), approvedNote = tribunalModal.querySelector('[data-tribunal-approved-note]'), context = tribunalModal.querySelector('[data-tribunal-context]');
    let trigger, active, candidates = [], selected = new Set();
    const close = () => { tribunalModal.hidden = true; document.body.classList.remove('tm-modal-open'); trigger?.focus(); window.scrollTo(0, modalScrollY); };
    const setError = text => { error.hidden = !text; error.textContent = text || ''; };
    const draw = () => { const term = normalize(modalSearch.value), full = selected.size >= 5; list.replaceChildren(); candidates.filter(candidate => !term || normalize(`${candidate.full_name} ${candidate.email} ${candidate.institutional_code}`).includes(term)).forEach(candidate => { const label = document.createElement('label'); label.className = 'tm-candidate'; const check = document.createElement('input'); check.type = 'checkbox'; check.checked = selected.has(+candidate.id); check.disabled = !check.checked && full; check.addEventListener('change', () => { check.checked ? selected.add(+candidate.id) : selected.delete(+candidate.id); draw(); }); const text = document.createElement('span'); text.innerHTML = '<strong></strong><small></small>'; text.querySelector('strong').textContent = candidate.full_name; text.querySelector('small').textContent = [candidate.academic_title, candidate.email].filter(Boolean).join(' · '); label.append(check, text); list.append(label); }); selectedList.replaceChildren(...[...selected].map(id => Object.assign(document.createElement('li'), { textContent: candidates.find(candidate => +candidate.id === id)?.full_name || `Docente #${id}` }))); count.textContent = `${selected.size} de 5 miembros seleccionados`; help.textContent = selected.size < 3 ? 'Selecciona al menos 3 docentes para conformar el Tribunal.' : selected.size === 5 ? 'Se alcanzó el máximo de 5 miembros.' : 'Composición válida: puedes confirmar el Tribunal.'; save.disabled = selected.size < 3 || selected.size > 5 || (active.status === 'defense' && reason.value.trim().length < 5); };
    const open = async button => { beforeModal(); trigger = button; active = { id: +button.dataset.projectId, status: button.dataset.projectStatus, code: button.dataset.projectCode, title: button.dataset.projectTitle }; selected = new Set(); setError(''); context.textContent = `${active.code} · ${active.title}`; defenseWarning.hidden = active.status !== 'defense'; approvedNote.hidden = active.status !== 'approved'; reasonWrap.hidden = active.status !== 'defense'; reason.value = ''; tribunalModal.hidden = false; document.body.classList.add('tm-modal-open'); dialog.focus(); list.textContent = 'Cargando docentes disponibles…'; try { const response = await fetch(`${tribunalConfig.dataset.candidates}&project_id=${active.id}`, { headers: { Accept: 'application/json' } }); const json = await response.json(); if (!response.ok || !json.success) throw new Error(json.message); candidates = json.data.items || []; const existing = JSON.parse(button.dataset.currentMembers || '[]').map(Number); selected = new Set(candidates.filter(candidate => existing.includes(+candidate.id)).map(candidate => +candidate.id)); draw(); } catch (exception) { setError(exception.message || 'No fue posible cargar docentes candidatos.'); list.replaceChildren(); } };
    root.querySelectorAll('[data-tribunal-manage]').forEach(button => button.addEventListener('click', () => open(button)));
    tribunalModal.querySelectorAll('[data-tribunal-close],[data-tribunal-cancel]').forEach(button => button.addEventListener('click', close)); tribunalModal.addEventListener('click', event => { if (event.target === tribunalModal) close(); }); document.addEventListener('keydown', event => { if (event.key === 'Escape' && !tribunalModal.hidden) close(); }); modalSearch.addEventListener('input', draw); reason.addEventListener('input', draw);
    save.addEventListener('click', async () => { if (save.disabled || !active) return; save.disabled = true; setError(''); const body = new FormData(); body.append('_csrf', tribunalConfig.dataset.csrf); body.append('project_id', active.id); body.append('expected_status', active.status); body.append('reason', reason.value); [...selected].forEach(id => body.append('member_ids[]', id)); try { const response = await fetch(tribunalConfig.dataset.save, { method: 'POST', body, headers: { Accept: 'application/json' } }); const json = await response.json(); if (!response.ok || !json.success) throw new Error(json.message); reload(json.message || 'Tribunal actualizado correctamente.'); } catch (exception) { setError(exception.message || 'No fue posible actualizar el Tribunal.'); draw(); } });
  }

  const defenseConfig = document.querySelector('#thesisDefenseConfig');
  const infoModal = document.querySelector('[data-defense-info-modal]');
  if (defenseConfig && infoModal) {
    const dialog = infoModal.querySelector('[data-defense-info-dialog]'), form = infoModal.querySelector('[data-defense-info-form]'), context = infoModal.querySelector('[data-defense-info-context]'), error = infoModal.querySelector('[data-defense-info-error]'), save = infoModal.querySelector('[data-defense-info-save]'); let trigger;
    const close = () => { infoModal.hidden = true; document.body.classList.remove('tm-modal-open'); trigger?.focus(); window.scrollTo(0, modalScrollY); };
    root.querySelectorAll('[data-defense-info]').forEach(button => button.addEventListener('click', () => { beforeModal(); trigger = button; const data = JSON.parse(button.dataset.info || '{}'); context.textContent = `${button.dataset.projectCode} · ${button.dataset.projectTitle}`; form.defense_date.value = data.date || ''; form.defense_time.value = (data.time || '').slice(0, 5); form.location.value = data.location || ''; form.modality.value = data.modality || ''; error.hidden = true; infoModal.hidden = false; document.body.classList.add('tm-modal-open'); dialog.focus(); }));
    infoModal.querySelectorAll('[data-defense-info-close],[data-defense-info-cancel]').forEach(button => button.addEventListener('click', close)); infoModal.addEventListener('click', event => { if (event.target === infoModal) close(); }); document.addEventListener('keydown', event => { if (event.key === 'Escape' && !infoModal.hidden) close(); });
    form.addEventListener('submit', async event => { event.preventDefault(); if (!trigger || save.disabled) return; save.disabled = true; error.hidden = true; const body = new FormData(form); body.set('_csrf', defenseConfig.dataset.csrf || ''); body.set('project_id', trigger.dataset.projectId); try { const response = await fetch(defenseConfig.dataset.info, { method: 'POST', body, headers: { Accept: 'application/json' } }); const json = await response.json(); if (!response.ok || !json.success) throw new Error(json.message); reload(json.message || 'Información de defensa guardada.'); } catch (exception) { error.textContent = exception.message; error.hidden = false; save.disabled = false; } });
  }

  const resultModal = document.querySelector('[data-result-modal]');
  if (defenseConfig && resultModal) {
    const dialog = resultModal.querySelector('[data-result-dialog]'), form = resultModal.querySelector('[data-result-form]'), context = resultModal.querySelector('[data-result-context]'), members = resultModal.querySelector('[data-result-members]'), effect = resultModal.querySelector('[data-result-effect]'), error = resultModal.querySelector('[data-result-error]'), save = resultModal.querySelector('[data-result-save]'); let trigger;
    const close = () => { resultModal.hidden = true; document.body.classList.remove('tm-modal-open'); trigger?.focus(); window.scrollTo(0, modalScrollY); };
    root.querySelectorAll('[data-tribunal-result]').forEach(button => button.addEventListener('click', () => { beforeModal(); trigger = button; context.textContent = `${button.dataset.projectCode} · ${button.dataset.projectTitle}`; members.textContent = `Tribunal: ${button.dataset.tribunalCount} miembros`; form.reset(); effect.textContent = ''; error.hidden = true; resultModal.hidden = false; document.body.classList.add('tm-modal-open'); dialog.focus(); }));
    resultModal.querySelectorAll('[data-result-close],[data-result-cancel]').forEach(button => button.addEventListener('click', close)); resultModal.addEventListener('click', event => { if (event.target === resultModal) close(); }); document.addEventListener('keydown', event => { if (event.key === 'Escape' && !resultModal.hidden) close(); }); form.result.forEach(input => input.addEventListener('change', () => { const approved = form.result.value === 'approved'; effect.textContent = approved ? 'Al confirmar, el proyecto avanzará al estado Aprobado por el Tribunal.' : 'El proyecto permanecerá en etapa de defensa. Este resultado quedará registrado en el historial.'; save.textContent = approved ? 'Registrar aprobación' : 'Registrar resultado'; }));
    form.addEventListener('submit', async event => { event.preventDefault(); if (!trigger || save.disabled) return; save.disabled = true; error.hidden = true; const body = new FormData(form); body.set('_csrf', defenseConfig.dataset.csrf || ''); body.set('project_id', trigger.dataset.projectId); body.set('expected_status', 'defense'); try { const response = await fetch(defenseConfig.dataset.result, { method: 'POST', body, headers: { Accept: 'application/json' } }); const json = await response.json(); if (!response.ok || !json.success) throw new Error(json.message); reload(json.message || 'Resultado registrado correctamente.'); } catch (exception) { error.textContent = exception.message; error.hidden = false; save.disabled = false; } });
  }
})();

(() => {
  const config = document.querySelector('#thesisDefenseConfig');
  const modal = document.querySelector('[data-new-attempt-modal]');
  if (!config || !modal) return;
  const dialog = modal.querySelector('[data-new-attempt-dialog]'), context = modal.querySelector('[data-new-attempt-context]'), current = modal.querySelector('[data-new-attempt-current]'), next = modal.querySelector('[data-new-attempt-next]'), tribunal = modal.querySelector('[data-new-attempt-tribunal]'), error = modal.querySelector('[data-new-attempt-error]'), save = modal.querySelector('[data-new-attempt-save]');
  let trigger = null, scrollY = 0;
  const close = () => { modal.hidden = true; document.body.classList.remove('tm-modal-open'); trigger?.focus(); window.scrollTo(0, scrollY); };
  document.querySelectorAll('[data-defense-new-attempt]').forEach(button => button.addEventListener('click', () => { trigger = button; scrollY = window.scrollY; context.textContent = `${button.dataset.projectCode} · ${button.dataset.projectTitle}`; current.textContent = `Defensa ${button.dataset.attempt}`; next.textContent = `Defensa ${Number(button.dataset.attempt) + 1}`; tribunal.textContent = `${button.dataset.tribunalCount} miembros`; error.hidden = true; modal.hidden = false; document.body.classList.add('tm-modal-open'); dialog.focus(); }));
  modal.querySelectorAll('[data-new-attempt-close],[data-new-attempt-cancel]').forEach(button => button.addEventListener('click', close)); modal.addEventListener('click', event => { if (event.target === modal) close(); }); document.addEventListener('keydown', event => { if (event.key === 'Escape' && !modal.hidden) close(); });
  save.addEventListener('click', async () => { if (!trigger || save.disabled) return; save.disabled = true; error.hidden = true; const body = new FormData(); body.set('_csrf', config.dataset.csrf || ''); body.set('project_id', trigger.dataset.projectId); body.set('expected_status', 'defense'); try { const response = await fetch(config.dataset.newAttempt, { method: 'POST', body, headers: { Accept: 'application/json' } }); const json = await response.json(); if (!response.ok || !json.success) throw new Error(json.message || 'No fue posible iniciar una nueva defensa.'); const saved = JSON.parse(sessionStorage.getItem('thesis-management-context') || '{}'); saved.tab = 'defense'; saved.message = 'Nueva defensa iniciada correctamente.'; saved.scrollY = scrollY; sessionStorage.setItem('thesis-management-context', JSON.stringify(saved)); window.location.reload(); } catch (exception) { error.textContent = exception.message; error.hidden = false; save.disabled = false; } });
})();

(() => {
  const modal = document.querySelector('[data-defense-info-modal]');
  if (!modal) return;
  const form = modal.querySelector('[data-defense-info-form]'), save = modal.querySelector('[data-defense-info-save]'), context = modal.querySelector('[data-defense-info-context]');
  const resetReadonly = () => { [...form.elements].forEach(element => element.disabled = false); save.hidden = false; };
  modal.querySelectorAll('[data-defense-info-close],[data-defense-info-cancel]').forEach(button => button.addEventListener('click', resetReadonly));
  modal.addEventListener('click', event => { if (event.target === modal) resetReadonly(); });
  document.addEventListener('keydown', event => { if (event.key === 'Escape' && !modal.hidden) resetReadonly(); });
  document.querySelectorAll('[data-defense-info-readonly]').forEach(button => button.addEventListener('click', () => { const data = JSON.parse(button.dataset.info || '{}'); context.textContent = `${button.dataset.projectCode} · ${button.dataset.projectTitle}`; form.defense_date.value = data.date || ''; form.defense_time.value = (data.time || '').slice(0,5); form.location.value = data.location || ''; form.modality.value = data.modality || ''; [...form.elements].forEach(element => { if (element !== save) element.disabled = true; }); save.hidden = true; modal.hidden = false; document.body.classList.add('tm-modal-open'); }));
})();
