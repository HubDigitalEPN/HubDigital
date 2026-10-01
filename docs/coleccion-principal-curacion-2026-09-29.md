# Colección principal: carga, curación y exploración

Fuente: pestaña `Colección_principal` de `Catálogo lab invertebrados EPN (25-sep-2026).xlsx`. SHA-256 del archivo fuente: `8ad5eae2eb4935d8629c9b42384b7eb5806f9be109de469dd9ccb5db44bd7114`. La carga se ejecutó con el importador existente sobre la base PostgreSQL que utiliza el portal de desarrollo. La procedencia, fecha y bitácora de importación se conservaron en OCI. El Excel no quedó almacenado en OCI; se retiró el CSV temporal descomprimido de 34 MB tras verificar la carga. Se conservaron el archivo comprimido, las bitácoras y los respaldos.

## Verificación en la base real

| Medida | Resultado |
| --- | ---: |
| Filas del Excel distintas | 49.696 |
| Especímenes en la base | 49.696 |
| Especímenes con configuración de divulgación | 49.696 |
| Registros sensibles con localidad o coordenadas públicas por error | 0 |
| Fechas normalizadas | 34.940 |
| Fechas textuales pendientes de interpretación | 83 |
| Coordenadas numéricas recuperadas del Excel | 3.998 |
| Coordenadas con referencia INEC aproximada | 2.230 |
| Coordenadas todavía vacías | 17.042 |
| Referencias INEC descartadas por discrepancia superior a 50 km | 57 |
| Localidades nuevas del catálogo | 1.277 |

Las 1.277 altas se detallan, una por fila y con código, provincia, nombre, cantón, parroquia y procedencia, en [localidades-nuevas-coleccion-principal-2026-09-29.csv](datos/localidades-nuevas-coleccion-principal-2026-09-29.csv). Son nombres de campo que no pudieron homologarse con seguridad a un nombre exacto del INEC; no se presentan como nombres oficiales. El texto original del Excel permanece en `localidad_verbatim`. Las localidades homologadas conservan el código y nombre INEC por separado. El cotejo se basó en la capa `loc_p` del [Marco Geoestadístico Nacional 2021 del INEC](https://www.ecuadorencifras.gob.ec/documentos/web-inec/Geografia_Estadistica/Documentos/GEODATABASE_NACIONAL_2021.zip).

Se recuperaron coordenadas numéricas con el separador decimal omitido solo cuando el punto resultante estaba en la provincia declarada y a menos de 50 km de una referencia geográfica del INEC. Se descartaron 517 candidatos que no pasaron esa comprobación. Las 57 referencias INEC previas incompatibles con la coordenada recuperada se desvincularon, sin modificar el texto de campo. Los puntos derivados de una localidad se etiquetan como aproximados; no deben interpretarse como el lugar exacto de colecta. Los registros que solo dicen «Ecuador» o no describen un sitio permanecen sin coordenadas.

La clasificación observable de los 49.696 registros quedó en: Arthropoda 34.078; Mollusca 613; Annelida 4; Nematoda 3; Nematomorpha 1; sin filo confirmado 14.997. No se infirió un filo para filas sin clase, orden, familia, género ni nombre científico. La interfaz del curador permite revisar también esas filas. Los valores textuales de fecha no interpretables permanecen en `fecha_verbatim` y la fecha normalizada queda vacía.

## Funciones del portal y límites

La búsqueda ofrece taxonomía, código, provincia y localidad, colector, fecha, método, bioma, elevación, coordenadas, hábitat, microhábitat, condición de tipo, casta y estadio de vida. Los filtros están en un panel izquierdo plegable y se conservan al cambiar entre tarjetas, registros y mapa dentro del mismo componente Livewire. La tabla pública permite paginar y descargar los resultados filtrados en CSV. La descarga usa un cursor para limitar memoria y respeta las marcas de divulgación por campo. El mapa agrupa los puntos públicos en cuadrículas de 0,25°, permite destacar un filo y seleccionar un rectángulo para abrir los registros de esa área. El análisis presenta riqueza documentada por provincia, cobertura temporal, completitud y listas de especies; sus paneles y la lista completa de especies se pueden descargar en CSV.

La selección funcional se comparó con la [búsqueda avanzada de AntWeb](https://www.antweb.org/advSearch.do), sus [herramientas](https://www.antweb.org/tools.do) y la [guía de uso](https://www.antweb.org/user_guide.jsp), así como con la [formación de GBIF sobre búsquedas, estadísticas y descargas](https://training.gbif.org/es/intro-to-gbif/data-access). Las funciones equivalentes incorporadas son filtros por identificación y características del ejemplar, exploración taxonómica y geográfica, mapa, estadísticas, listas regionales, exportaciones y comparación lado a lado de dos especies por registros, provincias, fechas y tipos divulgados. La comparación usa agregados en PostgreSQL. AntWeb y GBIF son plataformas de alcance mundial con colecciones, medios y servicios externos propios; la base EPN tiene actualmente **cero fotografías** publicadas. La galería de especie ya presente podrá mostrar imágenes cuando el curador las publique. La comparación fotográfica, una guía ilustrada y los servicios globales de AntWeb no están implementados ni pueden presentarse como disponibles con la colección actual.

Leaflet se incluye en el paquete local de Vite para que el mapa no dependa de cargar la biblioteca desde un CDN. La cartografía usa mosaicos públicos de OpenStreetMap con la atribución y la URL previstas por su [política de uso](https://operations.osmfoundation.org/policies/tiles/); ese servicio no ofrece una garantía de capacidad ilimitada. Si fallan los mosaicos, permanecen visibles el contorno local de Ecuador, Colombia y Perú, derivado de [Natural Earth 1:50m, dominio público](https://github.com/nvkelso/natural-earth-vector), y los puntos de la colección. Las barras de provincias, décadas y completitud se renderizan en HTML con datos del servidor, por lo que no dependen de que Chart.js se inicialice en el navegador.

## Análisis para el curador

De la carga, 8.573 registros pertenecen a especies identificadas y tienen conteo individual positivo: 1.735 especies y 13.161 individuos contados. Es la base elegible para los índices de abundancia. Las consultas agrupan por especie en PostgreSQL y transmiten las incidencias de muestras secuencialmente para no cargar los 49.696 ejemplares en memoria. El curador puede solicitar uno, varios o todos los cálculos: Shannon-Wiener, Simpson D (probabilidad de coincidencia sin reemplazo), Margalef, Menhinick, Pielou, alfa, beta de Sørensen entre dos localidades, gamma, abundancias absoluta y relativa, curva observada de acumulación, Chao1 y Jackknife de primer orden. Se explicitan `N`, `S` y el número de registros elegibles en cada resultado. Si no hay muestras o dos localidades comparables, el resultado correspondiente queda vacío.

La cuenta `adrian.troya@epn.edu.ec` existe en la base real, tiene correo verificado y rol `ADMIN`. El middleware de la ruta permite a un curador y al administrador; no se cambió ninguna credencial ni membresía.

Las fórmulas siguen la documentación de [diversidad](https://vegandevs.github.io/vegan/reference/diversity.html), [beta](https://vegandevs.github.io/vegan/reference/betadiver.html), [acumulación](https://vegandevs.github.io/vegan/reference/specaccum.html) y [estimación de riqueza](https://vegandevs.github.io/vegan/reference/specpool.html) del paquete ecológico vegan. La curva implementada es la riqueza **observada** al añadir muestras en orden de identificador; no se presenta como una extrapolación del esfuerzo de muestreo. No se ajustan modelos depredador-presa porque el Excel no aporta relaciones tróficas verificadas ni series comparables de abundancia de dos poblaciones.

Estos cambios de código están en la rama de trabajo. La validación automatizada, publicación en `main` y paquete OCI corresponden al comando `crear-paquete-oci`, ejecutado por el usuario según `AGENTS.md`. Hasta entonces el portal desplegado continuará mostrando la versión anterior, aunque las filas y la curación de datos ya estén en la base real.
