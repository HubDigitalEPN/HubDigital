<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\Entities;

use DateTimeImmutable;
use InvalidArgumentException;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\PrestamoId;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\RecordatorioDevolucionId;

final class RecordatorioDevolucion
{
    private function __construct(
        private readonly RecordatorioDevolucionId $id,
        private readonly PrestamoId $prestamoId,
        private readonly DateTimeImmutable $fechaProgramada,
        private readonly int $diasAntesVencimiento,
    ) {}

    public static function programar(
        RecordatorioDevolucionId $id,
        PrestamoId $prestamoId,
        DateTimeImmutable $fechaProgramada,
        int $diasAntesVencimiento,
    ): self {
        if ($diasAntesVencimiento < 1) {
            throw new InvalidArgumentException(
                "Los días antes del vencimiento deben ser ≥ 1, se recibió: {$diasAntesVencimiento}."
            );
        }

        return new self(
            id: $id,
            prestamoId: $prestamoId,
            fechaProgramada: $fechaProgramada,
            diasAntesVencimiento: $diasAntesVencimiento,
        );
    }

    public static function reconstituir(
        RecordatorioDevolucionId $id,
        PrestamoId $prestamoId,
        DateTimeImmutable $fechaProgramada,
        int $diasAntesVencimiento,
    ): self {
        return new self(
            id: $id,
            prestamoId: $prestamoId,
            fechaProgramada: $fechaProgramada,
            diasAntesVencimiento: $diasAntesVencimiento,
        );
    }

    // ── Accessors ─────────────────────────────────────────────────────────────

    public function id(): RecordatorioDevolucionId
    {
        return $this->id;
    }

    public function prestamoId(): PrestamoId
    {
        return $this->prestamoId;
    }

    public function fechaProgramada(): DateTimeImmutable
    {
        return $this->fechaProgramada;
    }

    public function diasAntesVencimiento(): int
    {
        return $this->diasAntesVencimiento;
    }
}
