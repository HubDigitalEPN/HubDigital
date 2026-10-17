<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\CatalogoPublico\Infrastructure\FichasTaxonomicas;
use Modules\CatalogoPublico\Infrastructure\ImportadorFichasAvif;

uses(Tests\DatabaseFeatureTestCase::class);

function importacionFichaAvifFixture(): array
{
    config(['deposit-storage.driver' => 'local', 'deposit-storage.require_remote' => false]); Storage::fake('local');
    $padre = null; $linaje = []; $prefijo = 'Importacion'.Str::random(12);
    foreach (['phylum', 'clase'] as $rango) {
        $id = (string) Str::uuid(); $nombre = $prefijo.' '.$rango;
        DB::table('taxonomia.taxones')->insert(['id' => $id, 'padre_id' => $padre, 'nombre_cientifico' => $nombre, 'rango' => $rango]);
        $linaje[] = [$rango, $nombre]; $padre = $id;
    }
    $fichas = app(FichasTaxonomicas::class);
    $imagenReferencia = UploadedFile::fake()->image('referencia.png', 720, 540);
    $foto = $fichas->prepararFoto((string) file_get_contents($imagenReferencia->getRealPath()));
    $carpeta = Storage::disk('local')->path('divulgacion/fichas');
    return [[
        'taxon_id' => $id, 'nombre' => $nombre, 'rango' => $rango, 'linaje' => $linaje,
        'foto_ruta' => $foto['foto_ruta'], 'foto_sha256' => $foto['foto_sha256'], 'foto_id' => 12345,
        'descripcion' => 'Leyenda de referencia.', 'foto_alt' => 'Organismo representativo de la clase.',
        'credito' => 'Autor de referencia · CC BY · Convertida a AVIF', 'fuente_url' => 'https://www.inaturalist.org/photos/12345',
        'licencia' => 'cc-by', 'coincidencia_exacta' => true, 'publicada' => true, 'sha_anterior' => null, 'version_anterior' => '',
    ], $carpeta];
}

test('la carga AVIF certificada por linaje es idempotente y conserva una edición posterior del administrador', function (): void {
    [$entrada, $carpeta] = importacionFichaAvifFixture(); $importador = app(ImportadorFichasAvif::class);
    $p = $importador->preparar($entrada, $carpeta);
    expect($p['estado'])->toBe('lista')->and($importador->aplicar($p))->toBe('importada');
    DB::table(FichasTaxonomicas::TABLA)->where('taxon_id', $entrada['taxon_id'])->update(['descripcion' => 'Leyenda del biólogo']);
    expect($importador->aplicar($importador->preparar($entrada, $carpeta)))->toBe('ya_importada')
        ->and(app(FichasTaxonomicas::class)->buscar($entrada['taxon_id'])->descripcion)->toBe('Leyenda del biólogo');
});

test('la carga rechaza fuentes sin licencia, otro linaje y especies sin correspondencia exacta', function (): void {
    [$entrada, $carpeta] = importacionFichaAvifFixture(); $importador = app(ImportadorFichasAvif::class);
    foreach ([['licencia' => 'all-rights-reserved'], ['foto_ruta' => '../privado.avif'], ['linaje' => [['clase', $entrada['nombre']]]],
        ['fuente_url' => 'https://example.org/foto'], ['foto_alt' => '']] as $cambio) {
        expect(fn () => $importador->preparar(array_replace($entrada, $cambio), $carpeta))->toThrow(InvalidArgumentException::class);
    }
    DB::table('taxonomia.taxones')->where('id', $entrada['taxon_id'])->update(['rango' => 'especie']);
    $entrada['rango'] = 'especie'; $entrada['linaje'][1][0] = 'especie'; $entrada['coincidencia_exacta'] = false;
    expect(fn () => $importador->preparar($entrada, $carpeta))->toThrow(InvalidArgumentException::class);
    expect(DB::table(FichasTaxonomicas::TABLA)->where('taxon_id', $entrada['taxon_id'])->exists())->toBeFalse();
});

test('una edición concurrente y una miniatura alterada impiden sobrescribir una ficha editorial', function (): void {
    [$entrada, $carpeta] = importacionFichaAvifFixture(); $importador = app(ImportadorFichasAvif::class);
    $p = $importador->preparar($entrada, $carpeta);
    app(FichasTaxonomicas::class)->guardar($entrada['taxon_id'], ['descripcion' => 'Cambio concurrente'], null, false, null);
    expect(fn () => $importador->aplicar($p))->toThrow(\Illuminate\Validation\ValidationException::class);
    expect($importador->preparar($entrada, $carpeta)['estado'])->toBe('conflicto')
        ->and(app(FichasTaxonomicas::class)->buscar($entrada['taxon_id'])->descripcion)->toBe('Cambio concurrente');
    file_put_contents($carpeta.DIRECTORY_SEPARATOR.$entrada['foto_sha256'].'.avif', 'otro contenido');
    expect(fn () => $importador->preparar($entrada, $carpeta))->toThrow(InvalidArgumentException::class);
});
