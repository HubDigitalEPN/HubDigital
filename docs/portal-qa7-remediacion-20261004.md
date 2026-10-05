# Remediación del portal — revisión 7

Fecha: 4 de octubre de 2026. Rama: `fix/portal-remediacion-qa7-20261004`, creada desde `main` en `33bc3044cfcad07ea639392c90f462747710a0bf`.

## Alcance y evidencia

Se tomó como entrada el [informe original](../../rev%207/qa_portal_parte7.md), sus tres ZIP y las evidencias extraídas en `C:\HT\LABINVEPN\rev 7\evidencias-extraidas\qa_portal_parte7`. Los originales permanecen conservados. Se revisaron los ocho hallazgos, la matriz de fallos/parciales, los turnos de chat, los datos de proyección y el contraste taxonómico; las capturas relevantes de negación y hoja terminal se contrastaron con sus estados TXT. No se ejecutaron los scripts de las evidencias ni se trataron sus observaciones históricas como pruebas de esta rama.

QA7 registra 282 casos dirigidos, con 24 fallos agrupables, cinco parciales y límites explícitos. Los 24 fallos no representan 24 defectos independientes. El inventario de 90 etiquetas tampoco acredita identificaciones físicas ni una tasa de error del catálogo.

Estado de entrega: **correcciones implementadas y revisadas estáticamente; aceptación integral pendiente**. No se ejecutó `crear-paquete-oci`, no se publicaron cambios y no se modificó `main`. No hay evidencia de un despliegue de esta rama. La autorización histórica de QA6 no habilita ejecutar el paquete en QA7.

## Hallazgos principales

| Hallazgo | Cambio implementado | Evidencia y aceptación pendiente |
| --- | --- | --- |
| QA7 001, Q33/Q34/Q39: exclusión invertida | Operador provincial independiente `fxprov`, desde el contexto del chat hasta filtros, estadísticas, mapa, fichas y exportaciones. Provincia reservada, desconocida o vacía no acredita estar fuera. Una exclusión ambigua o de varios lugares pide aclaración sin conteo parcial. La corrección «no …, sino …» conserva su interpretación de cambio de criterio. | `PortalQa7RemediacionTest`: inclusión/exclusión, filas, mapa, cero, ausencia, reserva y ambigüedad. Repetir el oráculo de Camponotus: 67 coincidencias textuales dentro, cero fuera; 64 determinaciones exactas y tres calificadas no se confunden. |
| QA7 002, Q05/Q10/Q12/Q16/Q21/Q23/Q35/Q40: procedimiento mal interpretado | Ayuda local resuelve acción y objeto antes de entidades. CSV/XLSX señalan sus controles y alcance; depósito incluye autenticación, rol y seguimiento, sin traslado previo a instrucciones curatoriales. Q35 propone Formicidae/Orellana/enero/1990–1999, sin Excel, Después quiero ni La tabla como entidades. | Casos de variantes y consulta compuesta añadidos. El formulario real de trámite sigue sujeto a permisos existentes. |
| QA7 003, Q11/Q24: enlace global | Enlaces operativos usan la selección aplicada; cambiar vista sólo altera `vista`. Incluyen jerarquía, filtros de tipo, arrays, periodo, mes, altitud y caja espacial. Limpiar ofrece un enlace global únicamente cuando se pide restablecer. | Casos de conservación de parámetros añadidos; comprobar IDs y URL después del clic en navegador. |
| QA7 004, Q03/Q20/Q29/Q42: seguimiento sin ámbito | Las elipsis de cantidad usan el contexto anterior o la selección de página según el referente; «de aquí» respeta selecciones vacías. La localidad utiliza permisos públicos. Paratipos se cuentan como registros, con advertencia sobre individuos. «Cuántos hay» pide unidad. Una ayuda intermedia conserva la consulta útil. | Conversaciones con selección, cero, localidad, condición de tipo y cambio de intención añadidas. |
| QA7 005, Q08/Q41: glosario confundido con trámite | Definiciones locales versionadas de typeStatus, disposition, occurrenceStatus, precisión, incertidumbre y localidad INEC; enlaces al diccionario público y a los registros actuales. | Casos de glosario con prohibición de solicitudes HTTP externas añadidos. |
| QA7 006, Q01/Q07/Q14/Q27/Q32: ayuda y fuentes | Capacidades antes del saludo genérico; instrucciones de provincia/método; hormigas propone Formicidae. Clasificación local del taxón y descendientes, linaje divulgado, conteo y límites de identificación. Noticias de hoy reciben el límite de actualidad antes de una búsqueda histórica. | Casos de especie y género, permisos y ausencia de HTTP añadidos. No se inventa historia natural ni una noticia actual. |
| QA7 007, C7-B83: Adulto da falso cero | Clave compartida para adult/Adulto y otros estadios localizados, tanto en entrada como en valor de fuente. No se modifica life_stage original. | Seis pares de alias con combinación provincial y reserva de campo. Repetir 28972 aislado y combinado en navegador. |
| QA7 008, C7-T08: Tubifex terminal sin ficha | La vista utiliza la decisión de hoja terminal del servidor, independientemente del rango. Fichas, selector y paginación de seis registros; ausencia explícita y sin paginación de ejemplares vacía. | Hojas de familia, género y especie con siete UUID y dos páginas. Repetir MEPN-INV-15888 en su ubicación. |

## Mejoras complementarias de QA7

| Recomendación o caso | Implementación / límite |
| --- | --- |
| Todos los filtros activos visibles | Resumen de criterios aplicados, distinto del borrador. Retiro individual de jerarquía, filtros simples y miembros de listas; periodo, altitud, latitud y longitud se retiran como grupos independientes. Conserva los otros criterios y el historial. |
| Método legible y original separado | Etiquetas españolas para las 29 claves observadas, valor fuente en casillas/fichas y originales en ayuda accesible del gráfico. Agrupación y filtro conservan las claves existentes. |
| Diccionario CSV/XLSX | Ruta pública `/portal/diccionario-exportacion`, contrato 2.0, equivalencias de 14 columnas CSV y 26 XLSX, tipos, nulos, INEC, duplicados y cautelas. Incertidumbre métrica válida tipada en XLSX; desconocida vacía y texto no interpretable conservado. |
| Autoridad taxonómica | Instantánea documental de las 90 etiquetas, fuente COL XR/GBIF, checklist, fecha, consulta y decisión. En las fichas se distingue el nombre publicado de la referencia externa; la familia respeta permisos. Coincidencias automáticas rechazadas no se presentan como candidato aceptado. Véase el [expediente curatorial](qa7-expediente-taxonomico.md). |
| Ayuda con sólo conteos | Acción renombrada a Resumen del taxón; muestra rango/linaje públicos y un límite explícito cuando no hay descripción de historia natural. |
| Puntos próximos | Selector de coordenadas originales dentro del mapa, disponible con teclado y en modo ampliado. Las opciones se reemplazan al cambiar filtros. No desplaza ni inventa coordenadas. |
| C7-D05: residual de 0,536170909 px | Inspección del Leaflet local: latLngToLayerPoint redondea a enteros. Capas originales conservan la proyección fraccionaria. La prueba escrita cubre esa transformación; **el criterio independiente de ≤0,5 px CSS continúa pendiente en navegador**, incluidos mapa normal, ampliado, zoom y dispositivo. No se relajó la tolerancia. |
| Carga y error | Se conservan recuperación de consulta e historial de QA6. Se añade reintento del detalle y de teselas; una tesela exitosa no oculta otra fallida, y las teselas descartadas retiran su error. |
| Edición de textos | Tildes en guía de préstamo, inicial de provincias en el control sin cambiar el valor original, textos de rango y resumen; las etiquetas de fuente no se reescriben en la colección. |
| C7-E04: cita y copia | Los contratos existentes de cita y enlace permanecen; comprobar contenido real del portapapeles y archivo, además del mensaje visual, durante la aceptación. |
| C7-R06: foco modal | Diálogo nativo, cierre por Escape y retorno al invocador conservados; retiro de chip devuelve foco a un control existente. Verificación completa de ciclo Tab, foco visible y tamaños móviles pendiente. No se declara conformidad WCAG. |

## Decisiones científicas y plataforma pendientes

El expediente inicial documenta las 90 etiquetas de la revisión sin reidentificar material. Naesiotus eschariferus conserva Orthalicidae como clasificación publicada; Bulimulidae se presenta como contraste externo y decisión pendiente. La solicitud posterior autoriza corregir grafías corroboradas en las dos bases; véase la continuidad documentada al final. Combinaciones históricas, códigos locales y discrepancias de clasificación permanecen pendientes de decisión curatorial. No se aplica una sustitución general de EC.

La cuarentena de MEPN-INV-30203 (Granada/Puerto de la Ragua) implementada en QA6 se conserva: originales auditados, coordenadas estructuradas retiradas y vínculo INEC incompatible separado. [El expediente de QA6](qa6-taxonomia-geografia.md) explica su identidad compuesta y pendiente curatorial. No hay nueva evidencia para atribuirle coordenadas o país definitivos ni para reinsertarlo en el mapa.

Se leyó `public/service-worker.js`: almacena la pantalla pública offline y usa red para las navegaciones; no escribe expedientes/PDF/sesiones en Cache Storage. Esto es revisión estática, no prueba de instalación, activación o aislamiento en producción. No se infiere que el manifiesto esté dañado a partir de ERR_ABORTED.

QA7 no acreditó una vulnerabilidad. La revisión de autorizaciones existentes y pruebas de depósitos identifica barreras de rol y acceso a objetos ajenos; su ejecución corresponde al paquete. El intento público de IDOR de QA7 sigue sin completar una comprobación entre cuentas autorizadas. No se declara seguridad integral, se cambian permisos ni se ejecuta un escaneo ofensivo a partir de límites de instrumentación.

Quedan por medir PWA/offline/instalabilidad, las tres familias de navegador, Lighthouse y sus métricas, cabeceras/cookies reales, contraste renderizado, portapapeles y comportamiento visual. El runtime de Browser respondió «No browser is available» y el inventario disponible fue vacío; tampoco había servidor local de la aplicación. No se sustituyó por un canal de navegador no autorizado ni se arrancaron suites como preparación visual.

## Validación y publicación

Revisión realizada: lectura estática de código, contratos y diffs; correspondencia del flujo chat–selección–consulta–mapa–exportación, permisos por campo, terminales, nulos, recuperación y foco declarado en las plantillas. Contraste con cobertura vigente del script `crear-paquete-oci-core.ps1` y las pruebas existentes. Esta revisión no ejecutó la aplicación.

Pruebas preparadas, no ejecutadas:

- `tests/Feature/PortalQa7RemediacionTest.php`: casos nuevos de los ocho hallazgos, contexto intermedio, resumen de filtros, diccionario y reserva del contraste externo.
- `tests/Frontend/portal-dashboard-actions.test.mjs`: casos nuevos de selector, errores de teselas y transformación fraccionaria; adaptación de Leaflet simulada para sus nuevos constructores, conservando las aserciones anteriores.
- `Modules/CatalogoPublico/tests/Unit/PortalContratoExportacionQa6Test.php`: caso adicional de incertidumbre métrica tipada, desconocida y cero explícito.

No se añadió Gherkin duplicado. [Distribución de cobertura](pruebas-cobertura.md) separa los casos nuevos de las suites anteriores. Los recursos de ayuda y referencia se añadieron a la lista de archivos obligatorios del paquete.

Siguiente validación integral: el usuario ejecuta `crear-paquete-oci` completo, incluidas PHP/PostgreSQL, Behat `--profile=default --tags=@listo --strict`, Java/Vite y verificaciones del artefacto. Un fallo se investiga leyendo el código; no se elude con pruebas aisladas. Sólo su resultado correcto habilita el avance y publicación de main y un paquete del mismo commit. El posterior despliegue aplica migraciones y conserva SOURCE-METADATA/SOURCE-MANIFEST.

La aceptación visual debe repetir Q01–Q42 afectados, 28972, la hoja de 15888, los enlaces con filtros y conjuntos de UUID, el residual cartográfico, puntos próximos, errores con reintento y navegación por teclado a 333/400/1180 px. Esta lista no constituye una prueba ejecutada. **El cierre del 100 % requiere ese resultado, la evidencia del entorno publicado y las decisiones curatoriales pendientes.**

## Continuidad autorizada: operaciones directas de datos y auditoría completa

La instrucción posterior conserva esta misma rama y el trabajo pendiente. Antes de intervenir se guardó `pendientes-inicio.zip`. Los respaldos, planes, consultas, respuestas y operaciones de base de datos están fuera del repositorio, en `C:\HT\LABINVEPN\trabajo-datos-20261004`; no forman parte del paquete. Se retiraron las dos migraciones nuevas y el seeder de catálogos todavía sin registrar, previamente preservados en ese ZIP. Las migraciones históricas y las pruebas del proyecto se conservan.

Se confirmó la conexión local `hubdigital` y la conexión real de la aplicación OCI `hubdigital_pruebas`, PostgreSQL en `127.0.0.1:5432` dentro de la VM, con su rol existente `hubdigital_pruebas_app`. La configuración está en `/etc/hubdigital/hubdigital.env`; las credenciales no se incluyen en este documento ni se imprimieron. Se conservan un respaldo local completo anterior y un respaldo OCI acotado de las tablas intervenidas. La revisión automática rechazó descargar el respaldo OCI completo; el respaldo acotado se realizó correctamente.

La colección local inicialmente vacía recibió los 49.696 especímenes de OCI, sus taxones, muestras, localidades e identificadores, sin copiar cuentas. El taxón local preexistente Atta cephalotes conservó su UUID; se enlazaron las referencias equivalentes. La copia de divulgación mantiene exactamente los permisos originales: 34.699 publicados y 14.997 no publicados. La copia abortaba si un trigger alteraba cualquier permiso respecto de la fuente; se confirmó sin diferencias.

### Catálogos y localidad

En ambas bases quedaron **25 cargos activos, 106 instituciones activas y ocho entradas institucionales pendientes**, correspondientes a nueve variantes de la fuente. Las equivalencias documentadas normalizan espacios, tildes, nombres completos y formas societarias; los textos que no identifican una sola entidad no se incorporan como institución. Los originales y las referencias modificadas se guardaron en `usuarios.catalogos_normalizacion_auditoria`. Las entradas pendientes permanecen en `usuarios.catalogos_nombres_pendientes`; la pantalla de instituciones incluye su filtro y motivo. Cinco nombres cuyo texto «Compañía» quedó dañado durante la preparación se corrigieron manteniendo sus identificadores y registrando también esa reparación.

Los cargos tienen pantalla propia y selección en el formulario de depósito. Las altas futuras usan el CSV de equivalencias como referencia de lectura; no hay un seeder nuevo ni una migración de datos en el paquete. Fuentes de casos dudosos confirmados: [Pecksambiente](https://www.pecs.com.ec/politica-de-privacidad/), [registro ambiental de CGA](https://certificacionpuntoverde.ambiente.gob.ec/libraries/EAlfresco.php/?doc=97be60fa-088d-4606-be90-2966ee9b9cff) y [documentación de Ecosambito en Superintendencia de Compañías](https://mercadodevalores.supercias.gob.ec/mercadovalores/descargadorServlet.jsf?idDocumento=39619&idSeccion=GMV&idTipoDocumento=10).

Se añadieron directamente las columnas de área, cantón/parroquia y sector/ruta/vía, con indicador de desglose. El texto original `localidad`, los verbatim y las coordenadas permanecen intactos. El desglose está aplicado en local y OCI a 49.696 filas por base: 22.118 contienen un área explícita, 24.717 un territorio contrastado y 26.358 requieren revisión. No se inventa una reserva cuando la fuente no la contiene. Los textos que exceden el tamaño estructurado quedan pendientes con su fuente completa conservada.

**OCI: desglose de filas aplicado y confirmado.** La revisión automática había bloqueado esa actualización masiva; la respuesta posterior del usuario autorizó expresamente proceder y permitió completarla. La transacción guardó el respaldo por espécimen y actualizó únicamente las columnas de desglose y la fecha de modificación, conservando los campos originales. Las lecturas posteriores confirmaron 49.696 filas desglosadas, 26.358 pendientes y cero diferencias respecto de las asignaciones respaldadas. La comparación con la copia anterior confirmó cero especímenes faltantes y cero cambios en los demás campos originales. La evidencia quedó en `resultado-datos-localidades-aplicadas-oci.json` y `conservacion-datos-oci.json`, fuera del repositorio. La aplicación distingue una localidad todavía sin desglosar de las tres partes nuevas y conserva una composición compatible con la versión anterior. Las importaciones futuras preservan el verbatim y marcan el desglose incierto para revisión.

### Contraste científico de los 49.696 registros

Se consultaron por internet los **3.111 nombres efectivos distintos** presentes en todo el inventario, incluidos los registros no publicados. Se usó la API v2 de GBIF con Catalogue of Life Extended Release, checklist `7ddf754f-d193-4cc9-b351-99906754a03b`; las grafías originales se conservaron y se normalizó únicamente el texto de consulta. Las respuestas, URL, hora UTC y candidatos quedaron guardados. Una consulta fallida se reintentó y respondió. El contraste no certifica la identificación física ni que un nombre ausente de la referencia sea inexistente: [GBIF documenta las coincidencias aproximadas, a rango superior y no resueltas](https://techdocs.gbif.org/en/data-processing/taxonomy-interpretation).

Se aplicaron **95 correcciones corroboradas que afectan a 837 registros**, en las dos bases: 76 nombres de taxón cambiaron manteniendo su UUID y 19 variantes enlazaron los especímenes observados a un taxón canónico equivalente ya existente. No se borraron taxones, no se cambiaron rangos o padres y no se reescribió el historial de identificaciones. Los nombres previos, UUID anterior/destino y evidencia están en `taxonomia.nombres_cientificos_correcciones` y en la auditoría por espécimen. Ejemplos: ECtatoma ruidum → [Ectatomma ruidum](https://www.ncbi.nlm.nih.gov/Taxonomy/Browser/wwwtax.cgi?id=196312), Cyclocephala mafafa → [Cyclocephala mafaffa](https://www.gbif.org/species/4994828) y Dolichoderus dEColatus → [Dolichoderus decollatus](https://www.antweb.org/description.do?genus=dolichoderus&museumCode=USNM&rank=species&species=decollatus&subfamily=dolichoderinae).

Las sustituciones se limitaron a concordancia independiente de grafía, rango y linaje disponible. Se conservaron homónimos ambiguos, códigos locales, calificadores, variantes publicadas y combinaciones históricas. Una divergencia de familia o de rango se marca para revisión; no se transforma en una reidentificación automática.

| Resultado por espécimen | Local | OCI |
| --- | ---: | ---: |
| Concordancia documental | 38.851 | 38.851 |
| Grafía corregida | 837 | 837 |
| Sinónimo o combinación histórica conservada | 251 | 251 |
| Nombre vacío, incluido marcador de ausencia | 3.698 | 3.698 |
| Texto no científico (`N/D`) | 1 | 1 |
| Sin coincidencia suficiente | 1.485 | 1.485 |
| Posible error de escritura pendiente | 497 | 497 |
| Código local o morfoespecie | 1.577 | 1.577 |
| Identificación calificada o anotada | 597 | 597 |
| Linaje o rango por revisar | 1.902 | 1.902 |
| **Total** | **49.696** | **49.696** |

Hay 9.757 registros en los grupos pendientes; la marca no afirma que todos sean nombres inválidos. Cada fila tiene su estado, motivo, nombre original, candidato cuando corresponde, fuente y fecha en `taxonomia.revision_nombres_cientificos`. Los CSV `revision-cientifica-local.csv` y `revision-cientifica-oci.csv` conservan la relación por UUID para consulta fuera de la aplicación.

La tabla general incluye **Nombre científico** y **Revisión del nombre científico**, con columnas opcionales de motivo, nombre anterior, candidato, fuente y fecha. Curador y administrador pueden filtrar pendientes, vacíos, textos no científicos, no resueltos, corregidos y sin revisar/modificados. El filtrado se aplica en SQL a todo el inventario y conserva total y paginación. Una edición posterior del nombre, taxón o verbatim deja obsoleto el contraste anterior y devuelve la fila a revisión; no se reutiliza una aprobación antigua. Esta evidencia interna es de solo lectura y no se incorpora a la divulgación pública.

### Evidencia posterior y entrega pendiente

Las lecturas posteriores confirmaron en ambas bases 49.696 especímenes, 4.102 taxones, 49.696 auditorías científicas y cero contrastes desactualizados. La comparación de las columnas originales de cada espécimen con la copia anterior, excluyendo únicamente `taxon_id` y `updated_at`, dio **cero filas faltantes y cero cambios en los demás campos** en local y OCI. Los tres campos nuevos de localidad se revisaron separadamente contra el respaldo; el campo original conservado no cambió. Se otorgaron al rol OCI existente lectura de las marcas científicas y pendientes, y gestión del catálogo de cargos con su secuencia; las tablas de respaldo conservan restringida su escritura.

Esto es evidencia de operaciones de datos y revisión estática del código. Se escribieron cinco pruebas en `RevisionCientificaColeccionTest.php` y se documentó su distribución; no se ejecutaron. No se ejecutó la aplicación para la revisión estática ni se dispone de aceptación visual renderizada de estos controles. **El código de columnas/filtros y QA7 aún no está publicado ni desplegado**: el paquete completo sigue a cargo del usuario según `AGENTS.md`. No se ejecutó `crear-paquete-oci`, no se modificó `main` y no se publicó en ningún remoto.
