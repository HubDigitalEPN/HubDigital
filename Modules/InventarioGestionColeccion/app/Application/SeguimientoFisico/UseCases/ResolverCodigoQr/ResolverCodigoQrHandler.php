<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ResolverCodigoQr;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Exceptions\EspecimenNoEncontradoException;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\CodigoQrRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\EspecimenRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\EspecimenId;

final class ResolverCodigoQrHandler
{
    public function __construct(
        private readonly CodigoQrRepositoryInterface $codigoQrRepo,
        private readonly EspecimenRepositoryInterface $especimenRepo,
    ) {}

    public function handle(ResolverCodigoQrInput $input): ResolverCodigoQrOutput
    {
        $codigoQr = $this->codigoQrRepo->buscarPorPayload($input->payload);

        if ($codigoQr === null) {
            throw new EspecimenNoEncontradoException($input->payload);
        }

        $especimen = $this->especimenRepo->buscarPorId(EspecimenId::desde($codigoQr->especimenId()));

        if ($especimen === null) {
            throw new EspecimenNoEncontradoException($codigoQr->especimenId());
        }

        return new ResolverCodigoQrOutput(
            id: (string) $especimen->id(),
            codigoCatalogo: $especimen->codigoCatalogo(),
            taxonId: $especimen->taxonId(),
            localidad: $especimen->localidad(),
            fechaColecta: $especimen->fechaColecta(),
            colector: $especimen->colector(),
            estado: $especimen->estado()->value,
            entidadDepositanteId: $especimen->entidadDepositanteId(),
        );
    }
}
