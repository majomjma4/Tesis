(() => {
    const getCfg = () => document.querySelector('#atConfig');
    let isModalProcessing = false;

    // Asegurar que el modal y el contenedor de toasts estén montados en document.body
    const ensureGlobalMounts = () => {
        const overlay = document.querySelector('#atModalOverlay');
        if (overlay && overlay.parentElement !== document.body) {
            document.body.appendChild(overlay);
        }
    };

    // Adapter local: conserva las llamadas del módulo y delega en el toast global.
    const showToast = ({ type = 'info', message, duration } = {}) => {
        if (!message) return;
        window.AppToast?.show(message, type, { duration });
    };

    const escapeHtml = (str) => {
        const div = document.createElement('div');
        div.textContent = str || '';
        return div.innerHTML;
    };

    // Helper de Estado de Carga para Botones (Previene Doble Clic)
    const setButtonLoading = (btn, isLoading, loadingText = '') => {
        if (!btn) return;
        if (isLoading) {
            btn.disabled = true;
            btn.dataset.originalHtml = btn.innerHTML;
            btn.innerHTML = `<i class="fa-solid fa-circle-notch fa-spin" aria-hidden="true"></i> ${loadingText ? `<span>${escapeHtml(loadingText)}</span>` : ''}`;
        } else {
            if (btn.dataset.originalHtml) {
                btn.innerHTML = btn.dataset.originalHtml;
                delete btn.dataset.originalHtml;
            }
            btn.disabled = false;
        }
    };

    // Helper Peticiones HTTP Fetch
    const send = async (url, data) => {
        const response = await fetch(url, { method: 'POST', body: data });
        const json = await response.json().catch(() => null);
        if (!response.ok || !json || !json.success) {
            const msg = json?.message || 'No fue posible completar la operación. Inténtalo nuevamente.';
            throw new Error(msg);
        }
        return json;
    };

    // Modal de Confirmación Visual Reutilizable
    const openModal = ({ title, message, warning, isDanger = true, confirmText = 'Confirmar', cancelText = 'Cancelar', onConfirm }) => {
        ensureGlobalMounts();
        const overlay = document.querySelector('#atModalOverlay');
        if (!overlay) return;

        const titleEl = overlay.querySelector('#atModalTitle');
        const msgEl = overlay.querySelector('#atModalMessage');
        const warnEl = overlay.querySelector('#atModalWarning');
        const iconEl = overlay.querySelector('#atModalIcon');
        const confirmBtn = overlay.querySelector('#atModalConfirm');
        const cancelBtn = overlay.querySelector('#atModalCancel');

        isModalProcessing = false;

        if (titleEl) titleEl.textContent = title || 'Confirmar acción';
        if (msgEl) msgEl.textContent = message || '¿Deseas continuar?';

        if (iconEl) {
            iconEl.className = `at-modal-icon ${isDanger ? '' : 'is-info'}`;
            iconEl.innerHTML = `<i class="fa-solid ${isDanger ? 'fa-triangle-exclamation' : 'fa-circle-info'}" aria-hidden="true"></i>`;
        }

        if (warnEl) {
            if (warning) {
                warnEl.style.display = 'inline-flex';
                warnEl.className = `at-modal-warning ${isDanger ? '' : 'is-info'}`;
                const span = warnEl.querySelector('span');
                if (span) span.textContent = warning;
            } else {
                warnEl.style.display = 'none';
            }
        }

        if (confirmBtn) {
            confirmBtn.className = `at-btn-modal-confirm ${isDanger ? 'is-danger' : ''}`;
            confirmBtn.textContent = confirmText;
            confirmBtn.disabled = false;
            const newConfirm = confirmBtn.cloneNode(true);
            confirmBtn.parentNode.replaceChild(newConfirm, confirmBtn);

            newConfirm.addEventListener('click', async () => {
                if (isModalProcessing) return;
                if (typeof onConfirm === 'function') {
                    const result = onConfirm({
                        setLoading: (isLoading, text) => {
                            isModalProcessing = isLoading;
                            setButtonLoading(newConfirm, isLoading, text || confirmText);
                            if (cancelBtn) cancelBtn.disabled = isLoading;
                        },
                        close: closeModal
                    });
                    if (result instanceof Promise) {
                        try {
                            isModalProcessing = true;
                            setButtonLoading(newConfirm, true, confirmText);
                            if (cancelBtn) cancelBtn.disabled = true;
                            await result;
                        } catch (err) {
                            console.error(err);
                        } finally {
                            isModalProcessing = false;
                            closeModal();
                        }
                    }
                } else {
                    closeModal();
                }
            });
        }

        if (cancelBtn) {
            cancelBtn.textContent = cancelText;
            cancelBtn.disabled = false;
            const newCancel = cancelBtn.cloneNode(true);
            cancelBtn.parentNode.replaceChild(newCancel, cancelBtn);
            newCancel.addEventListener('click', () => {
                if (!isModalProcessing) closeModal();
            });
        }

        overlay.hidden = false;
        document.body.classList.add('at-modal-open');
    };

    const closeModal = () => {
        if (isModalProcessing) return;
        const overlay = document.querySelector('#atModalOverlay');
        if (overlay) overlay.hidden = true;
        document.body.classList.remove('at-modal-open');
    };

    // Listeners globales para cerrar modal al hacer clic en el overlay o con tecla Escape
    const initModalGlobalListeners = () => {
        const overlay = document.querySelector('#atModalOverlay');
        if (overlay && !overlay.dataset.listenersBound) {
            overlay.dataset.listenersBound = 'true';
            overlay.addEventListener('click', (e) => {
                if (e.target === overlay && !isModalProcessing) {
                    closeModal();
                }
            });
        }

        if (!window._atModalEscapeBound) {
            window._atModalEscapeBound = true;
            window.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && !isModalProcessing) {
                    const activeOverlay = document.querySelector('#atModalOverlay:not([hidden])');
                    if (activeOverlay) closeModal();
                }
            });
        }
    };

    // Actualizar Selección Integrada en la Fila de Controles
    const updateSelectionState = () => {
        const checkboxes = document.querySelectorAll('.at-item-checkbox');
        const selectAll = document.querySelector('#atSelectAll');
        const selectAllText = document.querySelector('#atSelectAllText');
        const bulkActions = document.querySelector('#atBulkActions');
        const listControls = document.querySelector('#atListControls');
        const activeList = document.querySelector('.at-list.active');

        const totalItems = checkboxes.length;
        let checkedCount = 0;

        checkboxes.forEach(cb => {
            const card = cb.closest('.at-item-card');
            if (cb.checked) {
                checkedCount++;
                card?.classList.add('is-selected');
            } else {
                card?.classList.remove('is-selected');
            }
        });

        // Mostrar control de "Seleccionar todos" únicamente si existen 2 o más elementos
        if (listControls) {
            listControls.hidden = totalItems < 2;
        }

        if (selectAll) {
            selectAll.checked = totalItems > 0 && checkedCount === totalItems;
            selectAll.indeterminate = checkedCount > 0 && checkedCount < totalItems;
        }

        if (checkedCount === 0) {
            if (selectAllText) selectAllText.textContent = 'Seleccionar todos los visibles';
            if (bulkActions) bulkActions.hidden = true;
            activeList?.classList.remove('has-selection');
        } else {
            if (selectAllText) selectAllText.textContent = `${checkedCount} ${checkedCount === 1 ? 'seleccionado' : 'seleccionados'}`;
            if (bulkActions) bulkActions.hidden = false;
            activeList?.classList.add('has-selection');
        }
    };

    // Limpiar Selección
    const clearSelection = () => {
        document.querySelectorAll('.at-item-checkbox, #atSelectAll').forEach(cb => {
            cb.checked = false;
        });
        updateSelectionState();
    };

    // Actualizar Contadores del DOM
    const updateSummaryCounters = (summary) => {
        if (!summary) return;
        const globalTotal = document.querySelector('#atGlobalTotal');
        if (globalTotal) globalTotal.textContent = summary.total ?? 0;

        document.querySelectorAll('[data-count="users"]').forEach(el => el.textContent = summary.users ?? 0);
        document.querySelectorAll('[data-count="projects"]').forEach(el => el.textContent = summary.projects ?? 0);
        document.querySelectorAll('[data-count="materials"]').forEach(el => el.textContent = summary.materials ?? 0);
    };

    // Cargar Pestaña por AJAX sin recargar la página completa
    const loadTab = async (url, pushState = true) => {
        try {
            const fetchUrl = url + (url.includes('?') ? '&ajax=1' : '?ajax=1');
            const response = await fetch(fetchUrl, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const json = await response.json();
            if (!response.ok || !json || !json.success || !json.data?.html) {
                throw new Error(json?.message || 'No fue posible cargar la pestaña solicitada.');
            }

            const parser = new DOMParser();
            const doc = parser.parseFromString(json.data.html, 'text/html');

            const newContent = doc.querySelector('.at-list');
            const currentContent = document.querySelector('.at-list');
            if (newContent && currentContent) {
                currentContent.replaceWith(newContent);
            }

            const newToolbar = doc.querySelector('.at-toolbar');
            const currentToolbar = document.querySelector('.at-toolbar');
            if (newToolbar && currentToolbar) {
                currentToolbar.replaceWith(newToolbar);
            }

            const newStats = doc.querySelector('.at-stats');
            const currentStats = document.querySelector('.at-stats');
            if (newStats && currentStats) {
                currentStats.replaceWith(newStats);
            }

            updateSummaryCounters(json.data.summary);
            clearSelection();
            bindEvents();

            if (pushState) {
                history.pushState({ type: json.data.active_type, url }, '', url);
            }
        } catch (error) {
            console.error('Error al cambiar de pestaña:', error);
            showToast({
                type: 'error',
                title: 'Error de conexión',
                message: error.message || 'Ocurrió un problema al comunicarse con el servidor. Inténtalo nuevamente.'
            });
        }
    };

    // Obtener nombre formateado de la categoría activa
    const getActiveCategoryName = () => {
        const activeLink = document.querySelector('.at-tab-link.active');
        if (!activeLink) return 'esta categoría';
        const type = activeLink.dataset.tabType;
        if (type === 'users') return 'Usuarios';
        if (type === 'projects') return 'Proyectos';
        if (type === 'materials') return 'Materiales de apoyo';
        return 'esta categoría';
    };

    // Obtener la cantidad de elementos en la vista activa
    const getActiveItemsCount = () => {
        return document.querySelectorAll('.at-item-card').length;
    };

    const selectedIds = () => Array.from(document.querySelectorAll('.at-item-checkbox:checked')).map(item => item.value);
    const activeEntity = () => document.querySelector('.at-item-checkbox')?.dataset.entity || (document.querySelector('.at-list.active')?.dataset.panel === 'materials' ? 'support_material' : document.querySelector('.at-list.active')?.dataset.panel?.slice(0, -1));
    const refreshAfterOperation = async (result) => {
        updateSummaryCounters(result?.data?.summary);
        clearSelection();
        const activeLink = document.querySelector('.at-tab-link.active');
        if (activeLink) await loadTab(activeLink.getAttribute('href'), false);
        else location.reload();
    };
    const submitTrashOperation = async (endpoint, fields) => {
        const cfg = getCfg();
        if (!cfg) throw new Error('No fue posible iniciar la operación. Recarga la página.');
        const data = new FormData(); data.set('_csrf', cfg.dataset.csrf);
        Object.entries(fields).forEach(([key, value]) => Array.isArray(value) ? value.forEach(id => data.append(`${key}[]`, id)) : data.set(key, value));
        const result = await send(endpoint, data); await refreshAfterOperation(result); return result;
    };

    // Vincular todos los listeners interactivos
    const bindEvents = () => {
        ensureGlobalMounts();
        initModalGlobalListeners();
        const cfg = getCfg();

        // Checkbox Seleccionar Todos
        document.querySelector('#atSelectAll')?.addEventListener('change', (e) => {
            const isChecked = e.target.checked;
            document.querySelectorAll('.at-item-checkbox').forEach(cb => {
                cb.checked = isChecked;
            });
            updateSelectionState();
        });

        // Checkboxes Individuales
        document.querySelectorAll('.at-item-checkbox').forEach(cb => {
            cb.addEventListener('change', updateSelectionState);
        });

        // Pestañas (Navegación AJAX)
        document.querySelectorAll('.at-tab-link').forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                const url = link.getAttribute('href');
                if (url) loadTab(url, true);
            });
        });

        // Restaurar Individual (Con Modal de Confirmación previo)
        document.querySelectorAll('[data-restore]').forEach(button => {
            button.addEventListener('click', () => {
                if (!cfg) return;
                const card = button.closest('.at-item-card');
                const title = card?.querySelector('.at-item-info strong')?.textContent?.trim() || 'el elemento seleccionado';

                openModal({
                    title: 'Restaurar elemento',
                    message: `¿Deseas restaurar "${title}"?`,
                    warning: 'El elemento volverá a estar disponible en su módulo correspondiente.',
                    isDanger: false,
                    confirmText: 'Restaurar',
                    onConfirm: async ({ setLoading, close }) => {
                        const data = new FormData();
                        data.set('_csrf', cfg.dataset.csrf);
                        data.set('entity', button.dataset.entity);
                        data.set('id', button.dataset.id);
                        try {
                            setLoading(true, 'Restaurando...');
                            const result = await send(cfg.dataset.restore, data);
                            await refreshAfterOperation(result);
                            close();
                            showToast({
                                type: 'success',
                                title: 'Éxito',
                                message: result.message || 'El elemento fue restaurado correctamente.'
                            });
                        } catch (error) {
                            close();
                            showToast({
                                type: 'error',
                                title: 'Error',
                                message: error.message || 'No fue posible restaurar el elemento. Inténtalo nuevamente.'
                            });
                        }
                    }
                });
            });
        });

        // Eliminar Definitivamente Individual (Modal de Confirmación preparado para Fase 2B)
        document.querySelectorAll('[data-delete-single]').forEach(button => {
            button.addEventListener('click', () => {
                const card = button.closest('.at-item-card');
                const title = button.dataset.title || card?.querySelector('.at-item-info strong')?.textContent?.trim() || 'el elemento seleccionado';

                openModal({
                    title: 'Eliminar definitivamente',
                    message: `¿Deseas eliminar definitivamente "${title}"?`,
                    warning: 'Esta acción no se puede deshacer.',
                    isDanger: true,
                    confirmText: 'Eliminar definitivamente',
                    onConfirm: async ({ setLoading, close }) => {
                        try { setLoading(true, 'Eliminando...'); const result = await submitTrashOperation(cfg.dataset.delete, {entity: button.dataset.entity, id: button.dataset.id}); close(); showToast({type:'success',title:'Éxito',message:result.message}); }
                        catch (error) { close(); showToast({type:'error',title:'Error',message:error.message || 'No fue posible eliminar el elemento.'}); }
                    }
                });
            });
        });

        // Acciones por Categoría (Modales de Confirmación preparados para Fase 2B)
        document.querySelectorAll('[data-category-action]').forEach(btn => {
            btn.addEventListener('click', () => {
                const action = btn.dataset.categoryAction;
                const catName = getActiveCategoryName();
                const count = getActiveItemsCount();

                if (action === 'restore-all') {
                    openModal({
                        title: 'Restaurar todos',
                        message: `¿Deseas restaurar todos los elementos de la categoría ${catName} (${count} ${count === 1 ? 'elemento' : 'elementos'})?`,
                        warning: 'Los elementos volverán a estar activos en sus módulos correspondientes.',
                        isDanger: false,
                        confirmText: 'Restaurar todos',
                        onConfirm: async ({ setLoading, close }) => {
                            try { setLoading(true, 'Restaurando...'); const result = await submitTrashOperation(cfg.dataset.restoreAll, {entity: activeEntity()}); close(); showToast({type:'success',title:'Éxito',message:result.message}); }
                            catch (error) { close(); showToast({type:'error',title:'Error',message:error.message || 'No fue posible restaurar la categoría.'}); }
                        }
                    });
                } else if (action === 'empty-category') {
                    openModal({
                        title: 'Vaciar categoría',
                        message: `Se eliminarán definitivamente todos los elementos de la categoría ${catName} (${count} ${count === 1 ? 'elemento' : 'elementos'}).`,
                        warning: 'Esta acción no se puede deshacer.',
                        isDanger: true,
                        confirmText: 'Vaciar categoría',
                        onConfirm: async ({ setLoading, close }) => {
                            try { setLoading(true, 'Vaciando...'); const result = await submitTrashOperation(cfg.dataset.emptyCategory, {entity: activeEntity()}); close(); showToast({type:'success',title:'Éxito',message:result.message}); }
                            catch (error) { close(); showToast({type:'error',title:'Error',message:error.message || 'No fue posible vaciar la categoría.'}); }
                        }
                    });
                }
            });
        });

        // Acciones de Selección Múltiple (Modales preparados para Fase 2B)
        document.querySelector('#atBulkRestore')?.addEventListener('click', () => {
            const checked = document.querySelectorAll('.at-item-checkbox:checked');
            if (checked.length === 0) return;
            openModal({
                title: 'Restaurar elementos seleccionados',
                message: `¿Deseas restaurar los ${checked.length} elementos seleccionados?`,
                warning: 'Los elementos volverán a estar activos en sus módulos correspondientes.',
                isDanger: false,
                confirmText: 'Restaurar seleccionados',
                onConfirm: async ({ setLoading, close }) => {
                    try { setLoading(true, 'Restaurando...'); const result = await submitTrashOperation(cfg.dataset.restoreBatch, {entity: activeEntity(), ids: selectedIds()}); close(); showToast({type:'success',title:'Éxito',message:result.message}); }
                    catch (error) { close(); showToast({type:'error',title:'Error',message:error.message || 'No fue posible restaurar los elementos.'}); }
                }
            });
        });

        document.querySelector('#atBulkDelete')?.addEventListener('click', () => {
            const checked = document.querySelectorAll('.at-item-checkbox:checked');
            if (checked.length === 0) return;
            openModal({
                title: 'Eliminar elementos seleccionados',
                message: `¿Deseas eliminar definitivamente los ${checked.length} elementos seleccionados?`,
                warning: 'Esta acción no se puede deshacer.',
                isDanger: true,
                confirmText: 'Eliminar definitivamente',
                onConfirm: async ({ setLoading, close }) => {
                    try { setLoading(true, 'Eliminando...'); const result = await submitTrashOperation(cfg.dataset.deleteBatch, {entity: activeEntity(), ids: selectedIds()}); close(); showToast({type:'success',title:'Éxito',message:result.message}); }
                    catch (error) { close(); showToast({type:'error',title:'Error',message:error.message || 'No fue posible eliminar los elementos.'}); }
                }
            });
        });

        updateSelectionState();
    };

    // Manejo de Historial del Navegador (Atrás / Adelante con popstate)
    window.addEventListener('popstate', (e) => {
        if (e.state?.url) {
            loadTab(e.state.url, false);
        } else {
            loadTab(location.href, false);
        }
    });

    // Inicialización al cargar el DOM
    document.addEventListener('DOMContentLoaded', () => {
        ensureGlobalMounts();
        bindEvents();
    });

    ensureGlobalMounts();
    bindEvents();
})();
