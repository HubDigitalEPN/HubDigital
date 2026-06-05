<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\EnlazarEspecimenAMuestra;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\EspecimenRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\MuestraColectaRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\EspecimenId;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\MuestraColectaId;

final class EnlazarEspecimenAMuestraHandler
{
    public function __construct(
        private readonly EspecimenRepositoryInterface $especimenRepo,
        private readonly MuestraColectaRepositoryInterface $muestraRepo,
    ) {}

    public function handle(EnlazarEspecimenAMuestraInput $input): EnlazarEspecimenAMuestraOutput
    {
        $especimen = $this->especimenRepo->buscarPorId(EspecimenId::desde($input->especimenId));
        if ($especimen === null) {
            throw new \DomainException("Espécimen no encontrado: '{$input->especimenId}'.");
        }

        $muestraId = MuestraColectaId::desde($input->muestraId);
        $muestra = $this->muestraRepo->buscarPorId($muestraId);
        if ($muestra === null) {
            throw new \DomainException("Muestra de colecta no encontrada: '{$input->muestraId}'.");
        }

        $especimen->enlazarMuestra($muestraId);

        $this->especimenRepo->guardar($especimen);

        return new EnlazarEspecimenAMuestraOutput(
            especimenId: (string) $especimen->id(),
            muestraId: $especimen->muestraId() ?? '',
        );
    }
}
