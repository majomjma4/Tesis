<header class="as-head">
    <span>Administración</span>
    <h1>Configuración del sistema</h1>
    <p>Parámetros institucionales y reglas aplicadas en toda la plataforma.</p>
</header>
<?php if ($settingsError): ?><p class="as-error"><?= e($settingsError) ?></p><?php endif; ?>
<form class="as-form" id="settingsForm" action="<?= e($settingsSaveEndpoint) ?>">
    <input type="hidden" name="_csrf" value="<?= e($settingsCsrf) ?>">
    <section>
        <header><i class="fa-solid fa-building-columns"></i><div><h2>Información institucional</h2><p>Nombre utilizado en comunicaciones y documentos.</p></div></header>
        <label>Nombre de la institución<input name="institution_name" value="<?= e($settings['institution_name']) ?>" maxlength="180" required></label>
    </section>
    <section>
        <header><i class="fa-solid fa-hashtag"></i><div><h2>Códigos de proyectos</h2><p>Define los prefijos y la longitud de la numeración para proyectos nuevos.</p></div></header>
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
                <label>
                    <span><?= e($label) ?></span>
                    <input name="project_code_prefixes[<?= e($key) ?>]" value="<?= e($settings['project_code_prefixes'][$key]) ?>" minlength="2" maxlength="6" pattern="[A-Za-z0-9]{2,6}" required>
                </label>
            <?php endforeach; ?>
            <label>
                <span>Dígitos de la numeración</span>
                <input type="number" name="project_code_digits" min="2" max="6" value="<?= (int)$settings['project_code_digits'] ?>" required>
            </label>
        </div>
        <p class="as-note"><i class="fa-solid fa-circle-info"></i><span>Ejemplo: <strong id="projectCodeExample"><?= e($settings['project_code_prefixes']['thesis']) ?>-<?= date('Y') ?>-<?= str_pad('1', (int)$settings['project_code_digits'], '0', STR_PAD_LEFT) ?></strong>. Los cambios solo afectan códigos futuros; los existentes no se modifican ni se reutilizan.</span></p>
    </section>
    <section>
        <header><i class="fa-solid fa-file-shield"></i><div><h2>Archivos privados</h2><p>Formatos y límites validados en el servidor.</p></div></header>
        <div class="as-grid">
            <label>Límite por archivo (MB)<input type="number" name="file_max_mb" min="1" max="100" value="<?= (int)$settings['file_max_mb'] ?>" required></label>
            <label>Límite total por entrega (MB)<input type="number" name="file_total_max_mb" min="1" max="500" value="<?= (int)$settings['file_total_max_mb'] ?>" required></label>
        </div>
        <fieldset>
            <legend>Formatos habilitados</legend>
            <?php foreach (['pdf'=>'PDF · documentos','docx'=>'DOCX · editables','zip'=>'ZIP · código y anexos'] as $key=>$label): ?>
                <label><input type="checkbox" name="file_extensions[]" value="<?= $key ?>" <?= in_array($key,$settings['file_extensions'],true)?'checked':'' ?>><span><?= e($label) ?></span></label>
            <?php endforeach; ?>
        </fieldset>
    </section>
    <section class="as-locked">
        <header><i class="fa-solid fa-lock"></i><div><h2>Reglas protegidas</h2><p>Políticas que no pueden debilitarse desde la interfaz.</p></div></header>
        <dl><div><dt>Papelera</dt><dd>60 días</dd></div><div><dt>Contraseña temporal</dt><dd>Cambio obligatorio · 7 días</dd></div><div><dt>Almacenamiento</dt><dd>Privado, sin BLOB</dd></div></dl>
    </section>
    <p id="settingsMessage" hidden></p>
    <button type="submit">Guardar configuración</button>
</form>
