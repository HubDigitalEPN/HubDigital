# Distribución de las pruebas

Pest conserva las comprobaciones de componentes, las pruebas de persistencia y los recorridos completos que ya tiene implementados. Gherkin, ejecutado por Behat, añade contratos de negocio y recorridos entre componentes con resultados que Pest todavía no comprueba.

Un duplicado tiene las mismas entradas, el mismo recorrido y el mismo resultado comprobado. Usar un mismo componente para preparar datos no convierte dos recorridos distintos en un duplicado. Antes de añadir un escenario, revisar las pruebas activas de `phpunit.xml` y los escenarios `@listo` de Behat.

## Casos que se conservan en Pest

Se retiraron los escenarios Gherkin equivalentes y sus pasos exclusivos. Los repositorios en memoria compartidos con Pest se conservan.

| Caso | Pruebas que lo conservan |
| --- | --- |
| Actualizar datos de un espécimen y rechazar un identificador inexistente | `Modules/InventarioGestionColeccion/tests/Unit/ActualizarEspecimenHandlerTest.php` |
| Desactivar un taxón y rechazar un identificador inexistente | `Modules/InventarioGestionColeccion/tests/Unit/DesactivarTaxonHandlerTest.php` |
| Registrar localidades, validar duplicados y jerarquía, y listar con paginación | `Modules/InventarioGestionColeccion/tests/Unit/RegistrarLocalidadHandlerTest.php` y `ListarLocalidadesHandlerTest.php` |
| Registrar, confirmar y marcar para revisión una muestra de colecta | `Modules/InventarioGestionColeccion/tests/Unit/RegistrarMuestraColectaHandlerTest.php`, `ConfirmarMuestraHandlerTest.php` y `MarcarMuestraParaRevisionHandlerTest.php` |
| Rechazar una consulta fuera del dominio sin invocar al generador de respuestas | `Modules/CatalogoPublico/tests/Unit/ConsultarChatBotHandlerTest.php` |
| Enviar la solicitud firmada, pasarla a revisión y notificar a curaduría | `tests/Unit/EnviarSolicitudDepositoInvariantesTest.php` y `tests/Feature/FlujoDepositoPersistenciaE2ETest.php` |
| Recepción conforme de un depósito hasta su ingreso científico | `tests/Feature/FlujoDepositoPersistenciaE2ETest.php` |

La comprobación del evento `SolicitudDepositoPendienteDeRevision`, antes exclusiva del escenario de envío, se trasladó al caso Pest de envío firmado. Ese caso también comprueba que repetir el envío no vuelva a publicar el evento. Así se conserva la comprobación al retirar el escenario duplicado.

En la aprobación documental sin alertas se retiraron las comprobaciones repetidas de estado, QR y notificaciones. Gherkin conserva la auditoría del curador responsable, su fecha y el evento de aprobación. También conserva los recorridos de justificaciones, rechazo, donación y prioridad que tienen condiciones propias.

## Cuatro recorridos adicionales del QR

Están en [lectura_qr_movil.feature](../Modules/InventarioGestionColeccion/tests/Behat/Features/IdentificacionFisicaEspecimenes/lectura_qr_movil.feature), marcados como `@listo` por la característica.

| Recorrido Gherkin | Comprobación adicional |
| --- | --- |
| Generar la etiqueta, corregir localidad y colector y resolver el QR original | La ficha muestra los datos corregidos y conserva el taxón, el espécimen, el código de catálogo y la etiqueta. |
| Generar la etiqueta, cambiar la determinación científica y resolver el QR original | La ficha muestra el taxón actual sin cambiar la etiqueta ni su vínculo con el espécimen. |
| Etiquetar dos especímenes, corregir el primero y resolver ambas etiquetas | Cada ficha conserva su identidad y sus propios datos; la corrección no se propaga al segundo espécimen. |
| Etiquetar un espécimen indeterminado, consultar su ficha, identificarlo y volver a consultar | La misma etiqueta pasa de mostrar una ficha sin taxón a mostrar la primera determinación científica. |

Pest conserva las pruebas individuales de actualización e identificación. Estos escenarios comprueban su efecto posterior en la ficha resuelta desde una etiqueta existente. Ejecutan los componentes reales con repositorios en memoria; no son pruebas del navegador ni sustituyen la persistencia PostgreSQL de Pest.

Gherkin también conserva la auditoría de aprobación, la recepción permanente de donaciones y la transferencia de campos científicos al contexto del chatbot. La recepción normal del depósito se prueba en Pest; Gherkin mantiene los recorridos de corrección y cuarentena por observaciones.

## Validación y publicación

`phpunit.xml` incluye las suites Unit, Feature, Integration e Infrastructure de los tres módulos y `tests/Regression`. La inclusión de las pruebas de catálogo, préstamos y accesibilidad corrige una omisión anterior: estaban en el repositorio, pero `artisan test` del paquete no las seleccionaba. Los casos de reanudación de solicitudes antiguas exigen ahora regresar al paso de firma; el adaptador de catálogo y los listeners usan transacciones en la base PostgreSQL local ya migrada, sin recrear esquemas por prueba. Sus datos de prueba usan identificadores únicos y no presuponen una colección vacía.

`tests/Feature/VisibilidadCuratorialTest.php` verifica que un ejemplar sin filo permanezca en curaduría y fuera del CSV público hasta asignar un linaje con filo, que la entrada del portal y la ruta de estadísticas redirijan a la vista del mapa de Colección Biológica y que comparar especies devuelva 404. El recorrido Livewire comprueba el contenido de tarjetas, registros y mapa al alternarlos con el mismo filtro, y conserva la selección de exploración taxonómica al pasar al mapa y regresar a tarjetas. Usa un taxón único sin coincidencias para comprobar que los gráficos de provincias y décadas vacíos se rendericen con sus mensajes, sin depender de los taxones de la base local. La exploración y el árbol se muestran solo en tarjetas. Estos casos se conservan en Pest, sin añadir escenarios Gherkin equivalentes.

`tests/Feature/FlujoDepositoPersistenciaE2ETest.php` comprueba el bloqueo sin firma, la firma y validación criptográfica del oficio con el P12 real autorizado, su único objeto firmado en R2 simulado y su eliminación al descartar el borrador. El paquete comprueba que `.local/secrets` contenga el P12 y el archivo de credenciales antes de ejecutar la suite; ninguno se agrega a Git ni se muestra en la salida. La persona que ejecute `crear-paquete-oci` debe hacerlo desde una cuenta de Windows con acceso de lectura a esos dos archivos. Maven compila el firmador antes de Pest.

El paquete compila la interfaz, pero no realiza comparación visual automática de modo oscuro y diseño en navegador. Esa revisión sigue siendo necesaria en la versión desplegada; un resultado correcto de Pest y Vite no demuestra por sí solo la apariencia de todas las pantallas.

La etapa frontend de `crear-paquete-oci` instala desde cada `package-lock.json`, audita las dependencias de producción, desarrollo y opcionales con `npm audit --audit-level=low`, y compila tanto la raíz como cada módulo que declare `package.json`. Una vulnerabilidad conocida o un fallo de consulta al registro detiene la publicación. Los tres módulos conservan sus directorios de salida y el archivo `manifest.json` que espera Laravel; Inventario declara Sass para su entrada SCSS. Esta cobertura de dependencias y compilación pertenece al paquete, sin repetirla en Pest, Gherkin ni comandos aislados. El resultado depende de los avisos conocidos por npm en el momento de la ejecución; no acredita por sí solo el estado del contador de Dependabot en GitHub.

Los tres manifiestos compilados de módulos se incluyen explícitamente en `SOURCE-MANIFEST.sha256`, aunque sus directorios de salida estén ignorados por Git. El paquete comprueba sus huellas antes de comprimir y el verificador de identidad existente vuelve a comprobarlas en OCI al preparar y activar la release.

El usuario ejecuta `crear-paquete-oci`. El comando exige Behat, ejecuta `--profile=default --no-interaction --tags=@listo --strict` y detiene la publicación y el empaquetado ante un código de salida distinto de cero. La etiqueta y el perfil coinciden con los escenarios activos de GitHub. Los escenarios heredados aún sin implementar permanecen fuera de esa selección; no se deben quitar etiquetas para ocultar fallos de escenarios activos.

La validación Gherkin ocurre antes del commit, de la publicación en `main` y de la creación del archivo OCI. Las otras comprobaciones obligatorias del paquete también deben terminar correctamente. Estas pruebas se conservan en Git y sus archivos y dependencias de desarrollo se excluyen del paquete de producción.

Los cambios se revisan de forma estática durante el desarrollo. La ejecución automatizada queda a cargo del comando del usuario; añadir un escenario no significa que ya haya pasado.

## Selección del catálogo y consultas generales del chat

`tests/Feature/PortalCatalogoSeleccionTest.php` aporta 23 selecciones con resultados conocidos: código, taxón, provincia, localidad, preparación, colector, fechas, mes, método, coordenadas, elevación, bioma, hábitat, tipo, casta, estadio, rango de identificación, completitud y combinaciones. Compara los identificadores de la tabla y los conteos de tarjetas, mapa, filos, provincias, décadas y especies. Conserva una selección vacía y el recorrido desde provincia y década hasta registros y limpieza. También comprueba que el chatbot distinga registros de cualquier rango de especies distintas. Son contratos de persistencia y componentes en Pest; no se añaden duplicados Gherkin.

`Modules/CatalogoPublico/tests/Feature/FuentesPublicasChatTest.php` usa respuestas HTTP simuladas para verificar atribución, caché, fuente no disponible y cálculo aritmético sin red. La ejecución real de estos casos pertenece exclusivamente a `crear-paquete-oci`. Las llamadas externas reales y su disponibilidad no quedan certificadas por esos casos simulados.

Las pruebas de selección también distinguen coordenadas reservadas o fuera de rango de las utilizables, comprueban la selección global desde composición taxonómica y los errores de rangos espaciales incompletos con su limpieza.

La revisión complementaria de navegador cubre puntos visibles, leyenda, comportamiento de Leaflet al reemplazar el panel, foco y Escape de los menús y diálogos, flujo del texto alrededor de cada imagen, botones inferiores al desplazar y contraer filtros, tamaños de pantalla y descargas. El recorrido del chatbot incluye preguntas escritas, ramas mediante botones y reinicio de conversación. Esa evidencia visual complementa las aserciones de datos y no sustituye las suites del paquete. La base de la vista previa local no contiene ejemplares públicos: la concordancia de conteos se comprueba con las fixtures de Pest; los puntos reales se inspeccionan en una vista previa de los estilos sobre el portal de desarrollo.

Las opciones del menú se apoyan en los [formatos de descarga de GBIF](https://techdocs.gbif.org/en/data-use/download-formats) y en los [formatos vectoriales admitidos por QGIS](https://docs.qgis.org/3.44/en/docs/user_manual/managing_data_source/supported_data.html). CSV conserva tablas, JSON incluye los datos del panel y su consulta, GeoJSON exporta centros de cuadrícula con conteos para SIG, y el enlace y la cita conservan filtros y fecha. Los centros redondeados no son coordenadas individuales. El menú denomina estas explicaciones «Indicador». Estacionalidad de colecta, cobertura altitudinal y métodos de recolección reemplazan los paneles de calidad, especies escasas y especies más documentadas; reutilizan imágenes existentes como apoyo visual. Los intervalos altitudinales cuentan registros solapados y no deben sumarse.

### Revisión Dot y mapa público — octubre de 2026

`PortalCatalogoSeleccionTest` amplía la cobertura Pest de selección compartida: códigos en frases/listas, localidad compuesta, mes y año, coordenadas, identificación, enlaces del chat, correcciones de contexto, instrucciones concretas de filtrado, borradores inválidos que conservan la selección, jerarquía visible y CSV coincidente. Comprueba cada uno de los seis botones de indicadores, detalle de cuadrícula con agrupaciones descendientes, doce registros por página, campos reservados y fotos asociadas. `RutasObjetosR2Test` cubre la denegación de imágenes de registros excluidos por región. `FuentesPublicasChatTest` impide presentar fuentes generales sin relación y pronósticos no disponibles. Se amplía Pest en lugar de duplicar estos casos en Gherkin; los contratos Behat existentes permanecen activos.

La columna calculada `coordenadas_otras_regiones` conserva las coordenadas originales y se recalcula al corregirlas. Marca coordenadas mundiales válidas fuera de dos envolventes conservadoras: continente (latitud −5,1 a 1,9; longitud −81,3 a −75) y Galápagos (latitud −1,6 a 1,9; longitud −92,1 a −89,1). Es una alerta de revisión, no una asignación de país ni una frontera política exacta. Los registros marcados se excluyen de consultas, exportaciones, chat y fotos públicas; el curador puede verlos y filtrarlos. Coordenadas ausentes o inválidas conservan su tratamiento anterior y no generan puntos del mapa.

Los marcadores textuales de daño no cuentan como identificaciones válidas o provincias. Las fechas anteriores a 1800 o futuras quedan fuera de métricas temporales; se conserva y señala el valor original para revisión curatorial, sin inventar una fecha o nombre sustituto. Las pruebas comprueban esta distinción entre registros conservados y métricas utilizables.

La revisión manual de UX debe comprobar foco persistente en el mapa, actualización parcial, teclado en marcadores y diálogo, maximizar/minimizar a distintos zooms, tamaños de pantalla y estados vacíos. Estos recorridos visuales requieren un navegador disponible. Las comprobaciones automatizadas anteriores se ejecutan exclusivamente dentro de `crear-paquete-oci`.
