<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\GenerarCodigoQr;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\CodigoQr;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Exceptions\EspecimenNoEncontradoException;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Exceptions\EspecimenYaTieneQrException;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\CodigoQrRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\EspecimenRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\EspecimenId;

final class GenerarCodigoQrHandler
{
    public function __construct(
        private readonly CodigoQrRepositoryInterface $codigoQrRepo,
        private readonly EspecimenRepositoryInterface $especimenRepo,
    ) {}

    public function handle(GenerarCodigoQrInput $input): GenerarCodigoQrOutput
    {
        $especimen = $this->especimenRepo->buscarPorId(EspecimenId::desde($input->especimenId));

        if ($especimen === null) {
            throw new EspecimenNoEncontradoException($input->especimenId);
        }

        // Invariante: un espécimen no puede tener dos QR vigentes. Reimprimir desde
        // un mismo registro generaría etiquetas físicas ambiguas para el mismo bicho.
        if ($this->codigoQrRepo->buscarPorEspecimen($input->especimenId) !== null) {
            throw new EspecimenYaTieneQrException($input->especimenId);
        }

        $codigoQr = CodigoQr::generar(
            id: $this->codigoQrRepo->nextIdentity(),
            especimenId: (string) $especimen->id(),
            payload: bin2hex(random_bytes(16)),
            generadoEn: new \DateTimeImmutable,
        );

        $this->codigoQrRepo->guardar($codigoQr);

        return new GenerarCodigoQrOutput(
            codigoQrId: (string) $codigoQr->id(),
            especimenId: $codigoQr->especimenId(),
            payload: $codigoQr->payload(),
        );
    }
}
