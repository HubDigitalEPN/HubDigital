<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ConfirmarMuestra;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\MuestraColectaRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\MuestraColectaId;

final class ConfirmarMuestraHandler
{
    public function __construct(
        private readonly MuestraColectaRepositoryInterface $muestraRepo,
    ) {}

    public function handle(ConfirmarMuestraInput $input): ConfirmarMuestraOutput
    {
        $muestra = $this->muestraRepo->buscarPorId(MuestraColectaId::desde($input->muestraId));

        if ($muestra === null) {
            throw new \DomainException("Muestra de colecta no encontrada: '{$input->muestraId}'.");
        }

        $muestra->confirmar();

        $this->muestraRepo->guardar($muestra);

        return new ConfirmarMuestraOutput(
            muestraId: (string) $muestra->id(),
            estadoRevision: $muestra->estadoRevision()->value,
        );
    }
}
