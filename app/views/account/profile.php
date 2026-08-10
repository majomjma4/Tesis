<?php
declare(strict_types=1);
$roleLabels=['administrator'=>'Acceso administrativo','teacher'=>'Docente','student'=>'Estudiante'];
$formatDate=static function(?string $date,string $fallback='No registrado'):string{if(!$date||str_starts_with($date,'0000-00-00'))return $fallback;$timestamp=strtotime($date);return $timestamp===false?$fallback:date('d/m/Y H:i',$timestamp);};
?>
<main class="profile-shell">
    <nav><a href="<?= e(route('dashboard')) ?>">Inicio</a><i class="fa-solid fa-chevron-right"></i><span>Mi perfil</span></nav>
    <header class="profile-header"><span><?php if(!empty($profile['avatar_path'])): ?><img src="<?= e(route('profile-avatar') . '&v=' . rawurlencode((string) ($profile['avatar_updated_at'] ?? ''))) ?>" alt=""><?php else: ?><?= e(mb_strtoupper(mb_substr($profile['full_name'],0,1,'UTF-8'),'UTF-8')) ?><?php endif; ?></span><div><small>Cuenta institucional</small><h1><?= e($profile['full_name']) ?></h1><p><?= e($profile['email']) ?></p></div></header>
    <div class="profile-grid">
        <section class="profile-card profile-card-info">
            <h2>Información personal</h2><p>Actualiza los datos institucionales de tu cuenta.</p>
            <?php if($profileError): ?><div class="profile-message error" role="alert"><?= e($profileError) ?></div><?php endif; ?>
            <?php if($profileSuccess): ?><div class="profile-message success" role="status"><?= e($profileSuccess) ?></div><?php endif; ?>
            <form id="profileForm" method="post" data-profile-form>
                <input type="hidden" name="_csrf" value="<?= e($profileCsrf) ?>">
                <label>Nombre completo<input name="full_name" value="<?= e($profile['full_name']) ?>" maxlength="180" required></label>
                <label>Usuario (Opcional)<input name="username" value="<?= e($profile['username'] ?? '') ?>" maxlength="80" placeholder="Nombre de usuario"></label>
                <label>Correo institucional<input type="email" name="email" value="<?= e($profile['email']) ?>" maxlength="190" required></label>
                <label>Cédula<input type="text" value="<?= e($profile['institutional_code'] ?: 'No registrada') ?>" readonly tabindex="-1" class="profile-readonly-input" aria-readonly="true"></label>
                <div class="profile-form-actions">
                    <button type="submit" class="profile-submit-btn" data-profile-submit disabled aria-disabled="true">Guardar cambios</button>
                    <a href="<?= e(route('logout')) ?>" class="profile-logout-btn js-logout-trigger"><i class="fa-solid fa-right-from-bracket" aria-hidden="true"></i> Cerrar sesión</a>
                </div>
            </form>
        </section>
        <aside class="profile-card profile-card-summary">
            <h2>Resumen de la cuenta</h2>
            <dl>
                <div><dt>Rol</dt><dd><?= e(implode(', ',array_map(fn($role)=>$roleLabels[$role]??$role,$profile['roles']))) ?></dd></div>
                <div><dt>Cuenta creada</dt><dd><?= e($formatDate($profile['created_at'])) ?></dd></div>
                <div><dt>Último acceso</dt><dd><?= e($formatDate($profile['last_login_at'],'Aún no disponible')) ?></dd></div>
                <div><dt>Último cambio de contraseña</dt><dd><?= e($formatDate($profile['password_changed_at'],'Aún no disponible')) ?></dd></div>
            </dl>
            <a href="<?= e(route('change-password')) ?>" class="profile-password-btn"><i class="fa-solid fa-key" aria-hidden="true"></i> Cambiar contraseña</a>
        </aside>
    </div>
</main>
<div class="profile-confirm" id="profileConfirm" hidden>
    <section role="dialog" aria-modal="true" aria-labelledby="profileConfirmTitle" aria-describedby="profileConfirmText">
        <span><i class="fa-solid fa-shield-halved"></i></span><h2 id="profileConfirmTitle">Confirmar cambios</h2>
        <p id="profileConfirmText">Confirma tu contraseña actual para guardar los cambios en tu perfil.</p>
        <label>Contraseña actual<input form="profileForm" id="profileCurrentPassword" type="password" name="current_password" autocomplete="current-password" required></label>
        <div><button type="button" data-profile-cancel>Cancelar</button><button type="button" data-profile-confirm>Guardar cambios</button></div>
    </section>
</div>
<script src="<?= e(asset('js/account-profile.js')) ?>"></script>
