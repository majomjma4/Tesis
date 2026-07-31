(() => {
    const modal = document.querySelector('#aaModal');
    const confirmBox = document.querySelector('#aaConfirm');
    const form = document.querySelector('#aaForm');
    const config = document.querySelector('#aaConfig');
    const message = document.querySelector('#aaMessage');
    const datePicker = form?.querySelector('[data-date-picker]');
    const dateHeading = datePicker?.querySelector('[data-date-heading]');
    const dateDays = datePicker?.querySelector('[data-date-days]');
    const closurePending = confirmBox?.querySelector('[data-closure-pending]');
    const closurePendingList = confirmBox?.querySelector('[data-closure-pending-list]');
    const confirmAccept = confirmBox?.querySelector('[data-accept-confirm]');
    const confirmCancel = confirmBox?.querySelector('[data-cancel-confirm]');
    const toast = document.querySelector('#aaToast');
    const tooltip = document.querySelector('#aaTooltip');
    const accordions = [...document.querySelectorAll('[data-aa-accordion]')];
    if (!modal || !confirmBox || !form || !config) return;

    const restoredCatalog = sessionStorage.getItem('academicOpenCatalog');
    sessionStorage.removeItem('academicOpenCatalog');
    const setAccordionOpen = (accordion, open) => {
        const toggle = accordion.querySelector('[data-aa-accordion-toggle]');
        const panel = accordion.querySelector('[data-aa-accordion-panel]');
        if (!toggle || !panel) return;
        accordion.classList.toggle('is-open', open);
        toggle.setAttribute('aria-expanded', String(open));
        panel.setAttribute('aria-hidden', String(!open));
        panel.inert = !open;
    };
    accordions.forEach(accordion => {
        setAccordionOpen(accordion, accordion.dataset.catalog === restoredCatalog);
        accordion.querySelector('[data-aa-accordion-toggle]')?.addEventListener('click', () => {
            setAccordionOpen(accordion, !accordion.classList.contains('is-open'));
        });
    });
    const rememberCatalog = entity => {
        if (entity && entity !== 'period') sessionStorage.setItem('academicOpenCatalog', entity);
    };

    let tooltipTimer = null;
    let tooltipTarget = null;
    const hideTooltip = () => {
        window.clearTimeout(tooltipTimer);
        tooltipTimer = null;
        if (tooltipTarget) tooltipTarget.removeAttribute('aria-describedby');
        tooltipTarget = null;
        if (tooltip) tooltip.hidden = true;
    };
    const positionTooltip = target => {
        if (!tooltip || tooltip.hidden) return;
        const rect = target.getBoundingClientRect();
        const tooltipRect = tooltip.getBoundingClientRect();
        const top = rect.top - tooltipRect.height - 8 >= 8
            ? rect.top - tooltipRect.height - 8
            : rect.bottom + 8;
        const left = Math.min(
            Math.max(8, rect.left + (rect.width - tooltipRect.width) / 2),
            window.innerWidth - tooltipRect.width - 8
        );
        tooltip.style.top = `${top}px`;
        tooltip.style.left = `${left}px`;
    };
    const showTooltip = target => {
        if (!tooltip || !target.dataset.aaTooltip) return;
        hideTooltip();
        tooltipTarget = target;
        tooltip.textContent = target.dataset.aaTooltip;
        tooltip.hidden = false;
        target.setAttribute('aria-describedby', tooltip.id);
        positionTooltip(target);
    };
    document.querySelectorAll('[data-aa-tooltip]').forEach(target => {
        target.addEventListener('mouseenter', () => {
            window.clearTimeout(tooltipTimer);
            tooltipTimer = window.setTimeout(() => showTooltip(target), 800);
        });
        target.addEventListener('mouseleave', hideTooltip);
        target.addEventListener('focus', () => showTooltip(target));
        target.addEventListener('blur', hideTooltip);
    });

    const layers = [modal, confirmBox];
    layers.forEach(layer => {
        layer.hidden = true;
        document.body.append(layer);
    });
    let pendingAction = null;
    let activeDateName = null;
    let visibleDateMonth = new Date();

    const parseDate = value => {
        const match = String(value || '').match(/^(\d{4})-(\d{2})-(\d{2})$/);
        return match ? new Date(Number(match[1]), Number(match[2]) - 1, Number(match[3])) : null;
    };
    const dateKey = date => `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
    const displayDate = value => {
        const date = parseDate(value);
        return date ? new Intl.DateTimeFormat('es-EC', { day: '2-digit', month: '2-digit', year: 'numeric' }).format(date) : '';
    };
    const valueField = name => form.querySelector(`[data-date-value="${name}"]`);
    const displayField = name => form.querySelector(`[data-date-display="${name}"]`);
    const syncDateField = name => {
        const value = valueField(name)?.value || '';
        const display = displayField(name);
        if (display) display.value = displayDate(value);
    };
    const minimumDate = name => {
        if (name === 'ends_on') {
            const start = parseDate(valueField('starts_on')?.value);
            if (start) {
                start.setDate(start.getDate() + 1);
                return start;
            }
        }
        return parseDate(valueField(name)?.dataset.minDate);
    };
    const closeDatePicker = () => {
        if (!datePicker) return;
        datePicker.hidden = true;
        activeDateName = null;
    };
    const positionDatePicker = () => {
        if (!datePicker || !activeDateName || datePicker.hidden) return;
        const anchor = displayField(activeDateName)?.closest('.aa-date-control');
        if (!anchor) return;
        const rect = anchor.getBoundingClientRect();
        const pickerRect = datePicker.getBoundingClientRect();
        const top = rect.bottom + 8 + pickerRect.height <= window.innerHeight - 12
            ? rect.bottom + 8
            : Math.max(12, rect.top - pickerRect.height - 8);
        datePicker.style.top = `${top}px`;
        datePicker.style.left = `${Math.min(Math.max(12, rect.left), window.innerWidth - pickerRect.width - 12)}px`;
    };
    const renderDatePicker = () => {
        if (!datePicker || !dateHeading || !dateDays || !activeDateName) return;
        dateHeading.textContent = new Intl.DateTimeFormat('es-EC', { month: 'long', year: 'numeric' }).format(visibleDateMonth);
        dateDays.replaceChildren();
        const first = new Date(visibleDateMonth.getFullYear(), visibleDateMonth.getMonth(), 1);
        const offset = (first.getDay() + 6) % 7;
        const cursor = new Date(first);
        cursor.setDate(1 - offset);
        const selected = valueField(activeDateName)?.value || '';
        const minimum = minimumDate(activeDateName);
        const today = dateKey(new Date());
        for (let index = 0; index < 42; index += 1) {
            const day = new Date(cursor);
            const key = dateKey(day);
            const button = document.createElement('button');
            button.type = 'button';
            button.textContent = String(day.getDate());
            button.dataset.date = key;
            button.classList.toggle('is-outside', day.getMonth() !== visibleDateMonth.getMonth());
            button.classList.toggle('is-selected', key === selected);
            button.classList.toggle('is-today', key === today);
            button.disabled = Boolean(minimum && day < minimum);
            dateDays.append(button);
            cursor.setDate(cursor.getDate() + 1);
        }
        requestAnimationFrame(positionDatePicker);
    };
    const openDatePicker = name => {
        if (!datePicker) return;
        activeDateName = name;
        const selected = parseDate(valueField(name)?.value);
        const minimum = minimumDate(name);
        const base = selected || minimum || new Date();
        visibleDateMonth = new Date(base.getFullYear(), base.getMonth(), 1);
        datePicker.hidden = false;
        renderDatePicker();
    };

    const syncDialogState = () => document.body.classList.toggle('aa-dialog-open', layers.some(layer => !layer.hidden));
    const setValue = (name, value) => {
        const field = form.elements.namedItem(name);
        if (!field) return;
        field.value = String(value ?? '');
        field.dispatchEvent(new Event('change', { bubbles: true }));
        syncDateField(name);
    };
    const showFields = entity => {
        form.querySelectorAll('[data-fields]').forEach(section => {
            const active = section.dataset.fields === entity;
            section.hidden = !active;
            section.querySelectorAll('input, select, textarea, button').forEach(control => {
                control.disabled = !active;
            });
        });
    };
    const closeModal = () => {
        closeDatePicker();
        modal.hidden = true;
        message.hidden = true;
        syncDialogState();
    };
    const closeConfirm = () => {
        confirmBox.hidden = true;
        pendingAction = null;
        syncDialogState();
    };
    const openModal = (entity, values = {}) => {
        form.reset();
        message.hidden = true;
        setValue('entity', entity);
        setValue('id', values.id || '');
        setValue('action', 'save');
        showFields(entity);
        if (entity === 'period') {
            document.querySelector('#aaTitle').textContent = values.id
                ? 'Editar planificación'
                : (config.dataset.currentPeriod ? `Planificar ${config.dataset.suggestedName}` : 'Configurar primer período');
            document.querySelector('#aaModalEyebrow').textContent = 'Continuidad académica';
            setValue('starts_on', values.start || '');
            setValue('ends_on', values.end || '');
            document.querySelector('#aaSubmit').textContent = 'Guardar planificación';
        } else {
            const labels = {
                type: ['tipo de proyecto', 'Catálogo de proyectos'],
                material_type: ['tipo de material', 'Materiales de apoyo'],
                keyword: ['palabra clave', 'Materiales de apoyo'],
            };
            const [label, eyebrow] = labels[entity] || labels.type;
            document.querySelector('#aaTitle').textContent = `${values.id ? 'Editar' : 'Agregar'} ${label}`;
            document.querySelector('#aaModalEyebrow').textContent = eyebrow;
            setValue('name', values.name || '');
            document.querySelector('#aaSubmit').textContent = 'Guardar';
        }
        modal.hidden = false;
        syncDialogState();
        requestAnimationFrame(() => form.querySelector('[data-fields]:not([hidden]) input, [data-fields]:not([hidden]) select')?.focus());
    };
    const openConfirm = (title, text, action, confirmLabel = 'Confirmar', variant = 'warning') => {
        pendingAction = action;
        document.querySelector('#aaConfirmTitle').textContent = title;
        const confirmText = document.querySelector('#aaConfirmText');
        confirmText.textContent = text;
        confirmText.style.whiteSpace = 'pre-line';
        if (closurePending) closurePending.hidden = true;
        if (closurePendingList) closurePendingList.replaceChildren();
        confirmAccept.hidden = false;
        confirmAccept.textContent = confirmLabel;
        confirmAccept.classList.toggle('aa-primary', variant === 'primary');
        confirmAccept.classList.toggle('aa-danger', variant === 'danger');
        confirmAccept.classList.toggle('aa-warning', variant === 'warning');
        confirmBox.dataset.variant = variant;
        const confirmIcon = confirmBox.querySelector(':scope > div > span > i');
        if (confirmIcon) {
            confirmIcon.className = variant === 'primary'
                ? 'fa-solid fa-circle-check'
                : (variant === 'danger' ? 'fa-regular fa-trash-can' : 'fa-solid fa-triangle-exclamation');
            confirmIcon.setAttribute('aria-hidden', 'true');
        }
        confirmCancel.textContent = 'Cancelar';
        confirmBox.hidden = false;
        syncDialogState();
        requestAnimationFrame(() => confirmAccept?.focus());
    };
    const showConfirmError = text => {
        pendingAction = null;
        document.querySelector('#aaConfirmTitle').textContent = 'No fue posible completar la acción';
        document.querySelector('#aaConfirmText').textContent = text;
        confirmAccept.hidden = true;
        confirmCancel.textContent = 'Entendido';
        confirmBox.hidden = false;
        syncDialogState();
        requestAnimationFrame(() => confirmCancel?.focus());
    };
    const showClosurePending = projects => {
        pendingAction = null;
        document.querySelector('#aaConfirmTitle').textContent = 'No es posible cerrar el período académico';
        const confirmText = document.querySelector('#aaConfirmText');
        confirmText.textContent = 'Todavía existen proyectos que no han finalizado su proceso académico.\n\nTodos los proyectos del período deben encontrarse en estado “Publicado” antes de cerrar el período.';
        confirmText.style.whiteSpace = 'pre-line';
        closurePendingList?.replaceChildren(...projects.map(project => {
            const item = document.createElement('li');
            const code = document.createElement('strong');
            const title = document.createElement('span');
            const status = document.createElement('small');
            code.textContent = project.code || 'Sin código';
            title.textContent = project.title || 'Proyecto sin título';
            status.textContent = `Estado: ${project.status_label || project.status || 'Sin estado'}`;
            item.append(code, title, status);
            return item;
        }));
        if (closurePending) closurePending.hidden = false;
        confirmAccept.hidden = true;
        confirmCancel.textContent = 'Entendido';
        confirmBox.hidden = false;
        syncDialogState();
        requestAnimationFrame(() => confirmCancel?.focus());
    };
    const send = async (url, data) => {
        const response = await fetch(url, { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: data });
        const result = await response.json().catch(() => ({ success: false, message: 'La respuesta del servidor no es válida.' }));
        if (!response.ok || !result.success) {
            const error = new Error(result.message || 'No fue posible completar la acción.');
            error.data = result.data || {};
            throw error;
        }
        return result;
    };

    document.querySelectorAll('[data-form="period"]').forEach(button => button.addEventListener('click', () => openModal('period')));
    document.querySelectorAll('[data-form="type"],[data-form="material_type"],[data-form="keyword"]').forEach(button => {
        button.addEventListener('click', () => openModal(button.dataset.form));
    });
    document.querySelector('[data-edit-period]')?.addEventListener('click', event => {
        const button = event.currentTarget;
        openModal('period', {
            id: button.dataset.id,
            start: button.dataset.start,
            end: button.dataset.end,
        });
    });
    document.querySelector('[data-delete-period]')?.addEventListener('click', event => {
        const button = event.currentTarget;
        openConfirm(
            'Eliminar planificación',
            `Se eliminará la planificación de ${button.dataset.name}. El período actual continuará activo.`,
            { kind: 'delete-period', id: button.dataset.id }
        );
    });
    document.querySelectorAll('[data-catalog-edit]').forEach(button => button.addEventListener('click', () => openModal(button.dataset.entity, {
        id: button.dataset.id,
        name: button.dataset.name,
    })));
    const entityLabels = { type: 'tipo de proyecto', material_type: 'tipo de material', keyword: 'palabra clave' };
    document.querySelectorAll('[data-catalog-state]').forEach(button => button.addEventListener('click', () => {
        const activate = button.dataset.action === 'activate';
        openConfirm(
            `${activate ? 'Activar' : 'Desactivar'} ${entityLabels[button.dataset.entity] || 'registro'}`,
            activate
                ? 'Este registro volverá a estar disponible para nuevas selecciones.'
                : 'Este registro dejará de estar disponible para nuevas selecciones, pero continuará apareciendo en los registros históricos que ya lo utilizan.',
            { kind: 'catalog', entity: button.dataset.entity, action: button.dataset.action, id: button.dataset.id, name: button.dataset.name },
            activate ? 'Activar' : 'Desactivar',
            activate ? 'primary' : 'warning'
        );
    }));
    document.querySelectorAll('[data-catalog-delete]').forEach(button => button.addEventListener('click', () => {
        openConfirm(
            `Eliminar ${entityLabels[button.dataset.entity] || 'registro'}`,
            'Esta acción eliminará definitivamente el registro y no podrá deshacerse.',
            { kind: 'catalog', entity: button.dataset.entity, action: 'delete', id: button.dataset.id, name: button.dataset.name },
            'Eliminar definitivamente',
            'danger'
        );
    }));
    document.querySelector('[data-close-period]')?.addEventListener('click', () => {
        if (!config.dataset.targetPeriod || config.dataset.targetPeriod === '0') return;
        const closesEarly = config.dataset.closeEarly === '1';
        const earlyCloseWarning = closesEarly
            ? '\n\nEl período académico aún no ha alcanzado su fecha de finalización.\n\nSi continúas, el período será cerrado manualmente por decisión administrativa.\n\nUtiliza esta opción únicamente cuando exista una resolución institucional, prórroga finalizada o autorización correspondiente.'
            : '';
        openConfirm(
            'Cerrar período académico',
            `Vas a cerrar el período académico actual.\n\nAl confirmar ocurrirá lo siguiente:\n\n• El período actual cambiará a estado Cerrado.\n• El siguiente período planificado pasará automáticamente a estado Activo.\n• Los nuevos proyectos ya no podrán registrarse en el período que se está cerrando.\n• Los proyectos existentes conservarán su período original.\n\nEsta acción no podrá deshacerse.${earlyCloseWarning}`,
            { kind: 'close-period', target: config.dataset.targetPeriod, early: closesEarly },
            closesEarly ? 'Cerrar de todas formas' : 'Cerrar período'
        );
    });

    form.addEventListener('submit', async event => {
        event.preventDefault();
        const submit = form.querySelector('[type="submit"]');
        if (form.elements.namedItem('entity')?.value === 'period' && (!valueField('starts_on')?.value || !valueField('ends_on')?.value)) {
            message.textContent = 'Selecciona la fecha de inicio y la fecha de finalización.';
            message.hidden = false;
            return;
        }
        submit.disabled = true;
        message.hidden = true;
        try {
            const result = await send(config.dataset.save, new FormData(form));
            sessionStorage.setItem('academicToast', result.message || 'Información académica guardada correctamente.');
            rememberCatalog(form.elements.namedItem('entity')?.value);
            location.reload();
        } catch (error) {
            message.textContent = error.message;
            message.hidden = false;
            submit.disabled = false;
        }
    });

    confirmBox.querySelector('[data-accept-confirm]')?.addEventListener('click', async event => {
        if (!pendingAction) return;
        const button = event.currentTarget;
        const action = pendingAction;
        button.disabled = true;
        const data = new FormData();
        data.set('_csrf', config.dataset.csrf);
        try {
            if (action.kind === 'close-period') {
                data.set('target_period_id', action.target);
                if (action.early) data.set('confirm_early_close', '1');
                const result = await send(config.dataset.promote, data);
                sessionStorage.setItem('academicToast', result.message || 'Período actualizado correctamente.');
            } else if (action.kind === 'delete-period') {
                data.set('entity', 'period');
                data.set('action', 'delete');
                data.set('id', action.id);
                const result = await send(config.dataset.save, data);
                sessionStorage.setItem('academicToast', result.message || 'Planificación eliminada correctamente.');
            } else if (action.kind === 'catalog') {
                data.set('entity', action.entity);
                data.set('action', action.action);
                data.set('id', action.id);
                data.set('name', action.name || '');
                const result = await send(config.dataset.save, data);
                sessionStorage.setItem('academicToast', result.message || 'Catálogo actualizado correctamente.');
                rememberCatalog(action.entity);
            }
            location.reload();
        } catch (error) {
            if (action.kind === 'close-period' && error.data?.reason === 'unfinished_projects') {
                showClosurePending(Array.isArray(error.data.pending_projects) ? error.data.pending_projects : []);
            } else {
                closeConfirm();
                showConfirmError(error.message);
            }
            button.disabled = false;
        }
    });

    modal.querySelectorAll('[data-close]').forEach(button => button.addEventListener('click', closeModal));
    form.querySelectorAll('[data-open-date]').forEach(button => button.addEventListener('click', () => openDatePicker(button.dataset.openDate)));
    form.querySelectorAll('[data-date-display]').forEach(input => input.addEventListener('click', () => openDatePicker(input.dataset.dateDisplay)));
    datePicker?.querySelector('[data-date-prev]')?.addEventListener('click', () => {
        visibleDateMonth = new Date(visibleDateMonth.getFullYear(), visibleDateMonth.getMonth() - 1, 1);
        renderDatePicker();
    });
    datePicker?.querySelector('[data-date-next]')?.addEventListener('click', () => {
        visibleDateMonth = new Date(visibleDateMonth.getFullYear(), visibleDateMonth.getMonth() + 1, 1);
        renderDatePicker();
    });
    dateDays?.addEventListener('click', event => {
        const button = event.target.closest('[data-date]');
        if (!button || button.disabled || !activeDateName) return;
        const selectedName = activeDateName;
        setValue(selectedName, button.dataset.date);
        if (selectedName === 'starts_on') {
            const end = parseDate(valueField('ends_on')?.value);
            const minimumEnd = minimumDate('ends_on');
            if (end && minimumEnd && end < minimumEnd) setValue('ends_on', '');
        }
        closeDatePicker();
    });
    confirmBox.querySelector('[data-cancel-confirm]')?.addEventListener('click', closeConfirm);
    modal.addEventListener('click', event => { if (event.target === modal) closeModal(); });
    confirmBox.addEventListener('click', event => { if (event.target === confirmBox) closeConfirm(); });
    document.addEventListener('click', event => {
        if (!datePicker || datePicker.hidden || datePicker.contains(event.target) || event.target.closest('[data-open-date], [data-date-display]')) return;
        closeDatePicker();
    });
    window.addEventListener('resize', () => {
        positionDatePicker();
        if (tooltipTarget) positionTooltip(tooltipTarget);
    });
    window.addEventListener('scroll', hideTooltip, true);
    document.addEventListener('keydown', event => {
        if (event.key !== 'Escape') return;
        if (datePicker && !datePicker.hidden) closeDatePicker();
        else if (!confirmBox.hidden) closeConfirm();
        else if (!modal.hidden) closeModal();
    });
    syncDialogState();
    const savedToast = sessionStorage.getItem('academicToast');
    if (savedToast && toast) {
        sessionStorage.removeItem('academicToast');
        toast.textContent = savedToast;
        toast.hidden = false;
        window.setTimeout(() => { toast.hidden = true; }, 4200);
    }
})();
