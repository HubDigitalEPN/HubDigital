<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\RegistrarMuestraColecta;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\MuestraColecta;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\LocalidadRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\MuestraColectaRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\LocalidadId;

final class RegistrarMuestraColectaHandler
{
    public function __construct(
        private readonly MuestraColectaRepositoryInterface $muestraRepo,
        private readonly LocalidadRepositoryInterface $localidadRepo,
    ) {}

    public function handle(RegistrarMuestraColectaInput $input): RegistrarMuestraColectaOutput
    {
        if ($input->localidadId !== null) {
            $localidad = $this->localidadRepo->buscarPorId(LocalidadId::desde($input->localidadId));
            if ($localidad === null) {
                throw new \DomainException("Localidad no encontrada: '{$input->localidadId}'.");
            }
        }

        $fechaColecta = $input->fechaColecta !== null
            ? new \DateTimeImmutable($input->fechaColecta)
            : null;

        $muestra = MuestraColecta::crear(
            id: $this->muestraRepo->nextIdentity(),
            codigoMuestra: $input->codigoMuestra,
            fechaColecta: $fechaColecta,
            fechaVerbatim: $input->fechaVerbatim,
            localidadId: $input->localidadId,
            localidadVerbatim: $input->localidadVerbatim,
            colector: $input->colector,
            samplingProtocol: $input->samplingProtocol,
            motivoRevision: $input->motivoRevision,
        );

        $this->muestraRepo->guardar($muestra);

        return new RegistrarMuestraColectaOutput(
            id: $muestra->id(),
            codigoMuestra: $muestra->codigoMuestra(),
            fechaColecta: $muestra->fechaColecta()?->format('Y-m-d'),
            localidadId: $muestra->localidadId() !== null ? (string) $muestra->localidadId() : null,
            colector: $muestra->colector(),
            estadoRevision: $muestra->estadoRevision()->value,
            motivoRevision: $muestra->motivoRevision(),
        );
    }
}
