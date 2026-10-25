<?php

declare(strict_types=1);

use App\Livewire\Administracion\ConfiguracionFormatoIpt;
use App\Models\User;
use App\Support\ConfiguracionIpt;
use App\Support\ExportacionCatalogoExcel;
use App\Support\PerfilIptGbif;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Importers\ConfiguracionPlantillaCatalogo;
use Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\GestionRegistrosTaxonomicos\EspecimenIndex;

uses(Tests\DatabaseFeatureTestCase::class);

test('sólo administración modifica el perfil IPT y protege cabeceras obligatorias códigos y concurrencia', function (): void {
    Livewire::test(ConfiguracionFormatoIpt::class)->assertForbidden();
    Livewire::actingAs(User::factory()->curador()->create())->test(ConfiguracionFormatoIpt::class)->assertForbidden();
    $this->actingAs(User::factory()->administrador()->create());
    $config = app(ConfiguracionIpt::class); $antes = $config->leer(); $huella = ConfiguracionPlantillaCatalogo::huella($antes);
    $def = $antes; $def['columnas'] = array_values(array_filter($def['columnas'], fn ($c) => $c['campo'] !== 'habitat'));
    $def['columnas'][0]['ancho'] = 35;
    $config->guardar($def, $huella);
    expect($config->leer())->toEqual($def);
    expect(fn () => $config->guardar($antes, $huella))->toThrow(DomainException::class, 'Otro administrador');
    $invalida = $def; $invalida['columnas'][0]['formato'] = 'entero';
    expect(fn () => $config->validar($invalida))->toThrow(DomainException::class, 'identificadores');
    $invalida = $def; $invalida['columnas'] = array_values(array_filter($invalida['columnas'], fn ($c) => $c['campo'] !== 'eventDate'));
    expect(fn () => $config->validar($invalida))->toThrow(DomainException::class, 'eventDate');
});

test('el Excel curatorial mantiene filtros y no se limita a la página visible', function (): void {
    $this->actingAs(User::factory()->administrador()->create()); $prefijo = 'IPT-'.Str::uuid();
    $ids = [];
    for ($i = 0; $i < 27; $i++) {
        $id = (string)Str::uuid(); $ids[] = $id;
        DB::table('taxonomia.especimenes')->insert(['id' => $id, 'codigo_catalogo' => $prefijo.'-'.$i, 'colector' => $prefijo, 'estado' => 'disponible']);
    }
    DB::table('taxonomia.especimenes')->insert(['id' => (string)Str::uuid(), 'codigo_catalogo' => 'fuera-'.$prefijo, 'estado' => 'disponible']);
    $componente = Livewire::test(EspecimenIndex::class)->set('q', $prefijo)->set('perPage', 25);
    $componente->call('exportarExcel', 'ipt')->assertFileDownloaded('catalogo-ipt-gbif.xlsx');
    $revision = [];
    $filas = iterator_to_array(app(ExportacionCatalogoExcel::class)->filas($ids, PerfilIptGbif::COLUMNAS, false, true, PerfilIptGbif::COLUMNAS, $revision));
    expect($filas)->toHaveCount(27)->and($revision['scientificName'])->toBe(27)->and($revision['eventDate'])->toBe(27);
});

test('el perfil configurable no permite extraer campos privados mediante otro nombre de columna', function (): void {
    $this->actingAs(User::factory()->administrador()->create()); $id = (string)Str::uuid();
    $filo = (string)Str::uuid(); $taxon = (string)Str::uuid();
    DB::table('taxonomia.taxones')->insert([
        ['id' => $filo, 'padre_id' => null, 'nombre_cientifico' => 'IPTPhylum-'.$id, 'rango' => 'phylum'],
        ['id' => $taxon, 'padre_id' => $filo, 'nombre_cientifico' => 'IPTSpecies-'.$id, 'rango' => 'especie'],
    ]);
    DB::table('taxonomia.especimenes')->insert(['id' => $id, 'taxon_id' => $taxon, 'codigo_catalogo' => 'IPT-'.$id, 'occurrence_id' => '00045', 'colector' => 'Colector reservado', 'research_permit' => 'Permiso reservado', 'estado' => 'disponible']);
    DB::table('divulgacion.especimenes_divulgables')->insert(['id' => (string)Str::uuid(), 'especimen_id' => $id, 'publicado' => true, 'occurrence_id_visible' => true, 'recorded_by_visible' => false]);
    // La publicación se calcula en PostgreSQL: sin filo confirmado el trigger
    // excluye el registro antes de que se evalúen los permisos de sus campos.
    expect(DB::table('divulgacion.especimenes_divulgables')->where('especimen_id', $id)->value('publicado'))->toBeTrue();
    $revision = [];
    $filas = iterator_to_array(app(ExportacionCatalogoExcel::class)->filas([$id], ['researchPermit', 'recordedBy'], true, true, ['occurrenceID', 'scientificName'], $revision));
    expect($filas)->toBe([['', '']]);
    $this->getJson(route('portal.descarga', ['exportacion' => 'ipt', 'fc' => '00045']))->assertOk();
    auth()->logout();
    $this->getJson(route('portal.descarga', ['exportacion' => 'ipt', 'fc' => '00045']))->assertUnauthorized();
});
