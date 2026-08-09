(() => {
    const cfg = document.querySelector('#atConfig');
    if (!cfg) return;

    const send = async (url, data) => {
        const response = await fetch(url, { method: 'POST', body: data });
        const json = await response.json();
        if (!response.ok || !json.success) throw new Error(json.message);
        return json;
    };

    document.querySelectorAll('[data-tab]').forEach(button => button.addEventListener('click', () => {
        document.querySelectorAll('[data-tab]').forEach(item => item.classList.toggle('active', item === button));
        document.querySelectorAll('[data-panel]').forEach(item => item.classList.toggle('active', item.dataset.panel === button.dataset.tab));
    }));

    document.querySelector('#atUserForm')?.addEventListener('submit', async event => {
        event.preventDefault();
        if (!confirm('La cuenta perderá el acceso inmediatamente. ¿Continuar?')) return;
        try {
            await send(cfg.dataset.user, new FormData(event.currentTarget));
            location.reload();
        } catch (error) {
            alert(error.message);
        }
    });

    document.querySelectorAll('[data-restore]').forEach(button => button.addEventListener('click', async () => {
        const data = new FormData();
        data.set('_csrf', cfg.dataset.csrf);
        data.set('entity', button.dataset.entity);
        data.set('id', button.dataset.id);
        try {
            await send(cfg.dataset.restore, data);
            location.reload();
        } catch (error) {
            alert(error.message);
        }
    }));

    document.querySelector('#atPurge')?.addEventListener('click', async () => {
        const usersDays = Number(cfg.dataset.usersRetention || 60);
        const projectsDays = Number(cfg.dataset.projectsRetention || 60);
        const message = `Se procesarán usuarios que hayan superado el plazo configurado de ${usersDays} ${usersDays === 1 ? 'día' : 'días'} y proyectos que hayan superado el plazo configurado de ${projectsDays} ${projectsDays === 1 ? 'día' : 'días'}. ¿Continuar?`;
        if (!confirm(message)) return;
        const data = new FormData();
        data.set('_csrf', cfg.dataset.csrf);
        try {
            await send(cfg.dataset.purge, data);
            location.reload();
        } catch (error) {
            alert(error.message);
        }
    });
})();
