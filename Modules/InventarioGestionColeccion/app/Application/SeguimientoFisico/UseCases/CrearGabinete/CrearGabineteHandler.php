<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\CrearGabinete;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Gabinete;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\RanuraGabinete;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\GabineteRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\RanuraGabineteRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\CodigoGabinete;

final class CrearGabineteHandler
{
    public function __construct(
        private readonly GabineteRepository $gabineteRepo,
        private readonly RanuraGabineteRepository $ranuraRepo,
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

        for ($numero = 1; $numero <= $input->totalRanuras; $numero++) {
            $this->ranuraRepo->guardar(RanuraGabinete::crear(
                id: $this->ranuraRepo->nextIdentity(),
                gabineteId: $gabinete->id(),
                numeroRanura: $numero,
            ));
        }

        return new CrearGabineteOutput(
            id: (string) $gabinete->id(),
            codigo: (string) $gabinete->codigo(),
            nombre: $gabinete->nombre(),
            totalRanuras: $gabinete->totalRanuras(),
            activo: $gabinete->activo(),
        );
    }
}
