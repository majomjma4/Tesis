# Archivos de Material de Apoyo

Esta carpeta combina fixtures públicos de demostración con archivos generados
por los usuarios. Git los trata de forma diferente.

## Fixtures versionados

Los siguientes archivos pertenecen a los registros semilla de
`database/snapshot.sql` y deben estar disponibles después de cada `git clone` o
`git pull`:

- `guia_perfil_tesis.pdf`
- `lista_de_verificacion_para_elaboracion_del_perfil_de_tesis.txt`
- `material_tesis_completo.zip`
- `seguimiento_practicas.docx`
- `instructivo_proyectos_pis.pdf`
- `informe_vinculacion.docx`
- `reglamento_material_apoyo.txt`

Solo estos nombres explícitos, `.gitkeep` y este documento están exceptuados en
`.gitignore`.

## Archivos generados por el sistema

Las cargas y versiones creadas desde el módulo se almacenan en subcarpetas por
material, por ejemplo `1/<hash>.zip`. Pueden contener información privada y
pertenecen a la base de datos de cada instalación, por lo que permanecen
ignoradas y nunca deben añadirse como excepciones generales.

Para trasladar datos reales entre computadoras se debe respaldar y restaurar
juntos la base de datos y la carpeta completa `storage/support-materials/`,
fuera de Git. Para compartir un nuevo fixture, primero debe agregarse al
snapshot o a la migración semilla correspondiente y luego incluirse por su
nombre exacto como excepción en `.gitignore`.
