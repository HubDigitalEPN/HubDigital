<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Tests\Behat\Contexts\AdministracionCuratorialSolicitudesPrestamos;

use Behat\Behat\Tester\Exception\PendingException;
use Behat\Step\Then;
use Behat\Step\When;
use Modules\GestionPrestamosRecepciones\Tests\Behat\Contexts\BaseContext;

final class GestionProrrogasPrestamoContext extends BaseContext
{
    #[When('el curador registra la aprobación de la prórroga con una nueva fecha de vencimiento')]
    public function elCuradorRegistraLaAprobaciónDeLaPrórrogaConUnaNuevaFechaDeVencimiento(): void
    {
        throw new PendingException;
    }

    #[Then('el préstamo queda en estado activo con la nueva fecha de vencimiento')]
    public function elPréstamoQuedaEnEstadoActivoConLaNuevaFechaDeVencimiento(): void
    {
        throw new PendingException;
    }

    #[When('el curador registra el rechazo de la prórroga con un comentario')]
    public function elCuradorRegistraElRechazoDeLaPrórrogaConUnComentario(): void
    {
        throw new PendingException;
    }

    #[Then('el préstamo queda en estado activo con la fecha de vencimiento original')]
    public function elPréstamoQuedaEnEstadoActivoConLaFechaDeVencimientoOriginal(): void
    {
        throw new PendingException;
    }

    #[When('el curador intenta registrar el rechazo de la prórroga sin comentario')]
    public function elCuradorIntentaRegistrarElRechazoDeLaPrórrogaSinComentario(): void
    {
        throw new PendingException;
    }

    #[Then('el préstamo permanece en estado prórroga solicitada')]
    public function elPréstamoPermaneceEnEstadoPrórrogaSolicitada(): void
    {
        throw new PendingException;
    }
}
