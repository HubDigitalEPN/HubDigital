<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ConfirmarLocalidadCanonicaParaVerbatim;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\EspecimenRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\LocalidadRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\LocalidadId;

/**
 * Confirma una localidad canónica para todos los especímenes con un mismo
 * `localidad_verbatim` que aún no tienen `localidad_id`.
 *
 * Usa `enlazarLocalidadPorVerbatim()` (UPDATE SQL único) — escala a 48k filas.
 */
final class ConfirmarLocalidadCanonicaParaVerbatimHandler
{
    public function __construct(
        private readonly EspecimenRepositoryInterface $especimenRepo,
        private readonly LocalidadRepositoryInterface $localidadRepo,
    ) {}

    public function handle(ConfirmarLocalidadCanonicaParaVerbatimInput $input): ConfirmarLocalidadCanonicaParaVerbatimOutput
    {
        $localidadId = LocalidadId::desde($input->localidadId);
        $localidad = $this->localidadRepo->buscarPorId($localidadId);
        if ($localidad === null) {
            throw new \DomainException("Localidad canónica no encontrada: '{$input->localidadId}'.");
        }

        $verbatim = trim($input->verbatim);
        if ($verbatim === '') {
            throw new \InvalidArgumentException('El verbatim no puede estar vacío.');
        }

        $contador = $this->especimenRepo->enlazarLocalidadPorVerbatim($verbatim, (string) $localidadId);

        return new ConfirmarLocalidadCanonicaParaVerbatimOutput(
            verbatim: $verbatim,
            localidadId: (string) $localidadId,
            especimenesEnlazados: $contador,
        );
    }
}
