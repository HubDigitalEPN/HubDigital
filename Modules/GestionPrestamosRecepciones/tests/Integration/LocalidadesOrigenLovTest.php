<?php

declare(strict_types=1);

use App\Support\CatalogoLocalidadesEcuador;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Investigador\RegistroSolicitudDeposito;
use Tests\DatabaseFeatureTestCase;

uses(DatabaseFeatureTestCase::class);

test('el popup pagina localidades activas de la provincia sin duplicar nombres ni ampliar comodines', function (): void {
    $prefijo = 'QA-LOV-'.Str::uuid();
    $filas = [];
    for ($i = 0; $i < 45; $i++) {
        $nombre = $prefijo.' Sitio '.sprintf('%02d', $i);
        $filas[] = [
            'codigo' => $prefijo.'-'.$i, 'provincia_codigo' => '17', 'nombre' => $nombre,
            'canton' => 'Cantón sintético', 'parroquia' => 'Parroquia sintética',
            'busqueda' => CatalogoLocalidadesEcuador::normalizar($nombre.' Cantón sintético Parroquia sintética'),
            'fuente' => 'QA', 'activo' => true, 'created_at' => now(), 'updated_at' => now(),
        ];
    }
    $filas[] = [...$filas[0], 'codigo' => $prefijo.'-duplicado'];
    $filas[] = [...$filas[0], 'codigo' => $prefijo.'-inactivo', 'nombre' => $prefijo.' Inactivo', 'activo' => false];
    $filas[] = [...$filas[0], 'codigo' => $prefijo.'-otra', 'provincia_codigo' => '16'];
    DB::table(CatalogoLocalidadesEcuador::TABLA)->insert($filas);

    $primera = CatalogoLocalidadesEcuador::paginaLov('Pichincha', $prefijo.' canton sintetico', 0);
    $segunda = CatalogoLocalidadesEcuador::paginaLov('Pichincha', $prefijo.' canton sintetico', 1);
    $ultima = CatalogoLocalidadesEcuador::paginaLov('Pichincha', $prefijo.' canton sintetico', 2);
    $opciones = [...$primera['opciones'], ...$segunda['opciones'], ...$ultima['opciones']];

    expect($primera['opciones'])->toHaveCount(20)->and($primera['hayMas'])->toBeTrue()
        ->and($segunda['opciones'])->toHaveCount(20)->and($segunda['hayMas'])->toBeTrue()
        ->and($ultima['opciones'])->toHaveCount(5)->and($ultima['hayMas'])->toBeFalse()
        ->and(array_unique(array_column($opciones, 'etiqueta')))->toHaveCount(45)
        ->and(array_column($opciones, 'valor'))->not->toContain($prefijo.'-inactivo', $prefijo.'-otra')
        ->and(CatalogoLocalidadesEcuador::paginaLov('Pichincha', $prefijo.'%')['opciones'])->toBe([])
        ->and(CatalogoLocalidadesEcuador::paginaLov('Provincia ajena', $prefijo)['opciones'])->toBe([]);
});

test('cambiar de provincia retira la localidad y rechaza una selección de otra provincia o inactiva', function (): void {
    $prefijo = 'QA-LOV-'.Str::uuid();
    foreach (['pichincha' => ['17', true], 'pastaza' => ['16', true], 'inactiva' => ['16', false]] as $sufijo => [$provincia, $activo]) {
        DB::table(CatalogoLocalidadesEcuador::TABLA)->insert([
            'codigo' => $prefijo.'-'.$sufijo, 'provincia_codigo' => $provincia,
            'nombre' => $prefijo.' '.$sufijo, 'canton' => '', 'parroquia' => '',
            'busqueda' => CatalogoLocalidadesEcuador::normalizar($prefijo.' '.$sufijo),
            'fuente' => 'QA', 'activo' => $activo, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }
    $formulario = new RegistroSolicitudDeposito;
    $formulario->seleccionarProvincia('Pichincha');
    $formulario->seleccionarLocalidadOrigen($prefijo.'-pichincha');
    expect($formulario->localidadOrigenCodigo)->toBe($prefijo.'-pichincha');
    $formulario->seleccionarProvincia('Pastaza');
    expect($formulario->localidadOrigenCodigo)->toBe('');
    $formulario->seleccionarLocalidadOrigen($prefijo.'-pichincha');
    expect($formulario->localidadOrigenCodigo)->toBe('');
    $formulario->seleccionarLocalidadOrigen($prefijo.'-inactiva');
    expect($formulario->localidadOrigenCodigo)->toBe('');
    $formulario->seleccionarLocalidadOrigen($prefijo.'-pastaza');
    expect($formulario->localidadOrigenCodigo)->toBe($prefijo.'-pastaza');
});
