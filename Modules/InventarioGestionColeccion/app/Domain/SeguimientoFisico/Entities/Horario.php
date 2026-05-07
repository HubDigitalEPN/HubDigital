<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\HorarioId;

class Horario
{
    private function __construct(
        private readonly HorarioId $id,
        private int $horaInicio,
        private int $horaFin,
        private bool $activo,
    ) {}

    public static function crear(
        HorarioId $id,
        int $horaInicio,
        int $horaFin,
    ): self {
        self::validarHoras($horaInicio, $horaFin);

        return new self(
            id: $id,
            horaInicio: $horaInicio,
            horaFin: $horaFin,
            activo: true,
        );
    }

    public static function reconstituir(
        HorarioId $id,
        int $horaInicio,
        int $horaFin,
        bool $activo,
    ): self {
        self::validarHoras($horaInicio, $horaFin);

        return new self(
            id: $id,
            horaInicio: $horaInicio,
            horaFin: $horaFin,
            activo: $activo,
        );
    }

    public function actualizar(int $horaInicio, int $horaFin): void
    {
        self::validarHoras($horaInicio, $horaFin);

        $this->horaInicio = $horaInicio;
        $this->horaFin = $horaFin;
    }

    public function estaFueraDeHorario(\DateTimeImmutable $fecha): bool
    {
        $hora = (int) $fecha->format('H');

        return $hora < $this->horaInicio || $hora >= $this->horaFin;
    }

    private static function validarHoras(int $horaInicio, int $horaFin): void
    {
        if ($horaInicio < 0 || $horaInicio > 23) {
            throw new \InvalidArgumentException('Hora de inicio debe estar entre 0 y 23.');
        }

        if ($horaFin < 0 || $horaFin > 23) {
            throw new \InvalidArgumentException('Hora de fin debe estar entre 0 y 23.');
        }

        if ($horaInicio >= $horaFin) {
            throw new \InvalidArgumentException('Hora de inicio debe ser menor que hora de fin.');
        }
    }

    public function id(): HorarioId
    {
        return $this->id;
    }

    public function horaInicio(): int
    {
        return $this->horaInicio;
    }

    public function horaFin(): int
    {
        return $this->horaFin;
    }

    public function activo(): bool
    {
        return $this->activo;
    }
}
