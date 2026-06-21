<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\RegistrarVisitante;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Visitante;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\VisitanteRepositoryInterface;

final class RegistrarVisitanteHandler
{
    public function __construct(
        private readonly VisitanteRepositoryInterface $visitanteRepo,
    ) {}

    public function handle(RegistrarVisitanteInput $input): RegistrarVisitanteOutput
    {
        $visitante = Visitante::crear(
            id: $this->visitanteRepo->nextIdentity(),
            nombre: $input->nombre,
            contacto: $input->contacto,
            registradoEn: new \DateTimeImmutable('now'),
        );

        $this->visitanteRepo->guardar($visitante);

        return new RegistrarVisitanteOutput(
            id: $visitante->id(),
            nombre: $visitante->nombre(),
            contacto: $visitante->contacto(),
            versionAcceso: $visitante->versionAcceso(),
        );
    }
}
