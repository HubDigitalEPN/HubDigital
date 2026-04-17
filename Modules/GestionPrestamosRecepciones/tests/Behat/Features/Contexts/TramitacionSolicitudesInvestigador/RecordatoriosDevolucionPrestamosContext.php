<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Tests\Behat\Contexts\TramitacionSolicitudesInvestigador;

use Behat\Behat\Tester\Exception\PendingException;
use Behat\Step\Given;
use Behat\Step\When;
use Behat\Step\Then;
use Modules\GestionPrestamosRecepciones\Tests\Behat\Contexts\BaseContext;

final class RecordatoriosDevolucionPrestamosContext extends BaseContext
{
    #[Given('que existe un préstamo activo con condición próximo a la fecha de devolución configurada')]
    public function queExisteUnPréstamoActivoConCondiciónPróximoALaFechaDeDevoluciónConfigurada(): void
    {
        throw new PendingException();
    }

    #[When('se evalúa el plazo de devolución del préstamo')]
    public function seEvalúaElPlazoDeDevoluciónDelPréstamo(): void
    {
        throw new PendingException();
    }

    #[Then('el investigador recibe un recordatorio por correo indicando que su préstamo está próximo a vencer')]
    public function elInvestigadorRecibeUnRecordatorioPorCorreoIndicandoQueSuPréstamoEstáPróximoAVencer(): void
    {
        throw new PendingException();
    }

    #[Given('que existe un préstamo activo con condición fuera del plazo de devolución')]
    public function queExisteUnPréstamoActivoConCondiciónFueraDelPlazoDeDevolución(): void
    {
        throw new PendingException();
    }

    #[Then('el investigador recibe un recordatorio por correo indicando que su préstamo está vencido')]
    public function elInvestigadorRecibeUnRecordatorioPorCorreoIndicandoQueSuPréstamoEstáVencido(): void
    {
        throw new PendingException();
    }
}
