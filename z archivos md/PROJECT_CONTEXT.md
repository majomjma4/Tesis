# PROJECT_CONTEXT.md

# Plataforma Web Institucional para el Seguimiento, Revisión y Gestión Documental de Proyectos Académicos

Versión del documento: 1.0

---

Si dentro del proyecto existe un archivo denominado DEVELOPMENT_STATUS.md, la inteligencia artificial deberá leerlo antes de comenzar cualquier tarea para conocer el estado actual del desarrollo y evitar implementar funcionalidades ya existentes o asumir características que aún no han sido desarrolladas.

# 1. Introducción

Este documento tiene como objetivo proporcionar el contexto completo del proyecto para cualquier inteligencia artificial o desarrollador que participe en su desarrollo.

Antes de realizar cualquier modificación en el código, este documento debe leerse completamente.

Su propósito es garantizar que todas las decisiones técnicas, arquitectónicas, funcionales y de diseño mantengan una misma línea de trabajo durante todo el desarrollo del sistema.

Este documento representa la fuente principal de información del proyecto y deberá utilizarse como referencia durante todas las etapas de desarrollo.

Si en algún momento existe una contradicción entre este documento y el código del proyecto, primero deberá analizarse la situación y proponer una solución antes de realizar modificaciones importantes.

---

# 2. Descripción del proyecto

## Nombre del proyecto

Diseño e implementación de una plataforma web institucional para el seguimiento, revisión y gestión documental de proyectos académicos en la carrera de Desarrollo de Software.

## Descripción

Este proyecto corresponde al trabajo de titulación de la carrera de Tecnología en Desarrollo de Software.

La finalidad del sistema es digitalizar, organizar y optimizar el proceso de entrega, revisión, seguimiento y almacenamiento de proyectos académicos desarrollados por estudiantes dentro de la institución.

Actualmente este proceso suele realizarse mediante correos electrónicos, aplicaciones de mensajería y múltiples versiones de documentos, dificultando el control del avance, la organización de la información y el seguimiento de las observaciones realizadas por los docentes.

La plataforma busca centralizar todo el proceso dentro de un único sistema institucional.

No pretende reemplazar plataformas como GitHub.

Su propósito es completamente diferente.

Este sistema está orientado exclusivamente a la gestión documental y al seguimiento académico de proyectos.

---

# 3. Objetivos del sistema

El sistema deberá permitir:

- Registrar proyectos académicos.
- Gestionar usuarios según su rol.
- Administrar documentos.
- Mantener un historial completo de versiones.
- Facilitar la revisión documental.
- Registrar observaciones y comentarios.
- Gestionar el estado de cada proyecto.
- Mantener un repositorio institucional.
- Centralizar toda la información relacionada con cada proyecto.

Toda funcionalidad desarrollada deberá aportar valor a uno o varios de estos objetivos.

Si una nueva característica no contribuye al propósito general del sistema, deberá evaluarse antes de ser implementada.

---

# 4. Estado actual del desarrollo

El proyecto ya no se encuentra en una etapa de prototipado.

Actualmente el desarrollo se realiza directamente sobre la aplicación funcional utilizando PHP bajo una arquitectura MVC.

Las interfaces iniciales ya fueron diseñadas y ahora forman parte del desarrollo principal del sistema.

A partir de este punto, todas las mejoras deberán implementarse directamente sobre la estructura actual del proyecto.

No deberán desarrollarse prototipos independientes en HTML.

Todo nuevo desarrollo deberá integrarse correctamente con la arquitectura existente.

---

# 5. Tecnologías utilizadas

## Frontend

- HTML5
- CSS3
- JavaScript

## Backend

- PHP

## Base de datos

- MariaDB con motor InnoDB
- Codificación `utf8mb4`
- Conexión PDO y consultas preparadas
- Modelo relacional normalizado
- Archivos privados fuera de la base de datos; nunca BLOB

## Arquitectura

- Modelo - Vista - Controlador (MVC)

## Servidor de desarrollo

- Apache (XAMPP)

## Servidor de producción previsto

- Hosting Linux administrado mediante cPanel.
- Apache con protección `.htaccess`.
- Configuración local no versionada para aplicación y MySQL.

## Control de versiones

- Git
- GitHub

Las tecnologías seleccionadas permiten desarrollar una solución institucional organizada, escalable y de fácil mantenimiento.

El proyecto no utilizará frameworks de PHP.

El objetivo es desarrollar una solución propia manteniendo una arquitectura limpia y bien estructurada.

---

# 6. Arquitectura del proyecto

El sistema utiliza el patrón de arquitectura Modelo - Vista - Controlador (MVC).

La finalidad de esta arquitectura es separar claramente las responsabilidades de cada componente del sistema.

## Modelo

Es responsable de la comunicación con la base de datos.

Debe contener únicamente la lógica relacionada con el manejo de la información.

No debe contener código HTML.

No debe generar vistas.

## Controlador

Es el encargado de recibir las solicitudes del sistema.

Coordina la comunicación entre los modelos y las vistas.

Debe contener la lógica de negocio necesaria para procesar cada solicitud.

No debe contener consultas SQL extensas.

No debe contener código HTML innecesario.

## Vista

Es responsable únicamente de la interfaz gráfica.

Debe mostrar la información enviada por el controlador.

No debe contener lógica de negocio.

No debe acceder directamente a la base de datos.

Toda nueva funcionalidad desarrollada deberá respetar esta arquitectura.

---

# 7. Filosofía de desarrollo

La prioridad de este proyecto no es desarrollar la mayor cantidad de código posible.

La prioridad es construir una plataforma estable, organizada, mantenible y fácil de comprender.

Cada nueva funcionalidad deberá integrarse de forma natural con la arquitectura existente.

Siempre se priorizará la calidad del código sobre la velocidad de implementación.

El desarrollo deberá orientarse a construir una aplicación profesional que pueda mantenerse y ampliarse fácilmente en el futuro.

---

# 8. Principios obligatorios de desarrollo

Durante todo el proyecto deberán respetarse los siguientes principios:

- Mantener el código limpio.
- Mantener el código organizado.
- Mantener el código modular.
- Mantener el código optimizado.
- Utilizar nombres descriptivos.
- Evitar la duplicación de código.
- Reutilizar componentes cuando sea posible.
- Respetar la arquitectura MVC.
- Mantener una estructura consistente.
- No romper funcionalidades existentes.
- Analizar el contexto antes de modificar cualquier archivo.
- Pensar siempre en la mantenibilidad del proyecto.

Estos principios tienen prioridad sobre cualquier sugerencia automática realizada por una inteligencia artificial.

---

# 9. Claridad y mantenimiento del código

El código del proyecto debe ser fácil de leer, entender y mantener.

Cualquier persona que revise el proyecto deberá poder identificar rápidamente:

- Qué hace cada archivo.
- Para qué sirve cada función.
- Qué datos recibe.
- Qué datos devuelve.
- Cómo se relaciona con el resto del sistema.

Los comentarios deberán mantenerse con el formato utilizado actualmente dentro del proyecto.

Al agregar nuevas funciones, clases o módulos, deberá conservarse el mismo estilo de comentarios y organización existente.

No se deben agregar comentarios innecesarios sobre instrucciones evidentes.

El objetivo no es llenar el código de comentarios, sino escribir un código claro, organizado y fácil de comprender.

Cuando se agregue nuevo código, este deberá seguir el mismo estilo utilizado en el resto del archivo para mantener una estructura uniforme durante todo el proyecto.

---

# 10. Convenciones para la inteligencia artificial

Toda inteligencia artificial que participe en este proyecto deberá seguir las siguientes instrucciones:

Antes de modificar cualquier archivo deberá:

1. Leer completamente el archivo.
2. Comprender su funcionamiento.
3. Analizar su relación con otros archivos.
4. Identificar posibles impactos.
5. Verificar que la modificación respete la arquitectura MVC.

Nunca deberá realizar cambios masivos sin una justificación clara.

Nunca deberá eliminar funcionalidades existentes sin comprender previamente su propósito.

Nunca deberá modificar la estructura general del proyecto únicamente por preferencias personales.

Siempre deberá respetar la organización existente del sistema.

Podrá sugerir mejoras relacionadas con:

- Arquitectura.
- Organización del código.
- Seguridad.
- Rendimiento.
- Experiencia de usuario.
- Accesibilidad.
- Escalabilidad.

Cuando detecte una mejora importante, deberá explicarla junto con sus beneficios antes de implementarla.

Las decisiones relacionadas con la arquitectura, la estructura del sistema o cambios importantes deberán ser aprobadas antes de realizarse.

La inteligencia artificial actúa como un asistente de desarrollo.

No es responsable de tomar decisiones arquitectónicas por iniciativa propia.

Su función principal es colaborar respetando la visión original del proyecto y mantener la coherencia durante todo el desarrollo.

---

# 11. Filosofía de desarrollo del sistema

El desarrollo de esta plataforma se realiza de forma incremental.

Cada módulo debe construirse completamente antes de comenzar el siguiente.

No se busca desarrollar muchas funcionalidades rápidamente.

Se busca construir una plataforma estable, organizada, escalable y fácil de mantener.

Cada nueva funcionalidad deberá integrarse naturalmente con la arquitectura existente.

Siempre se priorizará la calidad sobre la cantidad.

Cuando exista más de una solución posible, deberá elegirse aquella que:

- Sea más sencilla de mantener.
- Reutilice código existente.
- Respete la arquitectura MVC.
- Facilite futuras ampliaciones.
- Mantenga la coherencia con el resto del sistema.

---

# 12. Usuarios del sistema

Actualmente el sistema posee tres tipos principales de usuarios.

Cada usuario tiene responsabilidades y permisos claramente definidos.

## Administrador

Es el encargado de administrar la plataforma.

Puede:

- Gestionar usuarios.
- Gestionar docentes.
- Gestionar estudiantes.
- Administrar proyectos.
- Administrar el repositorio institucional.
- Configurar parámetros generales.
- Consultar reportes.
- Administrar estados del sistema.
- Supervisar el funcionamiento general.

El administrador posee acceso completo a la plataforma.

---

## Docente

El docente representa el usuario principal del proceso de revisión documental.

Sus funciones principales son:

- Visualizar proyectos asignados.
- Descargar documentos.
- Revisar documentos.
- Registrar observaciones.
- Registrar comentarios.
- Cambiar estados.
- Consultar historial.
- Acceder al repositorio institucional.

La revisión documental constituye la función principal del docente.

Todo el flujo del sistema gira alrededor del proceso de revisión realizado por este usuario.

---

## Estudiante

El estudiante es responsable de la creación y actualización de su proyecto.

Puede:

- Crear proyectos.
- Subir nuevas versiones.
- Consultar observaciones.
- Consultar comentarios.
- Descargar documentos.
- Consultar el historial.
- Consultar estados.
- Acceder al repositorio institucional cuando corresponda.

En proyectos grupales únicamente el líder podrá realizar nuevos envíos.

Los demás integrantes únicamente visualizarán la información relacionada con el proyecto.

---

# 13. Flujo general del sistema

El funcionamiento principal de la plataforma sigue el siguiente proceso.

1. El estudiante crea un proyecto.

2. El proyecto queda registrado dentro del sistema.

3. El estudiante realiza el primer envío.

4. El docente recibe una notificación.

5. El docente revisa el documento.

6. El docente registra observaciones.

7. El docente cambia el estado del proyecto.

8. El estudiante recibe la notificación.

9. El estudiante realiza las correcciones.

10. El estudiante envía una nueva versión.

11. El historial conserva todas las versiones anteriores.

12. El proceso continúa hasta que el proyecto sea aprobado.

13. Una vez finalizado, el proyecto pasa al repositorio institucional.

Todo este flujo debe mantenerse registrado dentro del historial del proyecto.

---

# 14. Gestión documental

La gestión documental constituye el núcleo principal de la plataforma.

Toda la lógica del sistema gira alrededor del manejo de documentos académicos.

Inicialmente el sistema trabajará con:

- PDF
- Microsoft Word
- Archivos ZIP

Cada documento enviado deberá quedar asociado a:

- Proyecto.
- Versión.
- Fecha.
- Hora.
- Usuario que realizó el envío.
- Estado correspondiente.

El sistema deberá conservar todas las versiones enviadas.

No deberán eliminarse versiones anteriores.

Cada nueva entrega representa una nueva versión del proyecto.

---

# 15. Historial de versiones

Cada proyecto mantendrá un historial completo de todas sus entregas.

Cada versión deberá conservar:

- Documento enviado.
- Fecha.
- Hora.
- Usuario.
- Observaciones.
- Comentarios.
- Estado.

El historial permitirá conocer la evolución completa del proyecto desde su creación hasta su finalización.

Nunca deberá eliminarse información histórica.

---

# 16. Estados del proyecto

Durante su ciclo de vida un proyecto podrá encontrarse en diferentes estados.

Se utilizarán los siguientes, en este orden:

- En desarrollo.
- En revisión.
- Requiere cambios.
- Aprobado.
- En tribunal, únicamente para proyectos de tesis.
- Publicado.

Los proyectos que no sean tesis avanzarán directamente de Aprobado a Publicado.

La arquitectura deberá permitir incorporar nuevos estados sin afectar el funcionamiento del sistema.

---

# 17. Reglas de negocio

Las siguientes reglas deberán respetarse durante todo el desarrollo.

## Proyectos

Cada proyecto pertenece a uno o varios estudiantes.

Todo proyecto deberá tener un líder.

El líder será responsable del envío de nuevas versiones.

---

## Integrantes

Todos los integrantes podrán visualizar la información del proyecto.

Únicamente el líder podrá subir nuevas versiones.

---

## Versiones

Cada envío genera automáticamente una nueva versión.

Las versiones anteriores permanecerán almacenadas.

Nunca deberán sobrescribirse documentos existentes.

---

## Observaciones

Las observaciones registradas por el docente deberán conservarse junto a la versión correspondiente.

Cada observación deberá formar parte del historial.

---

## Comentarios

Los comentarios forman parte del proceso de revisión.

No deberán eliminarse automáticamente.

Deben permanecer asociados a la versión correspondiente.

---

## Estados

Cada cambio de estado deberá quedar registrado.

Será posible consultar el historial completo de cambios.

---

## Repositorio institucional

Cuando un proyecto sea publicado, pasará al repositorio institucional.

Desde ese momento podrá consultarse como proyecto concluido.

---

# 18. Módulos principales

La plataforma estará organizada mediante módulos independientes.

Cada módulo deberá tener responsabilidades claramente definidas.

Los módulos actuales son:

- Inicio (Dashboard)
- Calendario y seguimiento de fechas académicas
- Gestión de proyectos
- Gestión documental
- Revisión documental
- Historial de versiones
- Notificaciones
- Repositorio institucional
- Gestión de usuarios
- Perfil
- Configuración
- Reportes

Cada módulo deberá desarrollarse de forma independiente pero completamente integrado con el resto del sistema.

La comunicación entre módulos deberá mantenerse organizada y respetar la arquitectura MVC.

---

# 19. Escalabilidad

El sistema deberá desarrollarse pensando en futuras ampliaciones.

Las nuevas funcionalidades deberán integrarse sin modificar la estructura existente.

Siempre que sea posible deberán reutilizarse:

- Componentes.
- Modelos.
- Controladores.
- Vistas.
- Funciones.
- Recursos compartidos.

El objetivo es evitar duplicación de código y facilitar el mantenimiento futuro.

La arquitectura deberá permitir incorporar nuevos módulos, nuevos tipos de usuarios y nuevas funcionalidades sin necesidad de rediseñar completamente el sistema.

---

# 20. Filosofía de la interfaz de usuario

La plataforma debe transmitir una imagen profesional, moderna, intuitiva e institucional.

El objetivo es que cualquier usuario pueda utilizar el sistema sin necesidad de una capacitación extensa.

Cada pantalla deberá mantener una identidad visual consistente.

Se deberá respetar el mismo estilo en:

- Colores.
- Tipografías.
- Espaciados.
- Botones.
- Tarjetas.
- Formularios.
- Tablas.
- Iconografía.
- Animaciones.

Toda nueva interfaz deberá integrarse visualmente con las pantallas existentes.

No deberán diseñarse pantallas con estilos completamente diferentes al resto del sistema.

---

# 21. Experiencia de usuario (UX)

Cada decisión de diseño deberá mejorar la experiencia del usuario.

Se deberá procurar que las acciones importantes puedan realizarse con la menor cantidad de pasos posible.

El sistema debe comunicar claramente:

- El estado de un proyecto.
- Las acciones disponibles.
- Las notificaciones pendientes.
- Los errores.
- Las confirmaciones.

El usuario nunca debe sentirse perdido dentro de la plataforma.

Siempre deberá saber:

- Dónde se encuentra.
- Qué puede hacer.
- Qué ocurrió después de realizar una acción.

---

# 22. Organización del proyecto

La estructura actual del proyecto deberá mantenerse organizada.

Antes de crear nuevos archivos deberá verificarse si ya existe una solución reutilizable.

Se deberá evitar:

- Archivos duplicados.
- Funciones repetidas.
- Componentes innecesarios.
- Código sin utilizar.

Cada archivo deberá tener una única responsabilidad.

Si un archivo comienza a crecer demasiado, deberá evaluarse su división en componentes más pequeños.

---

# 23. Convenciones de programación

Durante todo el desarrollo deberán respetarse las siguientes convenciones:

- Utilizar nombres descriptivos.
- Evitar abreviaturas innecesarias.
- Mantener funciones pequeñas y específicas.
- Mantener clases con responsabilidades claras.
- Evitar código duplicado.
- Mantener una estructura uniforme.

El código deberá escribirse pensando en que otra persona pueda comprenderlo fácilmente.

La prioridad será siempre la legibilidad.

---

# 24. Comentarios del código

El proyecto ya posee un formato definido para organizar comentarios.

Ese formato deberá mantenerse durante todo el desarrollo.

Cuando se creen nuevos archivos o nuevas secciones, los comentarios deberán seguir el mismo estilo utilizado en el resto del proyecto.

Los comentarios deberán utilizarse para:

- Separar secciones importantes.
- Explicar procesos complejos.
- Facilitar la navegación dentro del código.

No deberán utilizarse para explicar instrucciones evidentes.

Un código bien escrito debe ser entendible por sí mismo.

Los comentarios deben complementar el código, no reemplazar una buena estructura.

---

# 25. Optimización

Toda nueva funcionalidad deberá desarrollarse buscando:

- Buen rendimiento.
- Bajo consumo de recursos.
- Código reutilizable.
- Facilidad de mantenimiento.

Antes de implementar una solución compleja deberá analizarse si existe una alternativa más simple.

La simplicidad será siempre una ventaja.

---

# 26. Seguridad

Aunque este proyecto tenga un enfoque académico, deberá desarrollarse siguiendo buenas prácticas de seguridad.

Siempre que corresponda deberán considerarse aspectos como:

- Validación de datos.
- Sanitización de entradas.
- Protección contra inyección SQL.
- Protección contra XSS.
- Manejo adecuado de sesiones.
- Control de permisos.
- Restricción de accesos según el rol del usuario.

La seguridad deberá formar parte del desarrollo desde el inicio y no añadirse únicamente al finalizar el proyecto.

---

# 27. Escalabilidad

El sistema debe permitir futuras ampliaciones.

Las nuevas funcionalidades deberán integrarse sin modificar la estructura general del proyecto.

Siempre que sea posible deberán reutilizarse:

- Componentes.
- Modelos.
- Controladores.
- Vistas.
- Funciones.
- Recursos compartidos.

La arquitectura deberá facilitar el crecimiento del sistema.

---

# 28. Estado actual del proyecto

El proyecto se encuentra en desarrollo activo.

La arquitectura MVC ya fue implementada.

Actualmente el desarrollo se realiza directamente sobre la aplicación funcional en PHP.

Cada nuevo módulo deberá respetar la estructura existente.

El objetivo actual es construir progresivamente cada módulo manteniendo una arquitectura limpia, organizada y fácil de mantener.

---

# 29. Forma de trabajo

El desarrollo seguirá un proceso incremental.

Antes de comenzar una nueva funcionalidad deberá analizarse el contexto del sistema.

Cada módulo deberá quedar completamente funcional antes de iniciar el siguiente.

Cuando se detecte una oportunidad de mejora, primero deberá analizarse.

Si la mejora afecta la arquitectura, la organización del proyecto o modifica una funcionalidad importante, deberá presentarse una propuesta antes de implementarla.

No deberán realizarse cambios importantes de manera automática.

---

# 30. Rol de la inteligencia artificial

La inteligencia artificial participa como asistente de desarrollo.

Su función principal es colaborar con el desarrollo del proyecto respetando las decisiones previamente establecidas.

La IA podrá:

- Detectar errores.
- Corregir problemas.
- Optimizar código.
- Proponer mejoras.
- Refactorizar cuando sea necesario.
- Detectar riesgos.
- Mejorar la experiencia del usuario.
- Sugerir buenas prácticas.

Antes de realizar cambios importantes deberá explicar:

- Qué problema detectó.
- Por qué considera que existe una mejor solución.
- Qué beneficios tendría implementarla.
- Qué impacto tendría sobre el proyecto.

La decisión final siempre corresponderá al desarrollador.

---

# 31. Objetivo final

El propósito de este proyecto no es únicamente desarrollar una plataforma funcional.

El objetivo es construir una aplicación con una arquitectura sólida, código limpio, una interfaz moderna y una base lo suficientemente organizada para facilitar su mantenimiento y evolución en el futuro.

Cada decisión tomada durante el desarrollo deberá contribuir a ese objetivo.

---

# 32. Consideraciones finales

Este documento deberá actualizarse conforme el proyecto evolucione.

Cuando se incorporen nuevos módulos, reglas de negocio o cambios importantes en la arquitectura, deberán documentarse para mantener este contexto actualizado.

La consistencia del proyecto dependerá tanto de la calidad del código como de la documentación que acompañe su desarrollo.

Todo cambio importante deberá reflejarse tanto en el código como en este documento.

Este documento constituye la referencia principal para cualquier inteligencia artificial o desarrollador que participe en el proyecto.

---

# 33. Reglas de colaboración para la Inteligencia Artificial

Toda inteligencia artificial que participe en este proyecto deberá respetar las siguientes reglas durante el desarrollo.

Estas reglas tienen como objetivo mantener la estabilidad del proyecto, conservar la calidad del código y evitar modificaciones innecesarias.

---

## Antes de realizar cualquier cambio

Antes de modificar un archivo, la IA deberá:

1. Leer completamente el archivo.
2. Comprender su funcionamiento.
3. Analizar cómo interactúa con el resto del sistema.
4. Verificar si ya existe una solución similar dentro del proyecto.
5. Respetar la arquitectura MVC.
6. Evaluar el impacto de la modificación.

Nunca deberá modificar archivos sin comprender previamente su propósito.

---

## Respeto por la estructura existente

La estructura actual del proyecto ya fue definida.

No deberá reorganizar carpetas, mover archivos o modificar la arquitectura únicamente por preferencias personales.

Si considera que existe una mejor organización, primero deberá proponerla y explicar sus ventajas antes de implementarla.

---

## Comentarios del código

El proyecto ya posee un formato específico para organizar comentarios.

Ese formato deberá mantenerse durante todo el desarrollo.

Al crear nuevas funciones, módulos o archivos deberán utilizarse comentarios con el mismo estilo utilizado en el resto del proyecto.

No deberá eliminar comentarios existentes sin una razón justificada.

---

## Formato del código

Todo nuevo código deberá mantener el mismo estilo utilizado en el archivo correspondiente.

Se deberá respetar:

- Indentación.
- Organización.
- Espaciado.
- Nombres de variables.
- Nombres de funciones.
- Distribución de secciones.

El objetivo es mantener una estructura uniforme en todo el proyecto.

---

## Codificación de archivos

La IA deberá conservar siempre la codificación original de los archivos.

No deberá modificar la codificación de un archivo sin autorización.

Siempre que sea posible deberá mantenerse:

- UTF-8

No deberá convertir archivos a otras codificaciones.

Tampoco deberá provocar cambios que generen caracteres corruptos como:

- Ã
- �
- Â

Las tildes, la letra "ñ" y todos los caracteres especiales del idioma español deberán conservarse correctamente.

Si detecta un problema relacionado con la codificación, deberá informarlo antes de guardar cambios masivos.

---

## Cambios pequeños antes que refactorizaciones grandes

Si el objetivo consiste en modificar una función específica, únicamente deberá modificarse esa función.

No deberá aprovechar una solicitud pequeña para reescribir completamente un archivo.

Las modificaciones deberán ser proporcionales al cambio solicitado.

---

## Reutilización del código

Antes de crear nuevas funciones deberá verificar si ya existe una solución reutilizable.

Siempre que sea posible deberá reutilizar:

- Componentes.
- Funciones.
- Métodos.
- Controladores.
- Modelos.
- Recursos compartidos.

La duplicación de código deberá evitarse.

---

## Optimización

La IA podrá optimizar el código cuando detecte oportunidades claras de mejora.

Sin embargo, deberá priorizar:

- Legibilidad.
- Mantenibilidad.
- Simplicidad.

No deberá aplicar optimizaciones que compliquen innecesariamente el código.

---

## Propuestas de mejora

Si durante el desarrollo identifica una mejora relacionada con:

- Arquitectura.
- Seguridad.
- Organización.
- Rendimiento.
- Experiencia de usuario.
- Accesibilidad.
- Escalabilidad.

Deberá explicarla antes de implementarla.

La explicación deberá indicar:

- Qué problema encontró.
- Por qué considera que existe una mejor solución.
- Qué beneficios tendría.
- Qué archivos serían afectados.

Las modificaciones importantes deberán aprobarse antes de realizarse.

---

## Manejo de errores

Si encuentra errores durante el desarrollo deberá intentar resolverlos respetando la arquitectura existente.

No deberá reemplazar grandes bloques de código únicamente para solucionar un problema puntual.

Siempre deberá buscar la solución menos invasiva.

---

## Dependencias

Antes de incorporar una nueva librería o dependencia deberá verificar si el problema puede resolverse utilizando herramientas ya existentes dentro del proyecto.

Se priorizará el uso de soluciones nativas de PHP, JavaScript, HTML y CSS.

No deberán agregarse dependencias innecesarias.

---

## Seguridad

Toda nueva funcionalidad deberá desarrollarse considerando aspectos básicos de seguridad.

Cuando corresponda deberán aplicarse buenas prácticas relacionadas con:

- Validación de datos.
- Sanitización.
- Manejo de sesiones.
- Control de permisos.
- Protección contra inyección SQL.
- Protección contra XSS.

---

## Comunicación durante el desarrollo

Cuando una solicitud pueda interpretarse de diferentes maneras, la IA deberá indicar las alternativas disponibles antes de implementar una solución.

No deberá asumir decisiones importantes sin consultar.

---

## Objetivo principal

La función de la inteligencia artificial no consiste únicamente en generar código.

Su principal responsabilidad es colaborar en el desarrollo de una plataforma estable, organizada, mantenible y fácil de comprender.

Cada modificación deberá mejorar el proyecto sin comprometer su estructura, su arquitectura ni su calidad.

## Regla fundamental

Si una modificación no fue solicitada, no deberá realizarse.

La IA deberá limitar los cambios al alcance de la solicitud realizada.

No deberá aprovechar una petición específica para modificar otras partes del proyecto sin autorización.

El objetivo es mantener un desarrollo controlado, estable y predecible.

## Conservación del contexto

Antes de proponer una nueva funcionalidad o modificar una existente, la IA deberá considerar el propósito general del proyecto.

Las decisiones deberán mantener coherencia con las reglas de negocio, la arquitectura y la visión definida en PROJECT_CONTEXT.md.

Ninguna modificación deberá contradecir las decisiones previamente documentadas.

## Actualización del CHANGELOG

Cuando se complete una funcionalidad importante o se produzca un cambio significativo en la arquitectura, la inteligencia artificial deberá proponer la actualización correspondiente del archivo `CHANGELOG.md`.

No deberá registrar correcciones menores, cambios estéticos o modificaciones de poca relevancia.

El CHANGELOG debe reflejar únicamente los hitos importantes del desarrollo del proyecto.
