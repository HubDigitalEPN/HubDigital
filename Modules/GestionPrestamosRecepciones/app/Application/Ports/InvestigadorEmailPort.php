<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\Ports;

interface InvestigadorEmailPort
{
    public function obtenerEmail(string $investigadorId): string;
}
