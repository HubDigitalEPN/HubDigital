<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ActualizarEntidadDepositante;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\EntidadDepositanteRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\EntidadDepositanteId;

final class ActualizarEntidadDepositanteHandler
{
    public function __construct(
        private readonly EntidadDepositanteRepositoryInterface $entidadRepo,
    ) {}

    public function handle(ActualizarEntidadDepositanteInput $input): ActualizarEntidadDepositanteOutput
    {
        $entidad = $this->entidadRepo->buscarPorId(EntidadDepositanteId::desde($input->entidadId));

        if ($entidad === null) {
            throw new \DomainException("Entidad depositante no encontrada: '{$input->entidadId}'.");
        }

        $entidad->actualizar(
            nombre: $input->nombre,
            tipo: $input->tipo,
            contacto: $input->contacto,
        );

        $this->entidadRepo->guardar($entidad);

        return new ActualizarEntidadDepositanteOutput(
            id: (string) $entidad->id(),
            nombre: $entidad->nombre(),
            tipo: $entidad->tipo()->value,
            contacto: $entidad->contacto(),
        );
    }
}
