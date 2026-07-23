(() => {
    const modal = document.querySelector('#apModal');
    const trash = document.querySelector('#apTrash');
    const form = document.querySelector('#apForm');
    const trashForm = document.querySelector('#apTrashForm');
    const cfg = document.querySelector('#apConfig');
    const enhanceQuickSelect = select => {
        const shell = document.createElement('div');
        shell.className = 'ap-quick-dropdown';
        select.parentNode.insertBefore(shell, select);
        shell.append(select);
        select.classList.add('ap-quick-native');
        const trigger = document.createElement('button');
        trigger.type = 'button'; trigger.className = 'ap-quick-trigger';
        trigger.setAttribute('aria-haspopup', 'listbox'); trigger.setAttribute('aria-expanded', 'false');
        trigger.innerHTML = '<span></span><i class="fa-solid fa-chevron-down" aria-hidden="true"></i>';
        const menu = document.createElement('div');
        menu.className = 'ap-quick-menu'; menu.setAttribute('role', 'listbox'); menu.hidden = true;
        const fitMenuToCompleteOptions = () => {
            const available = (window.visualViewport?.height || window.innerHeight) - trigger.getBoundingClientRect().bottom - 15;
            const menuStyle = getComputedStyle(menu);
            const bottomInset = parseFloat(menuStyle.paddingBottom) + parseFloat(menuStyle.borderBottomWidth);
            const heights = [...menu.querySelectorAll('[role="option"]')].slice(0, 4).map(option => Math.ceil(option.offsetTop + option.offsetHeight + bottomInset));
            const fitting = heights.filter(height => height <= available);
            menu.style.maxHeight = `${Math.min(fitting.at(-1) || heights[0] || available, available)}px`;
        };
        const sync = () => {
            trigger.querySelector('span').textContent = select.selectedOptions[0]?.textContent || 'Selecciona una opción';
            trigger.classList.toggle('is-placeholder', !select.value);
            menu.querySelectorAll('[role="option"]').forEach(option => option.setAttribute('aria-selected', String(option.dataset.value === select.value)));
        };
        [...select.options].forEach(option => {
            const item = document.createElement('button'); item.type = 'button'; item.dataset.value = option.value;
            item.setAttribute('role', 'option'); item.innerHTML = '<span></span><i class="fa-solid fa-check" aria-hidden="true"></i>';
            item.querySelector('span').textContent = option.textContent;
            item.addEventListener('click', () => { select.value = option.value; select.dispatchEvent(new Event('change', { bubbles: true })); close(); trigger.focus(); });
            menu.append(item);
        });
        const close = () => { shell.classList.remove('is-open'); menu.hidden = true; trigger.setAttribute('aria-expanded', 'false'); };
        trigger.addEventListener('click', event => {
            event.stopPropagation();
            const willOpen = !shell.classList.contains('is-open');
            document.querySelectorAll('.ap-quick-dropdown.is-open').forEach(item => {
                if (item === shell) return;
                item.classList.remove('is-open');
                item.querySelector('.ap-quick-menu').hidden = true;
                item.querySelector('.ap-quick-trigger')?.setAttribute('aria-expanded', 'false');
            });
            if (willOpen) {
                shell.classList.add('is-open'); menu.hidden = false; trigger.setAttribute('aria-expanded', 'true');
                const fourthOption = [...menu.querySelectorAll('[role="option"]')].slice(0, 4).at(-1);
                const desiredHeight = fourthOption ? fourthOption.offsetTop + fourthOption.offsetHeight + 8 : menu.scrollHeight;
                const available = (window.visualViewport?.height || window.innerHeight) - trigger.getBoundingClientRect().bottom - 15;
                if (available < desiredHeight) trigger.scrollIntoView({ block: 'center', inline: 'nearest', behavior: 'instant' });
                fitMenuToCompleteOptions();
            }
            else close();
        });
        select.addEventListener('change', sync); document.addEventListener('click', event => { if (!shell.contains(event.target)) close(); });
        shell.append(trigger, menu); sync();
    };
    const addQuickTextSelect = (target, label, options) => {
        if (!target || target.previousElementSibling?.classList.contains('ap-quick-text')) return;
        const field = document.createElement('label');
        field.className = 'ap-quick-text';
        field.textContent = label;
        const select = document.createElement('select');
        select.innerHTML = '<option value="">Selecciona una opción</option>';
        options.forEach(([text, value]) => select.add(new Option(text, value)));
        select.addEventListener('change', () => { if (select.value) target.value = select.value; });
        field.append(select);
        target.closest('label')?.before(field);
        enhanceQuickSelect(select);
        return select;
    };
    addQuickTextSelect(form?.elements.subtitle, 'Texto rápido', [
        ['Actualización académica', 'Actualización de información académica del proyecto.'],
        ['Ajuste de seguimiento', 'Ajuste de estado y seguimiento administrativo.'],
        ['Corrección de datos', 'Corrección de datos del expediente institucional.'],
    ]);
    const trashQuickReason = addQuickTextSelect(trashForm?.elements.reason, 'Motivo rápido', [
        ['Proyecto duplicado.', 'Proyecto duplicado. '],
        ['Registro creado por error.', 'Registro creado por error.'],
        ['Proyecto de pruebas', 'Proyecto de pruebas'],
        ['Solicitud de retiro del Proyecto', 'Solicitud de retiro del Proyecto'],
        ['Cancelado por la Coordinación', 'Cancelado por la Coordinación'],
        ['Otro motivo', 'Otro motivo'],
    ]);
    const trashReasonField = trashForm?.elements.reason?.closest('label');
    const trashReasonHint = document.createElement('p');
    trashReasonHint.className = 'ap-quick-hint';
    trashReasonHint.innerHTML = '<i class="fa-solid fa-circle-info" aria-hidden="true"></i> Se registrará este motivo en el historial del proyecto.';
    trashReasonField?.before(trashReasonHint);
    const syncTrashReasonField = () => {
        const isOther = trashQuickReason?.value === 'Otro motivo';
        if (!trashReasonField || !trashForm?.elements.reason) return;
        trashReasonField.hidden = !isOther;
        trashReasonHint.hidden = isOther || !trashQuickReason?.value;
        trashForm.elements.reason.disabled = !isOther;
        trashForm.elements.reason.required = isOther;
        if (isOther) { trashForm.elements.reason.value = ''; requestAnimationFrame(() => trashForm.elements.reason.focus()); }
    };
    trashQuickReason?.addEventListener('change', syncTrashReasonField);
    syncTrashReasonField();
    const selectFirstAvailable = select => {
        const option = [...(select?.options || [])].find(item => item.value);
        if (option) select.value = option.value;
    };
    const typeSelect = form?.elements.project_type_id;
    const careerSelect = form?.elements.career_id;
    const periodSelect = form?.elements.academic_period_id;
    const tutorSelect = form?.elements.tutor_id;
    const statusSelect = form?.elements.status;
    if (typeSelect?.options[0]) typeSelect.options[0].text = 'Seleccionar tipo';
    if (tutorSelect?.options[0]) tutorSelect.options[0].text = 'Seleccionar tutor';
    if (statusSelect && !statusSelect.querySelector('option[value=""]')) statusSelect.add(new Option('Seleccionar estado', ''), 0);
    if (careerSelect) {
        [...careerSelect.options].forEach(option => { if (!option.value || !option.textContent.includes('Desarrollo de Software')) option.remove(); });
        selectFirstAvailable(careerSelect);
    }
    const makeFixedField = (select, label) => {
        const wrapper = select?.closest('.custom-select');
        if (!select || !wrapper || wrapper.parentElement?.querySelector(`[data-fixed-field="${select.name}"]`)) return null;
        const value = document.createElement('span');
        value.className = 'ap-fixed-field'; value.dataset.fixedField = select.name;
        value.setAttribute('aria-label', label);
        wrapper.after(value); wrapper.hidden = true;
        return value;
    };
    const careerField = makeFixedField(careerSelect, 'Carrera seleccionada');
    const periodField = makeFixedField(periodSelect, 'Período académico seleccionado');
    const syncFixedFields = () => {
        if (careerField) careerField.textContent = 'Desarrollo de Software';
        if (periodField) periodField.textContent = periodSelect.selectedOptions[0]?.textContent.trim() || 'Sin período activo';
    };
    const setProjectDefaults = () => {
        selectFirstAvailable(careerSelect);
        selectFirstAvailable(periodSelect);
        typeSelect.value = '';
        tutorSelect.value = '';
        statusSelect.value = '';
        [careerSelect, periodSelect, typeSelect, tutorSelect, statusSelect].forEach(select => select?.dispatchEvent(new Event('change', { bubbles: true })));
    };
    const request = async (url, data) => {
        const response = await fetch(url, { method: 'POST', body: data, headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        const result = await response.json();
        if (!response.ok || !result.success) throw new Error(result.message);
        return result;
    };
    const open = project => {
        document.body.append(modal);
        form.reset();
        for (const [key, value] of Object.entries(project || {})) if (form.elements[key]) form.elements[key].value = value ?? '';
        if (!project) setProjectDefaults();
        else [careerSelect, periodSelect, typeSelect, tutorSelect, statusSelect].forEach(select => select?.dispatchEvent(new Event('change', { bubbles: true })));
        syncFixedFields();
        document.querySelector('#apTitle').textContent = project ? 'Editar proyecto' : 'Nuevo proyecto';
        modal.hidden = false;
        form.title.focus();
    };
    document.querySelector('#apNew')?.addEventListener('click', () => open());
    document.querySelectorAll('[data-edit]').forEach(button => button.addEventListener('click', () => open(JSON.parse(button.closest('article').querySelector('script').textContent))));
    document.querySelectorAll('[data-close]').forEach(button => button.addEventListener('click', () => modal.hidden = true));
    modal?.addEventListener('click', event => { if (event.target === modal) modal.hidden = true; });
    form?.addEventListener('submit', async event => {
        event.preventDefault();
        const data = new FormData(form);
        ['career_id', 'academic_period_id', 'project_type_id', 'tutor_id', 'status'].forEach(name => data.set(name, form.elements[name].value));
        try { await request(cfg.dataset.save, data); location.reload(); }
        catch (error) { const message = document.querySelector('#apMessage'); message.textContent = error.message; message.className = 'ap-message error'; message.hidden = false; }
    });
    document.querySelectorAll('[data-trash]').forEach(button => button.addEventListener('click', () => {
        document.body.append(trash);
        trashForm.reset();
        trashQuickReason?.dispatchEvent(new Event('change', { bubbles: true }));
        syncTrashReasonField();
        trashForm.id.value = JSON.parse(button.closest('article').querySelector('script').textContent).id;
        trash.hidden = false;
    }));
    document.querySelectorAll('[data-close-trash]').forEach(button => button.addEventListener('click', () => trash.hidden = true));
    trash?.addEventListener('click', event => { if (event.target === trash) trash.hidden = true; });
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
    const thesisOnlyFilters = [...(statusFilter?.options || [])].filter(option => ['defense', 'tribunal_approved'].includes(option.value));
    const syncFilterWorkflow = () => {
        if (!typeFilter || !thesisOnlyFilters.length) return;
        const selectedType = typeFilter.options[typeFilter.selectedIndex];
        const isGeneral = !typeFilter.value;
        const isThesis = /titulación|tesis/i.test(selectedType?.textContent || '');
        thesisOnlyFilters.forEach(option => { option.disabled = !isGeneral && !isThesis; option.hidden = !isGeneral && !isThesis; });
        if (!isGeneral && !isThesis && ['defense', 'tribunal_approved'].includes(statusFilter.value)) statusFilter.value = '';
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
    const thesisOnlyStatuses = [...status.options].filter(option => ['defense', 'tribunal_approved'].includes(option.value));
    if (!type || !thesisOnlyStatuses.length) return;
    const syncWorkflow = () => {
        const selectedType = type.options[type.selectedIndex];
        const isThesis = /titulación|tesis/i.test(selectedType?.textContent || '');
        thesisOnlyStatuses.forEach(option => { option.disabled = !isThesis; option.hidden = !isThesis; });
        if (!isThesis && ['defense', 'tribunal_approved'].includes(status.value)) {
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
