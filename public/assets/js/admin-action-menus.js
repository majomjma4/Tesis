// Posiciona los menús de opciones sobre tablas y contenedores con scroll.
(() => {
    const menus = [...document.querySelectorAll('.user-actions')].filter(menu => !menu.closest('details'));
    if (!menus.length) return;
    const close = () => menus.forEach(menu => {
        menu.hidden = true;
        menu.previousElementSibling?.setAttribute('aria-expanded', 'false');
    });
    const position = (menu, button) => {
        const rect = button.getBoundingClientRect(), margin = 8, width = Math.min(220, window.innerWidth - margin * 2);
        menu.style.width = `${width}px`;
        menu.style.left = `${Math.max(margin, Math.min(rect.right - width, window.innerWidth - width - margin))}px`;
        menu.style.top = `${rect.bottom + 6}px`;
        button.setAttribute('aria-expanded', 'true');
        requestAnimationFrame(() => {
            const menuRect = menu.getBoundingClientRect();
            if (menuRect.bottom > window.innerHeight - margin) menu.style.top = `${Math.max(margin, rect.top - menuRect.height - 6)}px`;
        });
    };
    menus.forEach(menu => {
        const button = menu.previousElementSibling;
        if (!button?.classList.contains('user-actions-button')) return;
        button.setAttribute('aria-haspopup', 'menu');
        button.setAttribute('aria-expanded', 'false');
        menu.setAttribute('role', 'menu');
        menu.querySelectorAll('button').forEach(item => item.setAttribute('role', 'menuitem'));
        button.addEventListener('click', event => {
            event.stopPropagation();
            const opening = menu.hidden;
            close();
            if (!opening) return;
            menu.hidden = false;
            position(menu, button);
        });
    });
    document.addEventListener('click', event => {
        if (!event.target.closest('.user-actions')) close();
    });
    window.addEventListener('scroll', close, true);
    window.addEventListener('resize', close);
})();

// El acceso global de la cabecera abre el formulario administrativo sin duplicar botones.
(() => {
    if (!document.body.classList.contains('admin-projects-page')) return;
    const url = new URL(window.location.href);
    if (url.searchParams.get('action') !== 'new') return;
    const modal = document.querySelector('#apModal');
    const form = document.querySelector('#apForm');
    if (!modal || !form) return;
    form.reset();
    form.elements.id.value = '';
    document.querySelector('#apTitle').textContent = 'Nuevo proyecto';
    modal.hidden = false;
    form.elements.title.focus();
    const clearAction = () => {
        const clean = new URL(window.location.href);
        clean.searchParams.delete('action');
        history.replaceState(null, '', clean);
    };
    form.addEventListener('submit', clearAction, { capture: true });
    modal.querySelectorAll('[data-close]').forEach(button => button.addEventListener('click', clearAction));
})();
