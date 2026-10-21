# Distribución de cobertura

El árbol de diversidad inicial tiene contratos exclusivos en `tests/Frontend/portal-taxonomy-diversity.test.mjs`: multifurcaciones y padres publicados, sectores contiguos, cadenas profundas, ramas extensas, terminales, encuadre, precisión de zoom hacia el cursor, conservación de distancias y topología, composición orgánica sin pisos, radios decrecientes y cajas con profundidad real. `tests/Frontend/portal-taxonomy-tooltip.test.mjs` cubre solicitudes de leyenda agrupadas, aborto, respuestas tardías, cierre y fallos de red sin alterar la selección; los permisos del endpoint ya pertenecen a Pest. No se duplican en Gherkin. `tests/Browser/portal-livewire.mjs` comprueba la perspectiva inicial, acercamientos superiores a 3×, restauración con Inicio, Ctrl + arrastrar sin cambiar la raíz ni trasladar el centro de la vista, giro con teclado, iconos de igual tamaño sin Ajustar ni Reintentar y alternancia retirando el motor previo; conserva la regresión completa de esferas. Las suites se ejecutan exclusivamente dentro de `crear-paquete-oci`. La inspección manual complementaria con Opera Neon se reserva para legibilidad, volumen y composición, aparición progresiva de clados, leyendas del catálogo real, foco, adaptación y fluidez.

`PortalFichasTaxonomicasTest.php` cubre conversión y carga AVIF, límite de 32 KB y 360 × 270 píxeles, leyenda editable, permisos, compatibilidad con fotos WebP anteriores y rechazo de un formato falsificado. `ImportadorFichasAvifTest.php` cubre linaje completo, fuente y licencia, cuota de especie con correspondencia exacta, integridad de los bytes, idempotencia y conservación de ediciones concurrentes. `PortalExploradorTaxonomicoTest.php` y `TaxonomiaPublicaQa4Test.php` cubren los separadores Unicode/NBSP y el último ancestro confirmado; no se agregan escenarios Gherkin duplicados. El inventario, la disponibilidad real de objetos R2 y la cobertura editorial por rango se revisan sobre los datos del ambiente, sin declararlos aprobados a partir de fixtures de prueba.

Los accesos de rango tienen contratos en `portal-taxonomy-diversity.test.mjs`: comienzo con Clase y Orden, apertura o cierre hasta Especie, selección inicial de Familia y omisión de rangos realmente ausentes. El zoom continuo de cámara descubre ramas según su tamaño proyectado, sin esperar al siguiente acceso de rango. Comprueba conteo de especies sin duplicar subespecies ni contar géneros sin identificar y crecimiento logarítmico de las yemas independiente de los tallos. La proyección con cámara real comprueba raíz fija y corona creciente en la vista general, en escritorio y móvil, tras giro y desplazamiento, incluyendo acumulación rápida y límites extremos del cálculo. Conserva la independencia entre registros y especies, apertura de dos rangos al pulsar una rama y formato «Orden (926)». El recorrido canónico de navegador comprueba ampliación automática, salida, botones padre/−/+/salir, regreso al padre publicado sin salir de pantalla completa, deshabilitación sin padre, rueda de 0,5× a 1× sin trasladar la raíz y acercamientos profundos con presupuesto acotado y una especie visible al llegar al límite. Sustituye el anterior contrato de centrado por abundancia, retirado por el nuevo comportamiento solicitado. La revisión manual complementaria valora legibilidad, tallos finos y pellizco en móvil. La jerarquía izquierda conserva acceso a todos los taxones publicados independientemente del zoom. Estos casos no se duplican en Gherkin.

`portal-taxonomy-explorer.test.mjs` comprueba que el menú del octree utiliza el contenedor de pantalla completa, restaura su padre al cerrarse y conserva la posición del modal durante la expansión. El recorrido de navegador añade una sesión realmente anónima: clic derecho sobre yema del árbol expandido y esfera con pantalla completa nativa, menú pulsable con «Ver en el mapa», ausencia de «Ver registros», selección aplicada al mapa y devolución del menú al diálogo. `PortalAccesoPorPerfilTest` conserva las denegaciones directas de registros y exportaciones y verifica que el enlace de mapa se renderiza para todos los perfiles. Esta cobertura no se repite en Gherkin.

El encuadre al pulsar una yema se proyecta con `PerspectiveCamera` real en la prueba de modelo, en ancho de escritorio y móvil: raíz abajo y exactamente centrada horizontalmente, todos los rangos abiertos dentro de la vista y un descendiente cerrado extremo que no altera el encuadre. El recorrido de navegador comprueba esa composición y el anclaje de la raíz durante los siguientes acercamientos y alejamientos.

Las observaciones curatoriales de mapa, encabezados Darwin Core y depósitos conservan los contratos anteriores de permisos, campos ocultos y paginación. `portal-phylum-model.test.mjs` comprueba que resaltar el linaje no aplica filtros adicionales. `WizardDepositoSinIdentidadTest.php` conserva los contratos del editor histórico y añade el flujo Excel: plantilla propia, carga, rechazo sin pérdida de matriz, reemplazo y reanudación. No se duplican estos casos en Gherkin. El pie identifica la release real, el dominio operativo y la licencia CC BY 4.0 autorizada para contenido propio; la revisión manual verifica sus desplegables y enlaces oficiales EPN, sin atribuir una licencia nueva a fotografías de terceros.

Las grillas administrativas se comprueban en Pest en `Auth/AdminInteractiveGridTest.php`:
orden global entre páginas, búsqueda con paginación, límites de filas, lista
autorizada de columnas y restablecimiento sin modificar cuentas. Los permisos
siguen cubiertos por `Auth/AdminUserManagementTest.php`. Turnstile conserva los
contratos de autenticación y registro con Siteverify simulado; los estados de
espera, renovación y reintento del cliente se cubren en Node. No se duplican en
Gherkin. La comparación con Vita, el contraste del menú, la fila de accesos y el
foco de las herramientas se revisan manualmente en Opera Neon QA al 75 %.

La comprobación integrada PHP/Java prepara una JVM privada tanto en Linux como
en Windows antes de medir el límite de tres segundos. En Windows usa un puerto
efímero en loopback y autenticación mutua, sin reemplazar el motor de localhost
ni incorporar el certificado sintético a la confianza del servicio existente.

crear-paquete-oci centraliza Pest con PostgreSQL, Behat, Java, Python, Node,
compilaciones y la integridad de las fuentes del paquete. No se ejecutan esas
suites por separado durante una tarea ordinaria.

El inicio del flujo es manual. GitHub Actions está deshabilitado en el
repositorio oficial y no se conservan workflows que ejecuten suites al publicar
commits o abrir pull requests. Las pruebas automáticas permanecen en el proyecto
y se ejecutan mediante crear-paquete-oci, incluida su opción SoloValidacionLocal.

Las auditorías de dependencias también se ejecutan dentro de crear-paquete-oci:
Composer revisa composer.lock antes de instalar PHP y npm revisa los cuatro
proyectos frontend. Una vulnerabilidad comunicada por estos registros bloquea
la publicación. Dependabot complementa estas comprobaciones en GitHub; necesita
el grafo de dependencias habilitado y no reemplaza las suites de regresión.
La actualización de Bouncy Castle del motor Java conserva el selftest
criptográfico y la validación integrada PHP/Java del paquete. No se agregan
escenarios Pest o Gherkin que dupliquen estas auditorías.

Pest cubre persistencia, permisos, aislamiento entre usuarios, integridad de
documentos, navegación del depósito, cantidades positivas, localidad opcional
y recuperación de errores. Java comprueba criptografía y estructura PDF. Behat
conserva los contratos de negocio y flujos de API exclusivos; antes de agregar
un escenario se comparan sus entradas, flujo y expectativas con Pest. Node y
la prueba de navegador cubren los contratos del frontend.

El validador Windows ejecuta Pest en hasta cuatro procesos, cada uno con una copia
temporal propia de PostgreSQL y su almacenamiento separado. Las transacciones
y los fixtures sólo afectan esas copias; al finalizar se retiran. Las rutas y
la configuración se reutilizan en memoria sin retirar casos ni aserciones.

Los recorridos manuales complementarios utilizan Opera Neon QA en localhost.
Los PDF privados de docs se revisan directamente en ese navegador por
solicitud del usuario y no se incorporan al paquete. No se conservan capturas,
informes ni resultados de esos recorridos.

La interpretación opcional Qwen se cubre en Pest mediante HTTP simulado: exclusión
de localhost y dev aunque se copie la variable, lista de intenciones autorizadas,
caché, concurrencia, indisponibilidad y conservación de los controles documentales.
Una intención inferida en el portal se ofrece como pregunta para confirmar;
en la ayuda documental la orientación se expresa de forma condicional.
Las menciones de rúbrica electrónica se atienden directamente con las reglas de firma.
No duplica estos casos en Gherkin. La medición manual del backend ARM64 sólo
comprueba latencia y recursos del servicio instalado; no repite esas suites.

El permiso de consulta/exportación y la redirección por perfil se verifican exclusivamente en `tests/Feature/PortalAccesoPorPerfilTest.php`: visitante, los cinco roles, curador sin verificar, URL de registros y llamadas directas Livewire/CSV. Los casos previos de tablas, filtros y exportación conservan sus aserciones con una sesión curatorial explícita. La puerta Neon usa un visitante real seguido de login de una cuenta efímera del fixture; nunca una ruta de acceso alternativa. Node verifica el presupuesto visual en 10.000 especies, agrupación por densidad y exclusión de rangos cerrados. La PWA prueba documentos Network First y ausencia de contenido privado en caché. No se duplican esos casos en Gherkin.

El mismo archivo de permisos comprueba las opciones renderizadas del chat para cada perfil: conserva la provincia de la selección, abre registros sólo con acceso curatorial y ofrece el mapa al resto. También conserva la sugerencia de contar especies y reserva la de ver registros a curaduría. El contrato de ayuda pública verifica que la exploración no exige cuenta y que las descargas sí explican la autorización necesaria; el contexto y los enlaces del motor de consultas mantienen sus casos previos.

## Wizard, pie compacto y grillas APEX

`FlujoDepositoPersistenciaE2ETest` conserva la vista previa parcial y comprueba que editar un campo informa solo de ese campo, que los errores pendientes no se autoguardan como datos validos y que Generar PDF valida el formulario completo. El paso inicial tiene una sola accion principal: Generar PDF, Firmar PDF e Ir al paso 2 Tramite segun su estado. `hubdigital-firmador.test.mjs` comprueba que un original ya preparado no vuelve a generarse al abrir la firma, que la clave se borra tras un fallo y que el certificado se conserva solo hasta cerrar o completar la firma. `ColaFirmasSolicitudR2Test` comprueba el mensaje concreto de clave p12 incorrecta y la ausencia de una firma persistida. La clasificacion criptografica se realiza en Java mediante la causa tipada del error; PHP y la interfaz muestran su mensaje seguro.

`PlatformFooterTest` conserva el contrato HTTP del contenido legal, licencia, citacion y creditos que consultan los modales. El recorrido canonico de Neon llega al final del catalogo y comprueba que los cinco enlaces son pulsables sin que Limpiar filtros los tape, que no hay fecha en el pie, que cada enlace abre un modal de tamano medio sin abandonar el mapa ni perder sus filtros y que Escape devuelve el foco. El recorrido manual complementa el ajuste movil, el desplazamiento del texto y la conservacion de la posicion al cerrar. Estos casos de navegador no se duplican en Pest ni Gherkin y se ejecutan solo mediante crear-paquete-oci. La inspeccion manual no repite comprobaciones de PDF ni suites aisladas.

`interactive-grid-client.test.mjs` cubre busqueda en filas no visibles, orden numerico estable, paginado, restauracion y conservacion de seleccion. `PortalCartografiaRealTest` conserva el contrato interno de lotes y agrega tamano de pagina, vuelta a la primera pagina al ordenar toda la seleccion y rechazo de columnas arbitrarias. El recorrido canonico de Neon comprueba la grilla del catalogo con paginado APEX, scroll sin acumulacion, paginas sin UUID repetidos y navegacion de vuelta. Los controles compartidos se reutilizan en tablas internas, de localidad y del catalogo; las paginas completas siguen consultandose en servidor. Esta cobertura no se duplica en Gherkin y solo se ejecuta mediante crear-paquete-oci.

La entrega del original informa su duracion en Server-Timing y envia Content-Length. La preparacion opcional del motor y de la conexion R2 se realiza al terminar la respuesta, una vez por contenido durante cinco minutos. No sustituye la validacion criptografica al firmar. La cache del PDF conserva su identidad por datos, version, perfil y plantilla y evita compilaciones concurrentes del mismo original.

La proyección pública conserva el orden de los UUID de la página después de hidratar sus detalles, usando los mismos valores visibles que muestra la tabla. La reanudación conserva acceso a la copia firmada anterior sin añadir otro botón principal. Los contratos de paginado de localidades mantienen sus cantidades, la consulta por grupos y la exclusión de datos reservados con los controles APEX compartidos.

La puerta de navegador consulta sus servidores de loopback con `node:http` y consume cada respuesta completa. Conserva los mismos estados HTTP, contenidos y aserciones; evita el aborto no capturable del parser Undici incluido en Node 24 (nodejs/undici#5360) durante el arranque.

# Zoom semántico y matriz de depósito v2

## Mapa mundial, plantilla curatorial y comprensión de consultas (21 de octubre de 2026)

- Pest comprueba que el catálogo, el mapa, el CSV, el chatbot y las fotografías admiten ocurrencias publicadas de otros países con nombre científico y coordenadas públicas válidas. La señal geográfica de revisión permanece sin bloquear su divulgación. Casos negativos independientes conservan la exclusión por nombre vacío o invisible, nombre reservado, coordenadas reservadas o incompletas, rangos inválidos y falta de publicación.
- `PlantillaCatalogoConfiguracionTest` cubre el libro completo de 106 campos de laboratorio más notesGeneral y taxonomicNotes, la correspondencia de encabezados y guía, la edición de alias, las obligaciones por fila, la invalidación del plan al cambiar su configuración, los cambios concurrentes y la independencia de la matriz de depósito. El historial descarga el original íntegro, incluidas las columnas repetidas; se prueban autorización e integridad.
- La ayuda de importación y los créditos usan el editor de textos del curador. La revisión manual con Opera Neon complementa los contratos de Pest con tamaño de modales, descarga, scroll, posición del breadcrumb, selector compacto y ausencia de pies visibles en todas las ilustraciones de «Explicar Métrica…».
- Qwen 4B cuantizado queda reservado a producción en loopback, con una sola inferencia, contexto de 1024 tokens y un límite de servicio de 4 GiB. Pest verifica exclusión de DEV/localhost, corrección acotada del vocabulario de intención sin alterar códigos, citas ni nombres científicos, preservación de negaciones y transmisión de historial acotado. La descarga de pesos se fija por SHA-256 oficial; consumo y latencia reales se revisan únicamente en producción. Las respuestas y cantidades siguen proviniendo de los servicios del catálogo.
- El árbol conserva Three.js, sus taxones reales y la fórmula logarítmica de especies por yema. La filotaxis evita bifurcaciones diametrales planas; Opera Neon comprueba la presentación frontal y al girar, los botones de zoom en pantalla completa, el breadcrumb superior y el control vertical ocultable. No se agregan escenarios Gherkin que repitan estos contratos de Pest/Node.

- Los accesos de rango conservan sus contratos de apertura y cierre, inicio con Clase y Orden y apertura de dos niveles al seleccionar una raíz. El slider, la rueda y el pellizco usan ahora la distancia física de cámara sin conversiones semánticas comprimidas: el detalle aparece según la longitud de rama proyectada, la densidad y el espacio visible. Node comprueba que entre 0,31× y 0,49× se descubren ramas sin mover las coordenadas y que el factor modifica realmente la distancia de cámara. La vista general mantiene la raíz anclada; en detalle el foco sigue una yema real, y el mínimo vuelve al encuadre general.
- Se retiran los contratos del paginador y del reacomodo compacto al eliminar esa presentación por solicitud del usuario. Su cobertura de conservación de datos queda en el recorrido de ventanas espaciales sobre 10.000 especies: cada destino y todos sus padres siguen accesibles con los presupuestos de 80 y 160 nodos. No se divide ni descarta el modelo para dibujarlo. Las yemas conservan su escala logarítmica normal y una perspectiva acotada, sin reducir ancestros para encajar todas las especies.
- La geometría Node verifica inserciones alrededor de los cuatro cuadrantes, profundidad comparable al ancho y una vuelta completa sin colapsar la copa en una lámina. Conserva relaciones, distancias, sectores, curvaturas y conteos. El recorrido canónico verifica zoom físico, ausencia del paginador, control vertical oculto y accesible mediante teclado, foco y Escape, límites, Ctrl+arrastre, Ctrl+flechas, selección y menú contextual. La revisión manual con Opera Neon complementa volumen desde la primera vista, legibilidad, hover, giro lateral y móvil con el catálogo real; no ejecuta suites por separado.
- Pest lee el archivo original `plantilla-laboratorio-invertebrados-v2.xlsx`: conserva sus 106 columnas mediante alias configurados e importa una observación, excluyendo las descripciones y la clave de colores. El contrato Livewire cubre esa misma carga desde el penúltimo paso, la conservación de una matriz anterior cuando se rechaza otro archivo y los mensajes de campos obligatorios sin un bloque de errores permanente en la pantalla.
- Pest comprueba que los requisitos y alias editados por el curador se persisten y afectan a la lectura, que los opcionales no bloquean, que se respeta la excepción parametrizada del permiso de movilización y que visitantes/depositantes no pueden configurar columnas. También impide alias ambiguos sin sobrescribir la definición válida.
- Opera Neon añade búsqueda en la grilla de configuración y apertura/cierre con foco del formulario modal, usando la cabecera ligera del portal y su acceso al panel del curador. No repite la persistencia ni la validación de celdas cubierta por Pest. No se añaden escenarios Gherkin duplicados.

# Actualización de matrices del catálogo desde la pantalla del curador

- `ImportacionCatalogoMatricesTest` comprueba con matrices sintéticas equivalentes a los archivos del curador la consolidación de columnas repetidas y alias, los conflictos no vacíos, fechas de Excel con formato, resultados guardados de fórmulas, primera hoja y filas de plantilla vacías. La validación no escribe taxones, muestras, especímenes ni historial.
- La integración PostgreSQL comprueba identidad con prefijos equivalentes, conservación de UUID/códigos/estado/divulgación, notas generales y taxonómicas, casta, fechas, columnas adicionales y auditoría anterior/posterior. Cubre reintento idempotente, identificación provisional a nivel de género, identidades ambiguas y cancelación por edición concurrente antes de aplicar el lote.
- El contrato Livewire comprueba permisos curatoriales, vista previa obligatoria, archivo privado en R2 y despacho del trabajo; el consumidor aplica el mismo servicio transaccional. La puerta de Opera Neon añade el recorrido visual de seleccionar CSV, recibir un conflicto visible y mantener deshabilitado el guardado, con los controles APEX y sin una navegación de error. No duplica los contratos de persistencia ni agrega escenarios Gherkin redundantes.
- Los originales Camponotus y Azteca se revisan en el recorrido manual local y, después del despliegue, se cargan desde la pantalla de producción con la cuenta autorizada. Los originales aportados no se publican como datos de prueba en Git. Toda suite se ejecuta únicamente mediante el comando manual `crear-paquete-oci`; no hay GitHub Actions.
- Pest cubre también el rechazo de identidades cruzadas entre occurrenceID y catalogNumber, el solicitante eliminado, CSV tabulado, el rollback tras la primera escritura y la hidratación de notesGeneral. Comprueba que añadir una subfamilia ausente mantiene el UUID del género y registra el enlace anterior y posterior, mientras una familia contradictoria sigue bloqueando la matriz. El disco transitorio de Livewire en testing usa el storage aislado del paquete para recibir cargas HTTP reales.
- Opera Neon alterna dos historiales con columnas opcionales distintas, abre una fila y pagina de veinte a dos filas. Compara todos los identificadores visibles para impedir que el modal mezcle datos de otra carga durante el morph; complementa la auditoría de persistencia de Pest.
