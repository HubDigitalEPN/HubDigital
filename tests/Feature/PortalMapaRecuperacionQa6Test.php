<?php

declare(strict_types=1);

use Illuminate\Database\DeadlockException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Modules\CatalogoPublico\Infrastructure\ConsultaMapaNoDisponible;
use Modules\CatalogoPublico\Infrastructure\PresupuestoConsultaPortal;
use Modules\CatalogoPublico\Presentation\Http\Controllers\PortalCatalogo;
use Modules\CatalogoPublico\Presentation\Http\Controllers\PortalEstadisticas;

uses(Tests\DatabaseFeatureTestCase::class);

function seleccionMapaRecuperacionQa6(): array
{
    $filo = (string) Str::uuid();
    $especie = (string) Str::uuid();
    $nombre = 'Mapaqa'.Str::lower((string) preg_replace('/[^A-Za-z]/', '', Str::random(24))).' femoratus';
    DB::table('taxonomia.taxones')->insert([
        ['id' => $filo, 'padre_id' => null, 'nombre_cientifico' => $nombre.' filo', 'rango' => 'phylum'],
        ['id' => $especie, 'padre_id' => $filo, 'nombre_cientifico' => $nombre, 'rango' => 'especie'],
    ]);
    $id = (string) Str::uuid();
    $codigo = 'QA6-MAPA-'.Str::uuid();
    DB::table('taxonomia.especimenes')->insert([
        'id' => $id, 'codigo_catalogo' => $codigo, 'occurrence_id' => $codigo, 'taxon_id' => $especie,
        'localidad' => 'Localidad QA6 mapa', 'country' => 'Ecuador', 'state_province' => 'Orellana',
        'fecha_colecta' => '2025-01-10', 'decimal_latitude' => -0.658, 'decimal_longitude' => -76.452,
    ]);
    DB::table('divulgacion.especimenes_divulgables')->insert([
        'id' => (string) Str::uuid(), 'especimen_id' => $id, 'publicado' => true,
    ]);
    return ['codigo' => $codigo, 'parametros' => ['vista' => 'mapa', 'nivel' => 'species', 'taxon' => $nombre,
        'fph' => $filo, 'fpais' => 'Ecuador', 'fprov' => 'Orellana']];
}

function servicioMapaNoDisponibleQa6(): object
{
    // La resolución de esta colaboración se hace por el contenedor; el doble
    // conserva su firma y arroja la misma excepción de infraestructura del fallo real.
    $servicio = new class
    {
        public int $intentos = 0;

        public function datosParaVista(array $filtros, bool $incluirContenidoTaxon = true): array
        {
            $this->intentos++;
            throw new ConsultaMapaNoDisponible('SQLSTATE 57014: select pg_sleep; detalle privado de prueba');
        }
    };
    app()->instance(PortalEstadisticas::class, $servicio);
    return $servicio;
}

test('QA6 el error de mapa admite reintento explícito y volver a registros conserva la selección', function (): void {
    $f = seleccionMapaRecuperacionQa6();
    $servicio = servicioMapaNoDisponibleQa6();
    $portal = Livewire::withQueryParams($f['parametros'])->test(PortalCatalogo::class)
        ->assertViewHas('errorMapa', true)->assertViewHas('datosMapa', null)
        ->assertSee('No se pudo cargar el mapa')->assertSee('Reintentar mapa')
        ->assertSee('Ver registros de la selección')->assertSee('Tus filtros se conservan')
        ->assertDontSee('SQLSTATE')->assertDontSee('pg_sleep')
        ->assertSet('nivel', 'species')->assertSet('taxon', $f['parametros']['taxon'])
        ->assertSet('filtroPais', 'Ecuador')->assertSet('filtroProvincia', 'Orellana');
    expect($servicio->intentos)->toBe(1);
    $portal->call('$refresh')->assertViewHas('errorMapa', true)
        ->assertSet('filtroPais', 'Ecuador')->assertSet('filtroProvincia', 'Orellana');
    expect($servicio->intentos)->toBe(2);
    $portal->call('cambiarVista', 'registros')->assertSet('vista', 'registros')
        ->assertSet('nivel', 'species')->assertSet('taxon', $f['parametros']['taxon'])
        ->assertSet('filtroPais', 'Ecuador')->assertSet('filtroProvincia', 'Orellana')
        ->assertViewHas('totalRegistrosVista', 1)->assertSee($f['codigo']);
    expect($servicio->intentos)->toBe(2);
});

test('QA6 la URL directa de mapa muestra un error recuperable con la selección intacta', function (): void {
    $f = seleccionMapaRecuperacionQa6();
    servicioMapaNoDisponibleQa6();
    $this->get(route('portal.catalogo', $f['parametros']))->assertOk()
        ->assertSee('No se pudo cargar el mapa')->assertSee('Reintentar mapa')
        ->assertSee('Ver registros de la selección')->assertSee($f['parametros']['taxon'])
        ->assertDontSee('SQLSTATE')->assertDontSee('pg_sleep');
});

test('QA6 PostgreSQL cancela una consulta lenta dentro del presupuesto y restaura la conexión', function (): void {
    $antes = DB::selectOne('SHOW statement_timeout')->statement_timeout;
    $presupuesto = new PresupuestoConsultaPortal('fixture-qa6-timeout');
    $error = null;
    try {
        $presupuesto->ejecutar(static function (): array {
            $limite = DB::selectOne("SELECT extract(epoch FROM current_setting('statement_timeout')::interval) AS segundos");
            expect((float) $limite->segundos)->toBeGreaterThan(0)->toBeLessThanOrEqual(8);
            // Reduce sólo esta consulta de fixture: verifica la cancelación real
            // sin consumir los ocho segundos del límite productivo en el paquete.
            DB::selectOne("SELECT set_config('statement_timeout', '150ms', true)");
            DB::selectOne('SELECT pg_sleep(1)');
            return [];
        });
    } catch (ConsultaMapaNoDisponible $capturado) {
        $error = $capturado;
    }
    expect($error)->toBeInstanceOf(ConsultaMapaNoDisponible::class)
        ->and($error->getPrevious())->toBeInstanceOf(QueryException::class)
        ->and((string) $error->getPrevious()->getCode())->toBe('57014')
        ->and(DB::selectOne('SHOW statement_timeout')->statement_timeout)->toBe($antes)
        ->and((int) DB::selectOne('SELECT 1 AS disponible')->disponible)->toBe(1);
});

test('QA6 un error SQL de caché conserva el agregado y la conexión del mapa', function (string $operacion): void {
    $f = seleccionMapaRecuperacionQa6();
    $nivelAntes = DB::connection()->transactionLevel();
    $fallos = 0;
    Cache::shouldReceive('get')->andReturnUsing(static function (string $clave) use ($operacion, &$fallos): mixed {
        if ($operacion === 'leer' && str_starts_with($clave, 'portal:estadisticas:')) {
            $fallos++;
            // El error se produce realmente dentro de PostgreSQL: capturarlo sin
            // rollback dejaría abortada la transacción de toda la selección.
            DB::selectOne('SELECT 1 / 0 AS cache_fallida_qa6');
        }
        return null;
    });
    Cache::shouldReceive('put')->andReturnUsing(static function (string $clave, mixed $valor, mixed $ttl) use ($operacion, &$fallos): bool {
        if ($operacion === 'guardar' && str_starts_with($clave, 'portal:estadisticas:')) {
            $fallos++;
            DB::selectOne('SELECT 1 / 0 AS cache_fallida_qa6');
        }
        return true;
    });

    Livewire::withQueryParams($f['parametros'])->test(PortalCatalogo::class)
        ->assertViewHas('errorMapa', false)
        ->assertViewHas('datosMapa', static fn (array $datos): bool =>
            (int) $datos['resumen']['registros'] === 1
            && (int) $datos['resumen']['identificados'] === 1
            && count($datos['mapa']) === 1
            && $datos['mapa'][0]['lat'] === -0.658
            && $datos['mapa'][0]['lon'] === -76.452)
        ->assertSet('nivel', 'species')->assertSet('taxon', $f['parametros']['taxon'])
        ->assertSet('filtroFiloId', $f['parametros']['fph'])
        ->assertSet('filtroPais', 'Ecuador')->assertSet('filtroProvincia', 'Orellana')
        ->assertDontSee('No se pudo cargar el mapa')->assertDontSee('SQLSTATE')
        ->assertDontSee('cache_fallida_qa6');

    expect($fallos)->toBe(1)
        ->and(DB::connection()->transactionLevel())->toBe($nivelAntes)
        ->and((int) DB::selectOne('SELECT 1 AS disponible')->disponible)->toBe(1);
})->with(['leer', 'guardar']);

test('QA6 un conflicto de concurrencia de caché muestra error recuperable y conserva la selección', function (string $operacion): void {
    $f = seleccionMapaRecuperacionQa6();
    $nivelAntes = DB::connection()->transactionLevel();
    $fallos = 0;
    // Se simula la excepción de Laravel sin provocar bloqueos entre conexiones
    // reales. El presupuesto y los savepoints del render sí son los productivos.
    Cache::shouldReceive('get')->andReturnUsing(static function (string $clave) use ($operacion, &$fallos): mixed {
        if ($operacion === 'leer' && str_starts_with($clave, 'portal:estadisticas:')) {
            $fallos++;
            throw new DeadlockException('deadlock detected: detalle privado QA6');
        }
        return null;
    });
    Cache::shouldReceive('put')->andReturnUsing(static function (string $clave, mixed $valor, mixed $ttl) use ($operacion, &$fallos): bool {
        if ($operacion === 'guardar' && str_starts_with($clave, 'portal:estadisticas:')) {
            $fallos++;
            throw new DeadlockException('deadlock detected: detalle privado QA6');
        }
        return true;
    });

    Livewire::withQueryParams($f['parametros'])->test(PortalCatalogo::class)
        ->assertViewHas('errorMapa', true)->assertViewHas('datosMapa', null)
        ->assertSee('No se pudo cargar el mapa')->assertSee('Reintentar mapa')
        ->assertSet('nivel', 'species')->assertSet('taxon', $f['parametros']['taxon'])
        ->assertSet('filtroFiloId', $f['parametros']['fph'])
        ->assertSet('filtroPais', 'Ecuador')->assertSet('filtroProvincia', 'Orellana')
        ->assertDontSee('deadlock')->assertDontSee('detalle privado QA6');

    expect($fallos)->toBe(1)
        ->and(DB::connection()->transactionLevel())->toBe($nivelAntes)
        ->and((int) DB::selectOne('SELECT 1 AS disponible')->disponible)->toBe(1);
})->with(['leer', 'guardar']);
