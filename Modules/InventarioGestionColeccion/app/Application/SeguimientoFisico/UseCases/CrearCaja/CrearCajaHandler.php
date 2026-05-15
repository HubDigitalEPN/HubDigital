<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\CrearCaja;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Caja;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\CajaRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\CodigoCaja;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\CodigoRfid;

final class CrearCajaHandler
{
    public function __construct(
        private readonly CajaRepository $cajaRepo,
    ) {}

    public function handle(CrearCajaInput $input): CrearCajaOutput
    {
        $id = $this->cajaRepo->nextIdentity();

        $caja = Caja::crear(
            id: $id,
            codigo: CodigoCaja::desde($input->codigo),
            familiaTaxonomicaId: $input->familiaTaxonomicaId,
            nombre: $input->nombre,
            capacidadMaxima: $input->capacidadMaxima,
        );

        $caja->asignarRfid(CodigoRfid::desde($input->codigoRfid));

        $this->cajaRepo->guardar($caja);

        return new CrearCajaOutput(
            id: (string) $caja->id(),
            codigo: (string) $caja->codigo(),
            codigoRfid: (string) $caja->codigoRfid(),
            nombre: $caja->nombre(),
            familiaTaxonomicaId: $caja->familiaTaxonomicaId(),
            capacidadMaxima: $caja->capacidadMaxima(),
            estado: $caja->estadoActual()->valor(),
        );
    }
}
