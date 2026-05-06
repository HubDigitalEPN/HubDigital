<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\CrearGabinete;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Gabinete;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\GabineteRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\CodigoGabinete;

final class CrearGabineteHandler
{
    public function __construct(
        private readonly GabineteRepository $gabineteRepo,
    ) {}

    public function handle(CrearGabineteInput $input): CrearGabineteOutput
    {
        $id = $this->gabineteRepo->nextIdentity();
        $codigo = CodigoGabinete::desde($input->codigo);

        $gabinete = Gabinete::crear(
            id: $id,
            codigo: $codigo,
            nombre: $input->nombre,
            totalRanuras: $input->totalRanuras,
        );

        $this->gabineteRepo->guardar($gabinete);

        return new CrearGabineteOutput(
            id: (string) $gabinete->id(),
            codigo: (string) $gabinete->codigo(),
            nombre: $gabinete->nombre(),
            totalRanuras: $gabinete->totalRanuras(),
            activo: $gabinete->activo(),
        );
    }
}
