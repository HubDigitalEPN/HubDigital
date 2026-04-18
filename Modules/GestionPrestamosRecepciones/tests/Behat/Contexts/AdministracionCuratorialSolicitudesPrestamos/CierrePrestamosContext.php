<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Tests\Behat\Contexts\AdministracionCuratorialSolicitudesPrestamos;

use Behat\Behat\Tester\Exception\PendingException;
use Behat\Step\Given;
use Behat\Step\Then;
use Behat\Step\When;
use Modules\GestionPrestamosRecepciones\Tests\Behat\Contexts\BaseContext;

final class CierrePrestamosContext extends BaseContext
{
    #[Given('que existe un préstamo en estado pendiente de verificación')]
    public function queExisteUnPréstamoEnEstadoPendienteDeVerificación(): void
    {
        throw new PendingException;
    }

    #[When('el curador registra la verificación de devolución con resultado :resultado')]
    public function elCuradorRegistraLaVerificaciónDeDevoluciónConResultado(string $resultado): void
    {
        throw new PendingException;
    }

    #[Then('el préstamo queda en estado :estado')]
    public function elPréstamoQuedaEnEstado(string $estado): void
    {
        throw new PendingException;
    }

    #[Then('el espécimen queda en condición :condicion_especimen')]
    public function elEspécimenQuedaEnCondición(string $condicion_especimen): void
    {
        throw new PendingException;
    }
}
