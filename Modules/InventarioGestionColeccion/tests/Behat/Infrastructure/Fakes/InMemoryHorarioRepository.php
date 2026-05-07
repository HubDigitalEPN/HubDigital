<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Tests\Behat\Infrastructure\Fakes;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Horario;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\HorarioRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\HorarioId;

final class InMemoryHorarioRepository implements HorarioRepository
{
    private ?Horario $horario = null;

    public function obtenerUnico(): Horario
    {
        if (! $this->horario) {
            // Default horario for tests
            $this->horario = Horario::crear(
                id: HorarioId::generar(),
                horaInicio: 8,
                horaFin: 18,
            );
        }

        return $this->horario;
    }

    public function guardar(Horario $horario): void
    {
        $this->horario = $horario;
    }

    public function setHorario(Horario $horario): void
    {
        $this->horario = $horario;
    }
}
