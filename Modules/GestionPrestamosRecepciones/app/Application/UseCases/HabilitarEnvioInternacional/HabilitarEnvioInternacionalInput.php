<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\HabilitarEnvioInternacional;

/**
 * Datos de entrada para habilitar el envío internacional.
 */
final readonly class HabilitarEnvioInternacionalInput
{
    public function __construct(
        public string $actaId,
        public string $curadorId,
        public string $documentoRuta,
    ) {}
}
