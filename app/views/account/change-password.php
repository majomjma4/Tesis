<main class="admin-access-shell<?= $forcedPasswordChange ? ' forced-password-shell' : '' ?>">
    <?php if (!$forcedPasswordChange): ?><nav aria-label="Migas de pan"><a href="<?= e(route('dashboard')) ?>">Inicio</a><i class="fa-solid fa-chevron-right"></i><span>Cambiar contraseña</span></nav><?php endif; ?>
    <section class="admin-access-card">
        <span class="admin-access-icon"><i class="fa-solid fa-shield-halved"></i></span>
        <p class="admin-access-eyebrow">Seguridad de la cuenta</p><h1><?= $forcedPasswordChange ? 'Actualiza tu contraseña para continuar' : 'Cambiar contraseña' ?></h1>
        <p><?= $forcedPasswordChange ? 'La contraseña temporal llegó a su límite de avisos o venció.' : 'El cambio cerrará las demás sesiones abiertas de tu cuenta.' ?></p>
        <?php if($passwordError): ?><div class="admin-access-alert is-error" role="alert"><?= e($passwordError) ?></div><?php endif; ?>
        <?php if($passwordSuccess): ?><div class="admin-access-alert is-success" role="status"><?= e($passwordSuccess) ?> <a href="<?= e(route('dashboard')) ?>">Volver al inicio</a></div><?php endif; ?>
        <form method="post" class="admin-password-form" novalidate>
            <input type="hidden" name="_csrf" value="<?= e($passwordCsrfToken) ?>">
            <label><span>Contraseña actual</span><input type="password" name="current_password" autocomplete="current-password" required></label>
            <label><span>Nueva contraseña</span><input type="password" name="new_password" autocomplete="new-password" minlength="8" required><small>Mínimo 8 caracteres, mayúscula, minúscula, número y símbolo.</small></label>
            <label><span>Confirmar nueva contraseña</span><input type="password" name="new_password_confirmation" autocomplete="new-password" required></label>
            <button type="submit"><i class="fa-solid fa-key"></i> Actualizar contraseña</button>
        </form>
    </section>
</main>
