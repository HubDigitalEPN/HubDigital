<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Adapters;

use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\Ports\HorarioValidadorPort;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\HorarioRepository;

class DatabaseHorarioValidadorAdapter implements HorarioValidadorPort
{
    public function __construct(
        private readonly HorarioRepository $horarioRepository,
    ) {}

    public function esFueraDeHorario(\DateTimeImmutable $fecha): bool
    {
        $horario = $this->horarioRepository->obtenerUnico();

        return $horario->estaFueraDeHorario($fecha);
    }
}
