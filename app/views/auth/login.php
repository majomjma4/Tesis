<main class="login-shell">
    <!-- Inicio de presentacion institucional -->
    <section class="login-intro" aria-label="Informacion institucional">
        <!-- Inicio de marca institucional -->
        <div class="institution-brand">
            <img src="<?= e(asset('img/Logo2.webp')) ?>" alt="Logo Instituto Superior Tecnologico El Libertador">
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
            <p>Ingresa con el correo y la contraseña asignada por el Instituto.</p>
        </header>
        <!-- Final de encabezado del formulario -->

        <!-- Inicio de alerta de validacion -->
        <div class="login-alert<?= !empty($loginError) ? ' show' : '' ?>" id="loginAlert" role="alert" aria-live="polite">
            <i class="fa-solid fa-circle-exclamation"></i>
            <span><?= e($loginError ?? 'Revisa los campos indicados.') ?></span>
        </div>
        <!-- Final de alerta de validacion -->

        <!-- Inicio de formulario de inicio de sesion -->
        <form class="login-form" id="loginForm" method="post" action="<?= e(route('login')) ?>" novalidate>
            <input type="hidden" name="_csrf" value="<?= e($loginCsrfToken ?? '') ?>">
            <!-- Inicio de campo de usuario -->
            <div class="form-group" id="userGroup">
                <label for="user">Correo electrónico</label>
                <div class="input-wrap">
                    <i class="fa-solid fa-user"></i>
                    <input type="email" id="user" name="user" autocomplete="off" inputmode="email" placeholder="nombre@correo.com" list="recentLoginUsers" value="<?= e($loginValue ?? '') ?>">
                    <datalist id="recentLoginUsers"></datalist>
                    <button class="login-history-clear" id="loginHistoryClear" type="button" aria-label="Borrar correos recientes" title="Borrar correos recientes" hidden><i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
                </div>
                <span class="field-error">Este campo es obligatorio.</span>
            </div>
            <!-- Final de campo de usuario -->

            <!-- Inicio de campo de contrasena -->
            <div class="form-group" id="passwordGroup">
                <label for="password">Contraseña asignada</label>
                <div class="input-wrap">
                    <i class="fa-solid fa-lock"></i>
                    <input type="password" id="password" name="password" autocomplete="current-password" placeholder="Ingresa tu contraseña asignada">
                    <button class="password-toggle" id="passwordToggle" type="button" aria-label="Mostrar contraseña" aria-pressed="false" title="Mostrar contraseña">
                        <i class="fa-regular fa-eye" aria-hidden="true"></i>
                    </button>
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
