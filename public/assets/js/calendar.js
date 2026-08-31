const calendarRoot = document.querySelector('.calendar-workspace');

if (calendarRoot) {
    const $ = (selector) => document.querySelector(selector);
    const $$ = (selector) => [...document.querySelectorAll(selector)];
    const endpoint = calendarRoot.dataset.eventsUrl;
    const calendarError = calendarRoot.dataset.calendarError || '';
    const csrfToken = calendarRoot.dataset.csrf || '';
    const projectUrl = calendarRoot.dataset.projectUrl || '';
    const projectFilterId = Number(calendarRoot.dataset.projectFilter || 0);
    const requestedEventId = Number(calendarRoot.dataset.requestedEventId || 0);
    const requestedEventUnavailable = calendarRoot.dataset.requestedEventUnavailable === 'true';
    const typeLabels = { delivery: 'Entregas', meeting: 'Reuniones', review: 'Revisiones', deadline: 'Fechas límite', personal: 'Personal', defense: 'Defensa', defense_schedule: 'Jornada de defensa' };
    const priorityLabels = { low: 'Baja', medium: 'Media', high: 'Alta' };
    const today = new Date(); today.setHours(0, 0, 0, 0);
    const compactCalendar = window.matchMedia('(max-width: 1180px)');
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

    // Inicio de utilidades de fechas y filtrado
    // Convierte fechas, detecta vencimientos y construye la colección visible según filtros y orden.
    function dateKey(date) { return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`; }
    function fromKey(key) { const [year, month, day] = key.split('-').map(Number); return new Date(year, month - 1, day); }
    function startOfWeek(date) { const result = new Date(date); result.setDate(result.getDate() - ((result.getDay() + 6) % 7)); result.setHours(0, 0, 0, 0); return result; }
    function addDays(date, amount) { const result = new Date(date); result.setDate(result.getDate() + amount); return result; }
    function normalizeSearch(value) { return String(value || '').trim().normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLocaleLowerCase('es'); }
    function isOverdue(event) { return !event.completed && fromKey(event.date) < today; }
    function sortEvents(items) { const priorities = { high: 0, medium: 1, low: 2 }; return [...items].sort((a, b) => { if (sortMode === 'priority') return (priorities[a.priority] ?? 1) - (priorities[b.priority] ?? 1) || `${a.date} ${a.title}`.localeCompare(`${b.date} ${b.title}`, 'es'); if (sortMode === 'completed') return Number(a.completed) - Number(b.completed) || `${a.date} ${a.title}`.localeCompare(`${b.date} ${b.title}`, 'es'); return `${a.date} ${a.title}`.localeCompare(`${b.date} ${b.title}`, 'es'); }); }
    function eventDestination(event) { const id = Number(event.projectId || 0); if (!id || !projectUrl) return null; const tab = event.type === 'review' ? 'observations' : (event.type === 'delivery' || event.type === 'deadline' ? 'deliveries' : 'calendar'); return { url: `${projectUrl}&id=${id}&tab=${tab}`, label: tab === 'observations' ? 'Ir a observaciones' : (tab === 'deliveries' ? 'Ir a entregas' : 'Ir al proyecto') }; }
    function matchesActiveFilters(event) {
        if (projectFilterId && Number(event.projectId || 0) !== projectFilterId) return false;
        const matchesType = activeFilter === 'all' || (activeFilter === 'pending' ? !event.completed : event.type === activeFilter);
        const matchesPriority = activePriority === 'all' || event.priority === activePriority;
        const title = normalizeSearch(event.title);
        return matchesType && matchesPriority && (!searchTerm || title.includes(searchTerm));
    }
    function matchingEvents() { return sortEvents(events.filter(matchesActiveFilters)); }
    function filteredEvents() {
        return matchingEvents().filter((event) => {
            const prefix = `${visibleDate.getFullYear()}-${String(visibleDate.getMonth() + 1).padStart(2, '0')}`;
            const weekEnd = addDays(today, 7);
            return quickScope === 'month' ? event.date.startsWith(prefix) : quickScope === 'week' ? (!event.completed && fromKey(event.date) >= today && fromKey(event.date) <= weekEnd) : quickScope === 'completed' ? (event.completed && event.date.startsWith(prefix)) : true;
        });
    }
    // Final de utilidades de fechas y filtrado

    // Inicio de comunicación y mensajes
    // Centraliza las solicitudes al endpoint y la retroalimentación temporal para el usuario.
    async function request(method, payload) {
        const response = await fetch(endpoint, { method, headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': csrfToken }, body: payload ? JSON.stringify({ ...payload, _csrf: csrfToken }) : undefined });
        const result = await response.json().catch(() => ({ success: false, message: 'El servidor devolvió una respuesta inválida.' }));
        if (!response.ok || !result.success) throw new Error(result.message || 'No fue posible completar la operación.');
        return result;
    }
    function toast(message, error = false, action = null) {
        window.AppToast?.show(message, error ? 'error' : 'success', action ? {
            duration: 6000,
            action: { label: action.label, callback: action.handler }
        } : {});
    }
    // Final de comunicación y mensajes

    // Inicio de construcción de componentes del calendario
    // Crea eventos arrastrables, tarjetas, acciones y estados vacíos reutilizados por las vistas.
    function createChip(event) {
        const chip = document.createElement('span');
        chip.className = `calendar-event-chip ${event.type}${event.completed ? ' completed' : ''}${isOverdue(event) ? ' overdue' : ''}`;
        chip.textContent = event.title;
        chip.draggable = !event.readOnly; chip.dataset.eventId = event.id; chip.title = event.readOnly ? 'Evento académico visible' : 'Arrastra para cambiar la fecha';
        chip.addEventListener('click', (clickEvent) => { clickEvent.stopPropagation(); openDetails(event); });
        chip.addEventListener('dragstart', (dragEvent) => { if (event.readOnly) return; dragEvent.dataTransfer.setData('text/calendar-event', event.id); dragEvent.dataTransfer.effectAllowed = 'move'; chip.classList.add('is-dragging'); });
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
            const cell = document.createElement('button'); cell.type = 'button'; cell.className = 'calendar-cell'; cell.dataset.calendarDate = key;
            if (date.getMonth() !== month) cell.classList.add('outside'); if (key === dateKey(today)) cell.classList.add('today'); if (key === selectedDate) cell.classList.add('selected');
            cell.setAttribute('aria-label', `${date.toLocaleDateString('es-EC', { weekday: 'long', day: 'numeric', month: 'long' })}, ${items.length} eventos`);
            cell.innerHTML = `<span class="calendar-number">${date.getDate()}</span><span class="calendar-cell-events"></span>`;
            items.slice(0, 3).forEach((item) => cell.lastElementChild.append(createChip(item)));
            if (items.length > 3) { const more = document.createElement('small'); more.textContent = `+${items.length - 3} más`; cell.lastElementChild.append(more); }
            cell.addEventListener('click', (event) => { if (event.target.closest('.calendar-event-chip')) return; selectedDate = key; if (date.getMonth() !== month) visibleDate = new Date(date.getFullYear(), date.getMonth(), 1); renderAll(); openAgendaModal(); });
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
            const heading = column.querySelector('button'); heading.dataset.calendarDate = key; heading.addEventListener('click', () => { selectedDate = key; renderAgenda(); openAgendaModal(); });
            const body = column.lastElementChild;
            if (!items.length) body.innerHTML = '<span class="week-empty">Sin eventos</span>';
            items.forEach((item) => { const card = document.createElement('button'); card.type = 'button'; card.className = `calendar-week-card ${item.type}${item.completed ? ' completed' : ''}${isOverdue(item) ? ' overdue' : ''}`; card.draggable = !item.readOnly; card.dataset.eventId = item.id; card.innerHTML = `<strong></strong><small>${isOverdue(item) ? 'Vencido · ' : ''}${typeLabels[item.type]}</small>`; card.querySelector('strong').textContent = item.title; card.addEventListener('click', () => openDetails(item)); card.addEventListener('dragstart', (event) => { if (!item.readOnly) event.dataTransfer.setData('text/calendar-event', item.id); }); body.append(card); });
            column.addEventListener('dblclick', (event) => { if (items.length || event.target.closest('button')) return; selectedDate = key; openModal(); });
            makeDropTarget(column, key); container.append(column);
        }
    }
    function renderList() {
        const container = $('#calendarListView'); container.innerHTML = ''; const prefix = `${visibleDate.getFullYear()}-${String(visibleDate.getMonth() + 1).padStart(2, '0')}`; const items = quickScope ? filteredEvents() : filteredEvents().filter((event) => event.date.startsWith(prefix));
        if (!items.length) { const scoped = Boolean(quickScope), filtered = Boolean(searchTerm || activeFilter !== 'all' || activePriority !== 'all'); container.append(emptyState(scoped ? 'fa-circle-check' : 'fa-magnifying-glass', scoped ? 'No hay eventos en este resumen' : 'No encontramos eventos', quickScope === 'completed' ? 'Aún no has completado eventos durante este mes.' : quickScope === 'week' ? 'No tienes eventos pendientes durante los próximos siete días.' : filtered ? 'Prueba limpiando la búsqueda o los filtros activos.' : 'Crea un evento para comenzar a organizar tu agenda.', scoped || filtered)); return; }
        let lastDate = '';
        items.forEach((event) => {
            if (event.date !== lastDate) { const heading = document.createElement('h3'); heading.className = 'calendar-list-date'; heading.dataset.calendarDate = event.date; if (event.date === dateKey(today)) { heading.classList.add('is-today'); heading.tabIndex = -1; } heading.textContent = fromKey(event.date).toLocaleDateString('es-EC', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }); container.append(heading); lastDate = event.date; }
            container.append(eventCard(event, 'list'));
        });
    }
    function eventCard(event, variant = 'agenda') {
        const article = document.createElement('article'); article.className = `calendar-agenda-item ${variant}${event.completed ? ' is-completed' : ''}`;
        article.innerHTML = `<span class="agenda-accent ${event.type}"></span><div class="agenda-content"><div class="agenda-topline"><span class="agenda-meta"></span><span class="agenda-badges"><span class="overdue-badge" hidden>Vencido</span><span class="priority-badge ${event.priority || 'medium'}">${priorityLabels[event.priority || 'medium']}</span></span></div><h3></h3><p></p><div class="agenda-actions"></div></div>`;
        article.querySelector('.agenda-meta').textContent = `${typeLabels[event.type]}${event.time ? ` · ${event.time}` : ''}`; article.querySelector('h3').textContent = event.title; article.querySelector('p').textContent = event.description || 'Sin descripción.';
        article.querySelector('.overdue-badge').hidden = !isOverdue(event); article.classList.toggle('is-overdue', isOverdue(event));
        const actions = article.querySelector('.agenda-actions');
        if (!event.readOnly) actions.append(actionButton(event.completed ? 'fa-arrow-rotate-left' : 'fa-check', event.completed ? 'Reabrir' : 'Completar', () => toggleComplete(event)), actionButton('fa-pen', 'Editar', () => openModal(event)), actionButton('fa-trash-can', 'Eliminar', () => openDeleteDialog(event), true));
        const navigation = navigationButton(event); if (navigation) actions.append(navigation);
        article.addEventListener('click', (clickEvent) => { if (!clickEvent.target.closest('.agenda-actions')) openDetails(event); });
        return article;
    }
    function actionButton(icon, label, handler, danger = false) { const button = document.createElement('button'); button.type = 'button'; if (danger) button.className = 'danger'; button.innerHTML = `<i class="fa-solid ${icon}"></i> ${label}`; button.addEventListener('click', handler); return button; }
    function navigationButton(event) { const destination = eventDestination(event); if (!destination) return null; const link = document.createElement('a'); link.className = 'agenda-go-link'; link.href = destination.url; link.innerHTML = '<i class="fa-solid fa-arrow-right"></i> Ir'; link.title = destination.label; link.setAttribute('aria-label', `${destination.label}: ${event.title}`); return link; }
    function emptyState(icon, title, message, showClear = false) { const state = document.createElement('div'); state.className = 'calendar-empty calendar-empty-guided'; state.innerHTML = `<i class="fa-solid ${icon}"></i><strong></strong><p></p>`; state.querySelector('strong').textContent = title; state.querySelector('p').textContent = message; if (showClear) { const button = document.createElement('button'); button.className = 'calendar-empty-add'; button.type = 'button'; button.innerHTML = '<i class="fa-solid fa-filter-circle-xmark"></i> Limpiar filtros'; button.addEventListener('click', clearFilters); state.append(button); } return state; }
    // Final de construcción de componentes del calendario

    // Inicio de renderizado y navegación
    // Sincroniza las vistas, agenda, indicadores, filtros y desplazamiento entre periodos.
    function renderAgenda() {
        const date = fromKey(selectedDate), list = $('#calendarAgendaList'), items = filteredEvents().filter((event) => event.date === selectedDate); list.innerHTML = '';
        $('#calendarAgendaTitle').textContent = date.toLocaleDateString('es-EC', { weekday: 'long' }); $('#calendarAgendaDate').textContent = date.toLocaleDateString('es-EC', { day: 'numeric', month: 'long', year: 'numeric' });
        if (!items.length) list.append(emptyState(searchTerm || activeFilter !== 'all' ? 'fa-magnifying-glass' : 'fa-calendar-plus', searchTerm || activeFilter !== 'all' ? 'Sin coincidencias para este día' : 'No hay eventos programados', searchTerm || activeFilter !== 'all' ? 'Limpia los filtros o consulta otra fecha.' : 'No se han registrado actividades académicas para esta fecha', searchTerm || activeFilter !== 'all'));
        else items.forEach((event) => list.append(eventCard(event)));
        renderUpcoming();
    }
    function renderUpcoming() {
        const list = $('#calendarUpcomingList'), upcoming = matchingEvents().filter((event) => !event.completed && event.date >= dateKey(today)).slice(0, 5); list.innerHTML = ''; $('#calendarUpcomingBadge').textContent = upcoming.length;
        if (!upcoming.length) { list.innerHTML = '<p class="upcoming-empty">No hay próximos eventos programados</p>'; return; }
        upcoming.forEach((event) => { const button = document.createElement('button'); button.type = 'button'; button.className = 'calendar-upcoming-item'; button.innerHTML = `<span class="upcoming-date"><strong>${fromKey(event.date).getDate()}</strong>${fromKey(event.date).toLocaleDateString('es-EC', { month: 'short' })}</span><span><strong></strong><small>${typeLabels[event.type]}${event.time ? ` · ${event.time}` : ''}</small></span>`; button.querySelector('span:nth-child(2) strong').textContent = event.title; button.addEventListener('click', () => openDetails(event)); list.append(button); });
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
    function renderAll() { updateTitle(); const panels = { month: $('#calendarMonthView'), week: $('#calendarWeekView'), list: $('#calendarListView') }; Object.entries(panels).forEach(([view, panel]) => { const selected = view === activeView; panel.hidden = !selected; panel.classList.toggle('active', selected); }); $('#calendarListTools').hidden = activeView !== 'list'; if (activeView === 'month') renderMonth(); else if (activeView === 'week') renderWeek(); else renderList(); renderAgenda(); updateStats(); }
    function clearQuickScope() { quickScope = null; $$('.calendar-stat-action').forEach((button) => { button.classList.remove('active'); button.setAttribute('aria-pressed', 'false'); }); }
    function focusDateInView(key) {
        const target = activeView === 'month'
            ? $(`.calendar-cell[data-calendar-date="${key}"]`)
            : activeView === 'week'
                ? $(`.calendar-week-heading[data-calendar-date="${key}"]`)
                : $(`.calendar-list-date[data-calendar-date="${key}"]`);
        if (!target) return;
        target.scrollIntoView({ behavior: 'smooth', block: 'center', inline: 'nearest' });
        if (typeof target.focus === 'function') target.focus({ preventScroll: true });
    }
    function focusTodayInView() { focusDateInView(dateKey(today)); }
    function resetToToday() { selectedDate = dateKey(today); visibleDate = new Date(today.getFullYear(), today.getMonth(), 1); }
    function goToToday() { clearQuickScope(); resetToToday(); renderAll(); focusTodayInView(); if (compactCalendar.matches) openAgendaModal(); }
    function switchView(view) { clearQuickScope(); activeView = view; try { localStorage.setItem(viewStorageKey, view); } catch (error) {} $$('.calendar-view-switcher button').forEach((button) => { const selected = button.dataset.view === view; button.classList.toggle('active', selected); button.setAttribute('aria-selected', String(selected)); }); renderAll(); }
    function navigate(direction) { if (activeView !== 'week') visibleDate = new Date(visibleDate.getFullYear(), visibleDate.getMonth() + direction, 1); else { const date = fromKey(selectedDate); date.setDate(date.getDate() + direction * 7); selectedDate = dateKey(date); visibleDate = new Date(date.getFullYear(), date.getMonth(), 1); } renderAll(); }
    function selectFirstSearchMatch() {
        if (!searchTerm) return null;
        const match = matchingEvents()[0];
        if (!match) return null;
        selectedDate = match.date;
        visibleDate = new Date(fromKey(match.date).getFullYear(), fromKey(match.date).getMonth(), 1);
        return match;
    }
    function clearFilters() { clearQuickScope(); activeFilter = 'all'; activePriority = 'all'; searchTerm = ''; $('#calendarSearch').value = ''; $$('.calendar-filter[data-filter]').forEach((item) => item.classList.toggle('active', item.dataset.filter === 'all')); $$('.calendar-priority-filter').forEach((item) => item.classList.remove('active')); renderAll(); $('#calendarSearch').focus(); }
    function applyQuickScope(scope) { quickScope = scope; activeFilter = 'all'; activePriority = 'all'; searchTerm = ''; $('#calendarSearch').value = ''; if (scope === 'week') visibleDate = new Date(today.getFullYear(), today.getMonth(), 1); $$('.calendar-filter[data-filter]').forEach((item) => item.classList.toggle('active', item.dataset.filter === 'all')); $$('.calendar-priority-filter').forEach((item) => item.classList.remove('active')); activeView = 'list'; $$('.calendar-view-switcher button').forEach((button) => { const selected = button.dataset.view === 'list'; button.classList.toggle('active', selected); button.setAttribute('aria-selected', String(selected)); }); $$('.calendar-stat-action').forEach((button) => { const selected = button.dataset.scope === scope; button.classList.toggle('active', selected); button.setAttribute('aria-pressed', String(selected)); }); renderAll(); $('.calendar-board').scrollIntoView({ behavior: 'smooth', block: 'start' }); }
    // Final de renderizado y navegación

    // Inicio de selectores personalizados
    // Mejora la presentación y accesibilidad de los campos nativos sin alterar sus valores.
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
    // Final de selectores personalizados

    // Inicio de diálogos y persistencia de eventos
    // Controla formularios, detalles, agenda móvil y operaciones que modifican recordatorios.
    const dateField = $('#calendarEventDate'), timeField = $('#calendarEventTime'), dateTrigger = $('#calendarEventDateTrigger'), timeTrigger = $('#calendarEventTimeTrigger'), datePicker = $('#calendarDatePicker'), timePicker = $('#calendarTimePicker');
    let pickerMonth = new Date(today.getFullYear(), today.getMonth(), 1);
    function formatPickerDate(value) { return value ? fromKey(value).toLocaleDateString('es-EC', { day: '2-digit', month: 'long', year: 'numeric' }) : 'Seleccionar fecha'; }
    function syncDateTimeControls() { dateTrigger.querySelector('span').textContent = formatPickerDate(dateField.value); timeTrigger.querySelector('span').textContent = timeField.value || 'Sin hora'; }
    function closeDateTimePickers() { datePicker.hidden = true; timePicker.hidden = true; dateTrigger.setAttribute('aria-expanded', 'false'); timeTrigger.setAttribute('aria-expanded', 'false'); }
    function renderDatePicker() {
        $('#calendarDateHeading').textContent = pickerMonth.toLocaleDateString('es-EC', { month: 'long', year: 'numeric' });
        const days = $('#calendarDateDays'); days.replaceChildren();
        const first = new Date(pickerMonth.getFullYear(), pickerMonth.getMonth(), 1), offset = (first.getDay() + 6) % 7, cursor = new Date(first);
        cursor.setDate(1 - offset);
        for (let index = 0; index < 42; index += 1) {
            const day = new Date(cursor), key = dateKey(day), button = document.createElement('button');
            button.type = 'button'; button.textContent = String(day.getDate()); button.dataset.date = key;
            button.classList.toggle('is-outside', day.getMonth() !== pickerMonth.getMonth()); button.classList.toggle('is-selected', key === dateField.value); button.classList.toggle('is-today', key === dateKey(today));
            button.addEventListener('click', () => { dateField.value = key; dateField.dispatchEvent(new Event('change', { bubbles: true })); syncDateTimeControls(); closeDateTimePickers(); });
            days.append(button); cursor.setDate(cursor.getDate() + 1);
        }
    }
    function syncTimeInputs() { const [hour = '', minute = ''] = (timeField.value || ':').split(':'); $('#calendarTimeHour').value = hour; $('#calendarTimeMinute').value = minute; }
    function applyTime() {
        const hour = $('#calendarTimeHour').value.trim(), minute = $('#calendarTimeMinute').value.trim();
        if (hour === '' && minute === '') { timeField.value = ''; }
        else if (/^\d{1,2}$/.test(hour) && /^\d{1,2}$/.test(minute) && Number(hour) <= 23 && Number(minute) <= 59) { timeField.value = `${hour.padStart(2, '0')}:${minute.padStart(2, '0')}`; }
        else { $('#calendarTimeHour').focus(); return; }
        timeField.dispatchEvent(new Event('change', { bubbles: true })); syncDateTimeControls(); closeDateTimePickers();
    }
    function openDatePicker() { closeDateTimePickers(); const base = dateField.value ? fromKey(dateField.value) : today; pickerMonth = new Date(base.getFullYear(), base.getMonth(), 1); renderDatePicker(); datePicker.hidden = false; dateTrigger.setAttribute('aria-expanded', 'true'); }
    function openTimePicker() { closeDateTimePickers(); syncTimeInputs(); timePicker.hidden = false; timeTrigger.setAttribute('aria-expanded', 'true'); }
    dateTrigger.addEventListener('click', (event) => { event.stopPropagation(); openDatePicker(); }); timeTrigger.addEventListener('click', (event) => { event.stopPropagation(); openTimePicker(); });
    $('#calendarDatePrev').addEventListener('click', () => { pickerMonth = new Date(pickerMonth.getFullYear(), pickerMonth.getMonth() - 1, 1); renderDatePicker(); }); $('#calendarDateNext').addEventListener('click', () => { pickerMonth = new Date(pickerMonth.getFullYear(), pickerMonth.getMonth() + 1, 1); renderDatePicker(); });
    $('#calendarDateToday').addEventListener('click', () => { dateField.value = dateKey(today); dateField.dispatchEvent(new Event('change', { bubbles: true })); syncDateTimeControls(); closeDateTimePickers(); }); $('#calendarDateClear').addEventListener('click', () => { dateField.value = ''; dateField.dispatchEvent(new Event('change', { bubbles: true })); syncDateTimeControls(); closeDateTimePickers(); });
    $('#calendarTimeClose').addEventListener('click', closeDateTimePickers); $('#calendarTimeApply').addEventListener('click', applyTime); $('#calendarTimeClear').addEventListener('click', () => { timeField.value = ''; timeField.dispatchEvent(new Event('change', { bubbles: true })); syncDateTimeControls(); closeDateTimePickers(); });
    ['#calendarTimeHour', '#calendarTimeMinute'].forEach((selector) => $(selector).addEventListener('keydown', (event) => { if (event.key === 'Enter') { event.preventDefault(); applyTime(); } }));
    document.addEventListener('click', (event) => { if (!event.target.closest('[data-calendar-date-control], [data-calendar-time-control]')) closeDateTimePickers(); });

    // En pantallas compactas, el modal se monta directamente en <body>. De ese
    // modo no queda atrapado por el contexto de apilamiento del contenido de la
    // página (que puede tener transformaciones durante la navegación).
    const agenda = $('#calendarAgenda');
    const agendaModal = $('#calendarAgendaModal');
    function mountAgendaModal() {
        if (agenda.parentElement === agendaModal) return;
        document.body.append(agendaModal);
        agendaModal.classList.add('calendar-page');
        agendaModal.append(agenda);
    }
    function restoreAgendaSidebar() {
        if (agenda.parentElement !== calendarRoot) calendarRoot.append(agenda);
        if (agendaModal.parentElement !== calendarRoot) calendarRoot.append(agendaModal);
        agendaModal.classList.remove('calendar-page');
    }
    function syncAgendaModalMode() {
        closeAgendaModal();
        if (compactCalendar.matches) mountAgendaModal();
        else restoreAgendaSidebar();
    }
    function openAgendaModal() { if (!compactCalendar.matches) return; mountAgendaModal(); agendaModal.hidden = false; agenda.classList.add('is-modal-open'); document.body.classList.add('modal-open'); $('#calendarAgendaClose').focus(); }
    function closeAgendaModal() { $('#calendarAgendaModal').hidden = true; $('#calendarAgenda').classList.remove('is-modal-open'); document.body.classList.remove('modal-open'); }
    function openModal(event = null) {
        closeAgendaModal();
        $('#calendarEventForm').reset(); $('#calendarEventId').value = event?.id || ''; $('#calendarEventTitle').value = event?.title || ''; $('#calendarEventDate').value = event?.date || selectedDate; $('#calendarEventTime').value = event?.time || ''; $('#calendarEventType').value = event?.type || 'delivery'; $('#calendarEventPriority').value = event?.priority || 'medium'; $('#calendarEventDescription').value = event?.description || ''; $('#calendarDescriptionCount').textContent = (event?.description || '').length; $('#calendarModalTitle').textContent = event ? 'Editar evento' : 'Nuevo evento'; syncSelectStyles(); syncDateTimeControls(); $('#calendarEventModal').hidden = false; document.body.classList.add('modal-open'); setTimeout(() => $('#calendarEventTitle').focus(), 0);
    }
    function closeModal() { closeDateTimePickers(); $('#calendarEventModal').hidden = true; document.body.classList.remove('modal-open'); }
    function openDetails(event) { closeAgendaModal(); activeDetailEvent = event; const readOnly = Boolean(event.readOnly); $('#calendarDetailType').textContent = typeLabels[event.type]; $('#calendarDetailType').className = `calendar-detail-type ${event.type}`; $('#calendarDetailTitle').textContent = event.title; $('#calendarDetailDate').textContent = `${fromKey(event.date).toLocaleDateString('es-EC', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })}${event.time ? ` — ${event.time}` : ''}`; $('#calendarDetailPriority').textContent = `Prioridad ${priorityLabels[event.priority || 'medium'].toLowerCase()}`; $('#calendarDetailStatus').textContent = event.completed ? 'Completado' : isOverdue(event) ? 'Vencido' : 'Pendiente'; $('#calendarDetailDescription').textContent = event.description || 'Este recordatorio no tiene una descripción adicional.'; const destination = eventDestination(event); const projectLink = $('#calendarDetailProjectLink'); projectLink.hidden = !destination; if (destination) { projectLink.href = destination.url; projectLink.querySelector('span').textContent = destination.label; } else { projectLink.removeAttribute('href'); } $('#calendarDetailEdit').hidden = readOnly; $('#calendarDetailComplete').hidden = readOnly; $('#calendarDetailComplete').innerHTML = event.completed ? '<i class="fa-solid fa-arrow-rotate-left"></i> Reabrir' : '<i class="fa-solid fa-check"></i> Completar'; $('#calendarDetailModal').hidden = false; document.body.classList.add('modal-open'); $('#calendarDetailClose').focus(); }
    function closeDetails() { activeDetailEvent = null; $('#calendarDetailModal').hidden = true; document.body.classList.remove('modal-open'); }
    function openDeleteDialog(event) { closeDateTimePickers(); closeCustomSelects(); closeAgendaModal(); pendingDelete = event; $('#calendarDeleteMessage').textContent = `Se eliminará “${event.title}” del ${fromKey(event.date).toLocaleDateString('es-EC', { day: 'numeric', month: 'long' })}. Esta acción no se puede deshacer.`; $('#calendarDeleteModal').hidden = false; document.body.classList.add('modal-open'); $('#calendarDeleteConfirm').focus(); }
    function closeDeleteDialog() { pendingDelete = null; $('#calendarDeleteModal').hidden = true; document.body.classList.remove('modal-open'); }
    async function deleteConfirmed() { if (!pendingDelete) return; const event = { ...pendingDelete }; const button = $('#calendarDeleteConfirm'); window.AppLoading?.setButtonLoading(button, true, 'Eliminando…'); try { await request('DELETE', { id: event.id }); events = events.filter((item) => item.id !== event.id); closeDeleteDialog(); renderAll(); toast('Evento eliminado.', false, { label: 'Deshacer', handler: async () => { try { await saveOne({ ...event, id: '' }); toast('Evento restaurado correctamente.'); } catch (error) { toast(error.message, true); } } }); } catch (error) { toast(error.message, true); } finally { window.AppLoading?.setButtonLoading(button, false); } }
    async function saveOne(event, render = true) { const result = await request('POST', event); const index = events.findIndex((item) => item.id === result.data.id); if (index >= 0) events[index] = result.data; else events.push(result.data); if (render) renderAll(); return result.data; }
    async function moveEvent(id, newDate) { const event = events.find((item) => item.id === id); if (!event || event.readOnly || event.date === newDate) return; const previous = event.date; event.date = newDate; renderAll(); try { await saveOne(event); selectedDate = newDate; renderAll(); toast(`Evento movido al ${fromKey(newDate).toLocaleDateString('es-EC', { day: 'numeric', month: 'long' })}.`); } catch (error) { event.date = previous; renderAll(); toast(error.message, true); } }
    async function toggleComplete(event) { try { await saveOne({ ...event, completed: !event.completed }); toast(event.completed ? 'Evento reabierto.' : 'Evento completado. ¡Buen trabajo!'); } catch (error) { toast(error.message, true); } }
    // Final de diálogos y persistencia de eventos

    // Inicio de eventos de interacción
    // Conecta controles, teclado, gestos táctiles y envío del formulario con la lógica anterior.
    $('#calendarPrevBtn').addEventListener('click', () => navigate(-1)); $('#calendarNextBtn').addEventListener('click', () => navigate(1)); $('#calendarTodayBtn').addEventListener('click', goToToday);
    $$('.calendar-stat-action').forEach((button) => button.addEventListener('click', () => applyQuickScope(button.dataset.scope)));
    $$('.calendar-view-switcher button').forEach((button) => button.addEventListener('click', () => switchView(button.dataset.view))); $$('.calendar-filter[data-filter]').forEach((button) => button.addEventListener('click', () => { clearQuickScope(); activeFilter = button.dataset.filter; $$('.calendar-filter[data-filter]').forEach((item) => item.classList.toggle('active', item === button)); renderAll(); }));
    $$('.calendar-priority-filter').forEach((button) => button.addEventListener('click', () => { clearQuickScope(); activePriority = activePriority === button.dataset.priority ? 'all' : button.dataset.priority; $$('.calendar-priority-filter').forEach((item) => item.classList.toggle('active', item.dataset.priority === activePriority)); renderAll(); }));
    $('#calendarSearch').addEventListener('input', (event) => { clearQuickScope(); searchTerm = normalizeSearch(event.target.value); selectFirstSearchMatch(); if (!searchTerm) resetToToday(); renderAll(); }); $('#calendarNewEventBtn').addEventListener('click', () => openModal()); $('#calendarAgendaAdd').addEventListener('click', () => openModal()); $('#calendarAgendaClose').addEventListener('click', closeAgendaModal); $('#calendarAgendaModal').addEventListener('click', (event) => { if (event.target === $('#calendarAgendaModal')) closeAgendaModal(); });
    $('#calendarModalClose').addEventListener('click', closeModal); $('#calendarModalCancel').addEventListener('click', closeModal); $('#calendarEventModal').addEventListener('click', (event) => { if (event.target === $('#calendarEventModal')) closeModal(); });
    $('#calendarEventDescription').addEventListener('input', (event) => $('#calendarDescriptionCount').textContent = event.target.value.length);
    $$('.calendar-select-wrap select').forEach((select) => select.addEventListener('change', syncSelectStyles));
    $('#calendarSort').addEventListener('change', (event) => { sortMode = event.target.value; renderAll(); });
    $('#calendarDeleteCancel').addEventListener('click', closeDeleteDialog); $('#calendarDeleteConfirm').addEventListener('click', deleteConfirmed); $('#calendarDeleteModal').addEventListener('click', (event) => { if (event.target === $('#calendarDeleteModal')) closeDeleteDialog(); });
    $('#calendarDetailClose').addEventListener('click', closeDetails); $('#calendarDetailModal').addEventListener('click', (event) => { if (event.target === $('#calendarDetailModal')) closeDetails(); }); $('#calendarDetailEdit').addEventListener('click', () => { const event = activeDetailEvent; closeDetails(); if (event && !event.readOnly) openModal(event); }); $('#calendarDetailComplete').addEventListener('click', async () => { const event = activeDetailEvent; if (!event || event.readOnly) return; closeDetails(); await toggleComplete(event); });
    $('#calendarEventForm').addEventListener('submit', async (formEvent) => {
        formEvent.preventDefault(); const button = $('#calendarSaveBtn'); window.AppLoading?.setButtonLoading(button, true, 'Guardando…');
        try {
            const existing = events.find((item) => item.id === $('#calendarEventId').value);
            const payload = { id: $('#calendarEventId').value, title: $('#calendarEventTitle').value.trim(), date: $('#calendarEventDate').value, time: $('#calendarEventTime').value || null, type: $('#calendarEventType').value, priority: $('#calendarEventPriority').value, description: $('#calendarEventDescription').value.trim(), completed: existing?.completed || false };
            await saveOne(payload, false); toast(existing ? 'Evento actualizado.' : 'Evento creado correctamente.');
            selectedDate = payload.date; visibleDate = new Date(fromKey(payload.date).getFullYear(), fromKey(payload.date).getMonth(), 1); closeModal(); renderAll();
        } catch (error) { toast(error.message, true); } finally { window.AppLoading?.setButtonLoading(button, false); }
    });
    document.addEventListener('keydown', (event) => { if (event.key !== 'Escape') return; if (!datePicker.hidden || !timePicker.hidden) { closeDateTimePickers(); return; } if ($('.calendar-select-wrap.is-open')) { closeCustomSelects(); return; } if (!$('#calendarDeleteModal').hidden) closeDeleteDialog(); else if (!$('#calendarDetailModal').hidden) closeDetails(); else if (!$('#calendarEventModal').hidden) closeModal(); else if (!$('#calendarAgendaModal').hidden && compactCalendar.matches) closeAgendaModal(); }); compactCalendar.addEventListener('change', syncAgendaModalMode);
    let touchStartX = 0, touchStartY = 0;
    $('.calendar-view-stage').addEventListener('touchstart', (event) => { const touch = event.changedTouches[0]; touchStartX = touch.clientX; touchStartY = touch.clientY; }, { passive: true });
    $('.calendar-view-stage').addEventListener('touchend', (event) => { const touch = event.changedTouches[0], deltaX = touch.clientX - touchStartX, deltaY = touch.clientY - touchStartY; if (!compactCalendar.matches || Math.abs(deltaX) < 55 || Math.abs(deltaX) < Math.abs(deltaY) * 1.25) return; navigate(deltaX < 0 ? 1 : -1); toast(deltaX < 0 ? 'Siguiente período' : 'Período anterior'); }, { passive: true });
    syncAgendaModalMode();
    initCustomSelects();
    $$('.calendar-view-switcher button').forEach((button) => { const selected = button.dataset.view === activeView; button.classList.toggle('active', selected); button.setAttribute('aria-selected', String(selected)); });
    renderAll();
    if (requestedEventId > 0) {
        const requestedEvent = events.find((event) => Number(event.id) === requestedEventId);
        if (requestedEvent) {
            selectedDate = requestedEvent.date;
            visibleDate = new Date(fromKey(requestedEvent.date).getFullYear(), fromKey(requestedEvent.date).getMonth(), 1);
            renderAll();
            requestAnimationFrame(() => openDetails(requestedEvent));
        }
    } else if (requestedEventUnavailable) {
        toast('El evento solicitado no está disponible.', true);
    }
    if (calendarError) {
        ['#calendarNewEventBtn', '#calendarAgendaAdd'].forEach((selector) => {
            const button = $(selector);
            if (button) button.disabled = true;
        });
    }
    // Final de eventos de interacción
}
