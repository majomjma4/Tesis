<main class="login-shell">
    <!-- Inicio de presentacion institucional -->
    <section class="login-intro" aria-label="Informacion institucional">
        <!-- Inicio de marca institucional -->
        <div class="institution-brand">
            <img src="<?= e(asset('img/logo2.webp')) ?>" alt="Logo Instituto Superior Tecnologico El Libertador">
            <div>
                <span>Instituto Superior Tecnologico</span>
                <strong>El Libertador</strong>
            </div>
        </div>
        <!-- Final de marca institucional -->

        <!-- Inicio de mensaje principal -->
        <h1>Gestión Documental Académica</h1>
        <p>Gestión, seguimiento y revisión de proyectos académicos de la carrera de Desarrollo de Software.</p>
        <!-- Final de mensaje principal -->

        <!-- Inicio de perfiles permitidos -->
        <div class="role-list" aria-label="Perfiles autorizados">
            <?php foreach ($roles as $role): ?>
                <span><i class="fa-solid <?= e($role['icon']) ?>"></i> <?= e($role['label']) ?></span>
            <?php endforeach; ?>
        </div>
        <!-- Final de perfiles permitidos -->
    </section>
    <!-- Final de presentacion institucional -->

    <!-- Inicio de panel de acceso -->
    <section class="login-panel" aria-label="Formulario de inicio de sesion">
        <!-- Inicio de encabezado del formulario -->
        <header class="login-panel-header">
            <span>Acceso institucional</span>
            <h2>Iniciar sesión</h2>
            <p>Ingresa con tu correo o usuario asignado por la institucion.</p>
        </header>
        <!-- Final de encabezado del formulario -->

        <!-- Inicio de alerta de validacion -->
        <div class="login-alert" id="loginAlert" role="alert" aria-live="polite">
            <i class="fa-solid fa-circle-exclamation"></i>
            <span>Usuario o contraseña incorrectos.</span>
        </div>
        <!-- Final de alerta de validacion -->

        <!-- Inicio de formulario de inicio de sesion -->
        <form class="login-form" id="loginForm" data-dashboard-url="<?= e(route('dashboard')) ?>" novalidate>
            <!-- Inicio de campo de usuario -->
            <div class="form-group" id="userGroup">
                <label for="user">Correo o usuario asignado</label>
                <div class="input-wrap">
                    <i class="fa-solid fa-user"></i>
                    <input type="text" id="user" name="user" autocomplete="username" placeholder="correo@ejemplo.com">
                </div>
                <span class="field-error">Este campo es obligatorio.</span>
            </div>
            <!-- Final de campo de usuario -->

            <!-- Inicio de campo de contrasena -->
            <div class="form-group" id="passwordGroup">
                <label for="password">Contraseña</label>
                <div class="input-wrap">
                    <i class="fa-solid fa-lock"></i>
                    <input type="password" id="password" name="password" autocomplete="current-password" placeholder="Ingresa tu contrasena">
                </div>
                <span class="field-error">Este campo es obligatorio.</span>
            </div>
            <!-- Final de campo de contrasena -->

            <div class="form-options">
                <a class="forgot-link" href="#">Olvidaste tu contraseña?</a>
            </div>

            <button class="login-submit" type="submit">
                <i class="fa-solid fa-right-to-bracket"></i>
                Iniciar sesión
            </button>
        </form>
        <!-- Final de formulario de inicio de sesion -->

        <!-- Inicio de nota informativa -->
        <p class="access-note">
            <i class="fa-solid fa-shield-halved"></i>
            <span>Acceso restringido a usuarios autorizados por la institución. Las cuentas son administradas por el personal autorizado de la institución.</span>
        </p>
        <!-- Final de nota informativa -->
    </section>
    <!-- Final de panel de acceso -->
</main>
