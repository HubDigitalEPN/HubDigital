# Plan y contraste del portal: cartografía y QA3

Solicitud del 2 de octubre de 2026. Base: `main`, rama `feat/portal-cartografia-qa3-20261002`.
El informe `qa_portal_parte3.md` es evidencia de la revisión de Dot del 1 de octubre; sus hipótesis se contrastan con el código. Las instrucciones de esta tarea prevalecen: la primera revisión de Codex es estática y las suites automatizadas se ejecutan únicamente en `crear-paquete-oci`. La ampliación solicitada por el usuario requiere, después de crear el paquete, una segunda revisión de los diez puntos y navegación real en Chrome o Edge visible, con corrección de los defectos encontrados y nuevo paquete si cambia el código. Dot conserva su revisión independiente del despliegue.

## Plan de acción y causas contrastadas

| Prioridad / evidencia | Causa o contrato observado en código | Cambio |
| --- | --- | --- |
| QA3-001 | Las tarjetas resolvían ejemplares por una etiqueta de catálogo no única. | Recuperar los UUID de la selección pública y paginar antes de materializar DTO. Conservar códigos y registros originales. Las fotos heredadas de códigos ambiguos se conservan para revisión y no se atribuyen a otro ejemplar. |
| QA3-002 | La exclusión de marcadores curatoriales no reconocía notas de reubicación. | Excluir esas notas de riqueza/taxonomía/geografía científica sin borrar la fila original ni inventar nombres. |
| QA3-003 y 005 | El detector extraía provincia y localidad homónimas independientemente; las comparaciones geográficas conservaban diferencias por acento. | Provincia/país no agregan localidad implícita. Normalizar las comparaciones sin modificar el dato fuente. |
| QA3-004 | El normalizador eliminaba guiones ISO y el detector solo reconocía meses/años. | Parser de límites diarios e ISO, intervalos y fechas válidas; pedir corrección cuando no pueda interpretar íntegramente el intervalo. |
| QA3-006 y 007 | Altitud, sexo, preservación y alternativas podían perder condiciones. | Aplicar elevación disponible; aclarar condiciones no soportadas y disyunciones antes de ofrecer un total parcial. |
| QA3-008 y 012 | Referencias estrechas y ayudas posteriores a reutilizar contexto científico. | Referencias ordinales a códigos, agregar/quitar mes, cambios de intención y respuestas concretas para acceso público, CSV y métricas. |
| QA3-009 | Ayuda de un único filo en un contenedor sin aislamiento del foco. | Ayuda contextual en cada tarjeta y diálogo nativo, cierre/Escape y devolución de foco. |
| QA3-010 | Metadatos en columnas y tamaños dependientes del viewport, no del ancho de ficha. | Adaptar al contenedor y permitir valores completos sin solapamiento. |
| QA3-011 | Propósito en botones sin estado de selección semántico. | Radios nativos excluyentes con `fieldset/legend` y restauración de selección. |
| QA3-013 y 014 | Toggle Flux en inglés y título de recuperación sin h1. | Control español con campo/estado identificable y encabezado principal semántico. |
| QA3-015 y 016 | Área invisible del contenedor del chat capturaba clics; cabecera distribuía todo en una fila estrecha. | Solo panel/trigger capturan puntero; acciones de cabecera en otra fila. |
| QA3-017 | Escape y fullscreen podían cerrar dos capas. | Priorizar diálogo superior y conservar maximización del mapa de fondo. |
| Capturas de mapa | Leaflet ya era cartográfico, pero los datos se redondeaban a 0,25° y una máscara oscurecía zonas sin registros. | Marcadores azules en latitud/longitud públicas originales; agrupar solo coordenadas coincidentes; conservar cartografía visible y exportar puntos WGS84 exactos. |
| Métodos de muestreo | La fuente Excel contiene `samplingProtocol`; el importador no preservaba ese campo. | Conservar protocolo original por espécimen y recuperar fuente usando identidad de fila, occurrence y código fuente, con procesamiento acotado. |
| Apertura, navegación y VM | Hidratar todos los ejemplares al construir tarjetas y repetir agregaciones aumenta trabajo/memoria. | Resúmenes SQL, consultas acotadas, 12 filas por página, apertura inmediata con estado de carga e índices selectivos. No particionar una colección pequeña sin evidencia de necesidad. |

## Representación científica

Taxón significa cualquier rango con un nombre científico. El gráfico solicitado es un árbol de clasificación taxonómica derivado de la base, no una genealogía evolutiva inferida. Cada especie muestra los datos publicados de sus propios ejemplares y el linaje disponible. El círculo indica cantidad de registros, no abundancia natural.

Los puntos mantienen la coordenada almacenada y su referencia de precisión. Situarlos exactamente no transforma una georreferencia territorial estimada en una localización GPS verificada. Se conservan las reglas de publicación y los campos reservados.

Las fotografías publicadas tienen prioridad en el mosaico. Las ilustraciones educativas son representativas de grupos y se identifican explícitamente: no documentan un ejemplar ni permiten identificar una especie. Todas las especies pueden resolver un recurso por su linaje; cuando no hay morfología compatible conocida se muestra un diagrama neutral. Esta entrega no inventa dibujos diagnósticos únicos para las aproximadamente 1.800 especies ni descripciones inexistentes en la base.

## Ilustraciones y procedencia

Se aplicó [imagegen](C:/Users/Usuario/.codex/skills/.system/imagegen/SKILL.md) con la herramienta integrada, sin llamadas API desde la aplicación. Recursos finales: `public/images/taxonomia/`. Dieciséis dibujos de grupos y cuatro variantes de hormigas, 320 × 320 px, WebP de aproximadamente 3–12 KB por archivo. Se solicitó un formato de poco peso como AVIF; el entorno no tiene codificador AVIF y se utilizó WebP. La VM sirve archivos estáticos compartidos, sin generar imágenes por solicitud ni cargar un modelo.

Prompts finales:

- Atlas científico en cuadrícula regular 4 × 4, un animal completo por celda sobre fondo blanco cálido, sin texto ni líneas, ilustración a tinta/lápiz de color de anatomía plausible: hormiga, coleóptero, mariposa, mosca, saltamontes, libélula, insecto palo, hemíptero, araña, escorpión, cangrejo, isópodo, caracol, anélido, nemátodo y diplópodo. Representantes genéricos, sin atribución a una especie científica determinada.
- Atlas Formicidae en cuadrícula 2 × 2, cuatro obreras genéricas completas: rojiza de perfil, pequeña oscura oblicua, esbelta ocre de perfil y robusta negra de perfil. Fondo blanco cálido, seis patas, antenas acodadas y cintura segmentada, estilo científico a tinta/lápiz de color. Sin etiquetas, fotografías simuladas ni atribución de especie.

Los originales generados se conservaron fuera del repositorio. Los archivos publicados son derivados acotados para la interfaz. La revisión taxonómica especializada de estas representaciones queda disponible para curaduría.

La petición posterior del usuario reemplaza esos dibujos por **20 representaciones fotorrealistas generadas**, de 320 × 320 px y 162.154 bytes en conjunto. Las fotografías auténticas de la colección siguen teniendo prioridad. Los WebP se sirven con una versión fija en la URL para que la caché no conserve los dibujos anteriores. Se inspeccionaron todos los recursos y se corrigieron cinco recortes mediante `image_gen`. La procedencia, referencias, prompts e inventario definitivos constan en [recursos fotorrealistas](recursos-taxonomicos-fotorrealistas.md); los prompts de tinta anteriores se conservan aquí como historia de la primera entrega.

## Validación y publicación

Primera revisión propia: lectura de código, contratos, permisos, consultas, rutas, identidad, errores y efectos del diff, sin navegador. Después del primer paquete se realiza la revisión adicional con navegador visible solicitada por el usuario. No se ejecutan suites aisladas. Las pruebas nuevas se conservan en Pest y Node y se ejecutan únicamente dentro de `crear-paquete-oci`. Los escenarios Behat existentes siguen con `--profile=default --tags=@listo --strict`; no se duplican los casos nuevos de Pest en Gherkin.

El paquete debe terminar todas sus etapas antes de registrar y publicar el commit en `origin/main`. La identidad del commit y huellas se incorporan al artefacto. Crear el paquete no despliega una release; OCI aplica las migraciones antes de activarla. Los resultados de navegador describen los recorridos observados y no certifican ausencia general de regresiones ni la capacidad de una VM de 1 GB.

## Segunda revisión solicitada después del primer paquete

El primer paquete terminó correctamente y publicó `7a4dfed3a09a65ace320c3d7c060640bd9719594`: 931 pruebas Pest, 3.404 aserciones, 83 escenarios Behat / 489 pasos y cinco contratos Node, además de las compilaciones y verificaciones del comando. Artefacto inicial: `portal-cartografico-qa3-y-muestreo-20261002-115529`. Se preserva ese resultado y se desarrolla la segunda revisión en `feat/portal-revalidacion-qa3-20261002`, basada en ese `main`.

La revisión adicional contrasta los diez puntos funcionales y la generación del paquete. Se amplió a todas las opciones del portal de Colección Biológica por petición posterior del usuario. Chrome se controla directamente por pestaña en segundo plano, sin enviar entradas al ratón o teclado del sistema ni traer la ventana al frente.

| Punto solicitado | Evidencia y revisión adicional |
| --- | --- |
| 1. Informe y plan | Los 17 hallazgos QA3 se contrastan arriba. Se revisan también identidad de fotos, exportaciones y contexto de selección del asistente. |
| 2. Cartografía real | Posiciones originales WGS84 y agrupación solo por coincidencia exacta. Se revisa la renovación del mapa de especie cuando cambia la selección. |
| 3. Cartografía sin máscara y mosaico | Base geográfica íntegra, marcadores azules y radio por cantidad. Mosaico por linaje público, con fotos originales prioritarias y cuatro variantes de Formicidae como representación orientativa. |
| 4. Muestreo | Protocolo original recuperable de la fuente y consulta con permisos. El dato original prevalece sobre una muestra vinculada. |
| 5. Ayudas de tarjetas | Todas las tarjetas y niveles tienen ayuda contextual. El linaje de tarjetas del explorador debe proceder de sus ramas públicas, aunque no haya un taxón navegado. |
| 6. Modal y gráfico por especie | Apertura inmediata con carga/error, dendrograma e información a la derecha. Representación individual con nombre propio, retrato amplio y clasificación pública legible; imagen orientativa compartida del grupo para mantener poco peso. No representa anatomía diagnóstica verificada de cada especie. |
| 7. Velocidad y paginación | Consultas SQL acotadas, índices y caché invalidable; 12 resultados por página. Revisión de ramas con permisos mixtos, portadas/galerías acotadas y exportación de la selección efectiva. |
| 8. VM de 1 GB | Sin modelos en ejecución, ilustraciones estáticas compartidas, recuperación de fuente por bloques y sin particionado innecesario. La revisión local no es una prueba de carga de esa VM. |
| 9. Separación de validaciones | Suites únicamente en el paquete. Navegación adicional autorizada después de la primera ejecución completa; se concentra en presentación, accesibilidad y coordinación entre controles. Dot conserva revisión independiente del despliegue. |
| 10. Cobertura del comando | Pest y Node incorporan los contratos nuevos; Behat conserva los escenarios activos sin duplicar casos equivalentes. Los archivos nuevos de selección y representación son obligatorios en el artefacto. |
| Ejecución y reintentos | El primer fallo de Pest se corrigió sin reducir aserciones y se reejecutó el comando completo hasta crear el paquete. Los cambios posteriores deben validarse y empaquetarse con una nueva ejecución completa. |

La base local de revisión inicialmente no tenía ejemplares públicos; el sitio remoto conserva el despliegue anterior. La navegación de la versión modificada utiliza el servidor local y una muestra temporal procedente de la fuente, con identidad independiente y limpieza acotada. El tamaño y los permisos de esa muestra no reproducen toda la colección desplegada.

### Posición cartográfica observada en Chrome

Se prepararon 22 filas reales de cuatro especies en `hubdigital_ui_20261002`, copia local independiente; no se alteraron la base `hubdigital` ni el portal remoto. Se contrastó el centro de cada marcador SVG con la proyección EPSG:3857 de Leaflet sobre una tesela cargada de OpenStreetMap (`10/294/513.png`). Las posiciones conservan los tres pares originales de la muestra:

| Latitud | Longitud | Registros | Diferencia horizontal / vertical de representación |
| --- | --- | --- | --- |
| −0,658 | −76,452 | 12 | −0,347 / −0,149 píxeles |
| −0,63194 | −76,14416 | 8 | +0,496 / −0,172 píxeles |
| −0,413 | −76,013 | 2 | −0,009 / +0,258 píxeles |

La diferencia inferior a medio píxel corresponde al redondeo de la posición dibujada de Leaflet y no a un desplazamiento de las coordenadas. Los tres marcadores observados usan `#17699b`. El código no genera ubicaciones, aplica dispersión aleatoria ni redondea a centros de cuadrícula. La revisión visual de esta muestra complementa los contratos del paquete; no valida la calidad del levantamiento geográfico original de toda la colección ni equivale a navegar una release nueva ya activada en OCI.

Las siete ayudas de indicadores se abrieron en Chrome con imágenes cargadas. Se recorrieron por teclado los menús (flechas, Inicio/Fin y Enter), el cierre con Escape y la devolución del foco. En el modal se recorrió el linaje completo Animalia → Arthropoda → Insecta → Hymenoptera → Apocrita → Formicidae → Ponerinae → Ponerini → Neoponera → Neoponera carinulata, con el árbol gráfico a la izquierda y representación/metadatos reales a la derecha. El protocolo `fogging` aparece tanto en el panel de muestreo como en la ficha del ejemplar.

La revisión estática posterior detectó que la navegación lateral recibía únicamente los descendientes del taxón seleccionado y omitía sus hermanos. El rail ahora consulta por separado el contexto del padre público, conservando los filtros y permisos efectivos; muestra los conteos propios de cada rama y permite paginar doce alternativas por página. La selección, los UUID y la paginación de los registros principales permanecen independientes. El nuevo caso Pest conserva esa separación y la exclusión de ramas reservadas; se valida únicamente dentro del paquete completo.

La aclaración posterior solicita un dendrograma con vista de conjunto desde la apertura del punto. El grafo reúne hasta doce hojas terminales reales y todos sus ancestros publicados por página, sin consultas adicionales para construirlo ni hidratar la colección. Reutiliza los nodos agregados del punto, preservando las variantes de padre de un mismo UUID según permisos. Seleccionar una hoja mantiene el conjunto visible y carga únicamente sus datos propios a la derecha; la página del árbol y la de registros son independientes. Un punto de una sola especie conserva una cadena única: la aplicación no inventa bifurcaciones, rangos ni relaciones evolutivas. Los casos nuevos de Pest cubren ese contrato y la paginación/reinicio del conjunto dentro del comando centralizado.

La tabla de registros mostró 12 filas iniciales y diez en la segunda página, con Siguiente deshabilitado al final. En viewport móvil real de la emulación (390 × 844 CSS px), el documento conserva ancho de 390 px y la tabla desplaza sus columnas dentro de su propio contenedor. Se incorporó un aviso visible de desplazamiento y acceso al contenedor por teclado. La revisión de autenticación comprobó selección excluyente de propósito con teclado, nombre/estado del botón de contraseña y formulario de recuperación; no envió cuentas, correos ni desafíos CAPTCHA.

El recorrido detectó una pérdida de localidad original al importar una celda `verbatimLocality` vacía junto a `localityName` informado: el mapper tomaba la alternativa vacía y anulaba el fallback. Se corrige la elección del primer valor no vacío. Una migración nueva recupera solo `localidad_verbatim` ausente desde 35.251 localidades de la fuente, con coincidencia exacta de fila, occurrence y old_code (incluyendo NULL conocido), en lotes de 500. No sustituye texto corregido, permisos, métodos ni coordenadas. La muestra visual se completa por sus 22 UUID en su base aislada. La recuperación histórica se aplica al desplegar en OCI, después de validar el paquete.

En una apertura manual local, con viewport observado de 1280 × 900 CSS px, un observador del atributo `open` del diálogo midió 46,5 ms desde el evento real del clic. Chrome mostró el diálogo y su estado de carga antes de recibir el árbol. Escape devolvió el foco al marcador. Esta observación acotada no mide la consulta completa, percentiles, una primera carga ni el rendimiento de OCI.

Se revisaron también Inicio y Depósitos en escritorio y a 390 × 844 CSS px. El menú móvil abrió por teclado, expresó su estado y cerró con Escape devolviendo el foco. Los intentos de clic de la herramienta en emulación táctil no entregaron un evento al documento; se excluyen de la evaluación funcional. No se envió ningún formulario ni correo.

La auditoría estática final encontró que el resumen de descendientes del taxón seleccionado no podía servir también para buscar sus hermanos. Se detuvo el intento de paquete antes de publicación y se corrigió ese contexto de navegación por separado, conservando filtros, permisos y selección principal. Los enlaces laterales requieren conteos propios y paginación independiente; las nuevas pruebas forman parte de la ejecución integral siguiente.

La captura adicional del requirente muestra una alineación artificial coherente con el cálculo antiguo `ROUND((latitud * 4)::numeric) / 4` y su equivalente de longitud, confirmado al leer la versión anterior. El cálculo actual agrupa los valores originales exactos y no contiene ese redondeo. La cartografía conserva accidentes geográficos, reservas y ciudades. Tampoco se desplazan puntos para mejorar su apariencia: si la fuente contiene posiciones coincidentes o alineadas, se representan así y se conserva su precisión declarada.

El recorrido adicional corrigió el foco de Añadir/Quitar localidad: el contexto Alpine del evento podía ser el botón, por lo que buscar sus inputs descendientes no encontraba campos. La búsqueda ahora parte del fieldset de localidades. Chrome confirmó que Añadir enfoca Localidad 2 y Quitar esa fila enfoca Localidad 1; la selección aplicada no cambia hasta solicitar Aplicar filtros.

Se contrastaron los límites existentes de despliegue: PHP tiene `memory_limit=192M`, FPM admite dos procesos y PostgreSQL configura `shared_buffers=96MB` y `work_mem=2MB` en `deploy/oracle`. Estos son límites del proyecto, no mediciones ni confirmación de la configuración activa de la VM. Las exportaciones de registros recorren lotes de 500 y escriben el XLSX en disco; imágenes y gráficos no requieren modelos ni bibliotecas adicionales en ejecución.

La última revisión visual comparó el dendrograma con las referencias proporcionadas. La ficha deja de repetir el árbol como pequeños rectángulos y conserva un retrato amplio con su procedencia y una clasificación pública desplegable. Los rangos adicionales proceden de la ruta pública seleccionada, sin consultas ni inferencias de ancestros. El diseño y sus límites se documentan en [dendrograma del portal](dendrograma-taxonomico-portal.md).
