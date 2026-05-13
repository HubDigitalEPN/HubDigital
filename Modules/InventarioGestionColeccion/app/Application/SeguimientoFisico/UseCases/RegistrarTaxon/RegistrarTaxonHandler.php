<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\RegistrarTaxon;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Taxon;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Exceptions\NombreTaxonDuplicadoException;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\TaxonRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\RangoTaxonomico;

final class RegistrarTaxonHandler
{
    public function __construct(
        private readonly TaxonRepositoryInterface $taxonRepo,
    ) {}

    public function handle(RegistrarTaxonInput $input): RegistrarTaxonOutput
    {
        $rangoVO = RangoTaxonomico::tryFrom($input->rango);

        if ($rangoVO === null) {
            throw new \InvalidArgumentException(
                "Rango taxonómico inválido: '{$input->rango}'. Valores válidos: ".
                implode(', ', array_column(RangoTaxonomico::cases(), 'value'))
            );
        }

        $existente = $this->taxonRepo->buscarPorNombreYRango($input->nombreCientifico, $rangoVO);

        if ($existente !== null) {
            throw new NombreTaxonDuplicadoException($input->nombreCientifico, $input->rango);
        }

        $taxon = Taxon::crear(
            id: $this->taxonRepo->nextIdentity(),
            nombreCientifico: $input->nombreCientifico,
            rango: $input->rango,
            autor: $input->autor,
            anioDescripcion: $input->anioDescripcion,
            padreId: $input->padreId,
        );

        $this->taxonRepo->guardar($taxon);

        return new RegistrarTaxonOutput(
            id: $taxon->id(),
            nombreCientifico: $taxon->nombreCientifico(),
            rango: $taxon->rango()->value,
            autor: $taxon->autor(),
            anioDescripcion: $taxon->anioDescripcion(),
            estado: $taxon->estado()->value,
        );
    }
}
