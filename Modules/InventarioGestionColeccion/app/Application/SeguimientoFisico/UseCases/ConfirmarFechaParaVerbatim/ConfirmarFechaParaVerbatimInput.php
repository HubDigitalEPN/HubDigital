<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ConfirmarFechaParaVerbatim;

final readonly class ConfirmarFechaParaVerbatimInput
{
    /**
     * @param  string[]|null  $especimenIds  Si se provee (no vacío), la fecha se
     *                                       aplica solo a esos especímenes; si es null, se aplica a todo el grupo verbatim.
     */
    public function __construct(
        public string $verbatim,
        public string $fechaInicio,
        public ?string $fechaFin = null,
        public ?array $especimenIds = null,
    ) {}
}
