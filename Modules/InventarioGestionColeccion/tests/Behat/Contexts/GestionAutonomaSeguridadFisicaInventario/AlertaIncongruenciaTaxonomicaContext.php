<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Tests\Behat\Contexts\GestionAutonomaSeguridadFisicaInventario;

use Behat\Behat\Tester\Exception\PendingException;
use Behat\Step\Given;
use Behat\Step\Then;
use Behat\Step\When;
use Modules\InventarioGestionColeccion\Tests\Behat\Contexts\BaseContext;

final class AlertaIncongruenciaTaxonomicaContext extends BaseContext
{
    #[Given('que existe un gabinete configurado para una familia taxonómica')]
    public function queExisteUnGabineteConfiguradoParaUnaFamiliaTaxonómica(): void
    {
        throw new PendingException;
    }

    #[Given('existe una caja entomológica con especímenes de familia :coincidencia')]
    public function existeUnaCajaEntomológicaConEspecímenesDeFamilia(string $coincidencia): void
    {
        throw new PendingException;
    }

    #[When('inserto la caja en una ranura vacía del gabinete')]
    public function insertoLaCajaEnUnaRanuraVacíaDelGabinete(): void
    {
        throw new PendingException;
    }

    #[Then(':resultado')]
    public function resultado(string $resultado): void
    {
        throw new PendingException;
    }

    #[Given('existe una caja entomológica sin familia taxonómica asignada')]
    public function existeUnaCajaEntomológicaSinFamiliaTaxonómicaAsignada(): void
    {
        throw new PendingException;
    }

    #[Then('se debe generar una alerta de "Familia No Asignada"')]
    public function seDebeGenerarUnaAlertaDeFamiliaNoAsignada(): void
    {
        throw new PendingException;
    }

    #[Then('el estado de la caja debe marcarse como "Pendiente de Clasificación"')]
    public function elEstadoDeLaCajaDebeMarcarseComoPendienteDeClasificación(): void
    {
        throw new PendingException;
    }
}
