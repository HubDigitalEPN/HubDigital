<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\RegistrarEntidadDepositante;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\EntidadDepositante;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Exceptions\NombreEntidadDuplicadoException;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\EntidadDepositanteRepositoryInterface;

final class RegistrarEntidadDepositanteHandler
{
    public function __construct(
        private readonly EntidadDepositanteRepositoryInterface $entidadRepo,
    ) {}

    public function handle(RegistrarEntidadDepositanteInput $input): RegistrarEntidadDepositanteOutput
    {
        $existente = $this->entidadRepo->buscarPorNombre($input->nombre);

        if ($existente !== null) {
            throw new NombreEntidadDuplicadoException($input->nombre);
        }

        $entidad = EntidadDepositante::crear(
            id: $this->entidadRepo->nextIdentity(),
            nombre: $input->nombre,
            tipo: $input->tipo,
            contacto: $input->contacto,
        );

        $this->entidadRepo->guardar($entidad);

        return new RegistrarEntidadDepositanteOutput(
            id: $entidad->id(),
            nombre: $entidad->nombre(),
            tipo: $entidad->tipo()->value,
            contacto: $entidad->contacto(),
        );
    }
}
