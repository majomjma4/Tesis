<main class="login-shell">
    <section class="login-intro" aria-label="Información institucional">
        <div class="institution-brand">
            <img src="<?= e(asset('img/Logo2.webp')) ?>" alt="Logo">
            <div>
                <strong>Gestión Documental</strong>
            </div>
        </div>
        <h1>Recuperar Contraseña</h1>
        <p>Solicita un enlace de restablecimiento para volver a acceder a tu cuenta de Gestión Documental Académica.</p>
    </section>

    <section class="login-panel" aria-label="Formulario de recuperación">
        <header class="login-panel-header">
            <span>Acceso institucional</span>
            <h2>¿Olvidaste tu contraseña?</h2>
            <p>Ingresa la cédula asociada a tu cuenta para enviarte las instrucciones.</p>
        </header>

        <?php if (!empty($forgotError)): ?>
            <div class="login-alert show" role="alert" aria-live="polite">
                <i class="fa-solid fa-circle-exclamation"></i>
                <span><?= e($forgotError) ?></span>
            </div>
        <?php endif; ?>

        <?php if (!empty($forgotSuccess)): ?>
            <div class="login-alert login-alert-success show" role="status" aria-live="polite">
                <i class="fa-solid fa-circle-check"></i>
                <span><?= e($forgotSuccess) ?></span>
            </div>
        <?php endif; ?>

        <form class="login-form" id="forgotForm" method="post" action="<?= e(route('forgot-password')) ?>" novalidate>
            <input type="hidden" name="_csrf" value="<?= e($forgotCsrfToken ?? '') ?>">

            <div class="form-group" id="codeGroup">
                <label for="institutional_code">Cédula</label>
                <div class="input-wrap">
                    <i class="fa-solid fa-id-card"></i>
                    <input type="text" id="institutional_code" name="institutional_code" inputmode="numeric"
                        minlength="10" maxlength="10" pattern="[0-9]{10}" placeholder="Ingresa tu cédula"
                        value="<?= e($codeValue ?? '') ?>" required>
                </div>
            </div>

            <div class="form-options">
                <a class="forgot-link" href="<?= e(route('login')) ?>">Regresar al inicio de sesión</a>
            </div>

            <button class="login-submit" type="submit">
                <i class="fa-solid fa-paper-plane"></i>
                Enviar instrucciones
            </button>
        </form>

        <p class="access-note">
            <i class="fa-solid fa-shield-halved"></i>
            <span>Si tienes problemas para recibir el enlace, por favor ponte en contacto con el administrador de la
                plataforma.</span>
        </p>
    </section>
</main>
