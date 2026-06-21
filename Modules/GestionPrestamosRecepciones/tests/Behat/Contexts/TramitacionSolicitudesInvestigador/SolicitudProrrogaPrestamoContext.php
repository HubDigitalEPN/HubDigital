<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Tests\Behat\Contexts\TramitacionSolicitudesInvestigador;

use Behat\Behat\Tester\Exception\PendingException;
use Behat\Step\Given;
use Behat\Step\Then;
use Behat\Step\When;
use Modules\GestionPrestamosRecepciones\Tests\Behat\Contexts\BaseContext;

final class SolicitudProrrogaPrestamoContext extends BaseContext
{
    #[Given('que existe un préstamo activo del investigador para prórroga')]
    public function queExisteUnPréstamoActivoAsociadoAlInvestigador(): void
    {
        throw new PendingException;
    }

    #[When('el investigador registra una solicitud de prórroga con una nueva fecha y justificación')]
    public function elInvestigadorRegistraUnaSolicitudDePrórrogaConUnaNuevaFechaYJustificación(): void
    {
        throw new PendingException;
    }

    #[Then('el préstamo queda en estado prórroga solicitada')]
    public function elPréstamoQuedaEnEstadoPrórrogaSolicitada(): void
    {
        throw new PendingException;
    }

    #[When('el investigador intenta registrar una solicitud de prórroga sin justificación')]
    public function elInvestigadorIntentaRegistrarUnaSolicitudDePrórrogaSinJustificación(): void
    {
        throw new PendingException;
    }

    #[Then('el préstamo permanece en estado activo')]
    public function elPréstamoPermaneceEnEstadoActivo(): void
    {
        throw new PendingException;
    }

    #[When('el investigador intenta registrar una solicitud de prórroga')]
    public function elInvestigadorIntentaRegistrarUnaSolicitudDePrórroga(): void
    {
        throw new PendingException;
    }

    #[Then('la solicitud no es permitida')]
    public function laSolicitudNoEsPermitida(): void
    {
        throw new PendingException;
    }

    #[When('/^la prórroga es resuelta con resultado (.+)$/u')]
    public function laPrórrogaEsResueltaConResultado(string $resultado): void
    {
        throw new PendingException;
    }

    #[Then('/^el investigador recibe una notificación por correo con el resultado (.+)$/u')]
    public function elInvestigadorRecibeUnaNotificaciónPorCorreoConElResultado(string $resultado): void
    {
        throw new PendingException;
    }
}
