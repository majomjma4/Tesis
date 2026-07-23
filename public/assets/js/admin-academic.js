(() => {
    const modal = document.querySelector('#aaModal');
    const confirmBox = document.querySelector('#aaConfirm');
    const form = document.querySelector('#aaForm');
    const config = document.querySelector('#aaConfig');
    const message = document.querySelector('#aaMessage');
    const datePicker = form?.querySelector('[data-date-picker]');
    const dateHeading = datePicker?.querySelector('[data-date-heading]');
    const dateDays = datePicker?.querySelector('[data-date-days]');
    if (!modal || !confirmBox || !form || !config) return;

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
        form.querySelector('[data-fields="period"]').hidden = entity !== 'period';
        form.querySelector('[data-fields="type"]').hidden = entity !== 'type';
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
            document.querySelector('#aaTitle').textContent = values.id ? 'Editar tipo de proyecto' : 'Agregar tipo de proyecto';
            document.querySelector('#aaModalEyebrow').textContent = 'Catálogo de proyectos';
            setValue('name', values.name || '');
            document.querySelector('#aaSubmit').textContent = 'Guardar';
        }
        modal.hidden = false;
        syncDialogState();
        requestAnimationFrame(() => form.querySelector('[data-fields]:not([hidden]) input, [data-fields]:not([hidden]) select')?.focus());
    };
    const openConfirm = (title, text, action) => {
        pendingAction = action;
        document.querySelector('#aaConfirmTitle').textContent = title;
        document.querySelector('#aaConfirmText').textContent = text;
        confirmBox.hidden = false;
        syncDialogState();
        requestAnimationFrame(() => confirmBox.querySelector('[data-accept-confirm]')?.focus());
    };
    const send = async (url, data) => {
        const response = await fetch(url, { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: data });
        const result = await response.json().catch(() => ({ success: false, message: 'La respuesta del servidor no es válida.' }));
        if (!response.ok || !result.success) throw new Error(result.message || 'No fue posible completar la acción.');
        return result;
    };

    document.querySelectorAll('[data-form="period"]').forEach(button => button.addEventListener('click', () => openModal('period')));
    document.querySelector('[data-form="type"]')?.addEventListener('click', () => openModal('type'));
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
    document.querySelectorAll('[data-edit-type]').forEach(button => button.addEventListener('click', () => openModal('type', {
        id: button.dataset.id,
        name: button.dataset.name,
    })));
    document.querySelectorAll('[data-deactivate-type]').forEach(button => button.addEventListener('click', () => {
        openConfirm(
            'Desactivar tipo de proyecto',
            `“${button.dataset.name}” dejará de estar disponible para proyectos nuevos. Los proyectos existentes conservarán este tipo.`,
            { kind: 'deactivate', id: button.dataset.id }
        );
    }));
    document.querySelector('[data-close-period]')?.addEventListener('click', () => {
        if (!config.dataset.targetPeriod || config.dataset.targetPeriod === '0') return;
        openConfirm(
            'Cerrar período académico',
            `${config.dataset.currentPeriod} se registrará como cerrado y se activará ${config.dataset.nextPeriod}. Los ${config.dataset.activeProjects} proyectos conservarán su período y su historial; no serán movidos ni eliminados.`,
            { kind: 'close-period', target: config.dataset.targetPeriod }
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
            await send(config.dataset.save, new FormData(form));
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
                await send(config.dataset.promote, data);
            } else if (action.kind === 'delete-period') {
                data.set('entity', 'period');
                data.set('action', 'delete');
                data.set('id', action.id);
                await send(config.dataset.save, data);
            } else {
                data.set('entity', 'type');
                data.set('action', 'deactivate');
                data.set('id', action.id);
                await send(config.dataset.save, data);
            }
            location.reload();
        } catch (error) {
            closeConfirm();
            alert(error.message);
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
    window.addEventListener('resize', positionDatePicker);
    document.addEventListener('keydown', event => {
        if (event.key !== 'Escape') return;
        if (datePicker && !datePicker.hidden) closeDatePicker();
        else if (!confirmBox.hidden) closeConfirm();
        else if (!modal.hidden) closeModal();
    });
    syncDialogState();
})();
