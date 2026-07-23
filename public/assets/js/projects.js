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
    const pagination = root.querySelector('[data-project-pagination]');
    let appliedSort = null;
    let currentPage = 1;
    let perPage = 10;
    const initialParams = new URLSearchParams(window.location.search);
    const normalize = value => String(value || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLocaleLowerCase('es');
    const thesisOnlyStatuses = [...(status?.options || [])].filter(option => ['defense', 'tribunal_approved'].includes(option.value));
    const syncStatusWorkflow = () => {
        if (!type || !thesisOnlyStatuses.length) return;
        const allowDefense = !type.value || type.value === 'thesis';
        thesisOnlyStatuses.forEach(option => { option.disabled = !allowDefense; option.hidden = !allowDefense; });
        if (!allowDefense && ['defense', 'tribunal_approved'].includes(status.value)) {
            status.value = '';
            status.dispatchEvent(new Event('change', { bubbles: true }));
        }
    };
    if (search) search.value = initialParams.get('q') || '';
    if (status && [...status.options].some(option => option.value === initialParams.get('status'))) status.value = initialParams.get('status');
    if (type && [...type.options].some(option => option.value === initialParams.get('type'))) type.value = initialParams.get('type');
    if (period && [...period.options].some(option => option.value === initialParams.get('period'))) period.value = initialParams.get('period');
    if (sort && [...sort.options].some(option => option.value === initialParams.get('sort'))) sort.value = initialParams.get('sort');
    syncStatusWorkflow();
    [status, type, period, sort].forEach(select => select?.dispatchEvent(new Event('change', { bubbles: true })));

    function enhanceSelect(select) {
        if (!select || select.dataset.enhanced === 'true') return;
        select.dataset.enhanced = 'true';
        select.classList.add('projects-native-select');
        const shell = document.createElement('div');
        shell.className = 'projects-dropdown';
        const trigger = document.createElement('button');
        trigger.type = 'button';
        trigger.className = 'projects-dropdown-trigger';
        trigger.setAttribute('aria-haspopup', 'listbox');
        trigger.setAttribute('aria-expanded', 'false');
        const value = document.createElement('span');
        const chevron = document.createElement('i');
        chevron.className = 'fa-solid fa-chevron-down';
        const menu = document.createElement('div');
        menu.className = 'projects-dropdown-menu';
        menu.setAttribute('role', 'listbox');
        menu.hidden = true;

        const sync = () => {
            value.textContent = select.options[select.selectedIndex]?.text || '';
            [...menu.children].forEach((option, index) => {
                const selected = index === select.selectedIndex;
                option.classList.toggle('is-selected', selected);
                option.setAttribute('aria-selected', String(selected));
            });
        };
        [...select.options].forEach((source, index) => {
            const option = document.createElement('button');
            option.type = 'button';
            option.className = 'projects-dropdown-option';
            option.setAttribute('role', 'option');
            option.innerHTML = `<span>${source.text}</span><i class="fa-solid fa-check"></i>`;
            option.addEventListener('click', () => {
                select.selectedIndex = index;
                select.dispatchEvent(new Event('change', { bubbles: true }));
                sync();
                close();
                trigger.focus();
            });
            menu.append(option);
        });
        const close = () => { shell.classList.remove('is-open'); menu.hidden = true; trigger.setAttribute('aria-expanded', 'false'); };
        trigger.append(value, chevron);
        trigger.addEventListener('click', () => {
            const open = menu.hidden;
            document.querySelectorAll('.projects-dropdown.is-open').forEach(item => item !== shell && item.querySelector('.projects-dropdown-trigger')?.click());
            shell.classList.toggle('is-open', open);
            menu.hidden = !open;
            trigger.setAttribute('aria-expanded', String(open));
            if (open) menu.querySelector('.is-selected')?.focus();
        });
        shell.addEventListener('keydown', event => {
            const options = [...menu.children];
            if (event.key === 'Escape') { close(); trigger.focus(); }
            if (!menu.hidden && ['ArrowDown', 'ArrowUp'].includes(event.key)) {
                event.preventDefault();
                const current = Math.max(0, options.indexOf(document.activeElement));
                options[(current + (event.key === 'ArrowDown' ? 1 : -1) + options.length) % options.length]?.focus();
            }
        });
        select.after(shell);
        shell.append(trigger, menu);
        select.addEventListener('change', sync);
        sync();
        document.addEventListener('click', event => { if (!shell.contains(event.target)) close(); });
    }
    [status, type, period, sort].forEach(enhanceSelect);

    function renderPagination(total) {
        if (!pagination) return;
        pagination.replaceChildren();
        const pages = Math.max(1, Math.ceil(total / perPage));
        currentPage = Math.min(currentPage, pages);
        pagination.hidden = total <= 10;
        if (total <= 10) return;
        const from = (currentPage - 1) * perPage + 1;
        const to = Math.min(currentPage * perPage, total);
        const summary = document.createElement('p'); summary.textContent = `Mostrando ${to} de ${total}`;
        const sizeLabel = document.createElement('label'); sizeLabel.append(document.createTextNode('Mostrar '));
        const size = document.createElement('select'); size.setAttribute('aria-label', 'Cantidad de proyectos visibles');
        [10, 25, 50, 75, 100].filter(value => value <= total).forEach(value => { const option = document.createElement('option'); option.value = value; option.textContent = value; option.selected = value === perPage; size.append(option); });
        size.addEventListener('change', () => { perPage = Number(size.value); currentPage = 1; update(false); }); sizeLabel.append(size);
        const controls = document.createElement('div'); controls.className = 'projects-pagination-pages';
        const button = (label, page, disabled = false, active = false) => { const item = document.createElement('button'); item.type = 'button'; item.textContent = label; item.disabled = disabled; item.classList.toggle('is-current', active); if (active) item.setAttribute('aria-current', 'page'); item.addEventListener('click', () => { currentPage = page; update(false); grid?.scrollIntoView({ behavior: 'smooth', block: 'start' }); }); controls.append(item); };
        button('‹', currentPage - 1, currentPage === 1);
        const start = Math.max(1, Math.min(currentPage - 2, pages - 4));
        for (let page = start; page <= Math.min(pages, start + 4); page++) button(String(page), page, false, page === currentPage);
        button('›', currentPage + 1, currentPage === pages);
        pagination.append(summary, sizeLabel, controls);
    }

    function update(resetPage = true) {
        if (resetPage) currentPage = 1;
        const query = normalize(search?.value.trim());
        const filtered = cards.filter(card => (!query || normalize(card.dataset.search).includes(query)) && (!status?.value || card.dataset.status === status.value) && (!type?.value || card.dataset.type === type.value) && (!period?.value || card.dataset.period === period.value));
        const sortValue = sort?.value || 'activity';
        if (sortValue !== appliedSort) {
            [...cards].sort((a, b) => sortValue === 'title' ? a.dataset.title.localeCompare(b.dataset.title, 'es') : sortValue === 'progress' ? Number(b.dataset.progress) - Number(a.dataset.progress) : Number(b.dataset.activity) - Number(a.dataset.activity)).forEach(card => grid?.append(card));
            appliedSort = sortValue;
        }
        const orderedFiltered = [...grid.querySelectorAll('[data-project-card]')].filter(card => filtered.includes(card));
        const availablePageSizes = [10, 25, 50, 75, 100].filter(value => value <= orderedFiltered.length);
        if (availablePageSizes.length && perPage > availablePageSizes.at(-1)) perPage = availablePageSizes.at(-1);
        const pageStart = (currentPage - 1) * perPage;
        cards.forEach(card => card.hidden = true);
        orderedFiltered.slice(pageStart, pageStart + perPage).forEach(card => card.hidden = false);
        renderPagination(orderedFiltered.length);
        if (empty) empty.hidden = filtered.length > 0;
        if (grid) grid.hidden = filtered.length === 0;
        const active = Boolean(query || status?.value || type?.value || period?.value);
        root.querySelectorAll('[data-project-clear]').forEach(button => button.hidden = !active);
        if (report) {
            const descriptions = [];
            if (query) descriptions.push(`búsqueda “${search.value.trim()}”`);
            if (status?.value) descriptions.push(`estado ${status.options[status.selectedIndex].text}`);
            if (type?.value) descriptions.push(`tipo ${type.options[type.selectedIndex].text}`);
            if (period?.value) descriptions.push(`periodo ${period.value}`);
            report.textContent = active ? `Mostrando ${filtered.length} de ${cards.length} proyectos: ${descriptions.join(', ')}.` : `Mostrando tus ${cards.length} proyectos.`;
        }
        const params = new URLSearchParams(window.location.search);
        const values = { q: search?.value.trim() || '', status: status?.value || '', type: type?.value || '', period: period?.value || '', sort: sort?.value === 'activity' ? '' : (sort?.value || '') };
        Object.entries(values).forEach(([key, value]) => value ? params.set(key, value) : params.delete(key));
        window.history.replaceState({}, '', `${window.location.pathname}?${params.toString()}`);
    }
    type?.addEventListener('change', syncStatusWorkflow);
    [search, status, type, period, sort].forEach(control => control?.addEventListener(control === search ? 'input' : 'change', update));
    root.querySelectorAll('[data-project-clear]').forEach(button => button.addEventListener('click', () => { if (search) search.value = ''; if (status) { status.value = ''; status.dispatchEvent(new Event('change')); } if (type) { type.value = ''; type.dispatchEvent(new Event('change')); } if (period) { period.value = ''; period.dispatchEvent(new Event('change')); } update(); search?.focus(); }));
    update();
})();
