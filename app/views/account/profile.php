<?php
declare(strict_types=1);
$roleLabels=['administrator'=>'Acceso administrativo','teacher'=>'Docente','student'=>'Estudiante'];
$formatDate=static function(?string $date,string $fallback='No registrado'):string{if(!$date||str_starts_with($date,'0000-00-00'))return $fallback;$timestamp=strtotime($date);return $timestamp===false?$fallback:date('d/m/Y H:i',$timestamp);};
$hasAvatar = !empty($profile['avatar_path']);
$initialLetter = mb_strtoupper(mb_substr($profile['full_name'],0,1,'UTF-8'),'UTF-8');
$avatarUrl = $hasAvatar ? (route('profile-avatar') . '&v=' . rawurlencode((string) ($profile['avatar_updated_at'] ?? ''))) : '';
?>
<main class="profile-shell" data-update-endpoint="<?= e(route('profile-avatar-update')) ?>" data-remove-endpoint="<?= e(route('profile-avatar-remove')) ?>" data-get-endpoint="<?= e(route('profile-avatar')) ?>" data-avatar-csrf="<?= e($profileAvatarCsrf ?? '') ?>">
    <nav><a href="<?= e(route('dashboard')) ?>">Inicio</a><i class="fa-solid fa-chevron-right"></i><span>Mi perfil</span></nav>
    <header class="profile-header">
        <div class="profile-avatar-wrapper" id="profileAvatarContainer">
            <span class="profile-avatar-display" id="profileAvatarDisplay">
                <?php if ($hasAvatar): ?>
                    <img id="profileHeaderAvatarImg" src="<?= e($avatarUrl) ?>" alt="Fotografía de perfil de <?= e($profile['full_name']) ?>">
                <?php else: ?>
                    <span id="profileHeaderAvatarInitial" class="profile-initial"><?= e($initialLetter) ?></span>
                <?php endif; ?>
            </span>
            <input type="file" id="profileAvatarFileInput" accept="image/jpeg,image/png,image/jpg" hidden>
        </div>
        <div class="profile-header-info">
            <small>Cuenta institucional</small>
            <h1><?= e($profile['full_name']) ?></h1>
            <p><?= e($profile['email']) ?></p>
            <div class="profile-avatar-actions">
                <button type="button" class="profile-avatar-action-btn primary" id="profileAvatarAddBtn"><i class="fa-solid fa-camera" aria-hidden="true"></i> <span id="profileAvatarAddText">Agregar foto</span></button>
                <button type="button" class="profile-avatar-action-btn danger" id="profileAvatarRemoveBtn" <?= !$hasAvatar ? 'hidden' : '' ?>><i class="fa-solid fa-trash-can" aria-hidden="true"></i> Eliminar foto</button>
            </div>
            <p class="profile-avatar-help"><i class="fa-solid fa-circle-info" aria-hidden="true"></i> Utiliza una fotografía reciente donde tu rostro sea claramente visible.</p>
        </div>
    </header>
    <div class="profile-grid">
        <section class="profile-card profile-card-info">
            <h2>Información personal</h2><p>Actualiza los datos institucionales de tu cuenta.</p>
            <?php if($profileError): ?><div class="profile-message error" role="alert"><?= e($profileError) ?></div><?php endif; ?>
            <?php if($profileSuccess): ?><div class="profile-message success" role="status"><?= e($profileSuccess) ?></div><?php endif; ?>
            <div class="profile-message" id="profileAvatarAlert" hidden role="status" aria-live="polite"></div>
            <form id="profileForm" method="post" data-profile-form>
                <input type="hidden" name="_csrf" value="<?= e($profileCsrf) ?>">
                <label>Nombre completo<input name="full_name" value="<?= e($profile['full_name']) ?>" maxlength="180" required></label>
                <label>Usuario (Opcional)<input name="username" value="<?= e($profile['username'] ?? '') ?>" maxlength="80" placeholder="Nombre de usuario"></label>
                <label>Correo institucional<input type="email" name="email" value="<?= e($profile['email']) ?>" maxlength="190" required></label>
                <label>Cédula<input type="text" value="<?= e($profile['institutional_code'] ?: 'No registrada') ?>" readonly tabindex="-1" class="profile-readonly-input" aria-readonly="true"></label>
                <div class="profile-form-actions">
                    <button type="submit" class="profile-submit-btn" data-profile-submit disabled aria-disabled="true">Guardar cambios</button>
                    <button type="button" class="profile-logout-btn js-logout-trigger"><i class="fa-solid fa-right-from-bracket" aria-hidden="true"></i> Cerrar sesión</button>
                </div>
            </form>
        </section>
        <aside class="profile-card profile-card-summary">
            <h2>Resumen de la cuenta</h2>
            <dl>
                <div><dt>Rol</dt><dd><?= e(implode(', ',array_map(fn($role)=>$roleLabels[$role]??$role,$profile['roles']))) ?></dd></div>
                <?php if (in_array('student', $profile['roles'], true)): ?><div><dt>Semestre actual</dt><dd><?= !empty($profile['current_semester']) ? e((string) $profile['current_semester'] . '.º semestre') : 'No disponible' ?></dd></div><?php endif; ?>
                <div><dt>Cuenta creada</dt><dd><?= e($formatDate($profile['created_at'])) ?></dd></div>
                <div><dt>Último acceso</dt><dd><?= e($formatDate($profile['last_login_at'],'Aún no disponible')) ?></dd></div>
                <div><dt>Último cambio de contraseña</dt><dd><?= e($formatDate($profile['password_changed_at'],'Aún no disponible')) ?></dd></div>
            </dl>
            <a href="<?= e(route('change-password')) ?>" class="profile-password-btn"><i class="fa-solid fa-key" aria-hidden="true"></i> Cambiar contraseña</a>
        </aside>
    </div>
</main>

<div class="profile-confirm profile-avatar-edit-modal" id="profileAvatarEditModal" hidden>
    <section role="dialog" aria-modal="true" aria-labelledby="profileAvatarEditTitle">
        <header class="profile-avatar-modal-header">
            <div>
                <h2 id="profileAvatarEditTitle">Recortar fotografía</h2>
                <p>Ajusta la posición y zoom de tu imagen de perfil</p>
            </div>
            <button type="button" class="profile-avatar-modal-close" id="profileAvatarEditCloseBtn" aria-label="Cerrar editor">&times;</button>
        </header>
        <div class="profile-crop-area">
            <div class="profile-crop-viewport" id="profileCropViewport">
                <img id="profileCropImage" alt="Fotografía a recortar" class="profile-crop-img">
                <div class="profile-crop-mask" aria-hidden="true"></div>
            </div>
            <div class="profile-crop-controls">
                <div class="profile-zoom-control">
                    <i class="fa-solid fa-magnifying-glass-minus" aria-hidden="true"></i>
                    <input type="range" id="profileCropZoom" min="1" max="3" step="0.01" value="1" aria-label="Zoom de fotografía">
                    <i class="fa-solid fa-magnifying-glass-plus" aria-hidden="true"></i>
                </div>
                <div class="profile-crop-preview-wrap">
                    <span>Vista previa circular:</span>
                    <canvas id="profileCropPreviewCanvas" width="70" height="70"></canvas>
                </div>
            </div>
        </div>
        <footer class="profile-avatar-modal-footer">
            <button type="button" class="profile-btn-secondary" id="profileAvatarEditCancelBtn">Cancelar</button>
            <button type="button" class="profile-btn-primary" id="profileAvatarEditSaveBtn">
                <i class="fa-solid fa-check" aria-hidden="true"></i> Usar fotografía
            </button>
        </footer>
    </section>
</div>

<div class="profile-confirm" id="profileAvatarRemoveModal" hidden>
    <section role="dialog" aria-modal="true" aria-labelledby="profileAvatarRemoveTitle" aria-describedby="profileAvatarRemoveText">
        <span class="profile-avatar-remove-icon"><i class="fa-solid fa-trash-can" aria-hidden="true"></i></span>
        <h2 id="profileAvatarRemoveTitle">¿Deseas eliminar tu fotografía de perfil?</h2>
        <p id="profileAvatarRemoveText">Se volverá a mostrar la inicial de tu nombre.</p>
        <div>
            <button type="button" id="profileAvatarRemoveCancelBtn">Cancelar</button>
            <button type="button" class="danger" id="profileAvatarRemoveConfirmBtn">Eliminar fotografía</button>
        </div>
    </section>
</div>

<div class="profile-confirm" id="profileConfirm" hidden>
    <section role="dialog" aria-modal="true" aria-labelledby="profileConfirmTitle" aria-describedby="profileConfirmText">
        <span><i class="fa-solid fa-shield-halved"></i></span><h2 id="profileConfirmTitle">Confirmar cambios</h2>
        <p id="profileConfirmText">Confirma tu contraseña actual para guardar los cambios en tu perfil.</p>
        <label>Contraseña actual<input form="profileForm" id="profileCurrentPassword" type="password" name="current_password" autocomplete="current-password" required></label>
        <div><button type="button" data-profile-cancel>Cancelar</button><button type="button" data-profile-confirm>Guardar cambios</button></div>
    </section>
</div>

<script src="<?= e(asset('js/account-profile.js')) ?>"></script>
