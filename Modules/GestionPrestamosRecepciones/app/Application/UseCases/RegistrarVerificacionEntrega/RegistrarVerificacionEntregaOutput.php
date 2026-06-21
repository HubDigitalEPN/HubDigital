<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\RegistrarVerificacionEntrega;

use Modules\GestionPrestamosRecepciones\Domain\Entities\VerificacionEspecimenes;

/**
 * Datos de salida tras registrar la verificación de entrega.
 */
final readonly class RegistrarVerificacionEntregaOutput
{
    public function __construct(
        public string $verificacionId,
        public string $prestamoId,
        public string $resultado,
    ) {}

    public static function fromVerificacion(VerificacionEspecimenes $verificacion): self
    {
        return new self(
            verificacionId: (string) $verificacion->id(),
            prestamoId: (string) $verificacion->prestamoId(),
            resultado: $verificacion->resultado()->value,
        );
    }
}
