# QA6: integridad taxonómica y conflicto geográfico

Este cambio atiende los hallazgos QA6-002 y QA6-003 de `rev 6/qa_portal_parte6.md`. La migración `2026_10_03_000017` aplica decisiones acotadas a registros identificados por `fila_origen_excel`, `occurrence_id` y `old_code`; el código público por sí solo no identifica una fila. Las claves proceden de las fuentes originales versionadas de localidades y protocolos, sin modificar la evidencia externa.

## Anastrepha: relación científica del género

La [publicación del Systematic Entomology Laboratory de USDA ARS](https://www.ars.usda.gov/research/publications/publication/?seqNo115=384801), de Rodríguez Clavijo y Norrbom (2021), sitúa explícitamente Anastrepha en Diptera/Tephritidae. La [clave de Anastrepha freidbergi de Norrbom y colaboradores](https://idtools.org/id/Anastrepha/Key/anatox/Media/Html/anfreidb.htm) documenta ese nombre. Ambas se consultaron el 3 de octubre de 2026: USDA respondió con su publicación y la ficha IDtools estuvo disponible en el índice del buscador; su apertura directa no respondió. El endpoint USDA `349900` del informe tampoco respondió en esta sesión. No se atribuye una comprobación en vivo a esos dos fallos.

Para MEPN-INV-37369, fila 31584 y oldCode `LOTE # 641`, se corrige la relación del género Anastrepha con la familia Tephritidae y el orden Diptera. Los UUID taxonómicos y del ejemplar se mantienen. Cuando existe un nivel intermedio bajo el ancestro correcto, se conserva; la remediación no introduce automáticamente Trypetinae ni cambia sinonimias. Los ancestros generales Animalia/Arthropoda/Insecta se reutilizan sin alterar relaciones incompatibles de otras ramas; una incompatibilidad en esos ancestros detiene la migración para revisión. La identificación del ejemplar físico sigue siendo la identificación original.

Antes de cambiar cada padre se registra el taxón y su ruta original en `taxonomia.correcciones_cientificas`, junto con fuentes, decisión, tratamiento y estado. La auditoría del ejemplar conserva su linaje completo, `taxon_verbatim` y notas previas. La nota científica se añade a `taxonomic_notes` sin sustituir el contenido existente. Árbol, filtros y exportaciones usan el mismo `taxon_id` y sus nuevos padres; la revisión del caché se invalida por el trigger vigente.

El importador aplica la misma decisión cuando un género Anastrepha llega de nuevo con el linaje incompatible. Conserva los campos de la clasificación fuente en `taxonomic_notes` y marca esa fila para revisión. Una fuente ya compatible con Diptera/Tephritidae, incluida Trypetinae, se conserva. No se sustituyen géneros por similitud de nombre ni se aplica una conversión global de `EC`.

## Puerto de la Ragua: cuarentena geográfica

El [MITECO/OAPN](https://www.miteco.gob.es/es/parques-nacionales-oapn/red-parques-nacionales/parques-nacionales/sierra-nevada/guia-visitante/itinerarios.html), consultado el 3 de octubre de 2026, sitúa Puerto de la Ragua en Sierra Nevada, en el límite provincial Granada/Almería, España. Esa fuente confirma el topónimo y la contradicción, pero no acredita dónde se colectó MEPN-INV-30203.

Para la fila 25342, occurrenceID `MEPN-INV-30203`, oldCode `787`, se exige también la combinación original Ecuador/Granada, localidad `Sierra Nevada, Puerto de la Ragua` y par exacto `-4.0226841, -79.194422`. La migración conserva en auditoría todos los campos y el objeto de localidad asociado, incluido el código INEC. El par pasa también a `coord_verbatim`, `verbatim_latitude` y `verbatim_longitude`, conservando valores previos cuando existen. Se retiran ambas coordenadas estructuradas y `localidad_id`, que vincula la referencia INEC incompatible; no se cambia el objeto de localidad compartido con otras filas.

El país, provincia, localidad original y notas de etiqueta se mantienen como evidencia por curar. Se registra `estado_revision=pendiente` y se agregan advertencias con fuente a `motivo_revision`, `locality_notes` y `specimen_notes`. El importador evita reintroducir el mismo conflicto y preserva el texto completo original. No se asigna España como país de colecta, ningún centroide ni una coordenada de sendero.

La política vigente exige un par válido para incorporar material al portal público: el registro pasa a curaduría y queda excluido de la selección pública, mapa, conteos y exportaciones públicas. La bandera de publicación no se elimina; aun marcada, la elegibilidad geográfica lo excluye hasta que se confirmen sus campos. La curaduría debe contrastar etiqueta e historial y aprobar país, localidad, coordenada, incertidumbre y procedencia antes de volver a publicar. Esta medida resuelve la difusión del dato incompatible; el lugar real de colecta sigue pendiente, con esa limitación expresa.

## Grafías de opciones geográficas

`NormalizacionGeografica::nombresDisponibles` agrupa opciones equivalentes según la misma clave que utiliza el filtro. Muestra Nariño y Chocó como grafías de referencia, respaldadas por [DANE DIVIPOLA](https://microdatos.dane.gov.co/index.php/catalog/866/variable/F2/V66?name=CDIGODIVIPOLA), conservando Narino/Choco en las filas originales. No añade provincias que no tengan material público y no atribuye a Colombia un registro únicamente por el nombre.

La selección resuelve grafías de barras, URLs y borradores contra las opciones públicas por la misma clave. El historial y el selector conservan la opción canónica. La riqueza agrupa las variantes en SQL antes de contar especies distintas, por lo que una especie presente tanto en Narino como en Nariño no se cuenta dos veces; sus registros sí se suman. La fuente de cada registro permanece intacta.

## Cobertura y estado

Se agregaron pruebas Pest de prevención de reimportación, preservación del linaje, identidad compuesta, lectura pública de familia, filtros por orden/familia, auditoría idempotente y exclusión del conflicto geográfico sin afectar códigos repetidos. El helper de opciones tiene cobertura propia. No se añaden escenarios Gherkin que repitan esas entradas y resultados.

Se revisaron estáticamente las consultas, contratos, preservación de fuentes, manejo de ancestros y efectos del caché. No se ejecutaron estas pruebas ni verificaciones automáticas fuera de `crear-paquete-oci`. Por autorización posterior del usuario para preparar la revisión en Edge, la migración `000017` se aplicó en la base local `hubdigital` después de confirmar `127.0.0.1:5432`; el comando informó `DONE` (585,11 ms). Esa preparación no acredita los casos de Pest ni la aceptación del despliegue. La validación integral corresponde a la siguiente ejecución autorizada del paquete. El cierre visual de árbol/ficha y la decisión curatorial de Ragua requieren evidencia posterior; no se declara ausencia de regresiones ni curación geográfica terminada.

El validador Windows del paquete admite ahora PostgreSQL ya iniciado mediante `pg_ctl`, además del servicio activo. Consulta únicamente su endpoint `127.0.0.1:5432`; conserva las comprobaciones de credencial/base y toda la suite. Sólo detiene el servicio cuando el propio paquete lo ha iniciado. Este ajuste se revisó estáticamente y no se ejecutó el paquete como parte de esta subtarea.
