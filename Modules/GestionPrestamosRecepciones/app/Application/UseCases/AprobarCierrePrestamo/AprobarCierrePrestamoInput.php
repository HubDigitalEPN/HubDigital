<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\AprobarCierrePrestamo;

final readonly class AprobarCierrePrestamoInput
{
    public function __construct(
        public string $prestamoId,
        public string $curadorId,
        public ?string $observacion = null,
    ) {}
}
