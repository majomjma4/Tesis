# Gestión Documental Académica

# Sistema web institucional para el seguimiento, revisión y gestión documental de proyectos académicos de la Carrera de Desarrollo de Software.

El proyecto busca centralizar el proceso de entrega, revisión, observaciones, control de versiones y almacenamiento de documentos académicos, reemplazando el intercambio mediante aplicaciones de mensajería y correos electrónicos.

## Capacidad de carga recomendada

Para habilitar la capacidad máxima de la aplicación (500 MB por archivo y 1 GB por operación), el administrador técnico del servidor debe configurar PHP con valores al menos equivalentes a `upload_max_filesize = 512M` y `post_max_size = 1100M`, y reiniciar el servicio web. La aplicación detecta la capacidad real del servidor y reduce automáticamente los máximos configurables cuando el entorno admite menos.

## Previsualización DOCX

El servidor requiere LibreOffice para generar de forma privada las vistas de revisión PDF de archivos DOCX. Configure `LIBREOFFICE_PATH` (por ejemplo, `C:\Program Files\LibreOffice\program\soffice.exe` en Windows o `/usr/bin/libreoffice` en Linux). El DOCX original permanece como el archivo académico oficial.

# Objetivo

Desarrollar una plataforma web que permita administrar de forma organizada los proyectos académicos de la institución, facilitando la comunicación entre estudiantes, docentes, tutores y jurados mediante un sistema de seguimiento documental con historial y repositorio institucional.

# Características principales

Autenticación
- Inicio de sesión institucional.
- Gestión de sesiones.
- Protección CSRF.
- Control de acceso por roles.

# Gestión de usuarios
- Docentes.
- Estudiantes.
- Docentes con acceso administrativo.

Estudiante y docente son los perfiles académicos del sistema. El acceso
administrativo se concede como un privilegio adicional únicamente a docentes,
sin hacerles perder su perfil académico ni sus asignaciones como tutores.

La cuenta administrativa inicial se conserva solamente para poner en marcha una
instalación nueva. El sistema protege al último administrador activo para evitar
que la plataforma quede sin acceso administrativo y registra los cambios
relevantes en la actividad administrativa.

# Dashboard

Panel principal con indicadores como:

- Proyectos asignados.
- Entregas pendientes.
- Observaciones.
- Documentos.
- Accesos rápidos.
- Actividad reciente.

# Gestión de proyectos

Permite administrar distintos tipos de proyectos académicos:

- Tesis
- Perfil de tesis
- Proyecto PIS
- Prácticas
- Vinculación

Cada proyecto posee información como:

- título
- autores
- tutor
- resumen
- tecnologías
- etiquetas
- semestre
- estado
- historial

# Seguimiento documental

Los estudiantes pueden subir nuevas versiones de sus documentos.

Cada entrega mantiene:

- historial de versiones
- fecha
- observaciones
- estado
- responsable de revisión

# Flujo de revisión

Los docentes pueden:

- revisar documentos
- registrar observaciones
- cambiar estados
- solicitar correcciones
- aprobar entregas

Estados disponibles:

- Pendiente
- Revisado
- Correcciones solicitadas
- Finalizado

# Centro de notificaciones

Sistema interno de notificaciones para informar:

- nuevas entregas
- observaciones
- cambios de estado
- asignaciones
- recordatorios

Incluye:

- lectura/no lectura
- archivado
- papelera
- filtros
- búsqueda

# Repositorio institucional

Catálogo público para consultar proyectos finalizados.

Incluye:

- búsqueda avanzada
- filtros
- favoritos
- contador de descargas
- detalle completo del proyecto
- explorador ZIP

# Explorador ZIP

Permite navegar el contenido del proyecto sin necesidad de descargar el archivo completo.

Funciones:

- navegación por carpetas
- breadcrumbs
- descarga individual
- descarga completa
- protección de rutas
- validación de archivos

# Material de apoyo

Repositorio institucional de documentos como:

- reglamentos
- formatos
- manuales
- documentos de apoyo

# Características:

- buscador
- filtros
- carrusel
- categorías
- contador
- vista responsive

# Calendario académico (en desarrollo)

Permitirá administrar:

- entregas
- reuniones
- defensas
- recordatorios
- eventos

# Seguridad

El sistema incorpora diferentes mecanismos de seguridad:

- Protección CSRF.
- Validaciones HTTP.
- Escape de contenido.
- Control de sesiones.
- Restricción por roles.
- Protección de archivos privados.
- Validación de rutas.
- Descargas seguras.

# Diseño

La interfaz fue desarrollada siguiendo un estilo institucional moderno.

# Características:

- Responsive.
- Modo claro y oscuro.
- Tarjetas informativas.
- Colores por tipo de proyecto.
- Componentes reutilizables.
- Navegación intuitiva.
- Sidebar adaptable.
- Dashboard moderno.

# Tecnologías

- Frontend

HTML5
CSS3
JavaScript

- Backend

PHP (MVC)

- Base de datos

MySQL / MariaDB

- Servidor

Apache (XAMPP)

- Control de versiones

Git
GitHub

# Arquitectura

El proyecto utiliza una arquitectura MVC (Model–View–Controller).

app/
│
├── controllers/
├── models/
├── views/
│   ├── layouts/
│   ├── dashboard/
│   ├── projects/
│   ├── repository/
│   ├── notifications/
│   └── ...
│
├── helpers/
├── config/
└── core/

public/
│
├── assets/
│   ├── css/
│   ├── js/
│   └── images/

index.php

# Instalación de la base de datos

La estructura y los datos transportables actuales se encuentran en
`database/snapshot.sql`. Las instrucciones para importarlos, aplicar migraciones
y crear la cuenta administrativa inicial están disponibles en
`database/README.md`.

## Requisitos de una instalación nueva

- PHP con `fileinfo`.
- PHP con `ZipArchive` o, en su defecto, soporte funcional para `PharData`.
- MariaDB/MySQL y permisos de escritura sobre `storage/support-materials`.
- Copiar `app/config/app.local.php.example` como `app/config/app.local.php`.
- Copiar `app/config/database.local.php.example` como
  `app/config/database.local.php` y completar las credenciales locales.
- Configurar `auth_required => true` antes de exponer el sistema.
- Importar el `database/snapshot.sql` actualizado.

Para comprobar los módulos disponibles:

```powershell
C:\xampp\php\php.exe -m
```

En otros entornos puede utilizarse `php -m`. La salida debe incluir `fileinfo`
y `zip`; si `zip` no está disponible, comprueba que PHP pueda utilizar
`PharData`:

```powershell
php -r "echo class_exists('PharData') ? 'PharData disponible' : 'PharData no disponible';"
```

Los archivos `app.local.php` y `database.local.php` son configuraciones privadas
de cada instalación y no deben publicarse en Git.

# Funcionalidades implementadas

✔ Sistema de autenticación

✔ Dashboard

✔ Gestión de proyectos

✔ Seguimiento documental

✔ Sistema de observaciones

✔ Estados de revisión

✔ Historial de documentos

✔ Repositorio institucional

✔ Explorador ZIP

✔ Material de apoyo

✔ Sistema de favoritos

✔ Búsqueda avanzada

✔ Filtros combinados

✔ Contador de descargas

✔ Protección CSRF

✔ Validaciones HTTP

✔ Diseño responsive

✔ Modo oscuro

✔ Componentes reutilizables

# Recordatorios programados

Los recordatorios de calendario y de perÃ­odos acadÃ©micos se sincronizan mediante
un proceso CLI periÃ³dico. El programador externo debe ejecutar diariamente:

```powershell
php scripts/sync_scheduled_reminders.php
```

El script no expone una ruta web y no debe ejecutarse desde el navegador.

# Funcionalidades en desarrollo 

- Calendario académico.
- Notificaciones en tiempo real.
- Reportes.
- Panel administrativo completo.
- Gestión avanzada de usuarios.
- Integración completa con base de datos.
- Asignación automática de jurados.
- Repositorio definitivo de proyectos institucionales.

# Objetivo institucional

La plataforma busca mejorar el proceso de gestión documental académica mediante un sistema centralizado que facilite el seguimiento de proyectos, reduzca la duplicidad de documentos, mantenga un historial de cambios y permita conservar un repositorio institucional organizado para futuras consultas.

# Estado del proyecto

# 🚧 En desarrollo activo

Actualmente se están implementando los módulos restantes y realizando mejoras continuas tanto en la interfaz como en la arquitectura del sistema para su posterior integración completa.
