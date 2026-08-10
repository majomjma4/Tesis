(() => {
    const form = document.querySelector('[data-profile-form]');
    const modal = document.getElementById('profileConfirm');
    const password = document.getElementById('profileCurrentPassword');
    const submitButton = document.querySelector('[data-profile-submit]');
    const confirmButton = document.querySelector('[data-profile-confirm]');
    const cancelButton = document.querySelector('[data-profile-cancel]');

    if (!form || !modal || !password || !submitButton || !confirmButton || !cancelButton) return;

    const initial = {
        fullName: String(form.elements.full_name?.value ?? '').trim(),
        email: String(form.elements.email?.value ?? '').trim().toLowerCase(),
    };
    let confirmationAccepted = false;

    const hasChanges = () => (
        String(form.elements.full_name?.value ?? '').trim() !== initial.fullName
        || String(form.elements.email?.value ?? '').trim().toLowerCase() !== initial.email
    );

    const syncSubmitState = () => {
        const changed = hasChanges();
        submitButton.disabled = !changed;
        submitButton.setAttribute('aria-disabled', String(!changed));
    };

    const closeModal = () => {
        modal.hidden = true;
        password.value = '';
        confirmationAccepted = false;
        document.body.classList.remove('profile-modal-open');
    };

    const openModal = () => {
        password.value = '';
        modal.hidden = false;
        document.body.classList.add('profile-modal-open');
        window.setTimeout(() => password.focus(), 0);
    };

    form.addEventListener('input', syncSubmitState);
    form.addEventListener('submit', (event) => {
        if (confirmationAccepted) return;

        event.preventDefault();
        if (!hasChanges()) return;
        if (!form.reportValidity()) return;
        openModal();
    });

    confirmButton.addEventListener('click', () => {
        if (!password.value) {
            password.reportValidity();
            password.focus();
            return;
        }

        confirmationAccepted = true;
        modal.hidden = true;
        document.body.classList.remove('profile-modal-open');
        form.requestSubmit();
    });

    cancelButton.addEventListener('click', closeModal);
    modal.addEventListener('click', (event) => {
        if (event.target === modal) closeModal();
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !modal.hidden) closeModal();
    });

    syncSubmitState();
})();
