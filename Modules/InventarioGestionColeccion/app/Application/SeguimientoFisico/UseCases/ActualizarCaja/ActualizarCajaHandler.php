<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ActualizarCaja;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Caja;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\CajaRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\CajaId;

/**
 * Caso de uso: actualizar los datos editables de una caja (nombre, observación y marca de
 * caja especial), preservando su estado físico, ubicación y clasificación taxonómica vigentes.
 *
 * @see ActualizarCajaInput
 * @see ActualizarCajaOutput
 */
final class ActualizarCajaHandler
{
    /**
     * @param  CajaRepository  $cajaRepo  Repositorio para recuperar y persistir la caja.
     */
    public function __construct(
        private readonly CajaRepository $cajaRepo,
    ) {}

    /**
     * Recupera la caja indicada, reconstituye una copia con los campos editables del input
     * (conservando estado, ranura, RFID y clasificación originales) y la persiste.
     *
     * @throws \DomainException si la caja no existe.
     */
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
            clasificacionTaxonomica: $caja->clasificacionTaxonomica(),
        );

        $this->cajaRepo->guardar($actualizada);

        return new ActualizarCajaOutput;
    }
}
