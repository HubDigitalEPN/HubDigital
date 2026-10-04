# Remediación QA6: métodos, ayuda de mapa y consultas compuestas

Esta revisión estática se basa en `rev 6/qa_portal_parte6.md`, especialmente QA6-006, QA6-007 y los cambios menores de UX. El código se prepara en la rama de trabajo de QA6. Las pruebas automáticas se ejecutan exclusivamente mediante `crear-paquete-oci`; este trabajo no ejecutó suites, compilaciones ni comprobaciones aisladas de sintaxis o formato.

## Contrato de métodos de colecta

La clave de agrupación y selección es el protocolo original con espacios extremos eliminados y convertido a minúsculas. El agregado, las opciones de filtro y la consulta comparten esa definición. Los pares Beating/beating, Pitfall/pitfall y pitfall_human_faECes/pitfall_human_faeces producen una categoría cada uno. No se aplica una corrección lingüística ni una inferencia sobre técnicas desconocidas.

`ProtocoloColectaPublico::sql()` conserva la precedencia del dato individual sobre el de muestra y su texto fuente. `claveSql()` y `clave()` se usan exclusivamente para comparar y agrupar. El panel JSON incorpora `fuentes`, con los valores originales públicos de cada categoría; las etiquetas accesibles de la barra también los incluyen. Las fichas y exportaciones individuales mantienen el protocolo fuente. Los campos reservados, los registros no publicados y los marcadores curatoriales siguen excluidos del agregado y del filtro.

Pest incorpora en `PortalMetodosQa6Test.php` tres casos de variantes: cada categoría anuncia tres registros; seleccionar una barra después de filtrar Pichincha conserva exactamente los dos registros de esa provincia. Se comprueban también recarga con una variante histórica, selección por checkbox, respaldo del método en la muestra, conservación del texto original y exclusión de fuentes reservadas/no publicadas. No se añade Gherkin porque el recorrido y resultado ya quedan cubiertos por estos contratos Pest.

## Ayuda y accesibilidad cognitiva

La explicación del mapa distingue el clúster de pantalla, que cuenta ubicaciones y acerca el mapa, del punto en un par original de coordenadas, que agrupa registros. Describe los colores por filo y su composición cuando hay varios, y actualiza las páginas de seis taxones terminales o seis ejemplares. Conserva las advertencias de incertidumbre, datos reservados y sesgo de muestreo. La explicación se contrastó por lectura con `portal-map-model.js`, `portal-dashboard.js` y el modal de ubicación.

La tabla ofrece columnas principales y un botón para ver todos los campos y fotografías. El código permanece fijo al desplazarse; el botón tiene estado accesible y no cambia los permisos ni la selección. Las columnas secundarias siguen en la tabla y se muestran juntas al activar el botón. Se conserva la paginación de seis porque QA6 propone valorar tamaños alternativos, y este cambio resuelve la comparación mediante menos columnas simultáneas sin modificar el contrato de páginas.

Los textos del encabezado del dashboard, las tarjetas y los diálogos distinguen un registro de varios y un taxón descendiente de varios. El catálogo utiliza miles con punto y decimales con coma, en coherencia con `es-EC` del cliente. Las vistas principales de taxón tienen un H1 coherente con el nivel seleccionado. Los textos de descarga explican el alcance del CSV/XLSX y presentan el identificador del perfil de intercambio vigente.

## Chat compuesto

Cuando una pregunta pide cantidad y después cómo usar el mapa, el asistente consulta la primera parte y añade una explicación de uso. El enlace adicional preserva los filtros de la selección aplicada si esa es la población pedida; para una consulta nueva se construye desde sus entidades, sin heredar otra selección de la página. La intención, los datos del conteo y los enlaces existentes se conservan.

`PortalChatCompuestoQa6Test.php` añade dos contratos Pest: selección aplicada con taxón, país, provincia, localidad y fechas; y consulta nueva por género/provincia frente a otra selección previa. Se verifican ambas partes de la respuesta y la igualdad de filtros del enlace al mapa. Los casos previos de conteo y ayuda de puntos ausentes se conservan; no sustituyen estos casos de pregunta compuesta.

## Evidencia y límites

La revisión estática del enlace de recuperación encontró que sólo reflejaba el cambio de vista y la restauración: una petición fallida tras pulsar un indicador conservaba la selección anterior. `portal-request-model.js` ahora reproduce los efectos públicos de métodos, provincia, década, mes, altitud, área, filo, navegación, limpieza y accesos a registros. Desenvuelve las tuplas de Livewire y aplica actualizaciones anidadas de checkboxes. La restauración parte de los valores por defecto para retirar filtros omitidos, y las llamadas se aplican en el mismo orden del mensaje. `tests/Frontend/portal-request-model.test.mjs` incorpora estos recorridos y verifica independientemente los parámetros del enlace; permanece pendiente su ejecución dentro del paquete.

Se revisaron estáticamente fuentes, agregados SQL, filtros, precedencia del protocolo, visibilidad, contenido accesible y contratos de las nuevas pruebas. Se actualizaron expectativas existentes para claves canónicas, sin alterar los valores originales ni quitar verificaciones. La ficha de prueba de paratipo coloca el estado nomenclatural en `type_status` y mantiene `disposition=in_collection`, comprobando ambos significados por separado.

La disposición se muestra por separado de la condición nomenclatural. Cuando el campo de tipo es público pero desconocido, el componente presenta «No informado»; un campo reservado conserva su ausencia pública. La prueba de columnas conserva las barreras curatoriales y la allowlist, ahora con disposición como columna propia.

La ejecución completa del paquete y los recorridos visuales/por teclado en Edge quedan a cargo del flujo principal de esta solicitud. Este documento no declara pruebas aprobadas, ausencia de regresiones ni cierre de verificación visual. La revisión de contraste, foco y adaptación a 333/400 píxeles requiere evidencia de ese recorrido.
