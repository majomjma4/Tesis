<main class="admin-access-shell<?= $forcedPasswordChange ? ' forced-password-shell' : ' voluntary-password-modal' ?>" data-password-change-modal data-password-forced="<?= $forcedPasswordChange ? '1' : '0' ?>" data-password-close-url="<?= e(route('profile')) ?>">
    <section class="admin-access-card" role="dialog" aria-modal="true" aria-labelledby="changePasswordTitle" tabindex="-1">
        <?php if (!$forcedPasswordChange): ?><button type="button" class="password-modal-close" data-password-modal-close aria-label="Cerrar"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button><?php endif; ?>
        <span class="admin-access-icon"><i class="fa-solid fa-shield-halved" aria-hidden="true"></i></span>
        <p class="admin-access-eyebrow">Seguridad de la cuenta</p>
        <h1 id="changePasswordTitle"><?= $forcedPasswordChange ? 'Actualiza tu contraseña para continuar' : 'Cambiar contraseña' ?></h1>
        <p><?= $forcedPasswordChange ? 'La contraseña temporal llegó a su límite de avisos o venció.' : 'El cambio cerrará las demás sesiones abiertas de tu cuenta.' ?></p>

        <?php if($passwordError): ?>
            <div class="admin-access-alert is-error" role="alert"><?= e($passwordError) ?></div>
        <?php endif; ?>
        <?php if($passwordSuccess): ?>
            <div class="admin-access-alert is-success" role="status">
                <p><?= e($passwordSuccess) ?></p>
                <div class="admin-access-success-action">
                    <form method="post" action="<?= e(route('logout')) ?>">
                        <input type="hidden" name="_csrf" value="<?= e($logoutCsrfToken ?? '') ?>">
                        <button type="submit" class="admin-access-primary"><i class="fa-solid fa-right-from-bracket" aria-hidden="true"></i> Iniciar sesión de nuevo</button>
                    </form>
                </div>
            </div>
        <?php endif; ?>

        <?php if(!$passwordSuccess): ?>
        <form method="post" class="admin-password-form" id="changePasswordForm" novalidate>
            <input type="hidden" name="_csrf" value="<?= e($passwordCsrfToken) ?>">

            <label class="password-field-group">
                <span>Contraseña actual</span>
                <div class="password-input-wrapper">
                    <input type="password" id="currentPassword" name="current_password" autocomplete="current-password" placeholder="Ingresa tu contraseña actual" required>
                    <button type="button" class="password-toggle-btn" aria-label="Mostrar contraseña" tabindex="0">
                        <i class="fa-solid fa-eye" aria-hidden="true"></i>
                    </button>
                </div>
            </label>

            <label class="password-field-group">
                <span>Nueva contraseña</span>
                <div class="password-input-wrapper">
                    <input type="password" id="newPassword" name="new_password" autocomplete="new-password" placeholder="Crea una nueva contraseña" minlength="8" required>
                    <button type="button" class="password-toggle-btn" aria-label="Mostrar contraseña" tabindex="0">
                        <i class="fa-solid fa-eye" aria-hidden="true"></i>
                    </button>
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
                    <input type="password" id="confirmPassword" name="new_password_confirmation" autocomplete="new-password" placeholder="Repite la nueva contraseña" required>
                    <button type="button" class="password-toggle-btn" aria-label="Mostrar contraseña" tabindex="0">
                        <i class="fa-solid fa-eye" aria-hidden="true"></i>
                    </button>
                </div>
            </label>

            <div class="password-match-status" id="passwordMatchStatus" hidden aria-live="polite"></div>

            <?php if (!$forcedPasswordChange): ?>
                <button type="button" class="admin-access-secondary" data-password-modal-close>Cancelar</button>
            <?php endif; ?>
            <button type="submit" id="submitPasswordBtn" disabled aria-disabled="true">
                <i class="fa-solid fa-key" aria-hidden="true"></i> Actualizar contraseña
            </button>
        </form>
        <?php endif; ?>
    </section>
</main>

<script src="<?= e(asset('js/account-change-password.js')) ?>"></script>
