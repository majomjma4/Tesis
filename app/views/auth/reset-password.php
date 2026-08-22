<main class="login-shell">
    <section class="login-intro" aria-label="Información institucional">
        <div class="institution-brand">
            <img src="<?= e(asset('img/Logo2.webp')) ?>" alt="Logo">
            <div>
                <strong>Gestión Documental</strong>
            </div>
        </div>
        <h1>Nueva Contraseña</h1>
        <p>Define una contraseña segura para restablecer el acceso a tu cuenta.</p>
    </section>

    <section class="login-panel" aria-label="Formulario de nueva contraseña">
        <header class="login-panel-header">
            <span>Acceso institucional</span>
            <h2>Restablecer contraseña</h2>
            <p>Establece y confirma tu nueva contraseña institucional.</p>
        </header>

        <?php if (!empty($tokenError)): ?>
            <div class="login-alert show" role="alert" aria-live="polite">
                <i class="fa-solid fa-circle-exclamation"></i>
                <span><?= e($tokenError) ?></span>
            </div>
            <div class="form-options" style="margin-top: 20px;">
                <a class="forgot-link" href="<?= e(route('forgot-password')) ?>">Solicitar nuevo enlace de recuperación</a>
            </div>
        <?php else: ?>
            <?php if (!empty($resetError)): ?>
                <div class="login-alert show" role="alert" aria-live="polite">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    <span><?= e($resetError) ?></span>
                </div>
            <?php endif; ?>

            <?php if (!empty($resetSuccess)): ?>
                <div class="login-alert show" style="background: rgba(16, 185, 129, 0.1); border-color: rgba(16, 185, 129, 0.2); color: #047857;" role="status" aria-live="polite">
                    <i class="fa-solid fa-circle-check" style="color: #10b981;"></i>
                    <span><?= e($resetSuccess) ?></span>
                </div>
                <div class="form-options" style="margin-top: 20px;">
                    <a class="forgot-link" href="<?= e(route('login')) ?>">Regresar al inicio de sesión</a>
                </div>
            <?php else: ?>
                <form class="login-form" id="resetForm" method="post" action="<?= e(route('reset-password')) ?>" novalidate>
                    <input type="hidden" name="_csrf" value="<?= e($resetCsrfToken ?? '') ?>">
                    <input type="hidden" name="token" value="<?= e($tokenValue ?? '') ?>">

                    <div class="form-group" id="passwordGroup">
                        <label for="password">Nueva contraseña</label>
                        <div class="input-wrap">
                            <i class="fa-solid fa-lock"></i>
                            <input type="password" id="password" name="password" autocomplete="new-password" placeholder="Mínimo 8 caracteres, mayúscula, minúscula, número y símbolo" required>
                        </div>
                    </div>

                    <div class="form-group" id="confirmGroup">
                        <label for="confirm_password">Confirmar nueva contraseña</label>
                        <div class="input-wrap">
                            <i class="fa-solid fa-lock"></i>
                            <input type="password" id="confirm_password" name="confirm_password" autocomplete="new-password" placeholder="Repite la contraseña" required>
                        </div>
                    </div>

                    <div class="form-options">
                        <a class="forgot-link" href="<?= e(route('login')) ?>">Regresar al inicio de sesión</a>
                    </div>

                    <button class="login-submit" type="submit">
                        <i class="fa-solid fa-key"></i>
                        Restablecer contraseña
                    </button>
                </form>
            <?php endif; ?>
        <?php endif; ?>

        <p class="access-note">
            <i class="fa-solid fa-shield-halved"></i>
            <span>La nueva contraseña debe cumplir con las políticas de seguridad institucional establecidas.</span>
        </p>
    </section>
</main>
