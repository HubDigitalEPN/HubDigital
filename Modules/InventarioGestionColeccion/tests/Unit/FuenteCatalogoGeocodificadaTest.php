<?php

declare(strict_types=1);

use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\Ports\GeocodificadorInversoPort;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\Ports\UbicacionGeocodificada;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Importers\Contracts\FuenteCatalogoIterator;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Importers\FilaCatalogoMapper;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Importers\FuenteCatalogoGeocodificada;

/** Geocoder falso que cuenta llamadas y devuelve siempre la misma ubicación. */
class GeoFakeStub implements GeocodificadorInversoPort
{
    public int $llamadas = 0;

    public function __construct(private ?UbicacionGeocodificada $u) {}

    public function geocodificar(float $latitud, float $longitud): ?UbicacionGeocodificada
    {
        $this->llamadas++;

        return $this->u;
    }
}

/** Fuente en-memoria para el decorador. */
class FuenteArrayStub implements FuenteCatalogoIterator
{
    public function __construct(private array $filas) {}

    public function iterar(): Generator
    {
        $n = 0;
        foreach ($this->filas as $f) {
            $n++;
            yield $n => $f;
        }
    }

    public function headers(): array
    {
        return [];
    }
}

function ubicacionFake(): UbicacionGeocodificada
{
    return new UbicacionGeocodificada(
        country: 'Ecuador',
        stateProvince: 'Sucumbíos',
        municipality: 'Shushufindi',
        poblado: 'Limoncocha',
        displayName: 'Limoncocha, Shushufindi, Sucumbíos, Ecuador',
    );
}

function decorar(array $filas, GeoFakeStub $geo, ?int $maxLookups = null): array
{
    $dec = new FuenteCatalogoGeocodificada(
        new FuenteArrayStub($filas),
        $geo,
        new FilaCatalogoMapper,
        throttleMs: 0,
        maxLookups: $maxLookups,
    );

    return [iterator_to_array($dec->iterar()), $dec];
}

test('rellena país/provincia/cantón/localidad faltantes desde las coordenadas', function (): void {
    $geo = new GeoFakeStub(ubicacionFake());
    [$filas] = decorar([
        ['occurrenceID' => 'A', 'decimalLatitude' => '-0.4', 'decimalLongitude' => '-76.5'],
    ], $geo);

    expect($filas[1]['country'])->toBe('Ecuador')
        ->and($filas[1]['state_province'])->toBe('Sucumbíos')
        ->and($filas[1]['municipality'])->toBe('Shushufindi')
        ->and($filas[1]['locality_name'])->toBe('Limoncocha')
        ->and($geo->llamadas)->toBe(1);
});

test('con coordenadas REEMPLAZA los campos administrativos y preserva la ubicación anterior', function (): void {
    $geo = new GeoFakeStub(ubicacionFake());
    [$filas] = decorar([
        ['occurrenceID' => 'A', 'country' => 'Perú', 'stateProvince' => 'Loreto', 'localityName' => 'Río Napo', 'decimalLatitude' => '-0.4', 'decimalLongitude' => '-76.5'],
    ], $geo);

    // País/provincia/cantón se reemplazan por lo de las coordenadas.
    expect($filas[1]['country'])->toBe('Ecuador')
        ->and($filas[1]['state_province'])->toBe('Sucumbíos')
        ->and($filas[1]['municipality'])->toBe('Shushufindi')
        // La localidad específica anotada por el colector NO se pisa.
        ->and($filas[1]['localityName'])->toBe('Río Napo')
        // La ubicación anterior (la del Excel) se preserva en localidad_verbatim.
        ->and($filas[1]['localidad_verbatim'])->toContain('Perú')
        ->and($filas[1]['localidad_verbatim'])->toContain('Loreto');
});

test('sin coordenadas mantiene la ubicación anterior tal cual', function (): void {
    $geo = new GeoFakeStub(ubicacionFake());
    [$filas] = decorar([
        ['occurrenceID' => 'A', 'country' => 'Perú', 'decimalLatitude' => '', 'decimalLongitude' => ''],
    ], $geo);

    expect($geo->llamadas)->toBe(0)
        ->and($filas[1]['country'])->toBe('Perú')
        ->and($filas[1])->not->toHaveKey('localidad_verbatim');
});

test('deduplica por coordenada: dos filas del mismo sitio = una sola consulta', function (): void {
    $geo = new GeoFakeStub(ubicacionFake());
    [$filas] = decorar([
        ['occurrenceID' => 'A', 'decimalLatitude' => '-0.4', 'decimalLongitude' => '-76.5'],
        ['occurrenceID' => 'B', 'decimalLatitude' => '-0.4', 'decimalLongitude' => '-76.5'],
    ], $geo);

    expect($geo->llamadas)->toBe(1)
        ->and($filas[1]['country'])->toBe('Ecuador')
        ->and($filas[2]['country'])->toBe('Ecuador');
});

test('sin coordenadas no consulta ni modifica la fila', function (): void {
    $geo = new GeoFakeStub(ubicacionFake());
    [$filas] = decorar([
        ['occurrenceID' => 'A', 'recordedBy' => 'Juan'],
    ], $geo);

    expect($geo->llamadas)->toBe(0)
        ->and($filas[1])->not->toHaveKey('country');
});

test('respeta el tope maxLookups', function (): void {
    $geo = new GeoFakeStub(ubicacionFake());
    [$filas] = decorar([
        ['occurrenceID' => 'A', 'decimalLatitude' => '-0.4', 'decimalLongitude' => '-76.5'],
        ['occurrenceID' => 'B', 'decimalLatitude' => '1.2', 'decimalLongitude' => '-78.9'],
    ], $geo, maxLookups: 1);

    expect($geo->llamadas)->toBe(1)
        ->and($filas[1]['country'])->toBe('Ecuador')
        ->and($filas[2])->not->toHaveKey('country');
});
