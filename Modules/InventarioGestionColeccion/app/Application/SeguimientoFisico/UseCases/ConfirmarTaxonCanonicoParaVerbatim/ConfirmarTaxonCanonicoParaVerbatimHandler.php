<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ConfirmarTaxonCanonicoParaVerbatim;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\EspecimenRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\TaxonRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\TaxonId;

/**
 * Confirma un taxón canónico para todos los especímenes con un mismo
 * `taxon_verbatim` que aún no tienen `taxon_id`.
 *
 * UPDATE SQL único — escalable.
 */
final class ConfirmarTaxonCanonicoParaVerbatimHandler
{
    public function __construct(
        private readonly EspecimenRepositoryInterface $especimenRepo,
        private readonly TaxonRepositoryInterface $taxonRepo,
    ) {}

    public function handle(ConfirmarTaxonCanonicoParaVerbatimInput $input): ConfirmarTaxonCanonicoParaVerbatimOutput
    {
        $taxonId = TaxonId::desde($input->taxonId);
        if ($this->taxonRepo->buscarPorId($taxonId) === null) {
            throw new \DomainException("Taxón canónico no encontrado: '{$input->taxonId}'.");
        }

        $verbatim = trim($input->verbatim);
        if ($verbatim === '') {
            throw new \InvalidArgumentException('El verbatim no puede estar vacío.');
        }

        $ids = $input->especimenIds;
        $contador = ($ids !== null && $ids !== [])
            ? $this->especimenRepo->enlazarTaxonPorIds($ids, (string) $taxonId)
            : $this->especimenRepo->enlazarTaxonPorVerbatim($verbatim, (string) $taxonId);

        return new ConfirmarTaxonCanonicoParaVerbatimOutput(
            verbatim: $verbatim,
            taxonId: (string) $taxonId,
            especimenesEnlazados: $contador,
        );
    }
}
