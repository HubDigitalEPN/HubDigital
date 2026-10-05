# Dendrograma del modal de registros

## Diseño y límites científicos

El gráfico representa exclusivamente la jerarquía taxonómica pública de los registros del punto. Conserva nombres, padres, rangos y conteos recibidos; no infiere parentesco evolutivo, edades, longitudes de ramas, nodos ausentes ni ancestros reservados. Los puntos azules del mapa continúan en sus coordenadas publicadas.

Se revisaron las referencias proporcionadas: [árbol humano con ramas curvas](https://nutcrackerman.com/wp-content/uploads/2016/09/phylogenetic-tree-20250718.png), [árboles filogenéticos de Khan Academy](https://www.khanacademy.org/science/biology/her/tree-of-life/a/phylogenetic-trees) y [construcción de un árbol evolutivo](https://www.khanacademy.org/science/biology/her/tree-of-life/a/building-an-evolutionary-tree). Las páginas Khan abiertas no ofrecieron texto íntegro; la búsqueda oficial confirmó su tema. Se adoptan solo recursos de composición (ramificaciones, tipografía científica, nodos discretos), sin trasladar las hipótesis o escalas temporales de esas referencias al inventario.

El concepto visual generado e inspeccionado permanece fuera del paquete en `C:\Users\Usuario\.codex\generated_images\01a0fd47-71d9-7461-a357-d440fa030b43\exec-d6470a02-26b6-49e5-84ed-1468e742e0bc.png`. Es un boceto con ejemplos, no una captura del sistema ni datos de la colección. Se implementa únicamente su composición del gráfico izquierdo; la ficha derecha conserva la representación propia de especie y los datos reales ya existentes. No se incorporan la fotografía, coordenadas o valores de muestra del boceto.

## Composición e inventario

- Fondo blanco, conexiones CSS discretas y selección azul. Los controles son botones HTML nativos en listas anidadas que conservan las relaciones públicas de padre e hijo.
- Los nombres científicos usan Georgia en cursiva; cada nodo muestra su rango. El conteo de registros aparece en especie. Suborden, Subfamilia y Tribu se mantienen, junto con los rangos primarios y otros rangos recibidos.
- El tronco utiliza espacio vertical hasta Orden; las ramas inferiores se distribuyen horizontalmente y se ajustan al ancho disponible. Las ramas con más de seis hijos se pueden plegar mediante `details/summary`; la ruta seleccionada permanece abierta. No se fuerza un alto vacío ni una cadena de diez columnas.
- Cada ejemplar termina en una hoja de registro, incluso si solo existe identificación hasta familia. No se inventa una especie ni se reconstruyen ancestros reservados. Una imagen de referencia nunca sustituye la ficha del registro.
- El contenedor limita su alto y permite desplazamiento cuando es necesario. En móvil la ficha se coloca debajo según el diseño responsive existente.

Fuentes: `DendrogramaTaxonomico.php` conserva el contrato compartido de geometría y normaliza relaciones sin consultas; `dendrograma-mapa.blade.php` organiza raíces; `rama-arbol-mapa.blade.php` dibuja listas y ramas adaptables; `nodo-arbol-mapa.blade.php` crea controles y etiquetas; `detalle-celda-mapa.blade.php` integra selección/ficha; `portal-taxonomy-dialogs.css` define la composición adaptable.

La ficha derecha muestra los campos divulgables del UUID seleccionado y su fotografía publicada cuando existe. La referencia taxonómica y las fotografías externas identificadas conservan sus propias fuentes y permisos. La lista pública `jerarquia.ancestros` conserva los rangos intermedios, repetidos o desconocidos recibidos, sin reconstruir rangos ocultos.

## Datos, páginas y selección

El backend reutiliza agregados públicos del punto y obtiene únicamente UUID, permisos de clasificación y código público para construir **todas las hojas de registro y sus ancestros publicados**. Hidrata los detalles de la hoja seleccionada; la tabla conserva seis registros por página. Un punto con un solo ejemplar abre directamente su ficha.

El conjunto usa `arbol_registros_total`, `arbol_hojas_total`, `registros_arbol` y `registro_seleccionado`. Se retiraron `arbol_pagina`, `arbol_ultima`, `paginarArbolCelda` y los botones Anterior/Siguiente del árbol. `paginarCelda` actúa exclusivamente sobre la tabla. No se fusionan claves de linaje distintas que compartan un UUID taxonómico; las hojas de ejemplar usan `registro:<UUID>` y solo se puede seleccionar un UUID de la ubicación pública vigente.

El gráfico no reconstruye padres no publicados. Ante datos inválidos evita ciclos y conserva separados los nodos, sin fabricar enlaces. La selección y sus ancestros visibles resaltan su camino. Los botones incluyen nombre, rango, conteo y padre público en su etiqueta accesible.

## Revisión y cobertura

La revisión de esta ampliación del 5 de octubre de 2026 es estática: contratos, relaciones, escape, estados, estructura accesible, composición CSS y permisos aportados por el backend. El usuario canceló la comprobación visual; no se certifica apariencia, interacción o rendimiento real. El resultado de compilación y las pruebas corresponden exclusivamente al siguiente `crear-paquete-oci` completo.

`DendrogramaTaxonomicoTest` conserva geometría, una sola especie, padre no publicado y linajes/ciclos, y actualiza el contrato del render a relaciones HTML anidadas y tronco vertical/ramas horizontales con nombres escapados. `PortalCartografiaRealTest` recorre todas las hojas de un punto, su UUID real, permisos y selección directa; mantiene la paginación exclusiva de la tabla. `PortalMapaFiltrosAutomaticosTest` añade identificación hasta familia y apertura de un registro con identidad científica/código reservados. Estas consultas no se duplican en la prueba de geometría ni en Gherkin.

Casos UX sin comprobar visualmente por instrucción del usuario: punto con varios registros frente a uno solo; nombres largos a 390 px; selección por Tab/Enter/Espacio; foco visible; plegado de ramas; Escape y retorno al marcador; legibilidad de Suborden/Subfamilia/Tribu; carga/error inmediatos del modal.
