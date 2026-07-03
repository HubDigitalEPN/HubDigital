<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\GuardarPatenteAnual;

/**
 * Output DTO con la patente registrada.
 */
final readonly class GuardarPatenteAnualOutput
{
    public function __construct(
        public int $anio,
        public string $codigo,
    ) {}
}
