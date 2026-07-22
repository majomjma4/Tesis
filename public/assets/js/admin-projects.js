(() => {
    const modal = document.querySelector('#apModal');
    const trash = document.querySelector('#apTrash');
    const form = document.querySelector('#apForm');
    const trashForm = document.querySelector('#apTrashForm');
    const cfg = document.querySelector('#apConfig');
    const request = async (url, data) => {
        const response = await fetch(url, { method: 'POST', body: data, headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        const result = await response.json();
        if (!response.ok || !result.success) throw new Error(result.message);
        return result;
    };
    const open = project => {
        form.reset();
        for (const [key, value] of Object.entries(project || {})) if (form.elements[key]) form.elements[key].value = value ?? '';
        document.querySelector('#apTitle').textContent = project ? 'Editar proyecto' : 'Nuevo proyecto';
        modal.hidden = false;
        form.title.focus();
    };
    document.querySelector('#apNew')?.addEventListener('click', () => open());
    document.querySelectorAll('[data-edit]').forEach(button => button.addEventListener('click', () => open(JSON.parse(button.closest('article').querySelector('script').textContent))));
    document.querySelectorAll('[data-close]').forEach(button => button.addEventListener('click', () => modal.hidden = true));
    form?.addEventListener('submit', async event => {
        event.preventDefault();
        try { await request(cfg.dataset.save, new FormData(form)); location.reload(); }
        catch (error) { const message = document.querySelector('#apMessage'); message.textContent = error.message; message.className = 'ap-message error'; message.hidden = false; }
    });
    document.querySelectorAll('[data-trash]').forEach(button => button.addEventListener('click', () => {
        trashForm.id.value = JSON.parse(button.closest('article').querySelector('script').textContent).id;
        trash.hidden = false;
    }));
    document.querySelector('[data-close-trash]')?.addEventListener('click', () => trash.hidden = true);
    trashForm?.addEventListener('submit', async event => {
        event.preventDefault();
        try { await request(cfg.dataset.trash, new FormData(trashForm)); location.reload(); }
        catch (error) { alert(error.message); }
    });
})();

(() => {
    const filters = document.querySelector('.ap-filters');
    if (!filters) return;
    const search = filters.querySelector('input[name="search"]');
    const clear = filters.querySelector('.ap-search-clear');
    const list = document.querySelector('.ap-list');
    const rows = [...list.querySelectorAll(':scope > article')];
    if (!search) return;
    const fold = value => String(value ?? '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLocaleLowerCase('es');
    const textNodes = row => [...row.querySelectorAll('.ap-main span,.ap-main h2,.ap-main p')];
    const original = new WeakMap();
    rows.forEach(row => textNodes(row).forEach(node => original.set(node, node.textContent)));
    const restore = () => rows.forEach(row => textNodes(row).forEach(node => { node.textContent = original.get(node) ?? node.textContent; }));
    const highlight = (node, terms) => {
        const value = node.textContent;
        const chars = [...value];
        let normalized = '';
        const positions = [];
        chars.forEach((char, index) => {
            const folded = fold(char);
            normalized += folded;
            [...folded].forEach(() => positions.push(index));
        });
        const ranges = [];
        terms.forEach(term => {
            let from = 0;
            while (term && (from = normalized.indexOf(term, from)) !== -1) {
                ranges.push([positions[from], positions[from + term.length - 1] + 1]);
                from += term.length;
            }
        });
        if (!ranges.length) return;
        ranges.sort((a, b) => a[0] - b[0]);
        const merged = ranges.reduce((result, range) => {
            const last = result.at(-1);
            if (last && range[0] <= last[1]) last[1] = Math.max(last[1], range[1]); else result.push(range);
            return result;
        }, []);
        const fragment = document.createDocumentFragment();
        let cursor = 0;
        merged.forEach(([start, end]) => {
            if (start > cursor) fragment.append(document.createTextNode(chars.slice(cursor, start).join('')));
            const mark = document.createElement('mark');
            mark.className = 'ap-search-highlight';
            mark.textContent = chars.slice(start, end).join('');
            fragment.append(mark);
            cursor = end;
        });
        if (cursor < chars.length) fragment.append(document.createTextNode(chars.slice(cursor).join('')));
        node.replaceChildren(fragment);
    };
    let noResults = list.querySelector('.ap-search-empty');
    if (rows.length && !noResults) {
        noResults = document.createElement('div');
        noResults.className = 'ap-empty ap-search-empty';
        noResults.hidden = true;
        noResults.innerHTML = '<i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i><h2>Sin coincidencias</h2><p>Prueba con otro título, código o tutor.</p>';
        list.append(noResults);
    }
    const serverQuery = new URLSearchParams(location.search).get('search') || '';
    let refreshTimer;
    const apply = () => {
        restore();
        const query = fold(search.value).trim();
        const terms = query.split(/\s+/).filter(Boolean);
        let visible = 0;
        rows.forEach(row => {
            const haystack = fold(row.innerText);
            const matches = !query || terms.every(term => haystack.includes(term));
            row.hidden = !matches;
            if (matches) {
                visible++;
                if (query) textNodes(row).forEach(node => highlight(node, terms));
            }
        });
        if (clear) clear.hidden = !search.value;
        if (noResults) noResults.hidden = visible !== 0 || !query;
        if (serverQuery && fold(search.value).trim() !== fold(serverQuery).trim()) {
            clearTimeout(refreshTimer);
            refreshTimer = setTimeout(() => filters.requestSubmit(), 450);
        }
    };
    const typeFilter = filters.querySelector('select[name="type_id"]');
    const statusFilter = filters.querySelector('select[name="status"]');
    const defenseFilter = [...(statusFilter?.options || [])].find(option => option.value === 'defense');
    const syncFilterWorkflow = () => {
        if (!typeFilter || !defenseFilter) return;
        const selectedType = typeFilter.options[typeFilter.selectedIndex];
        const isGeneral = !typeFilter.value;
        const isThesis = /titulación|tesis/i.test(selectedType?.textContent || '');
        defenseFilter.disabled = !isGeneral && !isThesis;
        defenseFilter.hidden = !isGeneral && !isThesis;
        if (defenseFilter.disabled && statusFilter.value === 'defense') statusFilter.value = '';
    };
    typeFilter?.addEventListener('change', () => { syncFilterWorkflow(); filters.requestSubmit(); });
    statusFilter?.addEventListener('change', () => filters.requestSubmit());
    search.addEventListener('input', apply);
    clear?.addEventListener('click', () => {
        search.value = '';
        apply();
        search.focus();
        if (serverQuery) filters.requestSubmit();
    });
    syncFilterWorkflow();
    apply();
})();

(() => {
    const form = document.querySelector('#apForm');
    if (!form) return;
    const type = form.elements.project_type_id;
    const status = form.elements.status;
    const defense = [...status.options].find(option => option.value === 'defense');
    if (!type || !defense) return;
    const syncWorkflow = () => {
        const selectedType = type.options[type.selectedIndex];
        const isThesis = /titulación|tesis/i.test(selectedType?.textContent || '');
        defense.disabled = !isThesis;
        defense.hidden = !isThesis;
        if (!isThesis && status.value === 'defense') {
            status.value = 'approved';
            status.dispatchEvent(new Event('change', { bubbles: true }));
        }
    };
    type.addEventListener('change', syncWorkflow);
    document.querySelectorAll('#apNew,[data-edit]').forEach(button => button.addEventListener('click', () => setTimeout(syncWorkflow)));
    syncWorkflow();
})();

(() => {
    if (!document.querySelector('.ap-connection-notice')) return;
    const empty = document.querySelector('.ap-empty');
    if (!empty) return;
    empty.querySelector('h2').textContent = 'No hay datos para mostrar';
    empty.querySelector('p').textContent = 'La información aparecerá cuando se restablezca la conexión.';
})();
