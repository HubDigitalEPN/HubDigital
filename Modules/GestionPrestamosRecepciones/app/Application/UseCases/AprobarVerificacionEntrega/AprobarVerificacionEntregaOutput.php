<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\AprobarVerificacionEntrega;

use Modules\GestionPrestamosRecepciones\Domain\Entities\Prestamo;

final readonly class AprobarVerificacionEntregaOutput
{
    public function __construct(
        public string $prestamoId,
        public string $estado,
    ) {}

    public static function fromPrestamo(Prestamo $prestamo): self
    {
        return new self(
            prestamoId: (string) $prestamo->id(),
            estado: $prestamo->estado()->value,
        );
    }
}
