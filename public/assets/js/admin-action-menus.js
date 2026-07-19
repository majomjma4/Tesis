// Posiciona los menús de opciones sobre tablas y contenedores con scroll.
(() => {
    const menus = [...document.querySelectorAll('.user-actions')];
    if (!menus.length) return;
    const close = () => menus.forEach(menu => {
        menu.hidden = true;
        menu.previousElementSibling?.setAttribute('aria-expanded', 'false');
    });
    menus.forEach(menu => {
        const button = menu.previousElementSibling;
        if (!button?.classList.contains('user-actions-button')) return;
        button.setAttribute('aria-haspopup', 'menu');
        button.setAttribute('aria-expanded', 'false');
        menu.setAttribute('role', 'menu');
        menu.querySelectorAll('button').forEach(item => item.setAttribute('role', 'menuitem'));
        button.addEventListener('click', () => setTimeout(() => {
            if (menu.hidden) { button.setAttribute('aria-expanded', 'false'); return; }
            const rect = button.getBoundingClientRect(), margin = 8, width = 220;
            menu.style.left = `${Math.max(margin, Math.min(rect.right - width, window.innerWidth - width - margin))}px`;
            menu.style.top = `${rect.bottom + 6}px`;
            button.setAttribute('aria-expanded', 'true');
            requestAnimationFrame(() => {
                const menuRect = menu.getBoundingClientRect();
                if (menuRect.bottom > window.innerHeight - margin) menu.style.top = `${Math.max(margin, rect.top - menuRect.height - 6)}px`;
            });
        }));
    });
    window.addEventListener('scroll', close, true);
    window.addEventListener('resize', close);
})();
