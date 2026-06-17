<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\PriorizarSolicitudEnCola;

/**
 * Input DTO para que el curador clasifique la prioridad de una solicitud en la cola
 * de revisión curatorial.
 */
final readonly class PriorizarSolicitudEnColaInput
{
    public function __construct(
        public string $solicitudId,
        public string $prioridad,
    ) {}
}
