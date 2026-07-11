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

### Interfaz

- Se unificó el diseño de las tarjetas del repositorio con el Dashboard para los modos claro y oscuro.
- Se incorporaron bordes de color, sombras y estados interactivos para las tarjetas.
- Se agregó un skeleton loader durante la precarga del repositorio.
- Se ajustó la distribución del catálogo a cuatro columnas en escritorio, dos en tablet y una en móvil.
- Se mejoró el comportamiento responsive de insignias, contadores, filtros y controles del carrusel.
- Se limitó el crecimiento de la interfaz en pantallas superiores a 1900 px mediante márgenes exteriores, conservando las proporciones internas.

### Mejoras Técnicas

- Se corrigió la activación independiente de los filtros desplegables de documentos de apoyo y proyectos.
- Se conservaron las palabras clave de forma no visible para mantener su disponibilidad en las búsquedas.
- Se preparó el carrusel para manejar hasta once documentos visibles y una tarjeta informativa adicional.

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

