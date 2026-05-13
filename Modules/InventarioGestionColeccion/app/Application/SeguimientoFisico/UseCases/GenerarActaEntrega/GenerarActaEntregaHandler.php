<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\GenerarActaEntrega;

use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\Ports\GeneradorActaPdfPort;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Exceptions\EntidadSinEspecimenesException;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\EntidadDepositanteRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\EspecimenRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\EntidadDepositanteId;

final class GenerarActaEntregaHandler
{
    public function __construct(
        private readonly EntidadDepositanteRepositoryInterface $entidadRepo,
        private readonly EspecimenRepositoryInterface $especimenRepo,
        private readonly GeneradorActaPdfPort $generadorPdf,
    ) {}

    public function handle(GenerarActaEntregaInput $input): GenerarActaEntregaOutput
    {
        $entidad = $this->entidadRepo->buscarPorId(EntidadDepositanteId::desde($input->entidadDepositanteId));

        if ($entidad === null) {
            throw new \DomainException("Entidad depositante no encontrada: '{$input->entidadDepositanteId}'.");
        }

        $especimenes = $this->especimenRepo->buscarPorEntidadDepositante($input->entidadDepositanteId);

        if (empty($especimenes)) {
            throw new EntidadSinEspecimenesException($input->entidadDepositanteId);
        }

        $pdfRuta = $this->generadorPdf->generar($entidad, $especimenes);

        return new GenerarActaEntregaOutput(
            entidadId: (string) $entidad->id(),
            entidadNombre: $entidad->nombre(),
            totalEspecimenes: count($especimenes),
            pdfRuta: $pdfRuta,
        );
    }
}
