const calendarRoot = document.querySelector('.calendar-workspace');

if (calendarRoot) {
    const $ = (selector) => document.querySelector(selector);
    const $$ = (selector) => [...document.querySelectorAll(selector)];
    const endpoint = calendarRoot.dataset.eventsUrl;
    const projectUrl = calendarRoot.dataset.projectUrl || '';
    const typeLabels = { delivery: 'Entrega', meeting: 'Reunión', review: 'Revisión', deadline: 'Fecha límite' };
    const priorityLabels = { low: 'Baja', medium: 'Media', high: 'Alta' };
    const today = new Date(); today.setHours(0, 0, 0, 0);
    const compactAgenda = window.matchMedia('(max-width: 1180px)');
    const viewStorageKey = 'tesis-calendar-view';
    let events = JSON.parse(calendarRoot.dataset.calendarEvents || '[]');
    let visibleDate = new Date(today.getFullYear(), today.getMonth(), 1);
    let selectedDate = dateKey(today);
    let activeFilter = 'all';
    let activePriority = 'all';
    let sortMode = 'date';
    let searchTerm = '';
    let activeView = (() => { try { const saved = localStorage.getItem(viewStorageKey); return ['month', 'week', 'list'].includes(saved) ? saved : 'month'; } catch (error) { return 'month'; } })();
    let quickScope = null;
    let pendingDelete = null;
    let activeDetailEvent = null;

    function dateKey(date) { return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`; }
    function fromKey(key) { const [year, month, day] = key.split('-').map(Number); return new Date(year, month - 1, day); }
    function startOfWeek(date) { const result = new Date(date); result.setDate(result.getDate() - ((result.getDay() + 6) % 7)); result.setHours(0, 0, 0, 0); return result; }
    function addDays(date, amount) { const result = new Date(date); result.setDate(result.getDate() + amount); return result; }
    function isOverdue(event) { return !event.completed && fromKey(event.date) < today; }
    function sortEvents(items) { const priorities = { high: 0, medium: 1, low: 2 }; return [...items].sort((a, b) => { if (sortMode === 'priority') return (priorities[a.priority] ?? 1) - (priorities[b.priority] ?? 1) || `${a.date} ${a.title}`.localeCompare(`${b.date} ${b.title}`, 'es'); if (sortMode === 'completed') return Number(a.completed) - Number(b.completed) || `${a.date} ${a.title}`.localeCompare(`${b.date} ${b.title}`, 'es'); return `${a.date} ${a.title}`.localeCompare(`${b.date} ${b.title}`, 'es'); }); }
    function eventDestination(event) { if (event.type === 'review') return { url: `${projectUrl}#project-history`, label: 'Ir al historial' }; if (event.type === 'delivery' || event.type === 'deadline') return { url: `${projectUrl}#project-delivery`, label: 'Ir a entregas' }; return { url: `${projectUrl}#project-overview`, label: 'Ir al proyecto' }; }
    function filteredEvents() {
        return sortEvents(events.filter((event) => {
            const matchesType = activeFilter === 'all' || (activeFilter === 'pending' ? !event.completed : event.type === activeFilter);
            const matchesPriority = activePriority === 'all' || event.priority === activePriority;
            const text = `${event.title} ${event.description || ''}`.toLocaleLowerCase('es');
            const prefix = `${visibleDate.getFullYear()}-${String(visibleDate.getMonth() + 1).padStart(2, '0')}`;
            const weekEnd = addDays(today, 7);
            const matchesScope = quickScope === 'month' ? event.date.startsWith(prefix) : quickScope === 'week' ? (!event.completed && fromKey(event.date) >= today && fromKey(event.date) <= weekEnd) : quickScope === 'completed' ? (event.completed && event.date.startsWith(prefix)) : true;
            return matchesType && matchesPriority && matchesScope && (!searchTerm || text.includes(searchTerm));
        }));
    }
    async function request(method, payload) {
        const response = await fetch(endpoint, { method, headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, body: payload ? JSON.stringify(payload) : undefined });
        const result = await response.json().catch(() => ({ success: false, message: 'El servidor devolvió una respuesta inválida.' }));
        if (!response.ok || !result.success) throw new Error(result.message || 'No fue posible completar la operación.');
        return result;
    }
    function toast(message, error = false, action = null) {
        const element = $('#calendarToast'); element.innerHTML = ''; const text = document.createElement('span'); text.textContent = message; element.append(text); element.classList.toggle('error', error);
        if (action) { const button = document.createElement('button'); button.type = 'button'; button.textContent = action.label; button.addEventListener('click', async () => { button.disabled = true; await action.handler(); element.hidden = true; }); element.append(button); }
        element.hidden = false; clearTimeout(toast.timer); toast.timer = setTimeout(() => { element.hidden = true; }, action ? 6000 : 3200);
    }
    function createChip(event) {
        const chip = document.createElement('span');
        chip.className = `calendar-event-chip ${event.type}${event.completed ? ' completed' : ''}${isOverdue(event) ? ' overdue' : ''}`;
        chip.textContent = event.title;
        chip.draggable = true; chip.dataset.eventId = event.id; chip.title = 'Arrastra para cambiar la fecha';
        chip.addEventListener('click', (clickEvent) => { clickEvent.stopPropagation(); openDetails(event); });
        chip.addEventListener('dragstart', (dragEvent) => { dragEvent.dataTransfer.setData('text/calendar-event', event.id); dragEvent.dataTransfer.effectAllowed = 'move'; chip.classList.add('is-dragging'); });
        chip.addEventListener('dragend', () => chip.classList.remove('is-dragging'));
        return chip;
    }
    function makeDropTarget(element, key) {
        element.dataset.date = key;
        element.addEventListener('dragover', (event) => { if (event.dataTransfer.types.includes('text/calendar-event')) { event.preventDefault(); element.classList.add('drag-over'); } });
        element.addEventListener('dragleave', () => element.classList.remove('drag-over'));
        element.addEventListener('drop', async (event) => { event.preventDefault(); element.classList.remove('drag-over'); const id = event.dataTransfer.getData('text/calendar-event'); await moveEvent(id, key); });
    }
    function renderMonth() {
        const year = visibleDate.getFullYear(), month = visibleDate.getMonth();
        const first = new Date(year, month, 1), start = new Date(year, month, 1 - ((first.getDay() + 6) % 7));
        const grid = $('#calendarDaysGrid'); grid.innerHTML = '';
        for (let index = 0; index < 42; index++) {
            const date = addDays(start, index), key = dateKey(date), items = filteredEvents().filter((event) => event.date === key);
            const cell = document.createElement('button'); cell.type = 'button'; cell.className = 'calendar-cell';
            if (date.getMonth() !== month) cell.classList.add('outside'); if (key === dateKey(today)) cell.classList.add('today'); if (key === selectedDate) cell.classList.add('selected');
            cell.setAttribute('aria-label', `${date.toLocaleDateString('es-EC', { weekday: 'long', day: 'numeric', month: 'long' })}, ${items.length} eventos`);
            cell.innerHTML = `<span class="calendar-number">${date.getDate()}</span><span class="calendar-cell-events"></span>`;
            items.slice(0, 3).forEach((item) => cell.lastElementChild.append(createChip(item)));
            if (items.length > 3) { const more = document.createElement('small'); more.textContent = `+${items.length - 3} más`; cell.lastElementChild.append(more); }
            cell.addEventListener('click', (event) => { if (event.target.closest('.calendar-event-chip')) return; selectedDate = key; if (date.getMonth() !== month) visibleDate = new Date(date.getFullYear(), date.getMonth(), 1); renderAll(); if (compactAgenda.matches) openAgenda(); });
            cell.addEventListener('dblclick', (event) => { if (items.length || event.target.closest('.calendar-event-chip')) return; selectedDate = key; openModal(); });
            makeDropTarget(cell, key); grid.append(cell);
        }
    }
    function renderWeek() {
        const week = startOfWeek(fromKey(selectedDate)); const container = $('#calendarWeekView'); container.innerHTML = '';
        for (let index = 0; index < 7; index++) {
            const date = addDays(week, index), key = dateKey(date), items = filteredEvents().filter((event) => event.date === key);
            const column = document.createElement('section'); column.className = `calendar-week-column${key === dateKey(today) ? ' today' : ''}`;
            column.innerHTML = `<button type="button" class="calendar-week-heading"><span>${date.toLocaleDateString('es-EC', { weekday: 'short' })}</span><strong>${date.getDate()}</strong></button><div class="calendar-week-events"></div>`;
            column.querySelector('button').addEventListener('click', () => { selectedDate = key; renderAgenda(); if (compactAgenda.matches) openAgenda(); });
            const body = column.lastElementChild;
            if (!items.length) body.innerHTML = '<span class="week-empty">Sin eventos</span>';
            items.forEach((item) => { const card = document.createElement('button'); card.type = 'button'; card.className = `calendar-week-card ${item.type}${item.completed ? ' completed' : ''}${isOverdue(item) ? ' overdue' : ''}`; card.draggable = true; card.dataset.eventId = item.id; card.innerHTML = `<strong></strong><small>${isOverdue(item) ? 'Vencido · ' : ''}${typeLabels[item.type]}</small>`; card.querySelector('strong').textContent = item.title; card.addEventListener('click', () => openDetails(item)); card.addEventListener('dragstart', (event) => event.dataTransfer.setData('text/calendar-event', item.id)); body.append(card); });
            column.addEventListener('dblclick', (event) => { if (items.length || event.target.closest('button')) return; selectedDate = key; openModal(); });
            makeDropTarget(column, key); container.append(column);
        }
    }
    function renderList() {
        const container = $('#calendarListView'); container.innerHTML = ''; const prefix = `${visibleDate.getFullYear()}-${String(visibleDate.getMonth() + 1).padStart(2, '0')}`; const items = quickScope ? filteredEvents() : filteredEvents().filter((event) => event.date.startsWith(prefix));
        if (!items.length) { const scoped = Boolean(quickScope), filtered = Boolean(searchTerm || activeFilter !== 'all' || activePriority !== 'all'); container.append(emptyState(scoped ? 'fa-circle-check' : 'fa-magnifying-glass', scoped ? 'No hay eventos en este resumen' : 'No encontramos eventos', quickScope === 'completed' ? 'Aún no has completado eventos durante este mes.' : quickScope === 'week' ? 'No tienes eventos pendientes durante los próximos siete días.' : filtered ? 'Prueba limpiando la búsqueda o los filtros activos.' : 'Crea un evento para comenzar a organizar tu agenda.', scoped || filtered)); return; }
        let lastDate = '';
        items.forEach((event) => {
            if (event.date !== lastDate) { const heading = document.createElement('h3'); heading.className = 'calendar-list-date'; heading.textContent = fromKey(event.date).toLocaleDateString('es-EC', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }); container.append(heading); lastDate = event.date; }
            container.append(eventCard(event, 'list'));
        });
    }
    function eventCard(event, variant = 'agenda') {
        const article = document.createElement('article'); article.className = `calendar-agenda-item ${variant}${event.completed ? ' is-completed' : ''}`;
        article.innerHTML = `<span class="agenda-accent ${event.type}"></span><div class="agenda-content"><div class="agenda-topline"><span class="agenda-meta"></span><span class="agenda-badges"><span class="overdue-badge" hidden>Vencido</span><span class="priority-badge ${event.priority || 'medium'}">${priorityLabels[event.priority || 'medium']}</span></span></div><h3></h3><p></p><div class="agenda-actions"></div></div>`;
        article.querySelector('.agenda-meta').textContent = typeLabels[event.type]; article.querySelector('h3').textContent = event.title; article.querySelector('p').textContent = event.description || 'Sin descripción.';
        article.querySelector('.overdue-badge').hidden = !isOverdue(event); article.classList.toggle('is-overdue', isOverdue(event));
        const actions = article.querySelector('.agenda-actions'); actions.append(actionButton(event.completed ? 'fa-arrow-rotate-left' : 'fa-check', event.completed ? 'Reabrir' : 'Completar', () => toggleComplete(event)), actionButton('fa-pen', 'Editar', () => openModal(event)), actionButton('fa-trash-can', 'Eliminar', () => openDeleteDialog(event), true), navigationButton(event));
        article.addEventListener('click', (clickEvent) => { if (!clickEvent.target.closest('.agenda-actions')) openDetails(event); });
        return article;
    }
    function actionButton(icon, label, handler, danger = false) { const button = document.createElement('button'); button.type = 'button'; if (danger) button.className = 'danger'; button.innerHTML = `<i class="fa-solid ${icon}"></i> ${label}`; button.addEventListener('click', handler); return button; }
    function navigationButton(event) { const destination = eventDestination(event), link = document.createElement('a'); link.className = 'agenda-go-link'; link.href = destination.url; link.innerHTML = '<i class="fa-solid fa-arrow-right"></i> Ir'; link.title = destination.label; link.setAttribute('aria-label', `${destination.label}: ${event.title}`); return link; }
    function emptyState(icon, title, message, showClear = false) { const state = document.createElement('div'); state.className = 'calendar-empty calendar-empty-guided'; state.innerHTML = `<i class="fa-solid ${icon}"></i><strong></strong><p></p>`; state.querySelector('strong').textContent = title; state.querySelector('p').textContent = message; if (showClear) { const button = document.createElement('button'); button.className = 'calendar-empty-add'; button.type = 'button'; button.innerHTML = '<i class="fa-solid fa-filter-circle-xmark"></i> Limpiar filtros'; button.addEventListener('click', clearFilters); state.append(button); } return state; }
    function renderAgenda() {
        const date = fromKey(selectedDate), list = $('#calendarAgendaList'), items = filteredEvents().filter((event) => event.date === selectedDate); list.innerHTML = '';
        $('#calendarAgendaTitle').textContent = date.toLocaleDateString('es-EC', { weekday: 'long' }); $('#calendarAgendaDate').textContent = date.toLocaleDateString('es-EC', { day: 'numeric', month: 'long', year: 'numeric' });
        if (!items.length) list.append(emptyState(searchTerm || activeFilter !== 'all' ? 'fa-magnifying-glass' : 'fa-calendar-plus', searchTerm || activeFilter !== 'all' ? 'Sin coincidencias para este día' : 'Tu día está disponible', searchTerm || activeFilter !== 'all' ? 'Limpia los filtros o consulta otra fecha.' : 'Agrega una entrega, reunión o revisión para mantener tu planificación al día.', searchTerm || activeFilter !== 'all'));
        else items.forEach((event) => list.append(eventCard(event)));
        renderUpcoming();
    }
    function renderUpcoming() {
        const list = $('#calendarUpcomingList'), upcoming = sortEvents(events.filter((event) => !event.completed && event.date >= dateKey(today))).slice(0, 5); list.innerHTML = ''; $('#calendarUpcomingBadge').textContent = upcoming.length;
        if (!upcoming.length) { list.innerHTML = '<p class="upcoming-empty">No tienes eventos pendientes. ¡Agenda al día!</p>'; return; }
        upcoming.forEach((event) => { const button = document.createElement('button'); button.type = 'button'; button.className = 'calendar-upcoming-item'; button.innerHTML = `<span class="upcoming-date"><strong>${fromKey(event.date).getDate()}</strong>${fromKey(event.date).toLocaleDateString('es-EC', { month: 'short' })}</span><span><strong></strong><small>${typeLabels[event.type]}</small></span>`; button.querySelector('span:nth-child(2) strong').textContent = event.title; button.addEventListener('click', () => openDetails(event)); list.append(button); });
    }
    function renderFilterStatus() {
        const status = $('#calendarFilterStatus'), active = activeFilter !== 'all' || activePriority !== 'all' || Boolean(searchTerm) || Boolean(quickScope); status.hidden = !active;
        const scopeLabels = { month: 'Eventos del mes', week: 'Próximos 7 días', completed: 'Completados del mes' };
        if (active) status.querySelector('span').textContent = [quickScope ? scopeLabels[quickScope] : '', activeFilter !== 'all' ? (activeFilter === 'pending' ? 'Pendientes' : typeLabels[activeFilter]) : '', activePriority !== 'all' ? `Prioridad ${priorityLabels[activePriority].toLowerCase()}` : '', searchTerm ? `“${$('#calendarSearch').value.trim()}”` : ''].filter(Boolean).join(' · ');
    }
    function updateStats() { const prefix = `${visibleDate.getFullYear()}-${String(visibleDate.getMonth() + 1).padStart(2, '0')}`, monthItems = events.filter((event) => event.date.startsWith(prefix)), completedItems = monthItems.filter((event) => event.completed).length, end = addDays(today, 7), progress = monthItems.length ? Math.round(completedItems / monthItems.length * 100) : 0; $('#calendarMonthCount').textContent = monthItems.length; $('#calendarUpcomingCount').textContent = events.filter((event) => !event.completed && fromKey(event.date) >= today && fromKey(event.date) <= end).length; $('#calendarCompletedCount').textContent = `${progress}%`; $('#calendarCompletedDetail').textContent = `${completedItems} de ${monthItems.length} completados`; $('#calendarProgressBar').style.width = `${progress}%`; }
    function updateTitle() {
        $('#calendarPrevBtn').hidden = quickScope === 'week'; $('#calendarNextBtn').hidden = quickScope === 'week';
        if (quickScope === 'week') $('#calendarMonthTitle').textContent = 'Próximos 7 días';
        else if (quickScope === 'completed') $('#calendarMonthTitle').textContent = `Completados de ${visibleDate.toLocaleDateString('es-EC', { month: 'long', year: 'numeric' })}`;
        else if (quickScope === 'month') $('#calendarMonthTitle').textContent = `Eventos de ${visibleDate.toLocaleDateString('es-EC', { month: 'long', year: 'numeric' })}`;
        else if (activeView === 'month') $('#calendarMonthTitle').textContent = visibleDate.toLocaleDateString('es-EC', { month: 'long', year: 'numeric' });
        else if (activeView === 'week') { const start = startOfWeek(fromKey(selectedDate)), end = addDays(start, 6); $('#calendarMonthTitle').textContent = `${start.toLocaleDateString('es-EC', { day: 'numeric', month: 'short' })} – ${end.toLocaleDateString('es-EC', { day: 'numeric', month: 'short', year: 'numeric' })}`; }
        else $('#calendarMonthTitle').textContent = `Agenda de ${visibleDate.toLocaleDateString('es-EC', { month: 'long', year: 'numeric' })}`;
    }
    function renderAll() { updateTitle(); const panels = { month: $('#calendarMonthView'), week: $('#calendarWeekView'), list: $('#calendarListView') }; Object.entries(panels).forEach(([view, panel]) => { const selected = view === activeView; panel.hidden = !selected; panel.classList.toggle('active', selected); }); $('#calendarListTools').hidden = activeView !== 'list'; if (activeView === 'month') renderMonth(); else if (activeView === 'week') renderWeek(); else renderList(); renderAgenda(); renderFilterStatus(); updateStats(); }
    function clearQuickScope() { quickScope = null; $$('.calendar-stat-action').forEach((button) => { button.classList.remove('active'); button.setAttribute('aria-pressed', 'false'); }); }
    function switchView(view) { clearQuickScope(); activeView = view; try { localStorage.setItem(viewStorageKey, view); } catch (error) {} $$('.calendar-view-switcher button').forEach((button) => { const selected = button.dataset.view === view; button.classList.toggle('active', selected); button.setAttribute('aria-selected', String(selected)); }); renderAll(); }
    function navigate(direction) { if (activeView !== 'week') visibleDate = new Date(visibleDate.getFullYear(), visibleDate.getMonth() + direction, 1); else { const date = fromKey(selectedDate); date.setDate(date.getDate() + direction * 7); selectedDate = dateKey(date); visibleDate = new Date(date.getFullYear(), date.getMonth(), 1); } renderAll(); }
    function clearFilters() { clearQuickScope(); activeFilter = 'all'; activePriority = 'all'; searchTerm = ''; $('#calendarSearch').value = ''; $$('.calendar-filter[data-filter]').forEach((item) => item.classList.toggle('active', item.dataset.filter === 'all')); $$('.calendar-priority-filter').forEach((item) => item.classList.remove('active')); renderAll(); $('#calendarSearch').focus(); }
    function applyQuickScope(scope) { quickScope = scope; activeFilter = 'all'; activePriority = 'all'; searchTerm = ''; $('#calendarSearch').value = ''; if (scope === 'week') visibleDate = new Date(today.getFullYear(), today.getMonth(), 1); $$('.calendar-filter[data-filter]').forEach((item) => item.classList.toggle('active', item.dataset.filter === 'all')); $$('.calendar-priority-filter').forEach((item) => item.classList.remove('active')); activeView = 'list'; $$('.calendar-view-switcher button').forEach((button) => { const selected = button.dataset.view === 'list'; button.classList.toggle('active', selected); button.setAttribute('aria-selected', String(selected)); }); $$('.calendar-stat-action').forEach((button) => { const selected = button.dataset.scope === scope; button.classList.toggle('active', selected); button.setAttribute('aria-pressed', String(selected)); }); renderAll(); $('.calendar-board').scrollIntoView({ behavior: 'smooth', block: 'start' }); }
    function syncSelectStyles() { $$('.calendar-select-wrap').forEach((wrap) => { const select = wrap.querySelector('select'); wrap.dataset.value = select?.value || ''; wrap._syncCustom?.(); }); }
    function closeCustomSelects(except = null) { $$('.calendar-select-wrap.is-open').forEach((wrap) => { if (wrap === except) return; wrap.classList.remove('is-open'); wrap.querySelector('.calendar-select-menu').hidden = true; wrap.querySelector('.calendar-select-trigger').setAttribute('aria-expanded', 'false'); }); }
    function initCustomSelects() {
        $$('.calendar-select-wrap').forEach((wrap) => {
            const select = wrap.querySelector('select'), trigger = document.createElement('button'), menu = document.createElement('div');
            trigger.type = 'button'; trigger.className = 'calendar-select-trigger'; trigger.setAttribute('aria-haspopup', 'listbox'); trigger.setAttribute('aria-expanded', 'false'); trigger.setAttribute('aria-label', select.getAttribute('aria-label') || 'Seleccionar opción'); trigger.innerHTML = '<span></span>';
            menu.className = 'calendar-select-menu'; menu.hidden = true; menu.setAttribute('role', 'listbox'); menu.setAttribute('aria-label', select.getAttribute('aria-label') || 'Opciones');
            [...select.options].forEach((option) => { const item = document.createElement('button'); item.type = 'button'; item.className = 'calendar-select-option'; item.dataset.value = option.value; item.setAttribute('role', 'option'); item.innerHTML = '<i class="calendar-option-dot"></i><span></span><i class="fa-solid fa-check calendar-option-check"></i>'; item.querySelector('span').textContent = option.textContent; item.addEventListener('click', () => { select.value = option.value; select.dispatchEvent(new Event('change', { bubbles: true })); closeCustomSelects(); trigger.focus(); }); menu.append(item); });
            wrap._syncCustom = () => { trigger.querySelector('span').textContent = select.options[select.selectedIndex]?.textContent || ''; menu.querySelectorAll('.calendar-select-option').forEach((item) => { const selected = item.dataset.value === select.value; item.classList.toggle('selected', selected); item.setAttribute('aria-selected', String(selected)); }); };
            const openMenu = () => { const opening = !wrap.classList.contains('is-open'); closeCustomSelects(wrap); wrap.classList.toggle('is-open', opening); menu.hidden = !opening; trigger.setAttribute('aria-expanded', String(opening)); if (opening) menu.querySelector('.selected')?.focus(); };
            trigger.addEventListener('click', (event) => { event.stopPropagation(); openMenu(); });
            trigger.addEventListener('keydown', (event) => { if (event.key === 'ArrowDown' || event.key === 'ArrowUp') { event.preventDefault(); if (!wrap.classList.contains('is-open')) openMenu(); } });
            menu.addEventListener('keydown', (event) => { const items = [...menu.querySelectorAll('.calendar-select-option')], index = items.indexOf(document.activeElement); if (event.key === 'ArrowDown' || event.key === 'ArrowUp') { event.preventDefault(); items[(index + (event.key === 'ArrowDown' ? 1 : -1) + items.length) % items.length]?.focus(); } else if (event.key === 'Escape') { event.preventDefault(); closeCustomSelects(); trigger.focus(); } });
            select.tabIndex = -1; select.setAttribute('aria-hidden', 'true'); wrap.classList.add('enhanced'); wrap.insertBefore(trigger, select); wrap.append(menu); wrap._syncCustom();
        });
        document.addEventListener('click', () => closeCustomSelects());
    }
    function openModal(event = null) {
        $('#calendarEventForm').reset(); $('#calendarEventId').value = event?.id || ''; $('#calendarEventTitle').value = event?.title || ''; $('#calendarEventDate').value = event?.date || selectedDate; $('#calendarEventType').value = event?.type || 'delivery'; $('#calendarEventPriority').value = event?.priority || 'medium'; $('#calendarEventDescription').value = event?.description || ''; $('#calendarDescriptionCount').textContent = (event?.description || '').length; $('#calendarModalTitle').textContent = event ? 'Editar evento' : 'Nuevo evento'; syncSelectStyles(); $('#calendarEventModal').hidden = false; document.body.classList.add('modal-open'); setTimeout(() => $('#calendarEventTitle').focus(), 0);
    }
    function closeModal() { $('#calendarEventModal').hidden = true; document.body.classList.remove('modal-open'); }
    function openDetails(event) { activeDetailEvent = event; $('#calendarDetailType').textContent = typeLabels[event.type]; $('#calendarDetailType').className = `calendar-detail-type ${event.type}`; $('#calendarDetailTitle').textContent = event.title; $('#calendarDetailDate').textContent = fromKey(event.date).toLocaleDateString('es-EC', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }); $('#calendarDetailPriority').textContent = `Prioridad ${priorityLabels[event.priority || 'medium'].toLowerCase()}`; $('#calendarDetailStatus').textContent = event.completed ? 'Completado' : isOverdue(event) ? 'Vencido' : 'Pendiente'; $('#calendarDetailDescription').textContent = event.description || 'Este recordatorio no tiene una descripción adicional.'; const destination = eventDestination(event); $('#calendarDetailProjectLink').href = destination.url; $('#calendarDetailProjectLink span').textContent = destination.label; $('#calendarDetailComplete').innerHTML = event.completed ? '<i class="fa-solid fa-arrow-rotate-left"></i> Reabrir' : '<i class="fa-solid fa-check"></i> Completar'; $('#calendarDetailModal').hidden = false; document.body.classList.add('modal-open'); $('#calendarDetailClose').focus(); }
    function closeDetails() { activeDetailEvent = null; $('#calendarDetailModal').hidden = true; document.body.classList.remove('modal-open'); }
    function openAgenda() { if (!compactAgenda.matches) return; $('#calendarAgenda').classList.add('is-open'); $('#calendarAgendaBackdrop').hidden = false; document.body.classList.add('calendar-agenda-open'); $('#calendarOpenAgendaBtn').setAttribute('aria-expanded', 'true'); }
    function closeAgenda() { $('#calendarAgenda').classList.remove('is-open'); $('#calendarAgendaBackdrop').hidden = true; document.body.classList.remove('calendar-agenda-open'); $('#calendarOpenAgendaBtn').setAttribute('aria-expanded', 'false'); }
    function openDeleteDialog(event) { pendingDelete = event; $('#calendarDeleteMessage').textContent = `Se eliminará “${event.title}” del ${fromKey(event.date).toLocaleDateString('es-EC', { day: 'numeric', month: 'long' })}. Esta acción no se puede deshacer.`; $('#calendarDeleteModal').hidden = false; document.body.classList.add('modal-open'); $('#calendarDeleteConfirm').focus(); }
    function closeDeleteDialog() { pendingDelete = null; $('#calendarDeleteModal').hidden = true; document.body.classList.remove('modal-open'); }
    async function deleteConfirmed() { if (!pendingDelete) return; const event = { ...pendingDelete }; const button = $('#calendarDeleteConfirm'); button.disabled = true; try { await request('DELETE', { id: event.id }); events = events.filter((item) => item.id !== event.id); closeDeleteDialog(); renderAll(); toast('Evento eliminado.', false, { label: 'Deshacer', handler: async () => { try { await saveOne(event); toast('Evento restaurado correctamente.'); } catch (error) { toast(error.message, true); } } }); } catch (error) { toast(error.message, true); } finally { button.disabled = false; } }
    async function saveOne(event, render = true) { const result = await request('POST', event); const index = events.findIndex((item) => item.id === result.data.id); if (index >= 0) events[index] = result.data; else events.push(result.data); if (render) renderAll(); return result.data; }
    async function moveEvent(id, newDate) { const event = events.find((item) => item.id === id); if (!event || event.date === newDate) return; const previous = event.date; event.date = newDate; renderAll(); try { await saveOne(event); selectedDate = newDate; renderAll(); toast(`Evento movido al ${fromKey(newDate).toLocaleDateString('es-EC', { day: 'numeric', month: 'long' })}.`); } catch (error) { event.date = previous; renderAll(); toast(error.message, true); } }
    async function toggleComplete(event) { try { await saveOne({ ...event, completed: !event.completed }); toast(event.completed ? 'Evento reabierto.' : 'Evento completado. ¡Buen trabajo!'); } catch (error) { toast(error.message, true); } }

    $('#calendarPrevBtn').addEventListener('click', () => navigate(-1)); $('#calendarNextBtn').addEventListener('click', () => navigate(1)); $('#calendarTodayBtn').addEventListener('click', () => { clearQuickScope(); selectedDate = dateKey(today); visibleDate = new Date(today.getFullYear(), today.getMonth(), 1); renderAll(); });
    $$('.calendar-stat-action').forEach((button) => button.addEventListener('click', () => applyQuickScope(button.dataset.scope)));
    $$('.calendar-view-switcher button').forEach((button) => button.addEventListener('click', () => switchView(button.dataset.view))); $$('.calendar-filter[data-filter]').forEach((button) => button.addEventListener('click', () => { clearQuickScope(); activeFilter = button.dataset.filter; $$('.calendar-filter[data-filter]').forEach((item) => item.classList.toggle('active', item === button)); renderAll(); }));
    $$('.calendar-priority-filter').forEach((button) => button.addEventListener('click', () => { clearQuickScope(); activePriority = activePriority === button.dataset.priority ? 'all' : button.dataset.priority; $$('.calendar-priority-filter').forEach((item) => item.classList.toggle('active', item.dataset.priority === activePriority)); renderAll(); }));
    $('#calendarSearch').addEventListener('input', (event) => { clearQuickScope(); searchTerm = event.target.value.trim().toLocaleLowerCase('es'); renderAll(); }); $('#calendarClearFilters').addEventListener('click', clearFilters); $('#calendarNewEventBtn').addEventListener('click', () => openModal()); $('#calendarAgendaAdd').addEventListener('click', () => openModal());
    $('#calendarOpenAgendaBtn').addEventListener('click', openAgenda); $('#calendarAgendaClose').addEventListener('click', closeAgenda); $('#calendarAgendaBackdrop').addEventListener('click', closeAgenda); $('#calendarModalClose').addEventListener('click', closeModal); $('#calendarModalCancel').addEventListener('click', closeModal); $('#calendarEventModal').addEventListener('click', (event) => { if (event.target === $('#calendarEventModal')) closeModal(); });
    $('#calendarEventDescription').addEventListener('input', (event) => $('#calendarDescriptionCount').textContent = event.target.value.length);
    $$('.calendar-select-wrap select').forEach((select) => select.addEventListener('change', syncSelectStyles));
    $('#calendarSort').addEventListener('change', (event) => { sortMode = event.target.value; renderAll(); });
    $('#calendarDeleteCancel').addEventListener('click', closeDeleteDialog); $('#calendarDeleteConfirm').addEventListener('click', deleteConfirmed); $('#calendarDeleteModal').addEventListener('click', (event) => { if (event.target === $('#calendarDeleteModal')) closeDeleteDialog(); });
    $('#calendarDetailClose').addEventListener('click', closeDetails); $('#calendarDetailModal').addEventListener('click', (event) => { if (event.target === $('#calendarDetailModal')) closeDetails(); }); $('#calendarDetailEdit').addEventListener('click', () => { const event = activeDetailEvent; closeDetails(); if (event) openModal(event); }); $('#calendarDetailComplete').addEventListener('click', async () => { const event = activeDetailEvent; if (!event) return; closeDetails(); await toggleComplete(event); });
    $('#calendarEventForm').addEventListener('submit', async (formEvent) => {
        formEvent.preventDefault(); const button = $('#calendarSaveBtn'); button.disabled = true;
        try {
            const existing = events.find((item) => item.id === $('#calendarEventId').value);
            const payload = { id: $('#calendarEventId').value, title: $('#calendarEventTitle').value.trim(), date: $('#calendarEventDate').value, type: $('#calendarEventType').value, priority: $('#calendarEventPriority').value, description: $('#calendarEventDescription').value.trim(), completed: existing?.completed || false };
            await saveOne(payload, false); toast(existing ? 'Evento actualizado.' : 'Evento creado correctamente.');
            selectedDate = payload.date; visibleDate = new Date(fromKey(payload.date).getFullYear(), fromKey(payload.date).getMonth(), 1); closeModal(); renderAll();
        } catch (error) { toast(error.message, true); } finally { button.disabled = false; }
    });
    document.addEventListener('keydown', (event) => { if (event.key !== 'Escape') return; if ($('.calendar-select-wrap.is-open')) { closeCustomSelects(); return; } if (!$('#calendarDeleteModal').hidden) closeDeleteDialog(); else if (!$('#calendarDetailModal').hidden) closeDetails(); else if (!$('#calendarEventModal').hidden) closeModal(); else closeAgenda(); }); compactAgenda.addEventListener('change', (event) => { if (!event.matches) closeAgenda(); });
    let touchStartX = 0, touchStartY = 0;
    $('.calendar-view-stage').addEventListener('touchstart', (event) => { const touch = event.changedTouches[0]; touchStartX = touch.clientX; touchStartY = touch.clientY; }, { passive: true });
    $('.calendar-view-stage').addEventListener('touchend', (event) => { const touch = event.changedTouches[0], deltaX = touch.clientX - touchStartX, deltaY = touch.clientY - touchStartY; if (!compactAgenda.matches || Math.abs(deltaX) < 55 || Math.abs(deltaX) < Math.abs(deltaY) * 1.25) return; navigate(deltaX < 0 ? 1 : -1); toast(deltaX < 0 ? 'Siguiente período' : 'Período anterior'); }, { passive: true });
    initCustomSelects();
    $$('.calendar-view-switcher button').forEach((button) => { const selected = button.dataset.view === activeView; button.classList.toggle('active', selected); button.setAttribute('aria-selected', String(selected)); });
    renderAll();
}
