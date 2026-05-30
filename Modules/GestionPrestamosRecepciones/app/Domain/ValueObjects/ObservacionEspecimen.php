<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\ValueObjects;

final readonly class ObservacionEspecimen
{
    public function __construct(
        public string $itemPrestamoId,
        public string $descripcion,
    ) {}
}
