<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ResolverCodigoQr;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Exceptions\EspecimenNoEncontradoException;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\CodigoQrRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\EspecimenRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\TaxonRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\EspecimenId;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\TaxonId;

final class ResolverCodigoQrHandler
{
    public function __construct(
        private readonly CodigoQrRepositoryInterface $codigoQrRepo,
        private readonly EspecimenRepositoryInterface $especimenRepo,
        private readonly TaxonRepositoryInterface $taxonRepo,
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

        // Resuelve el nombre científico legible (heurística de usabilidad: nunca
        // mostrar solo el id crudo del taxón). Null si el espécimen aún no está
        // determinado.
        $taxonNombre = null;
        if ($especimen->taxonId() !== null) {
            $taxon = $this->taxonRepo->buscarPorId(TaxonId::desde($especimen->taxonId()));
            $taxonNombre = $taxon?->nombreCientifico();
        }

        return new ResolverCodigoQrOutput(
            id: (string) $especimen->id(),
            codigoCatalogo: $especimen->codigoCatalogo(),
            taxonId: $especimen->taxonId(),
            taxonNombre: $taxonNombre,
            localidad: $especimen->localidad(),
            fechaColecta: $especimen->fechaColecta(),
            colector: $especimen->colector(),
            estado: $especimen->estado()->value,
            entidadDepositanteId: $especimen->entidadDepositanteId(),
        );
    }
}
