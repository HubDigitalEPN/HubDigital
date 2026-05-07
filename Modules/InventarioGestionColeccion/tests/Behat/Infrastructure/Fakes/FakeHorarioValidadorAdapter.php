<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Tests\Behat\Infrastructure\Fakes;

use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\Ports\HorarioValidadorPort;

final class FakeHorarioValidadorAdapter implements HorarioValidadorPort
{
    private bool $fueraDeHorario = false;

    public function setFueraDeHorario(bool $valor): void
    {
        $this->fueraDeHorario = $valor;
    }

    public function esFueraDeHorario(\DateTimeImmutable $fecha): bool
    {
        return $this->fueraDeHorario;
    }
}
