(() => {
    const refreshButton = document.querySelector('#dashboardRefresh');
    const status = document.querySelector('#dashboardUpdateStatus');
    const updateMeta = document.querySelector('.dashboard-update-meta');
    const toast = document.querySelector('#dashboardFreshToast');
    const refreshFlag = 'adminDashboardRefreshPending';

    // Keep the notification anchored to the viewport, outside animated layout containers.
    if (toast && toast.parentElement !== document.body) document.body.append(toast);

    function showFreshToast() {
        if (!toast) return;
        toast.hidden = false;
        requestAnimationFrame(() => toast.classList.add('is-visible'));
        window.setTimeout(() => {
            toast.classList.remove('is-visible');
            window.setTimeout(() => { toast.hidden = true; }, 220);
        }, 3200);
    }

    try {
        if (sessionStorage.getItem(refreshFlag) === '1') {
            sessionStorage.removeItem(refreshFlag);
            if (updateMeta?.dataset.updateOk === 'true') showFreshToast();
        }
    } catch {}

    refreshButton?.addEventListener('click', () => {
        if (refreshButton.disabled) return;
        refreshButton.disabled = true;
        refreshButton.setAttribute('aria-busy', 'true');
        refreshButton.querySelector('i')?.classList.add('is-spinning');
        if (status) {
            status.classList.remove('is-error');
            status.textContent = 'Actualizando datos…';
        }
        try { sessionStorage.setItem(refreshFlag, '1'); } catch {}
        window.setTimeout(() => window.location.reload(), 450);
    });
})();
