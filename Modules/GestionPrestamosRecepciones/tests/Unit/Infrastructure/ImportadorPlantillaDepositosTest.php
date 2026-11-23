<?php

declare(strict_types=1);

use Modules\GestionPrestamosRecepciones\Infrastructure\Files\ImportadorPlantillaDepositos;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

test('la carga administrativa interpreta las cabeceras, guía y colores sin incorporar los ejemplos', function (): void {
    $modulo = dirname(__DIR__, 3);
    $actual = json_decode(file_get_contents($modulo.'/database/data/matriz-depositantes-v3.json'), true, 512, JSON_THROW_ON_ERROR);
    $libro = IOFactory::load($modulo.'/resources/plantillas/matriz-depositantes-v3.xlsx');
    $ruta = tempnam(sys_get_temp_dir(), 'plantilla-depositos-');
    try {
        $hoja = $libro->getSheet(0);
        $hoja->setCellValue('A2', 'ESTE ES UN EJEMPLO');
        $hoja->setCellValue('A3', 'Código visible en el formulario de depósito.');
        $hoja->getStyle('A1')->getFill()->getStartColor()->setARGB('FFFFFF00');
        (new Xlsx($libro))->save($ruta);
        $resultado = (new ImportadorPlantillaDepositos)->leer($ruta, $actual);
        $columnas = array_column($resultado['definicion']['columnas'], null, 'campo');
        expect($resultado['visibles'])->toBe(42)
            ->and($resultado['nuevas'])->toBe(0)
            ->and($columnas['recordNumber']['requisito'])->toBe('recomendado')
            ->and($columnas['recordNumber']['descripcion'])->toBe('Código visible en el formulario de depósito.')
            ->and($columnas['researchPermit']['requisito'])->toBe('obligatorio')
            ->and(json_encode($resultado['definicion']))->not->toContain('ESTE ES UN EJEMPLO');

        $hoja->setCellValue('B1', 'recordNumber');
        (new Xlsx($libro))->save($ruta);
        expect(fn () => (new ImportadorPlantillaDepositos)->leer($ruta, $actual))
            ->toThrow(DomainException::class, 'está repetida');
    } finally {
        $libro->disconnectWorksheets();
        unlink($ruta);
    }
});
