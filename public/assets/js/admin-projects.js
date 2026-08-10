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
    const tutoring = form?.querySelector('[data-project-tutoring]');
    const tutoringList = form?.querySelector('[data-tutoring-list]');
    const tutoringEditor = form?.querySelector('[data-tutoring-editor]');
    const tutoringAdd = form?.querySelector('[data-tutoring-add]');
    const tutoringNote = form?.querySelector('[data-tutoring-note]');
    const authorCatalogNode = document.querySelector('#apAuthorsCatalog');
    const authors = (() => {
        if (!tutoring || !form) return null;
        const section = document.createElement('div');
        section.className = 'ap-tutoring';
        section.dataset.projectAuthors = '';
        section.innerHTML = '<div class="ap-tutoring-heading"><i class="fa-solid fa-users" aria-hidden="true"></i><div><small>Autores / Integrantes</small><strong>Estudiantes autores del proyecto</strong></div></div><aside class="ap-tutoring-warning"><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i><p>Los cambios en los autores se registrarán en el historial administrativo.<small>El proyecto debe conservar al menos un autor.</small></p></aside><div class="ap-tutoring-list" data-authors-list></div><div class="ap-tutoring-editor" data-authors-editor hidden></div><button class="ap-tutoring-add" type="button" data-authors-add><i class="fa-solid fa-plus" aria-hidden="true"></i>Añadir autor</button><p class="ap-tutoring-note" role="status" aria-live="polite" data-authors-note hidden></p>';
        tutoring.after(section);
        return section;
    })();
    const authorsList = authors?.querySelector('[data-authors-list]');
    const authorsEditor = authors?.querySelector('[data-authors-editor]');
    const authorsAdd = authors?.querySelector('[data-authors-add]');
    const authorsNote = authors?.querySelector('[data-authors-note]');
    const statusActions = form?.querySelector('[data-project-status-actions]');
    const statusComplete = form?.querySelector('[data-project-status-complete]');
    const statusDialog = document.querySelector('[data-project-status-dialog]');
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
    let initialTutoringState = '';
    let projectIsSaving = false;
    let temporaryTutors = [];
    let initialPrimaryTutorId = '';
    let tutoringEditorAbort = null;
    let temporaryAuthors = [];
    let authorsEditorAbort = null;
    const tutoringState = () => JSON.stringify(temporaryTutors.map(tutor => [String(tutor.user_id), tutor.is_primary ? 'principal' : 'additional'])
        .sort((a, b) => a[0].localeCompare(b[0]) || a[1].localeCompare(b[1])));
    const authorsState = () => JSON.stringify(temporaryAuthors.map(author => [String(author.user_id), author.is_leader ? 'leader' : 'member'])
        .sort((a, b) => a[0].localeCompare(b[0]) || a[1].localeCompare(b[1])));
    const selectedKeywordState = () => [...(keywordOptions?.querySelectorAll('input:checked') || [])].map(input => input.value.normalize('NFC')).sort((a, b) => a.localeCompare(b, 'es'));
    const normalizeComparableText = value => String(value ?? '')
        .normalize('NFC')
        .replace(/\r\n?/g, '\n')
        .split('\n')
        .map(line => line.trim().replace(/[^\S\n]+/gu, ' '))
        .join('\n')
        .trim();
    const editableState = () => JSON.stringify([
        normalizeComparableText(form?.elements.title?.value),
        form?.elements.subtitle?.value ?? '',
        tutoringState(),
        authorsState(),
        normalizeComparableText(form?.querySelector('[data-project-summary]')?.value),
        selectedKeywordState(),
    ]);
    const syncChangeState = () => {
        const dirty = Boolean(initialFormState) && editableState() !== initialFormState;
        if (changeState) changeState.hidden = !dirty;
        statusActions?.querySelectorAll('[data-project-status-transition]').forEach(button => {
            button.disabled = dirty;
            button.setAttribute('aria-disabled', String(dirty));
            button.title = dirty ? 'Guarda o descarta los cambios generales antes de modificar el estado.' : '';
        });
        if (projectSubmit) {
            projectSubmit.disabled = !dirty || projectIsSaving;
            projectSubmit.setAttribute('aria-disabled', String(projectSubmit.disabled));
        }
        return dirty;
    };
    const renderStatusTransitions = project => {
        if (!statusActions || !statusComplete) return;
        const allowed = Boolean(project?.capabilities?.change_status || project?.capabilities?.request_corrections || project?.capabilities?.manage_publication);
        const transitions = allowed && Array.isArray(project?.status_actions)
            ? project.status_actions
            : (allowed && Array.isArray(project?.status_transitions) ? project.status_transitions : []);
        statusActions.replaceChildren(...transitions.map(transition => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'ap-project-status-action';
            button.classList.toggle('is-exceptional', transition.action === 'revert_publication');
            button.dataset.projectStatusTransition = JSON.stringify(transition);
            button.setAttribute('aria-controls', 'projectStatusTransitionDialog');
            const icon = document.createElement('i');
            icon.className = `fa-solid ${transition.icon || 'fa-arrow-right'}`;
            icon.setAttribute('aria-hidden', 'true');
            const label = document.createElement('span');
            label.textContent = transition.label || 'Cambiar estado';
            button.append(icon, label);
            return button;
        }));
        statusActions.hidden = transitions.length === 0;
        const published = String(project?.status || '') === 'published';
        statusComplete.textContent = project?.publication_reversion?.message || 'El proyecto ha finalizado su flujo académico.';
        statusComplete.hidden = !published;
        if (statusDialog) {
            statusDialog.dataset.projectId = String(project?.id || '');
            statusDialog.dataset.currentStatus = String(project?.status || '');
        }
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
    const setProjectDefaults = () => {
        selectFirstAvailable(careerSelect);
        selectFirstAvailable(periodSelect);
        typeSelect.value = '';
        tutorSelect.value = '';
        statusSelect.value = 'development';
        [careerSelect, periodSelect, tutorSelect, statusSelect].forEach(select => select?.dispatchEvent(new Event('change', { bubbles: true })));
    };
    const request = async (url, data) => {
        const response = await fetch(url, { method: 'POST', body: data, headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        const result = await response.json();
        if (!response.ok || !result.success) throw new Error(result.message);
        return result;
    };
    const tutoringCatalog = (() => {
        try { return JSON.parse(form?.querySelector('[data-tutoring-catalog]')?.textContent || '[]'); }
        catch { return []; }
    })();
    const tutoringRoles = new Set(['tutor', 'co_tutor', 'cotutor', 'co-tutor']);
    const tutorInitials = person => String(person?.full_name || person?.username || '?').trim().split(/\s+/).slice(0, 2).map(part => part[0]).join('').toUpperCase();
    const normalizeTutor = (person, primary = false) => ({
        user_id: String(person?.user_id ?? person?.id ?? ''),
        username: String(person?.username || ''),
        full_name: String(person?.full_name || person?.name || ''),
        email: String(person?.email || ''),
        is_primary: primary,
    });
    const loadTutoring = project => {
        const principalId = String(project?.tutor_id ?? project?.tutor_user_id ?? '');
        initialPrimaryTutorId = principalId;
        const candidates = (Array.isArray(project?.participants) ? project.participants : [])
            .filter(person => tutoringRoles.has(String(person?.role_code || '').toLowerCase()) && person?.is_teacher !== false && Number(person?.is_teacher ?? 1) !== 0);
        if (principalId && !candidates.some(person => String(person?.user_id) === principalId)) {
            candidates.unshift({ user_id: principalId, username: project?.tutor_username, full_name: project?.tutor_name, email: project?.tutor_email });
        }
        const seen = new Set();
        temporaryTutors = candidates.map(person => normalizeTutor(person, String(person?.user_id) === principalId)).filter(person => {
            if (!person.user_id || seen.has(person.user_id)) return false;
            seen.add(person.user_id);
            return true;
        });
        renderTutoring();
    };
    const closeTutoringEditor = () => {
        tutoringEditorAbort?.abort();
        tutoringEditorAbort = null;
        if (tutoringEditor) { tutoringEditor.hidden = true; tutoringEditor.replaceChildren(); }
    };
    const announceTutoring = message => {
        if (!tutoringNote) return;
        tutoringNote.textContent = message;
        tutoringNote.hidden = !message;
    };
    const openTutoringEditor = (mode, targetId = '') => {
        if (!tutoringEditor) return;
        closeTutoringEditor();
        closeAuthorsEditor();
        const excluded = new Set(temporaryTutors.filter(tutor => mode === 'replace' ? tutor.user_id !== targetId : true).map(tutor => tutor.user_id));
        const available = tutoringCatalog.map(person => normalizeTutor(person)).filter(person => person.user_id && !excluded.has(person.user_id));
        const copy = document.createElement('p');
        copy.textContent = mode === 'add'
            ? 'El tutor se añadirá al guardar los cambios.'
            : 'El tutor seleccionado se sustituirá al guardar los cambios.';
        const picker = document.createElement('div'); picker.className = 'ap-tutor-picker';
        const tutorSearch = document.createElement('input'); tutorSearch.type = 'search'; tutorSearch.placeholder = 'Buscar tutor…'; tutorSearch.className = 'ap-tutor-picker-search'; tutorSearch.hidden = true;
        const trigger = document.createElement('button'); trigger.type = 'button'; trigger.className = 'ap-tutor-picker-trigger';
        const panelId = `apTutorPicker${mode}${targetId || 'New'}`;
        trigger.setAttribute('aria-haspopup', 'listbox'); trigger.setAttribute('aria-expanded', 'false'); trigger.setAttribute('aria-controls', panelId);
        trigger.innerHTML = '<span>Selecciona un docente</span><i class="fa-solid fa-chevron-down" aria-hidden="true"></i>';
        const panel = document.createElement('div'); panel.id = panelId; panel.className = 'ap-tutor-picker-options'; panel.setAttribute('role', 'listbox'); panel.hidden = true;
        let selectedId = '';
        const optionButtons = available.map(person => {
            const option = document.createElement('button'); option.type = 'button'; option.setAttribute('role', 'option'); option.setAttribute('aria-selected', 'false'); option.dataset.value = person.user_id;
            const label = document.createElement('span');
            const name = document.createElement('strong'); name.textContent = person.full_name || 'Docente registrado'; label.append(name);
            if (person.username) { const username = document.createElement('small'); username.textContent = `@${person.username}`; label.append(username); }
            const check = document.createElement('i'); check.className = 'fa-solid fa-check'; check.setAttribute('aria-hidden', 'true'); option.append(label, check); panel.append(option); return option;
        });
        const actions = document.createElement('div'); actions.className = 'ap-tutoring-editor-actions';
        const cancel = document.createElement('button'); cancel.type = 'button'; cancel.textContent = 'Cancelar';
        const apply = document.createElement('button'); apply.type = 'button'; apply.hidden = true;
        const filterTutors = () => { const term = tutorSearch.value.trim().toLocaleLowerCase('es'); optionButtons.forEach(option => { const person = available.find(candidate => candidate.user_id === option.dataset.value); option.hidden = Boolean(term && ![person?.full_name, person?.username, person?.email].join(' ').toLocaleLowerCase('es').includes(term)); }); };
        const closePicker = (restoreFocus = false) => { panel.hidden = true; tutorSearch.hidden = true; tutorSearch.value = ''; filterTutors(); picker.classList.remove('is-open'); trigger.setAttribute('aria-expanded', 'false'); if (restoreFocus) trigger.focus(); };
        const openPicker = () => { if (!available.length) return; panel.hidden = false; tutorSearch.hidden = false; picker.classList.add('is-open'); trigger.setAttribute('aria-expanded', 'true'); tutorSearch.focus(); };
        const choose = option => {
            const selected = available.find(person => person.user_id === (option.dataset.value || ''));
            if (!selected) return;
            if (mode === 'add') {
                if (temporaryTutors.some(tutor => tutor.user_id === selected.user_id)) return;
                const restoresInitialPrimary = selected.user_id === initialPrimaryTutorId;
                if (restoresInitialPrimary) temporaryTutors = temporaryTutors.map(tutor => ({ ...tutor, is_primary: false }));
                temporaryTutors.push({ ...selected, is_primary: temporaryTutors.length === 0 || restoresInitialPrimary });
            } else {
                const index = temporaryTutors.findIndex(person => person.user_id === targetId);
                if (index >= 0) temporaryTutors[index] = { ...selected, is_primary: temporaryTutors[index].is_primary };
            }
            closeTutoringEditor(); renderTutoring(); syncChangeState();
        };
        trigger.addEventListener('click', () => panel.hidden ? openPicker() : closePicker());
        tutorSearch.addEventListener('input', filterTutors);
        trigger.addEventListener('keydown', event => { if (['ArrowDown', 'ArrowUp', 'Enter', ' '].includes(event.key)) { event.preventDefault(); openPicker(); } else if (event.key === 'Escape') closePicker(); });
        optionButtons.forEach((option, index) => {
            option.addEventListener('click', () => choose(option));
            option.addEventListener('keydown', event => {
                if (event.key === 'Escape') { event.preventDefault(); closePicker(true); return; }
                if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); choose(option); return; }
                if (!['ArrowDown', 'ArrowUp', 'Home', 'End'].includes(event.key)) return;
                event.preventDefault();
                const next = event.key === 'Home' ? 0 : event.key === 'End' ? optionButtons.length - 1 : event.key === 'ArrowUp' ? Math.max(0, index - 1) : Math.min(optionButtons.length - 1, index + 1);
                optionButtons[next]?.focus();
            });
        });
        tutoringEditorAbort = new AbortController();
        document.addEventListener('click', event => { if (!tutoringAdd?.contains(event.target) && !picker.contains(event.target)) closePicker(); }, { signal: tutoringEditorAbort.signal });
        cancel.addEventListener('click', closeTutoringEditor);
        apply.addEventListener('click', () => {
            const selected = available.find(person => person.user_id === selectedId);
            if (!selected) return;
            if (mode === 'add') {
                const restoresInitialPrimary = selected.user_id === initialPrimaryTutorId;
                if (restoresInitialPrimary) temporaryTutors = temporaryTutors.map(tutor => ({ ...tutor, is_primary: false }));
                temporaryTutors.push({ ...selected, is_primary: temporaryTutors.length === 0 || restoresInitialPrimary });
            } else {
                const index = temporaryTutors.findIndex(person => person.user_id === targetId);
                if (index >= 0) temporaryTutors[index] = { ...selected, is_primary: temporaryTutors[index].is_primary };
            }
            closeTutoringEditor(); renderTutoring(); syncChangeState();
        });
        picker.append(trigger, tutorSearch, panel); actions.append(cancel, apply);
        tutoringEditor.replaceChildren(copy, picker, actions);
        tutoringEditor.hidden = false;
        openPicker();
    };
    function renderTutoring() {
        if (!tutoringList) return;
        tutoringList.replaceChildren();
        temporaryTutors.forEach(person => {
            const row = document.createElement('article'); row.className = 'ap-tutor-person';
            const identity = document.createElement(person.email ? 'button' : 'div'); identity.className = 'ap-tutor-identity';
            if (person.email) identity.type = 'button';
            const avatar = document.createElement('span'); avatar.className = 'ap-tribunal-avatar'; avatar.textContent = tutorInitials(person); avatar.setAttribute('aria-hidden', 'true');
            const copy = document.createElement('span');
            if (person.username) { const username = document.createElement('small'); username.textContent = `@${person.username}`; copy.append(username); }
            const name = document.createElement('strong'); name.textContent = person.full_name || 'Docente registrado';
            const badge = document.createElement('em'); badge.textContent = 'Tutor';
            copy.append(name, badge); identity.append(avatar, copy);
            if (person.email) {
                const contactId = `apTutorContact${person.user_id}`; identity.setAttribute('aria-expanded', 'false'); identity.setAttribute('aria-controls', contactId); identity.setAttribute('aria-label', `Mostrar correo de ${person.full_name}`);
                const chevron = document.createElement('i'); chevron.className = 'fa-solid fa-chevron-down'; chevron.setAttribute('aria-hidden', 'true'); identity.append(chevron);
                const contact = document.createElement('div'); contact.id = contactId; contact.className = 'ap-tutor-contact'; contact.hidden = true;
                const contactLabel = document.createElement('small'); contactLabel.textContent = 'Correo institucional';
                const contactEmail = document.createElement('a'); contactEmail.href = `mailto:${person.email}`; contactEmail.textContent = person.email;
                contact.append(contactLabel, contactEmail);
                identity.addEventListener('click', () => {
                    const wasOpen = !contact.hidden;
                    tutoringList.querySelectorAll('.ap-tutor-contact').forEach(panel => { panel.hidden = true; });
                    tutoringList.querySelectorAll('.ap-tutor-identity[aria-expanded]').forEach(trigger => trigger.setAttribute('aria-expanded', 'false'));
                    contact.hidden = wasOpen;
                    identity.setAttribute('aria-expanded', String(!contact.hidden));
                });
                row.append(identity, contact);
            } else row.append(identity);
            const actions = document.createElement('div'); actions.className = 'ap-tutor-actions';
            const replace = document.createElement('button'); replace.type = 'button'; replace.className = 'ap-tutor-action'; replace.dataset.tooltip = 'Reemplazar tutor'; replace.setAttribute('aria-label', 'Reemplazar tutor'); replace.innerHTML = '<i class="fa-solid fa-rotate" aria-hidden="true"></i>'; replace.addEventListener('click', event => { event.stopPropagation(); openTutoringEditor('replace', person.user_id); }); actions.append(replace);
            const remove = document.createElement('button'); remove.type = 'button'; remove.className = 'ap-tutor-action is-destructive'; remove.disabled = temporaryTutors.length <= 1; remove.setAttribute('aria-disabled', String(remove.disabled));
            const removeHelp = remove.disabled ? 'El proyecto debe conservar al menos un tutor.' : 'Retirar tutor'; remove.dataset.tooltip = removeHelp; remove.setAttribute('aria-label', removeHelp); remove.title = removeHelp; remove.innerHTML = '<i class="fa-solid fa-trash-can" aria-hidden="true"></i>';
            remove.addEventListener('click', event => {
                event.stopPropagation();
                temporaryTutors = temporaryTutors.filter(tutor => tutor.user_id !== person.user_id);
                if (person.is_primary && temporaryTutors.length) temporaryTutors[0].is_primary = true;
                announceTutoring('El docente dejará de formar parte de la Tutoría. El proyecto debe conservar al menos un tutor.');
                closeTutoringEditor(); renderTutoring(); syncChangeState();
            });
            actions.append(remove);
            row.append(actions); tutoringList.append(row);
        });
        if (!temporaryTutors.length) {
            const empty = document.createElement('p'); empty.className = 'ap-tutoring-empty'; empty.textContent = 'El proyecto debe conservar al menos un tutor.'; tutoringList.append(empty);
        }
    }
    tutoringAdd?.addEventListener('click', () => tutoringEditor?.hidden ? openTutoringEditor('add') : closeTutoringEditor());
    const authorsCatalog = (() => { try { return JSON.parse(authorCatalogNode?.textContent || '[]'); } catch { return []; } })();
    const normalizeAuthor = (person, leader = false) => ({
        user_id: String(person?.user_id ?? person?.id ?? ''), username: String(person?.username || ''),
        full_name: String(person?.full_name || person?.name || ''), institutional_code: String(person?.institutional_code || ''),
        is_leader: leader,
    });
    const authorInitials = person => String(person?.full_name || person?.username || '?').trim().split(/\s+/).slice(0, 2).map(part => part[0]).join('').toUpperCase();
    const announceAuthors = message => { if (authorsNote) { authorsNote.textContent = message; authorsNote.hidden = !message; } };
    const closeAuthorsEditor = () => { authorsEditorAbort?.abort(); authorsEditorAbort = null; if (authorsEditor) { authorsEditor.hidden = true; authorsEditor.replaceChildren(); } };
    const loadAuthors = project => {
        const seen = new Set();
        const candidates = (Array.isArray(project?.participants) ? project.participants : []).filter(person => String(person?.role_code || '').toLowerCase() === 'student');
        temporaryAuthors = candidates.map(person => normalizeAuthor(person, Number(person?.is_leader) === 1)).filter(person => {
            if (!person.user_id || seen.has(person.user_id)) return false;
            seen.add(person.user_id); return true;
        });
        if (temporaryAuthors.length && !temporaryAuthors.some(author => author.is_leader)) temporaryAuthors[0].is_leader = true;
        renderAuthors();
    };
    const renderAuthors = () => {
        if (!authorsList) return;
        authorsList.replaceChildren();
        temporaryAuthors.forEach(person => {
            const row = document.createElement('article'); row.className = 'ap-tutor-person';
            const identity = document.createElement('div'); identity.className = 'ap-tutor-identity';
            const avatar = document.createElement('span'); avatar.className = 'ap-tribunal-avatar'; avatar.textContent = authorInitials(person); avatar.setAttribute('aria-hidden', 'true');
            const copy = document.createElement('span');
            if (person.institutional_code) { const code = document.createElement('small'); code.textContent = person.institutional_code; copy.append(code); }
            const name = document.createElement('strong'); name.textContent = person.full_name || 'Estudiante registrado';
            const badge = document.createElement('em'); badge.textContent = person.is_leader ? 'Autor principal' : 'Integrante';
            copy.append(name, badge); identity.append(avatar, copy); row.append(identity);
            const actions = document.createElement('div'); actions.className = 'ap-tutor-actions';
            const leader = document.createElement('button'); leader.type = 'button'; leader.className = 'ap-tutor-action'; leader.disabled = person.is_leader; leader.setAttribute('aria-disabled', String(leader.disabled));
            leader.dataset.tooltip = person.is_leader ? 'Autor principal' : 'Establecer como autor principal'; leader.setAttribute('aria-label', leader.dataset.tooltip); leader.innerHTML = '<i class="fa-solid fa-star" aria-hidden="true"></i>';
            leader.addEventListener('click', () => { temporaryAuthors = temporaryAuthors.map(author => ({ ...author, is_leader: author.user_id === person.user_id })); announceAuthors('Se actualizó el autor principal. Guarda los cambios para confirmarlo.'); renderAuthors(); syncChangeState(); });
            const remove = document.createElement('button'); remove.type = 'button'; remove.className = 'ap-tutor-action is-destructive'; remove.disabled = temporaryAuthors.length <= 1; remove.setAttribute('aria-disabled', String(remove.disabled));
            remove.dataset.tooltip = remove.disabled ? 'El proyecto debe conservar al menos un autor.' : 'Retirar autor'; remove.setAttribute('aria-label', remove.dataset.tooltip); remove.title = remove.dataset.tooltip; remove.innerHTML = '<i class="fa-solid fa-trash-can" aria-hidden="true"></i>';
            remove.addEventListener('click', () => { if (temporaryAuthors.length <= 1) return; temporaryAuthors = temporaryAuthors.filter(author => author.user_id !== person.user_id); if (person.is_leader && temporaryAuthors.length) temporaryAuthors[0].is_leader = true; announceAuthors('El autor se retirará al guardar los cambios.'); closeAuthorsEditor(); renderAuthors(); syncChangeState(); });
            actions.append(leader, remove); row.append(actions); authorsList.append(row);
        });
        if (!temporaryAuthors.length) { const empty = document.createElement('p'); empty.className = 'ap-tutoring-empty'; empty.textContent = 'El proyecto debe conservar al menos un autor.'; authorsList.append(empty); }
    };
    const openAuthorsEditor = () => {
        if (!authorsEditor) return;
        closeAuthorsEditor();
        closeTutoringEditor();
        const excluded = new Set(temporaryAuthors.map(author => author.user_id));
        const available = authorsCatalog.map(person => normalizeAuthor(person)).filter(person => person.user_id && !excluded.has(person.user_id));
        const copy = document.createElement('p'); copy.textContent = 'El autor se añadirá al guardar los cambios.';
        const search = document.createElement('input'); search.type = 'search'; search.placeholder = 'Buscar estudiante…'; search.className = 'ap-tutor-picker-search';
        const picker = document.createElement('div'); picker.className = 'ap-tutor-picker';
        const options = document.createElement('div'); options.className = 'ap-tutor-picker-options'; options.setAttribute('role', 'listbox');
        let selectedId = '';
        const apply = document.createElement('button'); apply.type = 'button'; apply.hidden = true;
        const renderOptions = () => {
            const term = search.value.trim().toLocaleLowerCase('es'); options.replaceChildren();
            available.filter(person => !term || [person.full_name, person.username, person.institutional_code].join(' ').toLocaleLowerCase('es').includes(term)).forEach(person => {
                const option = document.createElement('button'); option.type = 'button'; option.setAttribute('role', 'option'); option.setAttribute('aria-selected', String(person.user_id === selectedId));
                const label = document.createElement('span'); const name = document.createElement('strong'); name.textContent = person.full_name || 'Estudiante registrado'; label.append(name);
                const meta = document.createElement('small'); meta.textContent = [person.institutional_code, person.username && `@${person.username}`].filter(Boolean).join(' · '); if (meta.textContent) label.append(meta);
                option.append(label); option.addEventListener('click', () => {
                    if (temporaryAuthors.some(author => author.user_id === person.user_id)) return;
                    temporaryAuthors.push({ ...person, is_leader: temporaryAuthors.length === 0 });
                    announceAuthors('El autor se añadirá al guardar los cambios.');
                    closeAuthorsEditor(); renderAuthors(); syncChangeState();
                }); options.append(option);
            });
        };
        authorsEditorAbort = new AbortController();
        document.addEventListener('click', event => { if (!authorsAdd?.contains(event.target) && !authorsEditor.contains(event.target)) closeAuthorsEditor(); }, { signal: authorsEditorAbort.signal });
        search.addEventListener('input', renderOptions); renderOptions();
        const actions = document.createElement('div'); actions.className = 'ap-tutoring-editor-actions'; const cancel = document.createElement('button'); cancel.type = 'button'; cancel.textContent = 'Cancelar';
        cancel.addEventListener('click', closeAuthorsEditor);
        actions.append(cancel, apply); picker.append(search, options); authorsEditor.replaceChildren(copy, picker, actions); authorsEditor.hidden = false; search.focus();
    };
    authorsAdd?.addEventListener('click', () => authorsEditor?.hidden ? openAuthorsEditor() : closeAuthorsEditor());
    const renderTribunal = project => {
        form?.querySelector('[data-project-tribunal]')?.remove();
        const academicGrid = form?.querySelector('[data-project-academic-grid]');
        if (!academicGrid) return;
        const typeCode = String(project?.type_code || project?.project_type_code || project?.type_key || '').toLowerCase();
        const typeName = String(project?.type_name || project?.type || '').trim();
        const isDegreeProject = typeCode === 'thesis' || (typeCode === '' && /titulación|trabajo de titulación/i.test(typeName));
        if (!isDegreeProject || !Array.isArray(project?.participants)) return;
        const section = document.createElement('div');
        section.className = 'ap-readonly ap-tribunal';
        section.dataset.projectTribunal = '';
        const sectionIcon = document.createElement('i');
        sectionIcon.className = 'fa-solid fa-scale-balanced';
        sectionIcon.setAttribute('aria-hidden', 'true');
        const sectionBody = document.createElement('span');
        const sectionTitle = document.createElement('small');
        sectionTitle.textContent = 'Tribunal';
        const content = document.createElement('div');
        content.className = 'ap-tribunal-content';
        sectionBody.append(sectionTitle, content);
        section.append(sectionIcon, sectionBody);
        academicGrid.append(section);
        const members = (Array.isArray(project?.participants) ? project.participants : []).filter(person =>
            ['tribunal', 'jury'].includes(String(person?.role_code || '').toLowerCase()) && Boolean(Number(person?.is_teacher))
        );
        if (!members.length) {
            const empty = document.createElement('p');
            empty.className = 'ap-tribunal-empty';
            empty.textContent = 'El proyecto aún no tiene un tribunal asignado.';
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
            const main = document.createElement('div');
            main.className = 'ap-tribunal-person-main';
            row.append(main);
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
            const payloadRole = String(person.tribunal_role_label || person.role_label || person.role_name || person.position || person.role || '').trim();
            role.textContent = payloadRole && !['tribunal', 'jury'].includes(payloadRole.toLowerCase()) ? payloadRole : 'Miembro del tribunal';
            identity.append(role);
            if (email) {
                const contact = document.createElement('span');
                contact.className = 'ap-tribunal-email';
                contact.textContent = email;
                identity.append(contact);
            }
            main.append(avatar, identity);
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
    const open = project => {
        activeProject = project || null;
        pendingSaveData = null;
        document.body.dispatchEvent(new MouseEvent('click', { bubbles: true }));
        form.reset();
        const formMessage = document.querySelector('#apMessage');
        formMessage.hidden = true;
        formMessage.textContent = '';
        form.querySelector('[type="submit"]').disabled = false;
        for (const [key, value] of Object.entries(project || {})) if (form.elements[key]) form.elements[key].value = value ?? '';
        if (!project) setProjectDefaults();
        else [careerSelect, periodSelect, tutorSelect].forEach(select => select?.dispatchEvent(new Event('change', { bubbles: true })));
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
        setText('[data-project-status]', value('status_label'), project ? 'Sin información' : 'En desarrollo');
        setText('[data-project-stage]', value('stage_label', 'current_stage_label', 'current_stage', 'stage'), 'Sin información registrada');
        renderStatusTransitions(project);
        setText('[data-project-code]', value('code'), 'Sin información');
        setText('[data-project-published]', readableDate(value('published_at', 'repository_published_at')), 'Sin publicar');
        setText('[data-project-updated]', readableDate(value('updated_at')), 'Sin información');
        const tags = form.querySelector('[data-project-tags]');
        if (tags) {
            const values = (Array.isArray(project?.tags) ? project.tags : Array.isArray(project?.keywords) ? project.keywords : []).slice(0, 4);
            tags.replaceChildren(...(values.length ? values.map(tag => Object.assign(document.createElement('span'), { textContent: typeof tag === 'string' ? tag : tag.name })) : [Object.assign(document.createElement('span'), { textContent: 'Sin etiquetas registradas' })]));
        }
        loadKeywordSelector(project);
        loadTutoring(project);
        loadAuthors(project);
        initialTutoringState = tutoringState();
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
            if (manageParticipants) manageParticipants.href = '#project-authors';
            if (manageFiles) manageFiles.href = projectUrl('documents');
        }
        showProjectDialog(modal);
        initialFormState = editableState();
        syncChangeState();
        form.title.focus();
    };
    window.AdminProjectEditor = { open };
    document.querySelector('#apNew')?.addEventListener('click', () => open());
    const resetTutoringPanels = () => {
        closeTutoringEditor();
        tutoring?.querySelectorAll('.ap-tutor-contact').forEach(contact => { contact.hidden = true; });
        tutoring?.querySelectorAll('.ap-tutor-identity[aria-expanded]').forEach(identity => identity.setAttribute('aria-expanded', 'false'));
        if (tutoringNote) { tutoringNote.hidden = true; tutoringNote.textContent = ''; }
        closeAuthorsEditor();
        if (authorsNote) { authorsNote.hidden = true; authorsNote.textContent = ''; }
    };
    const closeEditor = () => {
        closeKeywordSelector();
        resetTutoringPanels();
        modal.hidden = true;
    };
    manageParticipants?.addEventListener('click', event => {
        event.preventDefault();
        authors?.scrollIntoView({ block: 'center', behavior: 'smooth' });
        authorsAdd?.focus({ preventScroll: true });
    });
    document.querySelectorAll('[data-close]').forEach(button => button.addEventListener('click', closeEditor));
    modal?.addEventListener('click', event => { if (event.target === modal) closeEditor(); });
    document.addEventListener('keydown', event => {
        if (event.key !== 'Escape' || event.defaultPrevented || modal?.hidden || !saveConfirm?.hidden) return;
        closeEditor();
    });
    const saveProject = async data => {
        projectIsSaving = true;
        syncChangeState();
        try {
            await request(cfg.dataset.save, data);
            saveConfirm.hidden = true;
            closeEditor();
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
        if (form.elements.id.value) {
            const leader = temporaryAuthors.find(author => author.is_leader);
            if (!temporaryAuthors.length || !leader) {
                announceAuthors('El proyecto debe conservar al menos un autor principal.');
                authors?.scrollIntoView({ block: 'center', behavior: 'smooth' });
                return;
            }
            data.set('authors_managed', '1');
            data.delete('author_user_ids[]');
            temporaryAuthors.forEach(author => data.append('author_user_ids[]', author.user_id));
            data.set('author_leader_id', leader.user_id);
        }
        const primaryTutor = temporaryTutors.find(tutor => tutor.is_primary) || temporaryTutors[0];
        data.set('tutoring_managed', '1');
        data.delete('tutoring_user_ids[]');
        temporaryTutors.forEach(tutor => data.append('tutoring_user_ids[]', tutor.user_id));
        data.set('tutoring_primary_id', primaryTutor?.user_id || '');
        ['career_id', 'academic_period_id', 'project_type_id', 'tutor_id', 'status'].forEach(name => data.set(name, form.elements[name].value));
        data.set('tutor_id', primaryTutor?.user_id || '');
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
    const filterInteractionKey = 'admin-projects-filter-interaction';
    let submitAuthorized = false;
    const submitFilters = () => {
        submitAuthorized = true;
        filters.requestSubmit();
    };
    try {
        const pendingInteraction = JSON.parse(sessionStorage.getItem(filterInteractionKey) || 'null');
        sessionStorage.removeItem(filterInteractionKey);
        if (pendingInteraction && Number.isFinite(pendingInteraction.scrollY)) {
            requestAnimationFrame(() => {
                window.scrollTo(pendingInteraction.scrollX || 0, pendingInteraction.scrollY);
                if (pendingInteraction.searchFocused) {
                    search.focus({ preventScroll: true });
                    const position = Math.max(0, Math.min(Number(pendingInteraction.selectionStart) || search.value.length, search.value.length));
                    search.setSelectionRange(position, position);
                }
            });
        }
    } catch {
        // La búsqueda continúa funcionando si el almacenamiento de sesión no está disponible.
    }
    const apply = () => {
        restore();
        const query = fold(search.value).trim();
        const terms = query.split(/\s+/).filter(Boolean);
        const selectedStatus = statusFilter?.value || '';
        const selectedType = typeFilter?.value || '';
        const selectedPeriod = periodFilter?.value || '';
        let visible = 0;
        rows.forEach(row => {
            const haystack = fold(row.innerText);
            const matches = (!query || terms.every(term => haystack.includes(term)))
                && (!selectedStatus || row.dataset.projectStatus === selectedStatus)
                && (!selectedType || row.dataset.projectTypeId === selectedType)
                && (!selectedPeriod || row.dataset.projectPeriodId === selectedPeriod);
            row.hidden = !matches;
            if (matches) {
                visible++;
                if (query) textNodes(row).forEach(node => highlight(node, terms));
            }
        });
        if (clear) clear.hidden = !search.value;
        if (noResults) noResults.hidden = visible !== 0 || !query;
    };
    const typeFilter = filters.querySelector('select[name="type_id"]');
    const statusFilter = filters.querySelector('select[name="status"]');
    const periodFilter = filters.querySelector('select[name="period_id"]');
    filters.addEventListener('submit', event => {
        if (!submitAuthorized) {
            event.preventDefault();
            return;
        }
        if (periodFilter && !periodFilter.value) periodFilter.disabled = true;
        try {
            sessionStorage.setItem(filterInteractionKey, JSON.stringify({
                scrollX: window.scrollX,
                scrollY: window.scrollY,
                searchFocused: document.activeElement === search,
                selectionStart: search.selectionStart,
            }));
        } catch {
            // El formulario se envía normalmente si el almacenamiento no está disponible.
        }
    }, true);
    const thesisOnlyFilters = [...(statusFilter?.options || [])].filter(option => ['defense', 'tribunal_approved'].includes(option.value));
    const syncFilterWorkflow = () => {
        if (!typeFilter || !thesisOnlyFilters.length) return;
        const selectedType = typeFilter.options[typeFilter.selectedIndex];
        const isGeneral = !typeFilter.value;
        const isThesis = /titulación|tesis/i.test(selectedType?.textContent || '');
        thesisOnlyFilters.forEach(option => { option.disabled = !isGeneral && !isThesis; option.hidden = !isGeneral && !isThesis; });
        if (!isGeneral && !isThesis && ['defense', 'tribunal_approved'].includes(statusFilter.value)) statusFilter.value = '';
    };
    const syncPaginationFilters = () => {
        const values = new FormData(filters);
        const keys = ['search', 'status', 'type_id', 'period_id'];
        const updateUrl = url => {
            keys.forEach(key => {
                const value = String(values.get(key) || '');
                if (value) url.searchParams.set(key, value); else url.searchParams.delete(key);
            });
            return url;
        };
        document.querySelectorAll('.data-pagination-pages a[href]').forEach(link => {
            link.href = updateUrl(new URL(link.href, location.href)).toString();
        });
        const sizeForm = document.querySelector('.data-pagination-size');
        if (!sizeForm) return;
        keys.forEach(key => {
            const value = String(values.get(key) || '');
            let field = sizeForm.querySelector(`input[name="${key}"]`);
            if (!value) { field?.remove(); return; }
            if (!field) { field = document.createElement('input'); field.type = 'hidden'; field.name = key; sizeForm.append(field); }
            field.value = value;
        });
    };
    const applyFilters = () => { apply(); syncPaginationFilters(); };
    typeFilter?.addEventListener('change', () => { syncFilterWorkflow(); applyFilters(); });
    statusFilter?.addEventListener('change', applyFilters);
    periodFilter?.addEventListener('change', applyFilters);
    search.addEventListener('input', event => {
        event.stopPropagation();
        applyFilters();
    });
    search.addEventListener('keydown', event => {
        if (event.key === 'Enter') submitAuthorized = true;
    });
    clear?.addEventListener('click', () => {
        search.value = '';
        applyFilters();
        search.focus();
        if (fold(serverQuery).trim() !== '') submitFilters();
    });
    syncFilterWorkflow();
    applyFilters();
})();

(() => {
    if (!document.querySelector('.ap-connection-notice')) return;
    const empty = document.querySelector('.ap-empty');
    if (!empty) return;
    empty.querySelector('h2').textContent = 'No hay datos para mostrar';
    empty.querySelector('p').textContent = 'La información aparecerá cuando se restablezca la conexión.';
})();
