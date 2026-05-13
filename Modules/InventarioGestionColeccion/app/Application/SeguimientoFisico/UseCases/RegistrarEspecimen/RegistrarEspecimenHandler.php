<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\RegistrarEspecimen;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Especimen;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Exceptions\CodigoCatalogoDuplicadoException;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\EntidadDepositanteRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\EspecimenRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\TaxonRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\EntidadDepositanteId;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\TaxonId;

final class RegistrarEspecimenHandler
{
    public function __construct(
        private readonly EspecimenRepositoryInterface $especimenRepo,
        private readonly TaxonRepositoryInterface $taxonRepo,
        private readonly EntidadDepositanteRepositoryInterface $entidadRepo,
    ) {}

    public function handle(RegistrarEspecimenInput $input): RegistrarEspecimenOutput
    {
        $taxon = $this->taxonRepo->buscarPorId(TaxonId::desde($input->taxonId));

        if ($taxon === null) {
            throw new \DomainException("Taxón no encontrado: '{$input->taxonId}'.");
        }

        if ($input->entidadDepositanteId !== null) {
            $entidad = $this->entidadRepo->buscarPorId(EntidadDepositanteId::desde($input->entidadDepositanteId));

            if ($entidad === null) {
                throw new \DomainException("Entidad depositante no encontrada: '{$input->entidadDepositanteId}'.");
            }
        }

        $existente = $this->especimenRepo->buscarPorCodigoCatalogo($input->codigoCatalogo);

        if ($existente !== null) {
            throw new CodigoCatalogoDuplicadoException($input->codigoCatalogo);
        }

        $especimen = Especimen::crear(
            id: $this->especimenRepo->nextIdentity(),
            codigoCatalogo: $input->codigoCatalogo,
            taxonId: $input->taxonId,
            localidad: $input->localidad,
            fechaColecta: $input->fechaColecta,
            colector: $input->colector,
            entidadDepositanteId: $input->entidadDepositanteId,
        );

        $this->especimenRepo->guardar($especimen);

        return new RegistrarEspecimenOutput(
            id: $especimen->id(),
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
