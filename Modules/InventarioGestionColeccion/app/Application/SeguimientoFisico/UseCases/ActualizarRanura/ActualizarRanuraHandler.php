<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ActualizarRanura;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\RanuraGabineteRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\RanuraId;

/**
 * Caso de uso: activar o desactivar una ranura concreta de un gabinete, controlando si está
 * disponible para alojar cajas.
 *
 * @see ActualizarRanuraInput
 * @see ActualizarRanuraOutput
 */
final class ActualizarRanuraHandler
{
    /**
     * @param  RanuraGabineteRepository  $ranuraRepo  Recupera y persiste la ranura.
     */
    public function __construct(
        private readonly RanuraGabineteRepository $ranuraRepo,
    ) {}

    /**
     * Recupera la ranura y la activa o desactiva según el input, persistiendo el cambio.
     *
     * @throws \DomainException si la ranura no existe.
     */
    public function handle(ActualizarRanuraInput $input): ActualizarRanuraOutput
    {
        $ranura = $this->ranuraRepo->buscarPorId(RanuraId::desde($input->ranuraId));

        if ($ranura === null) {
            throw new \DomainException('Ranura no encontrada.');
        }

        if ($input->activa) {
            $ranura->activar();
        } else {
            $ranura->desactivar();
        }

        $this->ranuraRepo->guardar($ranura);

        return new ActualizarRanuraOutput;
    }
}
