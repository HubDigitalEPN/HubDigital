<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ActualizarTaxon;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\TaxonRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\TaxonId;

final class ActualizarTaxonHandler
{
    public function __construct(
        private readonly TaxonRepositoryInterface $taxonRepo,
    ) {}

    public function handle(ActualizarTaxonInput $input): ActualizarTaxonOutput
    {
        $taxon = $this->taxonRepo->buscarPorId(TaxonId::desde($input->taxonId));

        if ($taxon === null) {
            throw new \DomainException("Taxón no encontrado: '{$input->taxonId}'.");
        }

        $taxon->actualizar(
            nombreCientifico: $input->nombreCientifico,
            autor: $input->autor,
            anioDescripcion: $input->anioDescripcion,
            epitetoInfraespecifico: $input->epitetoInfraespecifico,
        );

        $this->taxonRepo->guardar($taxon);

        return new ActualizarTaxonOutput(
            id: (string) $taxon->id(),
            nombreCientifico: $taxon->nombreCientifico(),
            rango: $taxon->rango()->value,
            autor: $taxon->autor(),
            anioDescripcion: $taxon->anioDescripcion(),
            estado: $taxon->estado()->value,
            epitetoInfraespecifico: $taxon->epitetoInfraespecifico(),
        );
    }
}
