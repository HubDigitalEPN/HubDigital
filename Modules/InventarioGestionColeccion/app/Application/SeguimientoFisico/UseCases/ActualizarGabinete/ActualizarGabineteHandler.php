<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ActualizarGabinete;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Gabinete;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\GabineteRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\RanuraGabineteRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\GabineteId;

final class ActualizarGabineteHandler
{
    public function __construct(
        private readonly GabineteRepository $gabineteRepo,
        private readonly RanuraGabineteRepository $ranuraRepo,
    ) {}

    public function handle(ActualizarGabineteInput $input): ActualizarGabineteOutput
    {
        $id = GabineteId::desde($input->gabineteId);
        $gabinete = $this->gabineteRepo->buscarPorId($id);

        if ($gabinete === null) {
            throw new \DomainException('Gabinete no encontrado.');
        }

        $ranurasConfiguradas = count($this->ranuraRepo->buscarPorGabinete($id));

        if ($input->totalRanuras < $ranurasConfiguradas) {
            throw new \DomainException(
                "El total de ranuras no puede ser menor al número de ranuras ya configuradas ({$ranurasConfiguradas})."
            );
        }

        $actualizado = Gabinete::reconstituir(
            id: $gabinete->id(),
            codigo: $gabinete->codigo(),
            nombre: $input->nombre,
            totalRanuras: $input->totalRanuras,
            activo: $gabinete->activo(),
        );

        $this->gabineteRepo->guardar($actualizado);

        return new ActualizarGabineteOutput;
    }
}
