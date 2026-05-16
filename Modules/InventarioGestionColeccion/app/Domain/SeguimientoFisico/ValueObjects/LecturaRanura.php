<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects;

final readonly class LecturaRanura
{
    private function __construct(
        private int $numeroRanura,
        private ?CodigoRfid $rfidDetectado,
        private bool $esIncongruente,
    ) {
        if ($numeroRanura < 1) {
            throw new \InvalidArgumentException("El número de ranura debe ser mayor a 0.");
        }
    }

    public static function crear(int $numeroRanura, ?CodigoRfid $rfidDetectado, bool $esIncongruente = false): self
    {
        return new self($numeroRanura, $rfidDetectado, $esIncongruente);
    }

    public function numeroRanura(): int
    {
        return $this->numeroRanura;
    }

    public function rfidDetectado(): ?CodigoRfid
    {
        return $this->rfidDetectado;
    }

    public function esIncongruente(): bool
    {
        return $this->esIncongruente;
    }

    public function ranuraVacia(): bool
    {
        return $this->rfidDetectado === null;
    }
}
