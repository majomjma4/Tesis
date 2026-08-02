(() => {
    const modal = document.querySelector('#apModal');
    const trash = document.querySelector('#apTrash');
    const form = document.querySelector('#apForm');
    const trashForm = document.querySelector('#apTrashForm');
    const cfg = document.querySelector('#apConfig');
    const saveConfirm = document.querySelector('#apSaveConfirm');
    const presentationDialog = document.querySelector('#apPresentation');
    const presentationOptions = presentationDialog?.querySelector('[data-presentation-options]');
    const presentationError = presentationDialog?.querySelector('[data-presentation-error]');
    const descriptionDialog = document.querySelector('#apPublicDescription');
    const descriptionField = descriptionDialog?.querySelector('[data-public-description-field]');
    const descriptionError = descriptionDialog?.querySelector('[data-public-description-error]');
    const editWarning = form?.querySelector('[data-edit-warning]');
    const advancedOptions = form?.querySelector('[data-advanced-options]');
    const manageParticipants = form?.querySelector('[data-manage-participants]');
    const manageFiles = form?.querySelector('[data-manage-files]');
    const changeState = form?.querySelector('[data-change-state]');
    const projectSubmit = form?.querySelector('[type="submit"]');
    const keywordSelector = form?.querySelector('[data-project-keyword-selector]');
    const keywordTrigger = form?.querySelector('[data-project-keyword-trigger]');
    const keywordPanel = form?.querySelector('[data-project-keyword-panel]');
    const keywordSearch = form?.querySelector('[data-project-keyword-search]');
    const keywordOptions = form?.querySelector('[data-project-keyword-options]');
    const keywordSummary = form?.querySelector('[data-project-keyword-summary]');
    const keywordLimit = form?.querySelector('[data-project-keyword-limit]');
    const keywordChips = form?.querySelector('[data-project-keyword-chips]');
    const dialogLayers = [modal, trash, saveConfirm, presentationDialog, descriptionDialog].filter(Boolean);
    dialogLayers.forEach(layer => { layer.hidden = true; });
    dialogLayers.forEach(layer => document.body.append(layer));
    const syncProjectDialogs = () => document.body.classList.toggle('project-dialog-open', dialogLayers.some(layer => !layer.hidden));
    const showProjectDialog = layer => {
        if (!layer) return;
        dialogLayers.forEach(candidate => {
            candidate.hidden = candidate !== layer;
        });
        document.body.append(layer);
        layer.hidden = false;
        syncProjectDialogs();
    };
    new MutationObserver(syncProjectDialogs).observe(document.body, { subtree: true, attributes: true, attributeFilter: ['hidden'] });
    syncProjectDialogs();
    let pendingSaveData = null;
    let activeProject = null;
    let initialFormState = '';
    let projectIsSaving = false;
    const selectedKeywordState = () => [...(keywordOptions?.querySelectorAll('input:checked') || [])].map(input => input.value.normalize('NFC')).sort((a, b) => a.localeCompare(b, 'es'));
    const editableState = () => JSON.stringify([...['title', 'subtitle', 'tutor_id'].map(name => form?.elements[name]?.value ?? ''), form?.querySelector('[data-project-summary]')?.value ?? '', selectedKeywordState()]);
    const syncChangeState = () => {
        const dirty = Boolean(initialFormState) && editableState() !== initialFormState;
        if (changeState) changeState.hidden = !dirty;
        if (projectSubmit) {
            projectSubmit.disabled = !dirty || projectIsSaving;
            projectSubmit.setAttribute('aria-disabled', String(projectSubmit.disabled));
        }
        return dirty;
    };
    form?.addEventListener('input', syncChangeState);
    form?.addEventListener('change', syncChangeState);
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
        document.addEventListener('app:dropdown-open', event => { if (event.detail?.trigger !== trigger) close(); });
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
                document.dispatchEvent(new CustomEvent('app:dropdown-open', { detail: { trigger } }));
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
    if (tutorSelect?.options[0]) tutorSelect.options[0].text = 'Seleccionar tutor';
    if (statusSelect && !statusSelect.querySelector('option[value=""]')) statusSelect.add(new Option('Seleccionar estado', ''), 0);
    const thesisOnlyStatuses = [...(statusSelect?.options || [])].filter(option => ['defense', 'tribunal_approved'].includes(option.value));
    if (careerSelect) {
        [...careerSelect.options].forEach(option => { if (!option.value || !option.textContent.includes('Desarrollo de Software')) option.remove(); });
        selectFirstAvailable(careerSelect);
    }
    const makeFixedField = (select, label, icon) => {
        const wrapper = select?.closest('.custom-select');
        if (!select || !wrapper) return null;
        const existing = wrapper.parentElement?.querySelector(`[data-fixed-field="${select.name}"]`);
        if (existing) { wrapper.hidden = true; return existing; }
        const value = document.createElement('span');
        value.className = 'ap-fixed-field'; value.dataset.fixedField = select.name;
        value.setAttribute('aria-label', label);
        value.innerHTML = `<i class="${icon}" aria-hidden="true"></i><span><small>${label}</small><strong></strong></span>`;
        [...wrapper.parentElement.childNodes].filter(node => node.nodeType === Node.TEXT_NODE).forEach(node => { node.textContent = ''; });
        wrapper.after(value); wrapper.hidden = true;
        return value;
    };
    const careerField = makeFixedField(careerSelect, 'Carrera', 'fa-solid fa-code');
    const periodField = makeFixedField(periodSelect, 'Período académico', 'fa-regular fa-calendar');
    const syncFixedFields = () => {
        if (careerField) careerField.querySelector('strong').textContent = 'Desarrollo de Software';
        if (periodField) periodField.querySelector('strong').textContent = periodSelect.selectedOptions[0]?.textContent.trim() || 'Sin período activo';
    };
    const syncProjectStatusOptions = () => {
        const typeCode = String(activeProject?.type_code || activeProject?.type_key || '').toLowerCase();
        const isThesis = typeCode === 'thesis' || /titulación|tesis/i.test(String(activeProject?.type_name || activeProject?.type || ''));
        thesisOnlyStatuses.forEach(option => {
            option.disabled = !isThesis;
            option.hidden = !isThesis;
        });
    };
    const setProjectDefaults = () => {
        selectFirstAvailable(careerSelect);
        selectFirstAvailable(periodSelect);
        typeSelect.value = '';
        tutorSelect.value = '';
        statusSelect.value = '';
        [careerSelect, periodSelect, tutorSelect, statusSelect].forEach(select => select?.dispatchEvent(new Event('change', { bubbles: true })));
    };
    const request = async (url, data) => {
        const response = await fetch(url, { method: 'POST', body: data, headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        const result = await response.json();
        if (!response.ok || !result.success) throw new Error(result.message);
        return result;
    };
    const renderTribunal = project => {
        const section = form?.querySelector('[data-project-tribunal]');
        const content = form?.querySelector('[data-project-tribunal-content]');
        if (!section || !content) return;
        const isDegreeProject = String(project?.type_code || project?.project_type_code || '').toLowerCase() === 'thesis';
        section.hidden = !isDegreeProject;
        content.replaceChildren();
        if (!isDegreeProject) return;
        const members = (Array.isArray(project?.participants) ? project.participants : []).filter(person =>
            ['tribunal', 'jury'].includes(String(person?.role_code || '').toLowerCase()) && Boolean(Number(person?.is_teacher))
        );
        if (!members.length) {
            const empty = document.createElement('p');
            empty.className = 'ap-tribunal-empty';
            empty.innerHTML = '<i class="fa-solid fa-user-group" aria-hidden="true"></i><span>El proyecto aún no tiene un tribunal asignado.</span>';
            content.append(empty);
            return;
        }
        const list = document.createElement('div');
        list.className = 'ap-tribunal-list';
        members.forEach((person, index) => {
            const name = String(person.full_name || '').trim();
            const username = String(person.username || '').trim();
            const email = String(person.email || '').trim();
            const initials = name.split(/\s+/).filter(Boolean).slice(0, 2).map(part => part[0]).join('').toUpperCase() || 'U';
            const row = document.createElement('article');
            row.className = 'ap-tribunal-person';
            const main = document.createElement(email ? 'button' : 'div');
            main.className = 'ap-tribunal-person-main';
            if (email) {
                const contactId = `ap-tribunal-contact-${project.id}-${person.user_id || index}`;
                main.type = 'button';
                main.setAttribute('aria-expanded', 'false');
                main.setAttribute('aria-controls', contactId);
                main.dataset.tribunalContactToggle = '';
                const contact = document.createElement('div');
                contact.className = 'ap-tribunal-contact';
                contact.id = contactId;
                contact.hidden = true;
                const contactLabel = document.createElement('small');
                contactLabel.textContent = 'Correo institucional';
                const contactLink = document.createElement('a');
                contactLink.href = `mailto:${email}`;
                contactLink.textContent = email;
                contact.append(contactLabel, contactLink);
                row.append(main, contact);
            } else row.append(main);
            const avatar = document.createElement('span');
            avatar.className = 'ap-tribunal-avatar';
            avatar.setAttribute('aria-hidden', 'true');
            if (person.avatar_url) {
                const image = document.createElement('img');
                image.src = person.avatar_url;
                image.alt = '';
                avatar.append(image);
            } else avatar.textContent = initials;
            const identity = document.createElement('span');
            identity.className = 'ap-tribunal-identity';
            const primary = document.createElement('strong');
            primary.textContent = username ? `@${username}` : name;
            identity.append(primary);
            if (username) {
                const secondary = document.createElement('span');
                secondary.textContent = name;
                identity.append(secondary);
            }
            const role = document.createElement('small');
            role.textContent = 'Miembro del tribunal';
            identity.append(role);
            main.append(avatar, identity);
            if (email) {
                const chevron = document.createElement('i');
                chevron.className = 'fa-solid fa-chevron-down';
                chevron.setAttribute('aria-hidden', 'true');
                main.append(chevron);
            }
            list.append(row);
        });
        content.append(list);
    };
    const closeKeywordSelector = (restoreFocus = false) => {
        if (!keywordPanel || !keywordTrigger) return;
        keywordPanel.hidden = true;
        keywordSelector?.classList.remove('is-open');
        keywordTrigger.setAttribute('aria-expanded', 'false');
        if (restoreFocus) keywordTrigger.focus();
    };
    const openKeywordSelector = () => {
        if (!keywordPanel || !keywordTrigger) return;
        keywordPanel.hidden = false;
        keywordSelector?.classList.add('is-open');
        keywordTrigger.setAttribute('aria-expanded', 'true');
        keywordSearch?.focus();
    };
    const renderKeywordSelection = () => {
        const inputs = [...(keywordOptions?.querySelectorAll('input') || [])];
        const selected = inputs.filter(input => input.checked);
        const atLimit = selected.length >= 4;
        inputs.forEach(input => {
            input.disabled = atLimit && !input.checked;
            input.closest('[role="option"]')?.setAttribute('aria-selected', String(input.checked));
        });
        if (keywordSummary) keywordSummary.textContent = selected.length ? `${selected.length} ${selected.length === 1 ? 'etiqueta seleccionada' : 'etiquetas seleccionadas'}` : 'Selecciona etiquetas de clasificación';
        if (keywordLimit) keywordLimit.hidden = !atLimit;
        if (keywordChips) keywordChips.replaceChildren(...selected.map(input => {
            const chip = document.createElement('button');
            chip.type = 'button';
            chip.className = 'ap-keyword-chip';
            chip.setAttribute('aria-label', `Quitar ${input.value}`);
            const label = document.createElement('span');
            label.textContent = input.value;
            const icon = document.createElement('i');
            icon.className = 'fa-solid fa-xmark';
            icon.setAttribute('aria-hidden', 'true');
            chip.append(label, icon);
            chip.addEventListener('click', () => {
                input.checked = false;
                renderKeywordSelection();
                syncChangeState();
                keywordTrigger?.focus();
            });
            return chip;
        }));
    };
    const loadKeywordSelector = project => {
        if (!keywordOptions) return;
        const catalogNode = form.querySelector('[data-project-keyword-catalog]');
        let catalog = [];
        try { catalog = JSON.parse(catalogNode?.textContent || '[]'); } catch { catalog = []; }
        const current = (Array.isArray(project?.keywords) ? project.keywords : Array.isArray(project?.tags) ? project.tags : []).map(item => typeof item === 'string' ? item : item?.name).filter(Boolean);
        const names = [...catalog.map(String), ...current].filter((name, index, values) => values.findIndex(value => value.localeCompare(name, 'es', { sensitivity: 'base' }) === 0) === index);
        keywordOptions.replaceChildren(...names.map(name => {
            const row = document.createElement('label');
            row.className = 'ap-keyword-option';
            row.setAttribute('role', 'option');
            row.dataset.keywordSearch = name.normalize('NFD').replace(/\p{Mn}+/gu, '').toLocaleLowerCase('es');
            const input = document.createElement('input');
            input.type = 'checkbox';
            input.name = 'project_keywords[]';
            input.value = name;
            input.checked = current.some(item => item.localeCompare(name, 'es', { sensitivity: 'base' }) === 0);
            const text = document.createElement('span');
            text.textContent = name;
            row.append(input, text);
            input.addEventListener('change', () => { renderKeywordSelection(); syncChangeState(); });
            input.addEventListener('keydown', event => {
                if (event.key === 'Escape') { event.preventDefault(); closeKeywordSelector(true); return; }
                if (!['ArrowDown', 'ArrowUp', 'Home', 'End'].includes(event.key)) return;
                const visible = [...keywordOptions.querySelectorAll('input:not(:disabled)')].filter(option => !option.closest('[role="option"]')?.hidden);
                if (!visible.length) return;
                event.preventDefault();
                const index = visible.indexOf(input);
                const next = event.key === 'Home' ? 0 : event.key === 'End' ? visible.length - 1 : event.key === 'ArrowUp' ? Math.max(0, index - 1) : Math.min(visible.length - 1, index + 1);
                visible[next]?.focus();
            });
            return row;
        }));
        if (keywordSearch) keywordSearch.value = '';
        closeKeywordSelector();
        renderKeywordSelection();
    };
    keywordTrigger?.addEventListener('click', () => keywordPanel?.hidden ? openKeywordSelector() : closeKeywordSelector(true));
    keywordTrigger?.addEventListener('keydown', event => {
        if (!['ArrowDown', 'Enter', ' '].includes(event.key)) return;
        event.preventDefault();
        openKeywordSelector();
    });
    keywordSearch?.addEventListener('input', () => {
        const query = keywordSearch.value.normalize('NFD').replace(/\p{Mn}+/gu, '').trim().toLocaleLowerCase('es');
        keywordOptions?.querySelectorAll('[data-keyword-search]').forEach(row => { row.hidden = query !== '' && !row.dataset.keywordSearch.includes(query); });
    });
    keywordSearch?.addEventListener('keydown', event => {
        if (!['ArrowDown', 'End'].includes(event.key)) return;
        const visible = [...(keywordOptions?.querySelectorAll('input:not(:disabled)') || [])].filter(option => !option.closest('[role="option"]')?.hidden);
        if (!visible.length) return;
        event.preventDefault();
        (event.key === 'End' ? visible.at(-1) : visible[0])?.focus();
    });
    keywordSelector?.addEventListener('focusout', event => { if (!keywordSelector.contains(event.relatedTarget)) closeKeywordSelector(); });
    document.addEventListener('click', event => { if (keywordSelector && !keywordSelector.contains(event.target)) closeKeywordSelector(); });
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && keywordPanel && !keywordPanel.hidden) {
            event.preventDefault();
            closeKeywordSelector(true);
        }
    });
    form?.addEventListener('click', event => {
        const trigger = event.target.closest('[data-tribunal-contact-toggle]');
        if (!trigger || !form.contains(trigger)) return;
        const panel = document.getElementById(trigger.getAttribute('aria-controls'));
        const expanded = trigger.getAttribute('aria-expanded') === 'true';
        trigger.setAttribute('aria-expanded', String(!expanded));
        if (panel) panel.hidden = expanded;
    });
    const open = project => {
        activeProject = project || null;
        pendingSaveData = null;
        document.body.dispatchEvent(new MouseEvent('click', { bubbles: true }));
        form.reset();
        thesisOnlyStatuses.forEach(option => {
            option.disabled = false;
            option.hidden = false;
        });
        const formMessage = document.querySelector('#apMessage');
        formMessage.hidden = true;
        formMessage.textContent = '';
        form.querySelector('[type="submit"]').disabled = false;
        for (const [key, value] of Object.entries(project || {})) if (form.elements[key]) form.elements[key].value = value ?? '';
        if (!project) setProjectDefaults();
        else [careerSelect, periodSelect, tutorSelect].forEach(select => select?.dispatchEvent(new Event('change', { bubbles: true })));
        syncProjectStatusOptions();
        syncFixedFields();
        document.querySelector('#apTitle').textContent = project ? 'Editar' : 'Nuevo proyecto';
        const value = (...keys) => keys.map(key => project?.[key]).find(item => item !== undefined && item !== null && String(item).trim() !== '');
        const readableDate = raw => {
            if (!raw) return '';
            const parsed = new Date(String(raw).replace(' ', 'T') + (String(raw).includes('T') ? '' : 'Z'));
            return Number.isNaN(parsed.getTime()) ? String(raw) : new Intl.DateTimeFormat('es-EC', { dateStyle: 'medium' }).format(parsed);
        };
        const setText = (selector, text, fallback) => {
            const target = form.querySelector(selector);
            if (target) target.textContent = text || fallback;
        };
        const summary = form.querySelector('[data-project-summary]');
        if (summary) summary.value = value('summary', 'description', 'full_description') || '';
        setText('[data-project-type]', value('type_name', 'type'), 'Sin información');
        setText('[data-project-research-line]', value('research_line_name', 'research_line', 'line_name'), 'Sin información registrada');
        setText('[data-project-status]', statusSelect?.selectedOptions[0]?.textContent, 'Sin información');
        setText('[data-project-stage]', value('stage_label', 'current_stage_label', 'current_stage', 'stage'), 'Sin información registrada');
        setText('[data-project-code]', value('code'), 'Sin información');
        setText('[data-project-published]', readableDate(value('published_at', 'repository_published_at')), 'Sin publicar');
        setText('[data-project-updated]', readableDate(value('updated_at')), 'Sin información');
        const tags = form.querySelector('[data-project-tags]');
        if (tags) {
            const values = (Array.isArray(project?.tags) ? project.tags : Array.isArray(project?.keywords) ? project.keywords : []).slice(0, 4);
            tags.replaceChildren(...(values.length ? values.map(tag => Object.assign(document.createElement('span'), { textContent: typeof tag === 'string' ? tag : tag.name })) : [Object.assign(document.createElement('span'), { textContent: 'Sin etiquetas registradas' })]));
        }
        loadKeywordSelector(project);
        renderTribunal(project);
        if (editWarning) editWarning.hidden = !project;
        if (advancedOptions) {
            advancedOptions.hidden = !project;
            advancedOptions.open = false;
        }
        if (project) {
            const projectUrl = tab => {
                const url = new URL(location.href);
                url.search = '';
                url.searchParams.set('page', 'project-detail');
                url.searchParams.set('id', project.id);
                url.searchParams.set('tab', tab);
                return url.toString();
            };
            if (manageParticipants) manageParticipants.href = projectUrl('information');
            if (manageFiles) manageFiles.href = projectUrl('documents');
        }
        showProjectDialog(modal);
        initialFormState = editableState();
        syncChangeState();
        form.title.focus();
    };
    window.AdminProjectEditor = { open };
    document.querySelector('#apNew')?.addEventListener('click', () => open());
    document.querySelectorAll('[data-edit]').forEach(button => button.addEventListener('click', () => open(JSON.parse(button.closest('article').querySelector('script').textContent))));
    const requestedProjectEdit = new URLSearchParams(location.search).get('edit');
    if (requestedProjectEdit) {
        const requestedCard = [...document.querySelectorAll('.ap-list article')].find(card => {
            const data = card.querySelector('script');
            if (!data) return false;
            try { return String(JSON.parse(data.textContent).id) === requestedProjectEdit; }
            catch { return false; }
        });
        requestedCard?.querySelector('[data-edit]')?.click();
    }
    document.querySelectorAll('[data-close]').forEach(button => button.addEventListener('click', () => modal.hidden = true));
    modal?.addEventListener('click', event => { if (event.target === modal) modal.hidden = true; });
    const saveProject = async data => {
        projectIsSaving = true;
        syncChangeState();
        try {
            await request(cfg.dataset.save, data);
            saveConfirm.hidden = true;
            modal.hidden = true;
            const destination = new URL(location.href);
            destination.searchParams.delete('edit');
            location.replace(destination.toString());
        }
        catch (error) {
            const message = document.querySelector('#apMessage');
            message.textContent = error.message;
            message.className = 'ap-message error';
            message.hidden = false;
            if (modal.hidden) showProjectDialog(modal);
        }
        finally {
            projectIsSaving = false;
            syncChangeState();
        }
    };
    const openPresentationDialog = data => {
        const files = Array.isArray(activeProject?.presentation_files) ? activeProject.presentation_files : [];
        pendingSaveData = data;
        presentationOptions.replaceChildren();
        presentationError.hidden = true;
        files.forEach(file => {
            const option = document.createElement('label');
            option.className = 'ap-presentation-option';
            const input = document.createElement('input');
            input.type = 'radio';
            input.name = 'project_presentation_file';
            input.value = String(file.id);
            input.checked = files.length === 1 || String(file.id) === String(activeProject?.presentation_file_id || '');
            const icon = document.createElement('i');
            icon.className = file.icon || 'fa-regular fa-file';
            icon.setAttribute('aria-hidden', 'true');
            const copy = document.createElement('span');
            const name = document.createElement('strong');
            name.textContent = file.name || 'Archivo';
            const meta = document.createElement('small');
            meta.textContent = [file.format, file.size].filter(Boolean).join(' · ');
            copy.append(name, meta);
            option.append(input, icon, copy);
            presentationOptions.append(option);
        });
        presentationDialog.querySelector('h2').textContent = 'Seleccionar archivo de presentación (opcional)';
        presentationDialog.querySelector('.ap-presentation-copy').textContent = 'Puedes elegir el archivo que se mostrará automáticamente cuando una persona ingrese al Expediente Digital. Esta elección es opcional y no afecta la importancia de los demás documentos.';
        presentationDialog.querySelectorAll('[data-cancel-presentation]').forEach(button => {
            if (!button.getAttribute('aria-label')) button.textContent = 'Omitir';
        });
        presentationDialog.querySelector('[data-confirm-presentation]').textContent = 'Continuar';
        showProjectDialog(presentationDialog);
        (presentationOptions.querySelector('input:checked') || presentationDialog.querySelector('[data-cancel-presentation]'))?.focus();
    };
    const continuePublication = data => {
        data.set('publication_intent', '1');
        openPresentationDialog(data);
    };
    const preparePublicDescription = async data => {
        const probe = new FormData();
        probe.set('_csrf', data.get('_csrf'));
        probe.set('id', data.get('id'));
        probe.set('action', 'prepare_public_description');
        const result = await request(cfg.dataset.save, probe);
        if (!result.data.required) { continuePublication(data); return; }
        pendingSaveData = data;
        descriptionField.value = result.data.proposal || '';
        descriptionDialog.querySelector('[data-public-description-copy]').textContent = result.data.message;
        descriptionDialog.querySelector('[data-public-description-origin]').textContent = result.data.origin === 'institutional' ? 'Origen: texto institucional' : 'Origen: requiere redacción manual';
        descriptionDialog.querySelector('[data-public-description-count]').textContent = String(descriptionField.value.length);
        descriptionError.hidden = true;
        descriptionDialog.dataset.origin = result.data.origin || 'unavailable';
        showProjectDialog(descriptionDialog);
        descriptionField.focus();
    };
    form?.addEventListener('submit', async event => {
        event.preventDefault();
        if (!syncChangeState()) return;
        const data = new FormData(form);
        ['career_id', 'academic_period_id', 'project_type_id', 'tutor_id', 'status'].forEach(name => data.set(name, form.elements[name].value));
        const publishingFirstTime = data.get('status') === 'published' && activeProject?.status !== 'published';
        if (publishingFirstTime) {
            try { await preparePublicDescription(data); }
            catch (error) {
                const message = document.querySelector('#apMessage');
                message.textContent = error.message;
                message.className = 'ap-message error';
                message.hidden = false;
                showProjectDialog(modal);
            }
            return;
        }
        if (!form.elements.id.value) { saveProject(data); return; }
        pendingSaveData = data;
        showProjectDialog(saveConfirm);
        saveConfirm.querySelector('[data-confirm-save]')?.focus();
    });
    saveConfirm?.querySelector('[data-cancel-save]')?.addEventListener('click', () => {
        pendingSaveData = null;
        showProjectDialog(modal);
        form.querySelector('[type="submit"]')?.focus();
    });
    saveConfirm?.querySelector('[data-confirm-save]')?.addEventListener('click', () => {
        if (!pendingSaveData) return;
        const data = pendingSaveData; pendingSaveData = null; saveConfirm.hidden = true; saveProject(data);
    });
    saveConfirm?.addEventListener('click', event => { if (event.target === saveConfirm) saveConfirm.querySelector('[data-cancel-save]')?.click(); });
    document.addEventListener('keydown', event => { if (event.key === 'Escape' && !saveConfirm?.hidden) saveConfirm.querySelector('[data-cancel-save]')?.click(); });
    const cancelPresentation = () => {
        const data = pendingSaveData;
        presentationDialog.hidden = true;
        pendingSaveData = null;
        if (data) saveProject(data);
    };
    presentationDialog?.querySelectorAll('[data-cancel-presentation]').forEach(button => button.addEventListener('click', cancelPresentation));
    presentationDialog?.querySelector('[data-confirm-presentation]')?.addEventListener('click', () => {
        const selected = presentationOptions.querySelector('input:checked');
        if (!pendingSaveData) return;
        if (selected) pendingSaveData.set('presentation_file_id', selected.value);
        const data = pendingSaveData;
        pendingSaveData = null;
        presentationDialog.hidden = true;
        saveProject(data);
    });
    presentationDialog?.addEventListener('click', event => { if (event.target === presentationDialog) cancelPresentation(); });
    document.addEventListener('keydown', event => { if (event.key === 'Escape' && !presentationDialog?.hidden) cancelPresentation(); });
    descriptionField?.addEventListener('input', () => {
        descriptionDialog.querySelector('[data-public-description-count]').textContent = String(descriptionField.value.length);
        descriptionError.hidden = true;
    });
    const cancelDescription = () => {
        pendingSaveData = null;
        showProjectDialog(modal);
        form.querySelector('[type="submit"]')?.focus();
    };
    descriptionDialog?.querySelectorAll('[data-cancel-public-description]').forEach(button => button.addEventListener('click', cancelDescription));
    descriptionDialog?.querySelector('[data-confirm-public-description]')?.addEventListener('click', () => {
        const value = descriptionField.value.replace(/\s+/g, ' ').trim();
        if (value.length < 30) {
            descriptionError.textContent = value ? 'La descripción es demasiado breve para presentar el proyecto.' : 'Escribe una descripción antes de publicar.';
            descriptionError.hidden = false;
            descriptionField.focus();
            return;
        }
        if (!pendingSaveData) return;
        const data = pendingSaveData;
        pendingSaveData = null;
        data.set('public_description', value);
        data.set('description_origin', descriptionDialog.dataset.origin || 'administrator');
        continuePublication(data);
    });
    descriptionDialog?.addEventListener('click', event => { if (event.target === descriptionDialog) cancelDescription(); });
    document.addEventListener('keydown', event => { if (event.key === 'Escape' && !descriptionDialog?.hidden) cancelDescription(); });
    document.querySelectorAll('[data-trash]').forEach(button => button.addEventListener('click', () => {
        trashForm.reset();
        trashQuickReason?.dispatchEvent(new Event('change', { bubbles: true }));
        syncTrashReasonField();
        trashForm.id.value = JSON.parse(button.closest('article').querySelector('script').textContent).id;
        showProjectDialog(trash);
    }));
    document.querySelectorAll('[data-close-trash]').forEach(button => button.addEventListener('click', () => trash.hidden = true));
    trash?.addEventListener('click', event => { if (event.target === trash) trash.hidden = true; });
    trashForm?.addEventListener('submit', async event => {
        event.preventDefault();
        const data = new FormData(trashForm);
        const customReason = trashForm.elements.reason?.value.trim() || '';
        const selectedReason = trashQuickReason?.value?.trim() || '';
        data.set('reason', selectedReason === 'Otro motivo' ? customReason : selectedReason);
        try { await request(cfg.dataset.trash, data); location.reload(); }
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
        if (fold(search.value).trim() !== fold(serverQuery).trim()) {
            clearTimeout(refreshTimer);
            refreshTimer = setTimeout(() => filters.requestSubmit(), 450);
        }
    };
    const typeFilter = filters.querySelector('select[name="type_id"]');
    const statusFilter = filters.querySelector('select[name="status"]');
    const periodFilter = filters.querySelector('select[name="period_id"]');
    filters.addEventListener('submit', () => {
        if (periodFilter && !periodFilter.value) periodFilter.disabled = true;
    });
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
    periodFilter?.addEventListener('change', () => filters.requestSubmit());
    search.addEventListener('input', apply);
    clear?.addEventListener('click', () => {
        search.value = '';
        apply();
        search.focus();
        if (fold(serverQuery).trim() !== '') filters.requestSubmit();
    });
    syncFilterWorkflow();
    apply();
})();

(() => {
    if (!document.querySelector('.ap-connection-notice')) return;
    const empty = document.querySelector('.ap-empty');
    if (!empty) return;
    empty.querySelector('h2').textContent = 'No hay datos para mostrar';
    empty.querySelector('p').textContent = 'La información aparecerá cuando se restablezca la conexión.';
})();
