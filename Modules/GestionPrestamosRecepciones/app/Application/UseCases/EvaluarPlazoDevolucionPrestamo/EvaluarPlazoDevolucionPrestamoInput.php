<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\EvaluarPlazoDevolucionPrestamo;

final readonly class EvaluarPlazoDevolucionPrestamoInput
{
    public function __construct(
        public string $prestamoId,
    ) {}
}
