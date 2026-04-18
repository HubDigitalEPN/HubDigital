<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Tests\Behat\Contexts\GestionAutonomaSeguridadFisicaInventario;

use Behat\Behat\Tester\Exception\PendingException;
use Behat\Step\Given;
use Behat\Step\Then;
use Behat\Step\When;
use Modules\InventarioGestionColeccion\Tests\Behat\Contexts\BaseContext;

final class RegistroUbicacionCajasContext extends BaseContext
{
    #[Given('que se está monitoreando el gabinete')]
    public function queSeEstáMonitoreandoElGabinete(): void
    {
        throw new PendingException;
    }

    #[Given('/^existe una caja entomológica en condición de (.+)$/')]
    public function existeUnaCajaEntomológicaEnCondiciónDe(string $condicion_previa): void
    {
        throw new PendingException;
    }

    #[When(':accion la caja entomológica')]
    public function laCajaEntomológica(string $accion): void
    {
        throw new PendingException;
    }

    #[Then('se debe registrar el evento de :accion')]
    public function seDebeRegistrarElEventoDe(string $accion): void
    {
        throw new PendingException;
    }

    #[Then('/^el estado de la caja debe cambiar a (.+)$/')]
    public function elEstadoDeLaCajaDebeCambiarA(string $estado_resultante): void
    {
        throw new PendingException;
    }

    #[Given('que el horario autorizado de movimiento está configurado')]
    public function queElHorarioAutorizadoDeMovimientoEstáConfigurado(): void
    {
        throw new PendingException;
    }

    #[Given('la hora actual está fuera del horario autorizado')]
    public function laHoraActualEstáFueraDelHorarioAutorizado(): void
    {
        throw new PendingException;
    }

    #[When('retiro una caja entomológica de su ranura')]
    public function retiroUnaCajaEntomológicaDeSuRanura(): void
    {
        throw new PendingException;
    }

    #[Then('se debe generar una alerta de "Movimiento No Autorizado"')]
    public function seDebeGenerarUnaAlertaDeMovimientoNoAutorizado(): void
    {
        throw new PendingException;
    }

    #[Then('el curador responsable debe recibir una notificación inmediata')]
    public function elCuradorResponsableDebeRecibirUnaNotificaciónInmediata(): void
    {
        throw new PendingException;
    }
}
