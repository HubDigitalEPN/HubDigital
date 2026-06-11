<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\EliminarCaja;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\CajaRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\CajaId;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\EstadoCaja;

/**
 * Caso de uso: eliminar una caja, permitido únicamente cuando está en tránsito (no asignada a
 * ningún gabinete), para no perder la trazabilidad de cajas físicamente ubicadas.
 *
 * @see EliminarCajaInput
 * @see EliminarCajaOutput
 */
final class EliminarCajaHandler
{
    /**
     * @param  CajaRepository  $cajaRepo  Recupera y elimina la caja.
     */
    public function __construct(
        private readonly CajaRepository $cajaRepo,
    ) {}

    /**
     * Recupera la caja, verifica que esté en tránsito y la elimina.
     *
     * @throws \DomainException si la caja no existe o no está en tránsito.
     */
    public function handle(EliminarCajaInput $input): EliminarCajaOutput
    {
        $caja = $this->cajaRepo->buscarPorId(CajaId::desde($input->cajaId));

        if ($caja === null) {
            throw new \DomainException('Caja no encontrada.');
        }

        if (! $caja->estadoActual()->equals(EstadoCaja::EnTransito)) {
            throw new \DomainException('Solo se puede eliminar una caja que esté en tránsito (no asignada a un gabinete).');
        }

        $this->cajaRepo->eliminar($caja->id());

        return new EliminarCajaOutput;
    }
}
