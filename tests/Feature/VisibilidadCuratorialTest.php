<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Modules\CatalogoPublico\Presentation\Http\Controllers\PortalCatalogo;

test('un ejemplar sin filo queda en curaduría y entra al CSV público al confirmar su linaje', function (): void {
    $ahora = now();
    $filo = (string) Str::uuid();
    $taxon = (string) Str::uuid();
    $especimen = (string) Str::uuid();
    $codigo = 'QA-CURA-'.Str::upper(Str::random(10));
    $ocurrencia = 'QA-CURA-'.Str::upper(Str::random(10));
    $nombre = 'Curatella '.Str::lower(Str::random(10));

    DB::table('taxonomia.taxones')->insert([
        ['id' => $filo, 'nombre_cientifico' => 'Filo QA '.substr($filo, 0, 8),
            'rango' => 'phylum', 'autor' => 'QA', 'anio_descripcion' => 2026,
            'estado' => 'activo', 'created_at' => $ahora, 'updated_at' => $ahora],
        ['id' => $taxon, 'nombre_cientifico' => $nombre,
            'rango' => 'especie', 'autor' => 'QA', 'anio_descripcion' => 2026,
            'estado' => 'activo', 'created_at' => $ahora, 'updated_at' => $ahora],
    ]);
    DB::table('taxonomia.especimenes')->insert([
        'id' => $especimen, 'codigo_catalogo' => $codigo,
        'occurrence_id' => $ocurrencia, 'taxon_id' => $taxon,
        'localidad' => 'Localidad de verificación curatorial',
        'fecha_colecta' => '2026-09-20', 'colector' => 'QA',
        'estado' => 'disponible', 'created_at' => $ahora, 'updated_at' => $ahora,
    ]);
    DB::table('divulgacion.especimenes_divulgables')->insert([
        'id' => (string) Str::uuid(), 'especimen_id' => $especimen,
        'publicado' => true, 'created_at' => $ahora, 'updated_at' => $ahora,
    ]);

    expect(DB::table('divulgacion.especimenes_divulgables')
        ->where('especimen_id', $especimen)->value('publicado'))->toBeFalse();

    $curador = User::factory()->curador()->create();
    $this->actingAs($curador)
        ->get(route('divulgacion.index', ['catalogo' => $ocurrencia, 'publicacion' => 'curaduria']))
        ->assertOk()->assertSee($ocurrencia);
    auth()->logout();

    $csvAntes = $this->get(route('portal.lista-especies'))->assertOk()->streamedContent();
    expect($csvAntes)->not->toContain($nombre);

    DB::table('taxonomia.taxones')->where('id', $taxon)->update(['padre_id' => $filo]);
    expect(DB::table('divulgacion.especimenes_divulgables')
        ->where('especimen_id', $especimen)->value('publicado'))->toBeTrue();
    $csvDespues = $this->get(route('portal.lista-especies'))->assertOk()->streamedContent();
    expect($csvDespues)->toContain($nombre);
});

test('Colección Biológica responde como primera pantalla pública', function (): void {
    $this->get(route('portal.inicio'))->assertRedirect(route('portal.catalogo', ['vista' => 'mapa']));
    $this->get(route('portal.estadisticas'))->assertRedirect(route('portal.catalogo', ['vista' => 'mapa']));
    $this->get(route('portal.catalogo', ['vista' => 'mapa']))
        ->assertOk()
        ->assertSee('Colección Biológica')
        ->assertSee('Filtros de investigación')
        ->assertSee('Riqueza por provincia')
        ->assertSee('Vista de registros');
    $this->get('/portal/comparar-especies')->assertNotFound();
});

test('las tres vistas se alternan en el mismo componente y conservan la selección', function (): void {
    $taxonSinCoincidencias = 'QA-SIN-RESULTADOS-'.Str::uuid();

    Livewire::test(PortalCatalogo::class)
        ->assertSee('Catálogo del laboratorio de invertebrados')
        ->assertDontSee('Volver al árbol')
        ->set('filtroTaxon', $taxonSinCoincidencias)
        ->call('cambiarVista', 'mapa')->assertSet('vista', 'mapa')->assertSet('filtroTaxon', $taxonSinCoincidencias)
        ->assertSee('Riqueza por provincia')
        ->assertSee('No hay especies con provincia pública en esta selección.')
        ->assertSee('No hay fechas e identificaciones a especie visibles.')
        ->assertDontSee('Catálogo del laboratorio de invertebrados')->assertDontSee('Volver al árbol')
        ->call('cambiarVista', 'registros')->assertSet('vista', 'registros')->assertSet('filtroTaxon', $taxonSinCoincidencias)
        ->assertSee('Registros del catálogo')->assertDontSee('Riqueza por provincia')->assertDontSee('Volver al árbol')
        ->call('cambiarVista', 'tarjetas')->assertSet('vista', 'tarjetas')->assertSet('filtroTaxon', $taxonSinCoincidencias)
        ->assertSee('Catálogo del laboratorio de invertebrados')->assertDontSee('Riqueza por provincia')
        ->call('explorarNivel', 'phylum')->assertSet('explorar', 'phylum')
        ->assertSee('Volver al árbol')->assertDontSee('Catálogo del laboratorio de invertebrados')
        ->call('cambiarVista', 'mapa')->assertSet('explorar', 'phylum')->assertSet('filtroTaxon', $taxonSinCoincidencias)
        ->assertSee('Riqueza por provincia')->assertDontSee('Volver al árbol')
        ->call('cambiarVista', 'tarjetas')->assertSet('explorar', 'phylum')->assertSet('filtroTaxon', $taxonSinCoincidencias)
        ->assertSee('Volver al árbol')
        ->call('volverAlArbol')->assertSee('Catálogo del laboratorio de invertebrados')->assertDontSee('Volver al árbol');
});

test('los filtros públicos se aplican también a la lista CSV y respetan la ubicación visible', function (): void {
    $ahora = now();
    $filo = (string) Str::uuid();
    $colector = 'Filtro QA '.Str::uuid();
    $conUbicacion = 'Especie visible '.Str::lower(Str::random(8));
    $sinUbicacion = 'Especie sin coordenadas '.Str::lower(Str::random(8));
    $metodoRestringido = 'Especie método restringido '.Str::lower(Str::random(8));
    $metodo = 'Protocolo QA '.Str::uuid();
    $muestra = (string) Str::uuid();

    DB::table('taxonomia.muestras_colecta')->insert([
        'id' => $muestra, 'sampling_protocol' => $metodo,
        'created_at' => $ahora, 'updated_at' => $ahora,
    ]);

    DB::table('taxonomia.taxones')->insert([
        ['id' => $filo, 'padre_id' => null, 'nombre_cientifico' => 'Filo filtro '.substr($filo, 0, 8), 'rango' => 'phylum', 'autor' => 'QA', 'anio_descripcion' => 2026, 'estado' => 'activo', 'created_at' => $ahora, 'updated_at' => $ahora],
        ['id' => $taxonVisible = (string) Str::uuid(), 'padre_id' => $filo, 'nombre_cientifico' => $conUbicacion, 'rango' => 'especie', 'autor' => 'QA', 'anio_descripcion' => 2026, 'estado' => 'activo', 'created_at' => $ahora, 'updated_at' => $ahora],
        ['id' => $taxonSinUbicacion = (string) Str::uuid(), 'padre_id' => $filo, 'nombre_cientifico' => $sinUbicacion, 'rango' => 'especie', 'autor' => 'QA', 'anio_descripcion' => 2026, 'estado' => 'activo', 'created_at' => $ahora, 'updated_at' => $ahora],
        ['id' => $taxonMetodoRestringido = (string) Str::uuid(), 'padre_id' => $filo, 'nombre_cientifico' => $metodoRestringido, 'rango' => 'especie', 'autor' => 'QA', 'anio_descripcion' => 2026, 'estado' => 'activo', 'created_at' => $ahora, 'updated_at' => $ahora],
    ]);

    foreach ([[$taxonVisible, -0.21, -78.51, true], [$taxonSinUbicacion, null, null, true], [$taxonMetodoRestringido, -0.24, -78.55, false]] as [$taxon, $latitud, $longitud, $metodoVisible]) {
        $especimen = (string) Str::uuid();
        DB::table('taxonomia.especimenes')->insert([
            'id' => $especimen, 'codigo_catalogo' => 'QA-EST-'.Str::upper(Str::random(10)),
            'occurrence_id' => 'QA-EST-'.Str::upper(Str::random(10)), 'taxon_id' => $taxon, 'muestra_id' => $muestra,
            'localidad' => 'Quito', 'state_province' => 'Pichincha', 'colector' => $colector,
            'fecha_colecta' => '2025-06-01', 'decimal_latitude' => $latitud,
            'decimal_longitude' => $longitud, 'estado' => 'disponible',
            'created_at' => $ahora, 'updated_at' => $ahora,
        ]);
        DB::table('divulgacion.especimenes_divulgables')->insert([
            'id' => (string) Str::uuid(), 'especimen_id' => $especimen,
            'publicado' => true, 'sampling_protocol_visible' => $metodoVisible,
            'created_at' => $ahora, 'updated_at' => $ahora,
        ]);
    }

    Cache::forget('portal:provincias:v4');
    Cache::forget('portal:metodos-opciones:v1');
    $filtros = ['filo' => $filo, 'provincia' => 'Pichincha', 'colector' => $colector,
        'desde' => 2025, 'hasta' => 2025, 'mes' => 6, 'aptitud' => 'completos',
        'identificacion' => 'especie', 'ubicacion' => '1', 'metodo' => $metodo];
    $redireccion = $this->get(route('portal.estadisticas', $filtros))->assertRedirect();
    $destino = $redireccion->baseResponse->headers->get('Location');
    expect($destino)->toContain('fprov=Pichincha', 'fmes=6', 'vista=mapa');
    $this->get($destino)->assertOk()
        ->assertSee('1 registros públicos')->assertSee($metodo)->assertDontSee($sinUbicacion)->assertDontSee($metodoRestringido);
    $csv = $this->get(route('portal.lista-especies', $filtros))->assertOk()->streamedContent();
    expect($csv)->toContain($conUbicacion)->not->toContain($sinUbicacion)->not->toContain($metodoRestringido);
    $taxonRedireccion = $this->get(route('portal.estadisticas', ['taxon' => $conUbicacion, 'mes' => 6]))->assertRedirect();
    $this->get($taxonRedireccion->baseResponse->headers->get('Location'))
        ->assertOk()->assertSee($conUbicacion)->assertDontSee($sinUbicacion);
    $this->get(route('portal.catalogo', [
        'vista' => 'registros', 'fph' => $filo, 'fprov' => 'Pichincha',
        'ffd' => '2025-01-01', 'ffh' => '2025-12-31', 'fmes' => '6',
        'fid' => 'especie', 'fgeo' => '1', 'fap' => '1', 'fco' => $colector, 'fm' => [$metodo],
    ]))->assertOk()->assertSee($conUbicacion)
        ->assertDontSee($sinUbicacion)->assertDontSee($metodoRestringido);
});
