# DEVELOPMENT_STATUS.md

# Estado Actual del Desarrollo

> Actualización técnica: 18/07/2026. El proyecto está preparado para iniciar la integración controlada con MariaDB; la persistencia continúa deshabilitada.

> Administrador — Fase 1 completada: autenticación obligatoria, navegación por rol, sesión verificada, contraseña temporal, cambio seguro de contraseña y rutas administrativas protegidas.

Versión del documento: 1.0

Última actualización: 11/07/2026

---

# 1. Descripción

Este documento registra el estado actual del desarrollo de la plataforma.

Su objetivo es proporcionar una visión clara del progreso del proyecto, permitiendo identificar qué funcionalidades ya se encuentran implementadas, cuáles están en desarrollo y cuáles permanecen pendientes.

Este documento deberá actualizarse cada vez que se complete una funcionalidad importante.

Antes de comenzar una nueva tarea, se recomienda revisar este documento para conocer el estado actual del proyecto.

---

# 2. Estado General

Estado del proyecto:

En desarrollo

La arquitectura principal del sistema ya se encuentra implementada.

Actualmente el proyecto posee una estructura MVC funcional desarrollada en PHP, con una base sólida para continuar la implementación de la lógica del sistema.

La mayor parte del trabajo realizado hasta el momento corresponde a la organización del proyecto, estructura del código y desarrollo de la interfaz.

Las funcionalidades de negocio aún se encuentran en proceso de implementación.

---

# 3. Arquitectura

Estado:

🟢 Implementada

Implementado:

- Arquitectura MVC.
- Front Controller.
- Sistema de rutas.
- Controladores.
- Modelos.
- Vistas.
- Layouts.
- Helpers.
- Organización de assets.
- Separación de responsabilidades.

Observaciones:

La estructura general deberá mantenerse durante todo el desarrollo del proyecto.

No deberán realizarse cambios importantes en la arquitectura sin una justificación técnica.

---

# 4. Autenticación

Estado:

En desarrollo

Implementado:

- Interfaz de Login.
- Controlador de autenticación.
- Modelo de autenticación.
- Validaciones visuales.
- Redirección hacia el Dashboard.

Pendiente:

- Conexión a base de datos.
- Validación real de usuarios.
- Contraseñas cifradas.
- Manejo de sesiones.
- Recuperación de contraseña.
- Control de acceso.
- Recordar sesión.

---

# 5. Dashboard

Estado:

En desarrollo

Implementado:

- Sidebar.
- Navbar.
- Tarjetas informativas.
- Panel lateral.
- Diseño responsive.
- Modo oscuro (visual).
- Componentes principales de la interfaz.

Pendiente:

- Estadísticas reales.
- Datos dinámicos.
- Notificaciones reales.
- Calendario funcional.
- Integración con la base de datos.

---

# 6. Gestión de Proyectos

Estado:

🔴 Pendiente

Implementado:

- Diseño inicial de la interfaz.

Pendiente:

- Crear proyectos.
- Editar proyectos.
- Eliminar proyectos.
- Consultar proyectos.
- Gestión de integrantes.
- Definición del líder.
- Asignación de tutor.
- Estados del proyecto.
- Historial.
- Persistencia en base de datos.

---

# 7. Gestión Documental

Estado:

🔴 Pendiente

Pendiente:

- Subida de documentos.
- Validación de archivos.
- Gestión de PDF.
- Gestión de Word.
- Gestión de archivos ZIP.
- Descargas.
- Versionado.
- Organización de documentos.

---

# 8. Revisión Documental

Estado:

🔴 Pendiente

Pendiente:

- Observaciones.
- Comentarios.
- Correcciones.
- Historial de revisiones.
- Cambio de estados.
- Flujo docente.

---

# 9. Historial de Versiones

Estado:

🔴 Pendiente

Pendiente:

- Registro automático.
- Versionado.
- Historial de cambios.
- Consulta de versiones.
- Recuperación de versiones anteriores.

---

# 10. Notificaciones

Estado:

En desarrollo

Implementado:

- Diseño visual.

Pendiente:

- Generación automática.
- Persistencia.
- Marcado como leído.
- Notificaciones por eventos.
- Contador dinámico.

---

# 11. Calendario

Estado:

Funcional con persistencia temporal

Implementado:

- Gestión completa de eventos mediante modelo, controlador, vista y endpoint JSON.
- Persistencia temporal en `storage/calendar/events.json` hasta la migración a MySQL.
- Creación, edición, eliminación, finalización y reapertura de eventos.
- Vistas intercambiables de mes, semana y lista, con conservación de la vista elegida mediante almacenamiento local.
- Creación rápida al hacer doble clic sobre una fecha vacía.
- Reprogramación mediante arrastre entre fechas.
- Confirmación visual de eliminación y opción temporal para deshacerla.
- Detalle completo del recordatorio y navegación contextual hacia el apartado relacionado del proyecto.
- Clasificación por entrega, reunión, revisión y fecha límite.
- Prioridades alta, media y baja, con filtros específicos y ordenamiento en la vista de lista.
- Ordenamiento por proximidad, prioridad y eventos completados al final.
- Identificación visual de eventos vencidos.
- Búsqueda, filtros activos, limpieza conjunta y estados vacíos orientativos.
- Indicadores interactivos para eventos del mes, próximos siete días y progreso mensual.
- Cantidad completada, porcentaje y barra visual de progreso mensual.
- Agenda del día y sección independiente de próximos eventos.
- Diseño responsive, modo oscuro y navegación táctil entre periodos en dispositivos móviles.
- Selectores visuales personalizados y diálogos propios para las acciones principales.

Pendiente:

- Sustituir el archivo JSON por persistencia definitiva en MySQL.
- Relacionar los eventos con identificadores reales de proyectos y usuarios autenticados.
- Generar recordatorios automáticamente desde entregas, revisiones y fechas límite reales del sistema.
- Incorporar permisos por rol cuando se implemente la autenticación definitiva.

---

# 12. Repositorio Institucional

Estado:

En desarrollo

Implementado:

- Primera version funcional del repositorio institucional.
- Bloque de material complementario con guias y documentos de apoyo.
- Catalogo institucional de proyectos finalizados.
- Filtros por semestre, docente, categoria, tipo y PAO.
- Buscadores independientes para catalogo y material complementario.
- Resaltado visual de coincidencias en las busquedas.
- Estructura visual separada para encabezados, filtros y tarjetas.
- Vista principal del módulo ubicada en `app/views/repository/repositorio.php`.
- Carrusel para documentos de apoyo con cuatro tarjetas visibles y desplazamiento de una tarjeta por interacción.
- Navegación lateral limitada, sin recorrido circular y con flechas visibles únicamente cuando existe contenido en la dirección correspondiente.
- Límite visual de doce posiciones en el carrusel; cuando existen más de once documentos, la última posición se reserva para una tarjeta informativa de acceso a más contenido.
- Recalculo automático del carrusel al utilizar el buscador o el filtro de categoría.
- Skeleton loader para la precarga del repositorio.
- Diseño claro y oscuro consistente con las tarjetas del Dashboard.
- Distribución responsive del catálogo en cuatro columnas para escritorio, dos para tablet y una para móvil.
- Contenedor general centrado en pantallas de 1950 px en adelante sin alterar las proporciones internas del contenido.
- Listados principales protegidos contra crecimiento indefinido mediante páginas configurables de 10, 25, 50 o 100 registros.
- Bandeja personal de notificaciones paginada en sus vistas activa, archivada y papelera, con filtros y contadores SQL.
- Catálogo `Mis proyectos` paginado después de aplicar búsqueda, filtros y ordenamiento.
- Fase 1 de evolución del catálogo completada con contador descriptivo, colores por tipo, tecnologías, descargas y acción visual para explorar proyectos.
- Favoritos funcionales con persistencia temporal por sesión, aislamiento entre sesiones, filtro, contador y mensajes visuales.
- Acción de favoritos mediante POST con validación de proyecto publicado, token CSRF y respuestas JSON consistentes.
- Modelo temporal `FavoriteModel` separado de la vista y preparado para sustituirse por persistencia MySQL.
- Fase 3 completada con ruta y pantalla de detalle para proyectos publicados.
- Detalle académico responsive con encabezado, breadcrumbs, autores separados, tutor, resumen completo, tecnologías y palabras clave.
- Distribución adaptable 30/70 con panel informativo sticky únicamente en pantallas con altura suficiente.
- Fase 4 completada con exploración real y de solo lectura de archivos ZIP privados.
- Servicio `ArchiveService` para normalizar rutas, rechazar recorridos `../`, listar carpetas y archivos y calcular metadatos.
- Navegación asíncrona por carpetas, breadcrumbs internos, carpetas primero, tamaños legibles e iconos por formato.
- Estados funcionales para carpeta vacía, ZIP vacío, ZIP inexistente, ZIP ilegible y ruta inválida.
- Fixtures ZIP privados y regenerables para desarrollo, almacenados fuera de `public` e ignorados por Git.
- Respaldo de lectura mediante `PharData` porque la extensión `ZipArchive` no está habilitada actualmente en XAMPP.
- Fase 5 completada con descarga validada del ZIP completo y descarga individual de archivos internos.
- Streaming de archivos internos directamente desde el ZIP, sin extracción permanente ni rutas públicas.
- Conservación de nombres UTF-8 mediante `Content-Disposition` y tipos MIME seguros.
- Contador general incrementado únicamente para descargas completas válidas; las descargas individuales no lo modifican.
- Modelo temporal `DownloadModel` respaldado por sesión y preparado para migrarse a un contador global en MySQL.
- Favoritos compartidos entre catálogo y detalle mediante la misma acción protegida.
- Búsqueda ampliada a título, descripción, autores, tutor, tipo, tecnologías, palabras clave, PAO y año.
- Acción para limpiar conjuntamente la búsqueda, los filtros y el modo de favoritos.
- Fase 6 completada con visualizadores integrados para PDF, imágenes, texto plano y código fuente.
- Vista de código segura con contenido escapado, numeración de líneas y resaltado visual básico sin ejecutar archivos.
- Controles de ampliación para imágenes y acceso de descarga disponible desde todos los estados de vista previa.
- Estados informativos para archivos vacíos, demasiado grandes, incompatibles o con extensión y MIME inconsistentes.
- Visualización en línea restringida a PDF e imágenes validadas, con `nosniff`, CSP restrictiva y almacenamiento en caché deshabilitado.
- Fase 7 completada con vista previa segura de documentos DOCX dentro del explorador existente.
- Extracción controlada de títulos, párrafos, listas y tablas desde `word/document.xml`, sin representar HTML del documento.
- Archivos DOCX procesados temporalmente fuera del directorio público y eliminados al finalizar cada solicitud.
- Contenido externo, relaciones, macros, scripts y objetos incrustados excluidos de la vista previa.
- Estados diferenciados para documentos vacíos, dañados, demasiado grandes o sin estructura compatible.
- Fase final de pulido completada con mejoras de accesibilidad y pruebas generales de regresión.
- Desplegables navegables mediante flechas, Inicio, Fin y Escape, con roles y estados ARIA sincronizados.
- Tarjetas de proyecto activables mediante Enter o barra espaciadora sin interferir con el botón de favoritos.
- Restauración del foco al regresar desde una vista previa y estados de carga anunciables para tecnologías de asistencia.
- Contadores dinámicos anunciables, límites de zoom reflejados en los controles y compatibilidad con la preferencia de movimiento reducido.
- Verificación funcional de catálogo, CSRF, favoritos, detalle, ZIP, visualizadores, descargas, errores HTTP y codificación UTF-8.
- Módulo funcional de Material de apoyo separado del catálogo de proyectos mediante `SupportMaterialModel` y `SupportMaterialController`.
- Tarjetas del carrusel conectadas a detalles individuales de guías, formatos, instructivos, plantillas y reglamentos.
- Catálogo completo de materiales accesible desde el botón y la tarjeta `Ver más`, con búsqueda, categoría, contador y estado vacío.
- Detalle institucional con breadcrumbs, metadatos, descripción completa, palabras clave, archivo principal y archivos adicionales.
- Vistas previas seguras reutilizadas para PDF, DOCX y TXT, conservando el modal responsive y el visor PDF nativo.
- Descargas privadas validadas por material y archivo, con lista blanca de extensiones, protección de directorio y encabezados seguros.
- Paquete ZIP autorizado para materiales con múltiples archivos, sin generación dinámica durante la solicitud.
- Contador temporal por sesión incrementado únicamente al descargar el archivo principal o el paquete completo.
- Fixtures privados diferenciados por material y generador reproducible fuera del directorio público.

Pendiente:

- Migración de favoritos desde sesión hacia usuarios autenticados y base de datos MySQL.
- Migración de datos simulados y persistencia temporal hacia MySQL.
- Persistencia real del catálogo, archivos y descargas de Material de apoyo en MySQL.
- Vinculacion con archivos reales.
- Persistencia en base de datos.

---
# 13. Gestión de Usuarios

Estado:

🔴 Pendiente

Pendiente:

- Administración de usuarios.
- Roles.
- Docentes.
- Estudiantes.
- Administradores.
- Edición.
- Eliminación.
- Cambio de estado.

---

# 14. Perfil

Estado:

En desarrollo

Implementado:

- Opciones visuales dentro del menú de usuario.

Pendiente:

- Actualización de datos.
- Cambio de contraseña.
- Cambio de correo.
- Configuración personal.

---

# 15. Reportes

Estado:

🔴 Pendiente

Pendiente:

- Reportes académicos.
- Estadísticas.
- Exportación.
- Indicadores.

---

# 16. Configuración

Estado:

🔴 Pendiente

Pendiente:

- Parámetros del sistema.
- Configuración institucional.
- Gestión de catálogos.
- Configuración general.

---

# 17. Base de Datos

Estado:

🔴 Pendiente

Pendiente:

- Diseño final de tablas.
- Relaciones.
- Integración mediante PDO.
- Consultas.
- Persistencia de información.

---

# 18. Seguridad

Estado:

En desarrollo

Implementado:

- Escape de datos para la salida mediante helpers.

Pendiente:

- Protección mediante sesiones.
- Control de permisos.
- Validaciones del lado del servidor.
- Sanitización de entradas.
- Protección CSRF.
- Manejo seguro de archivos.

---

# 19. Observaciones Técnicas

Actualmente el proyecto cuenta con una base sólida para continuar el desarrollo.

La prioridad ya no consiste en modificar la arquitectura, sino en desarrollar progresivamente la lógica del negocio respetando la estructura existente.

Se recomienda mantener la organización actual del proyecto y continuar implementando funcionalidades de forma incremental.

Las mejoras importantes relacionadas con arquitectura o estructura deberán evaluarse antes de implementarse.

---

# 20. Próximo Objetivo

La evolución funcional y visual planificada para el Repositorio Institucional se encuentra completada hasta esta etapa.

El siguiente objetivo recomendado es iniciar la integración de infraestructura real:

- Diseñar las tablas y relaciones del repositorio en MySQL.
- Sustituir proyectos, tecnologías, favoritos y contadores simulados por consultas persistentes.
- Vincular favoritos con usuarios autenticados.
- Incorporar permisos y roles para consulta, publicación y descarga.
- Reemplazar los ZIP de prueba por archivos administrados por el sistema.

La autenticación real, las sesiones de usuario y el control de roles continúan siendo requisitos prioritarios para completar esta migración.

---

# 21. Registro de Actualización

Este documento deberá actualizarse cuando:

- Se implemente un nuevo módulo.
- Se complete una funcionalidad importante.
- Se modifique la arquitectura.
- Se incorporen nuevas características relevantes.
- Cambie el estado de desarrollo de algún módulo.

Su objetivo es reflejar el estado real del proyecto en todo momento.

---

# 22. Arquitectura y Despliegue

Estado:

Preparado para pruebas en cPanel

Implementado:

- Front Controller único con carga automática de clases MVC y servicios.
- Configuración local de aplicación y MySQL excluida del control de versiones.
- Protección Apache para directorios privados y desactivación del listado de carpetas.
- Modo de producción sin errores visibles ni autorecarga de desarrollo.
- Generador de paquete ZIP compatible con Linux y libre de secretos locales.
- Guía de despliegue, importación SQL, permisos, HTTPS y verificación posterior.

Pendiente:

- Crear la base MySQL definitiva de todos los módulos.
- Configurar dominio, HTTPS y credenciales reales en el proveedor seleccionado.
- Confirmar las extensiones PHP disponibles en la cuenta de cPanel.
- Ejecutar pruebas de aceptación en el servidor final.

---

# 23. Módulos Mis proyectos y Detalle del proyecto

Estado: Fase 0 completada y base funcional en desarrollo.

Implementado:

- Separación estructural entre catálogo, detalle y registro de un nuevo proyecto.
- Rutas `projects`, `project-detail` y `new-project`.
- Redirección automática al detalle cuando el usuario posee exactamente un proyecto.
- Estados diferenciados para cero, uno y varios proyectos.
- Contrato temporal centralizado en `ProjectModel`, identificado como simulado.
- Tarjetas contextuales para revisión, aprobación, defensa y publicación.
- Detalle por identificador con validación de pestañas y estado 404.
- Pestaña independiente de comentarios generales con relación opcional preparada.
- CSS y JavaScript aislados para catálogo y detalle.
- Ruta de Nuevo proyecto con estado pendiente, sin formulario ni guardado ficticio.

Pendiente:

- Persistencia real, autenticación, pertenencia y permisos por rol.
- Formularios backend para entregas, comentarios, observaciones y estados.
- Desarrollo completo de historial, participantes, calendario y documentos finales.
- Integración por identificador y pestaña con Dashboard, Notificaciones, Calendario y Repositorio.

## Avance del catálogo — Etapa 2

- Búsqueda por título, subtítulo, tipo, tutor, carrera, periodo, etiquetas y tecnologías.
- Filtros combinables por métrica, estado, tipo y periodo académico.
- Orden por actividad reciente, título y porcentaje de progreso.
- Descripción accesible y dinámica de los filtros activos.
- Tarjetas con participantes limitados, etiquetas relevantes e información adaptada a cada etapa.
- Estado vacío específico para búsquedas sin resultados y acción única para limpiar filtros.
- Ajustes responsive y estados de foco visibles para controles interactivos.

## Avance del detalle — Etapa 3

- Resumen ejecutivo reorganizado en estructura principal y columna contextual.
- Etapas derivadas del tipo y estado real del contrato temporal, sin porcentajes arbitrarios.
- Última entrega y observaciones prioritarias con acceso a sus pestañas completas.
- Actividad reciente con enlace al historial del expediente.
- Siguiente acción adaptada al estado actual y navegación al área correspondiente.
- Participantes, fechas clave e información académica visibles sin saturar la cabecera.
- Estados vacíos orientativos para proyectos sin entregas, observaciones o actividad.
- Diseño responsive y modo oscuro mediante estilos aislados del módulo.

## Avance de espacios de trabajo — Etapa 4A

- Entregas presentadas como versiones inmutables con etapa, autor, archivo, fecha, estado y comentario.
- Conservación visible de versiones anteriores dentro de la trazabilidad temporal.
- Observaciones con autor, rol, entrega, ubicación, categoría, estado y respuestas relacionadas.
- Comentarios generales separados de las observaciones formales.
- Relación opcional de comentarios con entrega, archivo u observación preparada en la interfaz.
- Estados vacíos diferenciados para proyectos sin entregas, observaciones o comentarios.
- Acciones de escritura deshabilitadas y explicadas hasta contar con persistencia, CSRF y permisos reales.

## Avance de espacios de trabajo — Etapa 4B

- Historial cronológico de solo lectura con usuario, rol, acción, detalle y fecha.
- Participantes agrupados en Estudiantes, Tutoría y Tribunal, con datos académicos de asignación.
- Calendario del proyecto conectado a la fuente global mediante `projectId`.
- Enlace al Calendario global con filtro `project_id` aplicado realmente por JavaScript.
- Preservación de `projectId` al normalizar y guardar eventos del calendario.
- Documentos finales con estados de publicación y acceso al Repositorio cuando corresponde.
- Estados vacíos reales para eventos, tribunal y documentos aún no disponibles.

## Integraciones del módulo — Etapa 5

- Dashboard enlazado con Resumen, Entregas, Observaciones, Historial, Calendario y Notificaciones.
- Notificaciones temporales y semilla SQL dirigidas a `project-detail&id={id}&tab={tab}`.
- Identificadores de proyecto alineados con el destino de cada notificación.
- Calendario global dirigido a la pestaña correspondiente según entrega, revisión, fecha límite o reunión.
- Proyecto publicado enlazado con su entrada real del Repositorio.
- Repositorio con retorno contextual a Documentos finales del seguimiento académico.
- Eliminación de accesos genéricos y fragmentos antiguos del flujo integrado.

## Preparación de infraestructura — Etapa 6

- Migración MariaDB con 14 tablas para usuarios, roles, proyectos, participantes, etapas, entregas, archivos, observaciones, respuestas, comentarios, eventos y auditoría.
- Relaciones mediante 29 claves foráneas e índices para consultas frecuentes.
- Matriz backend inicial de permisos para Estudiante, Docente y Administrador; tutoría y tribunal son responsabilidades internas del proyecto.
- Identidad del módulo centralizada y compatible con sesión real o modo demostración.
- Servicio de auditoría preparado para registrar estados anteriores y nuevos dentro de transacciones.
- Servicio de archivos privados con validación de extensión, MIME, tamaño, nombre seguro y prevención de recorrido de rutas.
- Directorio `storage/private` protegido contra acceso web y listado de archivos.

Importante:

- El contrato temporal continúa activo hasta importar la migración y completar el login real.
- Las escrituras siguen deshabilitadas para evitar persistencia parcial o engañosa.
- La activación definitiva requiere repositorios PDO, sesiones autenticadas y pruebas de permisos por rol.

## Administrador — Fase 2

Estado: completada.

- Dashboard exclusivo para el rol Administrador.
- Indicadores reales de usuarios activos, bloqueados, totales y registrados durante los últimos 30 días.
- Distribución de proyectos por estado, excluyendo la papelera.
- Actividad reciente consultada desde la auditoría de proyectos.
- Alertas de cuentas bloqueadas, contraseñas temporales vencidas, observaciones pendientes y proyectos eliminados.
- Próximas fechas combinadas desde periodos académicos y eventos de proyectos.
- Acciones rápidas hacia Usuarios, Proyectos y Académico.
- Estados vacíos honestos y manejo visible de fallos de consulta, sin datos simulados.

## Administrador — Fase 3

Estado: completada.

- Listado real de Estudiantes, Docentes y Administradores desde MariaDB.
- Búsqueda por nombre, correo o identificación y filtros por rol y estado.
- Creación y edición transaccional de cuentas y perfiles académicos.
- Asignación de carrera, periodo y semestre para estudiantes.
- Configuración de título y disponibilidad como tutor para docentes.
- Activación y bloqueo de accesos con cierre de sesiones existentes.
- Restablecimiento a contraseña temporal `Istel2026+`, cambio obligatorio y vencimiento a siete días.
- Protección CSRF, validación de correos duplicados y protección de la cuenta administrativa propia.
- Auditoría administrativa con actor, acción, entidad, fecha, IP y agente de usuario.
- Actividad de usuarios integrada al Dashboard administrativo.

## Administrador — Fase 4

Estado: completada.

- Importación masiva de Estudiantes y Docentes desde archivos CSV/TXT o texto pegado.
- Lectura de columnas separadas por coma, punto y coma o tabulación.
- Vista previa obligatoria con resultado y error específico por fila.
- Validación de correos e identificaciones duplicadas tanto en la lista como en MariaDB.
- Límite de 500 usuarios y 1 MB por operación.
- Configuración común de carrera, periodo y semestre para grupos de estudiantes.
- Opción para incorporar docentes a los selectores de tutores.
- Creación atómica dentro de una transacción: una fila inválida impide toda la importación.
- Contraseña temporal `Istel2026+`, cambio obligatorio y vencimiento en siete días.
- Registro resumido de la importación en la auditoría administrativa.

## Administrador — Fase 5

Estado: completada.

- Catálogo administrativo de proyectos conectado exclusivamente a MariaDB.
- Indicadores y filtros por texto, estado, tipo y periodo académico.
- Creación real de expedientes con código único, tipo, carrera, periodo, tutor y estado.
- Edición transaccional de los datos principales del proyecto.
- Tutores disponibles obtenidos de los perfiles docentes habilitados.
- Envío reversible a Papelera con motivo obligatorio, sin eliminar archivos ni participantes.
- Registro de creación, modificación y eliminación en la auditoría del proyecto.
- Experiencia administrativa separada de los datos temporales de Estudiante y Docente.

## Administrador — Fase 6

Estado: completada.

- Gestión real de periodos académicos, carreras, tipos de proyecto, líneas de investigación y asignaturas.
- Asignación de carrera, periodo, semestre y docente responsable a cada asignatura.
- Avance semestral confirmado por el Administrador, ejecutado dentro de una transacción.
- Cierre del periodo anterior, activación del siguiente y conservación del historial de matrículas.
- Estudiantes de décimo semestre marcados como completados sin generar un nivel inexistente.
- Trazabilidad de cambios académicos en la auditoría administrativa.

## Administrador — Fase 7

Estado: completada.

- Panel de publicación conectado a proyectos y archivos reales de MariaDB.
- Clasificación de expedientes elegibles, publicados e incompletos.
- Publicación restringida a proyectos aprobados o, en el caso de tesis, aprobados por el Tribunal, con al menos un documento.
- Retiro reversible de publicaciones sin eliminar el expediente ni sus archivos.
- Fecha de publicación y estado institucional actualizados dentro de una transacción.
- Registro de publicación y retiro en la auditoría del proyecto.

## Administrador — Fase 8

Estado: completada.

- Centro administrativo para notificaciones dirigidas con datos reales de MariaDB.
- Destinatarios por usuario, participantes de un proyecto, rol o toda la plataforma.
- Alcance de proyecto calculado con creador, tutor y participantes activos, sin duplicados.
- Comunicados globales restringidos al tipo Sistema y protegidos por doble confirmación.
- Entrega transaccional: todos los destinatarios reciben el aviso o no se registra ninguno.
- Emisor, alcance, cantidad de destinatarios y fecha conservados en auditoría.
- Historial resumido de envíos sin exponer el contenido privado de las bandejas personales.

## Administrador — Fase 9

Estado: completada.

- Papelera unificada de usuarios y proyectos con conservación durante 60 días.
- Revocación inmediata del acceso al eliminar una cuenta, sin eliminar sus proyectos.
- Motivo, fecha y Administrador responsable registrados en MariaDB y auditoría.
- Restauración de cuentas y expedientes durante el plazo disponible.
- Contador individual de días restantes y detección de elementos vencidos.
- Purga manual protegida por confirmación y limitada a registros con más de 60 días.
- Usuarios vencidos anonimizados como registros históricos para conservar relaciones académicas.
- Proyectos vencidos eliminados definitivamente mediante sus relaciones normalizadas.

## Administrador — Fase 10

Estado: completada.

- Reportes administrativos por rango de fechas con indicadores reales de MariaDB.
- Conteos de usuarios, proyectos, entregas y acciones auditadas.
- Distribución actual de usuarios por rol y proyectos por estado.
- Cronología unificada de auditoría administrativa y auditoría de proyectos.
- Exportaciones CSV de usuarios, proyectos y eventos de auditoría.
- Archivos UTF-8, delimitados para hojas de cálculo y protegidos contra inyección de fórmulas.
- Consultas y exportaciones de solo lectura, sin modificar información institucional.

## Administrador — Fase 11

Estado: completada.

- Configuración institucional persistida en la tabla `system_settings` de MariaDB.
- Nombre institucional editable con validación de longitud.
- Límites individuales y totales de archivos configurables dentro de rangos seguros.
- Formatos habilitables restringidos a PDF, DOCX y ZIP con validación MIME por extensión.
- Servicio de archivos privados conectado a la configuración, con valores seguros de respaldo.
- Reglas críticas de Papelera, contraseña temporal y almacenamiento visibles pero protegidas.
- Cambios de configuración registrados en la auditoría administrativa.

## Administrador — Fase 12

Estado: completada.

- Perfil administrativo conectado a la identidad real de MariaDB.
- Consulta de nombre, correo, rol, fecha de creación, último acceso y cambio de contraseña.
- Actualización de nombre y correo protegida por CSRF y contraseña actual.
- Validación de correo duplicado y longitudes antes de escribir.
- Refresco inmediato de la identidad en cabecera y menú de usuario.
- Invalidación de las demás sesiones mediante incremento de versión.
- Modificaciones del perfil registradas en auditoría.
- Acceso independiente al flujo seguro de cambio de contraseña.

## Administrador — Fase 13

Estado: completada.

- Auditoría final de rutas, sesión, rol Administrador, CSRF y operaciones sensibles.
- Diez pantallas administrativas verificadas por HTTP con sesión real y respuestas `200` sin errores fatales.
- Acceso anónimo a rutas administrativas verificado con redirección al Login.
- Rechazo CSRF normalizado al estado HTTP estándar `403` en respuestas JSON.
- Cookies de sesión reforzadas con modo estricto, `HttpOnly`, `SameSite=Lax` y `Secure` bajo HTTPS.
- Corrección de sintaxis CSS en Configuración y comprobación estructural de todos los estilos administrativos.
- Revisión responsive de código para los doce archivos CSS del rol Administrador.
- Regresión completa de 82 archivos PHP y 20 archivos JavaScript sin errores de sintaxis.
- Cuenta de pruebas conservada y contador de contraseña temporal restaurado al segundo aviso.

Resultado: experiencia del rol Administrador completada en sus trece fases y preparada para pruebas de aceptación manual.

## Próxima sesión de trabajo

- Ejecutar pruebas manuales completas del rol Administrador en escritorio y móvil.
- Revisar navegación, claridad visual, formularios, mensajes y estados vacíos.
- Registrar y corregir los cambios detectados durante las pruebas de aceptación.
- Crear proyectos de prueba y verificar asignaciones, estados, auditoría y notificaciones.
- Subir documentos controlados en PDF, DOCX y ZIP para comprobar límites, almacenamiento privado y visualización.
- Verificar publicación, restauración y reportes usando únicamente datos identificados como pruebas.
- Mantener el trabajo del rol Administrador en la rama `Administrador` hasta aceptar la versión.
