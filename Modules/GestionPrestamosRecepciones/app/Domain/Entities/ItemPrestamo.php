<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\Entities;

use InvalidArgumentException;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\ItemPrestamoId;

final class ItemPrestamo
{
    private function __construct(
        private readonly ItemPrestamoId $id,
        private readonly string $especimenCodigoExterno,
        private readonly int $cantidadSolicitada,
        private readonly ?array $especimenSnapshot,
        private ?string $condicionesEspecificas,
    ) {}

    // ── Named constructors ────────────────────────────────────────────────────

    public static function crear(
        ItemPrestamoId $id,
        string $especimenCodigoExterno,
        int $cantidadSolicitada,
        ?array $especimenSnapshot = null,
    ): self {
        if (trim($especimenCodigoExterno) === '') {
            throw new InvalidArgumentException('El código externo del especímen no puede estar vacío.');
        }

        if ($cantidadSolicitada < 1) {
            throw new InvalidArgumentException(
                "La cantidad solicitada debe ser al menos 1, se recibió: {$cantidadSolicitada}."
            );
        }

        return new self(
            id: $id,
            especimenCodigoExterno: trim($especimenCodigoExterno),
            cantidadSolicitada: $cantidadSolicitada,
            especimenSnapshot: $especimenSnapshot,
            condicionesEspecificas: null,
        );
    }

    /** Reconstitución desde persistencia — no valida invariantes. */
    public static function reconstituir(
        ItemPrestamoId $id,
        string $especimenCodigoExterno,
        int $cantidadSolicitada,
        ?array $especimenSnapshot,
        ?string $condicionesEspecificas,
    ): self {
        return new self(
            id: $id,
            especimenCodigoExterno: $especimenCodigoExterno,
            cantidadSolicitada: $cantidadSolicitada,
            especimenSnapshot: $especimenSnapshot,
            condicionesEspecificas: $condicionesEspecificas,
        );
    }

    // ── Métodos de negocio ────────────────────────────────────────────────────

    /** El curador fija condiciones específicas al aprobar la solicitud. */
    public function establecerCondiciones(string $condiciones): void
    {
        if (trim($condiciones) === '') {
            throw new InvalidArgumentException('Las condiciones específicas no pueden estar vacías.');
        }

        $this->condicionesEspecificas = trim($condiciones);
    }

    // ── Accessors ─────────────────────────────────────────────────────────────

    public function id(): ItemPrestamoId
    {
        return $this->id;
    }

    public function especimenCodigoExterno(): string
    {
        return $this->especimenCodigoExterno;
    }

    public function cantidadSolicitada(): int
    {
        return $this->cantidadSolicitada;
    }

    public function especimenSnapshot(): ?array
    {
        return $this->especimenSnapshot;
    }

    public function condicionesEspecificas(): ?string
    {
        return $this->condicionesEspecificas;
    }
}
