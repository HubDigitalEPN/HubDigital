<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\IdentificarEspecimen;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Identificacion;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\EspecimenRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\IdentificacionRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\TaxonRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\EspecimenId;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\TaxonId;

final class IdentificarEspecimenHandler
{
    public function __construct(
        private readonly IdentificacionRepositoryInterface $identificacionRepo,
        private readonly EspecimenRepositoryInterface $especimenRepo,
        private readonly TaxonRepositoryInterface $taxonRepo,
    ) {}

    public function handle(IdentificarEspecimenInput $input): IdentificarEspecimenOutput
    {
        $especimenId = EspecimenId::desde($input->especimenId);
        $especimen = $this->especimenRepo->buscarPorId($especimenId);
        if ($especimen === null) {
            throw new \DomainException("Espécimen no encontrado: '{$input->especimenId}'.");
        }

        if ($input->taxonId !== null) {
            $taxon = $this->taxonRepo->buscarPorId(TaxonId::desde($input->taxonId));
            if ($taxon === null) {
                throw new \DomainException("Taxón no encontrado: '{$input->taxonId}'.");
            }
        }

        // Invariante: sólo una identificación es_actual=true por espécimen.
        // Marcamos las anteriores como superadas antes de crear la nueva.
        $this->identificacionRepo->desactualizarTodasDe($especimenId);

        $fecha = $input->fechaDeterminacion !== null
            ? new \DateTimeImmutable($input->fechaDeterminacion)
            : null;

        $identificacion = Identificacion::crear(
            id: $this->identificacionRepo->nextIdentity(),
            especimenId: $especimenId,
            taxonId: $input->taxonId,
            identificadoPor: $input->identificadoPor,
            fechaDeterminacion: $fecha,
            calificador: $input->calificador,
            observaciones: $input->observaciones,
            esActual: true,
        );

        $this->identificacionRepo->guardar($identificacion);

        // El espécimen recibe el taxon_id del taxón identificado (si lo hay).
        if ($input->taxonId !== null) {
            $especimen->enlazarTaxon(TaxonId::desde($input->taxonId));
            $this->especimenRepo->guardar($especimen);
        }

        return new IdentificarEspecimenOutput(
            id: $identificacion->id(),
            especimenId: (string) $identificacion->especimenId(),
            taxonId: $identificacion->taxonId() !== null ? (string) $identificacion->taxonId() : null,
            identificadoPor: $identificacion->identificadoPor(),
            fechaDeterminacion: $identificacion->fechaDeterminacion()?->format('Y-m-d'),
            calificador: $identificacion->calificador(),
            observaciones: $identificacion->observaciones(),
            esActual: $identificacion->esActual(),
        );
    }
}
