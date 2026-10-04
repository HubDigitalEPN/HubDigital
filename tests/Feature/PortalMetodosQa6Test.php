<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Modules\CatalogoPublico\Application\Ports\ProveedorOpcionesFiltroPort;
use Modules\CatalogoPublico\Infrastructure\ProtocoloColectaPublico;
use Modules\CatalogoPublico\Presentation\Http\Controllers\PortalCatalogo;
use Modules\CatalogoPublico\Presentation\Http\Controllers\PortalEstadisticas;

uses(Tests\DatabaseFeatureTestCase::class);

test('QA6-006 cada categoría canónica selecciona su conteo con barra checkbox recarga y geografía previa', function (string $mayusculas, string $minusculas): void {
    $f = cartografiaRealFixture();
    DB::table('taxonomia.muestras_colecta')->where('id', $f['muestra'])->update(['sampling_protocol' => $minusculas]);
    DB::table('taxonomia.especimenes')->where('id', $f['ids'][0])->update(['sampling_protocol' => $mayusculas, 'muestra_id' => null]);
    DB::table('taxonomia.especimenes')->where('id', $f['ids'][1])->update(['sampling_protocol' => null]);
    DB::table('taxonomia.especimenes')->where('id', $f['ids'][2])->update(['sampling_protocol' => $minusculas]);
    $clave = ProtocoloColectaPublico::clave($mayusculas);
    $datos = app(PortalEstadisticas::class)->datosParaVista(['filo' => $f['filo']]);
    expect($datos['metodos'])->toHaveCount(1)
        ->and($datos['metodos'][0]['metodo'])->toBe($clave)
        ->and($datos['metodos'][0]['registros'])->toBe(3)
        ->and($datos['metodos'][0]['fuentes'])->toEqualCanonicalizing([$mayusculas, $minusculas]);
    $opciones = app(ProveedorOpcionesFiltroPort::class)->obtenerMetodosRecoleccion();
    expect(count(array_filter($opciones, fn (string $opcion): bool => $opcion === $clave)))->toBe(1)
        ->and($opciones)->not->toContain($mayusculas);

    $componente = Livewire::withQueryParams(['vista' => 'mapa', 'fph' => $f['filo'], 'fprov' => 'Pichincha'])->test(PortalCatalogo::class)
        ->assertViewHas('datosMapa', fn (array $datos): bool => $datos['metodos'][0]['registros'] === 2)
        ->call('seleccionarMetodo', $mayusculas)->assertSet('filtroMetodos', [$clave])->assertSet('filtroProvincia', 'Pichincha')
        ->assertViewHas('datosMapa', fn (array $datos): bool => (int) $datos['resumen']['registros'] === 2 && $datos['metodos'][0]['registros'] === 2);
    $componente->call('cambiarVista', 'registros')->assertViewHas('totalRegistrosVista', 2)
        ->assertViewHas('registrosVista', fn (array $filas): bool => count($filas) === 2 && array_diff([$mayusculas, $minusculas], array_column($filas, 'sampling_protocol')) === []);
    Livewire::withQueryParams(['vista' => 'registros', 'fph' => $f['filo'], 'fprov' => 'Pichincha', 'fm' => [$mayusculas]])
        ->test(PortalCatalogo::class)->assertViewHas('totalRegistrosVista', 2);
    Livewire::withQueryParams(['vista' => 'mapa', 'fph' => $f['filo'], 'fprov' => 'Pichincha'])->test(PortalCatalogo::class)
        ->set('borradorFiltros.filtroMetodos', [$clave])->call('aplicarBorrador')->assertHasNoErrors()
        ->assertViewHas('datosMapa', fn (array $datos): bool => (int) $datos['resumen']['registros'] === 2 && $datos['metodos'][0]['registros'] === 2);
})->with([
    'beating' => ['Beating', 'beating'],
    'pitfall' => ['Pitfall', 'pitfall'],
    'pitfall human faeces' => ['pitfall_human_faECes', 'pitfall_human_faeces'],
]);

test('QA6-006 las categorías no publican fuentes reservadas ni aportan sus registros al filtro', function (): void {
    $f = cartografiaRealFixture();
    DB::table('taxonomia.especimenes')->whereIn('id', $f['ids'])->update(['sampling_protocol' => 'Método QA6']);
    DB::table('divulgacion.especimenes_divulgables')->where('especimen_id', $f['ids'][1])->update(['sampling_protocol_visible' => false]);
    $taxonSinFilo = (string) Str::uuid();
    DB::table('taxonomia.taxones')->insert(['id' => $taxonSinFilo, 'nombre_cientifico' => 'Qa6reservado alpha', 'rango' => 'especie']);
    // La publicación es derivada por trigger; quitar el filo confirmado la revoca.
    DB::table('taxonomia.especimenes')->where('id', $f['ids'][2])->update(['taxon_id' => $taxonSinFilo]);
    expect((bool) DB::table('divulgacion.especimenes_divulgables')->where('especimen_id', $f['ids'][2])->value('publicado'))->toBeFalse();
    $datos = app(PortalEstadisticas::class)->datosParaVista(['filo' => $f['filo']]);
    expect($datos['metodos'])->toBe([['metodo' => 'método qa6', 'registros' => 1, 'fuentes' => ['Método QA6']]]);
    Livewire::withQueryParams(['vista' => 'registros', 'fph' => $f['filo'], 'fm' => ['Método QA6']])->test(PortalCatalogo::class)
        ->assertViewHas('totalRegistrosVista', 1)->assertViewHas('registrosVista', fn (array $filas): bool => $filas[0]->especimen_id === $f['ids'][0]);
});
