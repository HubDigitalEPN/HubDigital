@component('layouts.portal', ['title' => 'Diccionario de descargas · Laboratorio de Invertebrados EPN'])
    <div class="mx-auto w-full max-w-5xl px-4 py-8">
        <h1 class="font-display text-3xl font-bold">Diccionario de descargas públicas</h1>
        <p class="my-4">Perfil <code>{{ \Modules\CatalogoPublico\Domain\ValueObjects\PerfilExportacionPublica::IDENTIFICADOR }}</code> · revisión 5 de octubre de 2026. CSV y XLSX conservan campos distintos; ninguno acredita por sí solo la exactitud científica del dato.</p>
        <p class="mb-4">CSV: texto UTF-8 con separador punto y coma, toda la selección aplicada. XLSX: libro de la especie seleccionada con todas sus páginas; identificadores como texto, fechas y números tipados cuando son válidos. Un vacío significa dato no informado o reservado. La incertidumbre desconocida nunca se sustituye por cero. N.º de catálogo puede repetirse: no lo uses como clave única para unir filas.</p>
        <div class="overflow-x-auto" tabindex="0" role="region" aria-label="Equivalencias CSV y XLSX">
            <table class="w-full border-collapse text-left text-sm">
                <caption class="py-3 text-left font-semibold">Columnas, tipos y equivalencias de {{ \Modules\CatalogoPublico\Domain\ValueObjects\PerfilExportacionPublica::IDENTIFICADOR }}</caption>
                <thead><tr><th scope="col" class="p-2">CSV</th><th scope="col" class="p-2">XLSX</th><th scope="col" class="p-2">Tipo y significado</th></tr></thead>
                <tbody>
                @foreach([
                    ['N.º catálogo', 'occurrenceID', 'Texto. Identificador público; conservar ceros y posibles duplicados.'],
                    ['Taxón', 'scientificName', 'Texto. Nombre publicado en el rango de identificación; puede contener calificadores o códigos locales. No implica nombre aceptado por una autoridad externa.'],
                    ['Fecha', 'eventDate', 'Fecha original de colecta. XLSX tipa como fecha únicamente los valores interpretables; un texto de intervalo puede permanecer como texto.'],
                    ['Localidad del Excel', 'localityExcel', 'Texto original de la fuente, separado de la referencia administrativa.'],
                    ['Localidad INEC', 'localityInec', 'Texto. Localidad administrativa de referencia; no demuestra el sitio exacto.'],
                    ['Código INEC', '—', 'Texto, exclusivo de CSV. Conservar ceros iniciales; no convertir a número.'],
                    ['Provincia', 'stateProvince', 'Texto original de provincia. Las opciones de filtro pueden consolidar alias sin reescribir el valor fuente.'],
                    ['Latitud', 'decimalLatitude', 'Número decimal WGS84 entre −90 y 90; requiere el par público válido.'],
                    ['Longitud', 'decimalLongitude', 'Número decimal WGS84 entre −180 y 180; requiere el par público válido.'],
                    ['Precisión', 'coordinateUncertaintyInMeters', 'Sin equivalencia directa: CSV conserva el texto de precisión; XLSX contiene solo incertidumbre métrica interpretable, en metros. Vacío no significa cero.'],
                    ['Tipo', 'typeStatus', 'Texto. Condición nomenclatural, como holotype o paratype; no es disposición del material.'],
                    ['Referencia INEC', 'localityInecReference', 'Texto. Procedencia o cautela de la referencia administrativa.'],
                    ['Disposición', 'disposition', 'Texto. Situación del material, como in_collection u on_loan.'],
                    ['Perfil de exportación', 'exportProfile', 'Texto. Identificador de este contrato de intercambio.'],
                    ['—', 'occurrenceStatus', 'Texto. Detección o no detección durante la colecta: detected o notDetected. Los valores históricos present y absent se traducen respectivamente; un estado físico como destroyed o loaned no permite inferir detección y deja esta celda vacía.'],
                    ['—', 'occurrenceStatusVerbatim', 'Texto. Extensión local: estado original de la fuente, conservado sin cambios. Puede describir el estado físico del material; no es una declaración de detección. Comparar con disposition y specimenNotes sin sobrescribirlos.'],
                    ['—', 'individualCount', 'Número de individuos, cuando se informa y se permite divulgar.'],
                    ['Localidad', 'localityName', 'Unión de área protegida, cantón o parroquia y sector, ruta o vía, según los datos curatoriales disponibles. localityExcel conserva el texto original y localityInec la referencia administrativa.'],
                    ['—', 'country', 'Texto. País de colecta informado.'],
                    ['—', 'recordedBy', 'Texto. Colector o colectores.'],
                    ['—', 'samplingProtocol', 'Texto. Método fuente de recolección; distinto de su etiqueta legible y clave de filtro.'],
                    ['—', 'typeNotes', 'Texto. Notas sobre condición de tipo.'],
                    ['—', 'specimenNotes', 'Texto. Notas del ejemplar.'],
                    ['—', 'minimumElevationInMeters', 'Número. Elevación mínima de colecta en metros.'],
                    ['—', 'maximumElevationInMeters', 'Número. Elevación máxima de colecta en metros.'],
                    ['—', 'caste', 'Texto. Casta informada.'],
                    ['—', 'lifeStage', 'Texto. Estadio fuente; el filtro acepta etiquetas como Adulto y su código adult.'],
                    ['—', 'georeferenceRemarks', 'Texto. Advertencias y procedencia de las coordenadas. Consultar también la ficha: CSV no incluye esta columna.'],
                ] as [$csv, $xlsx, $descripcion])
                    <tr class="border-t"><th scope="row" class="p-2 font-normal">{{ $csv }}</th><td class="p-2"><code>{{ $xlsx }}</code></td><td class="p-2">{{ $descripcion }}</td></tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <p class="my-4">Fotografías, linaje completo y datos reservados no forman parte de estas descargas. Un cambio de autoridad taxonómica requiere una decisión curatorial documentada; las diferencias de grafía, sinonimia y códigos locales no autorizan a reidentificar ejemplares automáticamente.</p>
        <p class="my-4"><a class="underline" href="https://dwc.tdwg.org/terms/">Definiciones de términos Darwin Core (TDWG)</a>. Este perfil no constituye un archivo Darwin Core completo.</p>
        <a class="inline-block rounded border px-4 py-2 underline" href="{{ route('portal.catalogo', ['vista' => 'registros']) }}">Abrir catálogo público</a>
    </div>
@endcomponent
