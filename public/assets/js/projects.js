(() => {
    const root = document.querySelector('[data-projects-page]');
    if (!root) return;
    const tabs = [...root.querySelectorAll('[data-project-tab]')];
    const activate = (key, focus = false) => {
        tabs.forEach((tab) => {
            const active = tab.dataset.projectTab === key;
            tab.classList.toggle('active', active);
            tab.setAttribute('aria-selected', String(active));
            tab.tabIndex = active ? 0 : -1;
            root.querySelector(`[data-project-panel="${tab.dataset.projectTab}"]`)?.toggleAttribute('hidden', !active);
            if (active && focus) tab.focus();
        });
    };
    tabs.forEach((tab, index) => {
        tab.addEventListener('click', () => activate(tab.dataset.projectTab));
        tab.addEventListener('keydown', (event) => {
            if (!['ArrowRight', 'ArrowDown', 'ArrowLeft', 'ArrowUp', 'Home', 'End'].includes(event.key)) return;
            event.preventDefault();
            const next = event.key === 'Home' ? 0 : event.key === 'End' ? tabs.length - 1 : (index + (event.key === 'ArrowRight' || event.key === 'ArrowDown' ? 1 : -1) + tabs.length) % tabs.length;
            activate(tabs[next].dataset.projectTab, true);
        });
    });
    const notice = root.querySelector('[data-publish-demo-notice]');
    root.querySelectorAll('[data-publish-demo]').forEach((button) => button.addEventListener('click', () => {
        if (!notice) return;
        notice.hidden = false;
        window.clearTimeout(notice._hideTimer);
        notice._hideTimer = window.setTimeout(() => { notice.hidden = true; }, 5000);
    }));
})();
