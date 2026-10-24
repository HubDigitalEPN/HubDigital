# Ajustes del catálogo solicitados el 24 de octubre de 2026

Rama: `codex/catalogo-textos-mapa-plantilla-20261024`, creada desde `main`. Los archivos pendientes de otro encargo se conservaron en el stash `resguardo-previo-catalogo-20-ajustes-20261024`.

| Puntos | Implementación |
| --- | --- |
| 1 y 5 | Administración → Textos de todas las pantallas. Inventario por pantalla, búsqueda, edición, restauración, autoría y alta de un texto visible adicional. Incluye etiquetas y mensajes de mapa y filtros. Enlaces a los editores de contenido del portal y depósitos. Los valores curatoriales y títulos de campos conservan su propio origen. |
| 2 | Ubicación geográfica al inicio de tabla y ficha. Edición de celdas por clic o Enter y edición completa mediante el formulario. La actualización de fichas históricas ya no exige inventar fecha o colector ausentes; valida los datos introducidos y conserva el alta independiente. |
| 3 | Se retiró el título visible «Ficha del registro»; permanece un nombre accesible del diálogo. |
| 4 y 12 | Filo permite ancho natural y desplazamiento horizontal. Conserva el desplazamiento al seleccionar, admite varias ramas y conserva las selecciones en la URL. Las consultas agrupan descendientes para no duplicar registros. |
| 6 | Saludo, nombre, correo y perfil a la izquierda del botón del panel del curador, según la corrección solicitada durante la revisión. «Mi cuenta» queda en el extremo derecho de la cabecera. |
| 7, 8 y 9 | Cabeceras y requisito de guía con la paleta v2: obligatorio naranja, recomendado amarillo, interno gris, predeterminado tramado y opcional blanco. Instrucciones en fila 2 de Especímenes; datos desde fila 3. La guía distingue sus recomendaciones v2 de las reglas curatoriales de importación, sin cambiar las obligaciones de depósitos. |
| 10 | Administración crea columnas adicionales desde Especímenes. Sus valores se editan y se reciben en la importación; se registra historial. Sólo se retiran columnas sin datos ni uso histórico, con bloqueo transaccional frente a escrituras concurrentes. Se conserva la identidad obligatoria y el esquema físico del catálogo. |
| 11 y 14 | Las etiquetas internas y públicas se resuelven contra los campos estables de la plantilla curatorial. La ficha respeta el idioma y la cabecera definidos allí. Se conserva el significado específico de campos derivados como Localidad INEC. |
| 13 | Acciones → Columnas permite ocultar todas las columnas y desmarcar todas las casillas. La configuración propia de Especímenes también ofrece Ocultar todas. |
| 15 y 16 | Consulta anónima de mapa, árbol, tarjetas y registros con las reglas de visibilidad existentes. Las exportaciones HTTP y Livewire requieren cuenta verificada y permiso. El administrador habilita o deshabilita descargas de autoservicio; curaduría conserva su acceso. Se ocultan las acciones de descarga de datos y fotografías a quien carece del permiso. |
| 17 | Corrección ejecutada únicamente en PostgreSQL local: 59.722 celdas, 45.305 registros. «EC» pasa a «ec» y, al inicio del contenido, a «Ec». Respaldo privado por celda en `.local/respaldos-catalogo/normalizacion-ec-20261024-192212-d04bd6db.jsonl`. La operación confirmó su transacción. |
| 18 | Controles de pantalla completa para mapas de catálogo, especie y vista clásica. El árbol conserva el arrastre de cabecera y se puede cerrar desde fuera manteniendo el estado para reabrirlo con Árbol. |
| 19 | El árbol HTTP y ETE reciben el contexto aplicado de tarjetas y mapa. Abrir un taxón en el mapa conserva los filtros anteriores. Tarjetas usa la misma selección múltiple de Filo. |
| 20 | Búsqueda parcial en campos de localidad dentro de Ejemplar y colecta. Incluye localidades desglosadas, verbatim, municipio y referencias; país y provincia respetan sus permisos de visibilidad. También se amplía la búsqueda interna. |

## Identidades conservadas para revisión

La base impide nombres y rangos duplicados. La primera corrección se revirtió por esa restricción; la operación confirmada conservó estas tres celdas y corrigió las demás:

| Original | Nombre que ya existe | Situación |
| --- | --- | --- |
| Acanthognathus teledECtus | Acanthognathus teledectus | Mismo padre; dos UUID diferentes. |
| ECtatomminae | Ectatomminae | Mismo padre; dos UUID diferentes. |
| ErEChtia | Erechtia | Padres distintos; requiere criterio curatorial. |

El informe de identidades está en `.local/respaldos-catalogo/conflictos-ec.json`. No se eliminaron ni unificaron taxones. El usuario confirmó conservar las tres fichas para revisión curatorial y corregir el resto.

## Revisión visual local

Se revisaron estáticamente contratos, permisos, rutas, persistencia, importación y cambios de visibilidad. Por solicitud expresa del usuario se recorrieron las pantallas afectadas en Opera Neon, perfil dedicado de QA, localhost, zoom inicial 75 %. Se reutilizó la pestaña de QA y se mantuvieron intactas las pestañas personales. Se revisó escritorio de 1707 × 786 y emulación móvil de 390 × 844. El cambio de tamaño nativo no estuvo soportado por Neon; la emulación de viewport del mismo MCP sí funcionó.

| Puntos | Evidencia del recorrido |
| --- | --- |
| 1, 5 | Editor bajo Administración; filtro «Catálogo público y mapa», búsqueda y formulario. Se guardó un título temporal, se observó en el mapa y se restauró el original. Se retiraron fragmentos de código del inventario y se cambiaron las rutas técnicas del selector por nombres legibles. |
| 2 | Geografía al inicio de tabla y formulario de Especímenes. Apertura de edición por clic y guardado de los valores existentes con mensaje «Especímen actualizado correctamente». El caso de ficha histórica sin fecha/colector queda en las pruebas automatizadas pendientes. |
| 3, 11, 14 | Fichas y tabla usan cabeceras curatoriales como eventDate, recordedBy y localityName. «Detalle del espécimen» conserva clase sr-only; no aparece «Ficha del registro» como título visible. |
| 4, 12 | Filo mostró desplazamiento horizontal y vertical; conservó scrollTop 120 y scrollLeft aproximadamente 15 al seleccionar. Clitellata y Stylommatophora quedaron seleccionados a la vez, con 343 registros. |
| 6 | Capturas de saludo, correo y perfil seguidos por Panel del curador. Mi cuenta alineado al borde derecho interior de la cabecera, sin el hueco del contenedor móvil vacío. |
| 7, 8, 9 | Descarga real de plantilla con respuesta 200. Se revisaron valores y rellenos del archivo descargado y de la madre v2 en una vista temporal de sólo lectura servida en loopback, con Opera Neon. Naranja, amarillo, gris y tramado en cabeceras/Guía; instrucciones en fila 2. Esta vista no certifica la distribución exacta dentro de Excel. No se modificó el archivo descargado ni la referencia. |
| 10 | Se creó «QA visual temporal 24oct» y se eliminó sin uso; no quedó en la plantilla. Se corrigió una directiva @js que llegaba sin compilar al botón de eliminación. La revisión automática de aprobación rechazó pulsar Eliminar sobre localityName por riesgo de afectar una columna utilizada; se descartó el diálogo pendiente. Esa protección sólo se revisó estáticamente y queda sin prueba visual destructiva. |
| 13 | Acciones → Columnas → Ocultar todas dejó cero de las 26 cabeceras públicas visibles; se restauró la vista. En Especímenes quedaron sólo las columnas operativas Seleccionar, número y Acciones, con cero campos marcados; se restauraron las columnas por defecto. |
| 15, 16 | Lectura anónima en mapa, árbol, tarjetas y registros sin opciones de exportación en los menús revisados. Se deshabilitó el flag desde Administración y se comprobó persistencia al recargar y ausencia de exportaciones en la cuenta local de depositante. Al habilitarlo, esa cuenta vio exportaciones y descargó CSV de cuatro registros con respuesta 200. El flag quedó habilitado, su estado inicial. |
| 17 | Se observaron muestras corregidas como «referencia INec cercana» en fichas y registros. Las cantidades de la corrección provienen de la transacción ya ejecutada; el recorrido no vuelve a recorrer visualmente toda la base. Las tres identidades conflictivas siguen conservadas. |
| 18 | Pantalla completa y salida en mapa principal y mapa de especie. La ruta clásica de estadísticas redirige al mapa principal. El modal de árbol se arrastró, cerró desde fuera y reabrió conservando posición; también se revisó en móvil sin desbordamiento horizontal. |
| 19, 20 | La búsqueda de localidad Yasuní conservó 15.383 registros al alternar tarjetas, registros y mapa. El árbol recibió ambos filos seleccionados. Buscar Acanthinomyia en tarjetas y abrir Árbol conservó el mismo registro y su clasificación. |

Durante el recorrido se corrigió también la descarga XLSX: getComment pertenece a Worksheet, no a Cell. Las comprobaciones finales de consola en las pantallas revisadas no mostraron errores; los fallos 500 y Alpine anteriores se identificaron y corrigieron, no se cuentan como pruebas aprobadas.

## Preparación local y validación integral

Se habilitaron las tres migraciones nuevas y la migración previa de configuración de plantilla del 21 de octubre que faltaba en la base local hubdigital. Fueron aplicaciones de esquema para habilitar las pantallas, no una ejecución de las suites de migraciones. Se inició Vite en modo desarrollo en localhost:5173 para servir el código actual durante la revisión. La vista temporal del XLSX usó localhost:8002 y se detuvo al terminar.

El usuario autorizó ejecutar `crear-paquete-oci -SoloValidacionLocal`, corregir fallos y reintentar siempre el comando completo. La validación local terminó correctamente el 24 de octubre de 2026; los registros de cada intento se conservan en `.local/deployment/validacion-local-catalogo-*.log`. Se corrigieron espacios finales y se actualizó el adaptador Leaflet de Node para ejecutar los inicializadores del control de pantalla completa. Los contratos de navegador distinguen ahora lectura pública de descargas y verifican el cierre exterior del árbol solicitado. No se ejecutaron suites sueltas. Esta modalidad terminó antes de commit, push y empaquetado, sin validación remota ni despliegue.

El recorrido del paquete detectó que, en un linaje filtrado, el zoom máximo podía descartar la única especie por quedar detrás de la cámara y ampliar un espacio vacío. Se corrigió la selección de respaldo para centrar la especie real más próxima al objetivo cuando ninguna especie está dentro del recorte. Se conserva la aserción de navegador que exige taxones visibles durante todo el acercamiento y especies visibles en el límite.

El siguiente intento aprobó ese acercamiento y detectó una comprobación de rango que se adelantaba al final de la animación de cámara. El reposo del recorrido espera ahora el estado real de animación terminada antes de comprobar sus resultados, sin ampliar pausas fijas ni modificar las aserciones. El estado de esta sección corresponde a la preparación de los reintentos; el resultado definitivo se consulta en el registro local de la última ejecución.

Con la animación terminada se confirmó un segundo problema de encuadre en linajes filtrados: la leyenda habilitaba el rango correcto, pero su yema podía quedar fuera de pantalla. La selección explícita de rango centra ahora una yema de ese nivel, conservando el taxón raíz, el zoom físico y los límites gráficos. Las aserciones de Suborden/Familia permanecen intactas.

El intento de las 16:56 aprobó el recorrido completo de Opera Neon, Java, ETE, frontend, Node, Gherkin y PDF. PHP terminó con 1.621 casos aprobados y siete expectativas antiguas de presentación/acceso fallidas. Se actualizaron esas expectativas según la consulta pública, la restricción de descargas y las cabeceras de la plantilla madre, conservando las comprobaciones de privacidad, datos originales y formatos.

El siguiente recorrido completo terminó con código 0: 1.629 pruebas PHP y 10.400 aserciones, 246 contratos JavaScript, 83 escenarios Gherkin con 489 pasos y 11 pruebas ETE aprobados. También aprobó firma Java, compilación principal y de los tres módulos, recorrido real en Opera Neon, PDF e identidad de los fuentes durante la validación. La creación y comprobación de firma tomó 561 ms, dentro del máximo de tres segundos. Evidencia: `.local/deployment/validacion-local-catalogo-20261024-171854.log`. Esta aprobación corresponde a localhost; la publicación y el empaquetado requieren la modalidad completa autorizada por el usuario.

El control de exportaciones bloquea los mecanismos de descarga de la aplicación. Mostrar datos públicamente implica entregarlos al navegador: no permite garantizar que un visitante o una IA no copie o extraiga la información visible. Una prohibición absoluta de extracción sería incompatible con la consulta pública solicitada.
