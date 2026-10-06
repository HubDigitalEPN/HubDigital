<?php

return [
    'estacionalidad' => [
        'titulo' => 'Estacionalidad de colecta',
        'subtitulo' => 'Registros por mes · todos los años seleccionados',
        'nota' => 'Permite elegir meses para revisar el muestreo. No estima actividad ni abundancia natural.',
        'imagen' => 'decadas',
        'foto' => 'Documentación temporal de colectas en el laboratorio.',
        'parrafos' => [
            'Agrupa los registros por el mes inicial de su fecha de colecta pública. Solo usa fechas entre 1800 y la fecha actual; los valores fuera de este intervalo permanecen en el registro original para revisión curatorial.',
            'Seleccionar un mes filtra todos los indicadores y el mapa. Los años seleccionados se mantienen. La cantidad depende del esfuerzo de muestreo y de los datos publicados; no demuestra estacionalidad biológica.',
        ],
    ],
    'altitud' => [
        'titulo' => 'Cobertura altitudinal',
        'subtitulo' => 'Registros por intervalo de elevación pública',
        'nota' => 'Una colecta cuyo intervalo cruza varias franjas aparece en cada una; sus conteos no se suman.',
        'imagen' => 'riqueza',
        'foto' => 'Muestreo de invertebrados en ambientes de distinta elevación.',
        'parrafos' => [
            'Cuenta los registros cuyo intervalo de elevación pública se solapa con cada franja. Cuando solo existe un extremo se usa ese valor. Las franjas van de −500 a 9000 metros; los datos sin elevación quedan fuera de este indicador.',
            'Permite localizar material documentado para comparar ambientes a distintas altitudes. Seleccionar una franja aplica exactamente el mismo criterio de solapamiento a la colección. Los intervalos amplios pueden figurar en más de una franja: sus conteos no deben sumarse para obtener el total.',
        ],
    ],
    'metodos' => [
        'titulo' => 'Métodos de muestreo',
        'subtitulo' => 'Material disponible según técnica de colecta',
        'nota' => 'Filtra por técnica para revisar la comparabilidad del material. No se conoce el esfuerzo de cada muestreo.',
        'imagen' => 'mapa',
        'foto' => 'Material de campo para documentar métodos de colecta.',
        'parrafos' => [
            'Agrupa los registros por una misma clave del protocolo de colecta, sin distinguir mayúsculas y minúsculas ni espacios al inicio o al final. Por ejemplo, Beating y beating forman una sola categoría; el gráfico y su filtro usan exactamente esa población. El método original se conserva en las fichas y en la descarga de especie XLSX. El CSV general de registros no incluye ese campo; el CSV del indicador contiene categorías y conteos agregados. Se omiten campos vacíos y marcadores curatoriales de daño o falta de información.',
            'Seleccionar una técnica mantiene los demás filtros y permite revisar su distribución, periodos y material asociado. El conteo expresa registros disponibles, no eficiencia de captura: comparar técnicas requiere conocer su esfuerzo de muestreo.',
        ],
    ],
    'mapa' => [
        'titulo' => 'Mapa de la colección',
        'foto' => 'Trabajo de campo con GPS y cuaderno para registrar ubicaciones.',
        'parrafos' => [
            'Este indicador muestra las coordenadas públicas de los ejemplares de la selección aplicada sobre la cartografía de OpenStreetMap. Conserva visibles países, relieve, ciudades y demás elementos de la base cartográfica en las áreas con y sin registros.',
            'Al alejar el mapa aparecen clústeres de pantalla que reúnen varias ubicaciones cercanas. Su número indica cuántas ubicaciones originales contienen, no cuántos ejemplares. Al pulsarlos se acerca el mapa y se separan sus miembros; el clúster no es una coordenada de colecta.',
            'Al acercar aparecen los puntos en sus latitudes y longitudes WGS84 originales, sin desplazarlos a un centro de cuadrícula. Un punto reúne únicamente registros que comparten exactamente ese par de coordenadas. Su tamaño expresa la cantidad de registros, con un máximo visual. Selecciona el punto para explorar su árbol: hasta cinco registros aparecen como hojas; con más de cinco, el árbol llega hasta familia y los ejemplares se eligen con un buscador. La tabla adapta los registros por página a la altura disponible.',
            'El color identifica el filo: azul para Arthropoda, naranja para Mollusca, verde para Annelida, violeta para Nematoda y rosa para Nematomorpha; gris indica un filo no disponible. Si una ubicación o un clúster contiene varios filos, sus segmentos muestran la composición. El detalle accesible del punto informa los filos y sus conteos. Seleccionar un filo en Composición taxonómica filtra todos los paneles y el mapa.',
            'La precisión de una ubicación depende del dato original y de su referencia pública: conservar la coordenada no convierte una ubicación aproximada o recuperada en una medición GPS exacta. La selección pública exige ambas coordenadas válidas y visibles; los registros sin ese par o con coordenadas reservadas quedan fuera de los paneles públicos. La ausencia de puntos no demuestra ausencia de organismos y el número de registros no equivale a abundancia natural.',
        ],
    ],
    'filos' => [
        'titulo' => 'Composición taxonómica',
        'foto' => 'Ejemplares ilustrativos de distintos grupos de invertebrados en una mesa de laboratorio.',
        'parrafos' => [
            'La composición taxonómica describe cómo se distribuyen los registros seleccionados entre filos, grandes grupos del árbol de clasificación biológica. Sirve para reconocer los grupos mejor representados y orientar el estudio o la digitalización de la colección.',
            'Para cada registro con identificación pública se recorre su linaje hasta encontrar el filo. Se cuentan los registros del grupo y se calcula su porcentaje como cien multiplicado por ese conteo, dividido para el total de registros de la selección. El denominador incluye registros cuya identificación está reservada, de modo que los porcentajes visibles pueden sumar menos de cien.',
            'Seleccionar un filo aplica ese filtro a todos los paneles y al mapa. Un taxón es cualquier grupo con un nombre científico, sea reino, filo, clase, orden, familia, género o especie. Las fotografías públicas del panel corresponden a la selección; cuando faltan, se muestran ilustraciones representativas etiquetadas. Este indicador cuenta registros, no especies distintas ni individuos censados en la naturaleza, y no es un índice de Shannon o Simpson.',
        ],
    ],
    'riqueza' => [
        'titulo' => 'Registros por provincia',
        'foto' => 'Observación de distintos invertebrados durante un muestreo en un bosque tropical.',
        'parrafos' => [
            'Muestra los registros de la selección vigente que tienen una provincia pública válida. Incluye ejemplares identificados hasta familia u otros rangos, para que su cobertura territorial no desaparezca del panel.',
            'Las barras muestran las diez provincias con más registros. Las variantes de escritura de una provincia se reúnen en un mismo grupo. Los datos agregados descargables conservan además el número de especies distintas con identificación pública confirmada; un registro identificado solo hasta familia no se cuenta como una especie.',
            'El resultado depende del esfuerzo de muestreo y de la publicación de datos. No estima abundancia natural ni especies aún no observadas. Seleccionar una provincia aplica ese criterio a toda la colección.',
        ],
    ],
    'decadas' => [
        'titulo' => 'Cobertura temporal',
        'foto' => 'Revisión de cuadernos de campo históricos junto a una colección entomológica.',
        'parrafos' => [
            'La cobertura temporal muestra en qué décadas se recolectaron los registros de la selección vigente, incluyendo identificaciones hasta familia u otros rangos. Ayuda a reconocer periodos representados y materiales históricos.',
            'Se toma el año inicial de una fecha de colecta pública válida para obtener el inicio de la década. Cada registro aporta una unidad a su década. Los datos agregados descargables conservan además el número de especies distintas con identificación pública confirmada; las fechas reservadas no intervienen en este conteo.',
            'La línea o las barras expresan cobertura de la colección y no tendencias poblacionales. Fechas erróneas o incompletas pueden producir periodos inesperados; conviene revisarlas antes de interpretar cambios ecológicos. Al seleccionar una década se aplica su rango de fechas a toda la consulta, respetando el solapamiento de los intervalos de colecta.',
        ],
    ],
    'calidad' => [
        'titulo' => 'Calidad para análisis',
        'foto' => 'Curadora comprobando la identificación y la documentación de un espécimen.',
        'parrafos' => [
            'Este indicador mide la completitud de la información publicada. Ayuda a reconocer qué registros cuentan con los campos mínimos para iniciar una revisión espacial y temporal de especies, y cuáles requieren completar su documentación.',
            'Se cuentan por separado los registros con identificación pública a especie, con fecha pública y con coordenadas públicas válidas. El valor principal cuenta la intersección: un mismo registro debe cumplir las tres condiciones a la vez. Cada barra representa cien multiplicado por el conteo de su condición, dividido para el total de registros de la selección; con una selección vacía se muestra cero.',
            'Los tres conteos parciales se superponen y no deben sumarse. Tener los campos completos no certifica que la identificación, la fecha o la posición sean precisas. Antes de modelar deben revisarse las determinaciones, la incertidumbre geográfica, los duplicados y el esfuerzo de muestreo.',
        ],
    ],
    'raras' => [
        'titulo' => 'Especies con pocos registros',
        'foto' => 'Un ejemplar de escarabajo examinado con lupa junto a un cajón de colección.',
        'parrafos' => [
            'Este indicador identifica especies poco documentadas en la selección actual. Puede ayudar a priorizar revisiones de identificación, digitalización o búsqueda de antecedentes de colecta, siempre en relación con los filtros aplicados.',
            'Se agrupan los registros con nombre científico público e identificación a especie, y se cuenta cuántas ocurrencias tiene cada nombre. Se conservan los grupos con una, dos o tres ocurrencias y se muestran hasta doce, ordenados primero por cantidad y después por nombre. Al cambiar los filtros, una especie puede entrar o salir de este grupo.',
            'Pocos registros publicados no significan rareza ecológica ni amenaza de extinción. El resultado puede explicarse por muestreo desigual, material aún no digitalizado o restricciones de divulgación. Este conteo no estima la probabilidad de detección ni la población total de una especie.',
        ],
    ],
    'especies' => [
        'titulo' => 'Especies más documentadas',
        'foto' => 'Serie de ejemplares de una colección entomológica ordenada para comparar registros.',
        'parrafos' => [
            'Este indicador ordena las especies según la cantidad de registros publicados que cumplen los filtros. Permite reconocer series de material disponibles para revisión taxonómica y estudiar cómo está representada cada especie dentro de la colección.',
            'Se agrupan los registros identificados a especie por su nombre científico público y se cuenta cada registro una vez. El panel muestra las veinte especies con mayor conteo. La opción CSV del menú descarga la lista completa de especies de la selección, mientras que la exportación JSON conserva los datos visibles de este panel.',
            'Una especie muy documentada puede reflejar interés histórico de los colectores, facilidad de captura o disponibilidad de material, sin ser la más abundante en su ambiente. Los registros no se convierten automáticamente en individuos ni se corrigen por esfuerzo de muestreo. Conserve el enlace y la fecha de consulta junto a la descarga para explicar el alcance del análisis.',
        ],
    ],
];
