<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Tests\Behat\Infrastructure\Fakes;

use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\Ports\HorarioValidadorPort;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Horario;
use Modules\InventarioGestionColeccion\Tests\Behat\Infrastructure\InMemory\InMemoryHorarioRepository;

final class FakeHorarioValidadorAdapter implements HorarioValidadorPort
{
    private InMemoryHorarioRepository $horarioRepository;

    private ?bool $forceOutsideSchedule = null;

    public function __construct(InMemoryHorarioRepository $horarioRepository)
    {
        $this->horarioRepository = $horarioRepository;
    }

    public function esFueraDeHorario(\DateTimeImmutable $fecha): bool
    {
        // If explicitly forced, return the forced value
        if ($this->forceOutsideSchedule !== null) {
            return $this->forceOutsideSchedule;
        }

        // Otherwise, use the repository's horario
        $horario = $this->horarioRepository->obtenerUnico();

        return $horario->estaFueraDeHorario($fecha);
    }

    /**
     * Backward compatibility method for existing Behat tests.
     * Forces esFueraDeHorario() to return the specified value.
     */
    public function setFueraDeHorario(bool $valor): void
    {
        $this->forceOutsideSchedule = $valor;
    }
}
