<header class="as-head">
    <span>Administración</span>
    <h1>Configuración del sistema</h1>
    <p>Parámetros institucionales y reglas aplicadas en toda la plataforma.</p>
</header>

<?php if ($settingsError): ?>
    <p class="as-error"><?= e($settingsError) ?></p>
<?php endif; ?>

<div class="as-container">
    <form class="as-form" id="settingsForm" action="<?= e($settingsSaveEndpoint) ?>" method="POST" novalidate>
        <input type="hidden" name="_csrf" value="<?= e($settingsCsrf) ?>">

        <!-- Información institucional -->
        <div class="as-settings-section" id="tab-general">
            <section class="as-card">
                <header>
                    <i class="fa-solid fa-building-columns"></i>
                    <div>
                        <h2>Información institucional</h2>
                        <p>Nombre utilizado en comunicaciones y documentos oficiales del instituto.</p>
                    </div>
                </header>
                <div class="as-field-group">
                    <label for="input_institution_name">
                        <span>Nombre de la institución</span>
                    </label>
                    <input type="text" id="input_institution_name" name="institution_name" value="<?= e($settings['institution_name']) ?>" maxlength="180" required class="as-input">
                    <span class="as-field-help">Este nombre aparecerá en reportes, memorandos y encabezados oficiales.</span>
                    <span class="as-field-error" id="error_institution_name" hidden></span>
                </div>
            </section>
        </div>

        <!-- Códigos de proyectos -->
        <div class="as-settings-section" id="tab-projects">
            <section class="as-card">
                <header>
                    <i class="fa-solid fa-hashtag"></i>
                    <div>
                        <h2>Códigos de proyectos</h2>
                        <p>Define los prefijos y la longitud de la numeración para proyectos nuevos.</p>
                    </div>
                </header>
                <?php
                $codeTypes = [
                    'thesis' => 'Titulación',
                    'thesis_profile' => 'Perfil de tesis',
                    'pis' => 'Proyecto integrador de saberes',
                    'practice' => 'Prácticas preprofesionales',
                    'community' => 'Proyecto de vinculación',
                ];
                ?>
                <div class="as-code-grid">
                    <?php foreach ($codeTypes as $key => $label): ?>
                        <div class="as-field-group">
                            <label for="input_prefix_<?= e($key) ?>">
                                <span><?= e($label) ?></span>
                                <button type="button" class="as-help-btn" data-tooltip="Se utilizan únicamente para generar códigos de proyectos nuevos. Los códigos existentes no se modifican." aria-label="Ayuda para <?= e($label) ?>">
                                    <i class="fa-solid fa-circle-info"></i>
                                </button>
                            </label>
                            <input type="text" id="input_prefix_<?= e($key) ?>" name="project_code_prefixes[<?= e($key) ?>]" value="<?= e($settings['project_code_prefixes'][$key]) ?>" minlength="2" maxlength="6" pattern="[A-Z]{2,6}" required class="as-input as-uppercase">
                            <span class="as-field-error" id="error_prefix_<?= e($key) ?>" hidden></span>
                        </div>
                    <?php endforeach; ?>

                    <div class="as-field-group">
                        <label for="input_project_code_digits">
                            <span>Dígitos de la numeración</span>
                            <button type="button" class="as-help-btn" data-tooltip="Define cuántos números aparecen después del año. Con 3 dígitos: TIT-2026-001." aria-label="Ayuda para dígitos de numeración">
                                <i class="fa-solid fa-circle-info"></i>
                            </button>
                        </label>
                        <input type="number" id="input_project_code_digits" name="project_code_digits" min="2" max="6" value="<?= (int)$settings['project_code_digits'] ?>" required class="as-input">
                        <span class="as-field-error" id="error_project_code_digits" hidden></span>
                    </div>
                </div>

                <div class="as-note">
                    <i class="fa-solid fa-circle-info"></i>
                    <span>Ejemplo: <strong id="projectCodeExample"><?= e($settings['project_code_prefixes']['thesis']) ?>-<?= date('Y') ?>-<?= str_pad('1', (int)$settings['project_code_digits'], '0', STR_PAD_LEFT) ?></strong>. Los cambios solo afectan códigos futuros; los existentes no se modifican ni se reutilizan.</span>
                </div>
            </section>
        </div>

        <!-- Archivos -->
        <div class="as-settings-section" id="tab-files">
            <section class="as-card">
                <header>
                    <i class="fa-solid fa-file-shield"></i>
                    <div>
                        <h2>Archivos</h2>
                        <p>Esta política se aplica a cada archivo cargado en la plataforma.</p>
                    </div>
                </header>
                <div class="as-grid">
                    <div class="as-field-group">
                        <label for="input_file_max_mb">
                            <span>Tamaño máximo por archivo (MB)</span>
                            <button type="button" class="as-help-btn" data-tooltip="Tamaño máximo permitido para un único archivo." aria-label="Ayuda para límite por archivo">
                                <i class="fa-solid fa-circle-info"></i>
                            </button>
                        </label>
                        <input type="number" id="input_file_max_mb" name="file_max_mb" min="1" max="<?= (int)$uploadPolicy['file_ceiling_mb'] ?>" value="<?= (int)$uploadPolicy['max_mb'] ?>" required class="as-input">
                        <span class="as-field-help">Máximo disponible: <?= (int)$uploadPolicy['file_ceiling_mb'] ?> MB</span>
                        <span class="as-field-error" id="error_file_max_mb" hidden></span>
                    </div>

                    <div class="as-field-group">
                        <label for="input_file_total_max_mb">
                            <span>Límite total por operación (MB)</span>
                            <button type="button" class="as-help-btn" data-tooltip="Tamaño máximo acumulado entre todos los archivos incluidos en una entrega." aria-label="Ayuda para límite total por entrega">
                                <i class="fa-solid fa-circle-info"></i>
                            </button>
                        </label>
                        <input type="number" id="input_file_total_max_mb" name="file_total_max_mb" min="1" max="<?= (int)$uploadPolicy['operation_ceiling_mb'] ?>" value="<?= (int)$uploadPolicy['total_max_mb'] ?>" required class="as-input">
                        <span class="as-field-help">Máximo disponible: <?= (int)$uploadPolicy['operation_ceiling_mb'] >= 1024 ? '1 GB' : (int)$uploadPolicy['operation_ceiling_mb'].' MB' ?></span>
                        <span class="as-field-error" id="error_file_total_max_mb" hidden></span>
                    </div>
                </div>
                <?php if ((int)$uploadPolicy['file_ceiling_mb'] < (int)$uploadPolicy['application_file_ceiling_mb'] || (int)$uploadPolicy['operation_ceiling_mb'] < (int)$uploadPolicy['application_operation_ceiling_mb']): ?>
                    <p class="as-file-server-warning"><i class="fa-solid fa-triangle-exclamation"></i> La configuración actual del servidor limita el tamaño de las cargas. En este entorno puedes configurar hasta <?= (int)$uploadPolicy['file_ceiling_mb'] ?> MB por archivo y <?= (int)$uploadPolicy['operation_ceiling_mb'] ?> MB por operación. Para utilizar la capacidad máxima del sistema, un responsable técnico debe ampliar los límites de carga del servidor.</p>
                <?php else: ?>
                    <p class="as-file-capacity-note"><i class="fa-solid fa-circle-info"></i> El sistema permite configurar hasta 500 MB por archivo y 1 GB por operación. Estos límites se aplican a las nuevas cargas de la plataforma.</p>
                <?php endif; ?>

                <div class="as-format-legend" aria-label="Leyenda de capacidades">
                    <span><i class="fa-solid fa-eye"></i><strong>Consulta en plataforma</strong> PDF, DOCX, TXT e imágenes pueden abrirse dentro de la plataforma según sus límites técnicos de visualización.</span>
                    <span><i class="fa-solid fa-folder-open"></i><strong>ZIP</strong> Navegable y descargable.</span>
                    <span><i class="fa-solid fa-download"></i><strong>Solo descarga</strong> XLSX y PPTX.</span>
                </div>
                <div class="as-format-contexts">
                    <section class="as-format-context"><header><h3>Archivos de borradores</h3><p>Formatos que pueden adjuntarse durante la creación y preparación inicial de un proyecto.</p></header><div class="as-format-chip-list"><span class="as-format-static-chip">PDF</span><span class="as-format-static-chip">DOCX</span><span class="as-format-static-chip">ZIP</span></div></section>
                    <section class="as-format-context"><header><h3>Documentos de proyectos y materiales de apoyo</h3><p>Formatos permitidos en documentos asociados a los proyectos y en materiales de apoyo disponibles en la plataforma.</p></header><div class="as-format-context-group"><h4><i class="fa-solid fa-eye"></i> Consulta en plataforma</h4><div class="as-format-chip-list"><span class="as-format-static-chip">PDF</span><span class="as-format-static-chip">DOCX</span><span class="as-format-static-chip">TXT</span><span class="as-format-static-chip">PNG</span><span class="as-format-static-chip">JPG / JPEG</span><span class="as-format-static-chip">WEBP</span></div></div><div class="as-format-context-group"><h4><i class="fa-solid fa-folder-open"></i> Navegable y descargable</h4><div class="as-format-chip-list"><span class="as-format-static-chip">ZIP</span></div></div><div class="as-format-context-group"><h4><i class="fa-solid fa-download"></i> Solo descarga</h4><div class="as-format-chip-list"><span class="as-format-static-chip">XLSX</span><span class="as-format-static-chip">PPTX</span></div></div></section>
                </div>
            </section>
        </div>

        <!-- Acceso inicial -->
        <div class="as-settings-section" id="tab-security">
            <section class="as-card">
                <header>
                    <i class="fa-solid fa-key"></i>
                    <div>
                        <h2>Acceso inicial</h2>
                        <p>Credenciales utilizadas en nuevos accesos y restablecimientos.</p>
                    </div>
                </header>
                <div class="as-grid">
                    <div class="as-field-group">
                        <label for="input_temporary_password">
                            <span>Nueva contraseña temporal (opcional)</span>
                            <span class="as-status-badge <?= !empty($temporaryPasswordConfigured) ? 'is-active' : 'is-warning' ?>" title="<?= !empty($temporaryPasswordConfigured) ? 'Hay una contraseña temporal activa almacenada de forma segura.' : 'Configura una contraseña temporal para habilitar nuevas altas y restablecimientos.' ?>"><i class="fa-solid <?= !empty($temporaryPasswordConfigured) ? 'fa-shield-halved' : 'fa-triangle-exclamation' ?>"></i> <?= !empty($temporaryPasswordConfigured) ? 'Configurada' : 'Requiere configuración' ?></span>
                        </label>
                        <input type="text" id="input_temporary_password" name="temporary_password" minlength="10" maxlength="128" autocomplete="off" placeholder="Escribe una nueva contraseña para reemplazar la actual" class="as-input">
                        <span class="as-field-help">Si no deseas cambiarla, deja este campo vacío.</span>
                    </div>
                    <div class="as-field-group">
                        <label for="input_temporary_password_days">
                            <span>Vigencia (días)</span>
                        </label>
                        <input type="number" id="input_temporary_password_days" name="temporary_password_days" min="1" max="30" value="<?= (int)$settings['temporary_password_days'] ?>" class="as-input" required>
                        <span class="as-field-help">Tiempo máximo durante el cual una contraseña temporal puede utilizarse antes de expirar.</span>
                    </div>
                </div>
                <div class="as-checkbox-compact-group">
                    <label class="as-checkbox-compact">
                        <input type="checkbox" name="temporary_password_force_change" value="1" <?= !empty($settings['temporary_password_force_change'])?'checked':'' ?>>
                        <div class="as-checkbox-text">
                            <strong>Exigir cambio en el siguiente inicio de sesión</strong>
                            <span>El usuario deberá crear una nueva contraseña al acceder.</span>
                        </div>
                    </label>
                </div>
            </section>
        </div>

        <!-- Plazos y conservación -->
        <div class="as-settings-section" id="tab-retention">
            <section class="as-card">
                <header>
                    <i class="fa-solid fa-clock-rotate-left"></i>
                    <div>
                        <h2>Plazos y conservación</h2>
                        <p>Gestión de tiempos de retención, ventanas de recuperación y alertas en toda la plataforma.</p>
                    </div>
                </header>

                <div class="as-retention-group">
                    <div class="as-retention-header">
                        <h3><i class="fa-solid fa-trash-can"></i> Papelera general</h3>
                        <p>Define cuándo los elementos eliminados pasan a estar disponibles para eliminación definitiva.</p>
                    </div>
                    <div class="as-grid">
                        <div class="as-field-group">
                            <label for="input_retention_users_days">
                                <span>Usuarios (días)</span>
                                <button type="button" class="as-help-btn" data-tooltip="Transcurrido este plazo, el usuario podrá eliminarse definitivamente." aria-label="Ayuda retención usuarios">
                                    <i class="fa-solid fa-circle-info"></i>
                                </button>
                            </label>
                            <input type="number" id="input_retention_users_days" name="retention_users_days" min="1" max="365" value="<?= (int)$settings['retention_users_days'] ?>" class="as-input" required>
                        </div>
                        <div class="as-field-group">
                            <label for="input_retention_projects_days">
                                <span>Proyectos (días)</span>
                                <button type="button" class="as-help-btn" data-tooltip="Transcurrido este plazo, el proyecto podrá eliminarse definitivamente." aria-label="Ayuda retención proyectos">
                                    <i class="fa-solid fa-circle-info"></i>
                                </button>
                            </label>
                            <input type="number" id="input_retention_projects_days" name="retention_projects_days" min="1" max="365" value="<?= (int)$settings['retention_projects_days'] ?>" class="as-input" required>
                        </div>
                        <div class="as-field-group">
                            <label for="input_retention_materials_days">
                                <span>Materiales (días)</span>
                                <button type="button" class="as-help-btn" data-tooltip="Transcurrido este plazo, el material podrá eliminarse definitivamente." aria-label="Ayuda retención materiales">
                                    <i class="fa-solid fa-circle-info"></i>
                                </button>
                            </label>
                            <input type="number" id="input_retention_materials_days" name="retention_materials_days" min="1" max="365" value="<?= (int)$settings['retention_materials_days'] ?>" class="as-input" required>
                        </div>
                    </div>
                </div>

                <div class="as-retention-group">
                    <div class="as-retention-header">
                        <h3><i class="fa-solid fa-user-clock"></i> Sesión</h3>
                        <p>Controla cuándo una sesión sin actividad debe volver a validarse.</p>
                    </div>
                    <div class="as-grid">
                        <div class="as-field-group">
                            <label for="input_session_inactivity_minutes">
                                <span>Tiempo de inactividad de sesión</span>
                            </label>
                            <input type="number" id="input_session_inactivity_minutes" name="session_inactivity_minutes" min="1" max="1440" step="1" value="<?= (int)($settings['session_inactivity_minutes'] ?? 30) ?>" class="as-input" required>
                            <span class="as-field-help">Minutos de inactividad antes de cerrar la sesión.</span>
                            <span class="as-field-error" id="error_session_inactivity_minutes" hidden></span>
                        </div>
                    </div>
                </div>

                <div class="as-retention-group">
                    <div class="as-retention-header">
                        <h3><i class="fa-solid fa-bell"></i> Notificaciones</h3>
                        <p>Las notificaciones en papelera se eliminan automáticamente al cumplir el plazo.</p>
                    </div>
                    <div class="as-grid">
                        <div class="as-field-group">
                            <label for="input_notification_trash_retention_days">
                                <span>Notificaciones en papelera (días)</span>
                                <button type="button" class="as-help-btn" data-tooltip="Transcurrido este plazo, el sistema eliminará automáticamente la notificación. El aviso de 'Expira pronto' se muestra 7 días antes." aria-label="Ayuda retención notificaciones">
                                    <i class="fa-solid fa-circle-info"></i>
                                </button>
                            </label>
                            <input type="number" id="input_notification_trash_retention_days" name="notification_trash_retention_days" min="1" max="365" value="<?= (int)$settings['notification_trash_retention_days'] ?>" class="as-input" required>
                        </div>
                    </div>
                </div>

                <div class="as-retention-group">
                    <div class="as-retention-header">
                        <h3><i class="fa-solid fa-file-arrow-up"></i> Recuperación de archivos</h3>
                        <p>Ventana de tiempo para restaurar archivos individuales retirados de expedientes.</p>
                    </div>
                    <div class="as-grid">
                        <div class="as-field-group">
                            <label for="input_withdrawn_file_restore_hours">
                                <span>Archivos retirados (horas)</span>
                                <button type="button" class="as-help-btn" data-tooltip="Una vez vencido el plazo, el archivo deja de estar disponible para restauración." aria-label="Ayuda recuperación archivos">
                                    <i class="fa-solid fa-circle-info"></i>
                                </button>
                            </label>
                            <input type="number" id="input_withdrawn_file_restore_hours" name="withdrawn_file_restore_hours" min="1" max="72" value="<?= (int)$settings['withdrawn_file_restore_hours'] ?>" class="as-input" required>
                        </div>
                    </div>
                </div>

                <div class="as-retention-group">
                    <div class="as-retention-header">
                        <h3><i class="fa-solid fa-graduation-cap"></i> Gestión académica y calendario</h3>
                        <p>Ventanas temporales y generación previa de alertas institucionales.</p>
                    </div>
                    <div class="as-grid">
                        <div class="as-field-group">
                            <label for="input_academic_period_reversal_hours">
                                <span>Revertir cierre de período (horas)</span>
                                <button type="button" class="as-help-btn" data-tooltip="Después de este plazo ya no podrá deshacerse el cierre mediante la interfaz." aria-label="Ayuda reversión período">
                                    <i class="fa-solid fa-circle-info"></i>
                                </button>
                            </label>
                            <input type="number" id="input_academic_period_reversal_hours" name="academic_period_reversal_hours" min="1" max="72" value="<?= (int)$settings['academic_period_reversal_hours'] ?>" class="as-input" required>
                        </div>
                        <div class="as-field-group">
                            <label for="input_academic_period_reminder_days">
                                <span>Avisar sobre período académico (días antes)</span>
                                <button type="button" class="as-help-btn" data-tooltip="Define con cuántos días de anticipación se generan avisos sobre el inicio o cierre de un período." aria-label="Ayuda avisos período">
                                    <i class="fa-solid fa-circle-info"></i>
                                </button>
                            </label>
                            <input type="number" id="input_academic_period_reminder_days" name="academic_period_reminder_days" min="1" max="30" value="<?= (int)$settings['academic_period_reminder_days'] ?>" class="as-input" required>
                        </div>
                        <div class="as-field-group">
                            <label for="input_calendar_reminder_days">
                                <span>Recordar eventos (días antes)</span>
                                <button type="button" class="as-help-btn" data-tooltip="Define con cuántos días de anticipación se generan recordatorios de eventos del calendario." aria-label="Ayuda recordatorios calendario">
                                    <i class="fa-solid fa-circle-info"></i>
                                </button>
                            </label>
                            <input type="number" id="input_calendar_reminder_days" name="calendar_reminder_days" min="0" max="30" value="<?= (int)$settings['calendar_reminder_days'] ?>" class="as-input" required>
                        </div>
                    </div>
                </div>
            </section>
        </div>

        <!-- Acciones del Formulario -->
        <div class="as-form-actions">
            <button type="submit" class="as-submit-btn" id="asSubmitBtn" disabled aria-disabled="true">
                <i class="fa-solid fa-floppy-disk" id="asSubmitIcon"></i>
                <span class="as-spinner" id="asSubmitSpinner" hidden></span>
                <span id="asSubmitText">Guardar configuración</span>
            </button>
        </div>
    </form>
</div>

<!-- Sistema de Toast Reutilizable -->
<div class="as-toast-container" id="asToastContainer" aria-live="polite" aria-atomic="true"></div>

<!-- Modal de Confirmación Reutilizable -->
<div class="as-modal-overlay" id="asConfirmModal" hidden>
    <div class="as-modal" role="dialog" aria-modal="true" aria-labelledby="asModalTitle" aria-describedby="asModalDesc">
        <header class="as-modal-header">
            <span class="as-modal-icon" aria-hidden="true">
                <i class="fa-solid fa-triangle-exclamation"></i>
            </span>
            <h2 id="asModalTitle">Confirmar cambio</h2>
        </header>
        <div class="as-modal-body">
            <p id="asModalDesc">Este cambio puede afectar el funcionamiento general de la plataforma. ¿Deseas continuar?</p>
            <div class="as-modal-warning" id="asModalWarning" hidden></div>
        </div>
        <footer class="as-modal-footer">
            <button type="button" class="as-modal-btn-cancel" id="asModalCancelBtn">Cancelar</button>
            <button type="button" class="as-modal-btn-confirm" id="asModalConfirmBtn">Confirmar</button>
        </footer>
    </div>
</div>
