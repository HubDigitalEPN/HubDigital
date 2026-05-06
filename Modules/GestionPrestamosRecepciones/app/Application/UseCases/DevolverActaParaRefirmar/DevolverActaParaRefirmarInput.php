<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\DevolverActaParaRefirmar;

final readonly class DevolverActaParaRefirmarInput
{
    public function __construct(
        public string $actaId,
        public string $investigadorId,
        public string $motivo,
    ) {}
}
