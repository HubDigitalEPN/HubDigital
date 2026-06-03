<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ResolverDuplicadoDeCatalogNumber;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Especimen;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\EspecimenRepositoryInterface;

/**
 * Aplica la decisión del curador sobre un grupo de especímenes que comparten
 * el mismo `catalog_number`.
 *
 *  - `eventos_distintos`: los registros son legítimos eventos separados.
 *    Cada especímen pasa a `estadoRevision = confirmada`, motivo limpio.
 *  - `error_catalogacion`: hay un error de catalogación. Cada especímen pasa
 *    a `estadoRevision = pendiente` con el motivo provisto, para que el
 *    curador investigue y corrija catalog_number manualmente.
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

        $motivo = trim($input->motivo);
        if ($motivo === '') {
            throw new \InvalidArgumentException('motivo no puede estar vacío.');
        }

        if (! in_array($input->decision, [
            ResolverDuplicadoDeCatalogNumberInput::DECISION_EVENTOS_DISTINTOS,
            ResolverDuplicadoDeCatalogNumberInput::DECISION_ERROR_CATALOGACION,
        ], true)) {
            throw new \InvalidArgumentException("Decisión inválida: '{$input->decision}'.");
        }

        $afectados = 0;
        foreach ($this->especimenRepo->buscarTodos() as $especimen) {
            if ($especimen->catalogNumber() !== $catalogNumber) {
                continue;
            }
            $this->aplicarDecision($especimen, $input->decision, $motivo);
            $this->especimenRepo->guardar($especimen);
            $afectados++;
        }

        return new ResolverDuplicadoDeCatalogNumberOutput(
            catalogNumber: $catalogNumber,
            decisionAplicada: $input->decision,
            especimenesAfectados: $afectados,
        );
    }

    private function aplicarDecision(Especimen $especimen, string $decision, string $motivo): void
    {
        if ($decision === ResolverDuplicadoDeCatalogNumberInput::DECISION_EVENTOS_DISTINTOS) {
            // Si ya está confirmado, no hacemos nada. Si está pendiente, lo confirmamos.
            if ($especimen->estadoRevision()->puedeConfirmarse()) {
                $especimen->confirmarRevision();
            }

            return;
        }

        // DECISION_ERROR_CATALOGACION: marcar para revisión con el motivo del curador.
        $especimen->marcarParaRevision($motivo);
    }
}
