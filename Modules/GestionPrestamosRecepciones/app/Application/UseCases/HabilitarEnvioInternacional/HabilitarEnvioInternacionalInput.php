<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\HabilitarEnvioInternacional;

final readonly class HabilitarEnvioInternacionalInput
{
    public function __construct(
        public string $actaId,
        public string $curadorId,
        public string $documentoRuta,
    ) {}
}
