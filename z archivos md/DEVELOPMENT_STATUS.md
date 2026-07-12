# DEVELOPMENT_STATUS.md

# Estado Actual del Desarrollo

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

En desarrollo

Implementado:

- Diseño visual.

Pendiente:

- Eventos.
- Recordatorios.
- Integración con proyectos.
- Persistencia.

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
- Contenedor general centrado en pantallas superiores a 1900 px sin alterar las proporciones internas del contenido.
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
