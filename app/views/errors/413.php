<main class="admin-access-shell error-state-shell">
    <section class="admin-access-card is-centered error-state-card" aria-labelledby="requestSizeTitle">
        <div class="error-state-code is-warning" aria-hidden="true"><span>4</span><i class="fa-solid fa-file-arrow-up"></i><span>13</span></div>
        <p class="admin-access-eyebrow">Solicitud no procesada</p>
        <h1 id="requestSizeTitle">El archivo o la solicitud es demasiado grande</h1>
        <p><?= e($requestSizeError ?? 'La solicitud supera el tamaño máximo permitido por el servidor.') ?></p>
        <div class="error-state-actions">
            <a class="admin-access-primary" href="<?= e($requestSizeReturnUrl ?? route('dashboard')) ?>"><i class="fa-solid fa-arrow-left"></i> Volver</a>
        </div>
    </section>
</main>
