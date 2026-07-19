(() => {
    if ('scrollRestoration' in history) history.scrollRestoration = 'manual';
    const scrollKeyFor = url => {
        const destination = new URL(url, window.location.href);
        return `project-scroll:${destination.pathname}${destination.search}`;
    };
    const savedScroll = sessionStorage.getItem(scrollKeyFor(window.location.href));
    if (savedScroll !== null) {
        sessionStorage.removeItem(scrollKeyFor(window.location.href));
        requestAnimationFrame(() => requestAnimationFrame(() => window.scrollTo({ top: Number(savedScroll) || 0, behavior: 'auto' })));
    }
    document.addEventListener('click', event => {
        const link = event.target.closest('a[href]');
        if (!link || link.target === '_blank' || event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
        const destination = new URL(link.href, window.location.href);
        if (destination.origin !== window.location.origin || destination.pathname !== window.location.pathname || destination.searchParams.get('page') !== 'project-detail') return;
        sessionStorage.setItem(scrollKeyFor(destination.href), String(window.scrollY));
    });

    const menu = document.querySelector('[data-project-more-menu]');
    const trigger = menu?.querySelector(':scope > button');
    const panel = menu?.querySelector('[role="menu"]');
    const close = () => {
        if (!menu || !trigger || !panel) return;
        menu.classList.remove('is-open');
        panel.hidden = true;
        trigger.setAttribute('aria-expanded', 'false');
    };
    trigger?.addEventListener('click', () => {
        const open = panel.hidden;
        menu.classList.toggle('is-open', open);
        panel.hidden = !open;
        trigger.setAttribute('aria-expanded', String(open));
        if (open) panel.querySelector('a')?.focus();
    });
    menu?.addEventListener('keydown', event => {
        if (event.key === 'Escape') { close(); trigger.focus(); }
    });
    document.addEventListener('click', event => { if (!menu?.contains(event.target)) close(); });

    if (window.location.hash) {
        const target = document.querySelector(window.location.hash);
        if (target instanceof HTMLDetailsElement) {
            target.open = true;
            requestAnimationFrame(() => target.scrollIntoView({ behavior: 'smooth', block: 'start' }));
        }
    }

    const deliveryDataNode = document.querySelector('#projectDeliveriesData');
    const fixtureFiles = deliveryDataNode ? JSON.parse(deliveryDataNode.textContent || '[]').map(file => ({ ...file, type: file.format.toLowerCase(), label: file.format === 'ZIP' ? 'Explorar ZIP' : `Visualizar ${file.format}` })) : [];
    document.querySelectorAll('.document-row').forEach((row, index) => {
        const fakeButton = row.querySelector('summary button');
        if (fakeButton) fakeButton.remove();
        const expanded = row.querySelector('.document-expanded');
        if (!expanded) return;
        const projectId = new URLSearchParams(window.location.search).get('id') || '1';
        const fixture = fixtureFiles[index];
        if (!fixture) return;
        const action = document.createElement('button');
        action.type = 'button'; action.className = 'document-open-real';
        action.innerHTML = `<i class="fa-regular ${fixture.type === 'zip' ? 'fa-folder-open' : 'fa-eye'}"></i> ${fixture.label}`;
        action.addEventListener('click', () => {
            if (fixture.type === 'zip') {
                window.location.assign(`index.php?page=repository-detail&id=${encodeURIComponent(projectId)}`);
                return;
            }
            openProjectFilePreview(projectId, fixture.path);
        });
        expanded.append(action);
        const name = row.querySelector('.document-name');
        if (name) {
            name.classList.add('is-previewable'); name.setAttribute('role', 'button'); name.tabIndex = 0;
            name.setAttribute('aria-label', `${fixture.label}: ${fixture.file}`);
            const format = document.createElement('em'); format.className = `document-format is-${fixture.type}`; format.textContent = fixture.format; name.append(format);
            const activate = event => { event.preventDefault(); event.stopPropagation(); action.click(); };
            name.addEventListener('click', activate);
            name.addEventListener('keydown', event => { if (event.key === 'Enter' || event.key === ' ') activate(event); });
        }
    });

    function updateStatusColors() {
        document.querySelectorAll('.workspace-status').forEach(status => {
            const value = status.textContent.trim().toLocaleLowerCase('es');
            status.classList.add(value.includes('resuelta') || value.includes('aprob') ? 'status-success' : value.includes('atendida') || value.includes('revisión') ? 'status-info' : value.includes('archiv') ? 'status-muted' : value.includes('cambio') ? 'status-danger' : 'status-warning');
        });
    }
    updateStatusColors();

    let previewModal = null;
    async function openProjectFilePreview(projectId, path) {
        if (!previewModal) {
            previewModal = document.createElement('div'); previewModal.className = 'project-file-modal'; previewModal.hidden = true;
            previewModal.innerHTML = '<section role="dialog" aria-modal="true" aria-labelledby="projectFileTitle"><header><div><small>Vista previa</small><h2 id="projectFileTitle">Documento</h2></div><button type="button" aria-label="Cerrar"><i class="fa-solid fa-xmark"></i></button></header><div class="project-file-modal-body"></div></section>';
            document.body.append(previewModal);
            previewModal.querySelector('header button').addEventListener('click', closeProjectFilePreview);
            previewModal.addEventListener('click', event => { if (event.target === previewModal) closeProjectFilePreview(); });
        }
        const body = previewModal.querySelector('.project-file-modal-body');
        const title = previewModal.querySelector('h2'); title.textContent = path.split('/').pop();
        body.innerHTML = '<div class="project-file-loading"><i class="fa-solid fa-spinner fa-spin"></i> Preparando vista previa...</div>';
        previewModal.hidden = false; document.body.classList.add('project-file-modal-open');
        try {
            const response = await fetch(`index.php?page=repository-preview&id=${encodeURIComponent(projectId)}&path=${encodeURIComponent(path)}`, { credentials: 'same-origin' });
            const result = await response.json(); const preview = result.data?.preview;
            if (!response.ok || !result.success || !preview || preview.status !== 'ready') throw new Error(preview?.message || result.message || 'No fue posible visualizar el archivo.');
            body.replaceChildren();
            if (preview.preview_type === 'pdf') {
                const frame = document.createElement('iframe'); frame.src = preview.content_url; frame.title = `Vista previa de ${preview.name}`; body.append(frame);
            } else if (preview.preview_type === 'docx') {
                const article = document.createElement('article'); article.className = 'project-docx-preview';
                (preview.blocks || []).forEach(block => { const element = document.createElement(block.type === 'heading' ? 'h3' : 'p'); element.textContent = block.text || ''; article.append(element); }); body.append(article);
            } else throw new Error('Este formato no dispone de vista integrada.');
        } catch (error) {
            body.innerHTML = ''; const message = document.createElement('p'); message.className = 'project-file-error'; message.textContent = error.message; body.append(message);
        }
    }
    function closeProjectFilePreview() { if (!previewModal) return; previewModal.hidden = true; previewModal.querySelector('iframe')?.remove(); document.body.classList.remove('project-file-modal-open'); }
    document.addEventListener('keydown', event => { if (event.key === 'Escape' && previewModal && !previewModal.hidden) closeProjectFilePreview(); });

    const observationData = document.querySelector('#projectObservationsData');
    const observationMaster = document.querySelector('.observation-master-list');
    const observationDetail = document.querySelector('.observation-detail');
    if (observationData && observationDetail) {
        const observations = JSON.parse(observationData.textContent || '[]');
        const params = new URLSearchParams(window.location.search);
        const filter = params.get('filter') || 'pending';
        const statusKey = status => status.toLocaleLowerCase('es').includes('atendida') ? 'addressed' : (status.toLocaleLowerCase('es').includes('resuelta') ? 'resolved' : 'pending');
        document.querySelectorAll('.observation-master nav a').forEach(link => {
            const linkFilter = new URL(link.href).searchParams.get('filter') || 'pending';
            const count = linkFilter === 'all' ? observations.length : observations.filter(item => statusKey(item.status) === linkFilter).length;
            link.textContent = `${link.textContent.trim()} ${count}`;
        });
        const visible = filter === 'all' ? observations : observations.filter(item => statusKey(item.status) === filter);
        let selected = visible.find(item => String(item.id) === params.get('observation')) || visible[0];
        const listHost = observationMaster || document.createElement('div');
        listHost.className = 'observation-master-list';
        listHost.replaceChildren();
        visible.forEach(item => {
            const link = document.createElement('a');
            const next = new URL(window.location.href); next.searchParams.set('observation', item.id);
            link.href = next; link.className = item === selected ? 'is-selected' : '';
            const category = document.createElement('span'); category.textContent = item.category;
            const title = document.createElement('strong'); title.textContent = item.title;
            const date = document.createElement('small'); date.textContent = item.date;
            link.append(category, title, date); listHost.append(link);
        });
        if (!observationMaster) document.querySelector('.observation-master')?.append(listHost);
        observationDetail.replaceChildren();
        if (selected) {
            const header = document.createElement('header');
            const meta = document.createElement('div');
            const status = document.createElement('span'); status.className = 'workspace-status'; status.textContent = selected.status;
            const category = document.createElement('small'); category.textContent = selected.category; meta.append(status, category);
            const date = document.createElement('time'); date.textContent = selected.date; header.append(meta, date);
            const title = document.createElement('h2'); title.textContent = selected.title;
            const copy = document.createElement('p'); copy.textContent = selected.text;
            const facts = document.createElement('dl');
            [['Entrega', selected.delivery], ['Ubicación', selected.location], ['Autor', selected.author]].forEach(([label, value]) => { const wrap = document.createElement('div'); const dt = document.createElement('dt'); dt.textContent = label; const dd = document.createElement('dd'); dd.textContent = value; wrap.append(dt, dd); facts.append(wrap); });
            observationDetail.append(header, title, copy, facts);
            if (selected.responses?.length) {
                const followup = document.createElement('div'); followup.className = 'observation-followup'; const heading = document.createElement('strong'); heading.textContent = 'Seguimiento'; followup.append(heading);
                selected.responses.forEach(response => { const article = document.createElement('article'); const author = document.createElement('b'); author.textContent = response.author; const time = document.createElement('time'); time.textContent = response.date; const text = document.createElement('p'); text.textContent = response.text; article.append(author, time, text); followup.append(article); }); observationDetail.append(followup);
            }
        } else {
            const empty = document.createElement('p'); empty.className = 'workspace-empty-inline'; empty.textContent = 'No hay observaciones en este estado.'; observationDetail.append(empty);
        }
        updateStatusColors();
    }

    const composer = document.querySelector('.conversation-composer');
    if (composer) {
        const notice = document.createElement('p');
        notice.className = 'conversation-readonly';
        notice.innerHTML = '<i class="fa-solid fa-lock"></i> Conversación disponible en modo lectura. La publicación se habilitará al conectar almacenamiento.';
        composer.replaceWith(notice);
    }
    document.querySelector('.activity-view header label')?.remove();
})();
