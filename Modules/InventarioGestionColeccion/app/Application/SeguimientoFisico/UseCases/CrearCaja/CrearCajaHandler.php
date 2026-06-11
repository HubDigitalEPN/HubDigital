<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\CrearCaja;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Caja;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\CajaRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\CodigoCaja;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\CodigoRfid;

/**
 * Caso de uso: dar de alta una nueva caja entomológica con su código, su etiqueta RFID y sus
 * datos opcionales (observación, nombre, marca de especial), dejándola lista para ubicarse.
 *
 * @see CrearCajaInput
 * @see CrearCajaOutput
 */
final class CrearCajaHandler
{
    /**
     * @param  CajaRepository  $cajaRepo  Genera la identidad de la caja y la persiste.
     */
    public function __construct(
        private readonly CajaRepository $cajaRepo,
    ) {}

    /**
     * Crea la caja a partir del input, le asocia su código RFID y la persiste, devolviendo
     * sus datos ya consolidados (incluido el estado físico inicial).
     */
    public function handle(CrearCajaInput $input): CrearCajaOutput
    {
        $id = $this->cajaRepo->nextIdentity();

        $caja = Caja::crear(
            id: $id,
            codigo: CodigoCaja::desde($input->codigo),
            esEspecial: $input->esEspecial,
            observacion: $input->observacion,
            nombre: $input->nombre,
        );

        $caja->asignarRfid(CodigoRfid::desde($input->codigoRfid));

        $this->cajaRepo->guardar($caja);

        return new CrearCajaOutput(
            id: (string) $caja->id(),
            codigo: (string) $caja->codigo(),
            codigoRfid: (string) $caja->codigoRfid(),
            esEspecial: $caja->esEspecial(),
            observacion: $caja->observacion(),
            nombre: $caja->nombre(),
            estado: $caja->estadoActual()->valor(),
        );
    }
}
