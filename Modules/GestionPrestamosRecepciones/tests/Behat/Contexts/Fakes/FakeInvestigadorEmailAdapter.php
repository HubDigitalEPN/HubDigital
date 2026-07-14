<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Tests\Behat\Contexts\Fakes;

use Modules\GestionPrestamosRecepciones\Application\Ports\InvestigadorEmailPort;

/**
 * Doble de prueba del puerto que resuelve el correo del investigador, evitando
 * depender de la tabla de usuarios real durante los escenarios Behat.
 */
final class FakeInvestigadorEmailAdapter implements InvestigadorEmailPort
{
    public function obtenerEmail(string $investigadorId): string
    {
        return "{$investigadorId}@investigadores.test";
    }
}
