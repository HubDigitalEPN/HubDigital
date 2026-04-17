<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Tests\Behat\Contexts\TramitacionSolicitudesInvestigador;

use Behat\Behat\Tester\Exception\PendingException;
use Behat\Step\Given;
use Behat\Step\When;
use Behat\Step\Then;
use Modules\GestionPrestamosRecepciones\Tests\Behat\Contexts\BaseContext;

final class SeguimientoSolicitudesContext extends BaseContext
{
    // Dueño de: "existe una solicitud del investigador en estado :estado"
    // Dueño de: "existe un préstamo del investigador en estado :estado"
    // Dueño de: "la solicitud es retornada con estado :estado"
    // Dueño de: "el préstamo es retornado con estado :estado"

    #[Given('que existe una solicitud del investigador en estado borrador')]
    public function queExisteUnaSolicitudDelInvestigadorEnEstadoBorrador(): void
    {
        throw new PendingException();
    }

    #[When('el investigador solicita la información de la solicitud')]
    public function elInvestigadorSolicitaLaInformaciónDeLaSolicitud(): void
    {
        throw new PendingException();
    }

    #[Then('la solicitud es retornada con estado borrador')]
    public function laSolicitudEsRetornadaConEstadoBorrador(): void
    {
        throw new PendingException();
    }

    #[Given('que existe una solicitud del investigador en estado enviada')]
    public function queExisteUnaSolicitudDelInvestigadorEnEstadoEnviada(): void
    {
        throw new PendingException();
    }

    #[Then('la solicitud es retornada con estado enviada')]
    public function laSolicitudEsRetornadaConEstadoEnviada(): void
    {
        throw new PendingException();
    }

    #[Given('que existe una solicitud del investigador en estado observada')]
    public function queExisteUnaSolicitudDelInvestigadorEnEstadoObservada(): void
    {
        throw new PendingException();
    }

    #[Then('la solicitud es retornada con estado observada')]
    public function laSolicitudEsRetornadaConEstadoObservada(): void
    {
        throw new PendingException();
    }

    #[Given('que existe una solicitud del investigador en estado aprobada')]
    public function queExisteUnaSolicitudDelInvestigadorEnEstadoAprobada(): void
    {
        throw new PendingException();
    }

    #[Then('la solicitud es retornada con estado aprobada')]
    public function laSolicitudEsRetornadaConEstadoAprobada(): void
    {
        throw new PendingException();
    }

    #[Given('que existe una solicitud del investigador en estado rechazada')]
    public function queExisteUnaSolicitudDelInvestigadorEnEstadoRechazada(): void
    {
        throw new PendingException();
    }

    #[Then('la solicitud es retornada con estado rechazada')]
    public function laSolicitudEsRetornadaConEstadoRechazada(): void
    {
        throw new PendingException();
    }

    #[Given('que existe un préstamo del investigador en estado activo')]
    public function queExisteUnPréstamoDelInvestigadorEnEstadoActivo(): void
    {
        throw new PendingException();
    }

    #[When('el investigador solicita la información del préstamo')]
    public function elInvestigadorSolicitaLaInformaciónDelPréstamo(): void
    {
        throw new PendingException();
    }

    #[Then('el préstamo es retornado con estado activo')]
    public function elPréstamoEsRetornadoConEstadoActivo(): void
    {
        throw new PendingException();
    }

    #[Given('que existe un préstamo del investigador en estado prórroga solicitada')]
    public function queExisteUnPréstamoDelInvestigadorEnEstadoPrórrogaSolicitada(): void
    {
        throw new PendingException();
    }

    #[Then('el préstamo es retornado con estado prórroga solicitada')]
    public function elPréstamoEsRetornadoConEstadoPrórrogaSolicitada(): void
    {
        throw new PendingException();
    }

    #[Given('que existe un préstamo del investigador en estado vencido')]
    public function queExisteUnPréstamoDelInvestigadorEnEstadoVencido(): void
    {
        throw new PendingException();
    }

    #[Then('el préstamo es retornado con estado vencido')]
    public function elPréstamoEsRetornadoConEstadoVencido(): void
    {
        throw new PendingException();
    }

    #[Given('que existe un préstamo del investigador en estado en revisión')]
    public function queExisteUnPréstamoDelInvestigadorEnEstadoEnRevisión(): void
    {
        throw new PendingException();
    }

    #[Then('el préstamo es retornado con estado en revisión')]
    public function elPréstamoEsRetornadoConEstadoEnRevisión(): void
    {
        throw new PendingException();
    }

    #[Given('que existe un préstamo del investigador en estado cerrado')]
    public function queExisteUnPréstamoDelInvestigadorEnEstadoCerrado(): void
    {
        throw new PendingException();
    }

    #[Then('el préstamo es retornado con estado cerrado')]
    public function elPréstamoEsRetornadoConEstadoCerrado(): void
    {
        throw new PendingException();
    }
}
