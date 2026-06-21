<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ActualizarRanura;

/**
 * DTO de entrada con la ranura a modificar y el estado de activación deseado.
 */
final readonly class ActualizarRanuraInput
{
    public function __construct(
        public string $ranuraId,
        public bool $activa,
    ) {}
}
