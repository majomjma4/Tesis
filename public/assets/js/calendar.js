const calendarRoot = document.querySelector(".calendar-workspace");

if (calendarRoot) {
    const seedEvents = JSON.parse(calendarRoot.dataset.calendarEvents || "[]");
    const storageKey = "tesis-calendar-events-v1";
    let events = loadEvents();
    const grid = document.querySelector("#calendarDaysGrid");
    const monthTitle = document.querySelector("#calendarMonthTitle");
    const agendaList = document.querySelector("#calendarAgendaList");
    const agendaDate = document.querySelector("#calendarAgendaDate");
    const filters = document.querySelectorAll(".calendar-filter");
    const today = new Date();
    let visibleDate = new Date(today.getFullYear(), today.getMonth(), 1);
    let selectedDate = toDateKey(today);
    let activeFilter = "all";

    const eventModal = document.querySelector("#calendarEventModal");
    const eventForm = document.querySelector("#calendarEventForm");
    const eventIdInput = document.querySelector("#calendarEventId");
    const eventTitleInput = document.querySelector("#calendarEventTitle");
    const eventDateInput = document.querySelector("#calendarEventDate");
    const eventTimeInput = document.querySelector("#calendarEventTime");
    const eventTypeInput = document.querySelector("#calendarEventType");
    const eventDescriptionInput = document.querySelector("#calendarEventDescription");
    const agendaPanel = document.querySelector("#calendarAgenda");
    const agendaBackdrop = document.querySelector("#calendarAgendaBackdrop");
    const openAgendaButton = document.querySelector("#calendarOpenAgendaBtn");
    const compactAgenda = window.matchMedia("(max-width: 1180px)");
    const typeLabels = { delivery: "Entrega", meeting: "Reunion", review: "Revision", deadline: "Fecha limite" };

    function loadEvents() {
        try {
            const stored = localStorage.getItem(storageKey);
            if (stored) return JSON.parse(stored);
        } catch (error) {
            console.warn("No fue posible leer las tareas guardadas.", error);
        }
        const initialEvents = seedEvents.map((event, index) => ({ ...event, id: `seed-${index + 1}` }));
        try { localStorage.setItem(storageKey, JSON.stringify(initialEvents)); } catch (error) { console.warn("No fue posible guardar las tareas iniciales.", error); }
        return initialEvents;
    }

    function saveEvents() {
        try { localStorage.setItem(storageKey, JSON.stringify(events)); } catch (error) { console.warn("No fue posible guardar los cambios del calendario.", error); }
    }

    function toDateKey(date) {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, "0");
        const day = String(date.getDate()).padStart(2, "0");
        return `${year}-${month}-${day}`;
    }
    function dateFromKey(key) {
        const [year, month, day] = key.split("-").map(Number);
        return new Date(year, month - 1, day);
    }
    function filteredEvents() {
        return events.filter((event) => activeFilter === "all" || event.type === activeFilter);
    }
    function renderCalendar() {
        const year = visibleDate.getFullYear();
        const month = visibleDate.getMonth();
        const firstDay = new Date(year, month, 1);
        const startOffset = (firstDay.getDay() + 6) % 7;
        const startDate = new Date(year, month, 1 - startOffset);
        const monthEvents = filteredEvents();
        monthTitle.textContent = visibleDate.toLocaleDateString("es-EC", { month: "long", year: "numeric" });
        grid.innerHTML = "";

        for (let index = 0; index < 42; index += 1) {
            const date = new Date(startDate);
            date.setDate(startDate.getDate() + index);
            const key = toDateKey(date);
            const dayEvents = monthEvents.filter((event) => event.date === key);
            const button = document.createElement("button");
            button.type = "button";
            button.className = "calendar-cell";
            button.dataset.date = key;
            button.setAttribute("aria-label", date.toLocaleDateString("es-EC", { weekday: "long", day: "numeric", month: "long" }));
            if (date.getMonth() !== month) button.classList.add("outside");
            if (key === toDateKey(today)) button.classList.add("today");
            if (key === selectedDate) button.classList.add("selected");
            button.setAttribute("aria-pressed", String(key === selectedDate));
            button.innerHTML = `<span class="calendar-number">${date.getDate()}</span><span class="calendar-cell-events"></span>`;
            const eventContainer = button.querySelector(".calendar-cell-events");
            dayEvents.slice(0, 2).forEach((event) => {
                const marker = document.createElement("span");
                marker.className = `calendar-event-chip ${event.type}`;
                marker.textContent = event.title;
                eventContainer.appendChild(marker);
            });
            if (dayEvents.length > 2) {
                const more = document.createElement("small");
                more.textContent = `+${dayEvents.length - 2} mas`;
                eventContainer.appendChild(more);
            }
            button.addEventListener("click", () => {
                selectedDate = key;
                if (date.getMonth() !== month) visibleDate = new Date(date.getFullYear(), date.getMonth(), 1);
                renderCalendar();
                renderAgenda();
                if (compactAgenda.matches) openAgendaPanel();
            });
            grid.appendChild(button);
        }
        updateStats();
    }
    function renderAgenda() {
        const selected = dateFromKey(selectedDate);
        agendaDate.textContent = selected.toLocaleDateString("es-EC", { day: "numeric", month: "short" });
        const selectedEvents = filteredEvents().filter((event) => event.date === selectedDate).sort((a, b) => a.time.localeCompare(b.time));
        agendaList.innerHTML = "";
        if (!selectedEvents.length) {
            agendaList.innerHTML = '<div class="calendar-empty"><i class="fa-regular fa-calendar-xmark"></i><strong>No hay nada para este dia</strong><p>Puedes aprovechar el espacio o agregar una nueva tarea.</p><button type="button" class="calendar-empty-add"><i class="fa-solid fa-plus"></i> Agregar tarea</button></div>';
            agendaList.querySelector(".calendar-empty-add").addEventListener("click", () => openEventModal());
            return;
        }
        selectedEvents.forEach((event) => {
            const article = document.createElement("article");
            article.className = "calendar-agenda-item";
            const accent = document.createElement("span");
            accent.className = `agenda-accent ${event.type}`;
            const content = document.createElement("div");
            content.className = "agenda-content";
            const meta = document.createElement("span");
            meta.className = "agenda-meta";
            meta.textContent = `${typeLabels[event.type] || event.typeLabel} · ${event.time}`;
            const title = document.createElement("h3");
            title.textContent = event.title;
            const description = document.createElement("p");
            description.textContent = event.description || "Sin descripcion.";
            const actions = document.createElement("div");
            actions.className = "agenda-actions";
            const editButton = document.createElement("button");
            editButton.type = "button";
            editButton.innerHTML = '<i class="fa-solid fa-pen"></i> Editar';
            editButton.addEventListener("click", () => openEventModal(event));
            const deleteButton = document.createElement("button");
            deleteButton.type = "button";
            deleteButton.className = "danger";
            deleteButton.innerHTML = '<i class="fa-regular fa-trash-can"></i> Eliminar';
            deleteButton.addEventListener("click", () => deleteEvent(event.id));
            actions.append(editButton, deleteButton);
            content.append(meta, title, description, actions);
            article.append(accent, content);
            agendaList.appendChild(article);
        });
    }

    function openEventModal(event = null) {
        eventForm.reset();
        eventIdInput.value = event?.id || "";
        eventTitleInput.value = event?.title || "";
        eventDateInput.value = event?.date || selectedDate;
        eventTimeInput.value = event?.time || "09:00";
        eventTypeInput.value = event?.type || "delivery";
        eventDescriptionInput.value = event?.description || "";
        document.querySelector("#calendarModalTitle").textContent = event ? "Editar tarea" : "Nueva tarea";
        eventModal.hidden = false;
        document.body.classList.add("modal-open");
        window.setTimeout(() => eventTitleInput.focus(), 0);
    }

    function closeEventModal() {
        eventModal.hidden = true;
        document.body.classList.remove("modal-open");
    }

    function openAgendaPanel() {
        if (!compactAgenda.matches) return;
        agendaPanel.classList.add("is-open");
        agendaBackdrop.hidden = false;
        document.body.classList.add("calendar-agenda-open");
        openAgendaButton.setAttribute("aria-expanded", "true");
        document.querySelector("#calendarAgendaClose").focus();
    }

    function closeAgendaPanel() {
        agendaPanel.classList.remove("is-open");
        agendaBackdrop.hidden = true;
        document.body.classList.remove("calendar-agenda-open");
        openAgendaButton.setAttribute("aria-expanded", "false");
    }

    function deleteEvent(id) {
        const event = events.find((item) => item.id === id);
        if (!event || !window.confirm(`¿Eliminar la tarea "${event.title}"?`)) return;
        events = events.filter((item) => item.id !== id);
        saveEvents();
        renderCalendar();
        renderAgenda();
    }
    function updateStats() {
        const prefix = `${visibleDate.getFullYear()}-${String(visibleDate.getMonth() + 1).padStart(2, "0")}`;
        document.querySelector("#calendarMonthCount").textContent = events.filter((event) => event.date.startsWith(prefix)).length;
        document.querySelector("#calendarUpcomingCount").textContent = events.filter((event) => event.date >= toDateKey(today)).length;
        document.querySelector("#calendarDeliveryCount").textContent = events.filter((event) => event.type === "delivery" && event.date >= toDateKey(today)).length;
    }
    document.querySelector("#calendarPrevBtn").addEventListener("click", () => { visibleDate = new Date(visibleDate.getFullYear(), visibleDate.getMonth() - 1, 1); renderCalendar(); });
    document.querySelector("#calendarNextBtn").addEventListener("click", () => { visibleDate = new Date(visibleDate.getFullYear(), visibleDate.getMonth() + 1, 1); renderCalendar(); });
    document.querySelector("#calendarTodayBtn").addEventListener("click", () => { visibleDate = new Date(today.getFullYear(), today.getMonth(), 1); selectedDate = toDateKey(today); renderCalendar(); renderAgenda(); });
    filters.forEach((filter) => filter.addEventListener("click", () => {
        filters.forEach((item) => item.classList.remove("active"));
        filter.classList.add("active");
        activeFilter = filter.dataset.filter;
        renderCalendar();
        renderAgenda();
    }));
    document.querySelector("#calendarNewEventBtn").addEventListener("click", () => openEventModal());
    openAgendaButton.addEventListener("click", openAgendaPanel);
    document.querySelector("#calendarAgendaClose").addEventListener("click", closeAgendaPanel);
    agendaBackdrop.addEventListener("click", closeAgendaPanel);
    document.querySelector("#calendarModalClose").addEventListener("click", closeEventModal);
    document.querySelector("#calendarModalCancel").addEventListener("click", closeEventModal);
    eventModal.addEventListener("click", (event) => { if (event.target === eventModal) closeEventModal(); });
    eventForm.addEventListener("submit", (event) => {
        event.preventDefault();
        const id = eventIdInput.value || `event-${Date.now()}`;
        const task = {
            id,
            title: eventTitleInput.value.trim(),
            date: eventDateInput.value,
            time: eventTimeInput.value,
            type: eventTypeInput.value,
            typeLabel: typeLabels[eventTypeInput.value],
            description: eventDescriptionInput.value.trim(),
        };
        if (!task.title || !task.date || !task.time) return;
        const index = events.findIndex((item) => item.id === id);
        if (index >= 0) events[index] = task; else events.push(task);
        selectedDate = task.date;
        const taskDate = dateFromKey(task.date);
        visibleDate = new Date(taskDate.getFullYear(), taskDate.getMonth(), 1);
        saveEvents();
        closeEventModal();
        renderCalendar();
        renderAgenda();
    });
    document.addEventListener("keydown", (event) => {
        if (event.key !== "Escape") return;
        if (!eventModal.hidden) closeEventModal();
        else if (agendaPanel.classList.contains("is-open")) closeAgendaPanel();
    });
    compactAgenda.addEventListener("change", (event) => { if (!event.matches) closeAgendaPanel(); });
    renderCalendar();
    renderAgenda();
}
