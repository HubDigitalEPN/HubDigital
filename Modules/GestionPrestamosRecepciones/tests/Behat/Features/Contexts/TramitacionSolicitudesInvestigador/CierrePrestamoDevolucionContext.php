<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Tests\Behat\Contexts\TramitacionSolicitudesInvestigador;

use Behat\Behat\Tester\Exception\PendingException;
use Behat\Step\Given;
use Behat\Step\When;
use Behat\Step\Then;
use Modules\GestionPrestamosRecepciones\Tests\Behat\Contexts\BaseContext;

final class CierrePrestamoDevolucionContext extends BaseContext
{
    // Dueño de: "existe un préstamo activo asociado al investigador"
    // SKIP: 'que existe un préstamo del investigador en estado en revisión'
    //       → matches "existe un préstamo del investigador en estado :estado" → owner: SeguimientoSolicitudesContext

    #[Given('que existe un préstamo activo asociado al investigador')]
    public function queExisteUnPréstamoActivoAsociadoAlInvestigador(): void
    {
        throw new PendingException();
    }

    #[When('el investigador registra el envío de devolución del préstamo')]
    public function elInvestigadorRegistraElEnvíoDeDevoluciónDelPréstamo(): void
    {
        throw new PendingException();
    }

    #[Then('el préstamo queda en estado en revisión')]
    public function elPréstamoQuedaEnEstadoEnRevisión(): void
    {
        throw new PendingException();
    }

    #[When('la devolución es verificada con resultado sin novedades')]
    public function laDevoluciónEsVerificadaConResultadoSinNovedades(): void
    {
        throw new PendingException();
    }

    #[Then('el investigador recibe una notificación por correo con el resultado sin novedades')]
    public function elInvestigadorRecibeUnaNotificaciónPorCorreoConElResultadoSinNovedades(): void
    {
        throw new PendingException();
    }

    #[When('la devolución es verificada con resultado con observación')]
    public function laDevoluciónEsVerificadaConResultadoConObservación(): void
    {
        throw new PendingException();
    }

    #[Then('el investigador recibe una notificación por correo con el resultado con observación')]
    public function elInvestigadorRecibeUnaNotificaciónPorCorreoConElResultadoConObservación(): void
    {
        throw new PendingException();
    }
}
