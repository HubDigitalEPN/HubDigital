<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Modules\CatalogoPublico\Domain\ValueObjects\FiltrosBusqueda;
use Modules\CatalogoPublico\Infrastructure\Persistence\Eloquent\Repositories\EloquentProveedorEspecimenesParaArbol;
use Modules\CatalogoPublico\Application\Services\IlustracionTaxonomica;
use Modules\CatalogoPublico\Application\UseCases\ExportarRegistrosEspecimenes\ExportarRegistrosEspecimenesHandler;
use Modules\CatalogoPublico\Application\UseCases\ExportarRegistrosEspecimenes\ExportarRegistrosEspecimenesInput;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Modules\CatalogoPublico\Presentation\Http\Controllers\PortalCatalogo;
use Modules\CatalogoPublico\Presentation\Http\Controllers\PortalEstadisticas;

function cartografiaRealFixture(): array
{
    $filo = (string) Str::uuid();
    $prefijo = 'Carto'.Str::lower((string) preg_replace('/[^A-Za-z]/', '', Str::random(24)));
    $taxones = [(string) Str::uuid(), (string) Str::uuid()];
    DB::table('taxonomia.taxones')->insert(['id' => $filo, 'nombre_cientifico' => $prefijo, 'rango' => 'phylum', 'autor' => 'QA', 'anio_descripcion' => 2026]);
    foreach ($taxones as $i => $id) DB::table('taxonomia.taxones')->insert([
        'id' => $id, 'padre_id' => $filo, 'nombre_cientifico' => $prefijo.' '.($i ? 'beta' : 'alfa'), 'rango' => 'especie', 'autor' => 'QA', 'anio_descripcion' => 2026,
    ]);
    $muestra = (string) Str::uuid();
    DB::table('taxonomia.muestras_colecta')->insert(['id' => $muestra, 'sampling_protocol' => 'Red '.$prefijo]);
    $ids = $codigos = [];
    foreach ([0, 1, 2] as $i) {
        $ids[$i] = (string) Str::uuid();
        $codigos[$i] = 'QA-CARTO-'.Str::upper(Str::random(10));
        DB::table('taxonomia.especimenes')->insert([
            'id' => $ids[$i], 'codigo_catalogo' => $codigos[$i], 'occurrence_id' => $codigos[$i],
            'taxon_id' => $taxones[$i === 2 ? 1 : 0], 'muestra_id' => $muestra,
            'localidad' => $prefijo, 'state_province' => $i === 2 ? 'Napo' : 'Pichincha',
            'fecha_colecta' => '2025-01-10', 'colector' => 'QA',
            'decimal_latitude' => $i === 2 ? -2.5 : -0.25,
            'decimal_longitude' => $i === 2 ? -76.25 : -78.5,
        ]);
        DB::table('divulgacion.especimenes_divulgables')->insert(['id' => (string) Str::uuid(), 'especimen_id' => $ids[$i], 'publicado' => true]);
    }
    return compact('filo', 'prefijo', 'taxones', 'ids', 'codigos', 'muestra');
}
