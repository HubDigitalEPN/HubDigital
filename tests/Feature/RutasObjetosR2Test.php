<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Modules\CatalogoPublico\Infrastructure\Adapters\StorageImagenesAdapter;
use Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\GestionRegistrosTaxonomicos\DescargarActaEntregaController;

beforeEach(function (): void {
    config()->set('deposit-storage.driver', 'r2');
    config()->set('deposit-storage.require_remote', true);
    config()->set('deposit-storage.verify_after_write', true);
    config()->set('deposit-storage.r2', [
        'endpoint' => 'https://cuenta-pruebas.r2.cloudflarestorage.com',
        'bucket' => 'hubdigital-pruebas',
        'access_key_id' => 'clave-pruebas',
        'secret_access_key' => 'secreto-pruebas',
        'max_attempts' => 1,
        'timeout_seconds' => 5,
        'connect_timeout_seconds' => 2,
    ]);
});

/** @return array{ruta: string, contenido: string, objeto: string, especimen_id: string} */
function imagenPublicadaR2DePrueba(): array
{
    $ahora = now();
    $contenido = 'imagen-r2-qa';
    $phylumId = (string) Str::uuid();
    $taxonId = (string) Str::uuid();
    $especimenId = (string) Str::uuid();
    $occurrenceId = 'QA-R2-'.Str::lower(Str::random(12));
    $ruta = 'divulgacion/imagenes/qa-r2-'.Str::lower(Str::random(12)).'.jpg';

    DB::table('taxonomia.taxones')->insert([
        'id' => $phylumId,
        'nombre_cientifico' => 'Filo QA '.substr($phylumId, 0, 8),
        'rango' => 'phylum',
        'autor' => 'QA',
        'anio_descripcion' => 2026,
        'estado' => 'activo',
        'created_at' => $ahora,
        'updated_at' => $ahora,
    ]);
    DB::table('taxonomia.taxones')->insert([
        'id' => $taxonId,
        'nombre_cientifico' => 'Taxon QA '.Str::random(8),
        'rango' => 'species',
        'padre_id' => $phylumId,
        'autor' => 'QA',
        'anio_descripcion' => 2026,
        'estado' => 'activo',
        'created_at' => $ahora,
        'updated_at' => $ahora,
    ]);
    DB::table('taxonomia.especimenes')->insert([
        'id' => $especimenId,
        'codigo_catalogo' => 'QA-R2-'.Str::upper(Str::random(12)),
        'occurrence_id' => $occurrenceId,
        'taxon_id' => $taxonId,
        'localidad' => 'Coleccion de pruebas aislada',
        'fecha_colecta' => '2026-09-20',
        'colector' => 'QA',
        'estado' => 'disponible',
        'created_at' => $ahora,
        'updated_at' => $ahora,
    ]);
    DB::table('divulgacion.especimenes_divulgables')->insert([
        'id' => (string) Str::uuid(),
        'especimen_id' => $especimenId,
        'created_at' => $ahora,
        'updated_at' => $ahora,
    ]);
    DB::table('divulgacion.imagenes_taxonomicas')->insert([
        'id' => (string) Str::uuid(),
        'occurrence_id' => $occurrenceId,
        'nombre_original' => basename($ruta),
        'ruta' => $ruta,
        'disco' => 'r2',
        'sha256' => hash('sha256', $contenido),
        'autor_nombre' => 'QA',
        'autor_apellido' => 'Pruebas',
        'autor_nombre_completo' => 'QA Pruebas',
        'created_at' => $ahora,
        'updated_at' => $ahora,
    ]);

    return [
        'ruta' => $ruta,
        'contenido' => $contenido,
        'objeto' => rtrim(strtr(base64_encode($ruta), '+/', '-_'), '='),
        'especimen_id' => $especimenId,
    ];
}

function simularObjetoR2(string $contenido, string $mime): void
{
    Http::fake(static function (Request $request) use ($contenido, $mime) {
        return match ($request->method()) {
            'HEAD' => Http::response('', 200, [
                'Content-Type' => $mime,
                'Content-Length' => (string) strlen($contenido),
            ]),
            'GET' => Http::response($contenido, 200, ['Content-Type' => $mime]),
            default => Http::response('', 404),
        };
    });
}

test('la imagen divulgada se sirve desde R2 y la despublicada no consulta el objeto', function (): void {
    $imagen = imagenPublicadaR2DePrueba();
    simularObjetoR2($imagen['contenido'], 'image/jpeg');

    $respuesta = $this->get(route('portal.imagen', ['objeto' => $imagen['objeto']]))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/jpeg')
        ->assertStreamedContent($imagen['contenido']);
    expect($respuesta->headers->get('Cache-Control'))
        ->toContain('public')
        ->toContain('max-age=300');
    Http::assertSent(static fn (Request $request): bool => $request->method() === 'GET');

    DB::table('divulgacion.especimenes_divulgables')
        ->where('especimen_id', $imagen['especimen_id'])
        ->delete();
    Http::fake();

    $this->get(route('portal.imagen', ['objeto' => $imagen['objeto']]))->assertNotFound();
    Http::assertNothingSent();
});

test('una imagen de otras regiones permanece almacenada pero no se sirve al portal público', function (): void {
    $imagen = imagenPublicadaR2DePrueba();
    DB::table('taxonomia.especimenes')->where('id', $imagen['especimen_id'])->update(['decimal_latitude' => 40.4, 'decimal_longitude' => -3.7]);
    Http::fake();
    $this->get(route('portal.imagen', ['objeto' => $imagen['objeto']]))->assertNotFound();
    Http::assertNothingSent();
    expect(DB::table('divulgacion.imagenes_taxonomicas')->where('ruta', $imagen['ruta'])->exists())->toBeTrue();
});

test('una foto con etiqueta compartida no se atribuye a otro ejemplar ni consulta R2', function (): void {
    $imagen = imagenPublicadaR2DePrueba();
    $original = (array) DB::table('taxonomia.especimenes')->where('id', $imagen['especimen_id'])->first();
    unset($original['coordenadas_otras_regiones'], $original['busqueda_global']);
    $original['id'] = (string) Str::uuid();
    $original['codigo_catalogo'] = 'QA-ETIQUETA-'.Str::upper(Str::random(12));
    DB::table('taxonomia.especimenes')->insert($original);
    Http::fake();

    $this->get(route('portal.imagen', ['objeto' => $imagen['objeto']]))->assertNotFound();
    Http::assertNothingSent();
    expect(DB::table('divulgacion.imagenes_taxonomicas')->where('ruta', $imagen['ruta'])->exists())->toBeTrue();
});

test('la descarga de acta exige ability, rol, entidad vigente y una ruta no manipulada', function (): void {
    $ahora = now();
    $entidadId = (string) Str::uuid();
    DB::table('taxonomia.entidades_depositantes')->insert([
        'id' => $entidadId,
        'nombre' => 'Entidad QA '.Str::random(10),
        'tipo' => 'institucion',
        'contacto' => 'qa@example.test',
        'created_at' => $ahora,
        'updated_at' => $ahora,
    ]);
    $ruta = 'inventario/actas/acta_entrega_'.$entidadId.'_2026-09-20_12-00-00.txt';
    simularObjetoR2('acta-r2-qa', 'text/plain; charset=UTF-8');

    $sinPermiso = User::factory()->depositante()->create();
    Sanctum::actingAs($sinPermiso, ['depositos:gestionar']);
    $this->getJson(DescargarActaEntregaController::urlParaRuta($ruta))->assertForbidden();

    $curador = User::factory()->curador()->create();
    Sanctum::actingAs($curador, ['curaduria:gestionar']);
    $this->get(DescargarActaEntregaController::urlParaRuta($ruta))
        ->assertOk()
        ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
        ->assertStreamedContent('acta-r2-qa');

    $entidadInexistente = (string) Str::uuid();
    $rutaInexistente = 'inventario/actas/acta_entrega_'.$entidadInexistente.'_2026-09-20_12-00-00.txt';
    $this->getJson(DescargarActaEntregaController::urlParaRuta($rutaInexistente))->assertNotFound();

    $manipulada = rtrim(strtr(base64_encode('inventario/actas/../acta.txt'), '+/', '-_'), '=');
    $this->getJson(route('api.api.v1.taxonomia.entidades-depositantes.actas.descargar', ['objeto' => $manipulada]))
        ->assertNotFound();
});
