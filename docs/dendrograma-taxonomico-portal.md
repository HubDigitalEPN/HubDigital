# Dendrograma del modal de registros

## Diseño y límites científicos

El gráfico representa exclusivamente la jerarquía taxonómica pública de los registros del punto. Conserva nombres, padres, rangos y conteos recibidos; no infiere parentesco evolutivo, edades, longitudes de ramas, nodos ausentes ni ancestros reservados. Los puntos azules del mapa continúan en sus coordenadas publicadas.

Se revisaron las referencias proporcionadas: [árbol humano con ramas curvas](https://nutcrackerman.com/wp-content/uploads/2016/09/phylogenetic-tree-20250718.png), [árboles filogenéticos de Khan Academy](https://www.khanacademy.org/science/biology/her/tree-of-life/a/phylogenetic-trees) y [construcción de un árbol evolutivo](https://www.khanacademy.org/science/biology/her/tree-of-life/a/building-an-evolutionary-tree). Las páginas Khan abiertas no ofrecieron texto íntegro; la búsqueda oficial confirmó su tema. Se adoptan solo recursos de composición (ramificaciones, tipografía científica, nodos discretos), sin trasladar las hipótesis o escalas temporales de esas referencias al inventario.

El concepto visual generado e inspeccionado permanece fuera del paquete en `C:\Users\Usuario\.codex\generated_images\01a0fd47-71d9-7461-a357-d440fa030b43\exec-d6470a02-26b6-49e5-84ed-1468e742e0bc.png`. Es un boceto con ejemplos, no una captura del sistema ni datos de la colección. Se implementa únicamente su composición del gráfico izquierdo; la ficha derecha conserva la representación propia de especie y los datos reales ya existentes. No se incorporan la fotografía, coordenadas o valores de muestra del boceto.

## Composición e inventario

- Fondo blanco, curvas SVG finas azules y verdes, círculos discretos y selección azul. No hay tarjetas grandes ni cadena horizontal de diez columnas.
- Los nombres científicos usan Georgia en cursiva; cada nodo muestra su rango y registros. Suborden, Subfamilia y Tribu se mantienen, junto con los rangos primarios y otros rangos recibidos.
- La composición diagonal crece verticalmente. Los ancestros y las bifurcaciones mantienen filas distintas; el paso horizontal se adapta a la profundidad máxima para conservar espacio de lectura en móvil. Cada hijo avanza respecto de su padre, incluso al pasar de Género a Especie en un linaje de diez niveles: las hojas hermanas no se superponen en una única línea. El SVG adapta su ancho y conserva el grosor de los trazos; las etiquetas son botones HTML nativos, con tipografía estable y foco visible.
- Las hojas disponen de miniaturas de grupo únicamente si el linaje público recibido permite escoger una representación existente. Son recursos generados compartidos, no fotografías auténticas ni identificaciones de especie.
- El contenedor desplaza verticalmente; las etiquetas permanecen dentro de su ancho. En móvil la ficha se coloca debajo según el diseño responsive existente.

Fuentes: `DendrogramaTaxonomico.php` calcula geometría sin consultas; `dendrograma-mapa.blade.php` dibuja curvas; `nodo-arbol-mapa.blade.php` crea controles y etiquetas; `detalle-celda-mapa.blade.php` integra estado/páginas; `portal-estadisticas.css` define composición, color, tipografía y foco.

La ficha derecha utiliza `representacion-especie.blade.php` con un retrato generado grande de hasta 320 px, nombre propio y advertencia de procedencia. La clasificación se ofrece mediante `details/summary` y una lista de definición legible; conserva todos los ancestros públicos recibidos y no repite el dendrograma mediante minitarjetas. La lista pública `jerarquia.ancestros` tiene prioridad sobre campos resumidos, incluidos los rangos intermedios, repetidos o desconocidos. Cuando no hay representación morfológica la clasificación permanece abierta y no se inventa una anatomía.

## Datos, páginas y selección

El backend reutiliza agregados públicos del punto para mostrar hasta **12 taxones terminales reales y todos sus ancestros publicados** por página. No hidrata todos los ejemplares. La apertura muestra las ramificaciones disponibles; una ubicación monoespecífica conserva una cadena única.

El conjunto usa `arbol_registros_total`, `arbol_hojas_total`, `arbol_pagina`, `arbol_ultima` y `paginarArbolCelda`. La ficha mantiene su propio taxón, conteo y página de ejemplares. Al cambiar de página del árbol puede quedar abierta una ficha cuyo nodo ya no es visible; un aviso explica este estado. No se fusionan claves de linaje distintas que compartan un UUID taxonómico.

El gráfico no reconstruye padres no publicados. Ante datos inválidos evita ciclos y conserva separados los nodos, sin fabricar enlaces. La selección y sus ancestros visibles resaltan su camino. Los botones incluyen nombre, rango, conteo y padre público en su etiqueta accesible.

## Revisión y cobertura

La revisión de este cambio es estática: contratos, relaciones, escape, geometría, estados, foco, composición CSS y permisos aportados por el backend. No se ejecutaron aplicación, navegador, suites o compilaciones independientes por el agente de UI. La comparación visual con el producto corresponde al recorrido manual coordinado; el resultado de compilación y las pruebas corresponden exclusivamente al siguiente `crear-paquete-oci` completo.

`DendrogramaTaxonomicoTest` aporta cuatro contratos distintos: diez rangos/bifurcaciones con geometría compacta, una sola especie y padre no publicado, identidad de linajes/ciclos y render accesible con nombres escapados. El caso backend de overview prueba páginas de 12 hojas, permisos y selección directa; esas consultas no se duplican en la prueba de geometría.

Casos UX pendientes de comprobar visualmente: punto con varias especies frente a punto con una sola; nombres largos a 390 px; selección por Tab/Enter/Espacio; foco visible sin cortes; siguiente página del árbol manteniendo ficha; selección fuera de página; Escape y retorno al marcador; legibilidad de Suborden/Subfamilia/Tribu; carga/error inmediatos del modal.
