<?php

declare(strict_types=1);

use App\Models\User;
use Livewire\Livewire;
use Modules\GestionPrestamosRecepciones\Application\Ports\EsquemaMatrizPort;
use Modules\GestionPrestamosRecepciones\Infrastructure\Files\LectorMatrizDeposito;
use Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Curador\ConfiguracionMatrizDeposito;

uses(Tests\DatabaseFeatureTestCase::class);

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
    $this->actingAs($admin)->get(route('prestamos.curador.matriz.columnas'))->assertOk();
    $antes = app(EsquemaMatrizPort::class)->leer();
    Livewire::actingAs($admin)->test(ConfiguracionMatrizDeposito::class)
        ->call('editar', 'country')->set('aliasTexto', 'scientificName')->call('guardar')
        ->assertDispatched('show-toast', fn ($evento, $datos) => $datos['variant'] === 'error' && str_contains($datos['message'], 'otra columna'));
    expect(app(EsquemaMatrizPort::class)->leer())->toBe($antes);
});
