<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ReubicarEspecimenes;

/**
 * DTO de entrada para reubicar uno o varios especímenes a un unit tray de destino.
 *
 * `confirmar` permite avanzar pese a la advertencia taxonómica suave: en una primera
 * llamada (confirmar=false) si los especímenes quedarían fuera del orden del tray destino
 * no se persiste y se devuelve la advertencia; el actor puede reintentar con confirmar=true.
 */
final readonly class ReubicarEspecimenesInput
{
    /**
     * @param  string[]  $especimenIds
     */
    public function __construct(
        public string $destinoUnitTrayId,
        public array $especimenIds,
        public bool $confirmar = false,
    ) {}
}
