<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ActualizarCaja;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Caja;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\CajaRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\CajaId;

final class ActualizarCajaHandler
{
    public function __construct(
        private readonly CajaRepository $cajaRepo,
    ) {}

    public function handle(ActualizarCajaInput $input): ActualizarCajaOutput
    {
        $caja = $this->cajaRepo->buscarPorId(CajaId::desde($input->cajaId));

        if ($caja === null) {
            throw new \DomainException('Caja no encontrada.');
        }

        $actualizada = Caja::reconstituir(
            id: $caja->id(),
            codigo: $caja->codigo(),
            estado: $caja->estadoActual(),
            ranuraActualId: $caja->ranuraActualId(),
            codigoRfid: $caja->codigoRfid(),
            esEspecial: $input->esEspecial,
            observacion: $input->observacion,
            nombre: $input->nombre,
            capacidadMaxima: $input->capacidadMaxima,
            clasificacionTaxonomica: $caja->clasificacionTaxonomica(),
        );

        $this->cajaRepo->guardar($actualizada);

        return new ActualizarCajaOutput;
    }
}
