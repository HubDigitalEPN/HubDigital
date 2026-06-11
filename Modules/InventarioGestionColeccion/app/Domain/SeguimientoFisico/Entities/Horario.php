<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\HorarioId;

/**
 * Entidad que representa la ventana horaria laboral del laboratorio durante la cual
 * se considera "normal" que las cajas estén fuera de su ranura. Fuera de este rango
 * (o cuando una caja permanece ausente más allá de él) el seguimiento físico puede
 * disparar alertas de tiempo de extracción excedido.
 *
 * Se modela como un intervalo de horas enteras [horaInicio, horaFin) en formato 24h.
 * Construir vía {@see self::crear()}; rehidratar desde persistencia vía
 * {@see self::reconstituir()}.
 */
class Horario
{
    private function __construct(
        private readonly HorarioId $id,
        private int $horaInicio,
        private int $horaFin,
        private bool $activo,
    ) {}

    /**
     * Crea un horario nuevo y activo a partir de las horas de inicio y fin, validando
     * que formen un intervalo coherente.
     *
     * @throws \InvalidArgumentException Si las horas están fuera de 0-23 o el inicio no es menor que el fin.
     */
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

    /**
     * Rehidrata un horario desde persistencia conservando su estado de activación.
     *
     * @throws \InvalidArgumentException Si las horas persistidas no forman un intervalo válido.
     */
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

    /**
     * Reajusta el intervalo horario a un nuevo inicio y fin, revalidando la coherencia.
     *
     * @throws \InvalidArgumentException Si las nuevas horas no forman un intervalo válido.
     */
    public function actualizar(int $horaInicio, int $horaFin): void
    {
        self::validarHoras($horaInicio, $horaFin);

        $this->horaInicio = $horaInicio;
        $this->horaFin = $horaFin;
    }

    /**
     * Indica si la fecha dada cae fuera de la ventana horaria laboral, comparando
     * únicamente su hora contra el intervalo [horaInicio, horaFin).
     */
    public function estaFueraDeHorario(\DateTimeImmutable $fecha): bool
    {
        $hora = (int) $fecha->format('H');

        return $hora < $this->horaInicio || $hora >= $this->horaFin;
    }

    /**
     * Garantiza la invariante del horario: ambas horas dentro de 0-23 y el inicio
     * estrictamente menor que el fin.
     *
     * @throws \InvalidArgumentException Si alguna condición no se cumple.
     */
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
