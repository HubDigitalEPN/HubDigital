# Diagnóstico QA6-001 y reducción del agregado de especies

## Evidencia histórica del origen

Se consultó OCI por SSH en modo de lectura, sin solicitar páginas de la aplicación,
ejecutar consultas de negocio, pruebas o un despliegue. Los registros se procesaron
en la VM y únicamente se devolvieron contadores y duraciones, sin líneas originales,
rutas de solicitudes, filtros nuevos, cuentas, sesiones ni texto SQL.

En la ventana de marcas 2026-10-03 23:03–23:20:59 correspondiente al incidente:

- Se leyeron 34 archivos conservados y 52 líneas de esa ventana, sin archivos inaccesibles.
- Hubo tres cancelaciones por tiempo de PHP-FPM y tres salidas de trabajadores.
  Las duraciones de ejecución canceladas fueron 242.945,0; 241.090,6 y 241.007,7 ms.
- No se detectaron en esa ventana las categorías buscadas de memoria agotada,
  cancelación de sentencia PostgreSQL o saturación explícita de `pm.max_children`.
  Esto se limita a los registros disponibles y las categorías procesadas.
- La URL pública exacta documentada en QA6 devolvió dos estados 499 en el registro
  de acceso del origen. Ese estado registra el cierre de la conexión del cliente o
  proxy mientras el origen atendía; no sustituye la evidencia del 524 de Cloudflare.
- El Ray ID aportado por QA6 no apareció en las líneas conservadas. No se asigna
  una correspondencia directa de cada trabajador con ese Ray ID.
- Las cuatro pilas del slowlog de la ventana estaban detenidas en ejecución SQL
  mediante PDO/Illuminate Connection. Los tres trabajadores cancelados pertenecían
  también al conjunto de esas pilas.

El punto de llamada común fue `PortalEstadisticas.php:247` en la versión QA6
`f0e81e60e14fabbfb85f7516cc5b020ec7a7006f`. En ese código es la consulta de especies
con `JOIN taxones`, selección de todos los UUID de linaje válido, agrupación por
nombre científico, conteo y límite de veinte resultados. Las pilas incluyen el
cálculo de una entrada no disponible en caché (`PortalEstadisticas.php:211`) y la
entrada del mapa desde `PortalCatalogo.php:922`.

La evidencia localiza una demora en la ejecución del agregado SQL de especies.
No demuestra qué plan de consulta, espera de bloqueo o condición del motor produjo
esa demora: no se ejecutó `EXPLAIN`, no se extrajo SQL histórico ni se reprodujo la
consulta en el servidor. Tampoco respalda atribuir el incidente al móvil o a llamadas
de fotografías externas.

## Recursos y límites observados después del incidente

La lectura actual de configuración mostró `pm.max_children=2`, terminación FPM
a 240 segundos, slowlog a 15 segundos y lectura FastCGI Nginx a 240 segundos.
PHP tenía `memory_limit=128M`; PostgreSQL, `shared_buffers=128MB` y
`max_connections=100`. La VM mostraba 954 MiB de RAM, 366 MiB disponibles y sin swap
al obtener la instantánea. Esa lectura posterior no mide memoria, CPU o carga en
el momento histórico del fallo.

Dos trabajadores y tiempos de terminación amplios permiten que una consulta lenta
ocupe capacidad durante varios minutos. Es una condición compatible con el impacto,
sin evidencia suficiente para convertir el tamaño de la VM en causa exclusiva.

## Cambio localizado y conservación del contrato

`resumir` ya obtiene `$porTaxon` desde la selección pública completa con
`scientific_name_visible=true`, filtros aplicados y totales separados por UUID y
banderas de familia/género. La consulta SQL lenta de especies repetía esa misma
población para agrupar nombres. La consulta de especies poco representadas repetía
el conteo y sólo añadía el umbral de tres y otro límite.

`ResumenEspeciesPublicas` deriva ambas listas de los conteos y del mapa taxonómico
ya cargados. Exige rango especie y linaje válido, conserva los nombres originales
y suma todos los UUID y variantes de permisos que comparten el mismo nombre antes
de aplicar rankings o umbral. Conserva veinte especies principales y doce poco
representadas; añade un desempate determinista por nombre. No consulta ni hidrata
ejemplares adicionales y conserva la selección/permisos del agregado de origen.

En la siguiente medición de Edge con la selección real restaurada, especies ya
tardó aproximadamente 2,1 ms, pero riqueza fue cancelada al alcanzar 8,06 segundos;
el resumen tardó 684 ms y los conteos por taxón 239 ms. La reducción inicial de
bindings con `ANY` conservaba el cruce taxonómico costoso de riqueza.

Riqueza y décadas ahora consultan únicamente conteos por UUID y provincia
normalizada/década, con los mismos filtros, publicación y permisos específicos.
`ResumenDistribucionPublica` resuelve rango especie y linaje válido en el mapa
taxonómico ya cargado, suma registros y cuenta nombres distintos dentro de cada
grupo. Conserva Nariño/Chocó canónicos, diez provincias y el orden cronológico.
No carga ejemplares ni limita grupos antes de calcular su diversidad.

La descarga de la lista y el resumen principal conservan el array PostgreSQL
parametrizado con `ANY(?::uuid[])`. Mantiene la misma pertenencia, incluido un
conjunto vacío, y evita miles de parámetros individuales.

La caché analítica pasa a `v19` para no reutilizar la construcción anterior. Se
conservan revisión de datos, selección y límite de caché. El presupuesto de consulta
y la salida recuperable del mapa se mantienen como protección complementaria.

## Revisión y pendientes

Se revisaron estáticamente fuentes, SQL construido, filtros, permisos, límites y
cache. La pertenencia taxonómica del repositorio conserva sus recorridos con límite
y protección de ciclos; no se modificó su política de revisión curatorial.
El agregado de especies elimina dos reconsultas demostrablemente redundantes.

Las pruebas puras nuevas comprueban multiplicidad de UUID/nombres y permisos de
familia/género, exclusión de rango superior, linaje inválido, nombre pendiente,
umbrales después de sumar y límites/desempates. Se escribieron sin ejecutarlas.
Las pruebas puras de distribución comprueban grafías, UUID duplicados, nombres
distintos por grupo, exclusiones curatoriales y límites aplicados al final. Una
prueba PostgreSQL nueva reserva separadamente provincia, fecha y nombre, y comprueba
los totales de riqueza/décadas con identificaciones de especie y rango superior.
Todas se escribieron sin ejecutarlas. Las pruebas UX existentes siguen siendo
necesarias para verificar la navegación real.

Quedan pendientes el paquete integral autorizado y la medición de la URL real tras
el nuevo despliegue, incluyendo datos o error recuperable, tiempos y registros de
las etapas. La reducción del SQL no se presenta como una mejora de latencia medida.

La exportación inicial de líneas sanitizadas fue rechazada por revisión automática
porque podía trasladar rutas y filtros de solicitudes al entorno local sin una
autorización específica para ese contenido. Se sustituyó por procesamiento agregado
en la VM; esa alternativa fue aceptada y no exportó las líneas rechazadas.
