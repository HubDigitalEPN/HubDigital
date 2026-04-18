<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Tests\Behat\Contexts\TrazabilidadOperativaMovimientosCirculacion;

use Behat\Behat\Tester\Exception\PendingException;
use Behat\Step\Given;
use Behat\Step\Then;
use Behat\Step\When;
use Modules\InventarioGestionColeccion\Tests\Behat\Contexts\BaseContext;

final class MonitoreoTiempoExtraccionContext extends BaseContext
{
    #[Given('/^que existe una caja entomológica fuera de su posición con condición (.+)$/')]
    public function queExisteUnaCajaEntomológicaFueraDeSuPosiciónConCondición(string $condicion): void
    {
        throw new PendingException;
    }

    #[When('se verifican los tiempos de extracción')]
    public function seVerificanLosTiemposDeExtracción(): void
    {
        throw new PendingException;
    }

    #[Then('se debe registrar sin alertas y el estado permanece "En Tránsito"')]
    public function seDebeRegistrarSinAlertasYElEstadoPermanece(): void
    {
        throw new PendingException;
    }

    #[Then('se debe enviar una notificación preventiva al curador responsable')]
    public function seDebeEnviarUnaNotificaciónPreventivaAlCuradorResponsable(): void
    {
        throw new PendingException;
    }

    #[Then('se debe generar una alerta de "Tiempo de Extracción Excedido" y el estado cambia a "Extracción Prolongada"')]
    public function seDebeGenerarUnaAlertaDeTiempoDeExtraccionExcedido(): void
    {
        throw new PendingException;
    }

    #[Given('que existe una caja entomológica en estado "Extracción Prolongada"')]
    public function queExisteUnaCajaEntomológicaEnEstadoExtracciónProlongada(): void
    {
        throw new PendingException;
    }

    #[When('el curador registra la devolución de la caja a su ranura en el gabinete')]
    public function elCuradorRegistraLaDevoluciónDeLaCajaASuRanuraEnElGabinete(): void
    {
        throw new PendingException;
    }

    #[Then('se debe registrar la devolución')]
    public function seDebeRegistrarLaDevolución(): void
    {
        throw new PendingException;
    }

}
