<?php
$roleLabels=['student'=>'Estudiante','teacher'=>'Docente','administrator'=>'Cuenta inicial'];
$statusLabels=['active'=>'Activo','inactive'=>'Inactivo','blocked'=>'Bloqueado'];
$career=$catalogs['career']??null;$period=$catalogs['period']??null;

// Helper seguro para resaltar coincidencias de búsqueda en el servidor (escapa y añade <mark>)
$highlight = static function(string $text, string $search): string {
    $escaped = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    $search = trim($search);
    if ($search === '') {
        return $escaped;
    }
    // Normalizar y fold para buscar sin acentos si fuera necesario, pero la regla pide conservar mayúsculas/minúsculas originales.
    // Usamos una expresión regular case-insensitive con soporte UTF-8 (/ui) escapando caracteres de control.
    $pattern = '/' . preg_quote($search, '/') . '/ui';
    return preg_replace_callback($pattern, static function(array $matches): string {
        return '<mark class="users-search-highlight">' . htmlspecialchars($matches[0], ENT_QUOTES, 'UTF-8') . '</mark>';
    }, $escaped) ?? $escaped;
};
?>
<section class="users-heading">
    <div><span>Administración</span><h1>Gestión de usuarios</h1><p>Administra accesos y datos académicos desde un solo lugar.</p></div>
    <button id="newUserButton" type="button"><i class="fa-solid fa-user-plus"></i> Nuevo usuario</button>
</section>
<?php if($adminUsersError):?><div class="users-message error" role="alert"><?=e($adminUsersError)?></div><?php endif;?>

<section class="users-summary" aria-label="Resumen de usuarios">
    <article class="users-summary-total"><strong><?= (int)$userSummary['total']?></strong><span>Total</span></article>
    <article class="users-summary-active"><strong><?= (int)$userSummary['active']?></strong><span>Activos</span></article>
    <article class="users-summary-students"><strong><?= (int)$userSummary['students']?></strong><span>Estudiantes</span></article>
    <article class="users-summary-teachers"><strong><?= (int)$userSummary['teachers']?></strong><span>Docentes</span></article>
    <article class="users-summary-admins"><strong><?= (int)$userSummary['administrators']?></strong><span>Administradores</span></article>
</section>

<form class="users-filters admin-filter-bar" method="get" role="search">
    <input type="hidden" name="page" value="admin-users">
    <label class="users-filter-control users-filter-search admin-filter-item-search">
        <span class="sr-only">Buscar usuarios</span>
        <span class="users-search-field admin-filter-search">
            <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
            <input type="search" name="search" value="<?=e($filters['search'])?>" placeholder="Buscar por usuario, nombre, correo o cédula" autocomplete="off" data-no-search-history>
            <button type="button" class="users-search-clear" aria-label="Limpiar búsqueda" title="Limpiar búsqueda" hidden><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
        </span>
    </label>
    <label class="users-filter-control admin-filter-control">
        <span>Rol</span>
        <select name="role" aria-label="Filtrar por rol">
            <option value="">Todos</option>
            <option value="student" <?=$filters['role']==='student'?'selected':''?>>Estudiantes</option>
            <option value="teacher" <?=$filters['role']==='teacher'?'selected':''?>>Docentes</option>
            <option value="administrator" <?=$filters['role']==='administrator'?'selected':''?>>Administradores</option>
        </select>
    </label>
    <label class="users-filter-control admin-filter-control">
        <span>Estado</span>
        <select name="status" aria-label="Filtrar por estado">
            <option value="">Todos</option>
            <option value="active" <?=$filters['status']==='active'?'selected':''?>>Activos</option>
            <option value="inactive" <?=$filters['status']==='inactive'?'selected':''?>>Inactivos</option>
            <option value="blocked" <?=$filters['status']==='blocked'?'selected':''?>>Bloqueados</option>
        </select>
    </label>
</form>

<div id="adminUsersResults">
    <section class="users-table-card">
        <header><div><h2>Usuarios registrados</h2><span><?=count($users)?> resultados en esta página</span></div><button class="users-refresh" type="button" data-reset-url="<?=e(route('admin-users'))?>" aria-label="Actualizar usuarios" title="Actualizar usuarios"><i class="fa-solid fa-rotate"></i><span>Actualizar</span></button></header>
        <?php if(!$users):?><div class="users-empty"><i class="fa-solid fa-users"></i><strong>No se encontraron usuarios</strong><p>Cambia los filtros o registra una nueva cuenta.</p></div>
        <?php else:?><div class="users-list">
            <?php foreach($users as $user):?>
            <article class="user-record" data-role="<?=e($user['role_code'])?>" data-status="<?=e($user['status'])?>" data-search-text="<?=e(implode(' ',[$user['username']??'',$user['full_name'],$user['email']]))?>" data-search-number="<?=e($user['institutional_code']??'')?>">
                <div class="user-identity">
                    <span><?=e(mb_strtoupper(mb_substr($user['full_name'],0,1,'UTF-8'),'UTF-8'))?></span>
                    <div>
                        <strong><?= $highlight($user['full_name'], $filters['search'] ?? '') ?></strong>
                        <small><?= $highlight($user['email'], $filters['search'] ?? '') ?></small>
                        <?php if(!empty($user['username'])):?><small class="user-username">@<?= $highlight($user['username'], $filters['search'] ?? '') ?></small><?php endif;?>
                    </div>
                </div>
                <dl>
                    <div><dt>Cédula</dt><dd><?= !empty($user['institutional_code']) ? $highlight($user['institutional_code'], $filters['search'] ?? '') : 'No registrada' ?></dd></div>
                    <div><dt>Rol</dt><dd><span class="role-chip <?=e($user['role_code'])?>"><?=e($roleLabels[$user['role_code']]??$user['role_code'])?></span><?php if($user['is_admin']&&!$user['is_initial_admin']):?><small class="temporary-label">Acceso administrativo</small><?php elseif($user['is_initial_admin']):?><small class="temporary-label">Temporal · sin funciones académicas</small><?php endif;?></dd></div>
                    <div><dt>Semestre</dt><dd><?= $user['role_code']==='student'&&$user['semester']?(int)$user['semester'].'.º':'No aplica'?></dd></div>
                    <div><dt>Estado</dt><dd><span class="status-chip <?=e($user['status'])?>"><?=e($statusLabels[$user['status']]??$user['status'])?></span><?php if($user['must_change_password']):?><small class="temporary-label">Clave temporal</small><?php endif;?></dd></div>
                    <div><dt>Último acceso</dt><dd><?= $user['last_login_at']?e(date('d/m/Y H:i',strtotime($user['last_login_at']))):'Nunca'?></dd></div>
                </dl>
                <details class="user-actions-wrap">
                    <summary class="user-actions-button" aria-label="Acciones de <?=e($user['full_name'])?>"><i class="fa-solid fa-ellipsis-vertical"></i></summary>
                    <div class="user-actions" role="menu">
                        <button type="button" data-action="edit"><i class="fa-solid fa-pen-to-square" aria-hidden="true"></i><span>Editar</span></button>
                        <?php if(!$user['must_change_password']):?>
                            <button type="button" data-action="password"><i class="fa-solid fa-key" aria-hidden="true"></i><span>Restablecer contraseña</span></button>
                        <?php endif;?>
                        <?php if($user['status']!=='active'):?>
                            <button type="button" data-action="status" data-status="active" class="success"><i class="fa-solid fa-lock-open" aria-hidden="true"></i><span>Restablecer acceso</span></button>
                        <?php endif;?>
                        <?php if($user['status']!=='blocked'):?>
                            <button type="button" data-action="status" data-status="blocked" class="warning"><i class="fa-solid fa-lock" aria-hidden="true"></i><span>Bloquear acceso</span></button>
                        <?php endif;?>
                        <hr class="user-actions-separator">
                        <button type="button" data-action="trash" class="danger"><i class="fa-solid fa-trash-can" aria-hidden="true"></i><span>Enviar a Papelera</span></button>
                    </div>
                </details>
                <script type="application/json" class="user-data"><?=json_encode($user,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_UNICODE)?></script>
            </article>
            <?php endforeach;?>
            <div class="users-search-empty" hidden><i class="fa-solid fa-magnifying-glass"></i><strong>Sin coincidencias</strong><p>Prueba con otro usuario, nombre, correo o cédula.</p></div>
        </div><?php endif;?>
    </section>
</div>

<div class="user-modal" id="userModal" hidden><div class="user-modal-card" role="dialog" aria-modal="true" aria-labelledby="userModalTitle">
    <header><div><span>Cuenta institucional</span><h2 id="userModalTitle">Nuevo usuario</h2></div><button type="button" data-close-modal aria-label="Cerrar"><i class="fa-solid fa-xmark"></i></button></header>
    <form id="userForm"><input type="hidden" name="_csrf" value="<?=e($adminUserCsrf)?>"><input type="hidden" name="id" value=""><input type="hidden" name="career_id" value="<?= (int)($career['id']??0)?>"><input type="hidden" name="academic_period_id" value="<?= (int)($period['id']??0)?>">
        <button class="import-entry" id="importUsersButton" type="button"><i class="fa-solid fa-file-import"></i><span><strong>Importar una lista</strong><small>Crea varios estudiantes o docentes desde CSV, TXT o texto pegado.</small></span><i class="fa-solid fa-chevron-right"></i></button>
        <div class="form-grid">
            <label class="wide"><span>Nombre completo</span><input name="full_name" required maxlength="180" autocomplete="off"></label>
            <label class="wide"><span>Correo</span><input type="email" name="email" required maxlength="190" autocomplete="off"></label>
            <div class="role-fields identity-fields wide" data-for="student teacher"><label><span>Cédula</span><input name="institutional_code" inputmode="numeric" minlength="10" maxlength="10" pattern="[0-9]{10}" placeholder="Ingrese la cédula"></label></div>
            <label><span>Tipo de usuario</span><select name="role" required><option value="student">Estudiante</option><option value="teacher">Docente</option><option value="administrator" hidden>Cuenta inicial temporal</option></select></label>
            <label class="student-user-field" data-role-field="student"><span>Usuario <small>(opcional)</small></span><input name="username" maxlength="80" placeholder="Ej. maria.perez"></label>
            <label class="teacher-title-field" data-role-field="teacher"><span>Título académico <small>(opcional)</small></span><input name="academic_title" maxlength="120" placeholder="Ej. Msc."></label>
            <div class="role-fields student-fields wide" data-for="student">
                <div class="user-fixed-field"><i class="fa-solid fa-code" aria-hidden="true"></i><div><span>Carrera</span><strong><?=e($career['name']??'Desarrollo de Software')?></strong></div></div>
                <label><span>Semestre</span><select name="semester"><option value="">Selecciona</option><?php for($semester=1;$semester<=4;$semester++):?><option value="<?=$semester?>"><?=$semester?>.º semestre</option><?php endfor;?></select></label>
                <div class="user-fixed-field"><i class="fa-regular fa-calendar" aria-hidden="true"></i><div><span>Periodo académico</span><strong><?=e($period['name']??'Sin periodo activo')?></strong></div></div>
            </div>
            <div class="role-fields teacher-fields wide" data-for="teacher"><label class="permission-card"><input type="checkbox" name="can_manage_thesis" value="1"><span><strong>Gestión de Titulación</strong><small>Permite gestionar procesos de tribunal y titulación.</small></span></label><label class="permission-card"><input type="checkbox" name="is_admin" value="1"><span><strong>Acceso administrativo</strong><small>Permite acceder a funciones administrativas del sistema.</small></span></label></div>
        </div>
        <div class="form-note" id="temporaryPasswordNote"><i class="fa-solid fa-key"></i><span>La cuenta se creará con la contraseña temporal <strong>Istel2026+</strong> y deberá cambiarla.</span></div><div class="users-message" id="userFormMessage" hidden></div>
        <footer><button type="button" class="secondary" data-close-modal>Cancelar</button><button type="submit" class="primary">Guardar usuario</button></footer>
    </form>
</div></div>

<div class="user-confirm" id="userConfirm" hidden>
    <div role="alertdialog" aria-modal="true">
        <i class="fa-solid fa-triangle-exclamation"></i>
        <h2 id="confirmTitle">Confirmar acción</h2>
        <p id="confirmText"></p>

        <div class="user-trash-reason" id="trashReasonField" hidden>
            <label class="form-grid wide" style="display: grid; text-align: left; gap: 8px;">
                <span>Motivo de eliminación</span>
                <select id="trashReasonSelect" style="width: 100%; min-height: 44px; padding: 0 12px; border: 1px solid var(--line); border-radius: 10px; background: var(--surface-soft); color: var(--text); font: inherit;">
                    <option value="">Selecciona un motivo</option>
                    <option value="duplicate">Cuenta duplicada</option>
                    <option value="disengaged">Usuario retirado o desvinculado</option>
                    <option value="created_by_error">Registro creado por error</option>
                    <option value="administrative_request">Solicitud administrativa</option>
                    <option value="other">Otro (especificar motivo)</option>
                </select>
            </label>

            <label id="trashReasonDetailField" style="display: none; margin-top: 12px; text-align: left;" hidden>
                <span style="display: block; font-weight: 750; margin-bottom: 6px;">Detalle del motivo</span>
                <textarea id="trashReason" maxlength="500" rows="3" placeholder="Indica detalladamente el motivo de envío a Papelera" style="box-sizing: border-box; width: 100%; padding: 10px 12px; border: 1px solid var(--line); border-radius: 10px; background: var(--surface-soft); color: var(--text); font: inherit; resize: vertical; min-height: 76px;"></textarea>
            </label>
        </div>

        <div style="margin-top: 20px; display: flex; justify-content: center; gap: 10px;">
            <button type="button" class="secondary" data-cancel-confirm>Cancelar</button>
            <button type="button" class="primary" data-accept-confirm>Confirmar</button>
        </div>
    </div>
</div>

<div class="user-modal import-modal" id="importUsersModal" hidden><div class="user-modal-card" role="dialog" aria-modal="true" aria-labelledby="importUsersTitle">
    <header><div><span>Creación masiva</span><h2 id="importUsersTitle">Importar usuarios</h2></div><button type="button" data-close-import aria-label="Cerrar"><i class="fa-solid fa-xmark"></i></button></header>
    <form id="importUsersForm"><input type="hidden" name="_csrf" value="<?=e($adminUserCsrf)?>"><input type="hidden" name="career_id" value="<?= (int)($career['id']??0)?>"><input type="hidden" name="academic_period_id" value="<?= (int)($period['id']??0)?>">
        <div class="import-steps"><button type="button" class="active" data-import-step-button="1">1. Configurar</button><button type="button" data-import-step-button="2">2. Revisar</button><button type="button" data-import-step-button="3">3. Verificar</button></div>
        <section class="import-panel" data-import-step="1">
        <div class="form-grid import-config"><label><span>Tipo de usuarios</span><select name="role"><option value="student">Estudiantes</option><option value="teacher">Docentes</option></select></label><label class="import-student"><span>Semestre</span><select name="semester"><option value="">Selecciona</option><?php for($semester=1;$semester<=4;$semester++):?><option value="<?=$semester?>"><?=$semester?>.º semestre</option><?php endfor;?></select></label><div class="user-fixed-field"><i class="fa-solid fa-code" aria-hidden="true"></i><div><span>Carrera</span><strong><?=e($career['name']??'Desarrollo de Software')?></strong></div></div><div class="user-fixed-field"><i class="fa-regular fa-calendar" aria-hidden="true"></i><div><span>Periodo</span><strong><?=e($period['name']??'Sin periodo activo')?></strong></div></div></div>
            <div class="import-source"><label class="file-drop"><i class="fa-solid fa-file-csv"></i><strong>Sube un archivo CSV o TXT</strong><small>Máximo 1 MB · hasta 500 usuarios</small><input type="file" name="file" accept=".csv,.txt,text/csv,text/plain"></label><span>o pega la lista</span><label><span class="sr-only">Lista de usuarios</span><textarea name="content" rows="6" placeholder="Nombre completo,Correo,Cédula,Usuario&#10;María Pérez,maria@email.com,0202053831,maria.perez"></textarea></label><div class="import-format-info" id="importFormatInfo"><strong><i class="fa-solid fa-circle-info" aria-hidden="true"></i> Formato de importación</strong><ul><li>Obligatorios: nombre, correo y cédula.</li><li>Usuario es opcional.</li><li>Puedes cambiar el orden si incluyes encabezados reconocibles.</li><li>Sin encabezados, utiliza el orden mostrado en el ejemplo.</li><li>Máximo 500 usuarios por importación.</li></ul></div></div>
            <footer><button type="button" class="secondary" data-close-import>Cancelar</button><button type="button" class="primary" data-import-next> Siguiente</button></footer>
        </section>
        <section class="import-panel" data-import-step="2" hidden><div class="import-table-wrap"><table><thead><tr><th>Fila</th><th>Nombre</th><th>Correo</th><th>Cédula</th><th data-import-extra-heading>Usuario</th><th>Resultado</th></tr></thead><tbody data-import-rows></tbody></table></div><footer><button type="button" class="secondary" data-close-import>Cancelar</button><button type="button" class="secondary" data-import-back>Atrás</button><button type="button" class="primary" data-import-next>Siguiente</button></footer></section>
        <section class="import-panel" data-import-step="3" hidden><div class="import-verification"><h3>Verificación final</h3><dl data-import-config-summary></dl><p class="import-password-note">Las cuentas se crearán con la contraseña temporal institucional y deberán cambiarla al iniciar sesión.</p></div><div class="import-table-wrap compact"><table><thead><tr><th>Nombre</th><th>Correo</th><th>Cédula</th><th data-import-final-extra-heading>Usuario</th></tr></thead><tbody data-import-final-rows></tbody></table></div><footer><button type="button" class="secondary" data-close-import>Cancelar</button><button type="button" class="secondary" data-import-back>Atrás</button><button type="button" class="primary" data-import-create>Crear</button></footer></section>
        <div class="users-message" id="importMessage" hidden></div>
    </form>
</div></div>
<div class="user-confirm" id="importConfirm" hidden><div role="alertdialog" aria-modal="true"><i class="fa-solid fa-user-check"></i><h2>Crear usuarios</h2><p>¿Estás seguro de crear todas las cuentas mostradas? Esta acción quedará registrada.</p><div><button type="button" class="secondary" data-cancel-import-confirm>Cancelar</button><button type="button" class="primary" data-accept-import-confirm>Crear</button></div></div></div>

<div id="adminUsersConfig" data-save="<?=e($adminUserEndpoints['save']??'')?>" data-status="<?=e($adminUserEndpoints['status']??'')?>" data-password="<?=e($adminUserEndpoints['password']??'')?>" data-import="<?=e($adminUserEndpoints['import']??'')?>" data-trash="<?=e(route('admin-trash-user'))?>" data-csrf="<?=e($adminUserCsrf??'')?>" data-trash-csrf="<?=e((new AuthSessionService())->csrfToken('admin_trash'))?>"></div>
