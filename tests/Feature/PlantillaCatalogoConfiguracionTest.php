<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\GestionPrestamosRecepciones\Infrastructure\Storage\AlmacenamientoDepositos;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Importers\ActualizacionCatalogo;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Importers\ConfiguracionPlantillaCatalogo;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Importers\GeneradorPlantillaImportXlsx;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Importers\LectorActualizacionCatalogo;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

uses(Tests\DatabaseFeatureTestCase::class);

function archivoPlantillaCatalogoQa(string $bytes): string
{
    $ruta = tempnam(sys_get_temp_dir(), 'catalogo_config_');
    file_put_contents($ruta, $bytes);
    $GLOBALS['plantillas_catalogo_qa'][] = $ruta;
    return $ruta;
}

afterEach(function (): void {
    foreach ($GLOBALS['plantillas_catalogo_qa'] ?? [] as $ruta) if (is_file($ruta)) unlink($ruta);
    $GLOBALS['plantillas_catalogo_qa'] = [];
});

test('la descarga y su guía reúnen la matriz de laboratorio y las dos notas sin catalogNumber obsoleto', function (): void {
    $def = app(ConfiguracionPlantillaCatalogo::class)->leer();
    $fuente = json_decode(file_get_contents(base_path('Modules/GestionPrestamosRecepciones/database/data/matriz-deposito-v2.json')), true);
    expect(array_column($def['columnas'], 'campo'))->toEqualCanonicalizing([...array_column($fuente['columnas'], 'campo'), 'notesGeneral', 'taxonomicNotes']);
    expect(array_column($def['columnas'], 'cabecera'))->not->toContain('catalogNumber', 'invidualCount');
    ConfiguracionPlantillaCatalogo::validar($def);
    $ruta = archivoPlantillaCatalogoQa(GeneradorPlantillaImportXlsx::generar($def));
    expect(app(ConfiguracionPlantillaCatalogo::class)->desdeExcel($ruta))->toEqual($def);
    $libro = IOFactory::load($ruta);
    try {
        expect($libro->getSheetByName('Guía')->getHighestDataRow())->toBe(count($def['columnas']) + 1)
            ->and($libro->getSheetByName('Guía')->getCell('D2')->getValue())->toBe('obligatorio');
    } finally { $libro->disconnectWorksheets(); }
});

test('una cabecera editada y su guía se aplican en la lectura y cambian la huella del plan', function (): void {
    $servicio = app(ConfiguracionPlantillaCatalogo::class); $def = $servicio->leer();
    $ruta = archivoPlantillaCatalogoQa(GeneradorPlantillaImportXlsx::generar($def));
    $libro = IOFactory::load($ruta);
    $libro->getSheet(0)->setCellValue('A1', 'Identificador de ficha');
    $libro->getSheetByName('Guía')->setCellValue('B2', 'Identificador de ficha');
    $libro->getSheetByName('Guía')->setCellValue('C2', 'Identificador curatorial estable para actualizar la ficha.');
    (new Xlsx($libro))->save($ruta); $libro->disconnectWorksheets();
    $editada = $servicio->desdeExcel($ruta); $curador = User::factory()->curador()->create();
    $servicio->guardar($editada, ConfiguracionPlantillaCatalogo::huella($def), (string)$curador->id);
    expect($servicio->leer()['columnas'][0]['cabecera'])->toBe('Identificador de ficha')
        ->and(ConfiguracionPlantillaCatalogo::huella($servicio->leer()))->toBe(ConfiguracionPlantillaCatalogo::huella($editada));
    $libro = IOFactory::load($ruta); $libro->getSheet(0)->setCellValue('A2', 'QA-CONFIG-'.Str::uuid());
    (new Xlsx($libro))->save($ruta); $libro->disconnectWorksheets();
    $plan = app(ActualizacionCatalogo::class)->analizar($ruta, 'xlsx');
    expect($plan['filas'])->toBe(1);
    $filas = iterator_to_array(app(LectorActualizacionCatalogo::class)->filas($ruta, 'xlsx', ',', ConfiguracionPlantillaCatalogo::alias($editada)));
    expect($filas[2]['datos'])->toHaveKey('occurrence_id');
    $cambiada = $editada; $cambiada['columnas'][0]['descripcion'] .= ' Nueva revisión.';
    $servicio->guardar($cambiada, ConfiguracionPlantillaCatalogo::huella($editada), (string)$curador->id);
    expect(app(ActualizacionCatalogo::class)->analizar($ruta, 'xlsx')['huella'])->not->toBe($plan['huella']);
    expect(fn () => $servicio->guardar($def, ConfiguracionPlantillaCatalogo::huella($editada), (string)$curador->id))->toThrow(DomainException::class, 'cambió mientras');
});

test('obligaciones curatoriales se notifican por fila sin modificar los requisitos de depósitos', function (): void {
    $servicio = app(ConfiguracionPlantillaCatalogo::class); $def = $servicio->leer();
    $antes = DB::table('recepciones.configuracion_matriz')->where('clave', 'depositos')->value('definicion');
    $editada = $def;
    foreach ($editada['columnas'] as &$c) if ($c['campo'] === 'scientificName') $c['requisito'] = 'obligatorio';
    unset($c);
    $servicio->guardar($editada, ConfiguracionPlantillaCatalogo::huella($def), (string)Str::uuid());
    $ruta = archivoPlantillaCatalogoQa(GeneradorPlantillaImportXlsx::generar($editada));
    $libro = IOFactory::load($ruta); $libro->getSheet(0)->setCellValue('A2', 'QA-REQUERIDO-'.Str::uuid());
    (new Xlsx($libro))->save($ruta); $libro->disconnectWorksheets();
    expect(fn () => app(ActualizacionCatalogo::class)->analizar($ruta, 'xlsx'))->toThrow(DomainException::class, 'Fila 2: campos obligatorios sin completar: scientificName');
    expect(DB::table('recepciones.configuracion_matriz')->where('clave', 'depositos')->value('definicion'))->toBe($antes);
    $editada['columnas'][1]['cabecera'] = $editada['columnas'][0]['cabecera'];
    expect(fn () => $servicio->guardar($editada, ConfiguracionPlantillaCatalogo::huella($servicio->leer()), (string)Str::uuid()))->toThrow(DomainException::class, 'repetidos');
    expect($servicio->leer()['columnas'][1]['cabecera'])->toBe($def['columnas'][1]['cabecera']);
});

test('la pantalla curatorial informa de una guía inválida sin reemplazar la configuración', function (): void {
    $servicio = app(ConfiguracionPlantillaCatalogo::class); $antes = $servicio->leer();
    $curador = User::factory()->curador()->create(['must_change_password' => false]);
    $ruta = archivoPlantillaCatalogoQa(GeneradorPlantillaImportXlsx::generar($antes));
    $libro = IOFactory::load($ruta); $libro->getSheetByName('Guía')->setCellValue('C2', '=1+1');
    (new Xlsx($libro))->save($ruta); $libro->disconnectWorksheets();
    $archivo = \Illuminate\Http\UploadedFile::fake()->createWithContent('plantilla-modificada.xlsx', file_get_contents($ruta));
    \Livewire\Livewire::actingAs($curador)->test(\Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\GestionRegistrosTaxonomicos\ConfiguracionPlantillaCatalogoIndex::class)
        ->set('archivo', $archivo)->call('guardar')->assertHasNoErrors()->assertSee('no admiten fórmulas');
    expect($servicio->leer())->toBe($antes);
    $this->actingAs(User::factory()->depositante()->create(['must_change_password' => false]))->get(route('inventario.taxonomia.importar.plantilla.configurar'))->assertForbidden();
});

test('el historial descarga cada byte del Excel original con columnas repetidas y controla permiso e integridad', function (): void {
    config(['deposit-storage.driver' => 'local', 'deposit-storage.require_remote' => false]);
    $curador = User::factory()->curador()->create(['must_change_password' => false]);
    $id = (string)Str::uuid(); $ruta = 'catalogo/importaciones/'.$id.'/original.xlsx';
    $libro = new \PhpOffice\PhpSpreadsheet\Spreadsheet;
    $libro->getActiveSheet()->fromArray([['occurrenceID', 'occurrenceID', 'notesGeneral', 'taxonomicNotes'], ['QA-DESCARGA', '', 'Nota original', 'Revisar identificación']]);
    $temporal = archivoPlantillaCatalogoQa(''); (new Xlsx($libro))->save($temporal); $libro->disconnectWorksheets();
    $bytes = file_get_contents($temporal);
    $sha = app(AlmacenamientoDepositos::class)->guardarContenido($ruta, $bytes);
    try {
        DB::table('taxonomia.importaciones_catalogo')->insert(['id' => $id, 'archivo_nombre' => 'Matriz original.xlsx', 'fuente_sha256' => $sha, 'archivo_ruta' => $ruta, 'extension' => 'xlsx', 'delimitador' => ',', 'modo' => 'completar', 'creado_por_id' => $curador->id, 'estado' => 'completada', 'plan_sha256' => str_repeat('a', 64), 'resumen' => '{}', 'created_at' => now(), 'updated_at' => now()]);
        $url = route('inventario.taxonomia.importar.original', ['importacion' => $id]);
        $this->get($url)->assertRedirect(route('login'));
        $this->actingAs(User::factory()->depositante()->create(['must_change_password' => false]))->get($url)->assertForbidden();
        $this->actingAs($curador)->get($url)->assertOk()->assertDownload('Matriz original.xlsx')->assertContent($bytes);
        DB::table('taxonomia.importaciones_catalogo')->where('id', $id)->update(['archivo_nombre' => 'Matriz de colección.xlsx']);
        $unicode = $this->get($url)->assertOk()->assertDownload('Matriz de coleccion.xlsx')->assertContent($bytes);
        expect($unicode->headers->get('Content-Disposition'))->toContain("filename*=utf-8''Matriz%20de%20colecci%C3%B3n.xlsx");
        DB::table('taxonomia.importaciones_catalogo')->where('id', $id)->update(['fuente_sha256' => str_repeat('0', 64)]);
        $this->get($url)->assertStatus(409);
    } finally { app(AlmacenamientoDepositos::class)->eliminar($ruta); }
});
