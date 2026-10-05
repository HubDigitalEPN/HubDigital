<?php

declare(strict_types=1);

use App\Support\NormalizadorNombreCatalogo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\CatalogoPublico\Infrastructure\LocalidadPublica;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Especimen;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\EspecimenRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Services\DesgloseLocalidad;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Services\MapeadorFilaEspecimen;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\EspecimenId;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\RevisionNombreCientifico;

uses(Tests\DatabaseFeatureTestCase::class);

function revisionCientificaFixture(): array
{
    $prefijo = 'AUDCIEN-'.Str::uuid();
    $estados = ['vacio', 'no_cientifico', 'codigo_local', 'corregido', 'reconocido', null];
    $ids = [];
    foreach ($estados as $i => $estado) {
        $id = $ids[$i] = (string) Str::uuid();
        $taxon = $i >= 2 ? (string) Str::uuid() : null;
        $nombre = $taxon === null ? null : 'Auditociencia '.chr(97 + $i);
        $verbatim = match ($i) { 0 => '_', 1 => 'N/D', default => $nombre };
        if ($taxon !== null) DB::table('taxonomia.taxones')->insert(['id' => $taxon, 'rango' => 'especie', 'nombre_cientifico' => $nombre]);
        DB::table('taxonomia.especimenes')->insert(['id' => $id, 'codigo_catalogo' => $prefijo.'-'.$i, 'taxon_id' => $taxon, 'taxon_verbatim' => $verbatim]);
        if ($estado !== null) DB::table(RevisionNombreCientifico::TABLA)->insert([
            'especimen_id' => $id, 'taxon_id' => $taxon, 'nombre_original' => $i === 3 ? 'Auditociencia mal escrito' : ($nombre ?? $verbatim),
            'nombre_revisado' => $nombre ?? $verbatim, 'taxon_verbatim_original' => $verbatim, 'estado' => $estado,
            'motivo' => 'Contraste documental de prueba; no acredita identificación física.',
        ]);
    }
    return compact('prefijo', 'ids');
}

test('las colas científicas filtran todo el inventario y mantienen total y paginación', function (): void {
    Http::preventStrayRequests();
    ['prefijo' => $prefijo, 'ids' => $ids] = revisionCientificaFixture();
    $repo = app(EspecimenRepositoryInterface::class);
    $filtros = ['codigoCatalogo' => $prefijo, 'incidencia' => 'nombres_cientificos', 'limit' => 2];
    $primera = $repo->buscarPaginaConTotal($filtros);
    $segunda = $repo->buscarPaginaConTotal($filtros + ['offset' => 2]);
    expect($primera['total'])->toBe(4)->and($segunda['total'])->toBe(4)
        ->and(array_map(fn ($e) => (string) $e->id(), $primera['especimenes']))->toBe([$ids[0], $ids[1]])
        ->and(array_map(fn ($e) => (string) $e->id(), $segunda['especimenes']))->toBe([$ids[2], $ids[5]]);
    foreach (['nombres_vacios' => 0, 'nombres_no_cientificos' => 1, 'nombres_corregidos' => 3] as $incidencia => $i) {
        $filtrados = $repo->buscarConFiltros(['codigoCatalogo' => $prefijo, 'incidencia' => $incidencia]);
        expect(array_map(fn ($e) => (string) $e->id(), $filtrados))->toBe([$ids[$i]]);
    }
    Http::assertNothingSent();
});

test('editar el nombre o su texto fuente invalida el contraste anterior sin borrar su evidencia', function (): void {
    ['prefijo' => $prefijo, 'ids' => $ids] = revisionCientificaFixture();
    $repo = app(EspecimenRepositoryInterface::class);
    DB::table('taxonomia.especimenes')->where('id', $ids[4])->update(['taxon_verbatim' => 'Determinación revisada por curaduría']);
    $filtrados = $repo->buscarConFiltros(['codigoCatalogo' => $prefijo, 'incidencia' => 'nombres_sin_revisar']);
    expect(array_map(fn ($e) => (string) $e->id(), $filtrados))->toBe([$ids[4], $ids[5]]);
    $pagina = $repo->buscarPaginaConTotal(['codigoCatalogo' => $prefijo]);
    $filas = array_map(fn ($e) => MapeadorFilaEspecimen::mapear($e, $pagina['nombresTaxon'][$e->taxonId()] ?? null), $pagina['especimenes']);
    $revisadas = app(RevisionNombreCientifico::class)->completar($filas);
    expect($revisadas[4]['revisionNombreCientifico'])->toBe('Volver a revisar: nombre modificado')
        ->and($revisadas[3]['nombreCientificoOriginal'])->toBe('Auditociencia mal escrito')
        ->and($revisadas[0]['revisionNombreCientifico'])->toBe('Nombre vacío')
        ->and($revisadas[5]['revisionNombreCientifico'])->toBe('Sin revisar');
    $taxonCorregido = DB::table('taxonomia.especimenes')->where('id', $ids[3])->value('taxon_id');
    DB::table('taxonomia.taxones')->where('id', $taxonCorregido)->update(['nombre_cientifico' => 'Auditociencia nueva']);
    expect($repo->contarConFiltros(['codigoCatalogo' => $prefijo, 'incidencia' => 'nombres_corregidos']))->toBe(0)
        ->and(DB::table(RevisionNombreCientifico::TABLA)->where('especimen_id', $ids[3])->value('nombre_original'))->toBe('Auditociencia mal escrito');
});

test('la localidad larga conserva sus tres partes y el texto fuente al guardar y editar en masa', function (): void {
    $repo = app(EspecimenRepositoryInterface::class);
    $sector = str_repeat('Sector del sendero ', 20);
    $e = Especimen::crear(EspecimenId::generar(), 'AUDLOC-'.Str::uuid(), null, 'Reserva de prueba', '', '',
        localidad2: 'Archidona', localidad3: $sector, localidadVerbatim: 'Texto fuente sin modificar');
    $repo->guardar($e);
    $id = (string) $e->id();
    $repo->fijarCampoPorIds([$id], 'localidad2', 'Tena');
    $fila = DB::table('taxonomia.especimenes')->where('id', $id)->first();
    $guardado = $repo->buscarPorId($e->id());
    expect($guardado->localidad())->toBe('Reserva de prueba')->and($guardado->localidad2())->toBe('Tena')
        ->and($guardado->localidad3())->toBe(trim($sector))->and($fila->localidad_verbatim)->toBe('Texto fuente sin modificar')
        ->and(LocalidadPublica::desdeFila($fila))->toBe('Reserva de prueba, Tena, '.trim($sector));
    $localidadSql = DB::selectOne('SELECT '.LocalidadPublica::sql().' AS localidad FROM taxonomia.especimenes e WHERE e.id = ?', [$id]);
    expect($localidadSql->localidad)->toBe(LocalidadPublica::desdeFila($fila));
    expect($repo->buscarConFiltros(['codigoCatalogo' => 'AUDLOC-', 'busquedaGlobal' => 'Sector del sendero']))->toHaveCount(1);
    // Las filas históricas y las correcciones del campo publicado conservan
    // prioridad cuando todavía no existe un desglose aprobado.
    DB::table('taxonomia.especimenes')->where('id', $id)->update(['locality_name' => 'Localidad pública corregida', 'localidad_desglosada' => false]);
    $filaHistorica = DB::table('taxonomia.especimenes')->where('id', $id)->first();
    $historicaSql = DB::selectOne('SELECT '.LocalidadPublica::sql().' AS localidad FROM taxonomia.especimenes e WHERE e.id = ?', [$id]);
    expect(LocalidadPublica::desdeFila($filaHistorica))->toBe('Localidad pública corregida')
        ->and($historicaSql->localidad)->toBe('Localidad pública corregida')
        ->and($filaHistorica->localidad_verbatim)->toBe('Texto fuente sin modificar');
    $sinDesglose = (object) ['localidad' => 'Texto original pendiente', 'localidad_desglosada' => true];
    expect(LocalidadPublica::desdeFila($sinDesglose))->toBe('Texto original pendiente');
});

test('el desglose no inventa una reserva y deja una fuente excesiva para revisión', function (): void {
    expect(DesgloseLocalidad::desde(''))->toBe(['localidad' => null, 'localidad2' => null, 'localidad3' => null, 'requiere_revision' => false]);
    $desglose = DesgloseLocalidad::desde('Napo, Archidona, Vía a Cotundo', territorios: ['archidona' => 'Archidona'], contexto: ['Napo']);
    expect($desglose)->toBe(['localidad' => null, 'localidad2' => 'Archidona', 'localidad3' => 'Vía a Cotundo', 'requiere_revision' => false]);
    $largo = DesgloseLocalidad::desde('Archidona, '.str_repeat('Sendero ', 90), territorios: ['archidona' => 'Archidona']);
    expect($largo)->toBe(['localidad' => null, 'localidad2' => null, 'localidad3' => null, 'requiere_revision' => true]);
});

test('los catálogos usan equivalencias documentadas y rechazan entidades pendientes de identificar', function (): void {
    expect(NormalizadorNombreCatalogo::desde('cargo', '  Consultora   Ambiental '))->toBe('Consultor ambiental')
        ->and(NormalizadorNombreCatalogo::desde('institucion', 'EcoSambito C. Ltda'))->toBe('Consultora Ambiental Ecosambito Compañía Limitada');
    expect(fn () => NormalizadorNombreCatalogo::desde('institucion', 'Consultora independiente'))->toThrow(ValidationException::class);
});
