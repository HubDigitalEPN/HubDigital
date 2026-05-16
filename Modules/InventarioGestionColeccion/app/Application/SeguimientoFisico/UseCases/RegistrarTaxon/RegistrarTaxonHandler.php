<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\RegistrarTaxon;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Taxon;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Exceptions\NombreTaxonDuplicadoException;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\TaxonRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\RangoTaxonomico;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\TaxonId;

final class RegistrarTaxonHandler
{
    public function __construct(
        private readonly TaxonRepositoryInterface $taxonRepo,
    ) {}

    public function handle(RegistrarTaxonInput $input): RegistrarTaxonOutput
    {
        $rangoVO = RangoTaxonomico::desdeValorFlexible($input->rango);

        if ($rangoVO === null) {
            throw new \InvalidArgumentException(
                "Rango taxonómico inválido: '{$input->rango}'. Valores válidos: ".
                implode(', ', RangoTaxonomico::valoresAceptados())
            );
        }

        if ($input->padreId !== null) {
            $padre = $this->taxonRepo->buscarPorId(TaxonId::desde($input->padreId));

            if ($padre === null) {
                throw new \DomainException("Taxón padre no encontrado: '{$input->padreId}'.");
            }

            if (! $padre->rango()->puedeSerPadreDe($rangoVO)) {
                throw new \DomainException(
                    "El rango '{$padre->rango()->value}' no puede ser padre de '{$rangoVO->value}'."
                );
            }
        }

        $existente = $this->taxonRepo->buscarPorNombreYRango($input->nombreCientifico, $rangoVO);

        if ($existente !== null) {
            throw new NombreTaxonDuplicadoException($input->nombreCientifico, $input->rango);
        }

        $taxon = Taxon::crear(
            id: $this->taxonRepo->nextIdentity(),
            nombreCientifico: $input->nombreCientifico,
            rango: $rangoVO->value,
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
