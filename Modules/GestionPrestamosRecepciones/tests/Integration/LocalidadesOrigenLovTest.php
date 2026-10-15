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

/** Publicación real del catálogo: el trigger conserva el permiso del linaje. */
function localidadPublicaDepositoQa(string $nombre, string $provincia, array $permisos = [], array $partes = []): string
{
    $filo = qa4TaxonPublico('Arthropoda', 'phylum');
    $taxon = qa4TaxonPublico('LocalidadQa'.Str::lower(Str::random(12)), 'genero', $filo);
    $id = qa4RegistroPublico($taxon, 'QA-LOV', $provincia, $permisos);
    DB::table('taxonomia.especimenes')->where('id', $id)->update(array_replace([
        'locality_name' => $nombre, 'localidad' => $nombre,
    ], $partes));

    return $id;
}

test('el LOV de depósitos usa el texto público desglosado y excluye reservas y otras provincias', function (): void {
    $prefijo = 'QA-LOV-'.Str::uuid();
    $nombre = $prefijo.' Parque, Río, Tramo';
    $partes = ['localidad_desglosada' => true, 'localidad_area' => $prefijo.' Parque',
        'localidad2' => 'Río', 'localidad3' => 'Tramo'];
    localidadPublicaDepositoQa($prefijo.' original', 'Pichincha', [], $partes);
    localidadPublicaDepositoQa($prefijo.' otro original', 'Pichincha', [], $partes);
    localidadPublicaDepositoQa($prefijo.' reservado', 'Pichincha', ['locality_name_visible' => false]);
    localidadPublicaDepositoQa($prefijo.' provincia reservada', 'Pichincha', ['state_province_visible' => false]);
    localidadPublicaDepositoQa($prefijo.' fuera', 'Pastaza');
    $pagina = \App\Support\CatalogoLocalidadesDeposito::paginaLov('Pichincha', $prefijo);
    expect($pagina['opciones'])->toBe([['valor' => $nombre, 'etiqueta' => $nombre]])
        ->and(\App\Support\CatalogoLocalidadesDeposito::contiene('Pichincha', $nombre))->toBeTrue()
        ->and(\App\Support\CatalogoLocalidadesDeposito::paginaLov('Pichincha', $prefijo.'%')['opciones'])->toBe([]);
});

test('cambiar la provincia borra el lugar y no admite un lugar ajeno ni reservado', function (): void {
    $prefijo = 'QA-LOV-'.Str::uuid();
    localidadPublicaDepositoQa($prefijo.' Pichincha', 'Pichincha');
    localidadPublicaDepositoQa($prefijo.' Pastaza', 'Pastaza');
    localidadPublicaDepositoQa($prefijo.' reservado', 'Pastaza', ['locality_name_visible' => false]);
    $formulario = new RegistroSolicitudDeposito;
    $formulario->seleccionarProvincia('Pichincha');
    $formulario->seleccionarLocalidadOrigen($prefijo.' Pichincha');
    expect($formulario->localidad)->toBe($prefijo.' Pichincha');
    $formulario->seleccionarProvincia('Pastaza');
    expect($formulario->localidad)->toBe('')->and($formulario->localidadOrigenCodigo)->toBe('');
    foreach ([$prefijo.' Pichincha', $prefijo.' reservado'] as $nombre) {
        expect(fn () => $formulario->seleccionarLocalidadOrigen($nombre))->toThrow(\Illuminate\Validation\ValidationException::class);
        expect($formulario->localidad)->toBe('');
    }
    $formulario->seleccionarLocalidadOrigen($prefijo.' Pastaza');
    expect($formulario->localidad)->toBe($prefijo.' Pastaza');
    $formulario->seleccionarLocalidadOrigen('');
    expect($formulario->localidad)->toBe('');
});

test('el LOV público pagina sin repetir lugares y conserva nombres largos', function (): void {
    $prefijo = 'QA-LOV-'.Str::uuid();
    for ($i = 0; $i < 45; $i++) localidadPublicaDepositoQa($prefijo.' Lugar '.sprintf('%02d', $i), 'Pichincha');
    $primera = \App\Support\CatalogoLocalidadesDeposito::paginaLov('Pichincha', $prefijo, 0);
    $segunda = \App\Support\CatalogoLocalidadesDeposito::paginaLov('Pichincha', $prefijo, 1);
    expect($primera['opciones'])->toHaveCount(40)->and($primera['hayMas'])->toBeTrue()
        ->and($segunda['opciones'])->toHaveCount(5)->and($segunda['hayMas'])->toBeFalse()
        ->and(array_unique(array_column([...$primera['opciones'], ...$segunda['opciones']], 'valor')))->toHaveCount(45);
    $partes = ['localidad_desglosada' => true, 'localidad_area' => $prefijo.str_repeat('a', 120),
        'localidad2' => str_repeat('b', 120), 'localidad3' => str_repeat('c', 120)];
    localidadPublicaDepositoQa($prefijo.' largo', 'Pichincha', [], $partes);
    $nombre = implode(', ', [$partes['localidad_area'], $partes['localidad2'], $partes['localidad3']]);
    expect(\App\Support\CatalogoLocalidadesDeposito::contiene('Pichincha', $nombre))->toBeTrue();
});

test('origen admite omitir la localidad pero mantiene la provincia del catálogo obligatoria', function (): void {
    [$modelo] = borradorWizardSinIdentidadParaPruebas();
    $handler = app(\Modules\GestionPrestamosRecepciones\Application\UseCases\ActualizarOrigenSolicitudDeposito\ActualizarOrigenSolicitudDepositoHandler::class);
    $handler(new \Modules\GestionPrestamosRecepciones\Application\UseCases\ActualizarOrigenSolicitudDeposito\ActualizarOrigenSolicitudDepositoInput(
        solicitudId: $modelo->id, origenRecoleccion: 'Nacional (Ecuador)', situacionRegulatoria: 'Sin permisos del MAE', provinciaOrigen: 'Pichincha',
    ));
    expect($modelo->fresh()->localidad)->toBeNull()->and($modelo->fresh()->localidad_origen_codigo)->toBeNull();
    expect(fn () => $handler(new \Modules\GestionPrestamosRecepciones\Application\UseCases\ActualizarOrigenSolicitudDeposito\ActualizarOrigenSolicitudDepositoInput(
        solicitudId: $modelo->id, origenRecoleccion: 'Nacional (Ecuador)', situacionRegulatoria: 'Sin permisos del MAE', provinciaOrigen: 'Provincia inventada',
    )))->toThrow(DomainException::class);
    expect($modelo->fresh()->provincia_origen)->toBe('Pichincha');
});
