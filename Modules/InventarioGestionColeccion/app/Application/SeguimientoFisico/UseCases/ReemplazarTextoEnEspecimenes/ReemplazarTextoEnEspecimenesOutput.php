<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ReemplazarTextoEnEspecimenes;

final readonly class ReemplazarTextoEnEspecimenesOutput
{
    /**
     * @param  int  $cambiados  Filas donde el texto aparecía y se sustituyó.
     * @param  int  $sinCoincidencia  Filas de la selección donde no aparecía.
     * @param  list<array{codigoCatalogo: string, previo: ?string, nuevo: ?string}>  $muestra
     */
    public function __construct(
        public int $cambiados,
        public int $sinCoincidencia,
        public array $muestra = [],
        public ?string $edicionId = null,
    ) {}
}
