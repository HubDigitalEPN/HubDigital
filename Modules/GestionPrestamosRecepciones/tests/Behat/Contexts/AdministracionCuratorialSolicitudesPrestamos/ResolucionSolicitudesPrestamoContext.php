<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Tests\Behat\Contexts\AdministracionCuratorialSolicitudesPrestamos;

use Behat\Behat\Tester\Exception\PendingException;
use Behat\Step\Then;
use Behat\Step\When;
use Modules\GestionPrestamosRecepciones\Tests\Behat\Contexts\BaseContext;

final class ResolucionSolicitudesPrestamoContext extends BaseContext
{
    #[When('el curador registra la aprobación de la solicitud')]
    public function elCuradorRegistraLaAprobaciónDeLaSolicitud(): void
    {
        throw new PendingException;
    }

    #[Then('la solicitud queda en estado aprobada')]
    public function laSolicitudQuedaEnEstadoAprobada(): void
    {
        throw new PendingException;
    }

    #[Then('se crea un préstamo en estado activo')]
    public function seCreaUnPréstamoEnEstadoActivo(): void
    {
        throw new PendingException;
    }

    #[When('el curador registra la aprobación de la solicitud con condiciones')]
    public function elCuradorRegistraLaAprobaciónDeLaSolicitudConCondiciones(): void
    {
        throw new PendingException;
    }

    #[Then('la solicitud queda asociada a las condiciones establecidas')]
    public function laSolicitudQuedaAsociadaALasCondicionesEstablecidas(): void
    {
        throw new PendingException;
    }

    #[When('el curador registra la observación de la solicitud con un comentario')]
    public function elCuradorRegistraLaObservaciónDeLaSolicitudConUnComentario(): void
    {
        throw new PendingException;
    }

    #[Then('la solicitud queda en estado observada')]
    public function laSolicitudQuedaEnEstadoObservada(): void
    {
        throw new PendingException;
    }

    #[When('el curador registra el rechazo de la solicitud con un comentario')]
    public function elCuradorRegistraElRechazoDeLaSolicitudConUnComentario(): void
    {
        throw new PendingException;
    }

    #[Then('la solicitud queda en estado rechazada')]
    public function laSolicitudQuedaEnEstadoRechazada(): void
    {
        throw new PendingException;
    }

    #[When('el curador intenta registrar el rechazo de la solicitud sin comentario')]
    public function elCuradorIntentaRegistrarElRechazoDeLaSolicitudSinComentario(): void
    {
        throw new PendingException;
    }

    #[Then('la solicitud permanece en estado enviada')]
    public function laSolicitudPermaneceEnEstadoEnviada(): void
    {
        throw new PendingException;
    }
}
