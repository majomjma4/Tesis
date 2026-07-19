# Preparación para MariaDB

La aplicación queda preparada para MariaDB, InnoDB, `utf8mb4`, PDO y consultas preparadas. La persistencia continúa deshabilitada por defecto para no mezclar datos de demostración con información institucional.

## Orden de activación

1. Crear una base vacía con `utf8mb4_unicode_ci`.
2. Importar, en orden, las migraciones de `database/migrations`.
3. Crear usuarios con contraseñas generadas mediante `password_hash()`; nunca insertar contraseñas en texto plano.
4. Asignar roles en `user_roles` y completar `student_profiles` o `teacher_profiles`.
5. Registrar carreras, periodos y matrículas activas.
6. Copiar `app/config/database.local.php.example` como `database.local.php` y configurar una cuenta SQL con permisos solo sobre esta base.
7. Ejecutar `php scripts/database_health.php`.
8. Probar inicio de sesión con `auth_required=false`.
9. Activar `auth_required=true` únicamente después de confirmar el primer administrador.

## Principios establecidos

- Todas las tablas usan InnoDB y claves foráneas.
- Los textos usan `utf8mb4`.
- PDO trabaja con emulación deshabilitada y horario UTC.
- Las operaciones compuestas deben ejecutarse en transacciones.
- El código de proyecto se reserva con bloqueo `FOR UPDATE` dentro de la transacción.
- Los archivos permanecen fuera de `public`; la base guarda metadatos y rutas relativas, nunca BLOB.
- Las vistas no ejecutan SQL. El acceso se realiza mediante servicios y repositorios.
- Los permisos visibles nunca sustituyen la validación del servidor.
- Los únicos roles globales iniciales son Estudiante, Docente y Administrador. Tutor, líder o miembro de tribunal son funciones internas de un proyecto, no roles de acceso independientes.

## Convivencia temporal

`DB_ENABLED=false` conserva los modelos demostrativos actuales. Los nuevos servicios PDO no se utilizan hasta activar la conexión. La migración `20260719_prepare_database_adaptation.sql` conserva temporalmente las columnas antiguas de carrera y periodo para permitir una transición sin pérdida; se retirarán después de poblar las claves normalizadas.

## Primera integración recomendada

Implementar como una sola ruta vertical: autenticación real → crear proyecto → listar proyectos del usuario → abrir detalle. Después se migran entregas, revisión, calendario, notificaciones y repositorio de forma incremental.
