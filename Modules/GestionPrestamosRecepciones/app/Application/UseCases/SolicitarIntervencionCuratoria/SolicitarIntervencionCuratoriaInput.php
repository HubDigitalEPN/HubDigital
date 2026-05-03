<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\SolicitarIntervencionCuratoria;

final readonly class SolicitarIntervencionCuratoriaInput
{
    public function __construct(
        public string $solicitudId,
        public string $investigadorId,
    ) {}
}
