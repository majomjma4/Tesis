<main class="login-shell">
    <section class="login-intro" aria-label="Informacion institucional">
        <div class="institution-brand">
            <img src="<?= e(asset('img/logo2.webp')) ?>" alt="Logo Instituto Superior Tecnologico El Libertador">
            <div>
                <span>Instituto Superior Tecnologico</span>
                <strong>El Libertador</strong>
            </div>
        </div>

        <h1>Plataforma de Seguimiento Documental Academico</h1>
        <p>Gestion, revision y acompanamiento de proyectos academicos para la carrera de Desarrollo de Software.</p>

        <div class="role-list" aria-label="Perfiles autorizados">
            <?php foreach ($roles as $role): ?>
                <span><i class="fa-solid <?= e($role['icon']) ?>"></i> <?= e($role['label']) ?></span>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="login-panel" aria-label="Formulario de inicio de sesion">
        <header class="login-panel-header">
            <span>Acceso institucional</span>
            <h2>Iniciar sesion</h2>
            <p>Ingresa con tu correo o usuario asignado por la institucion.</p>
        </header>

        <div class="login-alert" id="loginAlert" role="alert" aria-live="polite">
            <i class="fa-solid fa-circle-exclamation"></i>
            <span>Usuario o contrasena incorrectos.</span>
        </div>

        <form class="login-form" id="loginForm" data-dashboard-url="<?= e(route('dashboard')) ?>" novalidate>
            <div class="form-group" id="userGroup">
                <label for="user">Correo institucional o usuario</label>
                <div class="input-wrap">
                    <i class="fa-solid fa-user"></i>
                    <input type="text" id="user" name="user" autocomplete="username" placeholder="usuario@gmail.com">
                </div>
                <span class="field-error">Este campo es obligatorio.</span>
            </div>

            <div class="form-group" id="passwordGroup">
                <label for="password">Contrasena</label>
                <div class="input-wrap">
                    <i class="fa-solid fa-lock"></i>
                    <input type="password" id="password" name="password" autocomplete="current-password" placeholder="Ingresa tu contrasena">
                </div>
                <span class="field-error">Este campo es obligatorio.</span>
            </div>

            <div class="form-options">
                <a class="forgot-link" href="#">Olvidaste tu contrasena?</a>
            </div>

            <button class="login-submit" type="submit">
                <i class="fa-solid fa-right-to-bracket"></i>
                Iniciar sesion
            </button>
        </form>

        <p class="access-note">
            <i class="fa-solid fa-shield-halved"></i>
            <span>Acceso restringido a usuarios registrados por la institucion. Las cuentas son creadas unicamente por el administrador del sistema.</span>
        </p>
    </section>
</main>
