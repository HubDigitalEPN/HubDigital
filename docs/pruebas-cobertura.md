# Distribución de cobertura

El árbol de diversidad inicial tiene contratos exclusivos en `tests/Frontend/portal-taxonomy-diversity.test.mjs`: multifurcaciones y padres publicados, sectores contiguos, cadenas profundas, ramas extensas, terminales, encuadre, precisión de zoom hacia el cursor, conservación de distancias y topología, composición orgánica sin pisos, radios decrecientes y cajas con profundidad real. `tests/Frontend/portal-taxonomy-tooltip.test.mjs` cubre solicitudes de leyenda agrupadas, aborto, respuestas tardías, cierre y fallos de red sin alterar la selección; los permisos del endpoint ya pertenecen a Pest. No se duplican en Gherkin. `tests/Browser/portal-livewire.mjs` comprueba la perspectiva inicial, acercamientos superiores a 3×, restauración con Inicio, Ctrl + arrastrar sin cambiar la raíz ni trasladar el centro de la vista, giro con teclado, iconos de igual tamaño sin Ajustar ni Reintentar y alternancia retirando el motor previo; conserva la regresión completa de esferas. Las suites se ejecutan exclusivamente dentro de `crear-paquete-oci`. La inspección manual complementaria con Opera Neon se reserva para legibilidad, volumen y composición, aparición progresiva de clados, leyendas del catálogo real, foco, adaptación y fluidez.

`PortalFichasTaxonomicasTest.php` cubre conversión y carga AVIF, límite de 32 KB y 360 × 270 píxeles, leyenda editable, permisos, compatibilidad con fotos WebP anteriores y rechazo de un formato falsificado. `ImportadorFichasAvifTest.php` cubre linaje completo, fuente y licencia, cuota de especie con correspondencia exacta, integridad de los bytes, idempotencia y conservación de ediciones concurrentes. `PortalExploradorTaxonomicoTest.php` y `TaxonomiaPublicaQa4Test.php` cubren los separadores Unicode/NBSP y el último ancestro confirmado; no se agregan escenarios Gherkin duplicados. El inventario, la disponibilidad real de objetos R2 y la cobertura editorial por rango se revisan sobre los datos del ambiente, sin declararlos aprobados a partir de fixtures de prueba.

El zoom por rangos tiene contratos en `portal-taxonomy-diversity.test.mjs`: comienzo en Clase, habilitación ordenada hasta Especie, zoom inverso, selección inicial de Familia y omisión de rangos realmente ausentes. La revisión visual comprueba que la apertura comienza en el filo completo, que sólo hay nodos y leyendas habilitados para el nivel actual y que los clados se descubren al acercarse. La jerarquía izquierda conserva acceso a todos los taxones publicados independientemente del zoom.

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
