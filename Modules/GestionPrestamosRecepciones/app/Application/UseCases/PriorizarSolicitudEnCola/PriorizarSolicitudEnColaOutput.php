<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\PriorizarSolicitudEnCola;

/**
 * Output DTO de la priorización: prioridad asignada y posición resultante en la cola
 * de revisión curatorial.
 */
final readonly class PriorizarSolicitudEnColaOutput
{
    public function __construct(
        public string $prioridad,
        public int $posicionEnCola,
    ) {}

    public static function fromPrimitives(string $prioridad, int $posicionEnCola): self
    {
        return new self(
            prioridad: $prioridad,
            posicionEnCola: $posicionEnCola,
        );
    }
}
