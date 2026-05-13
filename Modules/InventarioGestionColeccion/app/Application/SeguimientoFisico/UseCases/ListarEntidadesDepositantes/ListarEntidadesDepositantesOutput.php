<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarEntidadesDepositantes;

final readonly class ListarEntidadesDepositantesItemOutput
{
    public function __construct(
        public string $id,
        public string $nombre,
        public string $tipo,
        public string $contacto,
    ) {}
}

final readonly class ListarEntidadesDepositantesOutput
{
    /** @param ListarEntidadesDepositantesItemOutput[] $items */
    public function __construct(public array $items) {}
}
