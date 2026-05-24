<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\Ports;

use DateTimeImmutable;

final readonly class EventoHistorialDto
{
    public function __construct(
        public string $tipo,
        public DateTimeImmutable $ocurridoEn,
        public array $datos = [],
    ) {}
}
