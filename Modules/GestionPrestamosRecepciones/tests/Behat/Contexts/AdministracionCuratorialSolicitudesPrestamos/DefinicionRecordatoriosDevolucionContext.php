<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Tests\Behat\Contexts\AdministracionCuratorialSolicitudesPrestamos;

use Behat\Behat\Tester\Exception\PendingException;
use Behat\Step\Given;
use Behat\Step\Then;
use Behat\Step\When;
use Modules\GestionPrestamosRecepciones\Tests\Behat\Contexts\BaseContext;

final class DefinicionRecordatoriosDevolucionContext extends BaseContext
{
    #[Given('que no existe una configuración global de recordatorios definida')]
    public function queNoExisteUnaConfiguraciónGlobalDeRecordatoriosDefinida(): void
    {
        throw new PendingException;
    }

    #[When('el curador registra la configuración global de recordatorios')]
    public function elCuradorRegistraLaConfiguraciónGlobalDeRecordatorios(): void
    {
        throw new PendingException;
    }

    #[Then('todos los préstamos quedan sujetos a la configuración definida')]
    public function todosLosPréstamosQuedanSujetosALaConfiguraciónDefinida(): void
    {
        throw new PendingException;
    }

    #[Given('que existe una configuración global de recordatorios previamente definida')]
    public function queExisteUnaConfiguraciónGlobalDeRecordatoriosPreviamenteDefinida(): void
    {
        throw new PendingException;
    }

    #[When('el curador actualiza la configuración global de recordatorios')]
    public function elCuradorActualizaLaConfiguraciónGlobalDeRecordatorios(): void
    {
        throw new PendingException;
    }

    #[Then('todos los préstamos quedan sujetos a la configuración actualizada')]
    public function todosLosPréstamosQuedanSujetosALaConfiguraciónActualizada(): void
    {
        throw new PendingException;
    }

    #[Given('que existe un préstamo activo con recordatorios configurados')]
    public function queExisteUnPréstamoActivoConRecordatoriosConfigurados(): void
    {
        throw new PendingException;
    }

    #[When('el curador actualiza la configuración de recordatorios del préstamo')]
    public function elCuradorActualizaLaConfiguraciónDeRecordatoriosDelPréstamo(): void
    {
        throw new PendingException;
    }

    #[Then('el préstamo queda configurado con los recordatorios actualizados')]
    public function elPréstamoQuedaConfiguradoConLosRecordatoriosActualizados(): void
    {
        throw new PendingException;
    }

    #[Given('existe una configuración global de recordatorios definida')]
    public function existeUnaConfiguraciónGlobalDeRecordatoriosDefinida(): void
    {
        throw new PendingException;
    }

    #[Then('se generan los recordatorios de devolución según la configuración global')]
    public function seGeneranLosRecordatoriosDeDevoluciónSegúnLaConfiguraciónGlobal(): void
    {
        throw new PendingException;
    }
}
