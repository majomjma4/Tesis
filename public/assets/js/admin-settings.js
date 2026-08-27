(() => {
    const ModalSystem = {
        overlay: null,
        titleEl: null,
        descEl: null,
        warningEl: null,
        cancelBtn: null,
        confirmBtn: null,
        onConfirmCallback: null,
        triggerEl: null,

        init() {
            this.overlay = document.getElementById('asConfirmModal');
            if (!this.overlay) return;

            if (this.overlay.parentElement !== document.body) {
                document.body.appendChild(this.overlay);
            }

            this.titleEl = document.getElementById('asModalTitle');
            this.descEl = document.getElementById('asModalDesc');
            this.warningEl = document.getElementById('asModalWarning');
            this.cancelBtn = document.getElementById('asModalCancelBtn');
            this.confirmBtn = document.getElementById('asModalConfirmBtn');

            if (this.cancelBtn) {
                this.cancelBtn.addEventListener('click', () => this.close());
            }

            this.overlay.addEventListener('click', (e) => {
                if (e.target === this.overlay) this.close();
            });

            this.overlay.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') {
                    e.stopPropagation();
                    this.close();
                    return;
                }
                if (e.key === 'Tab') {
                    const focusables = Array.from(this.overlay.querySelectorAll('button:not([disabled]), [href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'));
                    if (!focusables.length) return;
                    const first = focusables[0];
                    const last = focusables[focusables.length - 1];
                    if (e.shiftKey && document.activeElement === first) {
                        e.preventDefault();
                        last.focus();
                    } else if (!e.shiftKey && document.activeElement === last) {
                        e.preventDefault();
                        first.focus();
                    }
                }
            });

            if (this.confirmBtn) {
                this.confirmBtn.addEventListener('click', () => {
                    const callback = this.onConfirmCallback;
                    this.close();
                    if (typeof callback === 'function') {
                        callback();
                    }
                });
            }
        },

        lockScroll() {
            const scrollbarWidth = window.innerWidth - document.documentElement.clientWidth;
            if (scrollbarWidth > 0) {
                document.body.style.paddingRight = `${scrollbarWidth}px`;
            }
            document.body.classList.add('as-modal-open');
        },

        unlockScroll() {
            document.body.classList.remove('as-modal-open');
            document.body.style.paddingRight = '';
        },

        open({ title = 'Confirmar cambio', description = '', warning = '', onConfirm = null } = {}) {
            if (!this.overlay) this.init();
            if (!this.overlay) return;

            this.triggerEl = document.activeElement;

            if (this.titleEl) this.titleEl.textContent = title;
            if (this.descEl) this.descEl.textContent = description;

            if (this.warningEl) {
                if (warning) {
                    this.warningEl.textContent = warning;
                    this.warningEl.hidden = false;
                } else {
                    this.warningEl.hidden = true;
                }
            }

            this.onConfirmCallback = onConfirm;
            this.overlay.hidden = false;
            this.lockScroll();

            setTimeout(() => {
                if (this.cancelBtn) {
                    this.cancelBtn.focus();
                } else if (this.confirmBtn) {
                    this.confirmBtn.focus();
                }
            }, 50);
        },

        close() {
            if (this.overlay) this.overlay.hidden = true;
            this.unlockScroll();
            this.onConfirmCallback = null;
            if (this.triggerEl && typeof this.triggerEl.focus === 'function') {
                this.triggerEl.focus();
                this.triggerEl = null;
            }
        }
    };

    // Exportar globalmente para reutilización
    window.AppConfirmModal = ModalSystem;

    // Dom Ready Handlers
    document.addEventListener('DOMContentLoaded', () => {
        ModalSystem.init();

        const form = document.querySelector('#settingsForm');
        const example = document.querySelector('#projectCodeExample');
        const prefix = form?.querySelector('[name="project_code_prefixes[thesis]"]');
        const digits = form?.querySelector('[name="project_code_digits"]');
        const submitBtn = document.getElementById('asSubmitBtn');
        const submitIcon = document.getElementById('asSubmitIcon');
        const submitSpinner = document.getElementById('asSubmitSpinner');
        const submitText = document.getElementById('asSubmitText');
        const settingNames = {
            institution_name: 'Nombre de la institución', project_code_prefixes: 'Prefijos de proyectos',
            project_code_digits: 'Dígitos de códigos de proyectos', file_max_mb: 'Límite por archivo',
            file_total_max_mb: 'Límite total por operación', file_extensions_private: 'Formatos de Borrador',
            file_extensions_project: 'Formatos de Documentos del proyecto', file_extensions_support: 'Formatos de Materiales de apoyo',
            temporary_password_days: 'Vigencia de contraseña temporal', temporary_password_force_change: 'Cambio obligatorio',
            retention_users_days: 'Retención de usuarios', retention_projects_days: 'Retención de proyectos', retention_materials_days: 'Retención de materiales',
            notification_trash_retention_days: 'Retención de notificaciones', withdrawn_file_restore_hours: 'Recuperación de archivos retirados',
            academic_period_reversal_hours: 'Reversión de cierre de período', academic_period_reminder_days: 'Aviso de período académico',
            calendar_reminder_days: 'Recordatorios de calendario', session_inactivity_minutes: 'Tiempo de inactividad de sesión', temporary_password: 'Política de contraseña temporal'
        };

        const normalizeText = (val) => String(val || '').trim().replace(/\s+/g, ' ');
        const normalizeNumber = (val) => {
            const num = Number(val);
            return isNaN(num) ? String(val || '').trim() : String(num);
        };
        const normalizePrefixes = () => [...(form?.querySelectorAll('[name^="project_code_prefixes["]') || [])].map(input => input.value.trim().toUpperCase().replace(/[^A-Z]/g, '')).join('|');
        const normalizeCheckbox = (cb) => (cb?.checked ? '1' : '0');

        const settingSnapshot = () => ({
            institution_name: normalizeText(form?.elements.institution_name?.value),
            project_code_prefixes: normalizePrefixes(),
            project_code_digits: normalizeNumber(form?.elements.project_code_digits?.value),
            file_max_mb: normalizeNumber(form?.elements.file_max_mb?.value),
            file_total_max_mb: normalizeNumber(form?.elements.file_total_max_mb?.value),
            temporary_password_days: normalizeNumber(form?.elements.temporary_password_days?.value),
            temporary_password_force_change: normalizeCheckbox(form?.elements.temporary_password_force_change),
            retention_users_days: normalizeNumber(form?.elements.retention_users_days?.value),
            retention_projects_days: normalizeNumber(form?.elements.retention_projects_days?.value),
            retention_materials_days: normalizeNumber(form?.elements.retention_materials_days?.value),
            notification_trash_retention_days: normalizeNumber(form?.elements.notification_trash_retention_days?.value),
            withdrawn_file_restore_hours: normalizeNumber(form?.elements.withdrawn_file_restore_hours?.value),
            academic_period_reversal_hours: normalizeNumber(form?.elements.academic_period_reversal_hours?.value),
            academic_period_reminder_days: normalizeNumber(form?.elements.academic_period_reminder_days?.value),
            calendar_reminder_days: normalizeNumber(form?.elements.calendar_reminder_days?.value),
            session_inactivity_minutes: normalizeNumber(form?.elements.session_inactivity_minutes?.value)
        });

        let initialSettings = settingSnapshot();
        let confirmedSubmit = false;

        const checkDirtyState = () => {
            if (!form || !submitBtn) return [];
            const current = settingSnapshot();
            const changed = Object.keys(current).filter(key => current[key] !== initialSettings[key]);

            const tempPassVal = form.elements.temporary_password?.value || '';
            if (tempPassVal !== '') {
                changed.push('temporary_password');
            }

            const isDirty = changed.length > 0;
            submitBtn.disabled = !isDirty;
            submitBtn.setAttribute('aria-disabled', String(!isDirty));
            return changed;
        };

        // Escuchar eventos en todos los controles del formulario
        form?.querySelectorAll('input, select, textarea').forEach(input => {
            input.addEventListener('input', checkDirtyState);
            input.addEventListener('change', checkDirtyState);
        });

        document.querySelector('[data-toggle-temporary-password]')?.addEventListener('click', event => { const input=form?.elements.temporary_password;if(!input)return;input.type=input.type==='password'?'text':'password';event.currentTarget.setAttribute('aria-label',input.type==='password'?'Mostrar contraseña':'Ocultar contraseña'); });

        // Actualización en vivo del ejemplo de código
        const updateExample = () => {
            if (!example) return;
            const size = Math.max(2, Math.min(6, Number(digits?.value) || 3));
            example.textContent = `${(prefix?.value || 'TIT').toUpperCase()}-${new Date().getFullYear()}-${String(1).padStart(size, '0')}`;
        };

        form?.querySelectorAll('[name^="project_code_prefixes["]').forEach(input => {
            input.addEventListener('input', () => {
                input.value = input.value.toUpperCase().replace(/[^A-Z]/g, '').slice(0, 6);
                updateExample();
                checkDirtyState();
            });
        });
        digits?.addEventListener('input', () => {
            updateExample();
            checkDirtyState();
        });
        updateExample();
        checkDirtyState();

        // Manejador del submit del formulario
        if (form) {
            form.addEventListener('submit', async (e) => {
                e.preventDefault();

                const changedKeys = checkDirtyState();
                if (!changedKeys.length) {
                    return;
                }

                // Validación simple de UI antes de enviar
                let isValid = true;
                form.querySelectorAll('.as-input[required]').forEach(input => {
                    const errorEl = document.getElementById(`error_${input.name.replace(/\[|\]/g, '_')}`);
                    if (!input.value.trim()) {
                        input.classList.add('is-invalid');
                        input.classList.remove('is-valid');
                        if (errorEl) {
                            errorEl.textContent = 'Este campo es obligatorio';
                            errorEl.hidden = false;
                        }
                        isValid = false;
                    } else {
                        input.classList.remove('is-invalid');
                        input.classList.add('is-valid');
                        if (errorEl) errorEl.hidden = true;
                    }
                });

                if (!isValid) {
                    window.AppToast?.warning('Revisa los campos requeridos antes de continuar.');
                    return;
                }
                if (Number(form.elements.file_total_max_mb?.value || 0) < Number(form.elements.file_max_mb?.value || 0)) {
                    window.AppToast?.warning('El límite total por operación no puede ser menor que el tamaño máximo por archivo.');
                    return;
                }

                if (!confirmedSubmit) {
                    const hasTempPasswordChange = changedKeys.includes('temporary_password');
                    const onlyInstitution = changedKeys.length === 1 && changedKeys[0] === 'institution_name';

                    let modalTitle = 'Confirmar cambios de configuración';
                    let modalDesc = `Se actualizarán: ${changedKeys.map(key => settingNames[key] || key).join(', ')}. Los cambios de códigos y límites solo afectarán futuras operaciones.`;
                    let modalWarning = '';

                    if (hasTempPasswordChange && changedKeys.length === 1) {
                        modalTitle = 'Cambiar contraseña temporal';
                        modalDesc = 'La nueva contraseña se utilizará únicamente en futuros accesos y restablecimientos.';
                        modalWarning = 'Las contraseñas temporales ya emitidas no serán modificadas.';
                    } else if (onlyInstitution) {
                        modalTitle = 'Confirmar cambio institucional';
                        modalDesc = 'El nuevo nombre de la institución se utilizará en las secciones y documentos que consumen esta configuración.';
                    } else if (hasTempPasswordChange) {
                        modalWarning = 'Atención: Se incluye la actualización de la contraseña temporal. Las contraseñas ya emitidas no serán modificadas.';
                    }

                    ModalSystem.open({
                        title: modalTitle,
                        description: modalDesc,
                        warning: modalWarning,
                        onConfirm: () => { confirmedSubmit = true; form.requestSubmit(); }
                    });
                    return;
                }
                confirmedSubmit = false;

                // Estado de carga (Loading)
                if (submitBtn) submitBtn.disabled = true;
                if (submitIcon) submitIcon.hidden = true;
                if (submitSpinner) submitSpinner.hidden = false;
                if (submitText) submitText.textContent = 'Guardando...';

                try {
                    const response = await fetch(form.action, {
                        method: 'POST',
                        body: new FormData(form)
                    });
                    const result = await response.json();

                    if (!response.ok || !result.success) {
                        throw new Error(result.message || 'No fue posible guardar la configuración.');
                    }

                    window.AppToast?.success(result.message || 'Configuración actualizada correctamente.');
                    if (form.elements.temporary_password) form.elements.temporary_password.value = '';
                    initialSettings = settingSnapshot();
                    checkDirtyState();
                } catch (error) {
                    window.AppToast?.error(error.message || 'Ocurrió un error al guardar la configuración.');
                    checkDirtyState();
                } finally {
                    if (submitIcon) submitIcon.hidden = false;
                    if (submitSpinner) submitSpinner.hidden = true;
                    if (submitText) submitText.textContent = 'Guardar configuración';
                }
            });
        }
    });
})();
