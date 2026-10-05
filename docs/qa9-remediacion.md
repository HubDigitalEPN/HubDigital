# Remediación de revisión 9 y fallo del paquete — 5 de octubre de 2026

Fuente recibida: `C:\HT\LABINVEPN\rev 9\qa_portal_parte9.md`. El informe observa el commit `b6371432676674b11cd288f2e63d4a73610e9702`; no describe todos los cambios pendientes en la rama `fix/portal-remediacion-qa8-20261005`. Se continúa en esta misma rama por instrucción del usuario, preservando el trabajo previo. Los anexos enlazados por el informe no están presentes en esa carpeta; no se presentan como examinados.

## Prioridad del contrato de producto

La petición del usuario prevalece sobre las recomendaciones históricas: no se restablecen Excluir provincia, selector de coordenadas y Abrir ubicación, Aplicar filtros, Mostrar todos los filos, botón del diccionario en la barra de vistas, ni paginación del árbol. Tampoco se restituyen los textos retirados. La limpieza permanece como **Limpiar Filtros**, los criterios cambian automáticamente y las opciones provinciales/localidades dependen de los datos públicos de la selección. La fotografía única y la referencia generada de Nematomorpha conservan su procedencia explícita.

El contador junto a Colección Biológica sigue expresando **registros**: cuenta entradas públicas, incluidas determinaciones hasta familia/filo, y no equivale al número de especies distintas. Los paneles provinciales y temporales incluyen esos registros; el campo agregado de especies mantiene sus requisitos de identificación pública confirmada.

## Fallo adjunto

La salida aportada muestra **1.241 pruebas Pest/PostgreSQL aprobadas, 7.103 aserciones y 477,55 segundos**, cuatro compilaciones Vite completadas y **56 contratos Node aprobados / uno fallido de 57**. El paquete se detuvo en `portal-dashboard-actions.test.mjs`, caso de ubicaciones solapadas accesibles por teclado.

El agrupador ordena los puntos por coordenadas. La prueba asociaba el marcador en posición cero con la entrada en posición cero, aunque ese orden no era parte del contrato. Se corrige la asociación por el par original de coordenadas y se exige el multiconjunto completo de aperturas, sus cantidades y la apertura por teclado tras filtrar. Se mantiene la ausencia del selector retirado. No se cambian el orden productivo, las coordenadas, las cantidades ni las aserciones de identidad para eludir el fallo.

## Hallazgos y cambios

| Hallazgo | Tratamiento vigente |
| --- | --- |
| H01 — exclusión provincial coloquial | Se reconoce «No quiero Orellana», «otras/demás provincias» y «resto de provincias» antes de las consultas y la ayuda. Se explica que la función fue retirada y no se cuentan ni enlazan conjuntos parciales. Se conservan las pruebas positivas de inclusión. La recomendación histórica de implementar la exclusión no aplica. |
| H02 — referencias interpretadas como taxones | «Para estos dos caracoles», «esta página» y «selección actual/aplicada» se resuelven sobre la selección pública. Las palabras de referencia no se convierten en taxones. Conteo, XLSX y mapa conservan los criterios completos, incluidos los límites espaciales. La consulta anterior ajena no contamina el resultado. Los ordinales de una lista anterior de códigos conservan su contrato. |
| H03 — aclaración innecesaria y pérdida de continuidad | Los criterios nombrados se comparan con los aplicados; coincidir no dispara una aclaración. Una discrepancia ofrece dos respuestas locales y conserva la petición original en sesión durante el mismo plazo de 30 minutos. La elección retoma conteo/descarga/mapa con el alcance elegido y limpia la petición pendiente. Nueva conversación elimina el contexto. «La selección aplicada de esta página» no se envía a fuentes generales. |
| H04 — menú sin depósito | Trámites y Depósitos y préstamos ofrecen ambas vías antes de la ayuda individual. Los destinos y pasos distinguen préstamo de depósito/donación. La guía pública incorpora procedencia lícita, datos MEPN guiados, documentos según el caso, revisión de curaduría y espera de instrucciones antes de trasladar material. |
| H05 — estado físico en occurrenceStatus | Se versiona el perfil de exportación a 3.0. La detección y el valor original se separan en XLSX; ficha y tabla identifican el campo fuente como Estado original. No se cambia la información curatorial ni se infiere detección de un estado físico. Detalle del contrato debajo. |
| H06 — propósito de registro desde Depósitos | El CTA lleva `rol=DEPOSITANTE`; registro acepta solo los dos propósitos públicos, permite cambiarlos y prioriza la elección previa de un formulario con errores. La entrada general/préstamo mantiene PRESTAMISTA. La selección inicial no otorga un rol interno ni sustituye la validación Fortify/Turnstile. |
| H07 — taxones terminales sin salida | Las tarjetas de taxones superiores con registros públicos ofrecen Ver registros de este taxón. Usa la transición existente, conservando taxón, filtros y acceso por UUID a la ficha. No crea una especie para un registro identificado hasta género o filo. |
| H08 — contraste del botón del diccionario | No aplica: el usuario ordenó retirar ese botón. La barra de vistas ocupa su propia fila, evitando que flote junto a títulos; el contador/descarga de especie permite ajuste de línea y el botón no se comprime. La ruta del diccionario sigue disponible desde las ayudas. |
| H09 — residual subpíxel | Los círculos e iconos originales usan proyección fraccionaria. Se elimina también el redondeo heredado de Marker durante `zoomanim`, identificado por lectura del Leaflet instalado. Se conserva WGS84 y el anclaje; no se aplica un desplazamiento artificial. Se prepara un contrato específico para zoom de ida y vuelta. La aceptación geométrica en ventanas reales sigue sin verificarse. |

Las variantes «ke bichos ai aki» y «cuantas espesies ai aki» reciben respectivamente una aclaración sobre registros/especies y el conteo de especies de la selección aplicada. No se promete soporte multilingüe ni se sustituyen fuentes generales por consultas científicas.

## Decisión de modelado para el perfil 3.0

La [lista de términos TDWG vigente](https://dwc.tdwg.org/list/#dwc_occurrenceStatus), versión 2026-05-26, distingue detección durante un evento de [disposición del material](https://dwc.tdwg.org/list/#dwc_disposition). Se utiliza este criterio exclusivamente para el contrato exportado, sin una migración curatorial:

- `occurrenceStatus`: `present`/`detected` → `detected`; `absent`/`notDetected` → `notDetected`, comparando sin diferencias de mayúsculas y espacios exteriores. Otros valores, incluido `destroyed`, `loaned`, `in_collection`, desconocidos y nulos, dejan la celda vacía. No se deduce ausencia ni detección de la existencia de una fila, su disposición o una nota de destrucción.
- `occurrenceStatusVerbatim`: extensión local nueva con el valor original completo, sin traducir ni corregir; comparte exactamente la barrera `occurrenceStatusVisible` con la detección.
- `disposition`, `specimenNotes`, `typeStatus` y sus permisos permanecen independientes. Por ejemplo, `destroyed` y `in_collection` se conservan en sus campos fuente, sin corregir automáticamente una contradicción que requiere revisión curatorial.
- XLSX pasa de 26 a 27 columnas; las coordenadas siguen en H/I. El campo nuevo se añade antes de `exportProfile`. CSV conserva sus 15 columnas y publica el identificador 3.0. El diccionario y el contrato de encabezados existente se actualizan juntos; los consumidores deben usar los encabezados y la versión declarada.
- El proveedor ya no sustituye un estado nulo por `present`. El DTO conserva un texto vacío, y la exportación mantiene desconocida la detección.

Esta decisión corrige la mezcla de significados; no acredita el estado físico actual de MEPN-INV-47554 ni resuelve físicamente la cola taxonómica de once etiquetas/146 registros mencionada por QA9. Esas revisiones conservan su carácter curatorial.

## Validación y publicación

**Revisado por el agente:** código, diffs, firmas y colaboradores, flujo de selección, contextos, rutas, permisos de divulgación, esquema de exportación, conservación de originales, estados de error, etiquetas y estructura accesible. Se contrastó la cobertura con el script de `crear-paquete-oci`; no se lanzó la aplicación ni una comprobación automatizada cubierta por el paquete.

**Ejecutado por el usuario:** únicamente la evidencia del paquete adjunto descrita arriba. Sus resultados corresponden al estado previo a las correcciones nuevas; no prueban que estas hayan pasado. No hay evidencia de que el paquete haya terminado o publicado este trabajo.

**Preparado para el siguiente paquete:** `PortalQa9RemediacionTest`, ampliaciones de `RegistrationTest` y del contrato XLSX, proveedor sin detección inferida y Node de teclado/zoom. Se conserva y actualiza el encabezado del escenario XLSX existente; no se añaden escenarios Gherkin duplicados. Distribución en [pruebas-cobertura.md](pruebas-cobertura.md).

**Pendiente:** ejecución completa de `crear-paquete-oci` por el usuario. La comprobación visual fue cancelada expresamente: no se certifican medidas geométricas, contraste renderizado, foco real, compatibilidad ni tiempos. Los NV de PWA, autenticación/autorización entre identidades, cabeceras y curación de registros requieren su propio entorno/evidencia; no se convierten en aprobados mediante lectura de código.

No se ejecutó el paquete, no se publicaron commits ni se desplegó una release. El cierre validado depende de las comprobaciones centralizadas y de la evidencia externa que corresponda.

## Cuarto resultado del paquete y corrección estática

Los adjuntos `3ab7bcd0-355c-4212-9e33-5478a30b5325`, `4c7aaad1-9b90-4c84-a5d8-51cbc55f537b`, `966c8cca-281e-4f1f-b25e-c0ca5faf1656` y `1469e36d-b714-4d13-af33-87c44eb33396` contienen fragmentos del resultado siguiente. Su resumen informa **1.258 pruebas aprobadas, cuatro fallidas, 7.338 aserciones y 497,43 segundos**. El paquete se detuvo en PostgreSQL; esta ejecución no acredita las etapas posteriores ni la creación del paquete.

| Casos fallidos | Causa y corrección |
| --- | --- |
| QA7 005/006, comparación de condición de tipo y disposición | La ampliación del glosario explicaba los significados pero había omitido la respuesta explícita «son campos distintos». Se restituye esa afirmación y se conservan las explicaciones de detección, estado original, vacíos e incertidumbre. La aserción existente permanece intacta. |
| QA9 H03, elección de una consulta nueva sin resultados | La rama de cero registros retornaba siempre `catalogo.count` y omitía el conteo de especies pedido. Ahora responde «Hay 0 especies publicadas» y `catalogo.species` cuando corresponde; mantiene los filtros, las sugerencias y la advertencia de que el resultado público no acredita ausencia biológica. Se conserva el contrato de consultas por registros. El caso compuesto exige también las tres acciones y el total numérico según la elección. |
| QA9 H07, taxones terminales género y filo | La preparación asignaba `publicado=false` directamente, pero el disparador `divulgable_filo_publicacion` recalcula ese campo a partir del linaje. Se retira `taxon_id` en la colección sintética, se exige que el disparador deje el registro no publicado y se conserva el ejemplar fuente. Se mantiene la aserción de ausencia del botón y se añaden tabla/conteo vacíos y recuperación del acceso al restablecer la identificación. No se modifica el disparador ni se desactiva la regla de publicación. |

Se revisaron estáticamente la selección pública, la rama de conteo vacío, el glosario y los disparadores de publicación. No se ejecutaron suites, comprobaciones aisladas, la aplicación ni un navegador. La corrección de los cuatro casos y las etapas restantes requieren la siguiente ejecución completa de `crear-paquete-oci` por el usuario. Las eliminaciones solicitadas siguen vigentes.
