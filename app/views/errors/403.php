<main class="admin-access-shell error-state-shell">
    <section class="admin-access-card is-centered error-state-card" aria-labelledby="forbiddenTitle">
        <div class="error-state-code is-danger" aria-hidden="true"><span>4</span><i class="fa-solid fa-shield-halved"></i><span>3</span></div>
        <p class="admin-access-eyebrow">Error 403</p>
        <h1 id="forbiddenTitle">No tienes permiso para entrar aquí</h1>
        <p>Tu cuenta está activa, pero esta sección pertenece a otro rol o requiere un permiso adicional.</p>
        <div class="error-state-actions">
            <a class="admin-access-primary" href="<?= e(route('dashboard')) ?>"><i class="fa-solid fa-house"></i> Volver al inicio</a>
        </div>
        <small>Si consideras que deberías tener acceso a este módulo, consulta con la administración.</small>
    </section>
</main>
