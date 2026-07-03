<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ObtenerCodigoQr;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\CodigoQrRepositoryInterface;

final class ObtenerCodigoQrHandler
{
    public function __construct(
        private readonly CodigoQrRepositoryInterface $codigoQrRepo,
    ) {}

    public function handle(ObtenerCodigoQrInput $input): ObtenerCodigoQrOutput
    {
        $codigoQr = $this->codigoQrRepo->buscarPorEspecimen($input->especimenId);

        if ($codigoQr === null) {
            return new ObtenerCodigoQrOutput(existe: false);
        }

        return new ObtenerCodigoQrOutput(
            existe: true,
            codigoQrId: (string) $codigoQr->id(),
            payload: $codigoQr->payload(),
        );
    }
}
