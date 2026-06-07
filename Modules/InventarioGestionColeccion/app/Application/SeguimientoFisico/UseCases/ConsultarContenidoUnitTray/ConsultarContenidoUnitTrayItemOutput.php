<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ConsultarContenidoUnitTray;

final readonly class ConsultarContenidoUnitTrayItemOutput
{
    public function __construct(
        public string $especimenId,
        public string $codigoCatalogo,
        public ?string $nombreCientifico,
    ) {}
}
