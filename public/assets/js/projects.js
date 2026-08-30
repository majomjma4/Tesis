(() => {
    const pageRoot = document.querySelector('[data-projects-page]');
    const root = pageRoot || document.querySelector('[data-student-workspace]');
    if (!root) return;

    const tabs = [...root.querySelectorAll('[data-project-tab]')];
    const activate = (key, focus = false) => tabs.forEach((tab) => {
        const active = tab.dataset.projectTab === key;
        tab.classList.toggle('active', active);
        tab.setAttribute('aria-selected', String(active));
        tab.tabIndex = active ? 0 : -1;
        root.querySelector(`[data-project-panel="${tab.dataset.projectTab}"]`)?.toggleAttribute('hidden', !active);
        if (active && focus) tab.focus();
    });
    tabs.forEach((tab, index) => tab.addEventListener('keydown', (event) => {
        if (!['ArrowRight', 'ArrowDown', 'ArrowLeft', 'ArrowUp', 'Home', 'End'].includes(event.key)) return;
        event.preventDefault();
        const next = event.key === 'Home' ? 0 : event.key === 'End' ? tabs.length - 1 : (index + (event.key === 'ArrowRight' || event.key === 'ArrowDown' ? 1 : -1) + tabs.length) % tabs.length;
        activate(tabs[next].dataset.projectTab, true);
    }));
    tabs.forEach((tab) => tab.addEventListener('click', () => activate(tab.dataset.projectTab)));
    if (location.hash === '#finished') activate('finished');

    const config = root.querySelector('[data-student-project-publish-config]');
    const modal = root.querySelector('[data-project-publish-modal]');
    if (!config || !modal) return;
    const ui = (name) => modal.querySelector(`[data-publish-ui-${name}]`);
    const dialog = modal.querySelector('.projects-publish-dialog');
    const available = modal.querySelector('.projects-publish-available');
    const manage = ui('manage');
    const summary = ui('summary');
    const error = ui('error');
    const count = ui('count');
    const previewList = ui('preview');
    const manageList = ui('files');
    const excludedBox = ui('excluded');
    const liveCount = ui('live-count');
    const empty = ui('empty');
    const confirm = ui('confirm');
    const back = ui('back');
    const finalConfirm = ui('final-confirm');
    const finalText = ui('final-text');
    const finalAccept = ui('final-accept');
    const finalCancel = ui('final-cancel');
    const changeConfirm = ui('change-confirm');
    const changeTitle = ui('change-title');
    const changeText = ui('change-text');
    const changeAccept = ui('change-accept');
    const changeCancel = ui('change-cancel');
    const footerCancel = ui('cancel');
    const closeButton = modal.querySelector('[data-project-publish-close]');
    let projectId = 0;
    let preparationId = '';
    let initial = null;
    let plan = null;
    let opener = null;
    let mode = 'summary';
    let busy = false;
    let closing = false;
    let pendingChange = null;
    const excluded = new Map();

    const formatCount = (n) => `${n} ${n === 1 ? 'archivo' : 'archivos'}`;
    const formatSize = (value) => {
        const bytes = Number(value || 0);
        if (!bytes) return '';
        if (bytes < 1024) return `${bytes} B`;
        const units = ['KB', 'MB', 'GB'];
        let number = bytes / 1024;
        let index = 0;
        while (number >= 1024 && index < units.length - 1) {
            number /= 1024;
            index++;
        }
        return `${number.toFixed(number >= 10 ? 0 : 1)} ${units[index]}`;
    };
    const typeIcon = (extension) => ({ pdf: 'fa-file-lines', zip: 'fa-file-zipper', docx: 'fa-file-word', doc: 'fa-file-word', xlsx: 'fa-file-excel', xls: 'fa-file-excel', pptx: 'fa-file-powerpoint', ppt: 'fa-file-powerpoint', png: 'fa-file-image', jpg: 'fa-file-image', jpeg: 'fa-file-image', webp: 'fa-file-image' }[String(extension || '').toLowerCase()] || 'fa-file');
    const request = async (requestMode, extra = {}, upload = null) => {
        const body = new FormData();
        body.set('_csrf', config.dataset.csrf || '');
        body.set('project_id', String(projectId));
        body.set('mode', requestMode);
        if (preparationId) body.set('preparation_id', preparationId);
        Object.entries(extra).forEach(([key, value]) => body.set(key, String(value)));
        if (upload) body.set('file', upload);
        const response = await fetch(config.dataset.endpoint || '', { method: 'POST', body, headers: { Accept: 'application/json' } });
        const payload = await response.json().catch(() => ({ success: false, message: 'No fue posible procesar la respuesta del servidor.' }));
        if (!response.ok || !payload.success) throw new Error(payload.message || 'No fue posible procesar la publicación.');
        return payload.data || {};
    };
    const setBusy = (value, label = '') => {
        busy = value;
        modal.classList.toggle('is-busy', value);
        [...modal.querySelectorAll('button,input')].forEach((control) => { control.disabled = value; });
        if (label && confirm) confirm.innerHTML = `<i class="fa-solid fa-spinner fa-spin" aria-hidden="true"></i>${label}`;
    };
    const showError = (message = '') => {
        error.textContent = message;
        error.hidden = !message;
    };
    const contextNote = (() => {
        const node = document.createElement('p');
        node.className = 'projects-publish-context-note';
        node.hidden = true;
        node.setAttribute('role', 'status');
        manage?.append(node);
        return node;
    })();
    const showContext = (kind = '') => {
        const messages = {
            add: '<strong>Archivo sin revisión académica</strong><span>Este archivo se añadirá directamente a la publicación final y no ha pasado por una nueva revisión del tutor. Verifica que corresponda a la versión definitiva.</span>',
            replace: '<strong>Reemplazo de versión final</strong><span>El nuevo archivo no será enviado nuevamente a revisión. La versión aprobada anterior permanecerá guardada en el historial.</span>',
            remove: '<strong>Quitar de la publicación</strong><span>Este archivo no formará parte de la publicación final. Su versión anterior permanecerá disponible en el historial.</span>',
        };
        contextNote.innerHTML = messages[kind] || '';
        contextNote.hidden = !messages[kind];
    };
    const switchMode = (value) => {
        mode = value;
        finalConfirm.hidden = true;
        available.hidden = value === 'manage';
        manage.hidden = value !== 'manage';
        summary.hidden = value !== 'summary';
        confirm.hidden = value !== 'summary';
        back.hidden = value !== 'manage';
        changeConfirm.hidden = true;
        pendingChange = null;
        modal.classList.toggle('is-wide', value === 'manage');
        showError();
        if (value === 'manage') {
            showContext();
            renderManage(plan);
        } else {
            showContext();
            if (value === 'summary') {
                renderAvailable(plan || initial);
                renderSummary(plan || initial);
            }
        }
    };
    const makeFileItem = (file, compact = false) => {
        const item = document.createElement('li');
        item.className = compact ? 'projects-publish-preview-file' : 'projects-publish-file-row';
        const icon = document.createElement('i');
        icon.className = `fa-solid ${typeIcon(file.extension)}`;
        icon.setAttribute('aria-hidden', 'true');
        const text = document.createElement('span');
        const name = document.createElement('strong');
        name.textContent = file.name || 'Archivo';
        text.append(name);
        if (!compact) {
            const meta = document.createElement('small');
            const bits = [formatSize(file.size_bytes), file.kind === 'updated' ? 'Actualizado' : file.kind === 'new' ? 'Nuevo' : 'Aprobado'].filter(Boolean);
            meta.textContent = bits.join(' · ');
            text.append(meta);
        }
        item.append(icon, text);
        return item;
    };
    const renderAvailable = (data) => {
        const files = data?.files || [];
        count.textContent = formatCount(data?.file_count || 0);
        previewList.replaceChildren(...files.map((file) => makeFileItem(file, true)));
        previewList.classList.remove('is-collapsed');
    };
    const renderExcluded = () => {
        if (!excluded.size) {
            excludedBox.hidden = true;
            excludedBox.replaceChildren();
            return;
        }
        excludedBox.hidden = false;
        const toggle = document.createElement('button');
        toggle.type = 'button';
        toggle.className = 'projects-publish-text-action';
        toggle.textContent = `${formatCount(excluded.size)} excluidos · Ver`;
        const list = document.createElement('div');
        list.hidden = true;
        for (const file of excluded.values()) {
            const row = document.createElement('div');
            row.textContent = file.name || 'Archivo';
            const restore = document.createElement('button');
            restore.type = 'button';
            restore.textContent = 'Restaurar a publicación';
            restore.addEventListener('click', async () => run(async () => {
                plan = await request('prepare-include', { file_id: file.file_id, included: 'true' });
                excluded.delete(file.file_id);
                renderManage(plan);
            }));
            row.append(restore);
            list.append(row);
        }
        toggle.addEventListener('click', () => {
            list.hidden = !list.hidden;
            toggle.textContent = `${formatCount(excluded.size)} excluidos · ${list.hidden ? 'Ver' : 'Ocultar'}`;
        });
        excludedBox.replaceChildren(toggle, list);
    };
    const positionMenu = (button, menu) => {
        const rect = button.getBoundingClientRect();
        menu.style.left = `${Math.max(8, Math.min(rect.right - 158, window.innerWidth - 166))}px`;
        const below = rect.bottom + 6;
        const height = Math.min(menu.scrollHeight || 90, window.innerHeight - 16);
        menu.style.top = `${below + height <= window.innerHeight ? below : Math.max(8, rect.top - height - 6)}px`;
    };
    const closeFloatingMenus = () => {
        document.querySelectorAll('.projects-publish-file-menu-list.is-floating').forEach((menu) => {
            menu.hidden = true;
            menu.classList.remove('is-floating');
            if (menu._origin?.isConnected) menu._origin.appendChild(menu);
            menu._origin = null;
        });
        document.querySelectorAll('.projects-publish-file-menu[aria-expanded="true"]').forEach((button) => button.setAttribute('aria-expanded', 'false'));
    };
    const askChange = (kind, file, upload = null, input = null) => {
        pendingChange = { kind, file, upload, input };
        const isReplacement = kind === 'replace';
        changeTitle.textContent = isReplacement ? 'Confirmar reemplazo' : 'Confirmar exclusión';
        changeText.textContent = isReplacement
            ? `Se reemplazará «${file.name || 'este archivo'}» en el conjunto de archivos de la publicación.`
            : `Se excluirá «${file.name || 'este archivo'}» del conjunto de archivos de la publicación.`;
        changeAccept.textContent = isReplacement ? 'Reemplazar' : 'Quitar';
        changeConfirm.hidden = false;
        changeAccept.focus();
    };
    const renderManage = (data) => {
        if (!data) return;
        closeFloatingMenus();
        plan = data;
        manageList.replaceChildren();
        for (const file of data.files || []) {
            const row = makeFileItem(file);
            const badge = document.createElement('em');
            badge.className = `projects-publish-file-badge is-${file.kind || 'current'}`;
            badge.textContent = file.kind === 'updated' ? 'Actualizado' : file.kind === 'new' ? 'Nuevo' : 'Aprobado';
            row.append(badge);
            const actions = document.createElement('div');
            actions.className = 'projects-publish-file-actions';
            const menu = document.createElement('button');
            menu.type = 'button';
            menu.className = 'projects-publish-file-menu';
            menu.setAttribute('aria-label', `Acciones para ${file.name || 'archivo'}`);
            menu.setAttribute('aria-expanded', 'false');
            menu.innerHTML = '<i class="fa-solid fa-ellipsis" aria-hidden="true"></i>';
            const menuList = document.createElement('div');
            menuList.className = 'projects-publish-file-menu-list';
            menuList.hidden = true;
            menu.addEventListener('click', (event) => {
                event.stopPropagation();
                const opening = menuList.hidden;
                closeFloatingMenus();
                if (opening) {
                    menuList.hidden = false;
                    menuList.classList.add('is-floating');
                    menuList._origin = actions;
                    document.body.appendChild(menuList);
                    positionMenu(menu, menuList);
                }
                menu.setAttribute('aria-expanded', String(opening));
            });
            if (file.file_id) {
                const picker = document.createElement('input');
                picker.type = 'file';
                picker.hidden = true;
                picker.addEventListener('cancel', () => showContext(''));
                picker.addEventListener('change', () => {
                    const uploaded = picker.files?.[0];
                    if (!uploaded) {
                        showContext('');
                        return;
                    }
                    showContext('replace');
                    closeFloatingMenus();
                    askChange('replace', file, uploaded, picker);
                });
                const replace = document.createElement('button');
                replace.type = 'button';
                replace.textContent = 'Reemplazar';
                replace.addEventListener('click', () => {
                    showContext('replace');
                    closeFloatingMenus();
                    picker.click();
                });
                const remove = document.createElement('button');
                remove.type = 'button';
                remove.textContent = 'Quitar de publicación';
                remove.addEventListener('click', () => {
                    showContext('remove');
                    closeFloatingMenus();
                    askChange('remove', file);
                });
                menuList.append(replace, remove, picker);
            } else {
                const remove = document.createElement('button');
                remove.type = 'button';
                remove.textContent = 'Quitar';
                remove.addEventListener('click', () => {
                    showContext('remove');
                    closeFloatingMenus();
                    askChange('remove', file);
                });
                menuList.append(remove);
            }
            actions.append(menu, menuList);
            row.append(actions);
            manageList.append(row);
        }
        const total = Number(data.file_count || 0);
        liveCount.textContent = `${formatCount(total)} se publicarán`;
        empty.hidden = total > 0;
        renderExcluded();
    };
    const renderSummary = (data) => {
        const total = Number(data?.file_count || 0);
        ui('summary-count').textContent = `Se publicarán ${formatCount(total)} en el Repositorio Académico.`;
        confirm.disabled = total < 1;
    };
    const close = async (discard = true) => {
        if (busy || closing || modal.hidden) return;
        closing = true;
        closeFloatingMenus();
        try {
            if (discard && preparationId && projectId) {
                try { await request('prepare-cancel'); } catch (_) {}
            }
            modal.hidden = true;
            document.body.classList.remove('projects-publish-open');
            document.body.style.overflow = '';
            projectId = 0;
            preparationId = '';
            initial = null;
            plan = null;
            excluded.clear();
            pendingChange = null;
            changeConfirm.hidden = true;
            finalConfirm.hidden = true;
            showError();
            showContext();
            if (opener?.isConnected) opener.focus();
        } finally {
            closing = false;
        }
    };
    const run = async (operation, failure = 'No fue posible actualizar los archivos.') => {
        if (busy) return;
        setBusy(true);
        showError();
        try {
            await operation();
        } catch (exception) {
            showError(exception.message || failure);
            if (mode === 'summary') {
                confirm.innerHTML = 'Publicar proyecto';
                finalAccept.innerHTML = 'Sí, publicar';
            }
        } finally {
            setBusy(false);
        }
    };

    root.querySelectorAll('[data-project-publish]').forEach((button) => button.addEventListener('click', () => run(async () => {
        opener = button;
        projectId = Number(button.dataset.projectId || 0);
        if (!projectId) return;
        initial = await request('preview');
        plan = initial;
        renderAvailable(initial);
        switchMode('summary');
        modal.hidden = false;
        document.body.classList.add('projects-publish-open');
        document.body.style.overflow = 'hidden';
        dialog?.focus();
    }, 'No fue posible preparar la publicación.')));
    ui('update')?.addEventListener('click', () => run(async () => {
        if (preparationId && plan) {
            switchMode('manage');
            return;
        }
        const data = await request('prepare');
        preparationId = data.preparation_id || '';
        if (!preparationId) throw new Error('No fue posible iniciar la preparación de archivos.');
        plan = data;
        switchMode('manage');
    }));
    ui('add')?.addEventListener('click', () => run(async () => {
        const input = ui('add-file');
        const selected = [...(input?.files || [])];
        if (!selected.length) throw new Error('Selecciona al menos un archivo para agregar.');
        showContext('add');
        for (const uploaded of selected) plan = await request('prepare-add', {}, uploaded);
        input.value = '';
        renderManage(plan);
        showContext('add');
    }));
    back?.addEventListener('click', () => switchMode('summary'));
    changeCancel?.addEventListener('click', () => {
        pendingChange?.input && (pendingChange.input.value = '');
        pendingChange = null;
        changeConfirm.hidden = true;
        showContext('');
    });
    changeAccept?.addEventListener('click', () => run(async () => {
        const change = pendingChange;
        if (!change) return;
        if (change.kind === 'replace') {
            plan = await request('prepare-replace', { file_id: change.file.file_id }, change.upload);
        } else if (change.file.file_id) {
            plan = await request('prepare-include', { file_id: change.file.file_id, included: 'false' });
            excluded.set(change.file.file_id, change.file);
        } else {
            plan = await request('prepare-remove-add', { file_key: String(change.file.key || '').replace('new:', '') });
        }
        pendingChange = null;
        changeConfirm.hidden = true;
        renderManage(plan);
    }));
    footerCancel?.addEventListener('click', () => close(true));
    closeButton?.addEventListener('click', () => close(true));
    modal.addEventListener('click', (event) => { if (event.target === modal) close(true); });
    document.addEventListener('click', (event) => { if (!event.target.closest('.projects-publish-file-menu, .projects-publish-file-menu-list')) closeFloatingMenus(); });
    confirm?.addEventListener('click', () => {
        const total = Number((plan || initial)?.file_count || 0);
        finalText.textContent = `Se publicarán ${formatCount(total)} en el Repositorio Académico.`;
        finalConfirm.hidden = false;
        finalAccept.focus();
    });
    finalCancel?.addEventListener('click', () => { finalConfirm.hidden = true; confirm.focus(); });
    finalAccept?.addEventListener('click', () => run(async () => {
        finalAccept.disabled = true;
        finalAccept.innerHTML = '<i class="fa-solid fa-spinner fa-spin" aria-hidden="true"></i> Publicando…';
        const result = await request('publish');
        sessionStorage.setItem('digitalRecordToast', 'Proyecto publicado correctamente.');
        setBusy(false);
        await close(false);
        const destination = result.detail_url || `${location.pathname}?page=repository-detail&id=${encodeURIComponent(projectId)}`;
        location.assign(destination);
    }, 'No fue posible publicar el proyecto.'));
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !modal.hidden && !busy) {
            const floating = modal.querySelector('.projects-publish-file-menu-list.is-floating') || document.querySelector('.projects-publish-file-menu-list.is-floating');
            if (floating) {
                event.preventDefault();
                closeFloatingMenus();
                return;
            }
            if (!changeConfirm.hidden) {
                changeCancel.click();
            } else if (!finalConfirm.hidden) {
                finalConfirm.hidden = true;
                confirm.focus();
            } else {
                close(true);
            }
        }
        if (event.key !== 'Tab' || modal.hidden) return;
        const focusable = [...modal.querySelectorAll('button:not([disabled]):not([hidden]),input:not([disabled]):not([hidden])')].filter((element) => element.offsetParent !== null);
        if (!focusable.length) return;
        const first = focusable[0];
        const last = focusable.at(-1);
        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    });
})();
