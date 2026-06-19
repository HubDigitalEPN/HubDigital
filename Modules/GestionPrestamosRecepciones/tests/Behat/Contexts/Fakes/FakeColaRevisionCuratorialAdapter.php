<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Tests\Behat\Contexts\Fakes;

use Modules\GestionPrestamosRecepciones\Application\Ports\ColaRevisionCuratorialPort;

final class FakeColaRevisionCuratorialAdapter implements ColaRevisionCuratorialPort
{
    public function posicionEnLaCola(string $solicitudId): int
    {
        // Una solicitud clasificada como prioritaria se posiciona al inicio de la cola.
        return 1;
    }
}
