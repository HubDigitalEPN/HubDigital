<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ExportarDarwinCoreArchive;

final readonly class ExportarDarwinCoreArchiveInput
{
    public function __construct(
        public bool $incluirNoPublicables = false,
    ) {}
}
