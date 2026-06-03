<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ResolverDuplicadoDeCatalogNumber;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\EspecimenRepositoryInterface;

/**
 * Aplica la decisión del curador sobre un grupo de especímenes que comparten
 * el mismo `catalog_number`.
 *
 *  - `eventos_distintos`: los registros son legítimos eventos separados.
 *    UPDATE estado_revision='confirmada', motivo_revision=NULL.
 *  - `error_catalogacion`: hay un error. UPDATE estado_revision='pendiente',
 *    motivo_revision=$motivo. El curador investiga después.
 *
 * UPDATE SQL único — escalable.
 */
final class ResolverDuplicadoDeCatalogNumberHandler
{
    public function __construct(
        private readonly EspecimenRepositoryInterface $especimenRepo,
    ) {}

    public function handle(ResolverDuplicadoDeCatalogNumberInput $input): ResolverDuplicadoDeCatalogNumberOutput
    {
        $catalogNumber = trim($input->catalogNumber);
        if ($catalogNumber === '') {
            throw new \InvalidArgumentException('catalogNumber no puede estar vacío.');
        }

        $decision = $input->decision;
        if (! in_array($decision, [
            ResolverDuplicadoDeCatalogNumberInput::DECISION_EVENTOS_DISTINTOS,
            ResolverDuplicadoDeCatalogNumberInput::DECISION_ERROR_CATALOGACION,
        ], true)) {
            throw new \InvalidArgumentException("Decisión inválida: '{$decision}'.");
        }

        if ($decision === ResolverDuplicadoDeCatalogNumberInput::DECISION_EVENTOS_DISTINTOS) {
            $afectados = $this->especimenRepo->confirmarRevisionPorCatalogNumber($catalogNumber);
        } else {
            $motivo = trim($input->motivo);
            if ($motivo === '') {
                throw new \InvalidArgumentException('Para error_catalogacion el motivo no puede estar vacío.');
            }
            $afectados = $this->especimenRepo->marcarRevisionPorCatalogNumber($catalogNumber, $motivo);
        }

        return new ResolverDuplicadoDeCatalogNumberOutput(
            catalogNumber: $catalogNumber,
            decisionAplicada: $decision,
            especimenesAfectados: $afectados,
        );
    }
}
