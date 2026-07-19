# CHANGELOG.md

# Registro de Cambios del Proyecto

Versión del documento: 1.0

---

## Descripción

Este documento registra los hitos más importantes del desarrollo de la plataforma.

Su propósito es mantener un historial cronológico de las decisiones técnicas, funcionalidades implementadas y cambios relevantes realizados durante el proyecto.

No se incluyen modificaciones menores, correcciones puntuales, cambios estéticos pequeños o ajustes internos que no representen un avance significativo.

Este documento deberá actualizarse únicamente cuando se complete una funcionalidad importante o se produzca un cambio relevante en el proyecto.

---

# [En desarrollo] - 18/07/2026

## Administrador — Fase 1

- Se activó la autenticación obligatoria en el entorno local y la validación de la cuenta contra MariaDB en cada solicitud.
- Se incorporó navegación exclusiva del Administrador, identidad real en la cabecera y accesos base a sus módulos.
- Se añadió control de versión de sesión para bloquear cuentas o cerrar sesiones anteriores después de cambiar la contraseña.
- Las contraseñas temporales muestran dos avisos y obligan el cambio en el tercer acceso o al vencer siete días.
- Se implementó cambio seguro de contraseña con CSRF, reglas de complejidad, `password_hash()` e invalidación de sesiones.
- Se añadió una respuesta 403 y protección de rutas administrativas en el servidor.

## Nuevo proyecto y simplificación del seguimiento

- Se simplificaron “Mis proyectos” y el detalle para priorizar una acción principal y divulgación progresiva.
- El detalle quedó organizado en Resumen, Documentos, Revisión, Actividad e Información.
- Se incorporó navegación estable, conservación del desplazamiento, skeleton global y vistas seguras para PDF, DOCX y ZIP.
- Se implementó Nuevo proyecto en cinco pasos con campos condicionales, equipo por semestre, etiquetas, archivos y confirmación.
- El borrador se valida mediante JavaScript y PHP sin simular una creación mientras la persistencia está deshabilitada.
- Se añadió guardado temporal versionado en `sessionStorage`, excluyendo archivos y tokens CSRF.

## Preparación para MariaDB y autenticación

- Se adoptó MariaDB con InnoDB, `utf8mb4`, PDO, consultas preparadas y archivos privados fuera de la base.
- Se añadieron carreras, periodos, perfiles, matrículas, líneas de investigación, asignaturas, favoritos, descargas y secuencias de códigos.
- Se alinearon los tipos `thesis`, `pis`, `practice` y `community` entre interfaz y esquema.
- Se prepararon sesiones autenticadas, CSRF de login, múltiples roles y protección de rutas activable.
- Se incorporaron repositorios PDO, catálogos institucionales y generación transaccional de códigos únicos.
- La base permanece deshabilitada hasta importar las migraciones, crear el primer administrador y probar la integración.

## Separación estructural del módulo de proyectos

- Se separó el catálogo “Mis proyectos” del seguimiento detallado de un expediente.
- Se incorporaron rutas independientes para catálogo, detalle y Nuevo proyecto.
- Se añadió el comportamiento específico para cero, uno y varios proyectos.
- Se implementaron tarjetas contextuales según la etapa académica.
- Se preparó el detalle con navegación por pestañas, incluyendo comentarios generales.
- Se aislaron los estilos y scripts para evitar ampliar los selectores globales.
- Se eliminó del flujo nuevo cualquier confirmación de guardado simulado.
- Se completó el catálogo con búsqueda amplia, filtros combinables, ordenamiento y descripción de filtros activos.
- Las tarjetas incorporan participantes y etiquetas limitadas sin sustituir el detalle del expediente.
- Se completó el Resumen del detalle con etapas derivadas, entrega reciente, observaciones, actividad, siguiente acción, participantes y fechas clave.
- Se desarrollaron las pestañas Entregas, Observaciones y Comentarios como espacios de consulta preparados para persistencia.
- Se completaron Historial, Participantes, Calendario del proyecto y Documentos finales, incluyendo el filtrado real del Calendario global por proyecto.
- Se integraron Dashboard, Notificaciones, Calendario y Repositorio con las rutas y pestañas del nuevo detalle de proyecto.
- Se preparó la infraestructura de persistencia de proyectos con esquema MariaDB, permisos, auditoría y almacenamiento privado.

---

# [v0.1.0] - 02/06/2026

## Inicio del Proyecto

### Desarrollo

- Se inició el desarrollo del proyecto.
- Se realizaron las primeras pruebas relacionadas con la estructura general del sistema.
- Se comenzó el diseño de la identidad visual de la plataforma.

### Diseño

- Se definió el estilo institucional de la interfaz.
- Se establecieron los primeros componentes visuales.

---

# [v0.2.0] - 17/06/2026

## Desarrollo de la Interfaz

### Dashboard

- Se desarrolló la primera versión del Dashboard.
- Se implementó la barra lateral de navegación.
- Se implementó la barra superior.
- Se incorporaron tarjetas informativas.
- Se añadieron paneles complementarios para mejorar la experiencia del usuario.

### Interfaz

- Se implementó un diseño responsive.
- Se incorporó un modo oscuro visual.
- Se añadieron animaciones e interacciones básicas.
- Se implementó un menú lateral adaptable.
- Se incorporó un menú de usuario mediante avatar.
- Se añadieron notificaciones visuales.
- Se implementó un cierre de sesión simulado para pruebas de navegación.

---

# [v0.3.0] - 02/07/2026

## Inicio del Desarrollo Funcional

### Autenticación

- Se desarrolló la primera versión visual del módulo de inicio de sesión.
- Se incorporó validación básica del formulario.
- Se implementó la navegación inicial hacia el Dashboard.

### Optimización

- Se optimizaron recursos gráficos utilizando imágenes en formato WebP.
- Se reorganizaron los recursos públicos del proyecto.

---

# [v0.4.0] - 03/07/2026

## Migración a Arquitectura MVC

### Arquitectura

- Se migró el proyecto desde una estructura basada en páginas HTML independientes hacia una aplicación desarrollada en PHP utilizando el patrón Modelo - Vista - Controlador (MVC).
- Se implementó un Front Controller como punto único de entrada.
- Se organizaron controladores, modelos, vistas, layouts, configuración, helpers y recursos públicos.
- Se implementó un sistema básico de rutas.
- Se separó la lógica de negocio, la presentación y el acceso a los datos.

### Desarrollo

- Se implementó la estructura base del Dashboard dentro de la arquitectura MVC.
- Se implementó la estructura base del módulo de autenticación.
- Se incorporaron layouts reutilizables para diferentes tipos de vistas.
- Se implementó un controlador de desarrollo para facilitar la auto recarga durante la programación.

### Mejoras Técnicas

- Se centralizó la generación de rutas internas.
- Se centralizó la gestión de recursos públicos.
- Se incorporaron funciones helper reutilizables.
- Se implementó escape de salida HTML como medida inicial de seguridad.

### Decisiones de Arquitectura

- Se decidió continuar el desarrollo directamente sobre la aplicación PHP.
- Se descartó continuar utilizando prototipos independientes en HTML.
- Se adoptó oficialmente el patrón MVC como arquitectura del proyecto.
- Se estableció una estructura preparada para la futura integración con MySQL.

### Documentación

- Se creó el documento `PROJECT_CONTEXT.md` como guía oficial del proyecto.
- Se creó el documento `DEVELOPMENT_STATUS.md` para registrar el estado actual del desarrollo.
- Se creó el documento `CHANGELOG.md` para mantener el historial del proyecto.

---

# [v0.5.0] - 04/07/2026

## Rediseño del Dashboard de Seguimiento Académico

### Dashboard

- Se rediseñó la pantalla principal para enfocarla en el seguimiento del informe académico actual.
- Se reorganizó la información principal del proyecto, documento vigente, semestre, tutor, última revisión y observaciones pendientes.
- Se incorporaron accesos rápidos para descargar el informe, consultar historial y revisar observaciones.
- Se añadió un resumen desplegable de integrantes del proyecto dentro de la tarjeta principal.
- Se reorganizaron las secciones de notificaciones, observaciones recientes y recordatorios para mejorar la lectura del estado académico.

### Interfaz

- Se simplificaron acciones duplicadas relacionadas con el envío de correcciones y subida de nuevas versiones.
- Se eliminó la sección de siguiente acción para evitar redundancia con el flujo principal del informe.
- Se ajustó la distribución visual del dashboard para mejorar el aprovechamiento del espacio disponible.
- Se mejoró la responsividad de componentes principales, botones, tarjetas y desplegables.
- Se movió la acción de nuevo proyecto a la barra superior como acceso global.

### Mejoras Técnicas

- Se implementó persistencia del modo claro y oscuro mediante almacenamiento local del navegador.
- Se añadieron datos simulados de integrantes del proyecto desde el modelo del Dashboard para preparar la futura integración con datos reales.
- Se ajustó la interacción del desplegable de integrantes para cierre por clic externo y tecla Escape.

---

# [v0.6.0] - 10/07/2026

## Modulo de Repositorio Institucional

### Repositorio

- Se desarrollo la primera version funcional del repositorio institucional para la carrera de Desarrollo de Software.
- Se incorporo un bloque de material complementario con guias y documentos de apoyo.
- Se agrego un catalogo institucional de proyectos finalizados.
- Se implementaron filtros por semestre, docente, categoria, tipo y PAO.
- Se anadio un buscador independiente para material complementario y otro para catalogo.
- Se habilito resaltado visual de coincidencias en la busqueda.
- Se ajusto la estructura visual para separar claramente filtros, encabezados y tarjetas.

### Interfaz

- Se mejoro la responsividad del repositorio para pantallas pequenas.
- Se reorganizo el comportamiento de las tarjetas para escritorio, tablet y movil.
- Se optimizo el diseno de los selectores personalizados y sus menus desplegables.

### Mejoras Tecnicas

- Se incorporaron datos simulados para proyectos y documentos de apoyo desde el modelo del repositorio.
- Se separo la logica de filtrado del catalogo y del material complementario.
- Se reforzo la interaccion de los filtros desplegables con cierre por clic externo y tecla Escape.

---

# [v0.6.1] - 11/07/2026

## Mejoras del Repositorio Institucional

### Repositorio

- Se renombró la vista principal del módulo a `app/views/repository/repositorio.php` y se actualizó su controlador.
- Se convirtió la sección de documentos de apoyo en un carrusel con cuatro tarjetas visibles.
- Se implementó el desplazamiento de una tarjeta por cada interacción con las flechas.
- Se evitó la navegación circular y se configuró la aparición de cada flecha únicamente cuando existe contenido disponible en su dirección.
- Se reservó la posición número doce para una tarjeta informativa cuando existen más de once documentos de apoyo.
- Se integró el carrusel con el buscador y el filtro de categoría.
- Se completó la primera fase visual de evolución del catálogo institucional.
- Se incorporó un contador descriptivo de proyectos y una acción para limpiar filtros.
- Se prepararon las tarjetas para la futura navegación hacia el detalle del proyecto.
- Se completó la segunda fase con favoritos funcionales y aislados mediante persistencia temporal por sesión.
- Se completó la tercera fase con navegación real desde las tarjetas hacia una pantalla de detalle dedicada.

### Interfaz

- Se unificó el diseño de las tarjetas del repositorio con el Dashboard para los modos claro y oscuro.
- Se incorporaron bordes de color, sombras y estados interactivos para las tarjetas.
- Se agregó un skeleton loader durante la precarga del repositorio.
- Se ajustó la distribución del catálogo a cuatro columnas en escritorio, dos en tablet y una en móvil.
- Se mejoró el comportamiento responsive de insignias, contadores, filtros y controles del carrusel.
- Se limitó el crecimiento de la interfaz en pantallas superiores a 1900 px mediante márgenes exteriores, conservando las proporciones internas.
- Se añadieron colores institucionales centralizados para Tesis, Perfil de tesis, Prácticas preprofesionales, Proyecto PIS y Vinculación.
- Se agregaron tecnologías, indicador de etiquetas adicionales, número de descargas y la acción visual `Explorar proyecto`.
- Se incorporaron corazones de favorito con estado visual y mensajes discretos de confirmación.
- Se agregó un estado vacío específico para usuarios sin favoritos y una acción para volver al catálogo completo.
- Se incorporó una vista 30/70 con encabezado, breadcrumbs, información académica, autores, tutor, resumen, tecnologías y palabras clave.
- Se completó la cuarta fase sustituyendo el explorador simulado por lectura real de archivos ZIP privados.
- Se agregó navegación por carpetas, breadcrumbs internos, tamaños legibles, iconos y ordenamiento de carpetas antes que archivos.
- Se incorporaron estados para carpetas y ZIP vacíos, archivos inexistentes, ZIP dañados y rutas inválidas.
- Se completó la quinta fase con descargas completas e individuales desde la pantalla de detalle.
- Se agregaron acciones de descarga por archivo dentro del explorador.
- Se completó la sexta fase con vistas previas integradas para PDF, imágenes, texto y código fuente.
- Se incorporó lectura de texto, formato legible para JSON y visualización escapada de código con números de línea.
- Se añadieron controles de zoom para imágenes y estados diferenciados para archivos vacíos, grandes o incompatibles.
- Se completó la séptima fase con lectura integrada y segura de documentos DOCX.
- La vista DOCX conserva títulos, párrafos, listas y tablas básicas dentro del diseño actual del repositorio.
- Se completó la fase final de pulido con mejoras de teclado, foco, estados anunciables y movimiento reducido.
- La pantalla de detalle mantiene modo oscuro, responsive y el layout principal de la plataforma.

### Mejoras Técnicas

- Se corrigió la activación independiente de los filtros desplegables de documentos de apoyo y proyectos.
- Se conservaron las palabras clave de forma no visible para mantener su disponibilidad en las búsquedas.
- Se preparó el carrusel para manejar hasta once documentos visibles y una tarjeta informativa adicional.
- Se amplió la búsqueda simulada para incluir autores, tutor, tipo, tecnologías, palabras clave, PAO y año.
- Se corrigieron los identificadores internos del filtro de docentes para que coincidan con los proyectos publicados.
- Se creó `FavoriteModel` como almacenamiento temporal por sesión, desacoplado de la vista y reemplazable por MySQL.
- Se agregó una acción POST con protección CSRF, validación de proyectos publicados y respuestas JSON.
- Se verificó el aislamiento entre dos sesiones, la persistencia tras recarga y los estados HTTP 403, 404 y 405.
- La vinculación definitiva con usuarios autenticados y base de datos permanece pendiente.
- Se agregó la ruta validada `repository-detail` con respuesta 404 para proyectos inexistentes.
- Se reutilizó la misma acción de favoritos en catálogo y detalle, evitando lógica duplicada.
- Se creó `ArchiveService` para aislar la lectura ZIP, normalizar rutas internas e impedir recorridos fuera del archivo.
- Se añadió una ruta JSON validada para consultar directorios sin exponer rutas físicas del servidor.
- Se incorporó un generador de fixtures ZIP privados para pruebas reproducibles.
- Debido a que `ZipArchive` está deshabilitado en el PHP actual de XAMPP, el servicio utiliza `PharData` como lector alternativo y conserva compatibilidad con `ZipArchive` para el entorno final.
- Se implementó streaming seguro de archivos internos sin extracción permanente ni exposición de rutas físicas.
- Se añadieron encabezados de descarga con nombre original UTF-8, MIME controlado y `nosniff`.
- Se creó `DownloadModel` para simular incrementos por sesión hasta disponer de persistencia global en MySQL.
- El contador aumenta solo después de validar el ZIP completo y no cambia por descargas individuales, navegación o fallos.
- Se probaron nombres con espacios, tildes, paréntesis y guiones, además de rutas inválidas y archivos inexistentes.
- Se creó `FilePreviewService` para clasificar formatos, validar MIME y aplicar límites antes de generar una vista previa.
- Los PDF y las imágenes se transmiten en línea desde el ZIP privado sin extracción permanente; texto y código se entregan como datos seguros.
- Se bloquearon SVG y formatos ejecutables, y se impidió que archivos con extensiones falsas se representen como contenido confiable.
- Se agregaron encabezados `nosniff`, CSP restrictiva, disposición `inline` controlada y caché deshabilitada para el contenido visualizable.
- Se verificaron PDF, PNG, JSON, PHP, TXT, archivos vacíos, archivos grandes, formatos no compatibles, rutas transversales y métodos HTTP no permitidos.
- Se creó `DocxPreviewService` para aislar la validación, apertura temporal y lectura estructurada de documentos Word.
- La extracción se limita a `word/document.xml`; no se cargan relaciones externas, macros, scripts, objetos incrustados ni HTML activo.
- El contenido DOCX se entrega como bloques de datos y se construye mediante nodos de texto seguros en el navegador.
- Los archivos temporales se eliminan siempre al finalizar y nunca se almacenan dentro de `public`.
- Se probaron documentos DOCX válidos y dañados, además del bloqueo de su transmisión directa mediante la ruta de contenido en línea.
- Se incorporó navegación completa por teclado en los filtros personalizados con atributos ARIA sincronizados.
- Las tarjetas pueden abrirse con Enter o barra espaciadora y las vistas previas restauran el foco al archivo de origen.
- Se añadieron estados `aria-busy`, regiones dinámicas anunciables y límites accesibles en los controles de zoom.
- Se agregó compatibilidad con `prefers-reduced-motion` sin modificar el diseño o posicionamiento existente.
- Se ejecutaron pruebas de regresión sobre catálogo, CSRF, favoritos, exploración ZIP, visualizadores, descargas, respuestas 404 y UTF-8.
- Se convirtió Material de apoyo en un módulo MVC funcional y diferenciado del catálogo de proyectos.
- Se conectaron las tarjetas y acciones `Ver más` con un catálogo completo y pantallas de detalle individuales.
- Se incorporaron archivos privados distintos para PDF, DOCX y TXT, además de un paquete ZIP autorizado con múltiples recursos.
- Se reutilizaron los servicios seguros de vista previa para PDF, DOCX y texto sin exponer rutas físicas.
- Se implementaron descargas validadas del archivo principal, adicionales y paquete completo mediante rutas controladas.
- Se agregó un contador temporal por sesión que solo cambia en descargas principales o completas válidas.
- Se añadieron búsqueda, filtro por categoría, contador, estados vacíos, modo oscuro y adaptación responsive al catálogo de materiales.

---

# [v0.7.0] - 17/07/2026

## Calendario Académico Funcional

### Calendario

- Se convirtió la pantalla de Calendario en un módulo MVC funcional con persistencia temporal mediante JSON.
- Se implementó la creación, edición, eliminación, finalización, reapertura y consulta detallada de eventos.
- Se incorporaron vistas de mes, semana y lista dentro del mismo espacio de navegación.
- Se habilitó la reprogramación de eventos mediante arrastre y la creación rápida con doble clic sobre fechas vacías.
- Se añadieron eventos vencidos, prioridades, filtros, búsqueda, ordenamiento y estados vacíos contextuales.
- Se incorporó la opción de deshacer eliminaciones durante unos segundos.
- Se conectaron los recordatorios con apartados relacionados de los proyectos mediante acciones contextuales.

### Experiencia de Usuario

- Se añadieron indicadores interactivos para eventos mensuales, próximos siete días y progreso del mes.
- El progreso mensual ahora muestra porcentaje, cantidad completada y una barra visual.
- Se incorporaron una agenda diaria y una sección de próximos eventos.
- Se reemplazaron confirmaciones y selectores básicos por diálogos y desplegables visuales propios.
- Se conserva la vista seleccionada después de recargar la página.
- Se añadió navegación táctil horizontal entre periodos en dispositivos móviles.
- Se mejoró la adaptación responsive de controles, tarjetas, acciones y barra superior.

### Mejoras Técnicas

- Se creó la ruta JSON `calendar-events` para las operaciones del calendario.
- Se centralizó la normalización y persistencia temporal de eventos en `CalendarModel`.
- Se mantuvo la separación entre modelo, controlador, vista y comportamiento JavaScript.
- La migración de eventos hacia MySQL y su asociación definitiva con usuarios y proyectos permanece pendiente.

---

# [v0.7.1] - 17/07/2026

## Organización MVC y Preparación para cPanel

### Arquitectura

- Se incorporó carga automática de clases para núcleo, controladores, modelos y servicios.
- Se simplificó el Front Controller eliminando la carga manual de cada clase.
- Se retiraron accesos heredados que duplicaban rutas de la aplicación MVC.
- Se corrigió la capitalización de recursos para servidores Linux.

### Producción y seguridad

- Se añadieron configuraciones locales ignoradas por Git para aplicación y base de datos.
- Se protegieron configuración, almacenamiento, SQL, scripts y documentación frente al acceso web directo.
- Se deshabilita la exposición de errores y la autorecarga cuando el entorno está configurado como producción.
- Se añadieron ejemplos de configuración PHP segura para cPanel.

### Despliegue

- Se incorporó una guía completa de instalación en cPanel.
- Se añadió un generador reproducible del paquete ZIP para producción.
- El paquete excluye Git, herramientas internas y credenciales locales, y utiliza rutas compatibles con Linux.

---

Las siguientes versiones del CHANGELOG se actualizarán únicamente cuando se complete una funcionalidad importante del sistema.

Ejemplos:

- Implementación del sistema de autenticación.
- Gestión de usuarios.
- Control de roles.
- Gestión de proyectos.
- Gestión documental.
- Historial de versiones.
- Revisión documental.
- Repositorio institucional.
- Reportes.
- Configuración del sistema.

Cada nueva versión deberá registrar únicamente los cambios relevantes realizados desde la versión anterior.

## Administrador — Dashboard conectado a MariaDB

- Se incorporó un inicio administrativo diferenciado del dashboard estudiantil.
- Los indicadores de usuarios, proyectos, alertas, actividad y fechas se obtienen mediante PDO desde MariaDB.
- Se añadieron accesos rápidos y estados vacíos que reflejan la información realmente disponible.
- Se mantuvo una jerarquía visual simplificada y adaptable a escritorio, tableta y móvil.

## Administrador — Gestión de usuarios

- Se sustituyó la pantalla pendiente por una gestión funcional de cuentas conectada a MariaDB.
- Se incorporaron alta, edición, filtros, activación, bloqueo y restablecimiento seguro de contraseñas.
- Los perfiles de estudiantes y docentes se guardan en sus tablas normalizadas dentro de transacciones.
- Se agregó `admin_audit_log` y su migración reproducible para conservar trazabilidad administrativa.

## Administrador — Importación masiva

- Se añadió un asistente de tres pasos para configurar, revisar y crear usuarios en lote.
- Admite CSV, TXT y contenido pegado con validación previa por fila.
- Las cuentas se crean de manera transaccional en las tablas normalizadas de usuarios, roles y perfiles.
- No se realizan escrituras durante la vista previa ni importaciones parciales cuando existen errores.
