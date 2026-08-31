(() => {
    const root = document.querySelector('[data-teacher-owned-content]');
    if (!root) return;

    const showError = message => {
        if (window.AppToast?.show) window.AppToast.show(message, 'error');
        else console.error(message);
    };

    const confirmationForAction = action => {
        if (action === 'restore') return {
            title: 'Restaurar contenido',
            message: 'Este contenido volverá a su ubicación correspondiente en el repositorio.',
            confirmText: 'Restaurar',
            danger: false
        };
        if (action === 'purge') return {
            title: 'Eliminar definitivamente',
            message: 'Esta acción eliminará definitivamente el contenido y sus archivos. No podrás recuperarlo después.',
            confirmText: 'Eliminar definitivamente',
            danger: true
        };
        const copy = {
            availability: ['Confirmar disponibilidad', '¿Quieres volver a hacer disponible este contenido?'],
            publication: ['Confirmar publicación', '¿Quieres reincorporar este contenido al Repositorio?'],
            trash: ['Enviar a la Papelera', '¿Quieres enviar este contenido a la Papelera?']
        }[action] || ['Confirmar acción', '¿Quieres completar esta acción?'];
        return { title: copy[0], message: copy[1], confirmText: 'Confirmar', danger: action === 'trash' };
    };

    const readJson = async response => {
        const result = await response.json().catch(() => ({}));
        if (!response.ok || !result.success) {
            throw new Error(result.message || 'No fue posible completar la acción.');
        }
        return result;
    };

    const reloadWithToast = message => {
        sessionStorage.setItem('repositoryToast', message || 'Contenido actualizado correctamente.');
        window.location.reload();
    };

    // main.js monta los overlays de modal directamente bajo document.body para
    // conservar su centrado respecto al viewport, por lo que no pertenecen al
    // root del panel aunque se declaren junto a él en la vista.
    const confirmationModal = document.querySelector('[data-teacher-trash-confirm]');
    const confirmationTitle = confirmationModal?.querySelector('[data-teacher-trash-confirm-title]');
    const confirmationMessage = confirmationModal?.querySelector('[data-teacher-trash-confirm-message]');
    const confirmationCancel = confirmationModal?.querySelector('[data-teacher-trash-confirm-cancel]');
    const confirmationSubmit = confirmationModal?.querySelector('[data-teacher-trash-confirm-submit]');
    let pendingConfirmation = null;
    let confirmationProcessing = false;
    let confirmationReturnFocus = null;

    const hideConfirmation = restoreFocus => {
        if (confirmationModal) confirmationModal.hidden = true;
        document.body.classList.remove('modal-open');
        const opener = confirmationReturnFocus;
        pendingConfirmation = null;
        confirmationReturnFocus = null;
        confirmationProcessing = false;
        if (restoreFocus && opener?.isConnected) opener.focus();
    };

    const closeConfirmation = () => {
        if (confirmationProcessing) return;
        hideConfirmation(true);
    };

    const openConfirmation = ({ title, message, confirmText = 'Confirmar', danger = false, onConfirm }, opener) => {
        if (!confirmationModal || !confirmationSubmit || typeof onConfirm !== 'function') {
            showError('No fue posible abrir la confirmaci\u00f3n.');
            return false;
        }
        pendingConfirmation = { onConfirm };
        confirmationReturnFocus = opener;
        if (confirmationTitle) confirmationTitle.textContent = title;
        if (confirmationMessage) confirmationMessage.textContent = message;
        confirmationSubmit.className = `notification-action ${danger ? 'danger' : 'primary'}`;
        confirmationSubmit.textContent = confirmText;
        confirmationSubmit.disabled = false;
        if (confirmationCancel) confirmationCancel.disabled = false;
        confirmationModal.hidden = false;
        document.body.classList.add('modal-open');
        confirmationSubmit.focus();
        return true;
    };

    const postAction = async (button, confirmed = false) => {
        const action = String(button.dataset.teacherRepositoryAction || '');
        const kind = String(button.dataset.teacherRepositoryKind || '');
        const id = String(button.dataset.teacherRepositoryId || '');
        const endpoint = String(button.dataset.teacherRepositoryEndpoint || '');
        if (!endpoint || !id || !['project', 'material'].includes(kind) || !action) return;
        if (!confirmed) {
            const copy = confirmationForAction(action);
            openConfirmation({ ...copy, onConfirm: () => postAction(button, true) }, button);
            return;
        }
        let reason = '';
        if (action === 'trash') {
            reason = String(window.prompt('Indica el motivo (mínimo 5 caracteres):', '') ?? '').trim();
            if (reason.length < 5) {
                showError('Indica un motivo de al menos cinco caracteres.');
                return;
            }
        }

        const data = new URLSearchParams({ _csrf: String(root.dataset.csrf || '') });
        if (['restore', 'purge'].includes(action)) {
            // repository-teacher-trash usa el mismo contrato que las acciones
            // masivas: kind + operation + ids[].
            data.set('kind', kind);
            data.set('operation', action);
            data.append('ids[]', id);
        } else {
            data.set('action', action);
            data.set('reason', reason);
            if (kind === 'project') data.set('project_id', id);
            else data.set('material_id', id);
            if (action === 'availability') data.set('is_available', String(button.dataset.teacherRepositoryAvailable || ''));
            if (action === 'publication') data.set('status', String(button.dataset.teacherRepositoryStatus || ''));
        }

        button.disabled = true;
        button.setAttribute('aria-busy', 'true');
        try {
            const response = await fetch(endpoint, {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                body: data,
                credentials: 'same-origin'
            });
            const result = await readJson(response);
            reloadWithToast(result.message);
        } catch (error) {
            showError(error instanceof Error ? error.message : 'No fue posible completar la acción.');
            button.disabled = false;
            button.removeAttribute('aria-busy');
        }
    };

    const selectedTrashItems = () => [...root.querySelectorAll('[data-teacher-trash-select]:checked')]
        .map(input => ({ kind: String(input.dataset.kind || ''), id: String(input.dataset.id || '') }))
        .filter(item => ['project', 'material'].includes(item.kind) && /^[1-9][0-9]*$/.test(item.id));

    const updateTrashSelection = () => {
        const inputs = [...root.querySelectorAll('[data-teacher-trash-select]')];
        const selected = selectedTrashItems();
        const all = root.querySelector('[data-teacher-trash-select-all]');
        if (all) {
            all.checked = inputs.length > 0 && selected.length === inputs.length;
            all.indeterminate = selected.length > 0 && selected.length < inputs.length;
        }
        const count = root.querySelector('[data-teacher-trash-selection-count]');
        if (count) count.textContent = selected.length + ' seleccionado' + (selected.length === 1 ? '' : 's');
        root.querySelectorAll('[data-teacher-trash-bulk-action]').forEach(button => {
            button.disabled = selected.length === 0 || button.getAttribute('aria-busy') === 'true';
        });
    };

    const postTrashBulk = async (button, confirmed = false, opener = button, emptyTrash = false) => {
        const items = selectedTrashItems();
        const operation = String(button.dataset.teacherTrashBulkAction || '');
        const endpoint = String(root.dataset.teacherTrashEndpoint || '');
        if (!endpoint || !['restore', 'purge'].includes(operation) || items.length === 0) return;
        if (!confirmed) {
            const countLabel = `${items.length} elemento${items.length === 1 ? '' : 's'} seleccionado${items.length === 1 ? '' : 's'}`;
            const copy = emptyTrash
                ? {
                    title: 'Vaciar papelera',
                    message: 'Se eliminarán definitivamente todos los elementos de tu papelera. Esta acción no se puede deshacer.',
                    confirmText: 'Vaciar papelera',
                    danger: true
                }
                : operation === 'restore'
                    ? { title: 'Restaurar seleccionadas', message: `¿Deseas restaurar los ${countLabel}?`, confirmText: 'Restaurar seleccionadas', danger: false }
                    : { title: 'Eliminar seleccionadas', message: `¿Deseas eliminar definitivamente los ${countLabel}? Esta acción no se puede deshacer.`, confirmText: 'Eliminar seleccionadas', danger: true };
            openConfirmation({ ...copy, onConfirm: () => postTrashBulk(button, true, opener, emptyTrash) }, opener);
            return;
        }
        const grouped = new Map();
        items.forEach(item => {
            if (!grouped.has(item.kind)) grouped.set(item.kind, []);
            grouped.get(item.kind).push(item.id);
        });
        root.querySelectorAll('[data-teacher-trash-bulk-action]').forEach(control => {
            control.disabled = true;
            control.setAttribute('aria-busy', 'true');
        });
        try {
            let message = '';
            for (const [kind, ids] of grouped) {
                const data = new URLSearchParams({
                    _csrf: String(root.dataset.csrf || ''),
                    kind,
                    operation
                });
                ids.forEach(id => data.append('ids[]', id));
                const response = await fetch(endpoint, {
                    method: 'POST',
                    headers: { 'Accept': 'application/json', 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                    body: data,
                    credentials: 'same-origin'
                });
                const result = await readJson(response);
                message = result.message || message;
            }
            reloadWithToast(message);
        } catch (error) {
            showError(error instanceof Error ? error.message : 'No fue posible completar la acción.');
            root.querySelectorAll('[data-teacher-trash-bulk-action]').forEach(control => {
                control.removeAttribute('aria-busy');
            });
            updateTrashSelection();
        }
    };

    confirmationSubmit?.addEventListener('click', async () => {
        if (!pendingConfirmation || confirmationProcessing) return;
        const pending = pendingConfirmation;
        confirmationProcessing = true;
        confirmationSubmit.disabled = true;
        if (confirmationCancel) confirmationCancel.disabled = true;
        try {
            await pending.onConfirm();
        } finally {
            hideConfirmation(false);
        }
    });

    confirmationCancel?.addEventListener('click', closeConfirmation);
    confirmationModal?.querySelector('[data-teacher-trash-confirm-close]')?.addEventListener('click', closeConfirmation);
    confirmationModal?.addEventListener('click', event => {
        if (event.target === confirmationModal) closeConfirmation();
    });

    document.addEventListener('keydown', event => {
        if (!confirmationModal || confirmationModal.hidden) return;
        if (event.key === 'Escape') {
            event.preventDefault();
            closeConfirmation();
            return;
        }
        if (event.key !== 'Tab') return;
        const focusable = [...confirmationModal.querySelectorAll('button:not(:disabled)')];
        if (focusable.length === 0) return;
        const first = focusable[0];
        const last = focusable[focusable.length - 1];
        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    });

    root.addEventListener('change', event => {
        const selectAll = event.target.closest?.('[data-teacher-trash-select-all]');
        if (selectAll) {
            root.querySelectorAll('[data-teacher-trash-select]').forEach(input => { input.checked = selectAll.checked; });
            updateTrashSelection();
            return;
        }
        if (event.target.closest?.('[data-teacher-trash-select]')) updateTrashSelection();
    });

    root.addEventListener('click', event => {
        const emptyButton = event.target.closest?.('[data-teacher-trash-empty]');
        if (emptyButton && root.contains(emptyButton)) {
            event.preventDefault();
            root.querySelectorAll('[data-teacher-trash-select]').forEach(input => { input.checked = true; });
            updateTrashSelection();
            const purgeButton = root.querySelector('[data-teacher-trash-bulk-action="purge"]');
            if (purgeButton) postTrashBulk(purgeButton, false, emptyButton, true);
            return;
        }
        const bulkButton = event.target.closest?.('[data-teacher-trash-bulk-action]');
        if (bulkButton && root.contains(bulkButton) && !bulkButton.disabled) {
            event.preventDefault();
            void postTrashBulk(bulkButton);
            return;
        }
        const button = event.target.closest?.('[data-teacher-repository-action]');
        if (!button || !root.contains(button) || button.disabled) return;
        event.preventDefault();
        void postAction(button);
    });

    updateTrashSelection();
})();
