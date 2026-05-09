<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\EliminarCaja;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\CajaRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\CajaId;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\EstadoCaja;

final class EliminarCajaHandler
{
    public function __construct(
        private readonly CajaRepository $cajaRepo,
    ) {}

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
