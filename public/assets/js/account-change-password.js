(() => {
    const modal = document.querySelector('[data-password-change-modal]');
    const forced = modal?.dataset.passwordForced === '1';
    const dialog = modal?.querySelector('.admin-access-card');
    const closeButtons = modal?.querySelectorAll('[data-password-modal-close]') ?? [];
    const closeVoluntary = () => { if (forced || !modal) return; modal.hidden = true; window.location.assign(modal.dataset.passwordCloseUrl || '/'); };
    if (!forced && modal) {
        closeButtons.forEach((button) => button.addEventListener('click', closeVoluntary));
        modal.addEventListener('click', event => { if (event.target === modal) closeVoluntary(); });
        document.addEventListener('keydown', event => { if (event.key === 'Escape' && !modal.hidden) closeVoluntary(); });
        dialog?.focus();
    }
    const form = document.getElementById('changePasswordForm') || document.getElementById('resetForm');
    const currentInput = document.getElementById('currentPassword');
    const newPassInput = document.getElementById('newPassword') || document.getElementById('password');
    const confirmPassInput = document.getElementById('confirmPassword') || document.getElementById('confirm_password');
    const submitBtn = document.getElementById('submitPasswordBtn') || form?.querySelector('button[type="submit"]');
    const reqList = document.querySelectorAll('.password-req-list li');
    const matchStatus = document.getElementById('passwordMatchStatus');

    // 1. Alternar Visibilidad de Contraseñas (Mostrar / Ocultar) sin alterar el valor
    document.querySelectorAll('.password-toggle-btn').forEach((btn) => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            const wrapper = btn.closest('.password-input-wrapper');
            const input = wrapper?.querySelector('input');
            const icon = btn.querySelector('i');
            if (!input || !icon) return;

            const selectionStart = input.selectionStart;
            const selectionEnd = input.selectionEnd;

            const isPassword = input.type === 'password';
            input.type = isPassword ? 'text' : 'password';

            icon.className = isPassword ? 'fa-solid fa-eye-slash' : 'fa-solid fa-eye';
            btn.setAttribute('aria-label', isPassword ? 'Ocultar contraseña' : 'Mostrar contraseña');

            input.focus();
            if (selectionStart !== null && selectionEnd !== null) {
                try { input.setSelectionRange(selectionStart, selectionEnd); } catch (_) {}
            }
        });
    });

    if (!form || !newPassInput || !confirmPassInput || !submitBtn) return;

    // 2. Evaluación de Requisitos de Complejidad (sin modificar caracteres ni trim)
    const checkRequirements = (val) => {
        const rules = {
            length: val.length >= 8,
            uppercase: /[A-Z]/.test(val),
            lowercase: /[a-z]/.test(val),
            number: /\d/.test(val),
            symbol: /[^A-Za-z0-9]/.test(val),
        };

        let allValid = true;
        reqList.forEach((li) => {
            const reqKey = li.dataset.req;
            const isValid = Boolean(rules[reqKey]);
            if (!isValid) allValid = false;

            li.classList.toggle('is-valid', isValid);
            const iconSpan = li.querySelector('.req-icon');
            if (iconSpan) {
                iconSpan.innerHTML = isValid ? '<i class="fa-solid fa-check"></i>' : '<i class="fa-solid fa-xmark"></i>';
            }
        });

        return allValid;
    };

    // 3. Evaluación de Coincidencia de Confirmación (sin trim)
    const checkMatch = () => {
        if (!confirmPassInput || !matchStatus) return false;
        const confirmVal = confirmPassInput.value;
        const newVal = newPassInput.value;

        if (confirmVal.length === 0) {
            matchStatus.hidden = true;
            matchStatus.innerHTML = '';
            return false;
        }

        matchStatus.hidden = false;
        const matches = confirmVal === newVal && confirmVal.length > 0;
        if (matches) {
            matchStatus.innerHTML = '<span class="is-valid"><i class="fa-solid fa-check"></i> Las contraseñas coinciden</span>';
        } else {
            matchStatus.innerHTML = '<span class="is-invalid"><i class="fa-solid fa-xmark"></i> Las contraseñas no coinciden</span>';
        }
        return matches;
    };

    // 4. Sincronización del Estado del Botón Principal
    const syncSubmitButton = () => {
        const hasCurrent = !currentInput || currentInput.value.length > 0;
        const reqsOk = checkRequirements(newPassInput.value);
        const matchOk = checkMatch();

        const canSubmit = hasCurrent && reqsOk && matchOk;
        submitBtn.disabled = !canSubmit;
        submitBtn.setAttribute('aria-disabled', String(!canSubmit));
    };

    // 5. Escuchar eventos de escritura, pegado (Ctrl+V / menú contextual), corte, cambio y autocompletado
    const events = ['input', 'change', 'paste', 'cut', 'keyup'];
    events.forEach((evtType) => {
        currentInput?.addEventListener(evtType, () => setTimeout(syncSubmitButton, 0));
        newPassInput.addEventListener(evtType, () => setTimeout(syncSubmitButton, 0));
        confirmPassInput.addEventListener(evtType, () => setTimeout(syncSubmitButton, 0));
    });

    // 6. Prevenir Doble Envío y Mostrar Estado de Carga
    form.addEventListener('submit', (e) => {
        if (submitBtn.disabled) {
            e.preventDefault();
            return;
        }
        submitBtn.disabled = true;
        submitBtn.setAttribute('aria-disabled', 'true');
        submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Actualizando...';
    });

    // 7. Limpiar exclusivamente el campo "Contraseña actual" tras respuesta de error del servidor
    const hasServerError = document.querySelector('.admin-access-alert.is-error');
    if (hasServerError && currentInput) {
        currentInput.value = '';
    }

    syncSubmitButton();
})();
