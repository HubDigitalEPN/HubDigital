<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\RegistrarVerificacionEntrega;

use Modules\GestionPrestamosRecepciones\Domain\Entities\VerificacionEntregaPrestamo;

/**
 * Datos de salida tras registrar la verificación de entrega.
 */
final readonly class RegistrarVerificacionEntregaOutput
{
    public function __construct(
        public string $verificacionId,
        public string $prestamoId,
        public string $estadoEnvio,
    ) {}

    /**
     * @param VerificacionEntregaPrestamo $verificacion
     * @return self
     */
    public static function fromVerificacion(VerificacionEntregaPrestamo $verificacion): self
    {
        return new self(
            verificacionId: (string) $verificacion->id(),
            prestamoId: (string) $verificacion->prestamoId(),
            estadoEnvio: $verificacion->estadoEnvio()->value,
        );
    }
}
