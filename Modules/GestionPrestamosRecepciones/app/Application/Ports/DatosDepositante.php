<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\Ports;

/**
 * Datos profesionales de un depositante, resueltos desde el contexto de usuarios,
 * para encabezar el Acta recepción-depósito (destinatario del oficio).
 */
final readonly class DatosDepositante
{
    public function __construct(
        public string $nombre,
        public ?string $cargo,
        public ?string $institucion,
        /** Correo de contacto; lo usa la colección para la ficha de la entidad depositante. */
        public ?string $email = null,
    ) {}
}
