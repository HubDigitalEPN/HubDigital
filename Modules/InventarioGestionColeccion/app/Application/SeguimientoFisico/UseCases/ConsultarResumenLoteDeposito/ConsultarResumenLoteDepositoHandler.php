<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ConsultarResumenLoteDeposito;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\EspecimenRepositoryInterface;

/**
 * Qué hay realmente en la colección para un depósito dado.
 *
 * Existe por dos motivos. El primero, arquitectónico: era la única lectura por la que
 * GestionPrestamosRecepciones importaba un repositorio del Domain de este módulo, lo que
 * la regla del proyecto prohíbe expresamente. El segundo, práctico: aquel camino
 * reconstruía uno a uno los códigos de catálogo derivados para poder preguntar por
 * ellos, de modo que el otro módulo tenía que conocer la fórmula del código. Ahora se
 * pregunta por la identidad del trámite y se acabó.
 */
final class ConsultarResumenLoteDepositoHandler
{
    public function __construct(
        private readonly EspecimenRepositoryInterface $especimenRepo,
    ) {}

    public function handle(ConsultarResumenLoteDepositoInput $input): ConsultarResumenLoteDepositoOutput
    {
        $resumen = $this->especimenRepo->resumenPorSolicitudDeposito($input->solicitudDepositoId);

        return new ConsultarResumenLoteDepositoOutput(
            especimenesEnColeccion: $resumen['total'],
            pendientesRevision: $resumen['pendientesRevision'],
            devueltos: $resumen['devueltos'],
        );
    }
}
