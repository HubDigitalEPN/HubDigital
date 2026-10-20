<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Especimen;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\EspecimenRepositoryInterface;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Importers\ActualizacionCatalogo;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Importers\LectorActualizacionCatalogo;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Jobs\ProcesarActualizacionCatalogo;
use Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\GestionRegistrosTaxonomicos\ActualizarCatalogoIndex;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

uses(Tests\DatabaseFeatureTestCase::class);

function matrizCatalogoPrueba(array $cabeceras, array $filas): string
{
    $book = new Spreadsheet;
    $book->getActiveSheet()->fromArray([$cabeceras, ...$filas], null, 'A1');
    $ruta = tempnam(sys_get_temp_dir(), 'matriz_catalogo_').'.xlsx';
    (new Xlsx($book))->save($ruta);
    $book->disconnectWorksheets();
    $GLOBALS['matrices_catalogo_temporales'][] = $ruta;
    return $ruta;
}

function fichaVaciaMatriz(string $codigo, ?string $oldCode = null): string
{
    $repo = app(EspecimenRepositoryInterface::class);
    $id = $repo->nextIdentity();
    $repo->guardar(Especimen::crear(id: $id, codigoCatalogo: $codigo, taxonId: null, localidad: '', fechaColecta: '', colector: '', occurrenceId: $codigo, oldCode: $oldCode));
    return (string)$id;
}

function cargaMatrizPreparada(string $ruta, User $curador, string $modo = 'completar'): string
{
    $plan = app(ActualizacionCatalogo::class)->analizar($ruta, 'xlsx', ',', $modo);
    $id = (string)\Illuminate\Support\Str::uuid();
    DB::table('taxonomia.importaciones_catalogo')->insert(['id' => $id, 'archivo_nombre' => 'practica.xlsx', 'fuente_sha256' => hash_file('sha256', $ruta), 'modo' => $modo, 'archivo_ruta' => 'catalogo/prueba.xlsx', 'extension' => 'xlsx', 'delimitador' => ',', 'creado_por_id' => $curador->id, 'estado' => 'en_cola', 'plan_sha256' => $plan['huella'], 'resumen' => json_encode($plan + ['modo' => $modo]), 'created_at' => now(), 'updated_at' => now()]);
    return $id;
}

afterEach(function (): void {
    foreach ($GLOBALS['matrices_catalogo_temporales'] ?? [] as $ruta) if (is_file($ruta)) unlink($ruta);
    $GLOBALS['matrices_catalogo_temporales'] = [];
});

test('las columnas repetidas y sus alias conservan valores no vacíos y todas sus posiciones originales', function (): void {
    $ruta = matrizCatalogoPrueba(['occurrenceID','occurrenceID','recordNumber','recordNumber','individualCount','invidualCount','caste','typECaste','notesGeneral','taxonomicNotes'], [['MEPN:INV:900001','','CAM-01','','','2','worker','','Nota general','Identificación por revisar']]);
    $filas = iterator_to_array(app(LectorActualizacionCatalogo::class)->filas($ruta, 'xlsx'));
    expect($filas)->toHaveCount(1)
        ->and($filas[2]['datos'])->toMatchArray(['occurrence_id' => 'MEPN:INV:900001', 'record_number' => 'CAM-01', 'individual_count' => '2', 'caste' => 'worker', 'notes_general' => 'Nota general', 'taxonomic_notes' => 'Identificación por revisar'])
        ->and(array_column($filas[2]['original'], 'cabecera'))->toContain('occurrenceID', 'recordNumber');
});

test('las columnas repetidas contradictorias bloquean la carga con fila y campo sin escribir datos', function (): void {
    $ruta = matrizCatalogoPrueba(['occurrenceID','catalogNumber','catalogNumber'], [['MEPN:INV:900002','CAT-01','CAT-02']]);
    $antes = DB::table('taxonomia.especimenes')->count();
    expect(fn () => app(ActualizacionCatalogo::class)->analizar($ruta, 'xlsx'))->toThrow(DomainException::class, 'Fila 2: las columnas equivalentes «catalogNumber»');
    expect(DB::table('taxonomia.especimenes')->count())->toBe($antes);
});

test('el Excel usa la primera hoja, convierte fechas con estilo y omite las fórmulas de plantilla vacía', function (): void {
    $book = new Spreadsheet;
    $sheet = $book->getActiveSheet();
    $sheet->fromArray([['occurrenceID','genus','specificEpithet','scientificName','eventDate'],['MEPN:INV:900003','Camponotus','atriceps','=B2&"_"&C2',Date::PHPToExcel(new DateTimeImmutable('2013-08-01'))],['','','','=B3&"_"&C3','']]);
    $sheet->getStyle('E2')->getNumberFormat()->setFormatCode('yyyy-mm-dd');
    $book->createSheet()->setTitle('Guía')->setCellValue('A1', 'Estas son instrucciones, no registros');
    $book->setActiveSheetIndex(1);
    $ruta = tempnam(sys_get_temp_dir(), 'matriz_fechas_').'.xlsx';
    $GLOBALS['matrices_catalogo_temporales'][] = $ruta;
    (new Xlsx($book))->save($ruta);
    $book->disconnectWorksheets();
    $filas = iterator_to_array(app(LectorActualizacionCatalogo::class)->filas($ruta, 'xlsx'));
    expect($filas)->toHaveCount(1)->and($filas[2]['datos']['event_date'])->toBe('2013-08-01')->and($filas[2]['datos']['scientific_name'])->toBe('Camponotus_atriceps');
});

test('validar el catálogo no crea taxones muestras especímenes ni historial de importación', function (): void {
    $ruta = matrizCatalogoPrueba(['occurrenceID','kingdom','phylum','class','order','family','genus','specificEpithet','notesGeneral'], [['MEPN:INV:900004','Animalia','Arthropoda','Insecta','Hymenoptera','Formicidae','Practicamyrma','exemplaris','Nota del original']]);
    $tablas = ['taxonomia.taxones','taxonomia.muestras_colecta','taxonomia.especimenes','taxonomia.importaciones_catalogo','taxonomia.importacion_filas_catalogo'];
    $antes = array_map(fn ($t) => DB::table($t)->count(), $tablas);
    $resumen = app(ActualizacionCatalogo::class)->analizar($ruta, 'xlsx');
    expect($resumen['nuevos'])->toBe(1)->and(array_map(fn ($t) => DB::table($t)->count(), $tablas))->toBe($antes);
});

test('se completan fichas existentes con prefijos equivalentes conservando uuid códigos estado y divulgación', function (): void {
    $id = fichaVaciaMatriz('MEPN-INV-900005', '1');
    $curador = User::factory()->curador()->create(['must_change_password' => false]);
    $ruta = matrizCatalogoPrueba(['occurrenceID','occurrenceID','recordNumber','individualCount','caste','typECaste','kingdom','phylum','class','order','suborder','family','subfamily','genus','specificEpithet','eventDate','recordedBy','country','stateProvince','localityName','notesGeneral','taxonomicNotes','columnaNueva'], [['MEPN:INV:900005','','CAM-01',2,'worker','','Animalia','Arthropoda','Insecta','Hymenoptera','Apocrita','Formicidae','Formicinae','Camponotus','atriceps','2013-08-01','Colector de práctica','Ecuador','Orellana','Yasuní','Nota general del curador','Nota taxonómica del curador','Contenido adicional']]);
    $total = DB::table('taxonomia.especimenes')->count();
    $publicados = DB::table('divulgacion.especimenes_divulgables')->count();
    $batch = cargaMatrizPreparada($ruta, $curador);
    $resultado = app(ActualizacionCatalogo::class)->aplicar($batch, $ruta);
    $row = DB::table('taxonomia.especimenes')->where('id', $id)->first();
    expect($resultado['actualizados'])->toBe(1)->and($resultado['nuevos'])->toBe(0)
        ->and($row->occurrence_id)->toBe('MEPN-INV-900005')->and($row->codigo_catalogo)->toBe('MEPN-INV-900005')
        ->and($row->record_number)->toBe('CAM-01')->and($row->individual_count)->toBe(2)->and($row->caste)->toBe('worker')
        ->and($row->fecha_colecta)->toBe('2013-08-01')->and($row->notes_general)->toBe('Nota general del curador')->and($row->taxonomic_notes)->toBe('Nota taxonómica del curador')
        ->and($row->old_code)->toBeNull()->and($row->estado)->toBe('disponible')
        ->and(DB::table('taxonomia.especimenes')->count())->toBe($total)->and(DB::table('divulgacion.especimenes_divulgables')->count())->toBe($publicados);
    $audit = DB::table('taxonomia.importacion_filas_catalogo')->where('importacion_id', $batch)->first();
    expect(array_column(json_decode($audit->original, true), 'valor'))->toContain('Contenido adicional')
        ->and(json_decode($audit->anterior, true)['taxon_id'])->toBeNull()->and(json_decode($audit->posterior, true)['taxon_id'])->toBe($row->taxon_id);
    app(ActualizacionCatalogo::class)->aplicar($batch, $ruta);
    expect(DB::table('taxonomia.importacion_filas_catalogo')->where('importacion_id', $batch)->count())->toBe(1)->and(DB::table('taxonomia.especimenes')->count())->toBe($total);
});

test('un código provisional se conserva con su nota sin crear una especie científica a partir del código', function (): void {
    $curador = User::factory()->curador()->create(['must_change_password' => false]);
    $ruta = matrizCatalogoPrueba(['occurrenceID','kingdom','phylum','class','order','family','genus','specificEpithet','scientificName','taxonomicNotes'], [['MEPN:INV:900006','Animalia','Arthropoda','Insecta','Hymenoptera','Formicidae','Azteca','PRACTICA01','Azteca_PRACTICA01','Nota original']]);
    $batch = cargaMatrizPreparada($ruta, $curador);
    app(ActualizacionCatalogo::class)->aplicar($batch, $ruta);
    $row = DB::table('taxonomia.especimenes')->where('occurrence_id', 'MEPN-INV-900006')->first();
    $taxon = DB::table('taxonomia.taxones')->where('id', $row->taxon_id)->first();
    expect($taxon->rango)->toBe('genero')->and($taxon->nombre_cientifico)->toBe('Azteca')
        ->and($row->taxon_verbatim)->toBe('Azteca_PRACTICA01')->and($row->taxonomic_notes)->toContain('Nota original', 'Identificación provisional original')
        ->and(DB::table('taxonomia.taxones')->where('nombre_cientifico', 'Azteca PRACTICA01')->exists())->toBeFalse();
});

test('una ficha modificada después de la vista previa conserva los cambios del curador y cancela el lote completo', function (): void {
    $id = fichaVaciaMatriz('MEPN-INV-900007');
    $curador = User::factory()->curador()->create(['must_change_password' => false]);
    $ruta = matrizCatalogoPrueba(['occurrenceID','notesGeneral'], [['MEPN:INV:900007','Nota fuente'], ['MEPN:INV:900008','Otra fila']]);
    $batch = cargaMatrizPreparada($ruta, $curador);
    DB::table('taxonomia.especimenes')->where('id', $id)->update(['colector' => 'Revisión concurrente', 'updated_at' => now()->addMinute()]);
    $antes = DB::table('taxonomia.especimenes')->count();
    expect(fn () => app(ActualizacionCatalogo::class)->aplicar($batch, $ruta))->toThrow(DomainException::class, 'El catálogo cambió');
    expect(DB::table('taxonomia.especimenes')->count())->toBe($antes)->and(DB::table('taxonomia.especimenes')->where('id', $id)->value('colector'))->toBe('Revisión concurrente')
        ->and(DB::table('taxonomia.importacion_filas_catalogo')->where('importacion_id', $batch)->count())->toBe(0);
});

test('una identidad duplicada entre fichas o entre filas se informa sin elegir arbitrariamente un espécimen', function (): void {
    fichaVaciaMatriz('MEPN-INV-900009');
    fichaVaciaMatriz('MEPN:INV:900009');
    $ruta = matrizCatalogoPrueba(['occurrenceID','notesGeneral'], [['MEPN:INV:900009','Revisar']]);
    expect(fn () => app(ActualizacionCatalogo::class)->analizar($ruta, 'xlsx'))->toThrow(DomainException::class, 'coincide con varias fichas');
    $repetida = matrizCatalogoPrueba(['occurrenceID'], [['MEPN:INV:900010'], ['MEPN-INV-900010']]);
    expect(fn () => app(ActualizacionCatalogo::class)->analizar($repetida, 'xlsx'))->toThrow(DomainException::class, 'ya aparece en la fila 2');
});

test('la pantalla exige curaduría y una vista previa vigente antes de archivar y encolar la importación', function (): void {
    Bus::fake([ProcesarActualizacionCatalogo::class]);
    configurarR2FalsoParaPruebas();
    Livewire::test(ActualizarCatalogoIndex::class)->assertForbidden();
    Livewire::actingAs(User::factory()->depositante()->create())->test(ActualizarCatalogoIndex::class)->assertForbidden();
    $curador = User::factory()->curador()->create(['must_change_password' => false]);
    $ruta = matrizCatalogoPrueba(['occurrenceID','notesGeneral','taxonomicNotes'], [['MEPN:INV:900011','General de práctica','Taxonomía de práctica']]);
    $panel = Livewire::actingAs($curador)->test(ActualizarCatalogoIndex::class)->set('archivo', UploadedFile::fake()->createWithContent('practica.xlsx', file_get_contents($ruta)))
        ->call('importar')->assertSet('errorMessage', 'Valida primero este archivo y revisa el resumen.')
        ->call('validar')->assertSet('resultado.nuevos', 1)->call('importar')->assertHasNoErrors();
    $id = $panel->get('importacionId');
    expect($id)->not->toBeNull()->and(DB::table('taxonomia.importaciones_catalogo')->where('id', $id)->value('estado'))->toBe('en_cola');
    Bus::assertDispatched(ProcesarActualizacionCatalogo::class);
    app()->call([new ProcesarActualizacionCatalogo($id), 'handle']);
    $panel->call('consultar')->assertSee('Carga completada:');
    $n = DB::table('taxonomia.especimenes')->where('occurrence_id', 'MEPN-INV-900011')->count();
    $panel->call('importar')->assertSee('Este archivo ya fue importado');
    expect(DB::table('taxonomia.especimenes')->where('occurrence_id', 'MEPN-INV-900011')->count())->toBe($n);
});

test('completar conserva datos existentes y actualizar sólo sustituye valores presentes en la matriz', function (): void {
    $id = fichaVaciaMatriz('MEPN-INV-900012');
    DB::table('taxonomia.especimenes')->where('id', $id)->update(['colector' => 'Colector anterior', 'habitat' => 'Hábitat conservado']);
    $ruta = matrizCatalogoPrueba(['occurrenceID','recordedBy','habitat'], [['MEPN:INV:900012','Colector actualizado','']]);
    $importador = app(ActualizacionCatalogo::class);
    expect($importador->analizar($ruta, 'xlsx')['sin_cambios'])->toBe(1)
        ->and($importador->analizar($ruta, 'xlsx', ',', 'actualizar')['actualizados'])->toBe(1);
    $curador = User::factory()->curador()->create(['must_change_password' => false]);
    $batch = cargaMatrizPreparada($ruta, $curador, 'actualizar');
    $importador->aplicar($batch, $ruta);
    expect(DB::table('taxonomia.especimenes')->where('id', $id)->value('colector'))->toBe('Colector actualizado')
        ->and(DB::table('taxonomia.especimenes')->where('id', $id)->value('habitat'))->toBe('Hábitat conservado');
});

test('una falla después de la primera escritura revierte especímenes taxones e historial de todas las filas', function (): void {
    $curador = User::factory()->curador()->create(['must_change_password' => false]);
    $ruta = matrizCatalogoPrueba(['occurrenceID','genus'], [['MEPN:INV:900013','Primamyrmaqa'], ['MEPN:INV:900014','Secundamyrmaqa']]);
    $batch = cargaMatrizPreparada($ruta, $curador);
    $puerto = \Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\TaxonRepositoryInterface::class;
    $real = app($puerto);
    $fake = Mockery::mock($puerto);
    $fake->shouldReceive('buscarTodos')->andReturn($real->buscarTodos());
    $fake->shouldReceive('nextIdentity')->andReturnUsing(fn () => $real->nextIdentity());
    $guardados = 0;
    $fake->shouldReceive('guardar')->andReturnUsing(function ($taxon) use (&$guardados, $real): void {
        if (++$guardados === 2) throw new DomainException('Fallo de persistencia simulado después de la primera fila.');
        $real->guardar($taxon);
    });
    app()->instance($puerto, $fake);
    $antes = DB::table('taxonomia.especimenes')->count();
    expect(fn () => app(ActualizacionCatalogo::class)->aplicar($batch, $ruta))->toThrow(DomainException::class, 'Fallo de persistencia simulado');
    expect($guardados)->toBe(2)->and(DB::table('taxonomia.especimenes')->count())->toBe($antes)
        ->and(DB::table('taxonomia.taxones')->whereIn('nombre_cientifico', ['Primamyrmaqa','Secundamyrmaqa'])->count())->toBe(0)
        ->and(DB::table('taxonomia.importacion_filas_catalogo')->where('importacion_id', $batch)->count())->toBe(0);
});

test('las notas generales importadas permanecen al hidratar y volver a guardar la ficha de inventario', function (): void {
    $id = fichaVaciaMatriz('MEPN-INV-900015');
    DB::table('taxonomia.especimenes')->where('id', $id)->update(['notes_general' => 'Nota original de la matriz']);
    $repo = app(EspecimenRepositoryInterface::class);
    $especimen = $repo->buscarPorId(\Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\EspecimenId::desde($id));
    expect($especimen->notesGeneral())->toBe('Nota original de la matriz');
    $repo->guardar($especimen);
    expect(DB::table('taxonomia.especimenes')->where('id', $id)->value('notes_general'))->toBe('Nota original de la matriz');
});

test('una clasificación incompatible con un género existente exige revisión antes de persistir', function (): void {
    $curador = User::factory()->curador()->create(['must_change_password' => false]);
    $base = matrizCatalogoPrueba(['occurrenceID','family','genus'], [['MEPN:INV:900016','FamiliaPracticaQa','GeneroPracticaQa']]);
    $batch = cargaMatrizPreparada($base, $curador);
    app(ActualizacionCatalogo::class)->aplicar($batch, $base);
    $contradictoria = matrizCatalogoPrueba(['occurrenceID','family','genus'], [['MEPN:INV:900017','OtraFamiliaPracticaQa','GeneroPracticaQa']]);
    expect(fn () => app(ActualizacionCatalogo::class)->analizar($contradictoria, 'xlsx'))->toThrow(DomainException::class, 'no pertenece a «OtraFamiliaPracticaQa»');
});

test('un CSV tabulado se valida desde la pantalla sin rechazar su separador', function (): void {
    $curador = User::factory()->curador()->create(['must_change_password' => false]);
    $ruta = tempnam(sys_get_temp_dir(), 'matriz_tab_').'.csv';
    $GLOBALS['matrices_catalogo_temporales'][] = $ruta;
    file_put_contents($ruta, "occurrenceID\tnotesGeneral\nMEPN:INV:900018\tNota tabulada\n");
    Livewire::actingAs($curador)->test(ActualizarCatalogoIndex::class)
        ->set('delimitador', 'tab')->set('archivo', UploadedFile::fake()->createWithContent('practica.csv', file_get_contents($ruta)))
        ->call('validar')->assertHasNoErrors()->assertSet('resultado.nuevos', 1);
});

test('una carga cuyo solicitante fue eliminado no toma prestada la sesión de otro curador', function (): void {
    $curador = User::factory()->curador()->create(['must_change_password' => false]);
    $ruta = matrizCatalogoPrueba(['occurrenceID'], [['MEPN:INV:900019']]);
    $batch = cargaMatrizPreparada($ruta, $curador);
    DB::table('taxonomia.importaciones_catalogo')->where('id', $batch)->update(['creado_por_id' => (string)\Illuminate\Support\Str::uuid()]);
    $this->actingAs($curador);
    expect(fn () => app()->call([new ProcesarActualizacionCatalogo($batch), 'handle']))->toThrow(DomainException::class, 'ya no tiene acceso de curaduría');
    expect(DB::table('taxonomia.importaciones_catalogo')->where('id', $batch)->value('estado'))->toBe('en_cola')
        ->and(DB::table('taxonomia.importacion_filas_catalogo')->where('importacion_id', $batch)->count())->toBe(0);
});

test('la matriz completa niveles intermedios ausentes conservando la identidad del género y la auditoría del cambio', function (): void {
    $curador = User::factory()->curador()->create(['must_change_password' => false]);
    $base = matrizCatalogoPrueba(['occurrenceID','family','genus'], [['MEPN:INV:900020','FamiliaNivelQa','GeneroNivelQa']]);
    app(ActualizacionCatalogo::class)->aplicar(cargaMatrizPreparada($base, $curador), $base);
    $genero = DB::table('taxonomia.taxones')->where('nombre_cientifico', 'GeneroNivelQa')->first();
    $completa = matrizCatalogoPrueba(['occurrenceID','family','subfamily','genus'], [['MEPN:INV:900021','FamiliaNivelQa','SubfamiliaNivelQa','GeneroNivelQa']]);
    $batch = cargaMatrizPreparada($completa, $curador);
    app(ActualizacionCatalogo::class)->aplicar($batch, $completa);
    $subfamilia = DB::table('taxonomia.taxones')->where('nombre_cientifico', 'SubfamiliaNivelQa')->first();
    expect(DB::table('taxonomia.taxones')->where('id', $genero->id)->value('padre_id'))->toBe($subfamilia->id)
        ->and($subfamilia->padre_id)->toBe($genero->padre_id);
    $posterior = json_decode(DB::table('taxonomia.importacion_filas_catalogo')->where('importacion_id', $batch)->value('posterior'), true);
    expect($posterior['_linajes_completados'][0])->toMatchArray(['taxon_id' => $genero->id, 'padre_anterior' => $genero->padre_id, 'padre_nuevo' => $subfamilia->id]);
});

test('occurrenceID y catalogNumber que señalan fichas distintas bloquean la carga completa', function (): void {
    fichaVaciaMatriz('MEPN-INV-900022');
    fichaVaciaMatriz('MEPN-INV-900023');
    $ruta = matrizCatalogoPrueba(['occurrenceID','catalogNumber'], [['MEPN:INV:900022','MEPN:INV:900023']]);
    expect(fn () => app(ActualizacionCatalogo::class)->analizar($ruta, 'xlsx'))->toThrow(DomainException::class, 'coincide con varias fichas');
});

test('dos alias históricos de la misma ficha no permiten actualizarla dos veces dentro de una matriz', function (): void {
    $id = fichaVaciaMatriz('MEPN-INV-900024');
    DB::table('taxonomia.especimenes')->where('id', $id)->update(['catalog_number' => 'CAT-HISTORICO-QA24']);
    $ruta = matrizCatalogoPrueba(['occurrenceID'], [['MEPN:INV:900024'], ['CAT-HISTORICO-QA24']]);
    expect(fn () => app(ActualizacionCatalogo::class)->analizar($ruta, 'xlsx'))->toThrow(DomainException::class, 'la misma ficha ya aparece en la fila 2');
});
