(() => {
    // 1. Sistema de Toasts Reutilizable
    const ToastSystem = {
        container: null,
        init() {
            let el = document.getElementById('asToastContainer');
            if (!el) {
                el = document.createElement('div');
                el.id = 'asToastContainer';
                el.className = 'as-toast-container';
                document.body.appendChild(el);
            }
            this.container = el;
        },
        show(type = 'info', title = '', message = '', duration = 4500) {
            if (!this.container) this.init();

            const icons = {
                success: 'fa-circle-check',
                error: 'fa-circle-exclamation',
                warning: 'fa-triangle-exclamation',
                info: 'fa-circle-info'
            };

            const toast = document.createElement('div');
            toast.className = `as-toast as-toast-${type}`;
            toast.innerHTML = `
                <i class="fa-solid ${icons[type] || icons.info} as-toast-icon"></i>
                <div class="as-toast-content">
                    ${title ? `<div class="as-toast-title">${this.escapeHtml(title)}</div>` : ''}
                    <div>${this.escapeHtml(message)}</div>
                </div>
                <button type="button" class="as-toast-close" aria-label="Cerrar notificación">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            `;

            const closeBtn = toast.querySelector('.as-toast-close');
            const removeToast = () => {
                toast.classList.add('is-hiding');
                setTimeout(() => {
                    if (toast.parentNode) toast.parentNode.removeChild(toast);
                }, 200);
            };

            closeBtn.addEventListener('click', removeToast);
            this.container.appendChild(toast);

            if (duration > 0) {
                setTimeout(removeToast, duration);
            }
        },
        escapeHtml(str) {
            const div = document.createElement('div');
            div.textContent = str;
            return div.innerHTML;
        }
    };

    // Exportar globalmente para reutilización en otros scripts si fuera necesario
    window.AppToast = ToastSystem;

    // 2. Modal de Confirmación Reutilizable
    const ModalSystem = {
        overlay: null,
        titleEl: null,
        descEl: null,
        warningEl: null,
        cancelBtn: null,
        confirmBtn: null,
        onConfirmCallback: null,

        init() {
            this.overlay = document.getElementById('asConfirmModal');
            if (!this.overlay) return;

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

            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && this.overlay && !this.overlay.hidden) {
                    this.close();
                }
            });

            if (this.confirmBtn) {
                this.confirmBtn.addEventListener('click', () => {
                    if (typeof this.onConfirmCallback === 'function') {
                        this.onConfirmCallback();
                    }
                    this.close();
                });
            }
        },

        open({ title = 'Confirmar cambio', description = '', warning = '', onConfirm = null } = {}) {
            if (!this.overlay) this.init();
            if (!this.overlay) return;

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
            if (this.confirmBtn) this.confirmBtn.focus();
        },

        close() {
            if (this.overlay) this.overlay.hidden = true;
            this.onConfirmCallback = null;
        }
    };

    // Exportar globalmente para reutilización
    window.AppConfirmModal = ModalSystem;

    // Dom Ready Handlers
    document.addEventListener('DOMContentLoaded', () => {
        ToastSystem.init();
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
            file_total_max_mb: 'Límite total por entrega', file_extensions_private: 'Formatos de Borrador',
            file_extensions_project: 'Formatos de Documentos del proyecto', file_extensions_support: 'Formatos de Materiales de apoyo',
            temporary_password_days: 'Vigencia de contraseña temporal', temporary_password_force_change: 'Cambio obligatorio',
            retention_users_days: 'Retención de usuarios', retention_projects_days: 'Retención de proyectos', retention_materials_days: 'Retención de materiales', temporary_password: 'Política de contraseña temporal'
        };
        const settingSnapshot = () => ({
            institution_name: String(form?.elements.institution_name?.value || '').trim(),
            project_code_prefixes: [...(form?.querySelectorAll('[name^="project_code_prefixes["]') || [])].map(input => input.value.trim().toUpperCase()).join('|'),
            project_code_digits: String(form?.elements.project_code_digits?.value || ''),
            file_max_mb: String(form?.elements.file_max_mb?.value || ''),
            file_total_max_mb: String(form?.elements.file_total_max_mb?.value || ''),
            temporary_password_days: String(form?.elements.temporary_password_days?.value || ''),
            temporary_password_force_change: form?.elements.temporary_password_force_change?.checked ? '1' : '0',
            retention_users_days: String(form?.elements.retention_users_days?.value || ''),
            retention_projects_days: String(form?.elements.retention_projects_days?.value || ''),
            retention_materials_days: String(form?.elements.retention_materials_days?.value || '')
        });
        let initialSettings = settingSnapshot();
        let confirmedSubmit = false;
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
            });
        });
        digits?.addEventListener('input', updateExample);
        updateExample();

        // Manejador del submit del formulario
        if (form) {
            form.addEventListener('submit', async (e) => {
                e.preventDefault();

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
                    ToastSystem.show('warning', 'Validación', 'Revisa los campos requeridos antes de continuar.');
                    return;
                }
                if (Number(form.elements.file_total_max_mb?.value || 0) < Number(form.elements.file_max_mb?.value || 0)) {
                    ToastSystem.show('warning', 'Validación', 'El límite total por operación no puede ser menor que el tamaño máximo por archivo.');
                    return;
                }

                const snapshot = settingSnapshot();
                if (form.elements.temporary_password?.value) snapshot.temporary_password = form.elements.temporary_password.value;
                const changedKeys = Object.keys(snapshot).filter(key => snapshot[key] !== initialSettings[key]);
                if (!changedKeys.length) {
                    ToastSystem.show('info', 'Sin cambios', 'No hay configuraciones nuevas para guardar.');
                    return;
                }
                if (!confirmedSubmit) {
                    const onlyInstitution = changedKeys.length === 1 && changedKeys[0] === 'institution_name';
                    ModalSystem.open({
                        title: onlyInstitution ? 'Confirmar cambio institucional' : 'Confirmar cambios de configuración',
                        description: onlyInstitution
                            ? 'El nuevo nombre de la institución se utilizará en las secciones y documentos que consumen esta configuración.'
                            : `Se actualizarán: ${changedKeys.map(key => settingNames[key]).join(', ')}. Los cambios de códigos y límites solo afectarán futuras operaciones.`,
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

                    ToastSystem.show('success', 'Éxito', result.message || 'Configuración actualizada correctamente.');
                    if (form.elements.temporary_password) form.elements.temporary_password.value = '';
                    initialSettings = settingSnapshot();
                } catch (error) {
                    ToastSystem.show('error', 'Error', error.message || 'Ocurrió un error al guardar la configuración.');
                } finally {
                    if (submitBtn) submitBtn.disabled = false;
                    if (submitIcon) submitIcon.hidden = false;
                    if (submitSpinner) submitSpinner.hidden = true;
                    if (submitText) submitText.textContent = 'Guardar configuración';
                }
            });
        }
    });
})();
