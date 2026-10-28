# Distribución de cobertura

`deployment-config-output.test.mjs` ejecuta los bloques reales de comprobación
de Nginx y PHP-FPM mediante Bash (Git Bash en Windows). Un productor externo
imprime más de 1 MiB: la ruta correcta debe aprobar incluso al principio de la
salida; una ruta ausente o un productor fallido deben bloquear. Esta regresión
de EPIPE/SIGPIPE con `pipefail` se ejecuta solo dentro de `crear-paquete-oci`.
También comprueba las tuberías antiguas mediante la compatibilidad del asistente
PROD: misma coincidencia, salida completa y rechazo de errores del productor.
El ayudante se limita a un subproceso; el bundle publicado permanece intacto.
No sustituye la comprobación del Nginx instalado en la VM ni duplica Gherkin.

El acceso, la solicitud de enlace y el restablecimiento envían el POST original
de Fortify por JSON y conservan CSRF, CAPTCHA, límites, segundo factor y destino
por perfil. El recorrido Neon del paquete verifica error de credenciales y
acceso correcto con Enter en el mismo documento, sin recarga completa, controles móviles sin solaparse y el ojo fuera
del recorrido Tab. Node cubre el POST, errores sin detalles técnicos y rechazo
de destinos externos. Pest comprueba las respuestas JSON por perfil, privacidad
de la recuperación y respuesta de restablecimiento sin redirección HTTP. El
formulario POST sigue disponible cuando JavaScript no está activo.

La contraseña se introduce manualmente: `manual-password.test.mjs` cubre teclado,
IME, edición, confirmación con Enter sin borrar la clave, autollenado, pegado, cambios sin evento y reinicio del estado en
memoria. La puerta de Neon del paquete rechaza un valor insertado por script y
autentica con eventos reales de teclado. La semántica PHP exige campos vacíos y
autocompletado desactivado. `Auth/AuthenticationTest` comprueba rechazo de cookies
«Recordarme» anteriores y de la opción enviada por un formulario manipulado.
La aplicación conserva las sesiones web normales; no controla el almacenamiento
interno de gestores de contraseñas del equipo ni lo declara deshabilitado.

`Auth/PasswordResetTest` complementa los contratos de identidad con el despacho
del trabajo de recuperación a la cola de dev, su consumo por el canal real de
notificaciones y la generación MIME HTML/texto en un transporte local sin red.
Comprueba permiso explícito en validación, aislamiento del correo operativo,
verificación de cuenta, dominio DEV/PROD, correo con alias, enlace válido,
caducidad, reemplazo, confirmación distinta, uso único e inicio con la nueva
contraseña. La entrega a una bandeja externa depende del SMTP de la VM y no se
declara comprobada mediante este transporte. `Settings/SecurityTest` conserva
el cambio autenticado, contraseña actual y revocación de accesos anteriores.
`PortalAccesoPorPerfilTest` añade salida por POST con CSRF en escritorio y móvil
para todos los perfiles; el cierre de sesión conserva su contrato en
`Auth/AuthenticationTest`. Opera Neon complementa posición, foco, tamaño y uso
de esos controles en localhost. No se duplican estos contratos en Gherkin.

La plantilla corta de depositantes conserva el XLSX de referencia de 42 columnas,
con su ejemplo, colores, descripciones y clave. `EsquemaMatrizDepositoTest`
comprueba la descarga generada y la lectura real sin importar las instrucciones
como especímenes. Se conservan la lectura de la plantilla anterior, los alias y
los requisitos modificados por curaduría; la plantilla del catálogo no cambia.
`WizardDepositoSinIdentidadTest` cubre el ejemplo válido, una fecha imposible,
el bloqueo del avance, el salto entre pasos y la reanudación. El contrato de
envío persistido con errores se comprueba en `EnviarSolicitudDepositoInvariantesTest`.
Los nombres de personas admiten barra o punto y coma y conservan nombres completos.

`DepositosPlantillaCierreTest` distingue aprobación documental de cierre con acta
final firmada, comprueba las acciones de escritorio y móvil, la descarga adjunta,
la autorización del documento y el rechazo de una devolución desde el trámite
cerrado. También verifica la edición curatorial de ambos bloques del portal y
el responsable designado para las nuevas actas: Vladimir Carvajal o el reemplazo
configurado, conservando al operador auditado y la identidad del firmante.
`DepositosCincoPuntosTest` conserva modalidades, observaciones, versión y marcadores
de firma del PDF, y exige retirar los textos indicados en las notas del acta.
Estos casos se ejecutan únicamente con `crear-paquete-oci`; no se duplican en Gherkin.
La descarga de Word para etiquetas queda sin implementar por indicación expresa;
no condiciona esta entrega. El acta conserva su plantilla PDF actual.

Los párrafos de privacidad, términos, licencia, citación y créditos se administran
en Textos del portal. `PortalTextosAdministracionTest` cubre publicación,
restauración, autoría y escape de HTML para los cinco enlaces; `PlatformFooterTest`
conserva sus datos de release y enlaces públicos, sin el subtítulo duplicado de
citación. `portal-pwa-cache.test.mjs` cubre los archivos CSS/JS versionados de la
portada y la exclusión de conversaciones. Inicio usa una copia exclusivamente
anónima durante un máximo de siete días, la entrega sin esperar la red y la renueva
en segundo plano sin cookies. La primera visita prepara también ese documento
y sus recursos al terminar de cargar, aunque la PWA se haya instalado después
de la navegación. Los contratos comprueban reapertura tras un día con la red
bloqueada, renovación de fondo, precarga acotada, recarga explícita e invalidación
por login/logout/registro/2FA/rol y respuestas autenticadas, incluida una renovación
anónima en vuelo y reinicio del service worker con su almacenamiento persistente.
La colección conserva Network First para respetar los permisos.
`PortalPwaPublicoTest` comprueba que Inicio de red entrega el token vigente sin
repetir el arranque de la colección y que `/portal/pwa/sesion` renueva el token
antes de Livewire únicamente al abrir una copia PWA, sin caché ni datos de cuenta.
`portal-pwa-session.test.mjs` cubre el orden del arranque: sin petición adicional
para HTML de red, espera del token en la copia PWA, recarga para una cuenta
autenticada y reintento accesible sin habilitar el token anterior ante desconexión.
Incluye actualizar una PWA ya instalada: la precarga se repite para el controlador
nuevo aunque el anterior haya recibido e ignorado el mensaje, sin duplicarla.
Las suites permanecen pendientes de `crear-paquete-oci` y no se ejecutan sueltas.
La configuración Nginx de HTTP y HTTPS comprime los archivos públicos CSS/JS/SVG
y mantiene revalidables los dos scripts de la PWA; la activación oficial instala
esas plantillas y valida Nginx antes de recargar. No se cambian las respuestas R2.
El recorrido canónico mantiene los casos exclusivos de
tooltip y morph, comprueba la ausencia de alternancia en el menú del mapa y el
conteo con la unidad registros. No se duplican estos casos en Gherkin. La revisión
manual en Opera Neon se reserva para las dos líneas de portada, presentación del
chat, foco, adaptación y frecuencia del llamado del asistente.

Las descargas Excel configurables de IPT se cubren en `tests/Unit/PerfilIptGbifTest.php` (69 cabeceras del ejemplo, fechas parciales, tipos, anchos, códigos y fórmula literal) y `tests/Feature/ExportacionCatalogoIptTest.php` (administración y concurrencia, filtros entre páginas, conservación de incompletos y privacidad del origen antes de mapear destinos). Se ejecutan exclusivamente mediante `crear-paquete-oci`; no se añaden Gherkin duplicados. El recorrido manual en Opera Neon se reserva para presentación, navegación por teclado, persistencia y descargas del catálogo real. Fuentes y decisiones: `docs/exportaciones-excel-ipt.md`.

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

Desde el 28 de octubre de 2026 el [reparto agente/paquete](validacion-agente-y-paquete.md)
conserva toda la cobertura: el agente ejecuta su 40 % al terminar cada solicitud;
`crear-paquete-oci` acredita esa evidencia y ejecuta el 60 % restante. No se
ejecutan suites sueltas ni se duplican entre las entradas.

La publicación sigue siendo manual. GitHub Actions está deshabilitado en el
repositorio oficial y no se conservan workflows que ejecuten suites al publicar
commits o abrir pull requests. Las pruebas automáticas permanecen en el proyecto
y se ejecutan por su entrada responsable; el agente inicia automáticamente su
bloque local y el usuario inicia el paquete o su opción SoloValidacionLocal.

Las auditorías de dependencias también se ejecutan dentro de crear-paquete-oci:
Composer revisa composer.lock y npm revisa los cuatro
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
- La VM de producción dispone de un CPU: la inferencia y la precarga usan un hilo, con un presupuesto de hasta 25 segundos para dudas y una exclusión mutua que lo supera. Pest comprueba que el bloqueo no expire durante una inferencia larga; la medida con el modelo real complementa el transporte HTTP simulado. El instalador aplica explícitamente el máximo de 4 GiB para evitar que un límite persistido anterior de 2 GiB prevalezca sobre la unidad.
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

## Textos, columnas y acceso público del catálogo (24 de octubre de 2026)

- `PortalAccesoPorPerfilTest` distingue lectura pública de exportación. Conserva los casos negativos de cuenta sin verificar y añade bloqueo del flag por HTTP y Livewire, acceso de cuentas verificadas habilitadas y conservación del acceso curatorial. El chatbot ofrece registros a visitantes y filtra acciones de descarga según ese permiso.
- `PortalExploradorTaxonomicoTest` conserva filtros al pasar del árbol al mapa, admite selecciones simultáneas entre ramas y comprueba que retirar una selección conserva las restantes. `PortalCatalogoSeleccionTest` compara la misma población en tabla, tarjetas, mapa y agregados con taxones múltiples y búsqueda parcial de localidad.
- `CatalogoAdministracionAmpliadaTest` cubre permisos del editor interno, publicación segura con autoría, restauración de textos, protección de columnas utilizadas incluso después de vaciarlas y edición de ubicación sin inventar fecha o colector en fichas históricas incompletas.
- `PlantillaCatalogoConfiguracionTest` conserva los contratos de alias, concurrencia e independencia de depósitos. Añade instrucciones en fila 2, datos desde fila 3 y correspondencia de colores con la guía v2. `NormalizadorEcCatalogoTest` fija la transformación literal solicitada y su excepción al inicio del contenido.
- Los contratos existentes de fotografía, cartografía, usabilidad y ayuda QA8/QA9 conservan las comprobaciones de privacidad, valores originales y formatos de exportación. Sus expectativas de presentación usan ahora las cabeceras de la plantilla madre y distinguen consulta pública de descarga autorizada. El visor comprueba expresamente ambos estados del enlace de descarga, con sesión curatorial en memoria en su prueba sin base de datos.
- El recorrido completo de publicación detectó una carrera entre el cierre nativo del árbol y su reapertura desde el botón. Node añade el evento de cierre diferido: la reapertura inmediata conserva la nueva sesión, su búsqueda y el foco, y el cierre anterior no la cancela. La comprobación de navegador de cierre exterior y reapertura conserva sus aserciones.
- `GeneradorPlantillaImportXlsxTest` mantiene la generación y lectura real del XLSX sin arrancar Laravel. La referencia de colores se resuelve desde la ubicación de los módulos, de modo que esos casos no dependan de que otra prueba haya cargado previamente la referencia en caché estática.
- Estas pruebas se ejecutan únicamente mediante `crear-paquete-oci`. La modalidad `-SoloValidacionLocal`, autorizada junto con la corrección de fallos y los reintentos completos, terminó con código 0 el 24 de octubre de 2026: 1.629 pruebas PHP, 10.400 aserciones, 246 contratos JavaScript, 83 escenarios Gherkin y 11 pruebas ETE aprobados, junto con Java, compilaciones, Opera Neon y PDF. No se ejecutaron suites aisladas. Evidencia: `.local/deployment/validacion-local-catalogo-20261024-171854.log`. Node conserva el caso de ayuda sin acceso explícito y añade apertura pública mediante ratón y teclado; su adaptador Leaflet ejecuta los inicializadores de mapa. Opera Neon comprueba las opciones públicas Ver registros y el cierre exterior del árbol con recuperación del foco, manteniendo la exclusión de descargas anónimas.
- La revisión UX complementaria en Opera Neon, perfil dedicado de QA y zoom inicial 75 %, observó mapa y tabla anónimos en localhost. El recorrido Acciones → Columnas → Ocultar todas dejó las 26 casillas sin marcar y, tras Aplicar, cero cabeceras visibles. Se restauró la vista. No hubo errores de consola en esas páginas; se observaron dos avisos de precarga de assets. La compilación disponible era anterior a los cambios de módulos JavaScript/CSS, por lo que ese recorrido no valida su compilación ni sus nuevas interacciones. Tampoco valida las pantallas dependientes de las migraciones nuevas. No se añadieron escenarios Gherkin duplicados.
- En el recorrido visual posterior solicitado por el usuario se habilitaron las migraciones necesarias en localhost y se sirvieron los fuentes mediante Vite de desarrollo. Opera Neon comprobó editor global con publicación y restauración de un título del mapa, geografía inicial y guardado del formulario de Especímenes, creación/retiro de una columna temporal sin uso, cero columnas de datos visibles y restauración en ambas grillas, conservación de scroll y selección múltiple de Filo, búsquedas compartidas de localidad/taxón, árbol arrastrable con cierre exterior, pantalla completa en los dos mapas activos y cabecera en escritorio/móvil. El flag se guardó en ambos estados; una cuenta local de depositante perdió sus exportaciones al deshabilitarlo y descargó un CSV pequeño al habilitarlo. Se restauraron textos, columnas y permiso iniciales. La revisión automática rechazó el intento de comprobar visualmente el bloqueo de eliminación de localityName; se conservó la columna y se revisó su guardia mediante lectura del código. La plantilla descargada se revisó en una vista local de valores/rellenos con Neon, no en Excel nativo. Se corrigieron la llamada getComment y la directiva del botón de eliminación. El alcance detallado y las limitaciones están en `docs/ajustes-catalogo-20261024.md`. Después se aprobaron las suites y compilaciones integrales en la modalidad local; no se añadieron Gherkin duplicados.


## Ayudas, avisos y publicación GBIF (25 de octubre de 2026)

- `PortalRegistrosUsabilidadTest` y `PortalColumnasCuradorTest` comprueban las 31 columnas públicas, los cinco rangos taxonómicos añadidos, sus permisos, la composición del nombre sin duplicar género y la presentación del identificador provisional original sin cambiar el taxón confirmado. La separación visual de mostrar/ocultar columnas se revisa en localhost con Opera Neon; no se añaden escenarios Gherkin duplicados. Las suites se ejecutan únicamente mediante el paquete completo.
- El paquete invoca `verify-deposit-pdf.php --medir-tiempo-local`: exige entorno `local`, URL de loopback y los límites de tres segundos en creación y verificación. La activación en VM ejecuta el mismo contrato criptográfico completo (firma válida, alterada, ausente, estructura y contenido activo), con la medición de rendimiento reservada a localhost, conforme al acuerdo del proyecto. Se conservan los límites operativos y las aserciones criptográficas. No se ejecutan pruebas aisladas tras un fallo.

- Se retiran las instrucciones de interfaz del portal, catálogo, administración, préstamos, depósitos y cuenta. Se conservan etiquetas, estados, datos curatoriales, información legal y confirmaciones de acciones. La guía incorporada en los Excel y los documentos generados no cambia.
- `hub-notifications.test.mjs` cubre el presentador común: texto sin HTML, conservación del foco, cierre a los cuatro segundos, cierre manual, lectura con foco, acción del expediente, eventos existentes, agrupación de errores de formulario y fallos HTTP sin exponer la respuesta técnica. La PWA conserva su registro y confirmación de entrega; comparte solamente el presentador. No se duplica esta cobertura en Gherkin.
- `DepositosUxEstadosTest` comprueba los estados de procesamiento, ausencia de archivos y acceso al expediente sin exigir la ayuda retirada. Conserva los contratos del QR, criterios de recepción y configuración de textos.
- Publicación GBIF usa el ancho disponible, dos columnas de configuración en escritorio y una en móvil. Se agrupan identificación y tipo de registro; EML y exportaciones conservan el ancho completo.
- En producción se guardaron los metadatos institucionales que ya constan en 158 filas originales del catálogo y se actualizó únicamente el nombre del administrador desde su perfil. La descarga IPT posterior, filtrada por Acanthinomyia, contiene 69 columnas y un registro con los cuatro obligatorios, institución, colección, derechos y licencia CC BY-NC 4.0. Evidencia: `.local/deployment/prod-config-dataset-9200adae-20261025.log`. Esto no acredita la completitud científica de los demás registros.
- La validación integral y la publicación de este encargo se realizan mediante `crear-paquete-oci` completo, con corrección de fallos y reintentos completos autorizados. No se ejecutan suites aisladas. Los registros se conservan en `.local/deployment/ajustes-prod-paquete-completo-20261025-*.log`; la activación debe conservar la identidad del commit publicado.

## Atribuciones fotográficas y páginas ampliadas (27 de octubre de 2026)

`CatalogoAtribucionesFotografiasTest.php` cubre el editor Livewire, los cinco metadatos opcionales, carga del raster sin reescritura, almacenamiento/persistencia, actualización sin alterar autoría histórica/huella/portada, escape del contacto, límites de entrada y acceso al editor. Comprueba páginas de 500 y 1000 con selección y orden en servidor, sin cambiar el límite de localidad. `AtribucionImagenTest.php` conserva los contratos de texto y tipos de vista; `VisorImagenPortalTest.php` comprueba ubicación del bloque después de la imagen, los cinco rótulos y los permisos de descarga existentes.

El escenario Behat ya existente de imágenes por nivel conserva vínculo, propagación y orden de portada; su expectativa de marca se adapta al cambio solicitado: bytes originales intactos, ninguna marca aplicada y fotógrafo en el DTO de atribuciones. No se agrega un escenario duplicado de Pest. Los clientes históricos de nombre/apellido conservan validación, mientras el editor nuevo utiliza Photographer completo y permite información pendiente. La migración solo recupera autoría histórica conocida y la licencia CC BY 4.0 de las marcas aplicadas por el sistema; no inventa titular, institución o contacto.

La revisión manual complementaria en Opera Neon se reserva para presentación, foco, menús y adaptación móvil. Las suites anteriores quedan pendientes de la siguiente ejecución autorizada de `crear-paquete-oci`; no se ejecutan aisladamente. Las fotos históricas cuyo archivo ya contiene una marca requieren el original sin marca para retirarla: no se recortan escalas ni se sintetizan píxeles.

`auth-forms.test.mjs` agrega el formulario anterior de login enviado después de que otra petición ya autenticó la sesión: si Fetch recibió HTML por una redirección exitosa, usa únicamente su destino final del mismo origen. Conserva errores 419, rechazo de destinos externos y respuestas JSON por perfil/CSRF. Este contrato nuevo también queda pendiente del paquete autorizado.

## Tema elegido y recuperación de contraseña (28 de octubre de 2026)

`hub-appearance.test.mjs` cubre el inicio en claro sin preferencia válida aunque el sistema esté oscuro, la persistencia de claro/oscuro tras recargar con el sistema opuesto, el paso por el portal público sin perder la preferencia y la opción Sistema únicamente cuando se selecciona expresamente. El portal conserva su paleta propia. `hub-turnstile.test.mjs` comprueba que el widget recibe el tema efectivo de la aplicación; conserva los casos de token, caducidad, error, reintento y navegación. Estas pruebas se ejecutan exclusivamente dentro de `crear-paquete-oci`; no se agregan escenarios Gherkin duplicados.

La separación visual y las áreas pulsables de recuperación/visibilidad, el foco y la adaptación móvil se revisan complementariamente en localhost con Opera Neon al 75 %. La semántica del campo, el POST de Fortify y el flujo de recuperación conservan su cobertura Pest existente.

## Cambios del 28/10/2026: validación distribuida y botones RBAC

`tests/Frontend/evidencia-agente.test.mjs` comprueba integridad, caducidad y rechazo
ante cambios de fuentes o runtime; se ejecuta con todos los contratos Node del
bloque del agente. La matriz y sus reglas se describen en
`docs/validacion-agente-y-paquete.md`.

Pest cubre asignación y cambio entre perfiles internos/externos, rechazo de roles
no asignados, ayudantes sin administración, revocación inmediata, permisos de ver
sin usar, edición concurrente y límites de divulgación en `Auth/BotonesRbacTest.php`
y `ExportacionExternaRbacTest.php`. Incluye hoja grande y limpieza de temporales al
fallar. No se agrega Gherkin duplicado: los contratos HTTP, Livewire y persistencia
quedan en Pest; Gherkin conserva sus flujos completos existentes.