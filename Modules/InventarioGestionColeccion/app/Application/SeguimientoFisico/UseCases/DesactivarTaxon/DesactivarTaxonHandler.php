<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\DesactivarTaxon;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\TaxonRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\TaxonId;

final class DesactivarTaxonHandler
{
    public function __construct(
        private readonly TaxonRepositoryInterface $taxonRepo,
    ) {}

    public function handle(DesactivarTaxonInput $input): DesactivarTaxonOutput
    {
        $taxon = $this->taxonRepo->buscarPorId(TaxonId::desde($input->taxonId));

        if ($taxon === null) {
            throw new \DomainException("Taxón no encontrado: '{$input->taxonId}'.");
        }

        $taxon->desactivar();
        $this->taxonRepo->guardar($taxon);

        return new DesactivarTaxonOutput(
            taxonId: (string) $taxon->id(),
            estado: $taxon->estado()->value,
        );
    }
}
