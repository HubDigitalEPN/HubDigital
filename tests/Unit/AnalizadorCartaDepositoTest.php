<?php

declare(strict_types=1);

use Modules\GestionPrestamosRecepciones\Domain\Services\AnalizadorCartaDeposito;

it('identifica cuatro propósitos documentales y exige una declaración además del título', function (string $nombre, string $tipo, string $declaracion): void {
    $analizador = new AnalizadorCartaDeposito;
    expect($analizador->tipoEsperado($nombre))->toBe($tipo);
    $texto = "{$nombre}\nTitular: Ana Pérez\nProyecto: Inventario del río\nMaterial biologico: especimenes para la coleccion. {$declaracion}";
    expect($analizador->analizar($texto, $tipo, ['titular' => 'ANA PEREZ', 'proyecto' => 'Inventario del río'])['compatible'])->toBeTrue()
        ->and($analizador->analizar("{$nombre}. Factura por servicios comerciales.", $tipo)['tipo_compatible'])->toBeFalse();
})->with([
    ['Documento de explicación de motivos y/o carta de justificación (institucional o personal)', 'carta_justificacion', 'No dispongo de permisos y explico su ausencia.'],
    ['Documento de procedencia de los especimenes', 'carta_procedencia', 'Los especimenes provienen de recolecciones en el proyecto.'],
    ['Carta de cesión de derechos / origen lícito', 'carta_cesion', 'Cedo los derechos del material a la coleccion.'],
    ['Carta de delegación / justificación de tercero', 'carta_delegacion', 'Delego a Luis la entrega y el tramite de deposito.'],
]);

it('rechaza una carta válida de otro titular o proyecto aunque el archivo tenga el nombre esperado', function (): void {
    $analizador = new AnalizadorCartaDeposito;
    $texto = "Carta de justificacion\nTitular: Camilo Ejemplo\nProyecto: Proyecto ajeno\nLos especimenes son para deposito. No dispongo de permisos.";
    $resultado = $analizador->analizar($texto, 'carta_justificacion', ['titular' => 'Ana Pérez', 'proyecto' => 'Inventario del río']);
    expect($resultado['tipo_compatible'])->toBeTrue()->and($resultado['compatible'])->toBeFalse()
        ->and($resultado['discrepancias'])->toHaveCount(2)
        ->and($resultado['discrepancias'][0])->toContain('titular')->and($resultado['discrepancias'][1])->toContain('proyecto');
});

it('no considera suficiente una palabra clave ni una carta sin identidad legible', function (): void {
    $analizador = new AnalizadorCartaDeposito;
    expect($analizador->analizar('Carta de justificación. Depósito de especímenes.', 'carta_justificacion')['compatible'])->toBeFalse();
    $resultado = $analizador->analizar('Carta de justificacion: especimenes para deposito. No dispongo de permisos.', 'carta_justificacion', ['titular' => 'Ana Pérez']);
    expect($resultado['compatible'])->toBeFalse()->and($resultado['discrepancias'][0])->toContain('No se puede identificar');
});
