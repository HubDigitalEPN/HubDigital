<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ActualizarRanura;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\RanuraGabineteRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\RanuraId;

final class ActualizarRanuraHandler
{
    public function __construct(
        private readonly RanuraGabineteRepository $ranuraRepo,
    ) {}

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
