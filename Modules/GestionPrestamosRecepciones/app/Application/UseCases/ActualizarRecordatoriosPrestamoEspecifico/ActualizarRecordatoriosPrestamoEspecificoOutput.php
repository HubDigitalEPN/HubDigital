<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\ActualizarRecordatoriosPrestamoEspecifico;

use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\PrestamoId;

final readonly class ActualizarRecordatoriosPrestamoEspecificoOutput
{
    public function __construct(
        public string $prestamoId,
        public int $cantidadRecordatorios,
    ) {}

    public static function from(PrestamoId $prestamoId, int $cantidadRecordatorios): self
    {
        return new self(
            prestamoId: (string) $prestamoId,
            cantidadRecordatorios: $cantidadRecordatorios,
        );
    }
}
