<main class="admin-access-shell forced-password-shell reset-password-shell">
    <section class="admin-access-card" role="dialog" aria-modal="true" aria-labelledby="resetPasswordTitle" tabindex="-1">
        <span class="admin-access-icon"><i class="fa-solid fa-shield-halved" aria-hidden="true"></i></span>
        <p class="admin-access-eyebrow">Seguridad de la cuenta</p>
        <?php if (!empty($tokenError)): ?>
            <h1 id="resetPasswordTitle">Enlace de recuperación no válido</h1>
            <p>Este enlace no puede utilizarse para restablecer tu contraseña.</p>
        <?php else: ?>
            <h1 id="resetPasswordTitle">Restablecer contraseña</h1>
            <p>Establece y confirma tu nueva contraseña institucional.</p>
        <?php endif; ?>

        <?php if (!empty($tokenError)): ?>
            <div class="admin-access-alert is-error" role="alert" aria-live="polite">
                <span><?= e($tokenError) ?></span>
            </div>
            <div class="admin-access-success-action">
                <a class="admin-access-secondary" href="<?= e(route('forgot-password')) ?>">Solicitar nuevo enlace de recuperación</a>
            </div>
        <?php else: ?>
            <?php if (!empty($resetError)): ?>
                <div class="admin-access-alert is-error" role="alert" aria-live="polite">
                    <span><?= e($resetError) ?></span>
                </div>
            <?php endif; ?>

            <?php if (!empty($resetSuccess)): ?>
                <div class="admin-access-alert is-success" role="status">
                    <p>Tu contraseña fue restablecida correctamente.</p>
                    <p>Por seguridad, las sesiones abiertas anteriormente fueron cerradas. Puedes cerrar las otras pestañas de la plataforma.</p>
                </div>
                <div class="admin-access-success-action">
                    <a class="admin-access-primary" href="<?= e(route('login')) ?>">Ir al inicio de sesión</a>
                </div>
            <?php else: ?>
                <?php if (!empty($hasTemporaryPassword)): ?>
                    <div class="admin-access-alert is-success" role="status" aria-live="polite">
                        <span>Tu cuenta utiliza actualmente una contraseña temporal. Al establecer una nueva contraseña, esta dejará de estar vigente.</span>
                    </div>
                <?php endif; ?>
                <form class="admin-password-form" id="resetForm" method="post" action="<?= e(route('reset-password')) ?>" novalidate>
                    <input type="hidden" name="_csrf" value="<?= e($resetCsrfToken ?? '') ?>">
                    <input type="hidden" name="token" value="<?= e($tokenValue ?? '') ?>">

                    <label class="password-field-group">
                        <span>Nueva contraseña</span>
                        <div class="password-input-wrapper">
                            <input type="password" id="newPassword" name="password" autocomplete="new-password" placeholder="Crea una nueva contraseña" minlength="8" required>
                            <button type="button" class="password-toggle-btn" aria-label="Mostrar contraseña" tabindex="0"><i class="fa-solid fa-eye" aria-hidden="true"></i></button>
                        </div>
                    </label>

                    <div class="password-requirements" id="passwordRequirements" aria-live="polite">
                        <p class="password-req-title">Tu contraseña debe incluir:</p>
                        <ul class="password-req-list">
                            <li data-req="length"><span class="req-icon" aria-hidden="true"><i class="fa-solid fa-xmark"></i></span> 8 caracteres o más</li>
                            <li data-req="uppercase"><span class="req-icon" aria-hidden="true"><i class="fa-solid fa-xmark"></i></span> Una letra mayúscula</li>
                            <li data-req="lowercase"><span class="req-icon" aria-hidden="true"><i class="fa-solid fa-xmark"></i></span> Una letra minúscula</li>
                            <li data-req="number"><span class="req-icon" aria-hidden="true"><i class="fa-solid fa-xmark"></i></span> Un número</li>
                            <li data-req="symbol"><span class="req-icon" aria-hidden="true"><i class="fa-solid fa-xmark"></i></span> Un símbolo</li>
                        </ul>
                    </div>

                    <label class="password-field-group">
                        <span>Confirmar nueva contraseña</span>
                        <div class="password-input-wrapper">
                            <input type="password" id="confirmPassword" name="confirm_password" autocomplete="new-password" placeholder="Repite la contraseña" required>
                            <button type="button" class="password-toggle-btn" aria-label="Mostrar contraseña" tabindex="0"><i class="fa-solid fa-eye" aria-hidden="true"></i></button>
                        </div>
                    </label>

                    <div class="password-match-status" id="passwordMatchStatus" hidden aria-live="polite"></div>

                    <button type="submit" id="submitPasswordBtn" disabled aria-disabled="true">
                        <i class="fa-solid fa-key" aria-hidden="true"></i>
                        Restablecer contraseña
                    </button>
                </form>
            <?php endif; ?>
        <?php endif; ?>
    </section>
</main>
