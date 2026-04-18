<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Tests\Behat\Contexts\AdministracionCuratorialSolicitudesPrestamos;

use Behat\Behat\Tester\Exception\PendingException;
use Behat\Step\Given;
use Behat\Step\Then;
use Behat\Step\When;
use Modules\GestionPrestamosRecepciones\Tests\Behat\Contexts\BaseContext;

final class SeguimientoPrestamosContext extends BaseContext
{
    #[Given('/^que existe una solicitud en estado (.+)$/u')]
    public function queExisteUnaSolicitudEnEstado(string $estado): void
    {
        throw new PendingException;
    }

    #[When('el curador solicita la información de la solicitud')]
    public function elCuradorSolicitaLaInformaciónDeLaSolicitud(): void
    {
        throw new PendingException;
    }

    #[Then('/^la solicitud es retornada con estado (.+)$/u')]
    public function laSolicitudEsRetornadaConEstado(string $estado): void
    {
        throw new PendingException;
    }

    #[Given('/^que existe un préstamo en estado (.+)$/u')]
    public function queExisteUnPréstamoEnEstado(string $estado): void
    {
        throw new PendingException;
    }

    #[When('el curador solicita la información del préstamo')]
    public function elCuradorSolicitaLaInformaciónDelPréstamo(): void
    {
        throw new PendingException;
    }

    #[Then('/^el préstamo es retornado con estado (.+)$/u')]
    public function elPréstamoEsRetornadoConEstado(string $estado): void
    {
        throw new PendingException;
    }

    #[Given('/^que existe una solicitud con (.+)$/u')]
    public function queExisteUnaSolicitudCon(string $condicion): void
    {
        throw new PendingException;
    }

    #[When('el curador solicita el historial de la solicitud')]
    public function elCuradorSolicitaElHistorialDeLaSolicitud(): void
    {
        throw new PendingException;
    }

    #[Then('/^el historial es retornado (.+)$/u')]
    public function elHistorialEsRetornado(string $resultado): void
    {
        throw new PendingException;
    }

    #[Given('/^que existe un préstamo con (.+)$/u')]
    public function queExisteUnPréstamoCon(string $condicion): void
    {
        throw new PendingException;
    }

    #[When('el curador solicita el historial del préstamo')]
    public function elCuradorSolicitaElHistorialDelPréstamo(): void
    {
        throw new PendingException;
    }
}
