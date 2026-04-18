<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Tests\Behat\Contexts\TrazabilidadOperativaMovimientosCirculacion;

use Behat\Behat\Tester\Exception\PendingException;
use Behat\Step\Given;
use Behat\Step\Then;
use Behat\Step\When;
use Modules\InventarioGestionColeccion\Tests\Behat\Contexts\BaseContext;

final class ReubicacionDigitalGuiadaContext extends BaseContext
{
    #[Given('que existe un espécimen ubicado en una caja de origen')]
    public function queExisteUnEspécimenUbicadoEnUnaCajaDeOrigen(): void
    {
        throw new PendingException;
    }

    #[Given('existe una caja de destino con espacio disponible y familia taxonómica compatible')]
    public function existeUnaCajaDeDestinoConEspacioDisponibleYFamiliaTaxonómicaCompatible(): void
    {
        throw new PendingException;
    }

    #[When('inicio el proceso de reubicación digital guiada para el espécimen')]
    public function inicioElProcesoDeReubicaciónDigitalGuiadaParaElEspécimen(): void
    {
        throw new PendingException;
    }

    #[When('selecciono la caja de destino')]
    public function seleccionoLaCajaDeDestino(): void
    {
        throw new PendingException;
    }

    #[Then('se debe actualizar la ubicación del espécimen a la caja de destino')]
    public function seDebeActualizarLaUbicaciónDelEspécimenALaCajaDeDestino(): void
    {
        throw new PendingException;
    }

    #[Then('se debe guardar un registro en el historial de custodia con los contenedores de origen y destino')]
    public function seDebeGuardarUnRegistroEnElHistorialDeCustodiaConLosContenedoresDeOrigenYDestino(): void
    {
        throw new PendingException;
    }

    #[Then('el estado del espécimen debe reflejar "Reubicado"')]
    public function elEstadoDelEspécimenDebeReflejarReubicado(): void
    {
        throw new PendingException;
    }

    #[Given('la caja de destino ha alcanzado su capacidad máxima')]
    public function laCajaDeDestinoHaAlcanzadoSuCapacidadMáxima(): void
    {
        throw new PendingException;
    }

    #[When('intento reubicar el espécimen en la caja de destino')]
    public function intentoReubicarElEspécimenEnLaCajaDeDestino(): void
    {
        throw new PendingException;
    }

    #[Then('se debe mostrar un mensaje de error "Contenedor destino lleno"')]
    public function seDebeMostrarUnMensajeDeErrorContenedorDestinoLleno(): void
    {
        throw new PendingException;
    }

    #[Then('el espécimen permanece en su contenedor de origen')]
    public function elEspécimenPermaneceEnSuContenedorDeOrigen(): void
    {
        throw new PendingException;
    }

    #[Given('que existe un espécimen de una familia taxonómica específica en una caja de origen')]
    public function queExisteUnEspécimenDeUnaFamiliaTaxonómicaEspecíficaEnUnaCajaDeOrigen(): void
    {
        throw new PendingException;
    }

    #[Given('la caja de destino está configurada para una familia taxonómica diferente')]
    public function laCajaDeDestinoEstáConfiguradaParaUnaFamiliaTaxonómicaDiferente(): void
    {
        throw new PendingException;
    }

    #[Then('se debe mostrar un mensaje de error "Incompatibilidad Taxonómica"')]
    public function seDebeMostrarUnMensajeDeErrorIncompatibilidadTaxonómica(): void
    {
        throw new PendingException;
    }

    #[Given('/^que existe un espécimen con (.+)$/')]
    public function queExisteUnEspécimenCon(string $condicion): void
    {
        throw new PendingException;
    }

    #[When('el curador solicita el historial de custodia del espécimen')]
    public function elCuradorSolicitaElHistorialDeCustodiaDelEspécimen(): void
    {
        throw new PendingException;
    }

    #[Then('/^el historial es retornado (.+)$/')]
    public function elHistorialEsRetornado(string $resultado): void
    {
        throw new PendingException;
    }
}
