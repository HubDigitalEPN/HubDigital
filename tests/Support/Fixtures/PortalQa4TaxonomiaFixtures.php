<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Modules\CatalogoPublico\Domain\ValueObjects\FiltrosBusqueda;
use Modules\CatalogoPublico\Infrastructure\Persistence\Eloquent\Repositories\EloquentProveedorEspecimenesParaArbol;
use Modules\CatalogoPublico\Presentation\Http\Controllers\PortalCatalogo;
use Modules\CatalogoPublico\Presentation\Http\Controllers\PortalEstadisticas;

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
