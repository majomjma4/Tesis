const calendarRoot = document.querySelector(".calendar-workspace");

if (calendarRoot) {
    const events = JSON.parse(calendarRoot.dataset.calendarEvents || "[]");
    const grid = document.querySelector("#calendarDaysGrid");
    const monthTitle = document.querySelector("#calendarMonthTitle");
    const agendaList = document.querySelector("#calendarAgendaList");
    const agendaDate = document.querySelector("#calendarAgendaDate");
    const filters = document.querySelectorAll(".calendar-filter");
    const today = new Date();
    let visibleDate = new Date(today.getFullYear(), today.getMonth(), 1);
    let selectedDate = toDateKey(today);
    let activeFilter = "all";

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
            });
            grid.appendChild(button);
        }
        updateStats();
    }
    function renderAgenda() {
        const selected = dateFromKey(selectedDate);
        agendaDate.textContent = selected.toLocaleDateString("es-EC", { day: "numeric", month: "short" });
        const selectedEvents = filteredEvents().filter((event) => event.date === selectedDate);
        agendaList.innerHTML = "";
        if (!selectedEvents.length) {
            agendaList.innerHTML = '<div class="calendar-empty"><i class="fa-regular fa-calendar-xmark"></i><strong>Dia despejado</strong><p>No hay actividades programadas.</p></div>';
            return;
        }
        selectedEvents.forEach((event) => {
            const eventDate = dateFromKey(event.date);
            const article = document.createElement("article");
            article.className = "calendar-agenda-item";
            article.innerHTML = `<span class="agenda-accent ${event.type}"></span><div class="agenda-content"><span class="agenda-meta"><b>${event.typeLabel}</b> ${eventDate.toLocaleDateString("es-EC", { day: "numeric", month: "short" })} · ${event.time}</span><h3>${event.title}</h3><p>${event.description}</p></div>`;
            agendaList.appendChild(article);
        });
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
    renderCalendar();
    renderAgenda();
}
