<main class="admin-access-shell error-state-shell">
    <section class="admin-access-card is-centered error-state-card" aria-labelledby="notFoundTitle">
        <div class="error-state-code" aria-hidden="true"><span>4</span><i class="fa-regular fa-compass"></i><span>4</span></div>
        <p class="admin-access-eyebrow">Página no encontrada</p>
        <h1 id="notFoundTitle">Parece que esta sección no existe</h1>
        <p>Es posible que el enlace haya cambiado, esté incompleto o que la información solicitada ya no se encuentre disponible.</p>
        <div class="error-state-actions">
            <a class="admin-access-primary" href="<?= e(route('dashboard')) ?>"><i class="fa-solid fa-house"></i> Volver al inicio</a>
            <a class="admin-access-secondary" href="<?= e(route('projects')) ?>"><i class="fa-regular fa-folder-open"></i> Ir a Proyectos</a>
        </div>
        <small>Si llegaste aquí desde una opción de la plataforma, puedes regresar e intentarlo nuevamente.</small>
    </section>
</main>
