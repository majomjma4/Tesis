<!-- Inicio de skeleton loader -->
<section class="skeleton-loader" id="projectsSkeleton" aria-label="Cargando información del proyecto">
    <div class="skeleton-dashboard-grid">
        <div class="skeleton-card large">
            <span class="skeleton-line title"></span>
            <span class="skeleton-line"></span>
            <span class="skeleton-line"></span>
            <span class="skeleton-line medium"></span>
        </div>
        <div class="skeleton-card side">
            <span class="skeleton-line title"></span>
            <span class="skeleton-line"></span>
            <span class="skeleton-line"></span>
            <span class="skeleton-line short"></span>
        </div>
    </div>
</section>
<!-- Final de skeleton loader -->

<!-- Contenido principal de Proyectos (inicialmente oculto por JS o mostrado tras carga) -->
<div class="dashboard-container projects-container" id="projectsContent" style="display: none;">
    
    <!-- Inicio de grid principal de proyectos -->
    <div class="dashboard-top-grid">
        
        <!-- Columna Izquierda: Información de Proyecto e Historial Documental -->
        <div class="left-column">
            
            <!-- Ficha del Proyecto Principal -->
            <section class="project-card active-project-card" aria-label="Información detallada del proyecto">
                <div class="project-card-top">
                    <div>
                        <span class="section-eyebrow"><?= e($project['career']) ?></span>
                        <h2 class="section-title" style="margin-top: 5px;"><?= e($project['title']) ?></h2>
                    </div>
                    <span class="project-status <?= e($project['status_class']) ?>"><?= e($project['status_label']) ?></span>
                </div>
                
                <p><?= e($project['description']) ?></p>
                
                <div class="project-details" style="margin-top: 15px; margin-bottom: 20px;">
                    <div>
                        <span><i class="fa-solid fa-graduation-cap"></i> Semestre</span>
                        <strong><?= e($project['semester']) ?></strong>
                    </div>
                    <div>
                        <span><i class="fa-solid fa-chalkboard-user"></i> Tutor académico</span>
                        <strong><?= e($project['tutor']) ?></strong>
                    </div>
                    <div>
                        <span><i class="fa-solid fa-magnifying-glass"></i> Línea de investigación</span>
                        <strong><?= e($project['line_of_research']) ?></strong>
                    </div>
                    <div>
                        <span><i class="fa-solid fa-calendar-day"></i> Creado el</span>
                        <strong><?= e($project['created_at']) ?></strong>
                    </div>
                    <div>
                        <span><i class="fa-solid fa-clock"></i> Última actividad</span>
                        <strong><?= e($project['last_activity']) ?></strong>
                    </div>
                </div>
                
                <!-- Integrantes del Proyecto -->
                <div class="project-team-section">
                    <h3 style="font-size: 15px; margin-bottom: 12px; color: var(--text);"><i class="fa-solid fa-users"></i> Equipo de Trabajo</h3>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 12px;">
                        <?php foreach ($project['team'] as $member): ?>
                            <div class="team-member" style="background: var(--surface-soft); border: 1px solid var(--line); border-radius: 14px; padding: 10px;">
                                <span class="team-avatar" style="margin-left: 0; width: 34px; height: 34px;"><?= e($member['initial']) ?></span>
                                <div>
                                    <strong style="font-size: 13px;"><?= e($member['name']) ?></strong>
                                    <small style="font-size: 11px; color: var(--muted);"><?= e($member['role']) ?></small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>
            
            <!-- Historial de Versiones Documentales -->
            <section class="timeline-section" style="margin-top: 28px;" aria-label="Historial de entregas documentales">
                <div class="section-heading compact-heading" style="margin-bottom: 20px;">
                    <div>
                        <span class="section-eyebrow">Trazabilidad documental</span>
                        <h2 class="section-title">Historial de Entregas</h2>
                    </div>
                    <span style="font-size: 14px; color: var(--muted); background: var(--surface-soft); padding: 6px 12px; border-radius: 999px; border: 1px solid var(--line); font-weight: 700; height: max-content;">
                        <?= count($history) ?> <?= count($history) === 1 ? 'versión' : 'versiones' ?>
                    </span>
                </div>
                
                <div class="document-timeline">
                    <?php foreach ($history as $idx => $doc): ?>
                        <article class="timeline-card-wrapper">
                            <!-- Indicador de la línea de tiempo -->
                            <div class="timeline-indicator">
                                <div class="timeline-dot <?= $idx === 0 ? 'active' : '' ?>">
                                    <i class="fa-solid <?= $idx === 0 ? 'fa-circle-dot' : 'fa-circle' ?>"></i>
                                </div>
                                <div class="timeline-line"></div>
                            </div>
                            
                            <!-- Tarjeta de la versión -->
                            <div class="timeline-card show">
                                <div class="timeline-card-header">
                                    <div class="version-info">
                                        <span class="version-badge">V<?= e((string)$doc['version']) ?></span>
                                        <h3 class="phase-title"><?= e($doc['phase']) ?></h3>
                                    </div>
                                    <span class="project-status <?= e($doc['status_class']) ?>"><?= e($doc['status_label']) ?></span>
                                </div>
                                
                                <div class="file-delivery-info">
                                    <div class="file-details">
                                        <i class="fa-solid fa-file-pdf pdf-icon"></i>
                                        <div>
                                            <a href="#" class="file-download-link" title="Descargar archivo">
                                                <strong><?= e($doc['file_name']) ?></strong>
                                            </a>
                                            <small><?= e($doc['file_size']) ?> • Subido por <?= e($doc['submitted_by']) ?></small>
                                        </div>
                                    </div>
                                    <div class="delivery-time">
                                        <i class="fa-regular fa-calendar"></i> <?= e($doc['delivery_date']) ?> a las <?= e($doc['delivery_time']) ?>
                                    </div>
                                </div>
                                
                                <div class="tutor-feedback-block">
                                    <strong>Retroalimentación del Tutor:</strong>
                                    <p><?= e($doc['feedback']) ?></p>
                                </div>
                                
                                <!-- Observaciones de esta entrega si las tiene -->
                                <?php if (!empty($doc['observations'])): ?>
                                    <div class="timeline-observations-box" style="margin-top: 14px;">
                                        <button class="toggle-observations-btn" type="button" aria-expanded="false">
                                            <span><i class="fa-solid fa-list-check"></i> Ver observaciones (<?= count($doc['observations']) ?>)</span>
                                            <i class="fa-solid fa-chevron-down"></i>
                                        </button>
                                        <div class="observations-list-content" hidden>
                                            <div style="padding-top: 10px;">
                                                <?php foreach ($doc['observations'] as $obs): ?>
                                                    <div class="observation-card" style="margin-bottom: 8px; border-radius: 12px; padding: 12px;">
                                                        <p style="margin-bottom: 0; font-size: 13px; line-height: 1.45;"><?= e($obs) ?></p>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>
        </div>
        
        <!-- Columna Derecha: Nueva Entrega y Métricas -->
        <aside class="right-column">
            
            <!-- Formulario de Nueva Entrega -->
            <section class="reminders-panel upload-version-panel" aria-label="Nueva entrega de documento">
                <div class="panel-heading">
                    <h2><i class="fa-solid fa-cloud-arrow-up"></i> Entregar Versión</h2>
                    <span>Formulario</span>
                </div>
                
                <div class="upload-alert" id="uploadFormAlert" role="alert" aria-live="polite" style="display: none; margin-bottom: 15px; border-radius: 14px; padding: 12px; font-size: 13px;">
                    <i class="fa-solid fa-circle-info"></i>
                    <span>Mensaje de validación</span>
                </div>
                
                <form class="login-form upload-document-form" id="uploadDocumentForm" novalidate>
                    <div class="form-group">
                        <label for="deliveryPhase">Fase académica de la entrega</label>
                        <div class="input-wrap select-wrap" style="padding: 0 10px 0 15px;">
                            <i class="fa-solid fa-layer-group"></i>
                            <select id="deliveryPhase" name="deliveryPhase" style="width:100%; min-height:50px; border:0; outline:0; background:transparent; color:var(--text); font:inherit; cursor:pointer;">
                                <option value="" disabled selected style="background: var(--surface);">Selecciona la fase...</option>
                                <?php foreach ($phases as $phase): ?>
                                    <option value="<?= e($phase['id']) ?>" style="background: var(--surface);"><?= e($phase['label']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <span class="field-error">Por favor selecciona la fase del proyecto.</span>
                    </div>
                    
                    <div class="form-group">
                        <label>Archivo académico (PDF, Word o ZIP)</label>
                        <div class="file-drag-zone" id="fileDragZone">
                            <input type="file" id="academicFile" name="academicFile" accept=".pdf,.doc,.docx,.zip" style="display: none;">
                            <i class="fa-solid fa-file-arrow-up upload-icon"></i>
                            <strong>Arrastra tu archivo aquí</strong>
                            <span>o haz clic para explorar</span>
                            <small>Formatos admitidos: PDF, Word, ZIP (Máx. 10MB)</small>
                        </div>
                        <span class="field-error" id="fileErrorText">Este campo es obligatorio.</span>
                    </div>
                    
                    <div class="form-group">
                        <label for="deliveryComments">Notas o comentarios de entrega</label>
                        <div class="input-wrap" style="padding: 10px 15px; align-items: flex-start;">
                            <i class="fa-solid fa-comment-dots" style="margin-top: 10px;"></i>
                            <textarea id="deliveryComments" name="deliveryComments" placeholder="Escribe detalles adicionales para el tutor..." style="width:100%; min-height:80px; border:0; outline:0; background:transparent; color:var(--text); font:inherit; resize:vertical; padding:5px 0;"></textarea>
                        </div>
                    </div>
                    
                    <button class="login-submit" type="submit" style="margin-top: 10px; background: linear-gradient(135deg, var(--primary), var(--accent));">
                        <i class="fa-solid fa-paper-plane"></i>
                        Enviar documento
                    </button>
                </form>
            </section>
            
            <!-- Métricas e información complementaria -->
            <section class="notifications-panel projects-stats-panel" style="margin-top: 24px;" aria-label="Métricas del proyecto">
                <div class="panel-heading">
                    <h2><i class="fa-solid fa-chart-simple"></i> Avance del Proceso</h2>
                </div>
                
                <div class="project-stats-list" style="display: grid; gap: 14px;">
                    <div style="background: var(--surface-soft); border: 1px solid var(--line); border-radius: 16px; padding: 14px; display: flex; justify-content: space-between; align-items: center;">
                        <div style="display: grid; gap: 4px;">
                            <span style="font-size: 11px; color: var(--muted); font-weight: 800; text-transform: uppercase;">Entregas Realizadas</span>
                            <strong style="font-size: 20px; color: var(--text);"><?= count($history) ?> versiones</strong>
                        </div>
                        <i class="fa-solid fa-folder-tree" style="font-size: 22px; color: var(--blue); opacity: 0.6;"></i>
                    </div>
                    
                    <div style="background: var(--surface-soft); border: 1px solid var(--line); border-radius: 16px; padding: 14px; display: flex; justify-content: space-between; align-items: center;">
                        <div style="display: grid; gap: 4px;">
                            <span style="font-size: 11px; color: var(--muted); font-weight: 800; text-transform: uppercase;">Estado de Aprobación</span>
                            <strong style="font-size: 14px; color: var(--text);">Fase III en Revisión</strong>
                        </div>
                        <i class="fa-solid fa-spinner" style="font-size: 22px; color: var(--warning); opacity: 0.6;"></i>
                    </div>
                    
                    <div style="background: var(--surface-soft); border: 1px solid var(--line); border-radius: 16px; padding: 14px; display: flex; justify-content: space-between; align-items: center;">
                        <div style="display: grid; gap: 4px;">
                            <span style="font-size: 11px; color: var(--muted); font-weight: 800; text-transform: uppercase;">Observaciones Activas</span>
                            <strong style="font-size: 20px; color: var(--text);">0 activas</strong>
                        </div>
                        <i class="fa-solid fa-clipboard-check" style="font-size: 22px; color: var(--accent); opacity: 0.6;"></i>
                    </div>
                </div>
            </section>
            
        </aside>
    </div>
    <!-- Final de grid principal de proyectos -->
    
</div>
