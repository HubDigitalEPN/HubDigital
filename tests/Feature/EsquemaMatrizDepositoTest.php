<?php

declare(strict_types=1);

use App\Livewire\Administracion\TablasCatalogos;
use App\Models\User;
use Livewire\Livewire;
use Modules\GestionPrestamosRecepciones\Application\Ports\EsquemaMatrizPort;
use Modules\GestionPrestamosRecepciones\Infrastructure\Files\LectorMatrizDeposito;
use Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Curador\ConfiguracionMatrizDeposito;

uses(Tests\DatabaseFeatureTestCase::class);

test('la cuadrícula de matrices cambia el orden y conserva el ejemplo de la columna de depósitos', function (): void {
    $this->actingAs(User::factory()->curador()->create());
    $esquema = app(EsquemaMatrizPort::class);
    $original = $esquema->leer();
    $grilla = Livewire::test(TablasCatalogos::class)->call('editarColumna', 'country')
        ->set('datos.orden', 1)->call('guardarColumna')->assertHasNoErrors();
    expect($esquema->leer()['columnas'][0]['campo'])->toBe('country');

    $libro = app(\Modules\GestionPrestamosRecepciones\Infrastructure\Files\PlantillaMatrizDepositante::class)->crear();
    try {
        $hoja = $libro->getSheet(0);
        $cabeceras = $hoja->rangeToArray('A1:'.$hoja->getHighestDataColumn().'1')[0];
        $posicion = array_search('recordNumber', $cabeceras, true);
        expect($cabeceras[0])->toBe($esquema->leer()['columnas'][0]['cabecera'])
            ->and($posicion)->toBeGreaterThan(0)
            ->and($hoja->getCell([$posicion + 1, 2])->getValue())->toBe('HMLX3276');
    } finally {
        $libro->disconnectWorksheets();
    }
    $grilla->call('solicitarEliminar', 'recordNumber')->call('confirmarCambio')->assertHasErrors('matriz');
    expect($esquema->leer()['columnas'])->toHaveCount(count($original['columnas']));
});

test('la plantilla corta descarga las 40 columnas oficiales y conserva colores, ejemplo, guía y leyenda', function (): void {
    $libro = app(\Modules\GestionPrestamosRecepciones\Infrastructure\Files\PlantillaMatrizDepositante::class)->crear();
    $ruta = tempnam(sys_get_temp_dir(), 'depositante-v3-');
    try {
        $hoja = $libro->getSheet(0);
        $general = array_column(\App\Support\PlantillasLaboratorio::referencia()['catalogo'], 'cabecera', 'campo');
        $campos = array_column(\App\Support\PlantillasLaboratorio::referencia()['depositantes'], 'campo');
        expect($hoja->rangeToArray('A1:AN1')[0])->toBe(array_map(static fn ($c) => $general[$c], $campos));
        expect($hoja->getHighestDataColumn())->toBe('AN')
            ->and($hoja->getCell('A1')->getValue())->toBe('recordNumber')
            ->and($hoja->getCell('AN1')->getValue())->toBe('microhabitat')
            ->and($hoja->getStyle('A1')->getFill()->getStartColor()->getARGB())->toBe('FFFFC000')
            ->and($hoja->getStyle('C1')->getFill()->getStartColor()->getARGB())->toBe('FFFFC000')
            ->and($hoja->getCell('C2')->getValue())->toBe('consulting')
            ->and((string) $hoja->getCell('A3')->getValue())->toContain('Código de campo')
            ->and((string) $hoja->getCell('H3')->getValue())->toBe(\App\Support\PlantillasLaboratorio::referencia()['depositantes'][7]['descripcion'])
            ->and($hoja->getCell('A5')->getValue())->toBe('Clave')
            ->and($hoja->getCell('A7')->getValue())->toBe('campo sugerido, caso aplique, se agradece el aporte');
        (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($libro))->save($ruta);
        $resultado = app(LectorMatrizDeposito::class)->leer($ruta);
        expect($resultado['registros'])->toHaveCount(1)->and($resultado['filas'])->toBe([2])
            ->and($resultado['registros'][0]['scientificName'])->toBe('Lycomedes hirtipes')
            ->and($resultado['registros'][0]['eventDate'])->toBe('1990-03-02');
        $normalizado = app(\Modules\GestionPrestamosRecepciones\Domain\Services\NormalizadorCamposDwC::class)->normalizar($resultado['registros'][0]);
        expect(array_filter($normalizado->cambios, fn ($c) => ! empty($c['invalido'])))->toBe([]);
    } finally {
        $libro->disconnectWorksheets(); unlink($ruta);
    }
});

test('la plantilla v2 adjunta importa una observación sin convertir las instrucciones ni la clave de colores en especímenes', function (): void {
    $resultado = app(LectorMatrizDeposito::class)->leer(base_path('tests/Fixtures/plantilla-laboratorio-invertebrados-v2.xlsx'));
    expect($resultado['registros'])->toHaveCount(1)
        ->and($resultado['filas'])->toBe([2])
        ->and($resultado['campos'])->toHaveCount(106)
        ->toContain('individualCount', 'dateIdentified', 'infraspecificEpithet', 'locality')
        ->not->toContain('invidualCount', 'dateDetermined', 'iInfraspecificEpithet', 'localityName')
        ->and($resultado['registros'][0]['scientificName'])->toBe('Lycomedes_hirtipes')
        ->and($resultado['registros'][0]['eventDate'])->toBe('1990/02/29');
    // El error de fecha del ejemplo se conserva para revisión; no se inventa otra fecha.
});

test('el Excel conserva género y epíteto vacíos de una identificación parcial y toma los permisos del expediente', function (): void {
    $libro = app(\Modules\GestionPrestamosRecepciones\Infrastructure\Files\PlantillaMatrizDepositante::class)->crear();
    $ruta = tempnam(sys_get_temp_dir(), 'depositante-expediente-');
    try {
        $hoja = $libro->getSheet(0);
        $esquema = array_column(app(EsquemaMatrizPort::class)->leer()['columnas'], 'cabecera', 'campo');
        $cabeceras = $hoja->rangeToArray('A1:'.$hoja->getHighestDataColumn().'1')[0];
        foreach (['genus', 'specificEpithet', 'researchPermit', 'transportPermit'] as $campo) {
            $i = array_search($esquema[$campo], $cabeceras, true);
            expect($i)->not->toBeFalse();
            $hoja->setCellValue([$i + 1, 2], $campo === 'researchPermit' ? 'NO USAR ESTE DATO' : null);
        }
        $hoja->setCellValue([array_search('taxonRank', $cabeceras, true) + 1, 2], 'family');
        (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($libro))->save($ruta);
        $fila = app(LectorMatrizDeposito::class)->leer($ruta, [
            'researchPermit' => 'PERMISO-RECOLECCION-QA', 'transportPermit' => 'PERMISO-MOVILIZACION-QA',
        ])['registros'][0];
        expect($fila['genus'])->toBe('')
            ->and($fila['specificEpithet'])->toBe('')
            ->and($fila['scientificName'])->not->toContain('indeterminado')
            ->and($fila['researchPermit'])->toBe('PERMISO-RECOLECCION-QA')
            ->and($fila['transportPermit'])->toBe('PERMISO-MOVILIZACION-QA');
    } finally {
        $libro->disconnectWorksheets();
        unlink($ruta);
    }
});

test('curaduría cambia la cabecera conservando el identificador protegido y la lectura de ambas plantillas', function (): void {
    $curador = User::factory()->curador()->create();
    Livewire::actingAs($curador)->test(ConfiguracionMatrizDeposito::class)
        ->call('editar', 'recordNumber')->set('columna.campo', 'codigoInvestigador')
        ->set('columna.cabecera', 'Código de campo')->call('guardar')->assertHasNoErrors();
    $columnas = app(EsquemaMatrizPort::class)->leer()['columnas'];
    expect(array_column($columnas, 'campo'))->toContain('recordNumber')->not->toContain('codigoInvestigador')
        ->and(collect($columnas)->firstWhere('campo', 'recordNumber')['cabecera'])->toBe('Código de campo');
    $libro = app(\Modules\GestionPrestamosRecepciones\Infrastructure\Files\PlantillaMatrizDepositante::class)->crear();
    $ruta = tempnam(sys_get_temp_dir(), 'depositante-cabecera-');
    try {
        expect($libro->getSheet(0)->getHighestDataColumn())->toBe('AN')
            ->and($libro->getSheet(0)->getCell('A1')->getValue())->toBe('Código de campo');
        (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($libro))->save($ruta);
        $resultado = app(LectorMatrizDeposito::class)->leer($ruta);
        expect($resultado['registros'])->toHaveCount(1)
            ->and($resultado['registros'][0]['recordNumber'])->toBe('HMLX3276');
        $anterior = app(LectorMatrizDeposito::class)->leer(base_path('tests/Fixtures/plantilla-laboratorio-invertebrados-v2.xlsx'));
        expect($anterior['registros'])->toHaveCount(1)->and($anterior['registros'][0]['recordNumber'])->toBe('HMLX3276');
    } finally {
        $libro->disconnectWorksheets(); unlink($ruta);
    }
});

test('el curador parametriza requisitos y alias que usa inmediatamente la lectura del Excel', function (): void {
    $curador = User::factory()->curador()->create();
    $panel = Livewire::actingAs($curador)->test(ConfiguracionMatrizDeposito::class)
        ->assertSee('Columnas de la matriz de depósito')
        ->call('editar', 'country')->set('columna.requisito', 'opcional')
        ->set('aliasTexto', 'país de recolección')->call('guardar')->assertHasNoErrors();
    $campo = collect(app(EsquemaMatrizPort::class)->leer()['columnas'])->firstWhere('campo', 'country');
    expect($campo['requisito'])->toBe('opcional')->and($campo['alias'])->toBe(['país de recolección']);
    $panel->call('editar')->set('columna.campo', 'campoCurador')->set('columna.cabecera', 'Dato del curador')
        ->set('columna.requisito', 'obligatorio')->call('guardar')->assertHasNoErrors();
    expect(fn () => app(LectorMatrizDeposito::class)->leer(base_path('tests/Fixtures/plantilla-laboratorio-invertebrados-v2.xlsx')))
        ->toThrow(DomainException::class, 'Dato del curador (filas 2)');
    $panel->call('editar', 'campoCurador')->set('columna.requisito', 'opcional')->call('guardar')->assertHasNoErrors();
    expect(app(LectorMatrizDeposito::class)->leer(base_path('tests/Fixtures/plantilla-laboratorio-invertebrados-v2.xlsx'))['registros'])->toHaveCount(1);
});

test('la matriz respeta columnas reordenadas, un nombre científico opcional vacío y los alias que acaba de definir el curador', function (): void {
    $curador = User::factory()->curador()->create();
    Livewire::actingAs($curador)->test(ConfiguracionMatrizDeposito::class)
        ->call('editar', 'country')->set('aliasTexto', 'país de recolección')->call('guardar')->assertHasNoErrors();
    $libro = \PhpOffice\PhpSpreadsheet\IOFactory::load(base_path('tests/Fixtures/plantilla-laboratorio-invertebrados-v2.xlsx'));
    $ruta = tempnam(sys_get_temp_dir(), 'matriz-v2-');
    try {
        $hoja = $libro->getActiveSheet();
        $hoja->setCellValue('BQ1', 'país de recolección');
        $hoja->setCellValue('AU2', '');
        $hoja->setCellValue('BD2', '');
        // Reordenar conserva juntas cabecera, observación y descripción.
        $a = $hoja->rangeToArray('BQ1:BQ3', null, false, false);
        $b = $hoja->rangeToArray('BR1:BR3', null, false, false);
        foreach (['BQ1', 'BQ2', 'BQ3', 'BR1', 'BR2', 'BR3'] as $celda) $hoja->getCell($celda)->setValue(null);
        $hoja->fromArray($b, null, 'BQ1'); $hoja->fromArray($a, null, 'BR1');
        $libro->createSheet()->setTitle('Guía de campos')->setCellValue('A1', 'Descripción de las columnas');
        $libro->setActiveSheetIndex(1);
        (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($libro))->save($ruta);
        $resultado = app(LectorMatrizDeposito::class)->leer($ruta);
        expect($resultado['registros'])->toHaveCount(1)
            ->and($resultado['registros'][0]['scientificName'])->toBe('Lycomedes hirtipes')
            ->and($resultado['registros'][0]['country'])->toBe('Colombia')
            ->and($resultado['registros'][0]['sex'])->toBe('');
    } finally {
        $libro->disconnectWorksheets(); unlink($ruta);
    }
});

test('los campos vacíos se informan juntos por mensaje y las excepciones curatoriales no vuelven obligatorio un dato opcional', function (): void {
    $esquemas = app(EsquemaMatrizPort::class);
    $definicion = $esquemas->leer();
    $columnas = array_filter($definicion['columnas'], fn ($c) => in_array($c['campo'], ['country', 'transportPermit', 'sex'], true));
    expect(fn () => \Modules\GestionPrestamosRecepciones\Domain\Services\EsquemaMatriz::validarRegistros([
        ['country' => '', 'stateProvince' => 'Orellana', 'transportPermit' => '', 'sex' => ''],
    ], [12], $columnas))->toThrow(DomainException::class, 'transportPermit (filas 12); country (filas 12)');
    \Modules\GestionPrestamosRecepciones\Domain\Services\EsquemaMatriz::validarRegistros([
        ['country' => 'Ecuador', 'stateProvince' => 'Pichincha', 'transportPermit' => '', 'sex' => ''],
    ], [12], $columnas);
});

test('solo curador y administrador pueden cambiar la matriz y los alias ambiguos conservan la definición anterior', function (): void {
    Livewire::test(ConfiguracionMatrizDeposito::class)->assertForbidden();
    Livewire::actingAs(User::factory()->depositante()->create())->test(ConfiguracionMatrizDeposito::class)->assertForbidden();
    $admin = User::factory()->administrador()->create();
    $matrices = route('admin.matrices', ['tabla' => 'matriz-depositos']);
    $this->actingAs($admin)->get(route('prestamos.curador.matriz.columnas'))->assertRedirect($matrices);
    $this->get($matrices)->assertOk()->assertSeeLivewire(\App\Livewire\Administracion\TablasCatalogos::class);
    $antes = app(EsquemaMatrizPort::class)->leer();
    Livewire::actingAs($admin)->test(ConfiguracionMatrizDeposito::class)
        ->call('editar', 'country')->set('aliasTexto', 'scientificName')->call('guardar')
        ->assertDispatched('show-toast', fn ($evento, $datos) => $datos['variant'] === 'error' && str_contains($datos['message'], 'otra columna'));
    expect(app(EsquemaMatrizPort::class)->leer())->toBe($antes);
});
