<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Modules\CatalogoPublico\Domain\ValueObjects\FiltrosBusqueda;
use Modules\CatalogoPublico\Infrastructure\Persistence\Eloquent\Repositories\EloquentProveedorEspecimenesParaArbol;
use Modules\CatalogoPublico\Presentation\Http\Controllers\PortalCatalogo;
use Modules\CatalogoPublico\Presentation\Http\Controllers\PortalEstadisticas;

uses(Tests\PostgresIntegrationTestCase::class);

function qa4TaxonPublico(string $nombre, string $rango, ?string $padre = null): string
{
    $id = DB::table('taxonomia.taxones')->where('nombre_cientifico', $nombre)->where('rango', $rango)->value('id');
    if ($id !== null) return $id;
    $id = (string) Str::uuid();
    DB::table('taxonomia.taxones')->insert(['id' => $id, 'nombre_cientifico' => $nombre, 'rango' => $rango, 'padre_id' => $padre]);
    return $id;
}

function qa4RegistroPublico(string $taxon, string $colector, string $provincia, array $permisos = []): string
{
    $id = (string) Str::uuid(); $codigo = 'QA4-'.Str::uuid();
    DB::table('taxonomia.especimenes')->insert([
        'id' => $id, 'codigo_catalogo' => $codigo, 'occurrence_id' => $codigo, 'taxon_id' => $taxon,
        'colector' => $colector, 'state_province' => $provincia, 'localidad' => 'Sitio '.$colector,
        'fecha_colecta' => '2025-06-15', 'decimal_latitude' => -0.4, 'decimal_longitude' => -90.3,
    ]);
    DB::table('divulgacion.especimenes_divulgables')->insert(array_replace([
        'id' => (string) Str::uuid(), 'especimen_id' => $id, 'publicado' => true,
    ], $permisos));
    return $id;
}

test('QA4-005 el mosaico geográfico y el filo explícito usan los mismos ocho moluscos sin añadir artrópodos', function (): void {
    $seleccion = 'QaCuatro'.Str::lower(Str::random(20));
    $molusca = qa4TaxonPublico('Mollusca', 'phylum');
    $artropoda = qa4TaxonPublico('Arthropoda', 'phylum');
    $especie = qa4TaxonPublico($seleccion.' marinus', 'especie', $molusca);
    $otra = qa4TaxonPublico($seleccion.' externus', 'especie', $artropoda);
    $ids = [];
    for ($i = 0; $i < 8; $i++) $ids[] = qa4RegistroPublico($especie, $seleccion, 'Galápagos');
    $fuera = qa4RegistroPublico($otra, $seleccion, 'Pichincha');
    $estadisticas = app(PortalEstadisticas::class);
    $implicita = $estadisticas->datosParaVista(['provincia' => 'Galápagos', 'colector' => $seleccion]);
    $explicita = $estadisticas->datosParaVista(['provincia' => 'Galápagos', 'colector' => $seleccion, 'filo' => $molusca]);
    expect((int) $implicita['resumen']['registros'])->toBe(8)->and($implicita['filos'])->toBe(['Mollusca' => 8])
        ->and($implicita['mosaico'])->toBe([])->and($implicita['ilustraciones_mosaico'])->toHaveCount(1)
        ->and($implicita['ilustraciones_mosaico'][0]['morfologia'])->toBeFalse()
        ->and($implicita['ilustraciones_mosaico'][0]['url'])->toBeNull()
        ->and(array_column($implicita['ilustraciones_mosaico'], 'grupo'))->not->toContain('Formicidae', 'Coleoptera', 'Lepidoptera', 'Araneae')
        ->and($explicita['ilustraciones_mosaico'][0]['foto_real'])->toBeTrue()
        ->and($explicita['ilustraciones_mosaico'][0]['phylum'])->toBe('Mollusca')
        ->and(array_column($explicita['ilustraciones_mosaico'], 'grupo'))->not->toContain('Formicidae', 'Coleoptera', 'Lepidoptera', 'Araneae')
        ->and((int) $explicita['resumen']['registros'])->toBe(8);
    $repo = app(EloquentProveedorEspecimenesParaArbol::class);
    $filtros = FiltrosBusqueda::desde(['filtroProvincia' => 'Galápagos', 'filtroColector' => $seleccion]);
    $primeraPagina = $repo->paginaPublica($filtros, 1);
    $segundaPagina = $repo->paginaPublica($filtros, 2);
    expect($primeraPagina['ids'])->toHaveCount(6)->and($primeraPagina['total'])->toBe(8)
        ->and($segundaPagina['ids'])->toHaveCount(2)
        ->and(array_intersect($primeraPagina['ids'], $segundaPagina['ids']))->toBe([])
        ->and([...$primeraPagina['ids'], ...$segundaPagina['ids']])->toEqualCanonicalizing($ids);
    $vacia = $estadisticas->datosParaVista(['provincia' => 'Provincia inexistente '.$seleccion, 'colector' => $seleccion]);
    expect((int) $vacia['resumen']['registros'])->toBe(0)
        ->and($vacia['ilustraciones_mosaico'][0]['morfologia'])->toBeFalse()
        ->and($vacia['ilustraciones_mosaico'][0]['url'])->toBeNull();
    foreach ([$ids[0], $fuera] as $i => $id) {
        DB::table('divulgacion.imagenes_taxonomicas')->insert([
            'id' => (string) Str::uuid(), 'occurrence_id' => DB::table('taxonomia.especimenes')->where('id', $id)->value('occurrence_id'),
            'nombre_original' => 'muestra-'.$i.'.jpg', 'ruta' => 'divulgacion/imagenes/qa4-'.Str::uuid().'.jpg', 'disco' => 'r2',
            'autor_nombre' => 'QA', 'autor_apellido' => 'Portal', 'autor_nombre_completo' => 'QA Portal',
            'created_at' => '2026-10-02 00:00:00', 'updated_at' => '2026-10-02 00:00:00',
        ]);
    }
    $conFoto = $estadisticas->datosParaVista(['provincia' => 'Galápagos', 'colector' => $seleccion]);
    expect($conFoto['mosaico'])->toHaveCount(1)->and($conFoto['mosaico'][0]['taxon'])->toBe($seleccion.' marinus')
        ->and($conFoto['mosaico'][0]['nombre'])->toBe('muestra-0.jpg');
});

test('QA4-005 cuatro hormigas requieren un linaje Formicidae público de la selección y se retiran al reservarlo', function (): void {
    $seleccion = 'QaHormigas'.Str::lower(Str::random(20));
    $artropoda = qa4TaxonPublico('Arthropoda', 'phylum');
    $familia = qa4TaxonPublico('Formicidae', 'familia', $artropoda);
    $genero = qa4TaxonPublico($seleccion, 'genero', $familia);
    $especie = qa4TaxonPublico($seleccion.' femoratus', 'especie', $genero);
    $ids = [];
    for ($i = 0; $i < 4; $i++) $ids[] = qa4RegistroPublico($especie, $seleccion, 'Provincia '.$seleccion);
    $estadisticas = app(PortalEstadisticas::class);
    $datos = $estadisticas->datosParaVista(['colector' => $seleccion]);
    expect((int) $datos['resumen']['registros'])->toBe(4)->and($datos['ilustraciones_mosaico'])->toHaveCount(4)
        ->and(array_unique(array_column($datos['ilustraciones_mosaico'], 'url')))->toHaveCount(4)
        ->and(array_unique(array_column($datos['ilustraciones_mosaico'], 'grupo')))->toBe(['Formicidae']);
    DB::table('divulgacion.especimenes_divulgables')->whereIn('especimen_id', $ids)->update(['family_visible' => false]);
    $reservada = $estadisticas->datosParaVista(['colector' => $seleccion]);
    expect((int) $reservada['resumen']['registros'])->toBe(4)
        ->and(array_column($reservada['ilustraciones_mosaico'], 'grupo'))->not->toContain('Formicidae')
        ->and($reservada['ilustraciones_mosaico'][0]['morfologia'])->toBeFalse();
    // La publicación es automática por filo: reasignar a un linaje sin filo
    // produce una retirada real; escribir publicado=false sería sobrescrito por el trigger.
    $sinFilo = qa4TaxonPublico($seleccion.' sinFilo', 'especie');
    DB::table('taxonomia.especimenes')->whereIn('id', $ids)->update(['taxon_id' => $sinFilo]);
    expect(DB::table('divulgacion.especimenes_divulgables')->whereIn('especimen_id', $ids)->where('publicado', true)->count())->toBe(0)
        ->and(DB::table('divulgacion.especimenes_divulgables')->whereIn('especimen_id', $ids)->where('publicado', false)->count())->toBe(4);
    expect((int) $estadisticas->datosParaVista(['colector' => $seleccion])['resumen']['registros'])->toBe(0);
});

test('la navegación de especie conserva sus registros y solo admite fotografías identificadas de esa misma especie', function (): void {
    $colector = 'QaFotografias'.Str::lower(Str::random(20));
    $reino = qa4TaxonPublico('Animalia', 'reino');
    $filo = qa4TaxonPublico('Arthropoda', 'phylum');
    $clase = qa4TaxonPublico('Insecta', 'clase', $filo);
    $orden = qa4TaxonPublico('Hymenoptera', 'orden', $clase);
    $familia = qa4TaxonPublico('Formicidae', 'familia', $orden);
    $generoConFoto = qa4TaxonPublico('Atta', 'genero', $familia);
    $generoSinFoto = qa4TaxonPublico('Camponotus', 'genero', $familia);
    $conFoto = qa4TaxonPublico('Atta cephalotes', 'especie', $generoConFoto);
    $sinFoto = qa4TaxonPublico('Camponotus femoratus', 'especie', $generoSinFoto);
    // El helper puede reutilizar taxones del inventario local. Este positivo
    // controla el linaje científico completo, incluida la raíz, dentro de la
    // transacción que revierte el caso; no modifica el inventario desplegado.
    foreach ([$reino => null, $filo => $reino, $clase => $filo, $orden => $clase,
        $familia => $orden, $generoConFoto => $familia, $generoSinFoto => $familia,
        $conFoto => $generoConFoto, $sinFoto => $generoSinFoto] as $id => $padre) {
        DB::table('taxonomia.taxones')->where('id', $id)->update(['padre_id' => $padre]);
    }
    $idConFoto = qa4RegistroPublico($conFoto, $colector, 'Pichincha');
    $idSinFoto = qa4RegistroPublico($sinFoto, $colector, 'Pichincha');
    $directa = app(PortalEstadisticas::class)->datosParaVista([
        'nivel' => 'species', 'taxon_navegado' => 'Atta cephalotes', 'colector' => $colector,
    ]);
    expect((int) $directa['resumen']['registros'])->toBe(1)
        ->and($directa['taxon_mosaico'])->toMatchArray([
            'kingdom' => 'Animalia', 'phylum' => 'Arthropoda', 'class' => 'Insecta',
            'order' => 'Hymenoptera', 'family' => 'Formicidae', 'genus' => 'Atta', 'species' => 'Atta cephalotes',
        ])
        ->and($directa['ilustraciones_mosaico'][0]['foto_real'])->toBeTrue();
    $componente = Livewire::withQueryParams(['vista' => 'mapa', 'fco' => $colector, 'fph' => $filo])->test(PortalCatalogo::class)
        ->assertSet('filtroFiloId', $filo)
        ->call('navegar', 'species', 'Atta cephalotes')
        ->assertSet('nivel', 'species')->assertSet('taxon', 'Atta cephalotes')
        ->assertViewHas('datosMapa', fn (array $datos): bool => (int) $datos['resumen']['registros'] === 1
            && $datos['taxon_mosaico']['species'] === 'Atta cephalotes'
            && array_unique(array_column($datos['ilustraciones_mosaico'], 'species')) === ['Atta cephalotes']
            && $datos['ilustraciones_mosaico'][0]['foto_real'] === true);
    $componente->call('abrirCelda', -0.4, -90.3)->call('cambiarVistaCelda', 'registros');
    expect(array_column($componente->instance()->detalleCelda['registros'], 'especimen_id'))->toBe([$idConFoto]);
    $componente->call('navegar', 'species', 'Camponotus femoratus')
        ->assertViewHas('datosMapa', fn (array $datos): bool => (int) $datos['resumen']['registros'] === 1
            && $datos['taxon_mosaico']['species'] === 'Camponotus femoratus'
            && $datos['ilustraciones_mosaico'][0]['url'] === null
            && $datos['ilustraciones_mosaico'][0]['foto_real'] === false);
    $componente->call('abrirCelda', -0.4, -90.3)->call('cambiarVistaCelda', 'registros');
    expect(array_column($componente->instance()->detalleCelda['registros'], 'especimen_id'))->toBe([$idSinFoto]);
});

test('el mosaico resuelve el rango y UUID de la selección sin usar homónimos ajenos ni ampliar permisos', function (): void {
    $seleccion = 'QaIdentidadFoto'.Str::lower(Str::random(20));
    $nombre = $seleccion.' alpha';
    $filo = qa4TaxonPublico($seleccion, 'phylum');
    $familia = qa4TaxonPublico($seleccion.' Familia uno', 'familia', $filo);
    $familiaAjena = qa4TaxonPublico($seleccion.' Familia dos', 'familia', $filo);
    // Mismo nombre en otro rango: la navegación científica sí conoce el rango especie.
    $genero = qa4TaxonPublico($nombre, 'genero', $familia);
    $generoAjeno = qa4TaxonPublico($seleccion.' Genero dos', 'genero', $familiaAjena);
    $especie = qa4TaxonPublico($nombre, 'especie', $genero);
    // La restricción fuente es nombre+rango. La variante de caja conserva otro UUID
    // permitido por ella, pero es un homónimo para la comparación exacta sin distinguir caja.
    $homonima = qa4TaxonPublico(mb_strtoupper($nombre), 'especie', $generoAjeno);
    $id = qa4RegistroPublico($especie, $seleccion, 'Provincia '.$seleccion);
    $idAjeno = qa4RegistroPublico($homonima, 'Fuera'.Str::lower(Str::random(20)), 'Provincia '.$seleccion);
    $estadisticas = app(PortalEstadisticas::class);
    $filtros = ['colector' => $seleccion, 'nivel' => 'species', 'taxon_navegado' => $nombre];
    $datos = $estadisticas->datosParaVista($filtros);
    expect((int) $datos['resumen']['registros'])->toBe(1)
        ->and($datos['taxon_mosaico']['species'])->toBe($nombre)
        ->and($datos['taxon_mosaico']['genus'])->toBe($nombre)
        ->and($datos['taxon_mosaico']['family'])->toBe($seleccion.' Familia uno')
        ->and(array_column($datos['taxon_mosaico']['ancestros'], 'nombre'))->not->toContain($seleccion.' Familia dos', $seleccion.' Genero dos');
    expect(app(EloquentProveedorEspecimenesParaArbol::class)
        ->paginaPublica(FiltrosBusqueda::desde(['filtroColector' => $seleccion]), 1, 'species', $nombre)['ids'])->toBe([$id]);

    DB::table('divulgacion.especimenes_divulgables')->where('especimen_id', $id)->update(['family_visible' => false, 'genus_visible' => false]);
    $reservada = $estadisticas->datosParaVista($filtros);
    expect($reservada['taxon_mosaico']['species'])->toBe($nombre)
        ->and($reservada['taxon_mosaico'])->not->toHaveKey('family')->not->toHaveKey('genus')
        ->and(array_column($reservada['taxon_mosaico']['ancestros'], 'nombre'))->not->toContain($seleccion.' Familia uno');

    // Ahora solo las dos hojas homónimas comparten el nombre buscado, con material
    // público en la misma selección. No se elige arbitrariamente uno de sus UUID.
    DB::table('taxonomia.taxones')->where('id', $genero)->update(['nombre_cientifico' => $seleccion.' Genero uno']);
    DB::table('divulgacion.especimenes_divulgables')->where('especimen_id', $id)->update(['family_visible' => true, 'genus_visible' => true]);
    $aislada = $estadisticas->datosParaVista(['colector' => $seleccion, 'taxon' => $nombre]);
    expect((int) $aislada['resumen']['registros'])->toBe(1)
        ->and($aislada['taxon_mosaico']['species'])->toBe($nombre)
        ->and($aislada['taxon_mosaico']['family'])->toBe($seleccion.' Familia uno');
    DB::table('taxonomia.especimenes')->where('id', $idAjeno)->update(['colector' => $seleccion]);
    $ambigua = $estadisticas->datosParaVista(['colector' => $seleccion, 'taxon' => $nombre]);
    expect((int) $ambigua['resumen']['registros'])->toBe(2)
        ->and($ambigua['taxon_mosaico'])->toBe([])
        ->and($ambigua['ilustraciones_mosaico'])->toHaveCount(1)
        ->and($ambigua['ilustraciones_mosaico'][0]['foto_real'])->toBeFalse()
        ->and($ambigua['ilustraciones_mosaico'][0]['url'])->toBeNull();
});

test('QA4-007 la nota fuente conserva su material sin convertirse en rango, especie, hermano ni ayuda científica', function (): void {
    $seleccion = 'QaNotas'.Str::lower(Str::random(20));
    $filo = qa4TaxonPublico($seleccion, 'phylum');
    $clase = qa4TaxonPublico($seleccion.' Clase', 'clase', $filo);
    $orden = qa4TaxonPublico($seleccion.' Orden', 'orden', $clase);
    $familia = qa4TaxonPublico($seleccion.' Familia', 'familia', $orden);
    $genero = qa4TaxonPublico($seleccion.' Genero', 'genero', $familia);
    $valida = qa4TaxonPublico($seleccion.' valida', 'especie', $genero);
    $idValido = qa4RegistroPublico($valida, $seleccion, 'Provincia '.$seleccion);
    $notas = ['muestra reubicada dentro de '.$seleccion, 'dañada '.$seleccion];
    $fuente = [];
    foreach ($notas as $i => $nota) {
        $padre = $filo;
        foreach (['clase', 'orden', 'familia', 'genero', 'especie'] as $rango) $padre = qa4TaxonPublico($nota, $rango, $padre);
        $fuente[] = qa4RegistroPublico($padre, $seleccion, 'Provincia '.$seleccion);
        if ($i === 1) $fuente[] = qa4RegistroPublico($padre, $seleccion, 'Provincia '.$seleccion);
    }
    // Un nombre inferior aparentemente válido tampoco reconstruye padres después de una nota.
    $claseNota = qa4TaxonPublico('trasladada '.$seleccion, 'clase', $filo);
    $debajo = qa4TaxonPublico($seleccion.' bajoNota', 'especie', $claseNota);
    $fuente[] = qa4RegistroPublico($debajo, $seleccion, 'Provincia '.$seleccion);
    $filtros = FiltrosBusqueda::desde(['filtroColector' => $seleccion]);
    $repo = app(EloquentProveedorEspecimenesParaArbol::class);
    $resumen = $repo->resumenJerarquia($filtros, 'phylum', $seleccion);
    expect($resumen['total'])->toBe(5)->and($resumen['curatoriales_total'])->toBe(4)
        ->and($resumen['curatoriales'])->toHaveCount(3)
        ->and(array_column($resumen['nodos'], 'taxon'))->toBe([$seleccion, $seleccion.' Clase', $seleccion.' Familia', $seleccion.' Genero', $seleccion.' Orden'])
        ->and(array_column($resumen['especies'], 'especie'))->toBe([$seleccion.' valida'])
        ->and($resumen['descendientes']['phylum:'.$seleccion])->toBe(['class' => 1, 'order' => 1, 'family' => 1, 'genus' => 1, 'species' => 1])
        ->and(array_column($resumen['curatoriales'], 'nota'))->toContain(...$notas)
        ->and($repo->paginaPublica($filtros, 1)['ids'])->toEqualCanonicalizing([$idValido, ...$fuente]);
    expect($repo->paginaPublica(FiltrosBusqueda::desde(['filtroColector' => $seleccion, 'filtroIdentificacion' => 'especie']), 1)['ids'])->toBe([$idValido])
        ->and($repo->paginaPublica(FiltrosBusqueda::desde(['filtroColector' => $seleccion, 'filtroDatosCompletos' => '1']), 1)['ids'])->toBe([$idValido]);
    foreach ($resumen['curatoriales'] as $dato) expect($dato)->not->toHaveKey('nivel')->not->toHaveKey('stats');
    $compatibles = $repo->obtenerTodos($filtros);
    expect($compatibles)->toHaveCount(5);
    foreach ($compatibles as $dto) {
        foreach (['class', 'order', 'family', 'genus', 'scientificName'] as $campo) expect($dto->jerarquia->$campo)->not->toContain('dañada', 'reubicada', 'trasladada');
    }
    $datos = app(PortalEstadisticas::class)->datosParaVista(['colector' => $seleccion]);
    expect((int) $datos['resumen']['registros'])->toBe(5)->and((int) $datos['resumen']['identificados'])->toBe(1)
        ->and(array_column($datos['especies'], 'nombre'))->toBe([$seleccion.' valida'])
        ->and(array_sum(array_column($datos['riqueza'], 'especies')))->toBe(1)
        ->and(array_sum(array_column($datos['mapa'], 'total')))->toBe(5)->and($datos['mapa'][0]['taxones'])->toBe(1);
    $componente = Livewire::withQueryParams(['vista' => 'tarjetas', 'nivel' => 'phylum', 'taxon' => $seleccion, 'fco' => $seleccion])->test(PortalCatalogo::class);
    foreach ($notas as $nota) $componente->assertDontSee('¿Qué es '.$nota.'?');
    $componente->call('cambiarVista', 'registros')->assertViewHas('totalRegistrosVista', 5)
        ->assertViewHas('registrosVista', fn ($registros): bool => count($registros) === 5 && count(array_filter($registros, fn ($r): bool => $r->taxon_en_revision)) === 4);
    $componente->call('cambiarVista', 'mapa')->call('abrirCelda', -0.4, -90.3);
    $detalle = $componente->instance()->detalleCelda;
    expect($detalle['arbol_registros_total'])->toBe(5)->and($detalle['curatoriales_total'])->toBe(4)
        ->and(array_column($detalle['arbol'], 'nombre'))->not->toContain(...$notas)
        ->and(array_column($detalle['arbol'], 'taxon_id'))->not->toContain($debajo)
        ->and($detalle['arbol_hojas_total'])->toBe(5)
        ->and(array_column($detalle['registros_arbol'], 'especimen_id'))->toEqualCanonicalizing([$idValido, ...$fuente]);
    $componente->call('cambiarVistaCelda', 'registros');
    expect(array_column($componente->instance()->detalleCelda['registros'], 'especimen_id'))->toEqualCanonicalizing([$idValido, ...$fuente]);
    expect(DB::table('taxonomia.taxones')->where('id', $claseNota)->value('nombre_cientifico'))->toBe('trasladada '.$seleccion)
        ->and(DB::table('taxonomia.especimenes')->whereIn('id', $fuente)->count())->toBe(4);
});

test('QA4-007 el aviso curatorial está acotado y respeta publicación, privacidad y filtros', function (): void {
    $seleccion = 'QaRevision'.Str::lower(Str::random(20));
    $filo = qa4TaxonPublico($seleccion, 'phylum');
    for ($i = 0; $i < 14; $i++) {
        $taxon = qa4TaxonPublico('muestra reubicada '.$seleccion.' '.$i, 'especie', $filo);
        qa4RegistroPublico($taxon, $seleccion, 'Provincia '.$seleccion);
    }
    // Un taxón sin filo permanece no publicado por la política automática de la BD.
    $noPublica = qa4TaxonPublico('dañada privada '.$seleccion, 'especie');
    $idNoPublico = qa4RegistroPublico($noPublica, $seleccion, 'Provincia '.$seleccion, ['publicado' => false]);
    expect(DB::table('divulgacion.especimenes_divulgables')->where('especimen_id', $idNoPublico)->value('publicado'))->toBeFalse();
    $sinNombre = qa4TaxonPublico('dañada reservada '.$seleccion, 'especie', $filo);
    qa4RegistroPublico($sinNombre, $seleccion, 'Provincia '.$seleccion, ['scientific_name_visible' => false]);
    $repo = app(EloquentProveedorEspecimenesParaArbol::class);
    $resumen = $repo->resumenJerarquia(FiltrosBusqueda::desde(['filtroColector' => $seleccion]));
    expect($resumen['total'])->toBe(15)->and($resumen['curatoriales_total'])->toBe(14)
        ->and($resumen['curatoriales'])->toHaveCount(12)->and($resumen['especies'])->toBe([])
        ->and(array_column($resumen['curatoriales'], 'nota'))->not->toContain('dañada privada '.$seleccion, 'dañada reservada '.$seleccion);
    $vacia = $repo->resumenRaiz(FiltrosBusqueda::desde(['filtroColector' => 'Otro '.$seleccion]));
    expect($vacia['curatoriales_total'])->toBe(0)->and($vacia['curatoriales'])->toBe([]);
});

test('QA4-007 el aviso de ancestro reservado no publica su nombre y se oculta con la identificación', function (): void {
    $seleccion = 'QaReserva'.Str::lower(Str::random(20));
    $filo = qa4TaxonPublico($seleccion, 'phylum');
    $nombreReservado = 'muestra reubicada reservada '.$seleccion;
    $familia = qa4TaxonPublico($nombreReservado, 'familia', $filo);
    $especie = qa4TaxonPublico($seleccion.' propia', 'especie', $familia);
    $id = qa4RegistroPublico($especie, $seleccion, 'Provincia '.$seleccion, ['family_visible' => false]);
    $componente = Livewire::withQueryParams(['vista' => 'registros', 'fco' => $seleccion])->test(PortalCatalogo::class)
        ->assertDontSee($nombreReservado)
        ->assertViewHas('registrosVista', fn ($r): bool => count($r) === 1 && $r[0]->scientific_name === $seleccion.' propia' && $r[0]->taxon_en_revision);
    $resumen = app(EloquentProveedorEspecimenesParaArbol::class)->resumenJerarquia(FiltrosBusqueda::desde(['filtroColector' => $seleccion]));
    expect($resumen['curatoriales_total'])->toBe(1)->and(array_column($resumen['curatoriales'], 'nota'))->toBe([$seleccion.' propia'])
        ->and(array_column($resumen['curatoriales'][0]['ruta'], 'id'))->not->toContain($familia)
        ->and(array_column($resumen['nodos'], 'taxon'))->not->toContain($nombreReservado);
    $componente->call('cambiarVista', 'mapa')->call('abrirCelda', -0.4, -90.3)->assertDontSee($nombreReservado)
        ->call('cambiarVistaCelda', 'registros');
    $detalle = $componente->instance()->detalleCelda;
    expect(array_column($detalle['arbol'], 'taxon_id'))->not->toContain($familia, $especie)
        ->and($detalle['registros'][0]->taxon_en_revision)->toBeTrue();
    DB::table('divulgacion.especimenes_divulgables')->where('especimen_id', $id)->update(['scientific_name_visible' => false]);
    $componente->call('cambiarVista', 'registros')->assertDontSee($nombreReservado)->assertDontSee($seleccion.' propia')
        ->assertViewHas('registrosVista', fn ($r): bool => count($r) === 1 && $r[0]->scientific_name === null && ! $r[0]->taxon_en_revision);
});

test('QA4-007 una nota solo en la hoja conserva sus padres confirmados y no ocupa el rail de especies hermanas', function (): void {
    $seleccion = 'QaHermanos'.Str::lower(Str::random(20));
    $filo = qa4TaxonPublico($seleccion, 'phylum');
    $familia = qa4TaxonPublico($seleccion.' Familia', 'familia', $filo);
    $genero = qa4TaxonPublico($seleccion.' Genero', 'genero', $familia);
    $primera = qa4TaxonPublico($seleccion.' alpha', 'especie', $genero);
    $segunda = qa4TaxonPublico($seleccion.' beta', 'especie', $genero);
    $nombreNota = 'dañada '.$seleccion;
    $nota = qa4TaxonPublico($nombreNota, 'especie', $genero);
    $ids = [qa4RegistroPublico($primera, $seleccion, 'Provincia '.$seleccion),
        qa4RegistroPublico($segunda, $seleccion, 'Provincia '.$seleccion),
        qa4RegistroPublico($nota, $seleccion, 'Provincia '.$seleccion)];
    $repo = app(EloquentProveedorEspecimenesParaArbol::class);
    $filtros = FiltrosBusqueda::desde(['filtroColector' => $seleccion]);
    $resumen = $repo->resumenJerarquia($filtros, 'genus', $seleccion.' Genero');
    expect($resumen['total'])->toBe(3)->and($resumen['curatoriales_total'])->toBe(1)
        ->and(array_column($resumen['especies'], 'especie'))->toBe([$seleccion.' alpha', $seleccion.' beta'])
        ->and($resumen['descendientes']['family:'.$seleccion.' Familia'])->toBe(['genus' => 1, 'species' => 2])
        ->and($resumen['curatoriales'][0]['padre'])->toBe($seleccion.' Genero')
        ->and($repo->paginaPublica($filtros, 1, 'genus', $seleccion.' Genero')['ids'])->toEqualCanonicalizing($ids);
    $componente = Livewire::withQueryParams(['nivel' => 'species', 'taxon' => $seleccion.' alpha', 'fco' => $seleccion])->test(PortalCatalogo::class)
        ->assertViewHas('hermanos', [['nivel' => 'species', 'taxon' => $seleccion.' beta', 'esEspecie' => true, 'total' => 1]])
        ->assertDontSee($nombreNota);
    $componente->call('navegar', 'genus', $seleccion.' Genero')
        ->assertViewHas('curatoriales_total', 1)->assertDontSee('¿Qué es '.$nombreNota.'?')
        ->call('cambiarVista', 'registros')->assertViewHas('totalRegistrosVista', 3)
        ->assertViewHas('registrosVista', fn ($r): bool => count(array_filter($r, fn ($fila): bool => $fila->taxon_en_revision)) === 1);
    expect(DB::table('taxonomia.taxones')->where('id', $nota)->value('nombre_cientifico'))->toBe($nombreNota);
});

test('QA4-007 los ciclos y cadenas superiores a treinta nodos conservan material sin publicar rutas truncadas', function (): void {
    $seleccion = 'QaLimite'.Str::lower(Str::random(20));
    // Cadena fuente anómala de 31 nodos: filo a 16 saltos de la hoja para conservar
    // publicación automática (máximo 20 saltos), pero exceder el límite público de 30.
    $padre = qa4TaxonPublico($seleccion.' Raiz', 'reino');
    $cadenaFuente = [$padre];
    for ($i = 1; $i < 30; $i++) {
        $rango = $i === 14 ? 'phylum' : ($i < 14 ? 'subreino' : 'familia');
        $padre = qa4TaxonPublico($seleccion.' Ancestro '.$i, $rango, $padre);
        $cadenaFuente[] = $padre;
    }
    $profunda = qa4TaxonPublico($seleccion.' profunda', 'especie', $padre);
    $cadenaFuente[] = $profunda;
    $circular = qa4TaxonPublico($seleccion.' Circular', 'phylum');
    DB::table('taxonomia.taxones')->where('id', $circular)->update(['padre_id' => $circular]);
    $ids = [qa4RegistroPublico($profunda, $seleccion, 'Provincia '.$seleccion),
        qa4RegistroPublico($circular, $seleccion, 'Provincia '.$seleccion)];
    expect(DB::table('taxonomia.taxones')->whereIn('id', $cadenaFuente)->count())->toBe(31)
        ->and(DB::table('divulgacion.especimenes_divulgables')->whereIn('especimen_id', $ids)->where('publicado', true)->count())->toBe(2);
    $repo = app(EloquentProveedorEspecimenesParaArbol::class);
    $filtros = FiltrosBusqueda::desde(['filtroColector' => $seleccion]);
    $resumen = $repo->resumenJerarquia($filtros);
    expect($resumen['total'])->toBe(2)->and($resumen['curatoriales_total'])->toBe(2)
        ->and($resumen['nodos'])->toBe([])->and($resumen['especies'])->toBe([])
        ->and(array_column($resumen['curatoriales'], 'ruta'))->toBe([[], []]);
    $legacy = $repo->obtenerTodos($filtros);
    expect($legacy)->toHaveCount(2);
    foreach ($legacy as $fila) expect($fila->jerarquia->phylum)->toBe('')->and($fila->jerarquia->family)->toBe('');
    $datos = app(PortalEstadisticas::class)->datosParaVista(['colector' => $seleccion]);
    expect((int) $datos['resumen']['registros'])->toBe(2)->and((int) $datos['resumen']['identificados'])->toBe(0)
        ->and($datos['filos'])->toBe([])->and($datos['mapa'][0]['total'])->toBe(2)->and($datos['mapa'][0]['taxones'])->toBe(0);
    $componente = Livewire::withQueryParams(['vista' => 'mapa', 'fco' => $seleccion])->test(PortalCatalogo::class)
        ->call('abrirCelda', -0.4, -90.3)->call('cambiarVistaCelda', 'registros');
    $detalle = $componente->instance()->detalleCelda;
    expect($detalle['arbol'])->toHaveCount(2)
        ->and(array_column($detalle['arbol'], 'rango'))->toBe(['registro', 'registro'])
        ->and(array_column($detalle['arbol'], 'taxon_id'))->toBe([null, null])
        ->and(array_column($detalle['arbol'], 'padre_id'))->toBe([null, null])
        ->and(array_column($detalle['arbol'], 'especimen_id'))->toEqualCanonicalizing($ids)
        ->and($detalle['rutas'])->toBe([])->and($detalle['seleccionado'])->toBeNull()
        ->and($detalle['arbol_registros_total'])->toBe(2)
        ->and($detalle['curatoriales_total'])->toBe(2)
        ->and(array_column($detalle['registros'], 'especimen_id'))->toEqualCanonicalizing($ids);
    foreach ($detalle['registros'] as $fila) expect($fila->taxon_en_revision)->toBeTrue();
    $componente->call('cambiarVistaCelda', 'grupos');
    foreach ($ids as $id) {
        $componente->call('navegarCelda', 'registro:'.$id);
        $hoja = $componente->instance()->detalleCelda;
        expect($hoja['registro_seleccionado']['especimen_id'])->toBe($id)
            ->and($hoja['seleccionado'])->toBeNull()->and($hoja['rutas'])->toBe([])
            ->and(array_column($hoja['registros'], 'especimen_id'))->toBe([$id])
            ->and($hoja['registros'][0]->taxon_en_revision)->toBeTrue();
    }
});

test('las opciones de filtros excluyen vacíos Unicode conservando material, nombres, permisos y alias', function (): void {
    $seleccion = 'QaProvincias'.Str::lower(Str::random(20));
    $filo = qa4TaxonPublico($seleccion, 'phylum');
    $filoInvisible = qa4TaxonPublico("\u{00A0}", 'phylum');
    $especie = qa4TaxonPublico($seleccion.' valida', 'especie', $filo);
    $vacios = ["\u{00A0}", "\t \u{2009}\u{202F}", " \u{FEFF}\u{200B}"];
    $idsVacios = [];
    foreach ($vacios as $texto) {
        $id = qa4RegistroPublico($especie, $seleccion, $texto);
        $idsVacios[] = $id;
        DB::table('taxonomia.especimenes')->where('id', $id)->update(['preparations' => $texto, 'biome' => $texto, 'sampling_protocol' => $texto]);
        // El colector invisible no contiene el identificador del conjunto principal.
        qa4RegistroPublico($especie, $texto, $texto);
    }
    $provincia = 'Manabí '.$seleccion;
    $idValido = qa4RegistroPublico($especie, $seleccion, $provincia);
    $reales = ['preparations' => 'Alcohol '.$seleccion, 'biome' => 'Chocó '.$seleccion, 'sampling_protocol' => 'Trampa de caída '.$seleccion];
    DB::table('taxonomia.especimenes')->where('id', $idValido)->update($reales);
    $reservada = 'Reservada '.$seleccion;
    $idReservado = qa4RegistroPublico($especie, $seleccion, $reservada, ['state_province_visible' => false]);
    $biomaSinAcento = 'Choco '.$seleccion;
    DB::table('taxonomia.especimenes')->where('id', $idReservado)->update(['biome' => $biomaSinAcento]);
    $sinFilo = qa4TaxonPublico($seleccion.' sinFilo', 'especie');
    $privada = 'No publicada '.$seleccion;
    $idNoPublicado = qa4RegistroPublico($sinFilo, $seleccion, $privada);
    $otraRegion = 'Otra región '.$seleccion;
    $idOtraRegion = qa4RegistroPublico($especie, $seleccion, $otraRegion);
    $excluidas = ['preparations' => 'Preparación excluida '.$seleccion, 'biome' => 'Bioma excluido '.$seleccion, 'sampling_protocol' => 'Método excluido '.$seleccion];
    DB::table('taxonomia.especimenes')->where('id', $idNoPublicado)->update($excluidas);
    DB::table('taxonomia.especimenes')->where('id', $idOtraRegion)->update($excluidas + ['decimal_latitude' => 51.5, 'decimal_longitude' => -0.1]);
    expect(DB::table('taxonomia.especimenes')->where('id', $idOtraRegion)->value('coordenadas_otras_regiones'))->toBeTrue();
    $colectorVisible = 'Cárdenas '.Str::uuid();
    qa4RegistroPublico($especie, $colectorVisible, $provincia);
    $colectorOculto = 'Colector reservado '.Str::uuid();
    $idCamposOcultos = qa4RegistroPublico($especie, $colectorOculto, $provincia, ['recorded_by_visible' => false, 'sampling_protocol_visible' => false]);
    $metodoOculto = 'Técnica reservada '.$seleccion;
    DB::table('taxonomia.especimenes')->where('id', $idCamposOcultos)->update(['sampling_protocol' => $metodoOculto]);
    $componente = Livewire::withQueryParams(['vista' => 'registros', 'fco' => $seleccion])->test(PortalCatalogo::class)
        ->assertViewHas('totalRegistrosVista', 5)
        ->assertViewHas('provinciasDisponibles', fn (array $opciones): bool => in_array($provincia, $opciones, true)
            && ! in_array($reservada, $opciones, true) && ! in_array($privada, $opciones, true) && ! in_array($otraRegion, $opciones, true)
            && count(array_filter($opciones, fn (string $p): bool => ! \Modules\CatalogoPublico\Infrastructure\NormalizacionGeografica::contieneNombre($p))) === 0
            && array_values($opciones) === $opciones);
    foreach (['preparacionesDisponibles' => 'preparations', 'biomasDisponibles' => 'biome', 'metodosRecoleccionDisponibles' => 'sampling_protocol'] as $propiedad => $campo) {
        $opciones = $componente->instance()->{$propiedad};
        $clave = $campo === 'sampling_protocol'
            ? \Modules\CatalogoPublico\Infrastructure\ProtocoloColectaPublico::clave(...)
            : static fn (string $valor): string => $valor;
        expect($opciones)->toContain($clave($reales[$campo]))->not->toContain(...array_map($clave, $vacios))->not->toContain($clave($excluidas[$campo]));
        expect(array_values($opciones))->toBe($opciones);
    }
    expect($componente->instance()->biomasDisponibles)->toContain($biomaSinAcento)
        ->and($componente->instance()->metodosRecoleccionDisponibles)->not->toContain(\Modules\CatalogoPublico\Infrastructure\ProtocoloColectaPublico::clave($metodoOculto))
        ->and($componente->instance()->colectoresDisponibles)->toContain($colectorVisible)->not->toContain(...$vacios)->not->toContain($colectorOculto);
    $filos = $componente->instance()->filosDisponibles;
    expect(array_column($filos, 'id'))->toContain($filo)->not->toContain($filoInvisible)
        ->and(collect($filos)->firstWhere('id', $filo)['nombre_cientifico'])->toBe($seleccion);
    $repo = app(EloquentProveedorEspecimenesParaArbol::class);
    expect($repo->paginaPublica(FiltrosBusqueda::desde(['filtroColector' => $seleccion, 'filtroProvincia' => 'MANABI '.$seleccion]), 1)['ids'])->toBe([$idValido]);
    expect($repo->paginaPublica(FiltrosBusqueda::desde(['filtroColector' => $seleccion,
        'filtroPreparaciones' => [$reales['preparations']], 'filtroBiomas' => [$reales['biome']],
        'filtroMetodos' => [$reales['sampling_protocol']]]), 1)['ids'])->toBe([$idValido]);
    foreach ($idsVacios as $i => $id) {
        foreach (['state_province', 'preparations', 'biome', 'sampling_protocol'] as $campo) expect(DB::table('taxonomia.especimenes')->where('id', $id)->value($campo))->toBe($vacios[$i]);
    }
    foreach ($reales + ['state_province' => $provincia] as $campo => $texto) expect(DB::table('taxonomia.especimenes')->where('id', $idValido)->value($campo))->toBe($texto);
});
