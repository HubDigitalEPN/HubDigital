<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\MarcarMuestraParaRevision;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\MuestraColectaRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\MuestraColectaId;

final class MarcarMuestraParaRevisionHandler
{
    public function __construct(
        private readonly MuestraColectaRepositoryInterface $muestraRepo,
    ) {}

    public function handle(MarcarMuestraParaRevisionInput $input): MarcarMuestraParaRevisionOutput
    {
        $muestra = $this->muestraRepo->buscarPorId(MuestraColectaId::desde($input->muestraId));

        if ($muestra === null) {
            throw new \DomainException("Muestra de colecta no encontrada: '{$input->muestraId}'.");
        }

        $muestra->marcarParaRevision($input->motivo);

        $this->muestraRepo->guardar($muestra);

        return new MarcarMuestraParaRevisionOutput(
            muestraId: (string) $muestra->id(),
            estadoRevision: $muestra->estadoRevision()->value,
            motivoRevision: $muestra->motivoRevision() ?? '',
        );
    }
}
