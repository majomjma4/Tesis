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

Pendiente:

- Descarga de documentos.
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

El siguiente paso recomendado para el desarrollo es implementar la infraestructura común del sistema:

- Conexión a la base de datos.
- Gestión de usuarios.
- Inicio de sesión real.
- Manejo de sesiones.
- Control de roles.

Una vez completada esta base, podrá iniciarse el desarrollo de los módulos de gestión de proyectos, gestión documental y revisión académica.

---

# 21. Registro de Actualización

Este documento deberá actualizarse cuando:

- Se implemente un nuevo módulo.
- Se complete una funcionalidad importante.
- Se modifique la arquitectura.
- Se incorporen nuevas características relevantes.
- Cambie el estado de desarrollo de algún módulo.

Su objetivo es reflejar el estado real del proyecto en todo momento.



