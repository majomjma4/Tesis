(() => {
    const root = document.querySelector('[data-projects-page]');
    if (!root) return;
    const cards = [...root.querySelectorAll('[data-project-card]')];
    const search = root.querySelector('[data-project-search]');
    const status = root.querySelector('[data-project-status]');
    const type = root.querySelector('[data-project-type]');
    const period = root.querySelector('[data-project-period]');
    const sort = root.querySelector('[data-project-sort]');
    const grid = root.querySelector('[data-project-grid]');
    const empty = root.querySelector('[data-project-empty]');
    const report = root.querySelector('[data-filter-status]');
    const metricButtons = [...root.querySelectorAll('[data-metric]')];
    let metric = '';

    function update() {
        const query = (search?.value || '').trim().toLocaleLowerCase('es');
        const filtered = cards.filter(card => (!query || card.dataset.search.includes(query)) && (!status?.value || card.dataset.status === status.value) && (!type?.value || card.dataset.type === type.value) && (!period?.value || card.dataset.period === period.value) && (!metric || card.dataset.metric === metric));
        const ordered = [...filtered].sort((a, b) => sort?.value === 'title' ? a.dataset.title.localeCompare(b.dataset.title, 'es') : sort?.value === 'progress' ? Number(b.dataset.progress) - Number(a.dataset.progress) : Number(b.dataset.activity) - Number(a.dataset.activity));
        cards.forEach(card => card.hidden = true);
        ordered.forEach(card => { card.hidden = false; grid?.append(card); });
        if (empty) empty.hidden = filtered.length > 0;
        if (grid) grid.hidden = filtered.length === 0;
        const active = Boolean(query || status?.value || type?.value || period?.value || metric);
        root.querySelectorAll('[data-project-clear]').forEach(button => button.hidden = !active);
        if (report) {
            const descriptions = [];
            if (query) descriptions.push(`búsqueda “${search.value.trim()}”`);
            if (metric) descriptions.push(`grupo ${metricButtons.find(item => item.dataset.metric === metric)?.textContent.trim() || metric}`);
            if (status?.value) descriptions.push(`estado ${status.options[status.selectedIndex].text}`);
            if (type?.value) descriptions.push(`tipo ${type.options[type.selectedIndex].text}`);
            if (period?.value) descriptions.push(`periodo ${period.value}`);
            report.textContent = active ? `Mostrando ${filtered.length} de ${cards.length} proyectos: ${descriptions.join(', ')}.` : `Mostrando tus ${cards.length} proyectos.`;
        }
    }
    [search, status, type, period, sort].forEach(control => control?.addEventListener(control === search ? 'input' : 'change', update));
    metricButtons.forEach(button => button.addEventListener('click', () => { const next = metric === button.dataset.metric ? '' : button.dataset.metric; metric = next; metricButtons.forEach(item => item.setAttribute('aria-pressed', String(item.dataset.metric === metric))); update(); }));
    root.querySelectorAll('[data-project-clear]').forEach(button => button.addEventListener('click', () => { if (search) search.value = ''; if (status) status.value = ''; if (type) type.value = ''; if (period) period.value = ''; metric = ''; metricButtons.forEach(item => item.setAttribute('aria-pressed', 'false')); update(); search?.focus(); }));
    update();
})();
