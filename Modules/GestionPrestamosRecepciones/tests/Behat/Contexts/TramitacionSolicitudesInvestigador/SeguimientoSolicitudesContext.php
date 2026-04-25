<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Tests\Behat\Contexts\TramitacionSolicitudesInvestigador;

use Behat\Behat\Tester\Exception\PendingException;
use Behat\Step\Given;
use Behat\Step\When;
use Modules\GestionPrestamosRecepciones\Tests\Behat\Contexts\BaseContext;

final class SeguimientoSolicitudesContext extends BaseContext
{
    #[Given('/^que existe una solicitud del investigador en estado (.+)$/u')]
    public function queExisteUnaSolicitudDelInvestigadorEnEstado(string $estado): void
    {
        throw new PendingException;
    }

    #[When('el investigador solicita la información de la solicitud')]
    public function elInvestigadorSolicitaLaInformaciónDeLaSolicitud(): void
    {
        throw new PendingException;
    }

    #[Given('/^que existe un préstamo del investigador en estado (.+)$/u')]
    public function queExisteUnPréstamoDelInvestigadorEnEstado(string $estado): void
    {
        throw new PendingException;
    }

    #[When('el investigador solicita la información del préstamo')]
    public function elInvestigadorSolicitaLaInformaciónDelPréstamo(): void
    {
        throw new PendingException;
    }
}
